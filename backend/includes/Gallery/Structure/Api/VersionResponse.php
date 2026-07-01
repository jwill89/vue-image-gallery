<?php

namespace Gallery\Structure\Api;

use OpenApi\Attributes as OA;

/**
 * Response for GET /version. Doc-only schema.
 */
#[OA\Schema(schema: 'VersionResponse', description: 'Application and API version.')]
class VersionResponse
{
    #[OA\Property(type: 'string', description: 'Application version (SemVer).')]
    public string $version = '';
    #[OA\Property(type: 'string', description: 'HTTP API contract version (SemVer).')]
    public string $api_version = '';
}
