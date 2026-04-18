<?php

namespace Routes\Internal;

use Psr\Container\ContainerInterface;
use Slim\Http\ServerRequest as Request;
use Slim\Http\Response;
use Gallery\Core\Configuration;
use Gallery\Collection\ImageCollection;
use Gallery\Collection\VideoCollection;
use Gallery\Collection\TagCollection;

/**
 * PageController class
 * This class is responsible for handling image-related requests for the API.
 */
class PageController extends AbstractController
{
    // Collections
    private ImageCollection $image_collection;
    private VideoCollection $video_collection;
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

        // Set collections for use in class methods
        $this->image_collection = new ImageCollection();
        $this->video_collection = new VideoCollection();
        $this->tag_collection = new TagCollection();
    }

    /**
     * getTotalImagePages function
     * This function is used to get the total number of image pages based on the number of items per page.
     *
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function getTotalImagePages(Request $request, Response $response, array $args): Response
    {
        // Get items per page from args
        $items_per_page = $this->parseParameters($args, 'items_per_page', Configuration::DEFAULT_PER_PAGE);

        // Assume status OK
        $status = 200;

        // Default Data
        $data = [];

        // Get total images
        $total_images = $this->image_collection->totalImages();
        $total_pages = (int)ceil($total_images / $items_per_page);

        // Set the return data
        $data = $total_pages;

        // Return data as json with HTTP status response
        return $response->withJson($data, $status);
    }

    /**
     * getTotalImagePagesWithTags function
     * This function is used to get the total number of image pages based on the number of items per page.
     *
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function getTotalImagePagesWithTags(Request $request, Response $response, array $args): Response
    {
        // Get items per page from args
        $tag_list = array_map('trim', explode(',', $this->parseParameters($args, 'tag_list', '')));
        $items_per_page = $this->parseParameters($args, 'items_per_page', Configuration::DEFAULT_PER_PAGE);

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

        // Get total images
        $total_images = $this->image_collection->totalImagesWithTags($tag_ids);
        $total_pages = (int)ceil($total_images / $items_per_page);

        // Set the return data
        $data = $total_pages;

        // Return data as json with HTTP status response
        return $response->withJson($data, $status);
    }

    /**
     * getTotalVideoPages function
     * This function is used to get the total number of video pages based on the number of items per page.
     *
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function getTotalVideoPages(Request $request, Response $response, array $args): Response
    {
        // Get items per page from args
        $items_per_page = $this->parseParameters($args, 'items_per_page', 40);

        // Assume status OK
        $status = 200;

        // Default Data
        $data = [];

        // Get total videos
        $total_videos = $this->video_collection->totalVideos();
        $total_pages = (int)ceil($total_videos / $items_per_page);

        // Set the return data
        $data = $total_pages;

        // Return data as json with HTTP status response
        return $response->withJson($data, $status);
    }

    /**
     * getTotalVideoWithTagsPages function
     * This function is used to get the total number of video pages based on the number of items per page.
     *
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function getTotalVideoWithTagsPages(Request $request, Response $response, array $args): Response
    {
        // Get items per page from args
        $tag_list = array_map('trim', explode(',', $this->parseParameters($args, 'tag_list', '')));
        $items_per_page = $this->parseParameters($args, 'items_per_page', 40);

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

        // Get total videos
        $total_videos = $this->video_collection->totalVideosWithTags($tag_ids);
        $total_pages = (int)ceil($total_videos / $items_per_page);

        // Set the return data
        $data = $total_pages;

        // Return data as json with HTTP status response
        return $response->withJson($data, $status);
    }
}
