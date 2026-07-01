<?php

namespace Gallery\Structure\Api;

use OpenApi\Attributes as OA;

/**
 * A Danbooru category -> gallery category mapping. Doc-only schema.
 */
#[OA\Schema(schema: 'CategoryMapping', description: 'A Danbooru-to-gallery category mapping.')]
class CategoryMapping
{
    #[OA\Property(type: 'integer')]
    public int $danbooru_category_id = 0;
    #[OA\Property(type: 'string')]
    public string $danbooru_category_name = '';
    #[OA\Property(type: 'integer')]
    public int $gallery_category_id = 0;
    #[OA\Property(type: 'string', nullable: true)]
    public ?string $gallery_category_name = null;
}
