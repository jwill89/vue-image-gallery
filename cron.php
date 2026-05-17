<?php

// Error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Autoloader
require('vendor/autoload.php');

// DB and Image functions
use Gallery\Collection\ImageCollection;
use Gallery\Collection\VideoCollection;
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

// Get all images/videos currently in the database
$images_in_database = $image_collection->getAll();
$videos_in_database = $video_collection->getAll();

// Build hash maps and filename sets for O(1) lookups
$image_hashes = [];      // MD5 hash => true
$image_filenames = [];   // filename => true (files that exist in full/)

foreach ($images_in_database as $img) {
    $image_hashes[$img->getHash()] = true;
    $image_filenames[$img->getFileName()] = true;
}

$video_hashes = [];
$video_filenames = [];

foreach ($videos_in_database as $vid) {
    $video_hashes[$vid->getHash()] = true;
    $video_filenames[$vid->getFileName()] = true;
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

foreach ($images_in_database as $img) {
    if (!file_exists($image_dir_full . $img->getFileName())) {
        if ($image_collection->delete($img)) {
            $images_removed++;
            // Remove from our lookup maps
            unset($image_hashes[$img->getHash()], $image_filenames[$img->getFileName()]);
        }
    }
}

foreach ($videos_in_database as $vid) {
    if (!file_exists($video_dir_full . $vid->getFileName())) {
        if ($video_collection->delete($vid)) {
            $videos_removed++;
            unset($video_hashes[$vid->getHash()], $video_filenames[$vid->getFileName()]);
        }
    }
}

// ============================================================
// Process New Images
// ============================================================

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

// ============================================================
// Process New Videos
// ============================================================

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

// ============================================================
// Output Results
// ============================================================

$end_time = microtime(true);
$execution_time = round($end_time - $start_time, 2);

echo "<strong>Images Added</strong>: {$images_added}<br/>";
echo "<strong>Images Removed</strong>: {$images_removed}<br/>";
echo "<strong>Images Skipped (duplicates)</strong>: {$images_skipped}<br/>";
echo "<strong>Videos Added</strong>: {$videos_added}<br/>";
echo "<strong>Videos Removed</strong>: {$videos_removed}<br/>";
echo "<strong>Videos Skipped (duplicates)</strong>: {$videos_skipped}<br/>";
echo "<strong>Execution Time</strong>: {$execution_time}s<br/>";
