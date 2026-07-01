<?php

namespace Routes\Internal;

use Slim\Http\ServerRequest as Request;
use Slim\Http\Response;
use Gallery\Repository\DanbooruRulesRepository;
use Gallery\Repository\TagCategoryRepository;
use OpenApi\Attributes as OA;

/**
 * DanbooruController
 * Manages Danbooru import rules: the `/danbooru/category-mappings` and
 * `/danbooru/tag-mappings` resources.
 */
class DanbooruController extends AbstractController
{
    private DanbooruRulesRepository $rules;
    private TagCategoryRepository $categories;

    public function __construct(DanbooruRulesRepository $rules, TagCategoryRepository $categories)
    {
        parent::__construct();
        $this->rules = $rules;
        $this->categories = $categories;
    }

    // ── Category mappings ───────────────────────────────────────────────

    /**
     * GET /danbooru/category-mappings — All Danbooru→gallery category mappings.
     */
    #[OA\Get(
        path: '/danbooru/category-mappings',
        summary: 'List category mappings',
        tags: ['Danbooru'],
        responses: [
            new OA\Response(response: 200, description: 'Category mappings', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/CategoryMapping'))),
        ]
    )]
    public function getCategoryMappings(Request $request, Response $response): Response
    {
        return $this->success($response, $this->rules->getCategoryMappings());
    }

    /**
     * POST /danbooru/category-mappings — Map a Danbooru category to a gallery
     * category. Body: { danbooru_category_id, danbooru_category_name, gallery_category_id }.
     */
    #[OA\Post(
        path: '/danbooru/category-mappings',
        summary: 'Create a category mapping',
        tags: ['Danbooru'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['danbooru_category_id', 'danbooru_category_name', 'gallery_category_id'],
            properties: [
                new OA\Property(property: 'danbooru_category_id', type: 'integer'),
                new OA\Property(property: 'danbooru_category_name', type: 'string'),
                new OA\Property(property: 'gallery_category_id', type: 'integer'),
            ]
        )),
        responses: [
            new OA\Response(response: 201, description: 'The created mapping', content: new OA\JsonContent(ref: '#/components/schemas/CategoryMapping')),
            new OA\Response(response: 400, description: 'InvalidInput', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'CategoryNotFound', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function addCategoryMapping(Request $request, Response $response): Response
    {
        $params = $this->parsedBody($request);
        $danbooruCategoryId = $this->intParam($params, 'danbooru_category_id', -1);
        $danbooruCategoryName = trim($params['danbooru_category_name'] ?? '');
        $galleryCategoryId = $this->intParam($params, 'gallery_category_id', 0);

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

        return $this->created($response, [
            'danbooru_category_id' => $danbooruCategoryId,
            'danbooru_category_name' => $danbooruCategoryName,
            'gallery_category_id' => $galleryCategoryId,
            'gallery_category_name' => $category->category_name,
        ]);
    }

    /**
     * DELETE /danbooru/category-mappings/{danbooru_category_id} — Remove a mapping.
     */
    #[OA\Delete(
        path: '/danbooru/category-mappings/{danbooru_category_id}',
        summary: 'Delete a category mapping',
        tags: ['Danbooru'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'danbooru_category_id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Deleted'),
            new OA\Response(response: 400, description: 'InvalidInput', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function deleteCategoryMapping(Request $request, Response $response, array $args): Response
    {
        $danbooruCategoryId = $this->intParam($args, 'danbooru_category_id', -1);

        if ($danbooruCategoryId < 0) {
            return $this->error($response, 'InvalidInput', 400, 'Danbooru category ID must be a non-negative integer.');
        }

        $success = $this->rules->deleteCategoryMapping($danbooruCategoryId);
        if (!$success) {
            return $this->error($response, 'DeleteFailed', 500, 'Could not delete the category mapping.');
        }

        $this->logger->info('Danbooru category mapping deleted', ['danbooru_category_id' => $danbooruCategoryId]);
        return $this->noContent($response);
    }

    // ── Tag name mappings ───────────────────────────────────────────────

    /**
     * GET /danbooru/tag-mappings — All Danbooru→gallery tag-name mappings.
     */
    #[OA\Get(
        path: '/danbooru/tag-mappings',
        summary: 'List tag name mappings',
        tags: ['Danbooru'],
        responses: [
            new OA\Response(response: 200, description: 'Tag name mappings', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/TagMapping'))),
        ]
    )]
    public function getTagMappings(Request $request, Response $response): Response
    {
        return $this->success($response, $this->rules->getTagMappings());
    }

    /**
     * POST /danbooru/tag-mappings — Map a Danbooru tag name to a gallery tag name.
     * Body: { danbooru_tag, gallery_tag }.
     */
    #[OA\Post(
        path: '/danbooru/tag-mappings',
        summary: 'Create a tag name mapping',
        tags: ['Danbooru'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['danbooru_tag', 'gallery_tag'],
            properties: [
                new OA\Property(property: 'danbooru_tag', type: 'string'),
                new OA\Property(property: 'gallery_tag', type: 'string'),
            ]
        )),
        responses: [
            new OA\Response(response: 201, description: 'The created mapping', content: new OA\JsonContent(ref: '#/components/schemas/TagMapping')),
            new OA\Response(response: 400, description: 'InvalidInput / DuplicateMapping', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function addTagMapping(Request $request, Response $response): Response
    {
        $params = $this->parsedBody($request);
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
        return $this->created($response, ['id' => $id, 'danbooru_tag' => $danbooruTag, 'gallery_tag' => $galleryTag]);
    }

    /**
     * PUT /danbooru/tag-mappings/{id} — Update a tag-name mapping.
     * Body: { danbooru_tag, gallery_tag }.
     */
    #[OA\Put(
        path: '/danbooru/tag-mappings/{id}',
        summary: 'Update a tag name mapping',
        tags: ['Danbooru'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['danbooru_tag', 'gallery_tag'],
            properties: [
                new OA\Property(property: 'danbooru_tag', type: 'string'),
                new OA\Property(property: 'gallery_tag', type: 'string'),
            ]
        )),
        responses: [
            new OA\Response(response: 200, description: 'The updated mapping', content: new OA\JsonContent(ref: '#/components/schemas/TagMapping')),
            new OA\Response(response: 400, description: 'InvalidInput / DuplicateMapping', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function editTagMapping(Request $request, Response $response, array $args): Response
    {
        $id = $this->intParam($args, 'id', 0);
        $params = $this->parsedBody($request);
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
        return $this->success($response, ['id' => $id, 'danbooru_tag' => $danbooruTag, 'gallery_tag' => $galleryTag]);
    }

    /**
     * DELETE /danbooru/tag-mappings/{id} — Remove a tag-name mapping.
     */
    #[OA\Delete(
        path: '/danbooru/tag-mappings/{id}',
        summary: 'Delete a tag name mapping',
        tags: ['Danbooru'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Deleted'),
            new OA\Response(response: 400, description: 'InvalidInput', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function deleteTagMapping(Request $request, Response $response, array $args): Response
    {
        $id = $this->intParam($args, 'id', 0);

        if ($id <= 0) {
            return $this->error($response, 'InvalidInput', 400, 'A valid mapping ID is required.');
        }

        $success = $this->rules->deleteTagMapping($id);
        if (!$success) {
            return $this->error($response, 'DeleteFailed', 500, 'Could not delete the tag mapping.');
        }

        $this->logger->info('Danbooru tag mapping deleted', ['id' => $id]);
        return $this->noContent($response);
    }
}
