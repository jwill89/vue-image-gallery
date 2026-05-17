<?php

namespace Gallery\Collection;

use Gallery\Storage\TagCategoryStorage;
use Gallery\Structure\TagCategory;
use Gallery\Structure\Tag;

/**
 * TagCategoryCollection class
 */
class TagCategoryCollection
{
    // Tag Database Storage Object
    private TagCategoryStorage $storage;

    /**
     * TagCategoryCollection constructor.
     * Initializes the TagCategoryStorage object.
     */
    public function __construct()
    {
        if (!isset($this->storage)) {
            $this->storage = new TagCategoryStorage();
        }
    }

    /**
     * Gets a tag category based on supplied category ID.
     *
     * @param int $category_id Category ID to retrieve.
     *
     * @return TagCategory|null TagCategory object if found, null otherwise.
     */
    public function get(int $category_id): ?TagCategory
    {
        return $this->storage->retrieve($category_id);
    }

    /**
     * Gets a tag category based on supplied shortcode, if it exists.
     *
     * @param string $short Shortcode of tag category to retrieve.
     *
     * @return TagCategory|null TagCategory object if found, null otherwise.
     */
    public function getByShortcode(string $short): ?TagCategory
    {
        return $this->storage->retrieveByShortcode($short);
    }

    /**
     * Gets all tag categories from the database, sorted by name.
     *
     * @return TagCategory[] Array of TagCategory objects.
     */
    public function getAll(): array
    {
        return $this->storage->retrieve();
    }

    /**
     * Gets tags belonging to a specific category.
     *
     * @param TagCategory $category The category to retrieve tags for.
     *
     * @return Tag[] Array of Tag objects belonging to the category.
     */
    public function getTagsForCategory(TagCategory $category): array
    {
        return $this->storage->retrieveTagsForCategory($category);
    }

    /**
     * Saves a tag category to the database.
     *
     * @param TagCategory $category The tag category to save.
     *
     * @return int The ID of the saved tag category.
     */
    public function save(TagCategory $category): int
    {
        // Save the tag to the database
        return $this->storage->store($category);
    }

    /**
     * Deletes a tag category from the database.
     *
     * @param TagCategory $category The tag category to delete.
     *
     * @return bool True if deletion was successful, false otherwise.
     */
    public function delete(TagCategory $category): bool
    {
        // Delete the tag from the database
        return $this->storage->delete($category);
    }
}
