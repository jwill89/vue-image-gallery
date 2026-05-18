<?php

namespace Routes\Internal;

use Psr\Container\ContainerInterface;
use Gallery\Collection\VideoCollection;

/**
 * VideoController class
 * Handles video-related API requests. Extends MediaController for shared pagination/retrieval logic.
 */
class VideoController extends MediaController
{
    private VideoCollection $video_collection;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->video_collection = new VideoCollection();
    }

    protected function getCollection(): VideoCollection
    {
        return $this->video_collection;
    }

    protected function getIdParam(): string
    {
        return 'video_id';
    }

    protected function getEntityName(): string
    {
        return 'Video';
    }

    protected function getTotalCount(): int
    {
        return $this->video_collection->totalVideos();
    }

    protected function getTotalCountWithTags(array $tag_ids): int
    {
        return $this->video_collection->totalVideosWithTags($tag_ids);
    }
}
