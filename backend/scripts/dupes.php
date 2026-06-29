<?php

// CLI-only guard
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo 'This script can only be run from the command line.';
    exit(1);
}

// Set working directory to project root so all relative paths resolve correctly
chdir(__DIR__ . '/..');

// Error logging (no display in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('memory_limit', '1024M');

// Autoloader
require __DIR__ . '/../vendor/autoload.php';

use Gallery\Core\DuplicateScanner;

// Set Time Limit for Script (unlimited)
set_time_limit(0);

// Instruct PHP to continue execution
ignore_user_abort(true);

// Run the scanner (built from the shared DI container; defaults match: maxDistance 2, dupes/)
$container = (new \DI\ContainerBuilder())->addDefinitions(__DIR__ . '/../api/dependencies.php')->build();
$scanner = $container->get(DuplicateScanner::class);
$result = $scanner->run();

// Output summary (CLI plain text)
echo "Images Compared: {$result['images_compared']}\n";
echo "LSH Candidates: " . number_format($result['lsh_candidates']) . "\n";
echo "Duplicates Found: {$result['duplicates_found']}\n";
echo "Execution Time: {$result['execution_time_seconds']}s\n";
