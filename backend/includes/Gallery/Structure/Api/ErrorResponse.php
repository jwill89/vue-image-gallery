<?php

namespace Gallery\Structure\Api;

use OpenApi\Attributes as OA;

/**
 * The standard error envelope returned on any 4xx/5xx response.
 *
 * Doc-only schema: these classes exist purely to describe response shapes to
 * swagger-php; they are never instantiated.
 */
#[OA\Schema(schema: 'ErrorResponse', description: 'Standard error envelope.')]
class ErrorResponse
{
    #[OA\Property(type: 'string', description: 'Stable PascalCase machine code (e.g. MediaNotFound).')]
    public string $error = '';
    #[OA\Property(type: 'string', description: 'Human-readable message for display.')]
    public string $message = '';
}
