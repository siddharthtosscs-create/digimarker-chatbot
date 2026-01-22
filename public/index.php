<?php
declare(strict_types=1);

/**
 * Public entry point - routes to HomeController
 */
require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'controllers' . DIRECTORY_SEPARATOR . 'HomeController.php';

$controller = new HomeController();
$controller->index();

