<?php

namespace Routes\Internal;

use Psr\Container\ContainerInterface;
use Slim\Http\ServerRequest as Request;
use Slim\Http\Response;
use Gallery\Core\Configuration;
use Gallery\Collection\TagCollection;

/**
 * MediaController class
 *
 * Abstract base controller for media endpoints (images and videos).
 * Eliminates duplication between ImageController and VideoController
 * by parameterizing the media type, collection, ID field, and entity name.
 */
abstract class MediaController extends AbstractController
{
    protected TagCollection $tag_collection;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->tag_collection = new TagCollection();
    }

    /**
     * Get the media collection instance (ImageCollection or VideoCollection).
     */
    abstract protected function getCollection(): object;

    /**
     * Get the route parameter name for the media ID (e.g., 'image_id' or 'video_id').
     */
    abstract protected function getIdParam(): string;

    /**
     * Get the human-readable entity name for error messages (e.g., 'Image' or 'Video').
     */
    abstract protected function getEntityName(): string;

    /**
     * Get a single media item or all items.
     */
    public function getItem(Request $request, Response $response, array $args): Response
    {
        $id = $this->parseParameters($args, $this->getIdParam(), null);

        if ($id === null) {
            return $this->success($response, $this->getCollection()->getAll());
        }

        if (!is_numeric($id) || $id <= 0) {
            return $this->error($response, 'Invalid' . $this->getEntityName() . 'ID', 400);
        }

        $data = $this->getCollection()->get((int)$id);
        return $data === null
            ? $this->error($response, $this->getEntityName() . 'NotFound', 404)
            : $this->success($response, $data);
    }

    /**
     * Get paginated media items with total_pages included in response.
     */
    public function getItemsForPage(Request $request, Response $response, array $args): Response
    {
        $page = (int)$this->parseParameters($args, 'page', 0);
        $items_per_page = min(max((int)$this->parseParameters($args, 'items_per_page', Configuration::DEFAULT_PER_PAGE), 1), 200);

        if ($page <= 0) {
            return $this->error($response, 'InvalidPageNumber', 400);
        }

        $collection = $this->getCollection();
        $items = $collection->getForPage($page, $items_per_page);
        $total = $this->getTotalCount();
        $total_pages = (int)ceil($total / $items_per_page);

        return $this->success($response, [
            'items' => $items,
            'total_pages' => $total_pages,
            'current_page' => $page,
        ]);
    }

    /**
     * Get paginated media items filtered by tags with total_pages included.
     */
    public function getItemsWithTags(Request $request, Response $response, array $args): Response
    {
        $tag_list = array_map('trim', explode(',', $this->parseParameters($args, 'tag_list', '')));
        $page = (int)$this->parseParameters($args, 'page', 1);
        $items_per_page = min(max((int)$this->parseParameters($args, 'items_per_page', Configuration::DEFAULT_PER_PAGE), 1), 200);

        $tag_ids = $this->resolveTagIds($tag_list, $this->tag_collection);

        if (empty($tag_ids)) {
            return $this->error($response, 'NoValidTagsSupplied', 404);
        }

        $collection = $this->getCollection();
        $items = $collection->getWithTags($tag_ids, $page, $items_per_page);
        $total = $this->getTotalCountWithTags($tag_ids);
        $total_pages = (int)ceil($total / $items_per_page);

        return $this->success($response, [
            'items' => $items,
            'total_pages' => $total_pages,
            'current_page' => $page,
        ]);
    }

    /**
     * Get the total number of media items.
     */
    public function getTotal(Request $request, Response $response, array $args): Response
    {
        return $this->success($response, $this->getTotalCount());
    }

    /**
     * Get total count of media items (used internally).
     */
    abstract protected function getTotalCount(): int;

    /**
     * Get total count of media items matching the given tags (used internally).
     *
     * @param int[] $tag_ids
     */
    abstract protected function getTotalCountWithTags(array $tag_ids): int;
}
