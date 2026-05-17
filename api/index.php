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
use Routes\Internal\PageController;
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
        $allowedOrigins = Configuration::ALLOWED_ORIGINS;

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
    $response = $handler->handle($request);

    // Determine allowed origin (restrict to same origin in production)
    $origin = $request->getHeaderLine('Origin');
    $allowed_origins = Configuration::ALLOWED_ORIGINS;

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
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE');
    }

    return $response;
});

// Image Controllers
$app->group('/images', function (RouteCollectorProxy $group) {
    $group->get('/page/{page}[/[{items_per_page}[/]]]', ImageController::class . ':getImagesForPage');
    $group->get('/with-tags/{tag_list}/{page}[/[{items_per_page}[/]]]', ImageController::class . ':getImagesWithTags');
    $group->get('/total[/]', ImageController::class . ':getTotalImages');
    $group->get('/[{image_id}[/]]', ImageController::class . ':getImage');
});

// Video Controllers
$app->group('/videos', function (RouteCollectorProxy $group) {
    $group->get('/page/{page}[/[{items_per_page}[/]]]', VideoController::class . ':getVideosForPage');
    $group->get('/with-tags/{tag_list}/{page}[/[{items_per_page}[/]]]', VideoController::class . ':getVideosWithTags');
    $group->get('/total[/]', VideoController::class . ':getTotalVideos');
    $group->get('/[{video_id}[/]]', VideoController::class . ':getVideo');
});

// Tag Controllers
$app->group('/tags', function (RouteCollectorProxy $group) {
    $group->get('/all[/]', TagController::class . ':getAllTags');
    $group->get('/display[/]', TagController::class . ':getTagListForDisplay');
    $group->post('/add[/]', TagController::class . ':addTag');
    $group->put('/edit/{tag_id}[/]', TagController::class . ':editTag');
    $group->get('/tag/{tag_id}[/]', TagController::class . ':getTag');
    $group->get('/for/image/{image_id}[/]', TagController::class . ':getTagsForImage');
    $group->get('/for/video/{video_id}[/]', TagController::class . ':getTagsForVideo');
    $group->patch('/image/add[/]', TagController::class . ':addTagsToImage');
    $group->patch('/image/remove[/]', TagController::class . ':removeTagFromImage');
    $group->patch('/video/add[/]', TagController::class . ':addTagsToVideo');
    $group->patch('/video/remove[/]', TagController::class . ':removeTagFromVideo');
});

// Page Controllers
$app->group('/pages', function (RouteCollectorProxy $group) {
    $group->get('/images[/[{items_per_page}[/]]]', PageController::class . ':getTotalImagePages');
    $group->get('/images/with-tags/{tag_list}[/[{items_per_page}[/]]]', PageController::class . ':getTotalImagePagesWithTags');
    $group->get('/videos[/[{items_per_page}[/]]]', PageController::class . ':getTotalVideoPages');
    $group->get('/videos/with-tags/{tag_list}[/[{items_per_page}[/]]]', PageController::class . ':getTotalVideoPagesWithTags');
});


// Authentication endpoint
$app->post('/auth/login[/]', function (ServerRequestInterface $request, ResponseInterface $response) {
    $params = json_decode((string)$request->getBody(), true) ?? [];
    $password = $params['password'] ?? '';

    if ($password === Configuration::ADMIN_PASSWORD) {
        $token = bin2hex(random_bytes(32));
        // Store token in session-like mechanism using a file (simple approach for SQLite-based app)
        $db = \Gallery\Core\DatabaseConnection::getInstance();
        $db->exec('CREATE TABLE IF NOT EXISTS auth_tokens (token TEXT PRIMARY KEY, created_at INTEGER NOT NULL)');
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

// Duplicates Controllers (protected by auth)
$app->group('/duplicates', function (RouteCollectorProxy $group) {
    $group->get('/report[/]', DuplicatesController::class . ':getLatestReport');
    $group->post('/scan[/]', DuplicatesController::class . ':runScan');
    $group->delete('/images[/]', DuplicatesController::class . ':deleteImages');
})->add(function (ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
    // Auth middleware for duplicates group
    $authHeader = $request->getHeaderLine('Authorization');
    $token = str_starts_with($authHeader, 'Bearer ') ? substr($authHeader, 7) : '';

    if (empty($token)) {
        $response = new \Slim\Psr7\Response();
        $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
        return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
    }

    $db = \Gallery\Core\DatabaseConnection::getInstance();
    $db->exec('CREATE TABLE IF NOT EXISTS auth_tokens (token TEXT PRIMARY KEY, created_at INTEGER NOT NULL)');
    $stmt = $db->prepare('SELECT 1 FROM auth_tokens WHERE token = :token AND created_at >= :min_time');
    $stmt->execute([':token' => $token, ':min_time' => time() - 86400]);

    if (!$stmt->fetchColumn()) {
        $response = new \Slim\Psr7\Response();
        $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
        return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
    }

    return $handler->handle($request);
});

// Run the app
$app->run();
