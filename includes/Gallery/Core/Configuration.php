<?php

namespace Gallery\Core;

/**
 * Configuration class
 * This class contains configuration constants and environment-based settings for the Gallery application.
 */
class Configuration
{
    public const int DEFAULT_PER_PAGE = 40;

    /**
     * Default allowed CORS origins (used if GALLERY_ALLOWED_ORIGINS env var is not set).
     */
    private const array DEFAULT_ALLOWED_ORIGINS = [
        'http://localhost',
        'http://localhost:5173',
        'https://localhost',
    ];

    /**
     * Get the admin password from environment variable.
     * Falls back to 'changeme' if not set (development only).
     *
     * Set the GALLERY_ADMIN_PASSWORD environment variable in production.
     */
    public static function getAdminPassword(): string
    {
        return self::getEnvVar('GALLERY_ADMIN_PASSWORD') ?? 'changeme';
    }

    /**
     * Resolve an environment variable from all possible sources.
     * Apache SetEnv with mod_rewrite prefixes vars with REDIRECT_ (possibly multiple times).
     */
    private static function getEnvVar(string $name): ?string
    {
        // Check $_ENV
        if (isset($_ENV[$name]) && $_ENV[$name] !== '') {
            return $_ENV[$name];
        }

        // Check getenv()
        $val = getenv($name);
        if ($val !== false && $val !== '') {
            return $val;
        }

        // Check apache_getenv() if available
        if (function_exists('apache_getenv')) {
            $val = apache_getenv($name);
            if ($val !== false && $val !== '') {
                return $val;
            }
        }

        // Check $_SERVER with possible REDIRECT_ prefixes from mod_rewrite
        foreach ($_SERVER as $key => $value) {
            if (preg_match('/^(?:REDIRECT_)*' . preg_quote($name, '/') . '$/', $key)) {
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * Get allowed CORS origins from environment variable.
     * The env var GALLERY_ALLOWED_ORIGINS should be a comma-separated list of origins.
     * Falls back to default localhost origins if not set.
     */
    public static function getAllowedOrigins(): array
    {
        $envOrigins = self::getEnvVar('GALLERY_ALLOWED_ORIGINS') ?? '';

        if (!empty($envOrigins)) {
            return array_map('trim', explode(',', $envOrigins));
        }

        return self::DEFAULT_ALLOWED_ORIGINS;
    }
}
