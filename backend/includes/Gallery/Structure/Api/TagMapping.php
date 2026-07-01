<?php

namespace Gallery\Structure\Api;

use OpenApi\Attributes as OA;

/**
 * A Danbooru tag name -> gallery tag name mapping. Doc-only schema.
 */
#[OA\Schema(schema: 'TagMapping', description: 'A Danbooru-to-gallery tag name mapping.')]
class TagMapping
{
    #[OA\Property(type: 'integer')]
    public int $id = 0;
    #[OA\Property(type: 'string')]
    public string $danbooru_tag = '';
    #[OA\Property(type: 'string')]
    public string $gallery_tag = '';
}
