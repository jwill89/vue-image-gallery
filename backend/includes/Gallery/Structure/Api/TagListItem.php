<?php

namespace Gallery\Structure\Api;

use OpenApi\Attributes as OA;

/**
 * A tag enriched with its category name and usage/implication counts, for the
 * tags-management page (GET /tags/display). Doc-only schema.
 */
#[OA\Schema(schema: 'TagListItem', description: 'A tag with category name and usage counts.')]
class TagListItem
{
    #[OA\Property(type: 'integer')]
    public int $tag_id = 0;
    #[OA\Property(type: 'string')]
    public string $tag_name = '';
    #[OA\Property(type: 'integer')]
    public int $category_id = 0;
    #[OA\Property(type: 'string', nullable: true)]
    public ?string $category_name = null;
    #[OA\Property(type: 'integer', description: 'Number of media items with this tag.')]
    public int $media_count = 0;
    #[OA\Property(type: 'integer', description: 'Number of implications originating from this tag.')]
    public int $implication_count = 0;
}
