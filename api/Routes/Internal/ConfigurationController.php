<?php

namespace Routes\Internal;

use Slim\Http\ServerRequest as Request;
use Slim\Http\Response;
use Gallery\Core\Configuration;

/**
 * ConfigurationController class
 * This class is responsible for handling configuration-related requests for the API.
 */
class ConfigurationController extends AbstractController
{
    /**
     * getGalleryTitle function
     * This function is used to get the title of the gallery.
     *
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function getGalleryTitle(Request $request, Response $response, array $args): Response
    {
        // Assume status OK
        $status = 200;

        // Default Data
        $data = [];

        // Get total images
        $data = Configuration::GALLERY_TITLE;

        // Return data as JSON with HTTP status response
        return $response->withJson($data, $status);
    }
}
