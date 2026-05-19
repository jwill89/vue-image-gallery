<?php

namespace Gallery\Collection;

use OutOfBoundsException;
use Gallery\Core\Configuration;
use Gallery\Storage\VideoStorage;
use Gallery\Structure\Video;

/**
 * VideoCollection class
 * This class is responsible for managing a collection of videos and interacting with the database and filesystem.
 * It provides methods to retrieve, save, delete videos, and create thumbnails.
 * It also handles the creation of thumbnails for the videos.
 */
class VideoCollection
{
    // Directory where Images are stored
    public const string VIDEO_DIRECTORY = 'videos/';
    public const string VIDEO_DIRECTORY_FULL = 'videos/full/';
    public const string VIDEO_DIRECTORY_THUMBNAILS = 'videos/thumbs/';

    // Video Database Storage Object
    private VideoStorage $storage;

    /**
     * VideoCollection constructor.
     * Initializes the VideoStorage object.
     */
    public function __construct()
    {
        if (!isset($this->storage)) {
            $this->storage = new VideoStorage();
        }
    }

    /**
     * Gets an video based on supplied video ID.
     *
     * @param int $video_id The ID of the video to retrieve.
     *
     * @return Video|null The Video object corresponding to the supplied ID, or null if not found.
     */
    public function get(int $video_id): ?Video
    {
        return $this->storage->retrieve($video_id);
    }

    /**
     * Gets all videos.
     *
     * @return Video[] An array of Video objects.
     */
    public function getAll(): array
    {
        return $this->storage->retrieve();
    }

    /**
     * Gets a lightweight summary of all videos (only ID, file_name, and hash).
     * Used by cron.php for orphan detection and deduplication without loading full objects.
     *
     * @return array[] Array of associative arrays with video_id, file_name, and hash.
     */
    public function getAllSummary(): array
    {
        return $this->storage->retrieveSummary();
    }

    /**
     * Gets a number of videos based on the supplied page number.
     *
     * @param int $page_number The page number to retrieve.
     * @param int|null $items_per_page Optional. The number of items per page. Defaults to the number set in the Configuration.
     *
     * @return Video[] An array of Video objects for the specified page.
     */
    public function getForPage(int $page_number, ?int $items_per_page = null): array
    {
        // Default to the number set in the Configuration if not provided
        $items_per_page = $items_per_page ?? Configuration::DEFAULT_PER_PAGE;

        return $this->storage->retrieveForPage($page_number, $items_per_page);
    }

    /**
     * Gets a number of videos based on the supplied page number and tag ID.
     *
     * @param array $tag_ids The tag IDs to filter videos by.
     * @param int $page_number The page number to retrieve.
     * @param int|null $items_per_page Optional. The number of items per page. Defaults to the number set in the Configuration.
     *
     * @return Video[] An array of Video objects for the specified page and tags.
     */
    public function getWithTags(array $tag_ids, int $page_number, ?int $items_per_page = null): array
    {
        // Default to the number set in the Configuration if not provided
        $items_per_page = $items_per_page ?? Configuration::DEFAULT_PER_PAGE;

        return $this->storage->retrieveWithTags($tag_ids, $page_number, $items_per_page);
    }

    /**
     * Gets the total number of videos in the database.
     *
     * @return int The total number of videos in the database.
     */
    public function totalVideos(): int
    {
        return $this->storage->retrieveTotalVideoCount();
    }

    /**
     * Gets the total number of videos in the database with specific tags.
     *
     * @param array $tag_ids The tag IDs to filter videos by.
     *
     * @return int The total number of videos with the specified tags.
     */
    public function totalVideosWithTags(array $tag_ids): int
    {
        return $this->storage->retrieveTotalVideoWithTagsCount($tag_ids);
    }

    /**
     * Saves an video to the database and generates a thumbnail.
     *
     * @param Video $video The Video object to save.
     *
     * @return int The ID of the newly saved video.
     * @throws ImagickException
     */
    public function save(Video $video): int
    {
        // Save the video to the database
        $video_id = $this->storage->store($video);

        // If we have an ID, we can assume it was successful and generate a thumbnail
        if ($video_id > 0) {
            // Determine if we need to use Imagick or FFMpeg to make the Thumbnail
            if (pathinfo($video->getFileName(), PATHINFO_EXTENSION) === 'gif') {
                // Use Imagick to create a thumbnail for GIFs
                $this->createGifThumbnail($video);
            } else {
                // Use FFMpeg to create a thumbnail for other video formats
                $this->createThumbnail($video);
            }
        }

        return $video_id;
    }

