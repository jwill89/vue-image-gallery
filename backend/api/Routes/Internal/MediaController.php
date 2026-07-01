<?php

namespace Routes\Internal;

use Slim\Http\ServerRequest as Request;
use Slim\Http\Response;
use Gallery\Core\CacheGroup;
use Gallery\Core\Configuration;
use Gallery\Core\DanbooruTagger;
use Gallery\Core\ResponseCache;
use Gallery\Collection\MediaCollection;
use Gallery\Repository\TagRepository;
use Gallery\Structure\Tag;
use OpenApi\Attributes as OA;

/**
 * MediaController
 *
 * All media endpoints (unified images + videos): listing/search, single-item
 * reads, upload deletion, and the media-scoped tag relationship
 * (GET/PATCH/DELETE /media/{id}/tags, POST /media/{id}/danbooru-tags).
 */
class MediaController extends AbstractController
{
    private MediaCollection $media_collection;
    private TagRepository $tag_repository;
    private DanbooruTagger $tagger;

    public function __construct(
        MediaCollection $media_collection,
        TagRepository $tag_repository,
        DanbooruTagger $tagger
    ) {
        parent::__construct();
        $this->media_collection = $media_collection;
        $this->tag_repository = $tag_repository;
        // Lazy: only warms its DB caches on first Danbooru import, so injecting
        // it for every media request stays cheap.
        $this->tagger = $tagger;
    }

