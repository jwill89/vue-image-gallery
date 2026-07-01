<?php

namespace Gallery\Structure\Api;

use OpenApi\Attributes as OA;

/**
 * Result of importing Danbooru tags for a media item
 * (POST /media/{media_id}/danbooru-tags). Doc-only schema.
 */
#[OA\Schema(schema: 'DanbooruFetchResult', description: 'Outcome of a Danbooru tag import.')]
class DanbooruFetchResult
{
    /** @var array<int, mixed> */
    #[OA\Property(type: 'array', items: new OA\Items(ref: '#/components/schemas/Tag'), description: "The media item's tags after import.")]
    public array $tags = [];
    /** @var array<int, mixed> */
    #[OA\Property(type: 'array', items: new OA\Items(ref: '#/components/schemas/Tag'), description: 'The full tag list (new tags may have been created).')]
    public array $all_tags = [];
    #[OA\Property(type: 'string', description: 'Lookup method used (md5 / iqdb / post).')]
    public string $method = '';
    #[OA\Property(type: 'integer')]
    public int $tags_applied = 0;
    #[OA\Property(type: 'integer')]
    public int $tags_created = 0;
}
