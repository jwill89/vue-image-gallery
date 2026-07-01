<?php

namespace Gallery\Structure\Api;

use OpenApi\Attributes as OA;

/**
 * A tag implication row: applying `tag_id` also applies `implied_tag_id`
 * (GET /tag-implications). Doc-only schema.
 */
#[OA\Schema(schema: 'TagImplication', description: 'A tag implication (tag -> implied tag).')]
class TagImplication
{
    #[OA\Property(type: 'integer')]
    public int $tag_id = 0;
    #[OA\Property(type: 'string')]
    public string $tag_name = '';
    #[OA\Property(type: 'integer')]
    public int $implied_tag_id = 0;
    #[OA\Property(type: 'string')]
    public string $implied_tag_name = '';
}
