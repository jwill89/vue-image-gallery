<?php

namespace Gallery\Core;

/**
 * Configuration class
 * This class contains configuration constants for the Gallery application.
 */
class Configuration
{
    public const string GALLERY_TITLE = 'Gallery';
    public const int DEFAULT_PER_PAGE = 40;

    /**
     * Allowed CORS origins. Add your production domain here.
     */
    public const array ALLOWED_ORIGINS = [
        'http://localhost',
        'http://localhost:5173',
        'https://localhost',
    ];
}
