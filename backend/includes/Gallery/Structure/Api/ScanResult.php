<?php

namespace Gallery\Structure\Api;

use OpenApi\Attributes as OA;

/**
 * Summary stats from a duplicate scan (POST /duplicates/scan). Doc-only schema.
 */
#[OA\Schema(schema: 'ScanResult', description: 'Duplicate-scan summary statistics.')]
class ScanResult
{
    #[OA\Property(type: 'boolean')]
    public bool $success = true;
    #[OA\Property(type: 'string')]
    public string $message = '';
    #[OA\Property(type: 'integer')]
    public int $images_compared = 0;
    #[OA\Property(type: 'integer')]
    public int $lsh_candidates = 0;
    #[OA\Property(type: 'integer')]
    public int $duplicates_found = 0;
    #[OA\Property(type: 'number', format: 'float', description: 'Execution time in seconds.')]
    public float $execution_time = 0.0;
}
