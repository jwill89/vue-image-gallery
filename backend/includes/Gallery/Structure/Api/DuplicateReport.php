<?php

namespace Gallery\Structure\Api;

use OpenApi\Attributes as OA;

/**
 * The latest duplicate-scan report (GET /duplicates/report), with dismissed
 * pairs filtered out. Doc-only schema.
 */
#[OA\Schema(schema: 'DuplicateReport', description: 'The latest duplicate-detection report.')]
class DuplicateReport
{
    #[OA\Property(type: 'string')]
    public string $report_file = '';
    #[OA\Property(type: 'string', nullable: true, description: 'ISO timestamp the report was generated.')]
    public ?string $generated_at = null;
    #[OA\Property(type: 'integer', nullable: true)]
    public ?int $images_compared = null;
    #[OA\Property(type: 'integer')]
    public int $duplicates_found = 0;
    /** @var array<int, mixed> */
    #[OA\Property(type: 'array', items: new OA\Items(ref: '#/components/schemas/DuplicateMatch'))]
    public array $matches = [];
}
