<?php

// Handle PHP built-in server static files
if (php_sapi_name() == 'cli-server') {
    $url = parse_url($_SERVER['REQUEST_URI']);
    $file = __DIR__ . $url['path'];
    if (is_file($file)) {
        return false;
    }
}

require_once __DIR__ . '/../app/Core/Router.php';

$router = new Router();

// Home
$router->get('/', 'HomeController@index');
$router->post('/', 'HomeController@index');

// Agents
$router->get('/agents/create', 'AgentController@create');
$router->post('/agents/store', 'AgentController@store');
$router->get('/agents/edit', 'AgentController@edit');
$router->post('/agents/update', 'AgentController@update');
$router->get('/agents/download', 'AgentController@download');

// Test & Chat
$router->get('/agents/test', 'TestController@index');
$router->post('/api/chat', 'TestController@chat');
$router->post('/api/optimize', 'AgentController@optimize');

// Users
$router->get('/users', 'UserController@index');
$router->post('/users', 'UserController@index');

// Dispatch
$uri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

// Handle query strings in URI for routing
$uri = parse_url($uri, PHP_URL_PATH);

$router->dispatch($uri, $method);