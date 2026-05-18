<?php

namespace Gallery\Collection;

use Gallery\Storage\TagStorage;
use Gallery\Structure\Tag;
use Gallery\Structure\Image;
use Gallery\Structure\Video;

/**
 * TagCollection class
 * This class is responsible for managing a collection of tags and interacting with the database.
 */
class TagCollection
{
    // Tag Database Storage Object
    private TagStorage $storage;

    /**
     * TagCollection constructor.
     * Initializes the TagStorage object.
     */
    public function __construct()
    {
        if (!isset($this->storage)) {
            $this->storage = new TagStorage();
        }
    }

    /**
     * get function
     * Gets a tag based on supplied tag ID.
     *
     * @param int $tag_id
     * @return Tag|null
     */
    public function get(int $tag_id): ?Tag
    {
        return $this->storage->retrieve($tag_id);
    }

    /**
     * getByName function
     * Gets a tag based on supplied tag name, if it exists
     *
     * @param string $tag_name
     * @return Tag|null
     */
    public function getByName(string $tag_name): ?Tag
    {
        return $this->storage->retrieveByName($tag_name);
    }

   /**
    * getOrCreate function
    * Retrieves a tag if it exists or creates one and returns it if it doesn't.
    *
    * @param string $tag_name
    * @return Tag
    */
    public function getOrCreate(string $tag_name): Tag
    {
        return $this->storage->retrieveOrCreate($tag_name);
    }

    /**
     * getAll function
     * Gets all tags.
     *
     * @return Tag[]
     */
    public function getAll(): array
    {
        return $this->storage->retrieve();
    }

    /**
     * getAllForPage function
     * Gets all tags for a given page.
     *
     * @return array
     */
    public function getAllForPage(): array
    {
        return $this->storage->retrieveAllTagsForPage();
    }

    /**
     * getTagsForImage function
     * Get all tags for a given image
     *
     * @param Image $image
     * @return array
     */
    public function getTagsForImage(Image $image): array
    {
        return $this->storage->retrieveTagsForImage($image);
    }

    /**
     * getTagsForVideo function
     * Get all tags for a given video
     *
     * @param Video $video
     * @return array
     */
    public function getTagsForVideo(Video $video): array
    {
        return $this->storage->retrieveTagsForVideo($video);
    }

    /**
     * totalTags function
     * Gets the total number of tags in the database.
     *
     * @return int
     */
    public function totalTags(): int
    {
        return $this->storage->retrieveTotalTagCount();
    }

    /**
     * addTagToImage function
     * Adds the supplied tag to the supplied image.
     *
     * @param Image $image
     * @param array $tag_ids
     * @return bool
     */
    public function addTagsToImage(Image $image, array $tag_ids): bool
    {
        return $this->storage->addTagsToImage($image, $tag_ids);
    }

    /**
     * addTagToVideo function
     * Adds the supplied tag to the supplied video.
     *
     * @param Video $video
     * @param array $tag_ids
     * @return bool
     */
    public function addTagsToVideo(Video $video, array $tag_ids): bool
    {
        return $this->storage->addTagsToVideo($video, $tag_ids);
    }

    /**
     * removeTagFromImage function
     * Removed the supplied tag from the supplied image.
     *
     * @param Image $image
     * @param Tag $tag
     * @return bool
     */
    public function removeTagFromImage(Image $image, Tag $tag): bool
    {
        return $this->storage->removeTagFromImage($image, $tag);
    }

    /**
     * removeTagFromVideo function
     * Removed the supplied tag from the supplied video.
     *
     * @param Video $video
     * @param Tag $tag
     * @return boolean
     */
    public function removeTagFromVideo(Video $video, Tag $tag): bool
    {
        return $this->storage->removeTagFromVideo($video, $tag);
    }

    /**
     * save function
     * Saves an tag to the database and generates a thumbnail.
     *
     * @param Tag $tag
     * @return int
     */
    public function save(Tag $tag): int
    {
        // Save the tag to the database
        return $this->storage->store($tag);
    }

    /**
     * delete function
     * Deletes an tag from the database and the filesystem.
     *
     * @param Tag $tag
     * @return bool
     */
    public function delete(Tag $tag): bool
    {
        return $this->storage->delete($tag);
    }

    /**
     * migrateTag function
     * Migrates all usages of one tag to another.
     *
     * @param Tag $sourceTag
     * @param Tag $targetTag
     * @return bool
     */
    public function migrateTag(Tag $sourceTag, Tag $targetTag): bool
    {
        return $this->storage->migrateTag($sourceTag, $targetTag);
    }
}
