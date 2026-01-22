<?php

// Handle PHP built-in server static files
if (php_sapi_name() == 'cli-server') {
    $url = parse_url($_SERVER['REQUEST_URI']);
    $file = __DIR__ . $url['path'];
    if (is_file($file)) {
        return false;
    }
}



// Increase upload limits at runtime
ini_set('upload_max_filesize', '100M');
ini_set('post_max_size', '100M');
ini_set('memory_limit', '512M');

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
$router->post('/agents/delete', 'AgentController@delete');
$router->post('/agents/delete-file', 'AgentController@deleteFile');
$router->get('/agents/download', 'AgentController@download');

// Test & Chat
$router->get('/agents/test', 'TestController@index');
$router->post('/api/chat', 'TestController@chat');
$router->post('/api/optimize', 'AgentController@optimize');

// Settings & Users
$router->get('/settings', 'SettingsController@index');
$router->post('/settings', 'SettingsController@index');

// Conversations
$router->get('/conversations', 'ConversationController@index');
$router->get('/conversations/show', 'ConversationController@show');
$router->get('/api/conversations/messages', 'ConversationController@apiMessages');
$router->post('/conversations/toggle-ai', 'ConversationController@toggleAi');
$router->post('/conversations/send-message', 'ConversationController@sendMessage');
$router->post('/conversations/send-audio', 'ConversationController@sendAudio');

// Dispatch
$uri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

// Handle query strings in URI for routing
$uri = parse_url($uri, PHP_URL_PATH);

$router->dispatch($uri, $method);