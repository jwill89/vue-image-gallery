<?php

namespace Gallery\Storage;

use PDO;
use Gallery\Core\DatabaseConnection;
use Gallery\Collection\TagCategoryCollection;
use Gallery\Structure\Tag;
use Gallery\Structure\TagCategory;
use Gallery\Structure\Image;
use Gallery\Structure\Video;

/**
 * TagStorage Class
 *
 * This class is responsible for managing tag storage in the database.
 */
class TagStorage
{
    // Table Constants
    private const string MAIN_TABLE = 'tags';
    private const string IMAGE_TAG_TABLE = 'image_tags';
    private const string VIDEO_TAG_TABLE = 'video_tags';
    private const string CATEGORIES_TABLE = 'tag_categories';

    // Main Class Object Constant
    private const string OBJ_CLASS = Tag::class;

    // Database Connection
    private PDO $db;

    // Cached category collection
    private ?TagCategoryCollection $categoryCollection = null;

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
     * Get or create the cached TagCategoryCollection instance.
     */
    private function getCategoryCollection(): TagCategoryCollection
    {
        if ($this->categoryCollection === null) {
            $this->categoryCollection = new TagCategoryCollection();
        }
        return $this->categoryCollection;
    }

    /**
     * Retrieves a tag or an array of tags from the database.
     *
     * @param integer|null $tag_id Optional. The ID of the tag to retrieve. If null, retrieves all tags.
     *
     * @return Tag|Tag[]|null A Tag object, null if not found, or an array of Tag objects.
     */
    public function retrieve(?int $tag_id = null): Tag|array|null
    {
        $where = ($tag_id !== null) ? " WHERE tag_id = :tag_id" : "";
        $sql = "SELECT * FROM " . self::MAIN_TABLE . "$where ORDER BY tag_name ASC";

        $stmt = $this->db->prepare($sql);

        if ($tag_id !== null) {
            $stmt->bindParam(':tag_id', $tag_id, PDO::PARAM_INT);
        }

        $stmt->execute();
        $tags = $stmt->fetchAll(PDO::FETCH_CLASS, self::OBJ_CLASS);

        if ($tag_id !== null) {
            return count($tags) === 1 ? $tags[0] : null;
        }

        return $tags;
    }

    /**
     * Retrieves a tag from the database based on tag name or returns null if it doesn't exist.
     *
     * @param string $tag_name The name of the tag to retrieve.
     *
     * @return Tag|null The tag object if found, null otherwise.
     */
    public function retrieveByName(string $tag_name): ?Tag
    {
        $sql = "SELECT * FROM " . self::MAIN_TABLE . " WHERE tag_name = :tag_name";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':tag_name', $tag_name, PDO::PARAM_STR);
        $stmt->execute();
        $stmt->setFetchMode(PDO::FETCH_CLASS, self::OBJ_CLASS);
        $tag = $stmt->fetch();

