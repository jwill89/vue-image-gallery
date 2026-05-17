<?php

// CLI-only guard
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo 'This script can only be run from the command line.';
    exit(1);
}

// Required Autoloader
require_once('../vendor/autoload.php');

use Gallery\Core\DatabaseConnection;

// Create Database Connection
$db = DatabaseConnection::getInstance();

echo "Gallery - Database Creation Setup\n";
echo "==================================\n\n";

// Check if DB Exists and is Writable
$db_exists = file_exists('gallery.db');
$db_writeable = is_writable('gallery.db');

if (!$db_exists) {
    echo "[ERROR] Database file does not exist. Please create the gallery.db database file.\n";
    exit(1);
}

if (!$db_writeable) {
    echo "[ERROR] Database file is not writable. Please check the permissions.\n";
    exit(1);
}

echo "[OK] Database file exists and is writable.\n\n";

// Table definitions
$tables = [
    'images' => <<<SQL
    CREATE TABLE IF NOT EXISTS "images" (
        "image_id"	INTEGER NOT NULL UNIQUE,
        "file_name"	TEXT NOT NULL UNIQUE,
        "file_time"	INTEGER NOT NULL,
        "hash"	TEXT NOT NULL,
        "bits_fingerprint"	TEXT NOT NULL,
        PRIMARY KEY("image_id" AUTOINCREMENT)
    )
    SQL,

    'tag_categories' => <<<SQL
    CREATE TABLE IF NOT EXISTS "tag_categories" (
        "category_id"	INTEGER NOT NULL,
        "category_name"	TEXT NOT NULL UNIQUE COLLATE NOCASE,
        "category_short"	TEXT NOT NULL UNIQUE COLLATE NOCASE,
        PRIMARY KEY("category_id" AUTOINCREMENT)
    )
    SQL,

    'tags' => <<<SQL
    CREATE TABLE IF NOT EXISTS "tags" (
        "tag_id"	INTEGER NOT NULL UNIQUE,
        "category_id"	INTEGER NOT NULL DEFAULT 1,
        "tag_name"	TEXT NOT NULL UNIQUE COLLATE NOCASE,
        PRIMARY KEY("tag_id" AUTOINCREMENT),
        CONSTRAINT "fk__tags__tag_categories" FOREIGN KEY("category_id") REFERENCES "tag_categories"("category_id")
    )
    SQL,

    'videos' => <<<SQL
    CREATE TABLE IF NOT EXISTS "videos" (
        "video_id"	INTEGER NOT NULL UNIQUE,
        "file_name"	TEXT NOT NULL UNIQUE,
        "file_time"	INTEGER NOT NULL,
        "hash"	TEXT NOT NULL,
        PRIMARY KEY("video_id" AUTOINCREMENT)
    )
    SQL,

    'image_tags' => <<<SQL
    CREATE TABLE IF NOT EXISTS "image_tags" (
        "image_id"	INTEGER NOT NULL,
        "tag_id"	INTEGER NOT NULL,
        CONSTRAINT "PRIMARY" PRIMARY KEY("image_id","tag_id"),
        CONSTRAINT "FK__image_tags__images" FOREIGN KEY("image_id") REFERENCES "images"("image_id") ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT "FK__image_tags__tags" FOREIGN KEY("tag_id") REFERENCES "tags"("tag_id") ON DELETE CASCADE ON UPDATE CASCADE
    )
    SQL,

    'video_tags' => <<<SQL
    CREATE TABLE IF NOT EXISTS "video_tags" (
        "video_id"	INTEGER NOT NULL,
        "tag_id"	INTEGER NOT NULL,
        CONSTRAINT "PRIMARY" PRIMARY KEY("video_id","tag_id"),
        CONSTRAINT "FK__video_tags__videos" FOREIGN KEY("video_id") REFERENCES "videos"("video_id") ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT "FK__video_tags__tags" FOREIGN KEY("tag_id") REFERENCES "tags"("tag_id") ON DELETE CASCADE ON UPDATE CASCADE
    )
    SQL,

    'rate_limits' => <<<SQL
    CREATE TABLE IF NOT EXISTS "rate_limits" (
        "ip" TEXT NOT NULL,
        "requested_at" INTEGER NOT NULL
    )
    SQL,

    'auth_tokens' => <<<SQL
    CREATE TABLE IF NOT EXISTS "auth_tokens" (
        "token" TEXT NOT NULL PRIMARY KEY,
        "created_at" INTEGER NOT NULL
    )
    SQL,
];

// Create tables
foreach ($tables as $name => $sql) {
    echo "Creating table '$name'... ";
    $success = $db->exec($sql);
    if ($success !== false) {
        echo "[OK]\n";
    } else {
        echo "[ERROR] " . $db->errorInfo()[2] . "\n";
    }
}

// Insert default tag categories
echo "\nInserting default tag categories... ";
$sql = <<<SQL
INSERT OR IGNORE INTO "tag_categories" ("category_id", "category_name", "category_short") 
    VALUES (1, 'General', 'g'),
           (2, 'Artist', 'a'),
           (3, 'Character', 'c'),
           (4, 'Source', 's'),
           (5, 'Personal List', 'p')
SQL;
$success = $db->exec($sql);
echo ($success !== false) ? "[OK]\n" : "[ERROR] " . $db->errorInfo()[2] . "\n";

// Create indexes
echo "\nCreating indexes...\n";
$indexes = [
    'idx_images_hash' => 'CREATE INDEX IF NOT EXISTS idx_images_hash ON images(hash)',
    'idx_videos_hash' => 'CREATE INDEX IF NOT EXISTS idx_videos_hash ON videos(hash)',
    'idx_rate_limits_ip_time' => 'CREATE INDEX IF NOT EXISTS idx_rate_limits_ip_time ON rate_limits(ip, requested_at)',
];

foreach ($indexes as $name => $sql) {
    echo "  Index '$name'... ";
    $success = $db->exec($sql);
    echo ($success !== false) ? "[OK]\n" : "[ERROR] " . $db->errorInfo()[2] . "\n";
}

echo "\nSetup complete.\n";
