<?php

namespace Routes\Internal;

use PDO;
use Monolog\Logger;
use Slim\Http\Response;
use Gallery\Core\DatabaseConnection;
use Gallery\Core\Logger as AppLogger;
use Gallery\Core\ResponseCache;
use Gallery\Collection\TagCollection;
use Psr\Container\ContainerInterface;

/**
 * AbstractController class
 */
abstract class AbstractController
{
    protected ContainerInterface $container;
    protected Logger $logger;

    /**
     * AbstractController constructor.
     *
     * @param ContainerInterface $container The container instance.
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->logger = AppLogger::getInstance();
    }

    /**
     * Get a PDO connection instance.
     *
     * @return PDO A PDO connection instance.
     */
    protected function getConnection(): PDO
    {
        return DatabaseConnection::getInstance();
    }

    /**
     * Return a successful JSON response.
     *
     * @param Response $response The response object.
     * @param mixed $data The data to return.
     * @param int $status HTTP status code.
     *
     * @return Response
     */
    protected function success(Response $response, mixed $data, int $status = 200): Response
    {
        return $response->withJson($data, $status);
    }

    /**
     * Return an error JSON response with a machine-readable code and human-readable message.
     *
     * @param Response $response The response object.
     * @param string $error The machine-readable error code (e.g. 'MediaNotFound').
     * @param int $status HTTP status code.
     * @param string|null $message Optional human-readable message. If omitted, auto-generated from $error.
     *
     * @return Response
     */
    protected function error(Response $response, string $error, int $status, ?string $message = null): Response
    {
        if ($message === null) {
            // Convert PascalCase error code to a readable sentence
            $message = trim(preg_replace('/(?<!^)([A-Z])/', ' $1', $error));
        }

        return $response->withJson([
            'error' => $error,
            'message' => $message,
        ], $status);
    }

    /**
     * Parse parameters from the request and return the value of the specified parameter.
     *
     * @param array $parameters The parameters array.
     * @param string $parameter_name The name of the parameter to retrieve.
     * @param mixed $default_value The default value to return if the parameter is not found.
     *
     * @return mixed The value of the specified parameter or the default value.
     */
    protected function parseParameters(array $parameters, string $parameter_name, mixed $default_value): mixed
    {
        return array_key_exists($parameter_name, $parameters) ? $parameters[$parameter_name] : $default_value;
    }

    /**
     * Resolves an array of tag names to an array of tag IDs in a single query.
     * Invalid/non-existent tags are silently skipped; IDs are de-duplicated.
     *
     * @param array $tag_names Array of tag name strings.
     * @param TagCollection $tag_collection The tag collection to look up tags.
     *
     * @return int[] Array of valid tag IDs.
     */
    protected function resolveTagIds(array $tag_names, TagCollection $tag_collection): array
    {
        return $tag_collection->getIdsByNames($tag_names);
    }

    /**
     * Return a cached JSON response, or generate, cache, and return it on miss.
     *
     * The generator callable must return the data to be JSON-encoded.
     * On a cache hit the stored JSON is written directly to the response body,
     * skipping both the DB query and the json_encode step.
     *
     * @param Response $response The Slim response object.
     * @param string   $group    Cache group for targeted invalidation.
     * @param string   $key      Unique cache key within the group.
     * @param int      $ttl      Time-to-live in seconds.
     * @param callable $generator Callable that returns the response data.
     *
     * @return Response
     */
    protected function cachedSuccess(Response $response, string $group, string $key, int $ttl, callable $generator): Response
    {
        $cache = ResponseCache::getInstance();
        $json = $cache->get($group, $key);

        if ($json !== null) {
            $response->getBody()->write($json);
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('X-Cache', 'HIT');
        }

        $data = $generator();
        $json = json_encode($data, JSON_THROW_ON_ERROR);
        $cache->set($group, $key, $json, $ttl);

        $response->getBody()->write($json);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('X-Cache', 'MISS');
    }

    /**
     * Invalidate one or more cache groups after a mutation.
     *
     * @param string ...$groups Cache group names to invalidate.
     */
    protected function invalidateCache(string ...$groups): void
    {
        ResponseCache::getInstance()->invalidateGroups(...$groups);
    }
}
