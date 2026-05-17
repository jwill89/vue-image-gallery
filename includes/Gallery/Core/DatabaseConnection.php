<?php

namespace Gallery\Core;

use PDO;

/**
 * DatabaseConnection class
 *
 * This class is responsible for creating a singleton instance of the database connection.
 * It uses the PDO extension to connect to a SQLite database.
 */
class DatabaseConnection
{
    // Path to the SQLite database file
    private const string PATH_TO_SQLITE_DB = "../db/gallery.db";
    private const string CRON_PATH_TO_SQLITE_DB = "db/gallery.db";

    // Access Through Connection
    private static PDO $conn;

    // Prevent New Object Instantiation
    private function __construct()
    {
    }

    // Prevent cloning
    private function __clone()
    {
    }

    /**
     * getInstance function
     *
     * This function returns a singleton instance of the database connection.
     * It checks if the connection is already established, and if not, it creates a new PDO instance.
     * It determines the correct path by checking if the database file exists at each location.
     *
     * @return PDO The PDO instance representing the database connection.
     */
    public static function getInstance(): PDO
    {
        // If the connection isn't set, set it.
        if (!isset(self::$conn)) {
            // Determine the correct path by checking file existence
            if (file_exists(self::PATH_TO_SQLITE_DB)) {
                $db_path = self::PATH_TO_SQLITE_DB;
            } elseif (file_exists(self::CRON_PATH_TO_SQLITE_DB)) {
                $db_path = self::CRON_PATH_TO_SQLITE_DB;
            } else {
                // Fallback: use the default API path (will create the file if needed)
                $db_path = self::PATH_TO_SQLITE_DB;
            }

            self::$conn = new PDO("sqlite:" . $db_path);
            self::$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$conn->exec('PRAGMA journal_mode=WAL');
            self::$conn->exec('PRAGMA foreign_keys=ON');
        }

        return self::$conn;
    }
}
