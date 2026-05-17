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
use Routes\Internal\ConfigurationController;
use Gallery\Core\Configuration;

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

// Setup Allowables and Response Origins
$app->add(function (ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
    $response = $handler->handle($request);

    // Determine allowed origin (restrict to same origin in production)
    $origin = $request->getHeaderLine('Origin');
    $allowed_origins = Configuration::ALLOWED_ORIGINS;

    // Only allow known origins, or same-origin requests (empty Origin header)
    $allow_origin = '';
    if (empty($origin) || in_array($origin, $allowed_origins, true)) {
        $allow_origin = $origin ?: '*';
    }

    return $response
        ->withHeader('Access-Control-Allow-Credentials', 'true')
        ->withHeader('Access-Control-Allow-Origin', $allow_origin)
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE')
        ->withHeader('X-Frame-Options', 'SAMEORIGIN')
        ->withHeader('X-Content-Type-Options', 'nosniff');
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

// Configuration Controllers
$app->group('/config', function (RouteCollectorProxy $group) {
    $group->get('/title[/]', ConfigurationController::class . ':getGalleryTitle');
});

// Duplicates Controllers
$app->group('/duplicates', function (RouteCollectorProxy $group) {
    $group->get('/report[/]', DuplicatesController::class . ':getLatestReport');
    $group->post('/scan[/]', DuplicatesController::class . ':runScan');
    $group->delete('/images[/]', DuplicatesController::class . ':deleteImages');
});

// Run the app
$app->run();
