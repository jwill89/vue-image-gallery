<?php

namespace Routes\Internal;

use Psr\Container\ContainerInterface;
use Slim\Http\ServerRequest as Request;
use Slim\Http\Response;
use Gallery\Collection\DanbooruRulesCollection;
use Gallery\Collection\TagCategoryCollection;

/**
 * DanbooruController
 * Manages Danbooru import rules (category mappings and tag name mappings).
 */
class DanbooruController extends AbstractController
{
    private DanbooruRulesCollection $rules;
    private TagCategoryCollection $categories;

    public function __construct(ContainerInterface $container, DanbooruRulesCollection $rules, TagCategoryCollection $categories)
    {
        parent::__construct($container);
        $this->rules = $rules;
        $this->categories = $categories;
    }

    // ========================================================================
    // Get all rules (category map + tag map)
    // ========================================================================

    public function getRules(Request $request, Response $response, array $args): Response
    {
        return $this->success($response, [
            'category_mappings' => $this->rules->getCategoryMappings(),
            'tag_mappings' => $this->rules->getTagMappings(),
        ]);
    }

    // ========================================================================
    // Category Map CRUD
    // ========================================================================

    public function addCategoryMapping(Request $request, Response $response, array $args): Response
    {
        $params = $request->getParsedBody() ?? [];
        $danbooruCategoryId = (int) ($params['danbooru_category_id'] ?? -1);
        $danbooruCategoryName = trim($params['danbooru_category_name'] ?? '');
        $galleryCategoryId = (int) ($params['gallery_category_id'] ?? 0);

        if ($danbooruCategoryId < 0) {
            return $this->error($response, 'InvalidInput', 400, 'Danbooru category ID must be a non-negative integer.');
        }
        if (empty($danbooruCategoryName)) {
            return $this->error($response, 'InvalidInput', 400, 'Danbooru category name is required.');
        }
        if ($galleryCategoryId <= 0) {
            return $this->error($response, 'InvalidInput', 400, 'A valid gallery category must be selected.');
        }

        $category = $this->categories->get($galleryCategoryId);
        if ($category === null) {
            return $this->error($response, 'CategoryNotFound', 404, 'The selected gallery category does not exist.');
        }

        $success = $this->rules->saveCategoryMapping($danbooruCategoryId, $danbooruCategoryName, $galleryCategoryId);
        if (!$success) {
            return $this->error($response, 'SaveFailed', 500, 'Could not save the category mapping.');
        }

        $this->logger->info('Danbooru category mapping saved', [
            'danbooru_category_id' => $danbooruCategoryId,
            'gallery_category_id' => $galleryCategoryId,
        ]);

        return $this->success($response, $this->rules->getCategoryMappings());
    }

    public function deleteCategoryMapping(Request $request, Response $response, array $args): Response
    {
        $params = $request->getParsedBody() ?? [];
        $danbooruCategoryId = (int) ($params['danbooru_category_id'] ?? -1);

        if ($danbooruCategoryId < 0) {
            return $this->error($response, 'InvalidInput', 400, 'Danbooru category ID must be a non-negative integer.');
        }

        $success = $this->rules->deleteCategoryMapping($danbooruCategoryId);
        if (!$success) {
            return $this->error($response, 'DeleteFailed', 500, 'Could not delete the category mapping.');
        }

        $this->logger->info('Danbooru category mapping deleted', ['danbooru_category_id' => $danbooruCategoryId]);
        return $this->success($response, $this->rules->getCategoryMappings());
    }

    // ========================================================================
    // Tag Name Map CRUD
    // ========================================================================

    public function addTagMapping(Request $request, Response $response, array $args): Response
    {
        $params = $request->getParsedBody() ?? [];
        $danbooruTag = trim($params['danbooru_tag'] ?? '');
        $galleryTag = trim($params['gallery_tag'] ?? '');

        if (empty($danbooruTag)) {
            return $this->error($response, 'InvalidInput', 400, 'Danbooru tag name is required.');
        }
        if (empty($galleryTag)) {
            return $this->error($response, 'InvalidInput', 400, 'Gallery tag name is required.');
        }
        if ($this->rules->tagMappingExists($danbooruTag)) {
            return $this->error($response, 'DuplicateMapping', 400, "A mapping for \"{$danbooruTag}\" already exists.");
        }

        $id = $this->rules->addTagMapping($danbooruTag, $galleryTag);
        if ($id === 0) {
            return $this->error($response, 'SaveFailed', 500, 'Could not save the tag mapping.');
        }

        $this->logger->info('Danbooru tag mapping added', ['danbooru_tag' => $danbooruTag, 'gallery_tag' => $galleryTag]);
        return $this->success($response, $this->rules->getTagMappings());
    }

    public function editTagMapping(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $params = $request->getParsedBody() ?? [];
        $danbooruTag = trim($params['danbooru_tag'] ?? '');
        $galleryTag = trim($params['gallery_tag'] ?? '');

        if ($id <= 0) {
            return $this->error($response, 'InvalidInput', 400, 'A valid mapping ID is required.');
        }
        if (empty($danbooruTag)) {
            return $this->error($response, 'InvalidInput', 400, 'Danbooru tag name is required.');
        }
        if (empty($galleryTag)) {
            return $this->error($response, 'InvalidInput', 400, 'Gallery tag name is required.');
        }
        if ($this->rules->tagMappingExists($danbooruTag, $id)) {
            return $this->error($response, 'DuplicateMapping', 400, "A mapping for \"{$danbooruTag}\" already exists.");
        }

        $success = $this->rules->updateTagMapping($id, $danbooruTag, $galleryTag);
        if (!$success) {
            return $this->error($response, 'SaveFailed', 500, 'Could not save the tag mapping.');
        }

        $this->logger->info('Danbooru tag mapping updated', ['id' => $id, 'danbooru_tag' => $danbooruTag]);
        return $this->success($response, $this->rules->getTagMappings());
    }

    public function deleteTagMapping(Request $request, Response $response, array $args): Response
    {
        $params = $request->getParsedBody() ?? [];
        $id = (int) ($params['id'] ?? 0);

        if ($id <= 0) {
            return $this->error($response, 'InvalidInput', 400, 'A valid mapping ID is required.');
        }

        $success = $this->rules->deleteTagMapping($id);
        if (!$success) {
            return $this->error($response, 'DeleteFailed', 500, 'Could not delete the tag mapping.');
        }

        $this->logger->info('Danbooru tag mapping deleted', ['id' => $id]);
        return $this->success($response, $this->rules->getTagMappings());
    }
}
