<?php

namespace Gallery\Collection;

use Gallery\Storage\TagStorage;
use Gallery\Structure\Tag;
use Gallery\Structure\Media;

/**
 * TagCollection class
 * Manages tags with unified media support.
 */
class TagCollection
{
    private TagStorage $storage;

    public function __construct(TagStorage $storage)
    {
        $this->storage = $storage;
    }

    public function get(int $tag_id): ?Tag
    {
        return $this->storage->retrieve($tag_id);
    }

    public function getByName(string $tag_name): ?Tag
    {
        return $this->storage->retrieveByName($tag_name);
    }

    /**
     * Resolve multiple tag names to IDs in a single query.
     * Unknown names are omitted; returned IDs are de-duplicated.
     *
     * @param string[] $tag_names
     * @return int[]
     */
    public function getIdsByNames(array $tag_names): array
    {
        return $this->storage->retrieveIdsByNames($tag_names);
    }

    public function getOrCreate(string $tag_name): Tag
    {
        return $this->storage->retrieveOrCreate($tag_name);
    }

    /**
     * Returns the subset of the given tag IDs that exist, in a single query.
     *
     * @param int[] $tag_ids
     * @return int[]
     */
    public function getExistingIds(array $tag_ids): array
    {
        return $this->storage->retrieveExistingIds($tag_ids);
    }

    public function getAll(): array
    {
        return $this->storage->retrieve();
    }

    public function getAllForPage(): array
    {
        return $this->storage->retrieveAllTagsForPage();
    }

    /**
     * Get all tags for a given media item.
     */
    public function getTagsForMedia(Media $media): array
    {
        return $this->storage->retrieveTagsForMedia($media);
    }

    public function totalTags(): int
    {
        return $this->storage->retrieveTotalTagCount();
    }

    /**
     * Add tags to a media item.
     */
    public function addTagsToMedia(Media $media, array $tag_ids): bool
    {
        return $this->storage->addTagsToMedia($media, $tag_ids);
    }

    /**
     * Remove a tag from a media item.
     */
    public function removeTagFromMedia(Media $media, Tag $tag): bool
    {
        return $this->storage->removeTagFromMedia($media, $tag);
    }

    public function save(Tag $tag): int
    {
        return $this->storage->store($tag);
    }

    public function delete(Tag $tag): bool
    {
        return $this->storage->delete($tag);
    }

    public function migrateTag(Tag $sourceTag, Tag $targetTag): bool
    {
        return $this->storage->migrateTag($sourceTag, $targetTag);
    }

    // ========================================================================
    // Tag Implication Methods
    // ========================================================================

    public function getAllImplications(): array
    {
        return $this->storage->retrieveAllImplications();
    }

    public function addImplication(int $tag_id, int $implied_tag_id): bool
    {
        return $this->storage->addImplication($tag_id, $implied_tag_id);
    }

    public function removeImplication(int $tag_id, int $implied_tag_id): bool
    {
        return $this->storage->removeImplication($tag_id, $implied_tag_id);
    }
}
