<?php

// CLI-only guard
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo 'This script can only be run from the command line.';
    exit(1);
}

// Error logging (no display in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('memory_limit', '1024M');

// Autoloader
require('vendor/autoload.php');

use Gallery\Core\DuplicateScanner;

// Set Time Limit for Script (unlimited)
set_time_limit(0);

// Instruct PHP to continue execution
ignore_user_abort(true);

// Run the scanner
$scanner = new DuplicateScanner(2, 'dupes/');
$result = $scanner->run();

// Output summary (CLI plain text)
echo "Images Compared: {$result['images_compared']}\n";
echo "Pairs Checked: " . number_format($result['pairs_checked']) . "\n";
echo "Duplicates Found: {$result['duplicates_found']}\n";
echo "Execution Time: {$result['execution_time_seconds']}s\n";
