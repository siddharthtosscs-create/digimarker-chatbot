<?php
declare(strict_types=1);

/**
 * DigiMarker Chat API Diagnostic Tool
 * 
 * This endpoint helps diagnose 500 errors on hosted servers.
 * Access: GET or POST to /api/diagnostic.php
 * 
 * SECURITY: Remove or protect this file in production!
 */

require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'controllers' . DIRECTORY_SEPARATOR . 'DiagnosticController.php';

$controller = new DiagnosticController();
$controller->handleRequest();
