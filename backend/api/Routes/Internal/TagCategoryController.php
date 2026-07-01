<?php

namespace Routes\Internal;

use Slim\Http\ServerRequest as Request;
use Slim\Http\Response;
use Gallery\Core\CacheGroup;
use Gallery\Repository\TagCategoryRepository;
use Gallery\Structure\TagCategory;
use OpenApi\Attributes as OA;

/**
 * TagCategoryController
 * CRUD for tag categories (the `/tag-categories` resource).
 */
class TagCategoryController extends AbstractController
{
    private const int MAX_CATEGORY_NAME_LENGTH = 50;
    private const int MAX_SHORTCODE_LENGTH = 5;
    private const array VALID_COLORS = [
        // Bulma built-in
        'white', 'light', 'dark', 'primary', 'link', 'info', 'success', 'warning', 'danger',
        // Extended palette (defined in frontend style.css)
        'teal', 'purple', 'pink', 'orange', 'cyan', 'lime', 'indigo', 'rose', 'amber', 'emerald',
    ];

    private TagCategoryRepository $category_repository;

    public function __construct(TagCategoryRepository $category_repository)
    {
        parent::__construct();
        $this->category_repository = $category_repository;
    }

    /**
     * GET /tag-categories — All tag categories.
     */
    #[OA\Get(
        path: '/tag-categories',
        summary: 'List tag categories',
        tags: ['Tag Categories'],
        responses: [
            new OA\Response(response: 200, description: 'All categories', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/TagCategory'))),
        ]
    )]
    public function getCategories(Request $request, Response $response): Response
    {
        return $this->success($response, $this->category_repository->getAll());
    }

    /**
     * POST /tag-categories — Create a tag category. Returns the created category.
     * Body: { category_name, category_short, color?, description?, sort_order? }
     */
    #[OA\Post(
        path: '/tag-categories',
        summary: 'Create a tag category',
        tags: ['Tag Categories'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['category_name', 'category_short'],
            properties: [
                new OA\Property(property: 'category_name', type: 'string'),
                new OA\Property(property: 'category_short', type: 'string'),
                new OA\Property(property: 'color', type: 'string'),
                new OA\Property(property: 'description', type: 'string'),
                new OA\Property(property: 'sort_order', type: 'integer'),
            ]
        )),
        responses: [
            new OA\Response(response: 201, description: 'The created category', content: new OA\JsonContent(ref: '#/components/schemas/TagCategory')),
            new OA\Response(response: 400, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function addCategory(Request $request, Response $response): Response
    {
        $result = $this->buildValidatedCategory($response, $this->parsedBody($request));
        if ($result instanceof Response) {
            return $result;
        }
        $category = $result;

        $id = $this->category_repository->save($category);
        if ($id === 0) {
            return $this->error($response, 'SaveFailed', 500, 'Could not create the category.');
        }

        $this->invalidateCache(CacheGroup::Tags);
        $this->logger->info('Category created', ['category_id' => $id, 'name' => $category->category_name]);
        return $this->created($response, $category);
    }

    /**
     * PUT /tag-categories/{category_id} — Update a tag category.
     * Returns the updated category. Body: same fields as create.
     */
    #[OA\Put(
        path: '/tag-categories/{category_id}',
        summary: 'Update a tag category',
        tags: ['Tag Categories'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'category_id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['category_name', 'category_short'],
            properties: [
                new OA\Property(property: 'category_name', type: 'string'),
                new OA\Property(property: 'category_short', type: 'string'),
                new OA\Property(property: 'color', type: 'string'),
                new OA\Property(property: 'description', type: 'string'),
                new OA\Property(property: 'sort_order', type: 'integer'),
            ]
        )),
        responses: [
            new OA\Response(response: 200, description: 'The updated category', content: new OA\JsonContent(ref: '#/components/schemas/TagCategory')),
            new OA\Response(response: 404, description: 'CategoryNotFound', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function editCategory(Request $request, Response $response, array $args): Response
    {
        $categoryId = $this->intParam($args, 'category_id', 0);
        if ($categoryId <= 0) {
            return $this->error($response, 'InvalidInput', 400, 'A valid category ID is required.');
        }

        $existing = $this->category_repository->get($categoryId);
        if ($existing === null) {
            return $this->error($response, 'CategoryNotFound', 404, 'The category does not exist.');
        }

        $result = $this->buildValidatedCategory($response, $this->parsedBody($request), $existing);
        if ($result instanceof Response) {
            return $result;
        }
        $category = $result;

        if ($this->category_repository->save($category) === 0) {
            return $this->error($response, 'SaveFailed', 500, 'Could not save the category.');
        }

        $this->invalidateCache(CacheGroup::Tags);
        $this->logger->info('Category edited', ['category_id' => $categoryId, 'name' => $category->category_name]);
        return $this->success($response, $category);
    }

    /**
     * DELETE /tag-categories/{category_id} — Delete a category that has no tags.
     */
    #[OA\Delete(
        path: '/tag-categories/{category_id}',
        summary: 'Delete a tag category',
        tags: ['Tag Categories'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'category_id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Deleted'),
            new OA\Response(response: 400, description: 'CategoryInUse', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'CategoryNotFound', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function deleteCategory(Request $request, Response $response, array $args): Response
    {
        $categoryId = $this->intParam($args, 'category_id', 0);

        if ($categoryId <= 0) {
            return $this->error($response, 'InvalidInput', 400, 'A valid category ID is required.');
        }

        $category = $this->category_repository->get($categoryId);
        if ($category === null) {
            return $this->error($response, 'CategoryNotFound', 404, 'The category does not exist.');
        }

        $tagCount = $this->category_repository->countTags($categoryId);
        if ($tagCount > 0) {
            return $this->error(
                $response,
                'CategoryInUse',
                400,
                "Cannot delete \"{$category->category_name}\" because {$tagCount} tag(s) belong to it. Reassign them first."
            );
        }

        $deleted = $this->category_repository->delete($category);
        if (!$deleted) {
            return $this->error($response, 'DeleteFailed', 500, 'Could not delete the category.');
        }

        $this->invalidateCache(CacheGroup::Tags);
        $this->logger->info('Category deleted', ['category_id' => $categoryId]);
        return $this->noContent($response);
    }

    /**
     * Validates category input from the request and applies it to a TagCategory.
     *
     * On success returns the populated category (fresh, or $existing mutated in
     * place). On any validation failure returns the error Response to send back.
     * When $existing is given, its ID is excluded from the name/shortcode
     * conflict check.
     *
     * @param array<string, mixed> $params
     * @return TagCategory|Response
     */
    private function buildValidatedCategory(Response $response, array $params, ?TagCategory $existing = null): TagCategory|Response
    {
        $name = trim($params['category_name'] ?? '');
        $short = trim(strtolower($params['category_short'] ?? ''));
        $color = trim($params['color'] ?? 'white');
        $description = trim($params['description'] ?? '');
        $sortOrder = (int) ($params['sort_order'] ?? 0);

        if (empty($name)) {
            return $this->error($response, 'InvalidInput', 400, 'Category name is required.');
        }
        if (mb_strlen($name) > self::MAX_CATEGORY_NAME_LENGTH) {
            return $this->error($response, 'NameTooLong', 400, 'Category name must be ' . self::MAX_CATEGORY_NAME_LENGTH . ' characters or fewer.');
        }
        if (empty($short)) {
            return $this->error($response, 'InvalidInput', 400, 'A shortcode is required.');
        }
        if (mb_strlen($short) > self::MAX_SHORTCODE_LENGTH) {
            return $this->error($response, 'ShortcodeTooLong', 400, 'Shortcode must be ' . self::MAX_SHORTCODE_LENGTH . ' characters or fewer.');
        }
        if (!in_array($color, self::VALID_COLORS, true)) {
            return $this->error($response, 'InvalidColor', 400, 'The selected color is not valid.');
        }

        $excludeId = $existing instanceof TagCategory ? $existing->category_id : 0;
        $conflicts = $this->category_repository->checkConflicts($name, $short, $excludeId);
        if (in_array('name', $conflicts, true)) {
            return $this->error($response, 'NameTaken', 400, "A category named \"{$name}\" already exists.");
        }
        if (in_array('short', $conflicts, true)) {
            return $this->error($response, 'ShortcodeTaken', 400, "The shortcode \"{$short}\" is already in use.");
        }

        $category = $existing ?? new TagCategory();
        return $category->setCategoryName($name)
                 ->setCategoryShort($short)
                 ->setColor($color)
                 ->setDescription($description)
                 ->setSortOrder($sortOrder);
    }
}
