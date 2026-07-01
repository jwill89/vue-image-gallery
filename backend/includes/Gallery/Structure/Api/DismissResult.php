<?php

namespace Gallery\Structure\Api;

use OpenApi\Attributes as OA;

/**
 * Result of dismissing a duplicate pair (POST /duplicates/dismissals).
 * Doc-only schema.
 */
#[OA\Schema(schema: 'DismissResult', description: 'A dismissed duplicate pair.')]
class DismissResult
{
    #[OA\Property(type: 'boolean')]
    public bool $dismissed = true;
    #[OA\Property(type: 'integer')]
    public int $media_id_1 = 0;
    #[OA\Property(type: 'integer')]
    public int $media_id_2 = 0;
}
