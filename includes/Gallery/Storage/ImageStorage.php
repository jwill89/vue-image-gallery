<?php

namespace Gallery\Storage;

use PDO;
use Gallery\Core\DatabaseConnection;
use Gallery\Structure\Image;

/**
 * ImageStorage Class
 *
 * This class is responsible for managing image storage in the database.
 */
class ImageStorage
{
    // Table Constants
    private const string MAIN_TABLE = 'images';
    private const string TAGS_TABLE = 'image_tags';

    // Main Class Object Constant
    private const string OBJ_CLASS = Image::class;

    // Database Connection
    private PDO $db;

    /**
     * Class constructor
     *
     * Initializes the Database Connection.
     */
    public function __construct()
    {
        if (!isset($this->db)) {
            $this->db = DatabaseConnection::getInstance();
        }
    }

    /**
     * Retrieves an image or an array of images from the database.
     *
     * @param int|null $image_id Optional. The ID of the image to retrieve. If null, retrieves all images.
     *
     * @return Image|Image[]|null An Image object if $image_id is provided, null if not found, otherwise an array of Image objects.
     */
    public function retrieve(?int $image_id = null): Image|array|null
    {
        $where = ($image_id !== null) ? " WHERE image_id = :image_id" : "";
        $sql = "SELECT * FROM " . self::MAIN_TABLE . "$where ORDER BY image_id DESC";

        $stmt = $this->db->prepare($sql);

        if ($image_id !== null) {
            $stmt->bindParam(':image_id', $image_id, PDO::PARAM_INT);
        }

        $stmt->execute();
        $images = $stmt->fetchAll(PDO::FETCH_CLASS, self::OBJ_CLASS);

        if ($image_id !== null) {
            return count($images) === 1 ? $images[0] : null;
        }

        return $images;
    }

    /**
     * Get image based on supplied file name.
     *
     * @param string $file_name The file name of the image to retrieve.
     *
     * @return Image|null An Image object if found, otherwise null.
     */
    public function retrieveByFilename(string $file_name): ?Image
    {
        $sql = "SELECT * FROM " . self::MAIN_TABLE . " WHERE file_name = :file_name";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':file_name', $file_name, PDO::PARAM_STR);
        $stmt->execute();
        $stmt->setFetchMode(PDO::FETCH_CLASS, self::OBJ_CLASS);
        $image = $stmt->fetch();

        return $image instanceof Image ? $image : null;
    }

    /**
     * Returns an array of images based on the supplied tag ids.
     *
     * @param array $tag_ids The array of tag IDs to search for.
     * @param int $page_number The page number to retrieve.
     * @param int $items_per_page The number of items per page.
     *
     * @return Image[] An array of Image objects.
     */
    public function retrieveWithTags(array $tag_ids, int $page_number, int $items_per_page): array
    {
        $tag_count = count($tag_ids);
        $offset = ($page_number - 1) * $items_per_page;

        $placeholders = implode(',', array_fill(0, $tag_count, '?'));
        $sql = "SELECT img.* FROM " . self::MAIN_TABLE . " img
                    LEFT JOIN " . self::TAGS_TABLE . " tag USING (image_id)
                    WHERE tag.tag_id IN ($placeholders)
                    GROUP BY img.image_id 
                    HAVING COUNT(DISTINCT tag.tag_id) = ?
                    ORDER BY img.image_id DESC
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
     * Retrieves a number of images based on the supplied page number and the number of images per page.
     *
     * @param integer $page_number The page number to retrieve.
     * @param integer $items_per_page The number of items per page.
     *
     * @return Image[] An array of Image objects.
     */
    public function retrieveForPage(int $page_number, int $items_per_page): array
    {
        $offset = ($page_number - 1) * $items_per_page;
        $sql = "SELECT * FROM " . self::MAIN_TABLE . " ORDER BY image_id DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $items_per_page, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_CLASS, self::OBJ_CLASS);
    }

    /**
     * Gets the total number of images in the database.
     *
     * @return integer The total number of images in the database.
     */
    public function retrieveTotalImageCount(): int
    {
        $sql = "SELECT COUNT(*) FROM " . self::MAIN_TABLE;
        $stmt = $this->db->query($sql);

        return (int)$stmt->fetchColumn();
    }

    /**
     * Gets the total number of images in the database with specific tags.
     *
     * @param array $tag_ids The array of tag IDs to search for.
     *
     * @return int The total number of images with the specified tags.
     */
    public function retrieveTotalImageWithTagsCount(array $tag_ids): int
    {
        $tag_count = count($tag_ids);
        $placeholders = implode(',', array_fill(0, $tag_count, '?'));
        $sql = "SELECT COUNT(*) FROM (SELECT img.image_id FROM " . self::MAIN_TABLE . " img
                    LEFT JOIN " . self::TAGS_TABLE . " tag USING (image_id)
                    WHERE tag.tag_id IN ($placeholders)
                    GROUP BY img.image_id 
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
     * Check if an image exists in the database based on file name or md5 hash.
     *
     * @param string $file_name The file name of the image to check.
     * @param string $hash The md5 hash of the image to check.
     *
     * @return bool True if the image exists, false otherwise.
     */
    public function imageExistsInDatabase(string $file_name, string $hash): bool
    {
        $sql = "SELECT 1 FROM " . self::MAIN_TABLE . " WHERE file_name = :file_name OR hash = :hash LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':file_name', $file_name, PDO::PARAM_STR);
        $stmt->bindParam(':hash', $hash, PDO::PARAM_STR);
        $stmt->execute();

        return (int)$stmt->fetchColumn() === 1;
    }

    /**
     * Retrieves a lightweight summary of all images (only ID, file_name, and hash).
     * Used by cron.php to avoid loading full Image objects into memory.
     *
     * @return array[] Array of associative arrays with image_id, file_name, and hash keys.
     */
    public function retrieveSummary(): array
    {
        $sql = "SELECT image_id, file_name, hash FROM " . self::MAIN_TABLE . " ORDER BY image_id DESC";
        $stmt = $this->db->query($sql);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Saves an image to the database.
     *
     * @param Image $image The image object to save.
     *
     * @return int The ID of the newly saved image.
     */
    public function store(Image $image): int
    {
        if (empty($image->getImageId())) {
            $sql = "INSERT INTO " . self::MAIN_TABLE . " (file_name, file_time, hash, bits_fingerprint) VALUES (:file_name, :file_time, :hash, :bits_fingerprint)";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':file_name', $image->getFileName(), PDO::PARAM_STR);
            $stmt->bindValue(':file_time', $image->getFileTime(), PDO::PARAM_INT);
            $stmt->bindValue(':hash', $image->getHash(), PDO::PARAM_STR);
            $stmt->bindValue(':bits_fingerprint', $image->getBitsFingerprint(), PDO::PARAM_STR);

            $stmt->execute();
            $image->setImageId((int)$this->db->lastInsertId());
        }

        return $image->getImageId();
    }

    /**
     * Deletes an image from the database based on the supplied image.
     *
     * @param Image $image The image object to delete.
     *
     * @return bool True if the image was deleted, false otherwise.
     */
    public function delete(Image $image): bool
    {
        $sql = "DELETE FROM " . self::MAIN_TABLE . " WHERE image_id = :image_id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':image_id', $image->getImageId(), PDO::PARAM_INT);

        return $stmt->execute();
    }
}
