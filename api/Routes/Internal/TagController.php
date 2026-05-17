<?php

namespace Routes\Internal;

use Psr\Container\ContainerInterface;
use Slim\Http\ServerRequest as Request;
use Slim\Http\Response;
use Gallery\Collection\TagCollection;
use Gallery\Collection\ImageCollection;
use Gallery\Collection\VideoCollection;
use Gallery\Structure\Image;
use Gallery\Structure\Video;
use Gallery\Structure\Tag;

/**
 * TagController class
 * This class is responsible for handling tag-related requests for the API.
 */
class TagController extends AbstractController
{
    // Maximum allowed tag name length
    private const int MAX_TAG_NAME_LENGTH = 100;

    // Valid category IDs
    private const array VALID_CATEGORY_IDS = [1, 2, 3, 4, 5];

    private TagCollection $tag_collection;
    private ImageCollection $image_collection;
    private VideoCollection $video_collection;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->tag_collection = new TagCollection();
        $this->image_collection = new ImageCollection();
        $this->video_collection = new VideoCollection();
    }

    public function getTag(Request $request, Response $response, array $args): Response
    {
        $tag_id = $this->parseParameters($args, 'tag_id', null);

        if ($tag_id === null) {
            return $response->withJson(['error' => 'NoTagIDProvided'], 400);
        }

        if (!is_numeric($tag_id) || $tag_id <= 0) {
            return $response->withJson(['error' => 'InvalidTagID'], 404);
        }

        $data = $this->tag_collection->get((int)$tag_id);
        return $response->withJson($data, 200);
    }

    public function getAllTags(Request $request, Response $response, array $args): Response
    {
        return $response->withJson($this->tag_collection->getAll(), 200);
    }

    public function getTagListForDisplay(Request $request, Response $response, array $args): Response
    {
        return $response->withJson($this->tag_collection->getAllForPage(), 200);
    }

    public function addTag(Request $request, Response $response, array $args): Response
    {
        $params = $request->getParsedBody();
        $tag_name = $this->sanitizeTagName($this->parseParameters($params, 'tag_name', ''));
        $tag_category = (int)$this->parseParameters($params, 'category_id', 1);

        if (empty($tag_name)) {
            return $response->withJson(['error' => 'InvalidTagName'], 400);
        }
        if (mb_strlen($tag_name) > self::MAX_TAG_NAME_LENGTH) {
            return $response->withJson(['error' => 'TagNameTooLong'], 400);
        }
        if (!in_array($tag_category, self::VALID_CATEGORY_IDS, true)) {
            return $response->withJson(['error' => 'InvalidCategoryID'], 400);
        }
        if ($this->tag_collection->getByName($tag_name) instanceof Tag) {
            return $response->withJson(['error' => 'TagAlreadyExists'], 400);
        }

        $tag = new Tag();
        $tag->setTagName($tag_name)->setCategoryId($tag_category);
        $tag_id = $this->tag_collection->save($tag);

        if ($tag_id === 0) {
            $this->logger->error('Failed to create tag', ['tag_name' => $tag_name]);
            return $response->withJson(['error' => 'CouldNotCreateTag'], 500);
        }

        $this->logger->info('Tag created', ['tag_id' => $tag_id, 'tag_name' => $tag_name, 'category_id' => $tag_category]);
        return $response->withJson(true, 200);
    }

    public function editTag(Request $request, Response $response, array $args): Response
    {
        $params = $request->getParsedBody();
        $tag_id = (int)$this->parseParameters($args, 'tag_id', 0);
        $tag_name = $this->sanitizeTagName($this->parseParameters($params, 'tag_name', ''));
        $tag_category = (int)$this->parseParameters($params, 'category_id', 1);

        if ($tag_id <= 0) {
            return $response->withJson(['error' => 'InvalidTagID'], 400);
        }
        if (empty($tag_name)) {
            return $response->withJson(['error' => 'InvalidTagName'], 400);
        }
        if (mb_strlen($tag_name) > self::MAX_TAG_NAME_LENGTH) {
            return $response->withJson(['error' => 'TagNameTooLong'], 400);
        }
        if (!in_array($tag_category, self::VALID_CATEGORY_IDS, true)) {
            return $response->withJson(['error' => 'InvalidCategoryID'], 400);
        }

        $tag = $this->tag_collection->get($tag_id);
        if (!($tag instanceof Tag)) {
            return $response->withJson(['error' => 'TagDoesNotExist'], 404);
        }

        $tag->setTagName($tag_name)->setCategoryId($tag_category);
        $saved_id = $this->tag_collection->save($tag);

        if ($saved_id === 0) {
            $this->logger->error('Failed to save tag edit', ['tag_id' => $tag_id]);
            return $response->withJson(['error' => 'CouldNotSaveTag'], 500);
        }

        $this->logger->info('Tag edited', ['tag_id' => $tag_id, 'tag_name' => $tag_name, 'category_id' => $tag_category]);
        return $response->withJson(true, 200);
    }

    public function getTagsForImage(Request $request, Response $response, array $args): Response
    {
        return $this->getTagsForMedia('image', $args, $response);
    }

    public function getTagsForVideo(Request $request, Response $response, array $args): Response
    {
        return $this->getTagsForMedia('video', $args, $response);
    }

    public function addTagsToImage(Request $request, Response $response, array $args): Response
    {
        return $this->addTagsToMedia('image', $request, $response);
    }

    public function addTagsToVideo(Request $request, Response $response, array $args): Response
    {
        return $this->addTagsToMedia('video', $request, $response);
    }

    public function removeTagFromImage(Request $request, Response $response, array $args): Response
    {
        return $this->removeTagFromMedia('image', $request, $response);
    }

    public function removeTagFromVideo(Request $request, Response $response, array $args): Response
    {
        return $this->removeTagFromMedia('video', $request, $response);
    }

    // ========================================================================
    // Private helper methods
    // ========================================================================

    private function getTagsForMedia(string $type, array $args, Response $response): Response
    {
        $id_key = $type . '_id';
        $media_id = $this->parseParameters($args, $id_key, 0);

        if (!is_numeric($media_id) || $media_id <= 0) {
            return $response->withJson(['error' => 'Invalid' . ucfirst($type) . 'ID'], 400);
        }

        $media = ($type === 'image')
            ? $this->image_collection->get((int)$media_id)
            : $this->video_collection->get((int)$media_id);

        if ($media === null) {
            return $response->withJson(['error' => ucfirst($type) . 'DoesNotExist'], 404);
        }

        $tags = ($type === 'image')
            ? $this->tag_collection->getTagsForImage($media)
            : $this->tag_collection->getTagsForVideo($media);

        return $response->withJson($tags, 200);
    }

    private function addTagsToMedia(string $type, Request $request, Response $response): Response
    {
        $params = $request->getParsedBody();
        $media_id = (int)$this->parseParameters($params, 'item_id', 0);
        $tag_list = array_unique(array_map('trim', explode(',', $this->parseParameters($params, 'tag_list', ''))));

        if ($media_id <= 0) {
            return $response->withJson(['error' => 'Invalid' . ucfirst($type) . 'ID'], 400);
        }

        $media = ($type === 'image')
            ? $this->image_collection->get($media_id)
            : $this->video_collection->get($media_id);

        if ($media === null) {
            return $response->withJson(['error' => ucfirst($type) . 'DoesNotExist'], 404);
        }

        if (empty($tag_list) || (count($tag_list) === 1 && $tag_list[0] === '')) {
            return $response->withJson(['error' => 'InvalidTagList'], 400);
        }

        // Get or create tags and collect IDs
        $tag_ids = [];
        foreach ($tag_list as $tag_name) {
            if (empty($tag_name)) {
                continue;
            }
            $tag = $this->tag_collection->getOrCreate($tag_name);
            $tag_ids[] = $tag->getTagId();
        }

        // Add tags
        $success = ($type === 'image')
            ? $this->tag_collection->addTagsToImage($media, $tag_ids)
            : $this->tag_collection->addTagsToVideo($media, $tag_ids);

        if (!$success) {
            $this->logger->error("Failed to add tags to $type", ['media_id' => $media_id, 'tag_ids' => $tag_ids]);
            return $response->withJson(['error' => 'CouldNotAddAllTagsTo' . ucfirst($type)], 500);
        }

        $this->logger->info("Tags added to $type", ['media_id' => $media_id, 'tag_ids' => $tag_ids]);

        // Return updated tags
        $data = ($type === 'image')
            ? $this->tag_collection->getTagsForImage($media)
            : $this->tag_collection->getTagsForVideo($media);

        return $response->withJson($data, 200);
    }

    private function removeTagFromMedia(string $type, Request $request, Response $response): Response
    {
        $params = $request->getParsedBody();
        $media_id = (int)$this->parseParameters($params, 'item_id', 0);
        $tag_id = (int)$this->parseParameters($params, 'tag_id', 0);

        if ($media_id <= 0) {
            return $response->withJson(['error' => 'Invalid' . ucfirst($type) . 'ID'], 400);
        }

        $media = ($type === 'image')
            ? $this->image_collection->get($media_id)
            : $this->video_collection->get($media_id);

        if ($media === null) {
            return $response->withJson(['error' => ucfirst($type) . 'DoesNotExist'], 404);
        }

        if ($tag_id <= 0) {
            return $response->withJson(['error' => 'InvalidTagID'], 400);
        }

        $tag = $this->tag_collection->get($tag_id);
        if (!($tag instanceof Tag)) {
            return $response->withJson(['error' => 'CouldNotFindTag'], 404);
        }

        $removed = ($type === 'image')
            ? $this->tag_collection->removeTagFromImage($media, $tag)
            : $this->tag_collection->removeTagFromVideo($media, $tag);

        if (!$removed) {
            $this->logger->error("Failed to remove tag from $type", ['media_id' => $media_id, 'tag_id' => $tag_id]);
            return $response->withJson(['error' => 'CouldNotRemoveTagFrom' . ucfirst($type)], 500);
        }

        $this->logger->info("Tag removed from $type", ['media_id' => $media_id, 'tag_id' => $tag_id]);

        // Return updated tags
        $data = ($type === 'image')
            ? $this->tag_collection->getTagsForImage($media)
            : $this->tag_collection->getTagsForVideo($media);

        return $response->withJson($data, 200);
    }

    /**
     * Trims, lowercases, and strips dangerous characters from a tag name.
     */
    private function sanitizeTagName(string $tag_name): string
    {
        $tag_name = trim($tag_name);
        $tag_name = mb_strtolower($tag_name, 'UTF-8');
        $tag_name = strip_tags($tag_name);
        $tag_name = preg_replace('/[\x00-\x1F\x7F]/u', '', $tag_name);

        return $tag_name;
    }
}
