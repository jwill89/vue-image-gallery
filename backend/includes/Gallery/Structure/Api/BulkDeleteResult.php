<?php

namespace Gallery\Structure\Api;

use OpenApi\Attributes as OA;

/**
 * Result of a bulk media delete (POST /media/bulk-delete). Doc-only schema.
 */
#[OA\Schema(schema: 'BulkDeleteResult', description: 'Outcome of a bulk media delete.')]
class BulkDeleteResult
{
    /** @var array<int, int> */
    #[OA\Property(type: 'array', items: new OA\Items(type: 'integer'), description: 'IDs successfully deleted.')]
    public array $deleted = [];
    /** @var array<int, int> */
    #[OA\Property(type: 'array', items: new OA\Items(type: 'integer'), description: 'IDs that could not be deleted.')]
    public array $failed = [];
    #[OA\Property(type: 'integer')]
    public int $total_deleted = 0;
}