    /**
     * GET /media/page/{page}[/{per_page}] — A page of media items.
     * Returns { items, total_pages, current_page }. per_page is clamped to 1..200.
     */
    #[OA\Get(
        path: '/media/page/{page}/{per_page}',
        summary: 'A page of media',
        tags: ['Media'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'per_page', in: 'path', required: true, schema: new OA\Schema(type: 'integer', maximum: 200, minimum: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'A page of media', content: new OA\JsonContent(ref: '#/components/schemas/MediaPage')),
            new OA\Response(response: 400, description: 'InvalidPageNumber', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function getItemsForPage(Request $request, Response $response, array $args): Response
    {
        $page = $this->intParam($args, 'page', 0);
        $per_page = $this->clampPerPage($this->intParam($args, 'per_page', Configuration::DEFAULT_PER_PAGE));

        if ($page <= 0) {
            return $this->error($response, 'InvalidPageNumber', 400, 'Page number must be 1 or greater.');
        }

        return $this->cachedSuccess($response, CacheGroup::Media, "page:{$page}:{$per_page}", ResponseCache::TTL_SHORT, function () use ($page, $per_page) {
            $total = $this->media_collection->totalMedia();
            return [
                'items' => $this->media_collection->getForPage($page, $per_page),
                'total_pages' => (int) ceil($total / $per_page),
                'current_page' => $page,
            ];
        });
    }

    /**
     * GET /media/untagged/{page}[/{per_page}] — A page of media with no tags.
     */
    #[OA\Get(
        path: '/media/untagged/{page}/{per_page}',
        summary: 'A page of untagged media',
        tags: ['Media'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'per_page', in: 'path', required: true, schema: new OA\Schema(type: 'integer', maximum: 200, minimum: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'A page of untagged media', content: new OA\JsonContent(ref: '#/components/schemas/MediaPage')),
            new OA\Response(response: 400, description: 'InvalidPageNumber', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function getUntaggedItems(Request $request, Response $response, array $args): Response
    {
        $page = $this->intParam($args, 'page', 0);
        $per_page = $this->clampPerPage($this->intParam($args, 'per_page', Configuration::DEFAULT_PER_PAGE));

        if ($page <= 0) {
            return $this->error($response, 'InvalidPageNumber', 400, 'Page number must be 1 or greater.');
        }

        return $this->cachedSuccess($response, CacheGroup::Media, "untagged:{$page}:{$per_page}", ResponseCache::TTL_SHORT, function () use ($page, $per_page) {
            $total = $this->media_collection->totalUntagged();
            return [
                'items' => $this->media_collection->getUntagged($page, $per_page),
                'total_pages' => max(1, (int) ceil($total / $per_page)),
                'current_page' => $page,
            ];
        });
    }

    /**
     * GET /media/with-tags/{tag_list}/{page}[/{per_page}] — A page of media
     * filtered by a comma-separated tag list; a leading '-' excludes a tag
     * (e.g. "cat,-dog"). Returns { items, total_pages, current_page }.
     */
    #[OA\Get(
        path: '/media/with-tags/{tag_list}/{page}/{per_page}',
        summary: 'A page of media filtered by tags',
        tags: ['Media'],
        parameters: [
            new OA\Parameter(name: 'tag_list', in: 'path', required: true, description: "Comma-separated tag names; a leading '-' excludes (e.g. cat,-dog).", schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'page', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'per_page', in: 'path', required: true, schema: new OA\Schema(type: 'integer', maximum: 200, minimum: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'A page of media', content: new OA\JsonContent(ref: '#/components/schemas/MediaPage')),
            new OA\Response(response: 404, description: 'NoValidTagsSupplied', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function getItemsWithTags(Request $request, Response $response, array $args): Response
    {
        $tag_list_raw = $this->stringParam($args, 'tag_list');
        $page = $this->intParam($args, 'page', 1);
        $per_page = $this->clampPerPage($this->intParam($args, 'per_page', Configuration::DEFAULT_PER_PAGE));

        $include_names = [];
        $exclude_names = [];
        foreach (array_map('trim', explode(',', $tag_list_raw)) as $name) {
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

        return $this->cachedSuccess($response, CacheGroup::Media, "tags:{$tag_list_raw}:{$page}:{$per_page}", ResponseCache::TTL_SHORT, function () use ($include_ids, $exclude_ids, $page, $per_page) {
            if (!empty($exclude_ids)) {
                $items = $this->media_collection->getWithTagFilter($include_ids, $exclude_ids, $page, $per_page);
                $total = $this->media_collection->totalWithTagFilter($include_ids, $exclude_ids);
            } else {
                $items = $this->media_collection->getWithTags($include_ids, $page, $per_page);
                $total = $this->media_collection->totalMediaWithTags($include_ids);
            }

            return [
                'items' => $items,
                'total_pages' => max(1, (int) ceil($total / $per_page)),
                'current_page' => $page,
            ];
        });
    }

    /** Clamp an items-per-page value to the allowed 1..200 range. */
    private function clampPerPage(int $per_page): int
    {
        return min(max($per_page, 1), 200);
    }

    /**
     * GET /media/{media_id} — A single media item by ID.
     */
    #[OA\Get(
        path: '/media/{media_id}',
        summary: 'A single media item',
        tags: ['Media'],
        parameters: [new OA\Parameter(name: 'media_id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'The media item', content: new OA\JsonContent(ref: '#/components/schemas/Media')),
            new OA\Response(response: 404, description: 'MediaNotFound', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function getItem(Request $request, Response $response, array $args): Response
    {
        $id = $this->intParam($args, 'media_id', 0);

        if ($id <= 0) {
            return $this->error($response, 'InvalidMediaID', 400, 'The media ID must be a positive number.');
        }

        $data = $this->media_collection->get($id);
        return $data === null
            ? $this->error($response, 'MediaNotFound', 404, 'The requested media item could not be found.')
            : $this->success($response, $data);
    }

    /**
     * GET /media/random — A single random media item.
     */
    #[OA\Get(
        path: '/media/random',
        summary: 'A random media item',
        tags: ['Media'],
        responses: [
            new OA\Response(response: 200, description: 'A random media item', content: new OA\JsonContent(ref: '#/components/schemas/Media')),
            new OA\Response(response: 404, description: 'MediaNotFound', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function getRandomItem(Request $request, Response $response): Response
    {
        $item = $this->media_collection->getRandom();

        return $item === null
            ? $this->error($response, 'MediaNotFound', 404, 'No media items are available.')
            : $this->success($response, $item);
    }

    /**
     * GET /media/count — The total media count, as { count }.
     */
    #[OA\Get(
        path: '/media/count',
        summary: 'Total media count',
        tags: ['Media'],
        responses: [
            new OA\Response(response: 200, description: 'The count', content: new OA\JsonContent(ref: '#/components/schemas/Count')),
        ]
    )]
    public function getCount(Request $request, Response $response): Response
    {
        return $this->cachedSuccess($response, CacheGroup::Media, 'count', ResponseCache::TTL_MEDIUM, function () {
            return ['count' => $this->media_collection->totalMedia()];
        });
    }

    /**
     * POST /media/by-ids — Get multiple media items by an array of IDs.
     * Body: { ids: int[] } (max 200). Missing IDs are silently skipped.
     * A public batched read (uses POST only to carry a large id list).
     */
    #[OA\Post(
        path: '/media/by-ids',
        summary: 'Batch-fetch media by IDs',
        tags: ['Media'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['ids'],
                properties: [new OA\Property(property: 'ids', type: 'array', items: new OA\Items(type: 'integer'))]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Matching media items', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/Media'))),
            new OA\Response(response: 400, description: 'InvalidInput', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function getItemsByIds(Request $request, Response $response): Response
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

        return $this->success($response, $this->media_collection->getByIds($ids));
    }

    /**
     * DELETE /media/{media_id} — Delete a media item (DB row, file, thumbnails).
     */
    #[OA\Delete(
        path: '/media/{media_id}',
        summary: 'Delete a media item',
        tags: ['Media'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'media_id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Deleted'),
            new OA\Response(response: 404, description: 'MediaNotFound', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function deleteItem(Request $request, Response $response, array $args): Response
    {
        $id = $this->intParam($args, 'media_id', 0);

        if ($id <= 0) {
            return $this->error($response, 'InvalidMediaID', 400, 'The media ID must be a positive number.');
        }

        $item = $this->media_collection->get($id);

        if ($item === null) {
            return $this->error($response, 'MediaNotFound', 404, 'The media item to delete could not be found.');
        }

        try {
            $this->media_collection->delete($item);
            $this->invalidateCache(CacheGroup::Media, CacheGroup::Tags);
            $this->logger->info('Media deleted', ['media_id' => $id]);
            return $this->noContent($response);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to delete media', ['media_id' => $id, 'error' => $e->getMessage()]);
            return $this->error($response, 'DeleteFailed', 500, 'The media item could not be deleted. Please try again.');
        }
    }

    /**
     * POST /media/bulk-delete — Delete one or more media items by ID.
     * Body: { media_ids: int[] }. Returns the deleted and failed ID lists.
     */
    #[OA\Post(
        path: '/media/bulk-delete',
        summary: 'Bulk-delete media items',
        tags: ['Media'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['media_ids'],
                properties: [new OA\Property(property: 'media_ids', type: 'array', items: new OA\Items(type: 'integer'))]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Deletion outcome', content: new OA\JsonContent(ref: '#/components/schemas/BulkDeleteResult')),
            new OA\Response(response: 400, description: 'InvalidInput', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function bulkDelete(Request $request, Response $response): Response
    {
        $params = $this->parsedBody($request);
        $media_ids = $params['media_ids'] ?? [];

        if (empty($media_ids) || !is_array($media_ids)) {
            return $this->error($response, 'InvalidInput', 400, 'A list of media IDs to delete is required.');
        }

        $this->logger->info('Bulk media delete requested', ['count' => count($media_ids)]);

        $deleted = [];
        $failed = [];

        foreach ($media_ids as $id) {
            $id = (int) $id;
            if ($id <= 0) {
                $failed[] = $id;
                continue;
            }

            try {
                $media = $this->media_collection->get($id);
                if ($media !== null && $this->media_collection->delete($media)) {
                    $deleted[] = $id;
                } else {
                    $failed[] = $id;
                }
            } catch (\Throwable $e) {
                $failed[] = $id;
            }
        }

        if (!empty($deleted)) {
            $this->invalidateCache(CacheGroup::Media, CacheGroup::Tags);
        }

        return $this->success($response, [
            'deleted' => $deleted,
            'failed' => $failed,
            'total_deleted' => count($deleted),
        ]);
    }

    /**
     * GET /media/{media_id}/tags — Tags applied to a media item. Cached.
     */
    #[OA\Get(
        path: '/media/{media_id}/tags',
        summary: "A media item's tags",
        tags: ['Media'],
        parameters: [new OA\Parameter(name: 'media_id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'The tags', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/Tag'))),
            new OA\Response(response: 404, description: 'MediaDoesNotExist', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function getMediaTags(Request $request, Response $response, array $args): Response
    {
        $media_id = $this->intParam($args, 'media_id', 0);

        if ($media_id <= 0) {
            return $this->error($response, 'InvalidMediaID', 400, 'The media ID must be a positive number.');
        }

        $media = $this->media_collection->get($media_id);

        if ($media === null) {
            return $this->error($response, 'MediaDoesNotExist', 404, 'The media item could not be found.');
        }

        return $this->cachedSuccess($response, CacheGroup::Tags, "for:media:{$media_id}", ResponseCache::TTL_SHORT, function () use ($media) {
            return $this->tag_repository->getTagsForMedia($media);
        });
    }

    /**
     * PATCH /media/{media_id}/tags — Apply tags to a media item (implications
     * resolved). Body: { tag_ids: int[] }. Returns the item's updated tag list.
     * Public (anyone may tag).
     */
    #[OA\Patch(
        path: '/media/{media_id}/tags',
        summary: 'Add tags to a media item',
        tags: ['Media'],
        parameters: [new OA\Parameter(name: 'media_id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['tag_ids'],
                properties: [new OA\Property(property: 'tag_ids', type: 'array', items: new OA\Items(type: 'integer'))]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Updated tag list', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/Tag'))),
            new OA\Response(response: 404, description: 'MediaDoesNotExist / TagDoesNotExist', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function addMediaTags(Request $request, Response $response, array $args): Response
    {
        $media_id = $this->intParam($args, 'media_id', 0);
        $params = $this->parsedBody($request);
        $tag_ids_raw = $this->parseParameters($params, 'tag_ids', []);

        if ($media_id <= 0) {
            return $this->error($response, 'InvalidMediaID', 400, 'The media ID must be a positive number.');
        }

        $media = $this->media_collection->get($media_id);

        if ($media === null) {
            return $this->error($response, 'MediaDoesNotExist', 404, 'The media item could not be found.');
        }

        $tag_ids = array_filter(array_map('intval', (array) $tag_ids_raw), fn($id) => $id > 0);

        if (empty($tag_ids)) {
            return $this->error($response, 'InvalidTagList', 400, 'At least one valid tag must be provided.');
        }

        // Validate that every supplied tag exists in a single query (no N+1).
        $existing_ids = $this->tag_repository->getExistingIds($tag_ids);
        $missing = array_values(array_diff($tag_ids, $existing_ids));
        if (!empty($missing)) {
            return $this->error($response, 'TagDoesNotExist', 404, "Tag #{$missing[0]} does not exist.");
        }

        $success = $this->tag_repository->addTagsToMedia($media, $tag_ids);

        if (!$success) {
            $this->logger->error('Failed to add tags to media', ['media_id' => $media_id, 'tag_ids' => $tag_ids]);
            return $this->error($response, 'CouldNotAddTagsToMedia', 500, 'Failed to add tags to the media item. Please try again.');
        }

        $this->invalidateCache(CacheGroup::Media, CacheGroup::Tags);
        $this->logger->info('Tags added to media', ['media_id' => $media_id, 'tag_ids' => $tag_ids]);

        return $this->success($response, $this->tag_repository->getTagsForMedia($media));
    }

    /**
     * DELETE /media/{media_id}/tags/{tag_id} — Remove a tag from a media item.
     * Returns the item's updated tag list. Public (anyone may tag).
     */
    #[OA\Delete(
        path: '/media/{media_id}/tags/{tag_id}',
        summary: 'Remove a tag from a media item',
        tags: ['Media'],
        parameters: [
            new OA\Parameter(name: 'media_id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'tag_id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Updated tag list', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/Tag'))),
            new OA\Response(response: 404, description: 'MediaDoesNotExist / CouldNotFindTag', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function removeMediaTag(Request $request, Response $response, array $args): Response
    {
        $media_id = $this->intParam($args, 'media_id', 0);
        $tag_id = $this->intParam($args, 'tag_id', 0);

        if ($media_id <= 0) {
            return $this->error($response, 'InvalidMediaID', 400, 'The media ID must be a positive number.');
        }

        $media = $this->media_collection->get($media_id);

        if ($media === null) {
            return $this->error($response, 'MediaDoesNotExist', 404, 'The media item could not be found.');
        }

        if ($tag_id <= 0) {
            return $this->error($response, 'InvalidTagID', 400, 'The tag ID must be a positive number.');
        }

        $tag = $this->tag_repository->get($tag_id);
        if (!($tag instanceof Tag)) {
            return $this->error($response, 'CouldNotFindTag', 404, 'The tag to remove could not be found.');
        }

        $removed = $this->tag_repository->removeTagFromMedia($media, $tag);

        if (!$removed) {
            $this->logger->error('Failed to remove tag from media', ['media_id' => $media_id, 'tag_id' => $tag_id]);
            return $this->error($response, 'CouldNotRemoveTagFromMedia', 500, 'Failed to remove the tag. Please try again.');
        }

        $this->invalidateCache(CacheGroup::Media, CacheGroup::Tags);
        $this->logger->info('Tag removed from media', ['media_id' => $media_id, 'tag_id' => $tag_id]);

        return $this->success($response, $this->tag_repository->getTagsForMedia($media));
    }

    /**
     * POST /media/{media_id}/danbooru-tags — Import tags from Danbooru for a
     * media item. Body: { danbooru_post_id? }. With a post ID, imports directly;
     * otherwise auto-looks-up by MD5 hash then IQDB visual similarity.
     */
    #[OA\Post(
        path: '/media/{media_id}/danbooru-tags',
        summary: 'Import Danbooru tags for a media item',
        tags: ['Media'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'media_id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(properties: [new OA\Property(property: 'danbooru_post_id', type: 'integer', nullable: true)])
        ),
        responses: [
            new OA\Response(response: 200, description: 'Import result', content: new OA\JsonContent(ref: '#/components/schemas/DanbooruFetchResult')),
            new OA\Response(response: 404, description: 'MediaDoesNotExist / NotFoundOnDanbooru', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 500, description: 'DanbooruNotConfigured', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function fetchDanbooruTags(Request $request, Response $response, array $args): Response
    {
        $mediaId = $this->intParam($args, 'media_id', 0);
        $params = $this->parsedBody($request);
        $danbooruPostId = $this->intParam($params, 'danbooru_post_id', 0);

        if ($mediaId <= 0) {
            return $this->error($response, 'InvalidMediaID', 400, 'A valid media ID is required.');
        }

        if (!DanbooruTagger::isConfigured()) {
            return $this->error($response, 'DanbooruNotConfigured', 500, 'Danbooru credentials are not configured on the server.');
        }

        $media = $this->media_collection->get($mediaId);
        if ($media === null) {
            return $this->error($response, 'MediaDoesNotExist', 404, 'The media item could not be found.');
        }

        if ($danbooruPostId > 0) {
            $result = $this->tagger->importTagsFromPost($mediaId, $danbooruPostId);
        } else {
            $result = $this->tagger->importTagsForMedia($mediaId, $media->hash, $media->file_name);
        }

        if (!$result['found']) {
            return $this->error(
                $response,
                'NotFoundOnDanbooru',
                404,
                $danbooruPostId > 0
                    ? "Danbooru post #{$danbooruPostId} could not be found."
                    : 'This media could not be found on Danbooru by hash or visual similarity.'
            );
        }

        $this->invalidateCache(CacheGroup::Media, CacheGroup::Tags);
        $this->logger->info('Danbooru tags imported', [
            'media_id' => $mediaId,
            'method' => $result['method'],
            'tags_applied' => $result['tags_applied'],
            'tags_created' => $result['tags_created'],
        ]);

        return $this->success($response, [
            'tags' => $this->tag_repository->getTagsForMedia($media),
            'all_tags' => $this->tag_repository->getAll(),
            'method' => $result['method'],
            'tags_applied' => $result['tags_applied'],
            'tags_created' => $result['tags_created'],
        ]);
    }
}
