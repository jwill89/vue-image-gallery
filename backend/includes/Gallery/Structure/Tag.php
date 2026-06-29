<?php

namespace Gallery\Structure;

/**
 * Tag class
 * This class represents a tag in the gallery.
 * It contains properties for the tag ID and name.
 */
class Tag extends AbstractStructure
{
    // Properties
    private int $tag_id = 0;
    private int $category_id = 0;
    private string $tag_name = '';

    /**
     * Get the ID of the tag.
     *
     * @return int The ID of the tag.
     */
    public function getTagId(): int
    {
        return $this->tag_id;
    }

    /**
     * Get the ID of the category.
     *
     * @return int The ID of the category.
     */
    public function getCategoryId(): int
    {
        return $this->category_id;
    }

    /**
     * Get the tag name.
     *
     * @return string The name of the tag.
     */
    public function getTagName(): string
    {
        return $this->tag_name;
    }

    /**
     * Set the ID of the tag.
     *
     * @param int $tag_id The ID of the tag.
     *
     * @return $this Returns the current instance for method chaining.
     */
    public function setTagId(int $tag_id): self
    {
        $this->tag_id = $tag_id;
        return $this;
    }

    /**
     * Set the ID of the category.
     *
     * @param int $category_id The ID of the category.
     *
     * @return $this Returns the current instance for method chaining.
     */
    public function setCategoryId(int $category_id): self
    {
        $this->category_id = $category_id;
        return $this;
    }

    /**
     * Set the tag name.
     *
     * @param string $tag_name The name of the tag.
     *
     * @return $this Returns the current instance for method chaining.
     */
    public function setTagName(string $tag_name): self
    {
        $this->tag_name = $tag_name;
        return $this;
    }
}
