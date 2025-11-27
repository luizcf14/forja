<?php

class Router
{
    protected $routes = [];

    public function get($uri, $controller)
    {
        $this->routes['GET'][$uri] = $controller;
    }

    public function post($uri, $controller)
    {
        $this->routes['POST'][$uri] = $controller;
    }

    public function dispatch($uri, $method)
    {
        // Strip query string
        $uri = parse_url($uri, PHP_URL_PATH);

        // Remove trailing slash if not root
        if ($uri !== '/' && substr($uri, -1) === '/') {
            $uri = rtrim($uri, '/');
        }

        if (array_key_exists($uri, $this->routes[$method])) {
            $controllerAction = $this->routes[$method][$uri];
            $parts = explode('@', $controllerAction);
            $controllerName = $parts[0];
            $action = $parts[1];

            require_once __DIR__ . "/../Controllers/$controllerName.php";
            $controller = new $controllerName();
            $controller->$action();
        } else {
            // Handle 404
            http_response_code(404);
            echo "404 Not Found";
        }
    }
}
