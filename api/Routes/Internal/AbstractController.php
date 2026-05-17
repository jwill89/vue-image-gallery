<?php

namespace Routes\Internal;

use PDO;
use Monolog\Logger;
use Slim\Http\Response;
use Gallery\Core\DatabaseConnection;
use Gallery\Core\Logger as AppLogger;
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
     * Return an error JSON response.
     *
     * @param Response $response The response object.
     * @param string $error The error code/message.
     * @param int $status HTTP status code.
     *
     * @return Response
     */
    protected function error(Response $response, string $error, int $status): Response
    {
        return $response->withJson(['error' => $error], $status);
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
     * Resolves an array of tag names to an array of tag IDs.
     * Invalid/non-existent tags are silently skipped.
     *
     * @param array $tag_names Array of tag name strings.
     * @param TagCollection $tag_collection The tag collection to look up tags.
     *
     * @return int[] Array of valid tag IDs.
     */
    protected function resolveTagIds(array $tag_names, TagCollection $tag_collection): array
    {
        $tag_ids = [];
        foreach ($tag_names as $tag_name) {
            $tag = $tag_collection->getByName($tag_name);
            if ($tag !== null) {
                $tag_ids[] = $tag->getTagId();
            }
        }
        return $tag_ids;
    }
}
