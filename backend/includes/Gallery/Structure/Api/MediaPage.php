<?php

namespace Gallery\Structure\Api;

use OpenApi\Attributes as OA;

/**
 * A paginated page of media items (GET /media). Doc-only schema.
 */
#[OA\Schema(schema: 'MediaPage', description: 'A paginated page of media items.')]
class MediaPage
{
    /** @var array<int, mixed> */
    #[OA\Property(type: 'array', items: new OA\Items(ref: '#/components/schemas/Media'))]
    public array $items = [];
    #[OA\Property(type: 'integer')]
    public int $total_pages = 0;
    #[OA\Property(type: 'integer')]
    public int $current_page = 0;
}
