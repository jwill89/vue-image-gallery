<?php

namespace Routes\Internal;

use Slim\Http\ServerRequest as Request;
use Slim\Http\Response;
use Gallery\Core\CacheGroup;
use Gallery\Core\Configuration;
use Gallery\Core\ResponseCache;
use Gallery\Collection\MediaCollection;
use Gallery\Repository\TagRepository;

/**
 * MediaController class
 * Concrete controller handling all media API endpoints (unified images + videos).
 */
class MediaController extends AbstractController
{
    private MediaCollection $media_collection;
    private TagRepository $tag_repository;

    public function __construct(MediaCollection $media_collection, TagRepository $tag_repository)
    {
        parent::__construct();
        $this->media_collection = $media_collection;
        $this->tag_repository = $tag_repository;
    }

    /**
     * GET /media/{media_id} — A single media item by ID.
     * The ID is required; there is no "list all" form (use the paginated routes).
     */
    public function getItem(Request $request, Response $response, array $args): Response
    {
        $id = $this->parseParameters($args, 'media_id', null);

        if ($id === null) {
            // No ID supplied. Returning the entire media table here would be a
            // heavy, publicly-cacheable dump (and an easy amplification target);
            // callers must use the paginated endpoints instead.
            return $this->error($response, 'MediaIDRequired', 400, 'A media ID is required. Use the paginated endpoints to list media.');
        }

        if (!is_numeric($id) || $id <= 0) {
            return $this->error($response, 'InvalidMediaID', 400, 'The media ID must be a positive number.');
        }

        $data = $this->media_collection->get((int)$id);
        return $data === null
            ? $this->error($response, 'MediaNotFound', 404, 'The requested media item could not be found.')
            : $this->success($response, $data);
    }

    /**
     * POST /media/by-ids/ — Get multiple media items by an array of IDs.
     * Expects JSON body: { "ids": [1, 2, 3] }
     * Returns only items that exist; missing IDs are silently skipped.
     * Capped at 200 IDs per request.
     */
    public function getItemsByIds(Request $request, Response $response, array $args): Response
    {
        $params = $this->parsedBody($request);
        $rawIds = $params['ids'] ?? [];

        if (!is_array($rawIds) || empty($rawIds)) {
            return $this->error($response, 'InvalidInput', 400, 'A non-empty array of media IDs is required.');
        }

        // Cap at 200 to prevent abuse
        $ids = array_slice(array_filter(array_map('intval', $rawIds), fn($id) => $id > 0), 0, 200);

        if (empty($ids)) {
            return $this->error($response, 'InvalidInput', 400, 'No valid media IDs were provided.');
        }

        $items = $this->media_collection->getByIds($ids);
        return $this->success($response, $items);
    }

    /**
     * GET /media/random — A single random media item.
     */
    public function getRandomItem(Request $request, Response $response, array $args): Response
    {
        $item = $this->media_collection->getRandom();

        return $item === null
            ? $this->error($response, 'MediaNotFound', 404, 'No media items are available.')
            : $this->success($response, $item);
    }

    /**
     * GET /media/page/{page}[/{items_per_page}] — A page of media items.
     * Returns { items, total_pages, current_page }. items_per_page is clamped to 1..200.
     */
    public function getItemsForPage(Request $request, Response $response, array $args): Response
    {
        $page = $this->intParam($args, 'page', 0);
        $items_per_page = min(max($this->intParam($args, 'items_per_page', Configuration::DEFAULT_PER_PAGE), 1), 200);

        if ($page <= 0) {
            return $this->error($response, 'InvalidPageNumber', 400, 'Page number must be 1 or greater.');
        }

        return $this->cachedSuccess($response, CacheGroup::Media, "page:{$page}:{$items_per_page}", ResponseCache::TTL_SHORT, function () use ($page, $items_per_page) {
            $items = $this->media_collection->getForPage($page, $items_per_page);
            $total = $this->media_collection->totalMedia();
            $total_pages = (int)ceil($total / $items_per_page);

            return [
                'items' => $items,
                'total_pages' => $total_pages,
                'current_page' => $page,
            ];
        });
    }

