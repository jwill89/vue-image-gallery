<?php

// Required Autoloader
require_once('../vendor/autoload.php');

use Gallery\Core\DatabaseConnection;

// No DB Error
$db_exists = false;
$db_writeable = false;

// Create Database Connection
// NOTE: If the db file does not exist, it should be created automatically by the PDO SQLite driver.
$db = DatabaseConnection::getInstance();

echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<title>Gallery - Create Database Structure</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/default.min.css">
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
<script>hljs.initHighlightingOnLoad();</script>
<style>
    .db-success {
        color:rgb(59, 117, 0);
        font-weight: bold;
    }
    .db-error {
        color:rgb(104, 0, 0);
        font-weight: bold;
    }
</style>
</head>
<body>
    <h1>Gallery - Database Creation Setup</h1>
    <p>This script will create the database structure for the Gallery application.</p>
    <p><strong>Note</strong>: You do not need to create the file manually, the script will create the DB file automatically with proper permissions.</p>
    <h3>Connecting to Database</h3>
HTML;

// Check if DB Exists and is Writable
$db_exists = file_exists('gallery.db');
$db_writeable = is_writable('gallery.db');

if (!$db_exists) {
    echo "<p class='db-error'>Database file does not exist. Please create the <code>gallery.db</code> database file.</p>";
} elseif (!$db_writeable) {
    echo "<p class='db-error'>Database file is not writable. Please check the permissions of the <code>gallery.db</code> database file.</p>";
} else {
    echo <<<HTML
        <p class='db-success'>Database file exists and is writable.</p>
        <h3>Creating Table 'images'</h3>
    HTML;

    // **********************************************
    // * Table Structure for primary table 'images' *
    // **********************************************

    // Setup the SQL for the table creation
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS "images" (
        "image_id"	INTEGER NOT NULL UNIQUE,
        "file_name"	TEXT NOT NULL UNIQUE,
        "file_time"	INTEGER NOT NULL,
        "hash"	TEXT NOT NULL,
        "bits_fingerprint"	TEXT NOT NULL,
        PRIMARY KEY("image_id" AUTOINCREMENT)
    )
    SQL;

    // Execute SQL
    $success = $db->exec($sql);

    echo <<<HTML
        <pre><code class="language-sql">$sql</code></pre>    
    HTML;

    if ($success !== false) {
        echo "<p class='db-success'>Table 'images' created successfully.</p>";
    } else {
        echo "<p class='db-error'>Error creating table 'images'. SQLite Code: " . $db->errorInfo()[1] . ", " . $db->errorInfo()[0] . " - " . $db->errorInfo()[2] . "</p>";
    }

    echo <<<HTML
        <h3>Creating Table 'tag_categories'</h3>
    HTML;

    // ******************************************************
    // * Table Structure for primary table 'tag_categories' *
    // ******************************************************

    // Setup the SQL for the table creation
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS "tag_categories" (
        "category_id"	INTEGER NOT NULL,
        "category_name"	TEXT NOT NULL UNIQUE COLLATE NOCASE,
        "category_short"	TEXT NOT NULL UNIQUE COLLATE NOCASE,
        PRIMARY KEY("category_id" AUTOINCREMENT)
    )
    SQL;

    // Execute SQL
    $success = $db->exec($sql);

    echo <<<HTML
        <pre><code class="language-sql">$sql</code></pre>    
    HTML;

    if ($success !== false) {
        echo "<p class='db-success'>Table 'tag_categories' created successfully.</p>";
    } else {
        echo "<p class='db-error'>Error creating table 'tag_categories'. SQLite Code: " . $db->errorInfo()[1] . ", " . $db->errorInfo()[0] . " - " . $db->errorInfo()[2] . "</p>";
    }

    echo <<<HTML
        <h3>Adding Table Data for Table 'tag_categories'</h3>
    HTML;

    // *************************************************
    // * Table Data for primary table 'tag_categories' *
    // *************************************************

    // Setup the SQL for the table creation
    $sql = <<<SQL
    INSERT INTO "tag_categories" ("category_id", "category_name", "category_short") 
        VALUES (1, 'General', 'g'),
               (2, 'Artist', 'a'),
               (3, 'Character', 'c'),
               (4, 'Source', 's'),
               (5, 'Personal List', 'p')
    SQL;

    // Execute SQL
    $success = $db->exec($sql);

    echo <<<HTML
        <pre><code class="language-sql">$sql</code></pre>    
    HTML;

    if ($success !== false) {
        echo "<p class='db-success'>Table 'tag_categories' created successfully.</p>";
    } else {
        echo "<p class='db-error'>Error creating table 'tag_categories'. SQLite Code: " . $db->errorInfo()[1] . ", " . $db->errorInfo()[0] . " - " . $db->errorInfo()[2] . "</p>";
    }

    echo <<<HTML
        <h3>Creating Table 'tags'</h3>
    HTML;

    // ********************************************
    // * Table Structure for primary table 'tags' *
    // ********************************************

    // Setup the SQL for the table creation
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS "tags" (
        "tag_id"	INTEGER NOT NULL UNIQUE,
        "category_id"	INTEGER NOT NULL DEFAULT 1,
        "tag_name"	TEXT NOT NULL UNIQUE COLLATE NOCASE,
        PRIMARY KEY("tag_id" AUTOINCREMENT),
        CONSTRAINT "fk__tags__tag_categories" FOREIGN KEY("category_id") REFERENCES "tag_categories"("category_id")
    )
    SQL;

    // Execute SQL
    $success = $db->exec($sql);

    echo <<<HTML
        <pre><code class="language-sql">$sql</code></pre>    
    HTML;

    if ($success !== false) {
        echo "<p class='db-success'>Table 'tags' created successfully.</p>";
    } else {
        echo "<p class='db-error'>Error creating table 'tags'. SQLite Code: " . $db->errorInfo()[1] . ", " . $db->errorInfo()[0] . " - " . $db->errorInfo()[2] . "</p>";
    }

    echo <<<HTML
        <h3>Creating Table 'videos'</h3>
    HTML;

    // **********************************************
    // * Table Structure for primary table 'videos' *
    // **********************************************

    // Setup the SQL for the table creation
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS "videos" (
        "video_id"	INTEGER NOT NULL UNIQUE,
        "file_name"	TEXT NOT NULL UNIQUE,
        "file_time"	INTEGER NOT NULL,
        "hash"	TEXT NOT NULL,
        PRIMARY KEY("video_id" AUTOINCREMENT)
    )
    SQL;

    // Execute SQL
    $success = $db->exec($sql);

    echo <<<HTML
        <pre><code class="language-sql">$sql</code></pre>    
    HTML;

    if ($success !== false) {
        echo "<p class='db-success'>Table 'videos' created successfully.</p>";
    } else {
        echo "<p class='db-error'>Error creating table 'videos'. SQLite Code: " . $db->errorInfo()[1] . ", " . $db->errorInfo()[0] . " - " . $db->errorInfo()[2] . "</p>";
    }

    echo <<<HTML
        <h3>Creating Table 'image_tags'</h3>
    HTML;

    // *****************************************************
    // * Table Structure for relational table 'image_tags' *
    // *****************************************************

    // Setup the SQL for the table creation
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS "image_tags" (
        "image_id"	INTEGER NOT NULL,
        "tag_id"	INTEGER NOT NULL,
        CONSTRAINT "PRIMARY" PRIMARY KEY("image_id","tag_id"),
        CONSTRAINT "FK__image_tags__images" FOREIGN KEY("image_id") REFERENCES "images"("image_id") ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT "FK__image_tags__tags" FOREIGN KEY("tag_id") REFERENCES "tags"("tag_id") ON DELETE CASCADE ON UPDATE CASCADE
    )
    SQL;

    // Execute SQL
    $success = $db->exec($sql);

    echo <<<HTML
        <pre><code class="language-sql">$sql</code></pre>    
    HTML;

    if ($success !== false) {
        echo "<p class='db-success'>Table 'image_tags' created successfully.</p>";
    } else {
        echo "<p class='db-error'>Error creating table 'image_tags'. SQLite Code: " . $db->errorInfo()[1] . ", " . $db->errorInfo()[0] . " - " . $db->errorInfo()[2] . "</p>";
    }

    echo <<<HTML
        <h3>Creating Table 'video_tags'</h3>
    HTML;

    // *****************************************************
    // * Table Structure for relational table 'video_tags' *
    // *****************************************************

    // Setup the SQL for the table creation
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS "video_tags" (
        "video_id"	INTEGER NOT NULL,
        "tag_id"	INTEGER NOT NULL,
        CONSTRAINT "PRIMARY" PRIMARY KEY("video_id","tag_id"),
        CONSTRAINT "FK__video_tags__videos" FOREIGN KEY("video_id") REFERENCES "videos"("video_id") ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT "FK__video_tags__tags" FOREIGN KEY("tag_id") REFERENCES "tags"("tag_id") ON DELETE CASCADE ON UPDATE CASCADE
        
    )
    SQL;

    // Execute SQL
    $success = $db->exec($sql);

    echo <<<HTML
        <pre><code class="language-sql">$sql</code></pre>    
    HTML;

    if ($success !== false) {
        echo "<p class='db-success'>Table 'video_tags' created successfully.</p>";
    } else {
        echo "<p class='db-error'>Error creating table 'video_tags'. SQLite Code: " . $db->errorInfo()[1] . ", " . $db->errorInfo()[0] . " - " . $db->errorInfo()[2] . "</p>";
    }
}

echo <<<HTML
</body>
</html>
HTML;
