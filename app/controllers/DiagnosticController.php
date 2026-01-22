<?php
declare(strict_types=1);

/**
 * DiagnosticController - Handles diagnostic requests
 */
class DiagnosticController
{
  private DiagnosticModel $model;

  public function __construct()
  {
    require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'DiagnosticModel.php';
    $this->model = new DiagnosticModel();
  }

  /**
   * Handle GET or POST request
   */
  public function handleRequest(): void
  {
    // Enable error reporting for diagnostics
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);

    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
      http_response_code(204);
      exit;
    }

    // Get diagnostics
    $diagnostics = $this->model->getDiagnostics();
    
    echo json_encode($diagnostics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
  }
}

