<?php

namespace Gallery\Structure\Api;

use OpenApi\Attributes as OA;

/**
 * A single duplicate-pair match, with both media items summarized and the
 * distance/SSIM similarity scores. Doc-only schema.
 */
#[OA\Schema(schema: 'DuplicateMatch', description: 'A duplicate media pair with similarity scores.')]
class DuplicateMatch
{
    /** @var array<string, mixed> */
    #[OA\Property(
        type: 'object',
        properties: [
            new OA\Property(property: 'media_id', type: 'integer'),
            new OA\Property(property: 'file_name', type: 'string'),
            new OA\Property(property: 'hash', type: 'string'),
        ]
    )]
    public array $media_1 = [];
    /** @var array<string, mixed> */
    #[OA\Property(
        type: 'object',
        properties: [
            new OA\Property(property: 'media_id', type: 'integer'),
            new OA\Property(property: 'file_name', type: 'string'),
            new OA\Property(property: 'hash', type: 'string'),
        ]
    )]
    public array $media_2 = [];
    #[OA\Property(type: 'integer', nullable: true, description: 'Hamming distance between fingerprints.')]
    public ?int $distance = null;
    #[OA\Property(type: 'number', format: 'float', nullable: true, description: 'Structural similarity (SSIM) score.')]
    public ?float $ssim = null;
}
