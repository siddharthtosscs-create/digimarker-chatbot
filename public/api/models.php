<?php
declare(strict_types=1);

/**
 * Models API - Lists available Gemini models
 */

require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'controllers' . DIRECTORY_SEPARATOR . 'ModelsController.php';

$controller = new ModelsController();
$controller->handleRequest();
