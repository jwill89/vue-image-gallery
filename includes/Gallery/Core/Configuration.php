<?php

namespace Gallery\Core;

/**
 * Configuration class
 * This class contains configuration constants for the Gallery application.
 */
class Configuration
{
    public const int DEFAULT_PER_PAGE = 40;

    /**
     * Password for protected endpoints (duplicates page).
     * Change this to a secure value in production.
     */
    public const string ADMIN_PASSWORD = 'changeme';

    /**
     * Allowed CORS origins. Add your production domain here.
     */
    public const array ALLOWED_ORIGINS = [
        'http://localhost',
        'http://localhost:5173',
        'https://localhost',
    ];
}
