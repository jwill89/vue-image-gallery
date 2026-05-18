<?php

// CLI-only guard
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo 'This script can only be run from the command line.';
    exit(1);
}

// Error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Autoloader
require('vendor/autoload.php');

// DB and Image functions
use Gallery\Collection\ImageCollection;
use Gallery\Collection\VideoCollection;
use Gallery\Core\DatabaseConnection;
use Gallery\Structure\Image;
use Gallery\Structure\Video;

// Set Time Limit for Script, 10 minutes
set_time_limit(600);

// Set Start Time
$start_time = microtime(true);

// Setup Collections
$image_collection = new ImageCollection();
$video_collection = new VideoCollection();

// Directory Constants
$image_dir_full = ImageCollection::IMAGE_DIRECTORY_FULL;
$image_dir_thumbs = ImageCollection::IMAGE_DIRECTORY_THUMBNAILS;
$video_dir_full = VideoCollection::VIDEO_DIRECTORY_FULL;
$video_dir_thumbs = VideoCollection::VIDEO_DIRECTORY_THUMBNAILS;

// ============================================================
// Scan Input Folders (new files waiting to be processed)
// ============================================================

// Get new images in the input folder (excludes directories)
$images_in_folder = array_values(array_filter(
    scandir(ImageCollection::IMAGE_DIRECTORY),
    static fn($item) => !is_dir(ImageCollection::IMAGE_DIRECTORY . $item)
));

// Get new videos in the input folder (excludes directories)
$videos_in_folder = array_values(array_filter(
    scandir(VideoCollection::VIDEO_DIRECTORY),
    static fn($item) => !is_dir(VideoCollection::VIDEO_DIRECTORY . $item)
));

// ============================================================
// Load Database Records & Build Lookup Maps
// ============================================================

// Get lightweight summaries (only id, file_name, hash) instead of full objects
$image_summaries = $image_collection->getAllSummary();
$video_summaries = $video_collection->getAllSummary();

// Build hash maps and filename sets for O(1) lookups
$image_hashes = [];      // MD5 hash => true
$image_filenames = [];   // filename => true (files that exist in full/)

foreach ($image_summaries as $img) {
    $image_hashes[$img['hash']] = true;
    $image_filenames[$img['file_name']] = true;
}

$video_hashes = [];
$video_filenames = [];

foreach ($video_summaries as $vid) {
    $video_hashes[$vid['hash']] = true;
    $video_filenames[$vid['file_name']] = true;
}

// ============================================================
// Initialize Counters
// ============================================================
$images_added = 0;
$images_removed = 0;
$images_skipped = 0;
$videos_added = 0;
$videos_removed = 0;
$videos_skipped = 0;

// ============================================================
// Remove Orphaned Database Entries
// (DB records whose files no longer exist on disk)
// ============================================================

$db = DatabaseConnection::getInstance();
$db->beginTransaction();

try {
    foreach ($image_summaries as $img) {
        if (!file_exists($image_dir_full . $img['file_name'])) {
            // Load full object only for the orphan we're deleting
            $image_obj = $image_collection->get($img['image_id']);
            if ($image_obj !== null && $image_collection->delete($image_obj)) {
                $images_removed++;
                unset($image_hashes[$img['hash']], $image_filenames[$img['file_name']]);
            }
        }
    }

    foreach ($video_summaries as $vid) {
        if (!file_exists($video_dir_full . $vid['file_name'])) {
            $video_obj = $video_collection->get($vid['video_id']);
            if ($video_obj !== null && $video_collection->delete($video_obj)) {
                $videos_removed++;
                unset($video_hashes[$vid['hash']], $video_filenames[$vid['file_name']]);
            }
        }
    }

    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    echo "[ERROR] Failed to remove orphaned entries: " . $e->getMessage() . "\n";
}

// Free summary arrays (no longer needed)
unset($image_summaries, $video_summaries);

// ============================================================
// Process New Images
// ============================================================

$db->beginTransaction();

try {
    foreach ($images_in_folder as $file_name) {
        $file_path = ImageCollection::IMAGE_DIRECTORY . $file_name;

        // Skip if this filename is already in the database (moved previously but not cleaned up)
        if (isset($image_filenames[$file_name])) {
            unlink($file_path);
            $images_skipped++;
            continue;
        }

        // Compute MD5 hash to check for content duplicates
        $image_md5 = md5_file($file_path);

        if (isset($image_hashes[$image_md5])) {
            // Duplicate content — delete the new file
            unlink($file_path);
            $images_skipped++;
            continue;
        }

        // Create and save the new image
        $image = new Image();
        $image->setFileName($file_name)
            ->setFileTime(filemtime($file_path))
            ->setHash($image_md5);

        // Save (auto-creates thumbnail and fingerprint)
        if ($image_collection->save($image) !== 0) {
            // Move file to the full-size directory
            rename($file_path, $image_dir_full . $file_name);

            // Add to lookup maps so subsequent files in this batch are checked
            $image_hashes[$image_md5] = true;
            $image_filenames[$file_name] = true;

            $images_added++;
        } else {
            $images_skipped++;
        }
    }

    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    echo "[ERROR] Failed to process new images: " . $e->getMessage() . "\n";
}

// ============================================================
// Process New Videos
// ============================================================

$db->beginTransaction();

try {
    foreach ($videos_in_folder as $file_name) {
        $file_path = VideoCollection::VIDEO_DIRECTORY . $file_name;

        // Skip if filename already exists in DB
        if (isset($video_filenames[$file_name])) {
            unlink($file_path);
            $videos_skipped++;
            continue;
        }

        // Compute MD5 hash
        $video_md5 = md5_file($file_path);

        if (isset($video_hashes[$video_md5])) {
            // Duplicate content — delete
            unlink($file_path);
            $videos_skipped++;
            continue;
        }

        // Create and save the new video
        $video = new Video();
        $video->setFileName($file_name)
            ->setFileTime(filemtime($file_path))
            ->setHash($video_md5);

        if ($video_collection->save($video) !== 0) {
            rename($file_path, $video_dir_full . $file_name);

            $video_hashes[$video_md5] = true;
            $video_filenames[$file_name] = true;

            $videos_added++;
        } else {
            $videos_skipped++;
        }
    }

    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    echo "[ERROR] Failed to process new videos: " . $e->getMessage() . "\n";
}

// ============================================================
// Output Results
// ============================================================

$end_time = microtime(true);
$execution_time = round($end_time - $start_time, 2);

echo "Images Added: {$images_added}\n";
echo "Images Removed: {$images_removed}\n";
echo "Images Skipped (duplicates): {$images_skipped}\n";
echo "Videos Added: {$videos_added}\n";
echo "Videos Removed: {$videos_removed}\n";
echo "Videos Skipped (duplicates): {$videos_skipped}\n";
echo "Execution Time: {$execution_time}s\n";
