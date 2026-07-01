<?php

declare(strict_types=1);

namespace Gallery\Tests\Unit;

use OpenApi\Generator;
use PHPUnit\Framework\TestCase;

/**
 * Guards the OpenAPI contract:
 *  - the `#[OA\*]` attributes across api/ + includes/ produce a valid document;
 *  - the committed openapi.json is up to date (regenerate with `composer docs`).
 */
final class OpenApiSpecTest extends TestCase
{
    private const string API_DIR = __DIR__ . '/../../api';
    private const string INCLUDES_DIR = __DIR__ . '/../../includes';
    private const string COMMITTED_SPEC = __DIR__ . '/../../openapi.json';

    /**
     * Scan the annotations and produce the spec object (matching `composer docs`).
     */
    private function generateSpec(): \OpenApi\Annotations\OpenApi
    {
        $generator = new Generator();
        $generator->setVersion('3.1.0');
        $spec = $generator->generate([self::API_DIR, self::INCLUDES_DIR]);

        if ($spec === null) {
            throw new \RuntimeException('OpenAPI generation produced no document.');
        }

        return $spec;
    }

    public function testSpecIsValid(): void
    {
        $spec = $this->generateSpec();
        $this->assertTrue($spec->validate(), 'Generated OpenAPI document failed validation.');
    }

    public function testCoreResourcesAreDocumented(): void
    {
        /** @var array<string, mixed> $spec */
        $spec = json_decode($this->generateSpec()->toJson(), true);

        foreach (['/media', '/media/{media_id}', '/tags', '/tag-categories', '/tag-implications', '/auth/login', '/version'] as $path) {
            $this->assertArrayHasKey($path, $spec['paths'], "Missing documented path: {$path}");
        }

        foreach (['Media', 'Tag', 'TagCategory', 'MediaPage', 'ErrorResponse'] as $schema) {
            $this->assertArrayHasKey($schema, $spec['components']['schemas'], "Missing schema: {$schema}");
        }

        $this->assertArrayHasKey('bearerAuth', $spec['components']['securitySchemes']);
    }

    public function testCommittedSpecIsUpToDate(): void
    {
        $this->assertFileExists(self::COMMITTED_SPEC, 'openapi.json is missing — run `composer docs`.');

        $fresh = json_decode($this->generateSpec()->toJson(), true);
        $committed = json_decode((string) file_get_contents(self::COMMITTED_SPEC), true);

        $this->assertEquals(
            $fresh,
            $committed,
            'backend/openapi.json is out of date — run `composer docs` and commit the result.'
        );
    }
}
