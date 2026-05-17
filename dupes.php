<?php

// Debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('memory_limit', '1024M');

// Autoloader
require('vendor/autoload.php');

use Jenssegers\ImageHash\Hash;
use Jenssegers\ImageHash\ImageHash;
use Jenssegers\ImageHash\Implementations\DifferenceHash;
use Gallery\Collection\ImageCollection;
use Gallery\Structure\Image;

// Set Time Limit for Script (unlimited)
set_time_limit(0);

// Instruct PHP to continue execution
ignore_user_abort(true);

// Set Start Time
$start_time = microtime(true);

// Image Comparison Hasher
$hasher = new ImageHash(new DifferenceHash());

// Get the images already in the database
$image_collection = new ImageCollection();
$images_in_database = $image_collection->getAll();
$image_count = count($images_in_database);

// Pre-compute all hashes once (avoid repeated Hash::fromBits calls)
$hashes = [];
foreach ($images_in_database as $img) {
    try {
        $hashes[$img->getImageId()] = Hash::fromBits($img->getBitsFingerprint());
    } catch (Exception $e) {
        // Skip images with invalid fingerprints
        continue;
    }
}

// Matches Array
$matches = [];

// Track seen pairs with a hash set for O(1) lookup
$seen_pairs = [];

// Compare each unique pair only once: i < j
// This reduces comparisons from N*N to N*(N-1)/2
$image_ids = array_keys($hashes);
$hash_count = count($image_ids);

for ($i = 0; $i < $hash_count; $i++) {
    for ($j = $i + 1; $j < $hash_count; $j++) {
        $id1 = $image_ids[$i];
        $id2 = $image_ids[$j];

        $distance = $hasher->distance($hashes[$id1], $hashes[$id2]);

        if ($distance <= 2) {
            $matches[] = [$id1, $id2, $distance];
        }
    }
}

// End Script Time
$end_time = microtime(true);
$execution_time = round($end_time - $start_time, 2);

// Save to File if we have matches
if (!empty($matches)) {
    // Ensure dupes directory exists
    if (!is_dir('dupes')) {
        if (!mkdir('dupes', 0755, true) && !is_dir('dupes')) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', 'dupes'));
        }
    }

    $output = [
        'generated_at' => date('Y-m-d H:i:s'),
        'images_compared' => $hash_count,
        'pairs_checked' => ($hash_count * ($hash_count - 1)) / 2,
        'duplicates_found' => count($matches),
        'execution_time_seconds' => $execution_time,
        'matches' => $matches
    ];

    file_put_contents(
        'dupes/dupes-' . date('Y-m-d') . '.json',
        json_encode($output, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)
    );
}

// Output summary (visible when run in browser)
echo "<strong>Images Compared</strong>: {$hash_count}<br/>";
echo "<strong>Pairs Checked</strong>: " . number_format(($hash_count * ($hash_count - 1)) / 2) . "<br/>";
echo "<strong>Duplicates Found</strong>: " . count($matches) . "<br/>";
echo "<strong>Execution Time</strong>: {$execution_time}s<br/>";
