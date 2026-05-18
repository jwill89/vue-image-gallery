<?php

namespace Gallery\Storage;

use PDO;
use Gallery\Core\DatabaseConnection;
use Gallery\Structure\Video;

/**
 * VideoStorage Class
 * This class is responsible for managing video storage in the database.
 */
class VideoStorage
{
    // Table Constants
    private const string MAIN_TABLE = 'videos';
    private const string TAGS_TABLE = 'video_tags';

    // Main Class Object Constant
    private const string OBJ_CLASS = Video::class;

    // Database Connection
    private PDO $db;

    /**
     * Class constructor
     * Initializes the Database Connection.
     */
    public function __construct()
    {
        if (!isset($this->db)) {
            $this->db = DatabaseConnection::getInstance();
        }
    }

    /**
     * Retrieves a video or an array of videos from the database.
     *
     * @param int|null $video_id The ID of the video to retrieve. If null, retrieves all videos.
     *
     * @return Video|Video[]|null An array of Video objects, a single Video, or null if not found.
     */
    public function retrieve(?int $video_id = null): Video|array|null
    {
        $where = ($video_id !== null) ? " WHERE video_id = :video_id" : "";
        $sql = "SELECT * FROM " . self::MAIN_TABLE . "$where ORDER BY video_id DESC";

        $stmt = $this->db->prepare($sql);

        if ($video_id !== null) {
            $stmt->bindParam(':video_id', $video_id, PDO::PARAM_INT);
        }

        $stmt->execute();
        $videos = $stmt->fetchAll(PDO::FETCH_CLASS, self::OBJ_CLASS);

        if ($video_id !== null) {
            return count($videos) === 1 ? $videos[0] : null;
        }

        return $videos;
    }

    /**
     * Get video based on supplied file name.
     *
     * @param string $file_name The file name of the video to retrieve.
     *
     * @return Video|null Returns a Video object if found, null otherwise.
     */
    public function retrieveByFilename(string $file_name): ?Video
    {
        $sql = "SELECT * FROM " . self::MAIN_TABLE . " WHERE file_name = :file_name";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':file_name', $file_name, PDO::PARAM_STR);
        $stmt->execute();
        $stmt->setFetchMode(PDO::FETCH_CLASS, self::OBJ_CLASS);
        $video = $stmt->fetch();

