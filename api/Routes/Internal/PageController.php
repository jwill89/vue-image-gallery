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
 * This class is responsible for handling pagination requests for the API.
 */
class PageController extends AbstractController
{
    private ImageCollection $image_collection;
    private VideoCollection $video_collection;
    private TagCollection $tag_collection;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->image_collection = new ImageCollection();
        $this->video_collection = new VideoCollection();
        $this->tag_collection = new TagCollection();
    }

    public function getTotalImagePages(Request $request, Response $response, array $args): Response
    {
        $items_per_page = min(max((int)$this->parseParameters($args, 'items_per_page', Configuration::DEFAULT_PER_PAGE), 1), 200);
        $total_images = $this->image_collection->totalImages();

        return $response->withJson((int)ceil($total_images / $items_per_page), 200);
    }

    public function getTotalImagePagesWithTags(Request $request, Response $response, array $args): Response
    {
        $tag_list = array_map('trim', explode(',', $this->parseParameters($args, 'tag_list', '')));
        $items_per_page = min(max((int)$this->parseParameters($args, 'items_per_page', Configuration::DEFAULT_PER_PAGE), 1), 200);

        $tag_ids = $this->resolveTagIds($tag_list, $this->tag_collection);
        $total_images = $this->image_collection->totalImagesWithTags($tag_ids);

        return $response->withJson((int)ceil($total_images / $items_per_page), 200);
    }

    public function getTotalVideoPages(Request $request, Response $response, array $args): Response
    {
        $items_per_page = min(max((int)$this->parseParameters($args, 'items_per_page', Configuration::DEFAULT_PER_PAGE), 1), 200);
        $total_videos = $this->video_collection->totalVideos();

        return $response->withJson((int)ceil($total_videos / $items_per_page), 200);
    }

    public function getTotalVideoPagesWithTags(Request $request, Response $response, array $args): Response
    {
        $tag_list = array_map('trim', explode(',', $this->parseParameters($args, 'tag_list', '')));
        $items_per_page = min(max((int)$this->parseParameters($args, 'items_per_page', Configuration::DEFAULT_PER_PAGE), 1), 200);

        $tag_ids = $this->resolveTagIds($tag_list, $this->tag_collection);
        $total_videos = $this->video_collection->totalVideosWithTags($tag_ids);

        return $response->withJson((int)ceil($total_videos / $items_per_page), 200);
    }
}
