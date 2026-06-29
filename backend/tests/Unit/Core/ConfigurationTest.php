<?php

declare(strict_types=1);

namespace Gallery\Tests\Unit\Core;

use Gallery\Core\Configuration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(Configuration::class)]
final class ConfigurationTest extends TestCase
{
    private const array KEYS = [
        'GALLERY_ADMIN_PASSWORD',
        'GALLERY_URL',
        'GALLERY_ALLOWED_ORIGINS',
        'DANBOORU_LOGIN',
        'DANBOORU_API_KEY',
        'FONTAWESOME_KIT_ID',
    ];

    protected function setUp(): void
    {
        // Mark .env as already loaded so Configuration never reads a real .env file;
        // each test then fully controls the environment via $_ENV / putenv.
        (new ReflectionClass(Configuration::class))->getProperty('envLoaded')->setValue(null, true);
        $this->clearEnv();
    }

    protected function tearDown(): void
    {
        $this->clearEnv();
    }

    private function clearEnv(): void
    {
        foreach (self::KEYS as $key) {
            unset($_ENV[$key], $_SERVER[$key]);
            putenv($key);
        }
    }

    private function setEnv(string $key, string $value): void
    {
        $_ENV[$key] = $value;
        putenv("{$key}={$value}");
    }

    public function testAdminPasswordFallsBackToChangemeWhenUnset(): void
    {
        $this->assertSame('changeme', Configuration::getAdminPassword());
        $this->assertFalse(Configuration::isAdminConfigured());
    }

    public function testAdminPasswordReadsConfiguredValue(): void
    {
        $this->setEnv('GALLERY_ADMIN_PASSWORD', 's3cret');

        $this->assertSame('s3cret', Configuration::getAdminPassword());
        $this->assertTrue(Configuration::isAdminConfigured());
    }

    public function testAllowedOriginsDefaultsToLocalhostWhenUnset(): void
    {
        $this->assertSame(
            ['http://localhost', 'http://localhost:5173', 'https://localhost'],
            Configuration::getAllowedOrigins()
        );
    }

    public function testAllowedOriginsParsesCommaSeparatedListAndTrims(): void
    {
        $this->setEnv('GALLERY_ALLOWED_ORIGINS', 'https://a.example , https://b.example');

        $this->assertSame(
            ['https://a.example', 'https://b.example'],
            Configuration::getAllowedOrigins()
        );
    }

    public function testGalleryUrlTrimsTrailingSlash(): void
    {
        $this->setEnv('GALLERY_URL', 'https://gallery.example.com/');
        $this->assertSame('https://gallery.example.com', Configuration::getGalleryUrl());
    }

    public function testGalleryUrlEmptyByDefault(): void
    {
        $this->assertSame('', Configuration::getGalleryUrl());
    }

    public function testDanbooruAndFontAwesomeGettersDefaultToEmpty(): void
    {
        $this->assertSame('', Configuration::getDanbooruLogin());
        $this->assertSame('', Configuration::getDanbooruApiKey());
        $this->assertSame('', Configuration::getFontAwesomeKitId());
    }

    public function testDanbooruAndFontAwesomeGettersReadConfiguredValues(): void
    {
        $this->setEnv('DANBOORU_LOGIN', 'user');
        $this->setEnv('DANBOORU_API_KEY', 'key123');
        $this->setEnv('FONTAWESOME_KIT_ID', 'abc');

        $this->assertSame('user', Configuration::getDanbooruLogin());
        $this->assertSame('key123', Configuration::getDanbooruApiKey());
        $this->assertSame('abc', Configuration::getFontAwesomeKitId());
    }
}
