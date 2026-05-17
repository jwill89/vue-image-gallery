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

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->image_collection = new ImageCollection();
        $this->tag_collection = new TagCollection();
    }

    public function getImage(Request $request, Response $response, array $args): Response
    {
        $image_id = $this->parseParameters($args, 'image_id', null);

        if ($image_id === null) {
            return $response->withJson($this->image_collection->getAll(), 200);
        }

        if (!is_numeric($image_id) || $image_id <= 0) {
            return $response->withJson(['error' => 'InvalidImageID'], 400);
        }

        $data = $this->image_collection->get((int)$image_id);
        return $response->withJson($data, $data === null ? 404 : 200);
    }

    public function getImagesForPage(Request $request, Response $response, array $args): Response
    {
        $page = (int)$this->parseParameters($args, 'page', 0);
        $items_per_page = min(max((int)$this->parseParameters($args, 'items_per_page', Configuration::DEFAULT_PER_PAGE), 1), 200);

        if ($page <= 0) {
            return $response->withJson(['error' => 'InvalidPageNumber'], 400);
        }

        return $response->withJson($this->image_collection->getForPage($page, $items_per_page), 200);
    }

    public function getImagesWithTags(Request $request, Response $response, array $args): Response
    {
        $tag_list = array_map('trim', explode(',', $this->parseParameters($args, 'tag_list', '')));
        $page = (int)$this->parseParameters($args, 'page', 1);
        $items_per_page = min(max((int)$this->parseParameters($args, 'items_per_page', Configuration::DEFAULT_PER_PAGE), 1), 200);

        $tag_ids = $this->resolveTagIds($tag_list, $this->tag_collection);

        if (empty($tag_ids)) {
            return $response->withJson(['error' => 'NoValidTagsSupplied'], 404);
        }

        return $response->withJson($this->image_collection->getWithTags($tag_ids, $page, $items_per_page), 200);
    }

    public function getTotalImages(Request $request, Response $response, array $args): Response
    {
        return $response->withJson($this->image_collection->totalImages(), 200);
    }
}
