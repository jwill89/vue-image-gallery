<?php

// Required Autoloader
require_once('../vendor/autoload.php');

use DI\Container;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteCollectorProxy;
use Routes\Internal\DuplicatesController;
use Routes\Internal\ImageController;
use Routes\Internal\VideoController;
use Routes\Internal\TagController;
use Gallery\Core\Configuration;
use Gallery\Core\Logger;
use Gallery\Core\RateLimiter;

// Create Container using PHP-DI
$container = new Container();

// Register Container
AppFactory::setContainer($container);

// Set up the App and Log
$app = AppFactory::create();

// Set Base Path
$app->setBasePath("/api");

// Setup Middleware
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

// Setup Error Middleware (disable detailed errors in production)
$error_middleware = $app->addErrorMiddleware(false, true, true);

// ============================================================
// Reusable Auth Token Verification
// ============================================================

/**
 * Verify a Bearer token from the Authorization header.
 * Returns true if valid, false otherwise.
 */
function verifyAuthToken(string $authHeader): bool
{
    $token = str_starts_with($authHeader, 'Bearer ') ? substr($authHeader, 7) : '';

    if (empty($token)) {
        return false;
    }

    $db = \Gallery\Core\DatabaseConnection::getInstance();
    $stmt = $db->prepare('SELECT 1 FROM auth_tokens WHERE token = :token AND created_at >= :min_time');
    $stmt->execute([':token' => $token, ':min_time' => time() - 86400]);

    return (bool) $stmt->fetchColumn();
}

/**
 * Create an unauthorized JSON response.
 */
function unauthorizedResponse(): ResponseInterface
{
    $response = new \Slim\Psr7\Response();
    $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
    return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
}

// ============================================================
// Auth Middleware for State-Changing Operations
// Protects POST, PUT, PATCH, DELETE on all routes except /auth/login
// ============================================================
$authMiddleware = function (ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
    $method = strtoupper($request->getMethod());
    $path = $request->getUri()->getPath();

    // Skip auth for login endpoint and OPTIONS preflight
    if ($method === 'OPTIONS' || str_ends_with($path, '/auth/login') || str_ends_with($path, '/auth/login/')) {
        return $handler->handle($request);
    }

    // Only require auth for state-changing methods
    if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        if (!verifyAuthToken($request->getHeaderLine('Authorization'))) {
            return unauthorizedResponse();
        }
    }

    return $handler->handle($request);
};

// Rate Limiting Middleware
$app->add(function (ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
    $rateLimiter = new RateLimiter(120, 60); // 120 requests per 60 seconds
    $ip = $request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0';
    $result = $rateLimiter->check($ip);

    if (!$result['allowed']) {
        Logger::getInstance()->warning('Rate limit exceeded', ['ip' => $ip, 'retry_after' => $result['retry_after']]);
        $response = new \Slim\Psr7\Response();
        $response->getBody()->write(json_encode(['error' => 'RateLimitExceeded', 'retry_after' => $result['retry_after']]));
        return $response
            ->withStatus(429)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Retry-After', (string) $result['retry_after'])
            ->withHeader('X-RateLimit-Remaining', '0');
    }

    $response = $handler->handle($request);
    return $response->withHeader('X-RateLimit-Remaining', (string) $result['remaining']);
});

// CSRF Origin Check Middleware (reject cross-origin state-changing requests)
$app->add(function (ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
    $method = strtoupper($request->getMethod());

    if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        $origin = $request->getHeaderLine('Origin');
        $referer = $request->getHeaderLine('Referer');
        $allowedOrigins = Configuration::getAllowedOrigins();

        // Allow requests with no Origin (same-origin, curl, etc.)
        if (!empty($origin) && !in_array($origin, $allowedOrigins, true)) {
            $response = new \Slim\Psr7\Response();
            $response->getBody()->write(json_encode(['error' => 'ForbiddenOrigin'], JSON_THROW_ON_ERROR));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        // If no Origin header, check Referer as fallback
        if (empty($origin) && !empty($referer)) {
            $refererOrigin = parse_url($referer, PHP_URL_SCHEME) . '://' . parse_url($referer, PHP_URL_HOST);
            $port = parse_url($referer, PHP_URL_PORT);
            if ($port) {
                $refererOrigin .= ':' . $port;
            }
            if (!in_array($refererOrigin, $allowedOrigins, true)) {
                $response = new \Slim\Psr7\Response();
                $response->getBody()->write(json_encode(['error' => 'ForbiddenOrigin'], JSON_THROW_ON_ERROR));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }
        }
    }

    return $handler->handle($request);
});

