<?php
declare(strict_types=1);

/**
 * ModelsController - Handles models API requests
 */
class ModelsController
{
  private ModelsModel $model;

  public function __construct()
  {
    require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'ModelsModel.php';
    $this->model = new ModelsModel();
  }

  /**
   * Handle GET request
   */
  public function handleRequest(): void
  {
    // CORS / preflight
    $allowedOriginsEnv = getenv('DIGIMARKER_CHAT_ALLOWED_ORIGINS') ?: '*';
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    if ($allowedOriginsEnv === '*') {
      header('Access-Control-Allow-Origin: *');
    } else {
      $allowed = array_values(array_filter(array_map('trim', explode(',', $allowedOriginsEnv))));
      if ($origin !== '' && in_array($origin, $allowed, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
      }
    }

    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-API-Key');
    header('Content-Type: application/json; charset=utf-8');

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
      http_response_code(204);
      exit;
    }

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
      http_response_code(405);
      echo json_encode(['error' => 'Method not allowed. Use GET.']);
      exit;
    }

    // Optional API key protection
    $requiredApiKey = (string)(getenv('DIGIMARKER_CHAT_API_KEY') ?: '');
    if ($requiredApiKey !== '') {
      $providedApiKey = (string)($_SERVER['HTTP_X_API_KEY'] ?? '');
      if (!hash_equals($requiredApiKey, $providedApiKey)) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
      }
    }

    // Fetch models
    $result = $this->model->fetchModels();
    
    if (!empty($result['ok'])) {
      if (isset($result['raw'])) {
        echo json_encode(['raw' => $result['raw']]);
      } else {
        echo json_encode([
          'models' => $result['models'],
          'count' => $result['count'],
        ]);
      }
      exit;
    }

    // Handle errors
    http_response_code(500);
    echo json_encode([
      'error' => $result['error'] ?? 'Unknown error',
      'details' => $result['details'] ?? null,
      'status' => $result['status'] ?? null,
      'body' => $result['body'] ?? null,
    ]);
    exit;
  }
}

