<?php

namespace Routes\Internal;

use Slim\Http\ServerRequest as Request;
use Slim\Http\Response;
use Gallery\Core\Configuration;
use OpenApi\Attributes as OA;

/**
 * SystemController
 *
 * Meta endpoints: the running version, the raw OpenAPI document, and the
 * Scalar-powered interactive API reference. All are public.
 */
class SystemController extends AbstractController
{
    /**
     * GET /version — The running application and API contract versions.
     */
    #[OA\Get(
        path: '/version',
        summary: 'Application and API version',
        tags: ['System'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Version info',
                content: new OA\JsonContent(ref: '#/components/schemas/VersionResponse')
            ),
        ]
    )]
    public function getVersion(Request $request, Response $response): Response
    {
        return $this->success($response, [
            'version' => Configuration::VERSION,
            'api_version' => Configuration::API_VERSION,
        ]);
    }

    /**
     * GET /openapi.json — The generated OpenAPI 3.1 document.
     *
     * Serves the spec committed to the repo (regenerated with `composer docs`),
     * so production does not depend on swagger-php being installed.
     */
    #[OA\Get(
        path: '/openapi.json',
        summary: 'The OpenAPI 3.1 specification',
        tags: ['System'],
        responses: [
            new OA\Response(response: 200, description: 'The OpenAPI document (JSON)'),
            new OA\Response(response: 404, description: 'Spec not generated'),
        ]
    )]
    public function getOpenApiSpec(Request $request, Response $response): Response
    {
        $specPath = __DIR__ . '/../../../openapi.json';

        if (!is_file($specPath)) {
            return $this->error($response, 'SpecNotFound', 404, 'The OpenAPI spec has not been generated. Run `composer docs`.');
        }

        $response->getBody()->write((string) file_get_contents($specPath));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Cache-Control', 'public, max-age=300');
    }

    /**
     * GET /docs — The interactive API reference (Scalar), pointed at the spec.
     */
    #[OA\Get(
        path: '/docs',
        summary: 'Interactive API reference (Scalar)',
        tags: ['System'],
        responses: [
            new OA\Response(response: 200, description: 'HTML documentation page'),
        ]
    )]
    public function getDocs(Request $request, Response $response): Response
    {
        $html = <<<'HTML'
            <!doctype html>
            <html>
              <head>
                <meta charset="utf-8" />
                <meta name="viewport" content="width=device-width, initial-scale=1" />
                <title>Gallery — API Reference</title>
              </head>
              <body>
                <script id="api-reference" data-url="/api/openapi.json"></script>
                <script src="https://cdn.jsdelivr.net/npm/@scalar/api-reference"></script>
              </body>
            </html>
            HTML;

        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
