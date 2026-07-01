<?php

namespace Gallery\Structure\Api;

use OpenApi\Attributes as OA;

/**
 * Response for POST /auth/login. Doc-only schema.
 */
#[OA\Schema(schema: 'LoginResponse', description: 'Issued admin bearer token.')]
class LoginResponse
{
    #[OA\Property(type: 'string', description: 'Bearer token, valid for 24 hours.')]
    public string $token = '';
}