// Setup Allowables and Response Origins
$app->add(function (ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
    // Handle CORS preflight OPTIONS requests before routing
    if (strtoupper($request->getMethod()) === 'OPTIONS') {
        $response = new \Slim\Psr7\Response();
    } else {
        $response = $handler->handle($request);
    }

    // Determine allowed origin
    $origin = $request->getHeaderLine('Origin');
    $allowed_origins = Configuration::getAllowedOrigins();

    // Add security headers always
    $response = $response
        ->withHeader('X-Frame-Options', 'SAMEORIGIN')
        ->withHeader('X-Content-Type-Options', 'nosniff');

    // Only add CORS headers for cross-origin requests with a known origin
    if (!empty($origin) && in_array($origin, $allowed_origins, true)) {
        $response = $response
            ->withHeader('Access-Control-Allow-Credentials', 'true')
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
            ->withHeader('Access-Control-Max-Age', '86400');
    }

    return $response;
});

// ============================================================
// CORS Preflight OPTIONS Handler (catch-all)
// ============================================================
$app->options('/{routes:.+}', function (ServerRequestInterface $request, ResponseInterface $response) {
    return $response->withStatus(204);
});

// ============================================================
// Image Controllers
// ============================================================
$app->group('/images', function (RouteCollectorProxy $group) {
    $group->get('/page/{page}[/[{items_per_page}[/]]]', ImageController::class . ':getItemsForPage');
    $group->get('/with-tags/{tag_list}/{page}[/[{items_per_page}[/]]]', ImageController::class . ':getItemsWithTags');
    $group->get('/total[/]', ImageController::class . ':getTotal');
    $group->get('/[{image_id}[/]]', ImageController::class . ':getItem');
});

// ============================================================
// Video Controllers
// ============================================================
$app->group('/videos', function (RouteCollectorProxy $group) {
    $group->get('/page/{page}[/[{items_per_page}[/]]]', VideoController::class . ':getItemsForPage');
    $group->get('/with-tags/{tag_list}/{page}[/[{items_per_page}[/]]]', VideoController::class . ':getItemsWithTags');
    $group->get('/total[/]', VideoController::class . ':getTotal');
    $group->get('/[{video_id}[/]]', VideoController::class . ':getItem');
});

// ============================================================
// Tag Controllers (state-changing operations protected by auth middleware)
// ============================================================
$app->group('/tags', function (RouteCollectorProxy $group) {
    $group->get('/all[/]', TagController::class . ':getAllTags');
    $group->get('/display[/]', TagController::class . ':getTagListForDisplay');
    $group->get('/tag/{tag_id}[/]', TagController::class . ':getTag');
    $group->get('/for/image/{image_id}[/]', TagController::class . ':getTagsForImage');
    $group->get('/for/video/{video_id}[/]', TagController::class . ':getTagsForVideo');
    // Protected: state-changing tag operations
    $group->post('/add[/]', TagController::class . ':addTag');
    $group->put('/edit/{tag_id}[/]', TagController::class . ':editTag');
    $group->patch('/image/add[/]', TagController::class . ':addTagsToImage');
    $group->patch('/image/remove[/]', TagController::class . ':removeTagFromImage');
    $group->patch('/video/add[/]', TagController::class . ':addTagsToVideo');
    $group->patch('/video/remove[/]', TagController::class . ':removeTagFromVideo');
    $group->post('/migrate[/]', TagController::class . ':migrateTag');
    $group->delete('/delete[/]', TagController::class . ':deleteTag');
})->add($authMiddleware);

// ============================================================
// Authentication Endpoint
// ============================================================
$app->post('/auth/login[/]', function (ServerRequestInterface $request, ResponseInterface $response) {
    $params = json_decode((string)$request->getBody(), true) ?? [];
    $password = $params['password'] ?? '';

    if ($password === Configuration::getAdminPassword()) {
        $token = bin2hex(random_bytes(32));
        $db = \Gallery\Core\DatabaseConnection::getInstance();

        // Clean expired tokens (older than 24 hours)
        $db->exec('DELETE FROM auth_tokens WHERE created_at < ' . (time() - 86400));

        $stmt = $db->prepare('INSERT INTO auth_tokens (token, created_at) VALUES (:token, :time)');
        $stmt->execute([':token' => $token, ':time' => time()]);

        Logger::getInstance()->info('Admin login successful', ['ip' => $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown']);
        $response->getBody()->write(json_encode(['token' => $token]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }

    Logger::getInstance()->warning('Admin login failed', ['ip' => $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown']);
    $response->getBody()->write(json_encode(['error' => 'InvalidPassword']));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
});

// ============================================================
// Duplicates Controllers (protected by auth middleware)
// ============================================================
$app->group('/duplicates', function (RouteCollectorProxy $group) {
    $group->get('/report[/]', DuplicatesController::class . ':getLatestReport');
    $group->post('/scan[/]', DuplicatesController::class . ':runScan');
    $group->delete('/images[/]', DuplicatesController::class . ':deleteImages');
})->add($authMiddleware);

// Run the app
$app->run();
