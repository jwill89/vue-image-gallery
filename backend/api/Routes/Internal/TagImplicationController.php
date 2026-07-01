<?php

namespace Routes\Internal;

use Slim\Http\ServerRequest as Request;
use Slim\Http\Response;
use Gallery\Core\CacheGroup;
use Gallery\Core\ResponseCache;
use Gallery\Repository\TagRepository;
use Gallery\Structure\Tag;
use OpenApi\Attributes as OA;

/**
 * TagImplicationController
 * The `/tag-implications` resource: applying a tag transitively applies the
 * tags it implies.
 */
class TagImplicationController extends AbstractController
{
    private TagRepository $tag_repository;

    public function __construct(TagRepository $tag_repository)
    {
        parent::__construct();
        $this->tag_repository = $tag_repository;
    }

    /**
     * GET /tag-implications — All tag implications. Cached.
     */
    #[OA\Get(
        path: '/tag-implications',
        summary: 'List tag implications',
        tags: ['Tag Implications'],
        responses: [
            new OA\Response(response: 200, description: 'All implications', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/TagImplication'))),
        ]
    )]
    public function getImplications(Request $request, Response $response): Response
    {
        return $this->cachedSuccess($response, CacheGroup::Tags, 'implications', ResponseCache::TTL_MEDIUM, function () {
            return $this->tag_repository->getAllImplications();
        });
    }

    /**
     * POST /tag-implications — Add "tag_id implies implied_tag_id".
     * Body: { tag_id, implied_tag_id }. Rejects cycles and self-implication.
     * Returns the created implication.
     */
    #[OA\Post(
        path: '/tag-implications',
        summary: 'Create a tag implication',
        tags: ['Tag Implications'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['tag_id', 'implied_tag_id'],
            properties: [
                new OA\Property(property: 'tag_id', type: 'integer'),
                new OA\Property(property: 'implied_tag_id', type: 'integer'),
            ]
        )),
        responses: [
            new OA\Response(response: 201, description: 'The created implication', content: new OA\JsonContent(ref: '#/components/schemas/TagImplication')),
            new OA\Response(response: 400, description: 'CannotImplySelf / CycleDetected', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'TagDoesNotExist', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function addImplication(Request $request, Response $response): Response
    {
        $params = $this->parsedBody($request);
        $tag_id = $this->intParam($params, 'tag_id', 0);
        $implied_tag_id = $this->intParam($params, 'implied_tag_id', 0);

        if ($tag_id <= 0 || $implied_tag_id <= 0) {
            return $this->error($response, 'InvalidTagID', 400, 'Both tag IDs must be positive numbers.');
        }

        if ($tag_id === $implied_tag_id) {
            return $this->error($response, 'CannotImplySelf', 400, 'A tag cannot imply itself.');
        }

        $tag = $this->tag_repository->get($tag_id);
        $impliedTag = $this->tag_repository->get($implied_tag_id);

        if (!($tag instanceof Tag) || !($impliedTag instanceof Tag)) {
            return $this->error($response, 'TagDoesNotExist', 404, 'One or both of the specified tags could not be found.');
        }

        $success = $this->tag_repository->addImplication($tag_id, $implied_tag_id);

        if (!$success) {
            return $this->error($response, 'CycleDetected', 400, 'This implication would create a circular dependency.');
        }

        $this->invalidateCache(CacheGroup::Tags);
        $this->logger->info('Tag implication added', ['tag_id' => $tag_id, 'implied_tag_id' => $implied_tag_id]);
        return $this->created($response, [
            'tag_id' => $tag_id,
            'tag_name' => $tag->tag_name,
            'implied_tag_id' => $implied_tag_id,
            'implied_tag_name' => $impliedTag->tag_name,
        ]);
    }

    /**
     * DELETE /tag-implications/{tag_id}/{implied_tag_id} — Remove an implication.
     */
    #[OA\Delete(
        path: '/tag-implications/{tag_id}/{implied_tag_id}',
        summary: 'Remove a tag implication',
        tags: ['Tag Implications'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'tag_id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'implied_tag_id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Removed'),
            new OA\Response(response: 400, description: 'InvalidTagID', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function removeImplication(Request $request, Response $response, array $args): Response
    {
        $tag_id = $this->intParam($args, 'tag_id', 0);
        $implied_tag_id = $this->intParam($args, 'implied_tag_id', 0);

        if ($tag_id <= 0 || $implied_tag_id <= 0) {
            return $this->error($response, 'InvalidTagID', 400, 'Both tag IDs must be positive numbers.');
        }

        $success = $this->tag_repository->removeImplication($tag_id, $implied_tag_id);

        if (!$success) {
            $this->logger->error('Failed to remove tag implication', ['tag_id' => $tag_id, 'implied_tag_id' => $implied_tag_id]);
            return $this->error($response, 'CouldNotRemoveImplication', 500, 'The implication could not be removed. Please try again.');
        }

        $this->invalidateCache(CacheGroup::Tags);
        $this->logger->info('Tag implication removed', ['tag_id' => $tag_id, 'implied_tag_id' => $implied_tag_id]);
        return $this->noContent($response);
    }
}
