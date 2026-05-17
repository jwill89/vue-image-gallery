<?php

namespace Routes\Internal;

use PDO;
use Gallery\Core\DatabaseConnection;
use Psr\Container\ContainerInterface;

/**
 * AbstractController class
 */
abstract class AbstractController
{
    protected ContainerInterface $container;

    /**
     * AbstractController constructor.
     *
     * @param ContainerInterface $container The container instance.
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
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
}
