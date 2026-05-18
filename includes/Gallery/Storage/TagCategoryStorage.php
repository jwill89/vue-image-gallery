<?php

namespace Gallery\Storage;

use PDO;
use Gallery\Core\DatabaseConnection;
use Gallery\Structure\TagCategory;
use Gallery\Structure\Tag;

/**
 * TagCategoryStorage Class
 * This class is responsible for managing tag category storage in the database.
 */
class TagCategoryStorage
{
    // Table Constants
    private const string MAIN_TABLE = 'tag_categories';
    private const string TAGS_TABLE = 'tags';

    // Main Class Object Constant
    private const string OBJ_CLASS = TagCategory::class;

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
     * Retrieves a tag category or an array of tag categories from the database.
     *
     * @param integer|null $category_id The ID of the category to retrieve. If null, retrieves all categories.
     *
     * @return TagCategory|TagCategory[]|null A TagCategory, null if not found, or an array of TagCategory objects.
     */
    public function retrieve(?int $category_id = null): TagCategory|array|null
    {
        $where = ($category_id !== null) ? " WHERE category_id = :category_id" : "";
        $sql = "SELECT * FROM " . self::MAIN_TABLE . "$where ORDER BY category_name ASC";

        $stmt = $this->db->prepare($sql);

        if ($category_id !== null) {
            $stmt->bindParam(':category_id', $category_id, PDO::PARAM_INT);
        }

        $stmt->execute();
        $categories = $stmt->fetchAll(PDO::FETCH_CLASS, self::OBJ_CLASS);

        if ($category_id !== null) {
            return count($categories) === 1 ? $categories[0] : null;
        }

        return $categories;
    }

    /**
     * Retrieves a tag category from the database based on shortcode or returns null if it doesn't exist.
     *
     * @param string $short The shortcode of the tag category to retrieve.
     *
     * @return TagCategory|null The tag category object if found, null otherwise.
     */
    public function retrieveByShortcode(string $short): ?TagCategory
    {
        $sql = "SELECT * FROM " . self::MAIN_TABLE . " WHERE category_short = :category_short";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':category_short', $short, PDO::PARAM_STR);
        $stmt->execute();
        $stmt->setFetchMode(PDO::FETCH_CLASS, self::OBJ_CLASS);
        $category = $stmt->fetch();

        return $category instanceof TagCategory ? $category : null;
    }

    /**
     * Get tags based on supplied category.
     *
     * @param TagCategory $category The tag category to retrieve tags for.
     *
     * @return Tag[] An array of Tag objects.
     */
    public function retrieveTagsForCategory(TagCategory $category): array
    {
        $sql = "SELECT t.* FROM " . self::TAGS_TABLE . " t
                LEFT JOIN " . self::MAIN_TABLE . " tc USING (category_id)
                WHERE tc.category_id = :category_id
                ORDER BY t.tag_name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':category_id', $category->getCategoryId(), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_CLASS, Tag::class);
    }

    /**
     * Saves a tag category to the database.
     *
     * @param TagCategory $category The tag category to save.
     *
     * @return int The ID of the newly saved tag category.
     */
    public function store(TagCategory $category): int
    {
        if (empty($category->getCategoryId())) {
            $sql = "INSERT INTO " . self::MAIN_TABLE . " (category_name, category_short) VALUES (:category_name, :category_short)";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':category_name', $category->getCategoryName(), PDO::PARAM_STR);
            $stmt->bindValue(':category_short', $category->getCategoryShort(), PDO::PARAM_STR);

            $stmt->execute();
            $category->setCategoryId((int)$this->db->lastInsertId());
        }

        return $category->getCategoryId();
    }

    /**
     * Deletes a tag category from the database.
     *
     * @param TagCategory $category The tag category to delete.
     *
     * @return bool True if the category was deleted, false otherwise.
     */
    public function delete(TagCategory $category): bool
    {
        $sql = "DELETE FROM " . self::MAIN_TABLE . " WHERE category_id = :category_id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':category_id', $category->getCategoryId(), PDO::PARAM_INT);

        return $stmt->execute();
    }
}
