<?php

namespace Routes\Internal;

use Psr\Container\ContainerInterface;
use Slim\Http\ServerRequest as Request;
use Slim\Http\Response;
use Gallery\Core\Configuration;
use Gallery\Collection\ImageCollection;
use Gallery\Collection\TagCollection;

/**
 * ImageController class
 * This class is responsible for handling image-related requests for the API.
 */
class ImageController extends AbstractController
{
    private ImageCollection $image_collection;
    private TagCollection $tag_collection;

    /**
     * ImageController constructor
     * This function is used to initialize the ImageController class.
     * It sets up the image and tag collections for use in the class methods.
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        // Parent Constructor
        parent::__construct($container);

        // Set collections for user in class methods
        $this->image_collection = new ImageCollection();
        $this->tag_collection = new TagCollection();
    }

    /**
     * getImage function
     * This function is used to get an image or a collection of images.
     * It can be used to get a single image by ID or all images if no ID is provided.
     *
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function getImage(Request $request, Response $response, array $args): Response
    {
        // Initialize Image ID if provided
        $image_id = $this->parseParameters($args, 'image_id', null);

        // Assume status OK
        $status = 200;

        // Default Data
        $data = [];

        // If invalid ID provided, return error
        if (!empty($image_id) && (!is_numeric($image_id) || $image_id <= 0)) {
            $data = ['error' => 'InvalidImageID'];
            $status = 400;
        // If image ID provided, get the image
        } elseif (!empty($image_id) && $image_id > 0) {
            $data = $this->image_collection->get($image_id);
        // If no image ID provided, get all images
        } elseif ($image_id === null) {
            $data = $this->image_collection->getAll();
        }

        // Return data as json with HTTP status response
        return $response->withJson($data, $status);
    }

    /**
     * getImagesForPage function
     * This function is used to get a collection of images for a specific page.
     * It can be used to paginate through the images.
     *
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function getImagesForPage(Request $request, Response $response, array $args): Response
    {
        // Initialize variables if provided
        $page = (int)$this->parseParameters($args, 'page', 0);
        $items_per_page = min(max((int)$this->parseParameters($args, 'items_per_page', Configuration::DEFAULT_PER_PAGE), 1), 200);

        // Assume status OK
        $status = 200;

        // Default Data
        $data = [];

        // Error on invalid page
        if ($page <= 0) {
            $data = ['error' => 'InvalidPageNumber'];
            $status = 400;
        // If valid page provided, get images for that page
        } elseif ($page > 0) {
            // Get images and encode
            $data = $this->image_collection->getForPage($page, $items_per_page);
        }

        // Return data as json with HTTP status response
        return $response->withJson($data, $status);
    }

    /**
     * getImagesWithTags function
     * This function is used to get a collection of images based on the supplied tags.
     *
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return void
     */
    public function getImagesWithTags(Request $request, Response $response, array $args): Response
    {
        // Initialize Required Variables
        $tag_list = array_map('trim', explode(',', $this->parseParameters($args, 'tag_list', '')));
        $page = (int)$this->parseParameters($args, 'page', 1);
        $items_per_page = min(max((int)$this->parseParameters($args, 'items_per_page', Configuration::DEFAULT_PER_PAGE), 1), 200);

        // Assume status OK
        $status = 200;

        // Default Data
        $data = [];

        // Initialize Tag IDs for Collection Use
        $tag_ids = [];

        // Check the tags are valid
        foreach ($tag_list as $tag_name) {
            if (($tag = $this->tag_collection->getByName($tag_name)) !== null) {
                $tag_ids[] = $tag->getTagId();
            }
        }

        // Get the images with the tags
        if (empty($tag_ids)) {
            $data = ['error' => "NoValidTagsSupplied"];
            $status = 404;
        } else {
            $data = $this->image_collection->getWithTags($tag_ids, $page, $items_per_page);
        }

        // Return data as JSON with HTTP status response
        return $response->withJson($data, $status);
    }

    /**
     * getTotalImages function
     * This function is used to get the total number of images in the database.
     *
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function getTotalImages(Request $request, Response $response, array $args): Response
    {
        // Assume status OK
        $status = 200;

        // Default Data
        $data = [];

        // Get total images
        $data = $this->image_collection->totalImages();

        // Return data as JSON with HTTP status response
        return $response->withJson($data, $status);
    }
}
