<?php
// Split API entry point. Routes live in app/routes/api.php.
require dirname(__DIR__, 3) . '/app/bootstrap.php';

$router = new Split\Api\Router();
require dirname(__DIR__, 3) . '/app/routes/api.php';

$path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', preg_replace('#^/api/v1#', '', $path) ?: '/');