        return $video instanceof Video ? $video : null;
    }

    /**
     * Returns an array of videos based on the supplied tag ids.
     *
     * @param array $tag_ids The tag IDs to filter videos by.
     * @param int $page_number The page number to retrieve.
     * @param int $items_per_page The number of items per page.
     *
     * @return Video[] An array of Video objects.
     */
    public function retrieveWithTags(array $tag_ids, int $page_number, int $items_per_page): array
    {
        $tag_count = count($tag_ids);
        $offset = ($page_number - 1) * $items_per_page;

        $placeholders = implode(',', array_fill(0, $tag_count, '?'));
        $sql = "SELECT vid.* FROM " . self::MAIN_TABLE . " vid
                    LEFT JOIN " . self::TAGS_TABLE . " tag USING (video_id)
                    WHERE tag.tag_id IN ($placeholders)
                    GROUP BY vid.video_id 
                    HAVING COUNT(DISTINCT tag.tag_id) = ?
                    ORDER BY vid.video_id DESC
                    LIMIT ? OFFSET ?";

        $stmt = $this->db->prepare($sql);

        $bind_index = 1;
        foreach ($tag_ids as $tid) {
            $stmt->bindValue($bind_index++, (int)$tid, PDO::PARAM_INT);
        }
        $stmt->bindValue($bind_index++, $tag_count, PDO::PARAM_INT);
        $stmt->bindValue($bind_index++, $items_per_page, PDO::PARAM_INT);
        $stmt->bindValue($bind_index, $offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_CLASS, self::OBJ_CLASS);
    }

    /**
     * Retrieves a number of videos based on the supplied page number and the number of videos per page.
     *
     * @param integer $page_number The page number to retrieve.
     * @param integer $items_per_page The number of items per page.
     *
     * @return Video[] An array of Video objects.
     */
    public function retrieveForPage(int $page_number, int $items_per_page): array
    {
        $offset = ($page_number - 1) * $items_per_page;
        $sql = "SELECT * FROM " . self::MAIN_TABLE . " ORDER BY video_id DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $items_per_page, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_CLASS, self::OBJ_CLASS);
    }

    /**
     * Gets the total number of videos in the database.
     *
     * @return int The total number of videos in the database.
     */
    public function retrieveTotalVideoCount(): int
    {
        $sql = "SELECT COUNT(*) FROM " . self::MAIN_TABLE;
        $stmt = $this->db->query($sql);

        return (int)$stmt->fetchColumn();
    }

    /**
     * Gets the total number of videos with specific tags in the database.
     *
     * @param array $tag_ids The tag IDs to filter videos by.
     *
     * @return int The total number of videos with the specified tags.
     */
    public function retrieveTotalVideoWithTagsCount(array $tag_ids): int
    {
        $tag_count = count($tag_ids);
        $placeholders = implode(',', array_fill(0, $tag_count, '?'));
        $sql = "SELECT COUNT(*) FROM (SELECT vid.video_id FROM " . self::MAIN_TABLE . " vid
                    LEFT JOIN " . self::TAGS_TABLE . " tag USING (video_id)
                    WHERE tag.tag_id IN ($placeholders)
                    GROUP BY vid.video_id 
                    HAVING COUNT(DISTINCT tag.tag_id) = ?)";

        $stmt = $this->db->prepare($sql);

        $bind_index = 1;
        foreach ($tag_ids as $tid) {
            $stmt->bindValue($bind_index++, (int)$tid, PDO::PARAM_INT);
        }
        $stmt->bindValue($bind_index, $tag_count, PDO::PARAM_INT);

        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    /**
     * Check if a video exists in the database based on file name.
     *
     * @param string $file_name The file name of the video to check.
     *
     * @return bool Returns true if the video exists, false otherwise.
     */
    public function videoExistsInDatabase(string $file_name): bool
    {
        $sql = "SELECT 1 FROM " . self::MAIN_TABLE . " WHERE file_name = :file_name LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':file_name', $file_name, PDO::PARAM_STR);
        $stmt->execute();

        return (int)$stmt->fetchColumn() === 1;
    }

    /**
     * Retrieves a lightweight summary of all videos (only ID, file_name, and hash).
     * Used by cron.php to avoid loading full Video objects into memory.
     *
     * @return array[] Array of associative arrays with video_id, file_name, and hash keys.
     */
    public function retrieveSummary(): array
    {
        $sql = "SELECT video_id, file_name, hash FROM " . self::MAIN_TABLE . " ORDER BY video_id DESC";
        $stmt = $this->db->query($sql);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Saves a video to the database.
     *
     * @param Video $video The video object to store.
     *
     * @return int The ID of the stored video.
     */
    public function store(Video $video): int
    {
        if (empty($video->getVideoId())) {
            $sql = "INSERT INTO " . self::MAIN_TABLE . " (file_name, file_time, hash) VALUES (:file_name, :file_time, :hash)";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':file_name', $video->getFileName(), PDO::PARAM_STR);
            $stmt->bindValue(':file_time', $video->getFileTime(), PDO::PARAM_INT);
            $stmt->bindValue(':hash', $video->getHash(), PDO::PARAM_STR);

            $stmt->execute();
            $video->setVideoId((int)$this->db->lastInsertId());
        }

        return $video->getVideoId();
    }

    /**
     * Deletes a video from the database based on the supplied video.
     *
     * @param Video $video The video object to delete.
     *
     * @return bool True if the video was deleted, false otherwise.
     */
    public function delete(Video $video): bool
    {
        $sql = "DELETE FROM " . self::MAIN_TABLE . " WHERE video_id = :video_id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':video_id', $video->getVideoId(), PDO::PARAM_INT);

        return $stmt->execute();
    }
}
