<?php

namespace Gallery\Structure;

use OpenApi\Attributes as OA;

/**
 * TagCategory class
 * Represents a tag category in the gallery.
 *
 * Properties use asymmetric visibility (PHP 8.4): reads are public
 * (e.g. $category->category_name), writes go through the fluent setters.
 * private(set) lets PDO's FETCH_CLASS hydrate rows while blocking external
 * mutation.
 */
#[OA\Schema(schema: 'TagCategory', description: 'A tag category (color, shortcode, sort order).')]
class TagCategory extends AbstractStructure
{
    #[OA\Property(type: 'integer')]
    public private(set) int $category_id = 0;
    #[OA\Property(type: 'string')]
    public private(set) string $category_name = '';
    #[OA\Property(type: 'string', description: 'Short code (<= 5 chars) used in tag prefixes.')]
    public private(set) string $category_short = '';
    #[OA\Property(type: 'string', description: 'Bulma/extended palette color name.')]
    public private(set) string $color = 'white';
    #[OA\Property(type: 'string')]
    public private(set) string $description = '';
    #[OA\Property(type: 'integer')]
    public private(set) int $sort_order = 0;

    public function setCategoryId(int $category_id): self
    {
        $this->category_id = $category_id;
        return $this;
    }

    public function setCategoryName(string $category_name): self
    {
        $this->category_name = $category_name;
        return $this;
    }

    public function setCategoryShort(string $category_short): self
    {
        $this->category_short = $category_short;
        return $this;
    }

    public function setColor(string $color): self
    {
        $this->color = $color;
        return $this;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function setSortOrder(int $sort_order): self
    {
        $this->sort_order = $sort_order;
        return $this;
    }
}
