<?php

namespace Routes\Internal;

use Psr\Container\ContainerInterface;
use Slim\Http\ServerRequest as Request;
use Slim\Http\Response;
use Gallery\Core\Configuration;
use Gallery\Collection\VideoCollection;
use Gallery\Collection\TagCollection;

/**
 * VideoController class
 * This class is responsible for handling video-related requests for the API.
 */
class VideoController extends AbstractController
{
    private VideoCollection $video_collection;
    private TagCollection $tag_collection;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->video_collection = new VideoCollection();
        $this->tag_collection = new TagCollection();
    }

    public function getVideo(Request $request, Response $response, array $args): Response
    {
        $video_id = $this->parseParameters($args, 'video_id', null);

        if ($video_id === null) {
            return $response->withJson($this->video_collection->getAll(), 200);
        }

        if (!is_numeric($video_id) || $video_id <= 0) {
            return $response->withJson(['error' => 'InvalidVideoID'], 400);
        }

        $data = $this->video_collection->get((int)$video_id);
        return $response->withJson($data, $data === null ? 404 : 200);
    }

    public function getVideosForPage(Request $request, Response $response, array $args): Response
    {
        $page = (int)$this->parseParameters($args, 'page', 0);
        $items_per_page = min(max((int)$this->parseParameters($args, 'items_per_page', Configuration::DEFAULT_PER_PAGE), 1), 200);

        if ($page <= 0) {
            return $response->withJson(['error' => 'InvalidPageNumber'], 400);
        }

        return $response->withJson($this->video_collection->getForPage($page, $items_per_page), 200);
    }

    public function getVideosWithTags(Request $request, Response $response, array $args): Response
    {
        $tag_list = array_map('trim', explode(',', $this->parseParameters($args, 'tag_list', '')));
        $page = (int)$this->parseParameters($args, 'page', 1);
        $items_per_page = min(max((int)$this->parseParameters($args, 'items_per_page', Configuration::DEFAULT_PER_PAGE), 1), 200);

        $tag_ids = $this->resolveTagIds($tag_list, $this->tag_collection);

        if (empty($tag_ids)) {
            return $response->withJson(['error' => 'NoValidTagsSupplied'], 404);
        }

        return $response->withJson($this->video_collection->getWithTags($tag_ids, $page, $items_per_page), 200);
    }

    public function getTotalVideos(Request $request, Response $response, array $args): Response
    {
        return $response->withJson($this->video_collection->totalVideos(), 200);
    }
}