    /**
     * Creates a thumbnail for the supplied Video object using FFmpeg CLI directly.
     * Uses the 'thumbnail' filter to intelligently select a representative frame,
     * and lanczos scaling for sharp output.
     *
     * @param Video $video_obj The Video object for which to create a thumbnail.
     */
    public function createThumbnail(Video $video_obj): void
    {
        $max_size = 200;
        $source_path = self::VIDEO_DIRECTORY . $video_obj->getFileName();
        $thumbnail_path = self::VIDEO_DIRECTORY_THUMBNAILS . pathinfo($video_obj->getFileName(), PATHINFO_FILENAME) . '.jpg';

        // Use FFmpeg with the 'thumbnail' filter for intelligent frame selection
        // scale uses lanczos for sharp downscaling, force_original_aspect_ratio maintains proportions
        $scale = "scale='min({$max_size},iw)':'min({$max_size},ih)':flags=lanczos:force_original_aspect_ratio=decrease,format=yuv420p";
        $cmd = sprintf(
            'ffmpeg -i %s -vf "thumbnail,%s" -frames:v 1 -q:v 2 -y %s 2>/dev/null',
            escapeshellarg($source_path),
            $scale,
            escapeshellarg($thumbnail_path)
        );

        exec($cmd, $output, $returnCode);

        // Fallback: if thumbnail filter fails (e.g. very short video), grab first frame
        if ($returnCode !== 0 || !file_exists($thumbnail_path)) {
            $cmd = sprintf(
                'ffmpeg -i %s -vf "%s" -frames:v 1 -q:v 2 -y %s 2>/dev/null',
                escapeshellarg($source_path),
                $scale,
                escapeshellarg($thumbnail_path)
            );
            exec($cmd);
        }
    }

    /**
     * Creates a thumbnail for animated GIFs and WebP using FFmpeg CLI.
     * Extracts the first frame and scales with lanczos for sharp results.
     *
     * @param Video $video_obj The Video object for which to create a thumbnail.
     */
    public function createGifThumbnail(Video $video_obj): void
    {
        // Max Width/Height of Thumbnail
        $max_size = 200;
        $source_path = self::VIDEO_DIRECTORY . $video_obj->getFileName();
        $thumbnail_path = self::VIDEO_DIRECTORY_THUMBNAILS . pathinfo($video_obj->getFileName(), PATHINFO_FILENAME) . '.jpg';

        $scale = "scale='min({$max_size},iw)':'min({$max_size},ih)':flags=lanczos:force_original_aspect_ratio=decrease";
        $cmd = sprintf(
            'ffmpeg -i %s -vf "%s" -frames:v 1 -q:v 2 -y %s 2>/dev/null',
            escapeshellarg($source_path),
            $scale,
            escapeshellarg($thumbnail_path)
        );

        exec($cmd);
    }

    /**
     * Deletes an video from the database and the filesystem.
     *
     * @param Video $video The Video object to delete.
     *
     * @return bool True if the video was successfully deleted, false otherwise.
     */
    public function delete(Video $video): bool
    {
        // Delete the video from the database
        $success = $this->storage->delete($video);

        // Delete the video and thumbnail from the filesystem
        if ($success) {
            $video_path = self::VIDEO_DIRECTORY_FULL . $video->getFileName();
            $video_path_info = pathinfo($video_path);
            $thumbnail_path = self::VIDEO_DIRECTORY_THUMBNAILS . $video_path_info['filename'] . '.' . 'jpg';

            // Delete the video file
            if (file_exists($video_path)) {
                unlink($video_path);
            }

            // Delete the thumbnail file
            if (file_exists($thumbnail_path)) {
                unlink($thumbnail_path);
            }
        } else {
            throw new OutOfBoundsException('Video not found in database.');
        }

        return true;
    }
}
