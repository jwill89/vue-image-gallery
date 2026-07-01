<?php

namespace Gallery\Structure\Api;

use OpenApi\Attributes as OA;

/**
 * The overall result of an upload request (POST /media). Doc-only schema.
 */
#[OA\Schema(schema: 'UploadSummary', description: 'Aggregate upload result.')]
class UploadSummary
{
    /** @var array<int, mixed> */
    #[OA\Property(type: 'array', items: new OA\Items(ref: '#/components/schemas/UploadResult'))]
    public array $results = [];
    #[OA\Property(type: 'integer')]
    public int $total_uploaded = 0;
    #[OA\Property(type: 'integer')]
    public int $total_duplicates = 0;
    #[OA\Property(type: 'integer')]
    public int $total_failed = 0;
    #[OA\Property(type: 'integer', description: 'Total Danbooru tags applied (only when fetch_tags was set).')]
    public int $total_tags_applied = 0;
}