    /**
     * GET /media/with-tags/{tag_list}/{page}[/{items_per_page}] — A page of media
     * filtered by a comma-separated tag list. A leading '-' excludes a tag
     * (e.g. "cat,-dog"). Returns { items, total_pages, current_page }.
     */
    public function getItemsWithTags(Request $request, Response $response, array $args): Response
    {
        $tag_list_raw = $this->stringParam($args, 'tag_list');
        $tag_list = array_map('trim', explode(',', $tag_list_raw));
        $page = $this->intParam($args, 'page', 1);
        $items_per_page = min(max($this->intParam($args, 'items_per_page', Configuration::DEFAULT_PER_PAGE), 1), 200);

        $include_names = [];
        $exclude_names = [];
        foreach ($tag_list as $name) {
            if ($name === '') {
                continue;
            }
            if (str_starts_with($name, '-')) {
                $stripped = ltrim(substr($name, 1));
                if ($stripped !== '') {
                    $exclude_names[] = $stripped;
                }
            } else {
                $include_names[] = $name;
            }
        }

        $include_ids = $this->resolveTagIds($include_names, $this->tag_repository);
        $exclude_ids = $this->resolveTagIds($exclude_names, $this->tag_repository);

        if (empty($include_ids) && empty($exclude_ids)) {
            return $this->error($response, 'NoValidTagsSupplied', 404, 'None of the specified tag names matched existing tags.');
        }

        return $this->cachedSuccess($response, CacheGroup::Media, "tags:{$tag_list_raw}:{$page}:{$items_per_page}", ResponseCache::TTL_SHORT, function () use ($include_ids, $exclude_ids, $page, $items_per_page) {
            if (!empty($exclude_ids)) {
                $items = $this->media_collection->getWithTagFilter($include_ids, $exclude_ids, $page, $items_per_page);
                $total = $this->media_collection->totalWithTagFilter($include_ids, $exclude_ids);
            } else {
                $items = $this->media_collection->getWithTags($include_ids, $page, $items_per_page);
                $total = $this->media_collection->totalMediaWithTags($include_ids);
            }

            $total_pages = max(1, (int)ceil($total / $items_per_page));

            return [
                'items' => $items,
                'total_pages' => $total_pages,
                'current_page' => $page,
            ];
        });
    }

    /**
     * GET /media/untagged/{page}[/{items_per_page}] — A page of media that have
     * no tags applied. Returns { items, total_pages, current_page }.
     */
    public function getUntaggedItems(Request $request, Response $response, array $args): Response
    {
        $page = $this->intParam($args, 'page', 0);
        $items_per_page = min(max($this->intParam($args, 'items_per_page', Configuration::DEFAULT_PER_PAGE), 1), 200);

        if ($page <= 0) {
            return $this->error($response, 'InvalidPageNumber', 400, 'Page number must be 1 or greater.');
        }

        return $this->cachedSuccess($response, CacheGroup::Media, "untagged:{$page}:{$items_per_page}", ResponseCache::TTL_SHORT, function () use ($page, $items_per_page) {
            $items = $this->media_collection->getUntagged($page, $items_per_page);
            $total = $this->media_collection->totalUntagged();
            $total_pages = max(1, (int)ceil($total / $items_per_page));

            return [
                'items' => $items,
                'total_pages' => $total_pages,
                'current_page' => $page,
            ];
        });
    }

    /**
     * DELETE /media/{media_id} — Delete a media item (DB row, file, thumbnails).
     */
    public function deleteItem(Request $request, Response $response, array $args): Response
    {
        $id = $this->parseParameters($args, 'media_id', null);

        if (!is_numeric($id) || $id <= 0) {
            return $this->error($response, 'InvalidMediaID', 400, 'The media ID must be a positive number.');
        }

        $item = $this->media_collection->get((int)$id);

        if ($item === null) {
            return $this->error($response, 'MediaNotFound', 404, 'The media item to delete could not be found.');
        }

        try {
            $this->media_collection->delete($item);
            $this->invalidateCache(CacheGroup::Media, CacheGroup::Tags);
            $this->logger->info('Media deleted', ['media_id' => (int)$id]);
            return $this->success($response, ['deleted' => (int)$id]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to delete media', ['media_id' => (int)$id, 'error' => $e->getMessage()]);
            return $this->error($response, 'DeleteFailed', 500, 'The media item could not be deleted. Please try again.');
        }
    }

    /**
     * GET /media/total — The total media count.
     */
    public function getTotal(Request $request, Response $response, array $args): Response
    {
        return $this->cachedSuccess($response, CacheGroup::Media, 'total', ResponseCache::TTL_MEDIUM, function () {
            return $this->media_collection->totalMedia();
        });
    }
}
