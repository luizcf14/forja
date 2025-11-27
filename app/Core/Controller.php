<?php

class Controller
{
    public function view($view, $data = [])
    {
        extract($data);

        // Check if view exists
        if (file_exists(__DIR__ . "/../Views/$view.php")) {
            require_once __DIR__ . "/../Views/$view.php";
        } else {
            die("View does not exist: " . $view);
        }
    }

    public function redirect($url)
    {
        header("Location: $url");
        exit;
    }
}