        return $tag instanceof Tag ? $tag : null;
    }

    /**
     * Retrieves a tag if it exists or creates it and stores it if it doesn't.
     *
     * @param string $tag_name The name of the tag to retrieve or create.
     *
     * @return Tag The tag object.
     */
    public function retrieveOrCreate(string $tag_name): Tag
    {
        // Tags and shortcodes are all lowercase
        $tag_name = strtolower($tag_name);

        // First, split the tag name to see if a category shortcode was used.
        if (str_contains($tag_name, ':')) {
            [$category_shortcode, $name] = array_map('trim', explode(':', $tag_name, 2));
            $category = $this->getCategoryCollection()->getByShortcode($category_shortcode);
        } else {
            $category = null;
        }

        // Check if we have a tag using the name based on if the category shortcode was valid
        $tag_exists = ($category instanceof TagCategory) ? $this->retrieveByName($name) : $this->retrieveByName($tag_name);

        if ($tag_exists instanceof Tag) {
            return $tag_exists;
        }

        // Create a new tag
        $tag = new Tag();

        if ($category instanceof TagCategory) {
            $tag->setTagName($name)->setCategoryId($category->getCategoryId());
        } else {
            $tag->setTagName($tag_name)->setCategoryId(1);
        }

        $tag_id = $this->store($tag);
        $tag->setTagId($tag_id);

        return $tag;
    }

    /**
     * Get tags based on supplied image.
     *
     * @param Image $image The image to retrieve tags for.
     *
     * @return Tag[] An array of Tag objects associated with the image.
     */
    public function retrieveTagsForImage(Image $image): array
    {
        $sql = "SELECT tt.* FROM " . self::MAIN_TABLE . " tt
                    LEFT JOIN " . self::IMAGE_TAG_TABLE . " it USING (tag_id)
                    WHERE it.image_id = :image_id 
                    ORDER BY CASE WHEN tt.category_id = 1 THEN 10
                                  ELSE tt.category_id END,
                            tt.tag_name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':image_id', $image->getImageId(), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_CLASS, self::OBJ_CLASS);
    }

    /**
     * Get tags based on supplied video.
     *
     * @param Video $video The video to retrieve tags for.
     *
     * @return Tag[] An array of Tag objects associated with the video.
     */
    public function retrieveTagsForVideo(Video $video): array
    {
        $sql = "SELECT tt.* FROM " . self::MAIN_TABLE . " tt
                    LEFT JOIN " . self::VIDEO_TAG_TABLE . " vt USING (tag_id)
                    WHERE vt.video_id = :video_id
                    ORDER BY CASE WHEN tt.category_id = 1 THEN 10
                                  ELSE tt.category_id END,
                            tt.tag_name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':video_id', $video->getVideoId(), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_CLASS, self::OBJ_CLASS);
    }

    /**
     * Get all tags with category and usage counts for the tags page.
     *
     * @return array
     */
    public function retrieveAllTagsForPage(): array
    {
        $sql = "SELECT t.tag_id, t.tag_name, t.category_id, tc.category_name,
                COALESCE(ic.image_count, 0) AS image_count,
                COALESCE(vc.video_count, 0) AS video_count
             FROM " . self::MAIN_TABLE . " t
             LEFT JOIN " . self::CATEGORIES_TABLE . " tc USING (category_id)
             LEFT JOIN (SELECT tag_id, COUNT(*) AS image_count FROM " . self::IMAGE_TAG_TABLE . " GROUP BY tag_id) ic ON ic.tag_id = t.tag_id
             LEFT JOIN (SELECT tag_id, COUNT(*) AS video_count FROM " . self::VIDEO_TAG_TABLE . " GROUP BY tag_id) vc ON vc.tag_id = t.tag_id
             ORDER BY t.category_id ASC, t.tag_name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Add tags to an image. Uses INSERT OR IGNORE to skip duplicates.
     *
     * @param Image $image Image object to which tags will be added.
     * @param array $tag_ids Array of tag IDs to be added to the image.
     *
     * @return bool True on success, false on failure.
     */
    public function addTagsToImage(Image $image, array $tag_ids): bool
    {
        $sql = "INSERT OR IGNORE INTO " . self::IMAGE_TAG_TABLE . " (image_id, tag_id) VALUES (:image_id, :tag_id)";
        $stmt = $this->db->prepare($sql);
        $image_id = $image->getImageId();

        foreach ($tag_ids as $tag_id) {
            if (!$stmt->execute([':image_id' => $image_id, ':tag_id' => $tag_id])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Add tags to a video. Uses INSERT OR IGNORE to skip duplicates.
     *
     * @param Video $video Video object to which tags will be added.
     * @param array $tag_ids Array of tag IDs to be added to the video.
     *
     * @return bool True on success, false on failure.
     */
    public function addTagsToVideo(Video $video, array $tag_ids): bool
    {
        $sql = "INSERT OR IGNORE INTO " . self::VIDEO_TAG_TABLE . " (video_id, tag_id) VALUES (:video_id, :tag_id)";
        $stmt = $this->db->prepare($sql);
        $video_id = $video->getVideoId();

        foreach ($tag_ids as $tag_id) {
            if (!$stmt->execute([':video_id' => $video_id, ':tag_id' => $tag_id])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Removes a tag from an image.
     *
     * @param Image $image The image from which the tag will be removed.
     * @param Tag $tag The tag to be removed.
     *
     * @return bool True on success, false on failure.
     */
    public function removeTagFromImage(Image $image, Tag $tag): bool
    {
        $sql = "DELETE FROM " . self::IMAGE_TAG_TABLE . " WHERE image_id = :image_id AND tag_id = :tag_id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':image_id', $image->getImageId(), PDO::PARAM_INT);
        $stmt->bindValue(':tag_id', $tag->getTagId(), PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Removes a tag from a video.
     *
     * @param Video $video The video from which the tag will be removed.
     * @param Tag $tag The tag to be removed.
     *
     * @return bool True on success, false on failure.
     */
    public function removeTagFromVideo(Video $video, Tag $tag): bool
    {
        $sql = "DELETE FROM " . self::VIDEO_TAG_TABLE . " WHERE video_id = :video_id AND tag_id = :tag_id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':video_id', $video->getVideoId(), PDO::PARAM_INT);
        $stmt->bindValue(':tag_id', $tag->getTagId(), PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Gets the total number of tags in the database.
     *
     * @return int The total number of tags.
     */
    public function retrieveTotalTagCount(): int
    {
        $sql = "SELECT COUNT(*) FROM " . self::MAIN_TABLE;
        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return (int)$stmt->fetchColumn();
    }

    /**
     * Check if a tag exists in the database based on tag title.
     *
     * @param string $tag_name The name of the tag to check.
     *
     * @return bool True if the tag exists, false otherwise.
     */
    public function tagExists(string $tag_name): bool
    {
        $sql = "SELECT 1 FROM " . self::MAIN_TABLE . " WHERE tag_name = :tag_name LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':tag_name', $tag_name, PDO::PARAM_STR);
        $stmt->execute();

        return (int)$stmt->fetchColumn() === 1;
    }

    /**
     * Saves a tag to the database.
     *
     * @param Tag $tag The tag object to be saved.
     *
     * @return int The ID of the saved tag.
     */
    public function store(Tag $tag): int
    {
        if (empty($tag->getTagId())) {
            $sql = "INSERT INTO " . self::MAIN_TABLE . " (category_id, tag_name) VALUES (:category_id, :tag_name)";
        } else {
            $sql = "UPDATE " . self::MAIN_TABLE . " SET category_id = :category_id, tag_name = :tag_name WHERE tag_id = :tag_id";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':category_id', $tag->getCategoryId(), PDO::PARAM_INT);
        $stmt->bindValue(':tag_name', $tag->getTagName(), PDO::PARAM_STR);

        if (!empty($tag->getTagId())) {
            $stmt->bindValue(':tag_id', $tag->getTagId(), PDO::PARAM_INT);
        }

        if ($stmt->execute() && empty($tag->getTagId())) {
            $tag->setTagId((int)$this->db->lastInsertId());
        }

        return $tag->getTagId();
    }

    /**
     * Deletes a tag from the database based on the supplied tag.
     *
     * @param Tag $tag The tag object to be deleted.
     *
     * @return bool True on success, false on failure.
     */
    public function delete(Tag $tag): bool
    {
        $sql = "DELETE FROM " . self::MAIN_TABLE . " WHERE tag_id = :tag_id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':tag_id', $tag->getTagId(), PDO::PARAM_INT);

        return $stmt->execute();
    }
}
