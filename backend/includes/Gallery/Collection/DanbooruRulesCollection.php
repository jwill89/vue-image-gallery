<?php

namespace Gallery\Collection;

use Gallery\Storage\DanbooruRulesStorage;

/**
 * DanbooruRulesCollection
 * Manages Danbooru import rules (category mappings and tag name mappings).
 */
class DanbooruRulesCollection
{
    private DanbooruRulesStorage $storage;

    public function __construct()
    {
        $this->storage = new DanbooruRulesStorage();
    }

    // ── Category Map ────────────────────────────────────────

    public function getCategoryMappings(): array
    {
        return $this->storage->retrieveCategoryMappings();
    }

    public function saveCategoryMapping(int $danbooruCategoryId, string $danbooruCategoryName, int $galleryCategoryId): bool
    {
        return $this->storage->storeCategoryMapping($danbooruCategoryId, $danbooruCategoryName, $galleryCategoryId);
    }

    public function deleteCategoryMapping(int $danbooruCategoryId): bool
    {
        return $this->storage->deleteCategoryMapping($danbooruCategoryId);
    }

    /**
     * @return array<int, int> danbooru_category_id => gallery_category_id
     */
    public function getCategoryMapArray(): array
    {
        return $this->storage->getCategoryMapAsArray();
    }

    /**
     * @return array<int, array{gallery_category_id: int, field: string}>
     */
    public function getCategoryMapWithFields(): array
    {
        return $this->storage->getCategoryMapWithFields();
    }

    // ── Tag Name Map ────────────────────────────────────────

    public function getTagMappings(): array
    {
        return $this->storage->retrieveTagMappings();
    }

    public function addTagMapping(string $danbooruTag, string $galleryTag): int
    {
        return $this->storage->storeTagMapping($danbooruTag, $galleryTag);
    }

    public function updateTagMapping(int $id, string $danbooruTag, string $galleryTag): bool
    {
        return $this->storage->updateTagMapping($id, $danbooruTag, $galleryTag);
    }

    public function deleteTagMapping(int $id): bool
    {
        return $this->storage->deleteTagMapping($id);
    }

    public function tagMappingExists(string $danbooruTag, int $excludeId = 0): bool
    {
        return $this->storage->tagMappingExists($danbooruTag, $excludeId);
    }

    /**
     * @return array<string, string> danbooru_tag => gallery_tag
     */
    public function getTagMapArray(): array
    {
        return $this->storage->getTagMapAsArray();
    }
}
