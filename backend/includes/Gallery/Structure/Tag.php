<?php

namespace Gallery\Structure;

use OpenApi\Attributes as OA;

/**
 * Tag class
 * Represents a tag in the gallery.
 *
 * Properties use asymmetric visibility (PHP 8.4): reads are public
 * (e.g. $tag->tag_name), writes go through the fluent setters. private(set)
 * lets PDO's FETCH_CLASS hydrate rows while blocking external mutation.
 */
#[OA\Schema(schema: 'Tag', description: 'A tag.')]
class Tag extends AbstractStructure
{
    #[OA\Property(type: 'integer')]
    public private(set) int $tag_id = 0;
    #[OA\Property(type: 'integer')]
    public private(set) int $category_id = 0;
    #[OA\Property(type: 'string')]
    public private(set) string $tag_name = '';

    public function setTagId(int $tag_id): self
    {
        $this->tag_id = $tag_id;
        return $this;
    }

    public function setCategoryId(int $category_id): self
    {
        $this->category_id = $category_id;
        return $this;
    }

    public function setTagName(string $tag_name): self
    {
        $this->tag_name = $tag_name;
        return $this;
    }
}
