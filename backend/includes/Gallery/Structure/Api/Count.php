<?php

namespace Gallery\Structure\Api;

use OpenApi\Attributes as OA;

/**
 * A simple `{ count }` wrapper (e.g. GET /media/count). Doc-only schema.
 */
#[OA\Schema(schema: 'Count', description: 'A count wrapper.')]
class Count
{
    #[OA\Property(type: 'integer')]
    public int $count = 0;
}
