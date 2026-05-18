<?php

namespace Routes\Internal;

use Psr\Container\ContainerInterface;
use Gallery\Collection\ImageCollection;

/**
 * ImageController class
 * Handles image-related API requests. Extends MediaController for shared pagination/retrieval logic.
 */
class ImageController extends MediaController
{
    private ImageCollection $image_collection;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->image_collection = new ImageCollection();
    }

    protected function getCollection(): ImageCollection
    {
        return $this->image_collection;
    }

    protected function getIdParam(): string
    {
        return 'image_id';
    }

    protected function getEntityName(): string
    {
        return 'Image';
    }

    protected function getTotalCount(): int
    {
        return $this->image_collection->totalImages();
    }

    protected function getTotalCountWithTags(array $tag_ids): int
    {
        return $this->image_collection->totalImagesWithTags($tag_ids);
    }
}
