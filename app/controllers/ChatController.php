<?php
declare(strict_types=1);

/**
 * ChatController - Handles chat API requests
 */
class ChatController
{
  private ChatModel $model;
  private ?SessionModel $sessionModel = null;

  public function __construct()
  {
    require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'ChatModel.php';
    $this->model = new ChatModel();
    
    // Initialize SessionModel (gracefully handle if DB is not configured)
    try {
      require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'SessionModel.php';
      $this->sessionModel = new SessionModel();
    } catch (Throwable $e) {
      // Database not configured or unavailable - continue without session logging
      // This ensures chat functionality works even if DB is not set up
      if ($this->model->getDebugMode()) {
        error_log("SessionModel initialization failed (non-critical): " . $e->getMessage());
      }
    }
  }

  /**
   * Handle POST request
   */
  public function handleRequest(): void
  {
    // Enable error display for debugging
    $debugMode = $this->model->getDebugMode();
    if ($debugMode) {
      ini_set('display_errors', '1');
      ini_set('display_startup_errors', '1');
      error_reporting(E_ALL);
      ini_set('log_errors', '1');
    } else {
      ini_set('display_errors', '0');
      ini_set('log_errors', '1');
      error_reporting(E_ALL);
    }

    // Catch fatal errors
    register_shutdown_function(function() {
      $error = error_get_last();
      if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
          'error' => 'PHP Fatal Error',
          'message' => $error['message'],
          'file' => $error['file'],
          'line' => $error['line'],
          'diagnostic' => [
            'php_version' => PHP_VERSION,
            'script_dir' => __DIR__,
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'not set',
          ],
        ]);
      }
    });

    // Check PHP Extensions
    if (!function_exists('curl_init')) {
      http_response_code(500);
      header('Content-Type: application/json; charset=utf-8');
      echo json_encode([
        'error' => 'PHP cURL extension is not enabled.',
        'details' => 'Contact your hosting provider to enable the cURL extension in PHP. In cPanel: PHP Selector → Extensions → Enable cURL.',
        'diagnostic' => [
          'php_version' => PHP_VERSION,
          'curl_available' => false,
          'loaded_extensions' => get_loaded_extensions(),
          'how_to_fix' => [
            'cPanel' => 'Go to: cPanel → Select PHP Version → Extensions → Check "curl" → Save',
            'VPS/Server' => 'Install: sudo apt-get install php-curl (Ubuntu/Debian) or sudo yum install php-curl (CentOS/RHEL)',
            'php.ini' => 'Add: extension=curl to your php.ini file',
          ],
        ],
      ]);
      exit;
    }

    $requiredExtensions = ['json', 'mbstring'];
    $missingExtensions = [];
    foreach ($requiredExtensions as $ext) {
      if (!extension_loaded($ext)) {
        $missingExtensions[] = $ext;
      }
    }
    if (!empty($missingExtensions)) {
      http_response_code(500);
      header('Content-Type: application/json; charset=utf-8');
      echo json_encode([
        'error' => 'Required PHP extensions are missing.',
        'details' => 'The following extensions are required but not loaded: ' . implode(', ', $missingExtensions),
        'diagnostic' => [
          'missing_extensions' => $missingExtensions,
          'loaded_extensions' => get_loaded_extensions(),
        ],
      ]);
      exit;
    }

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

    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-API-Key');
    header('Content-Type: application/json; charset=utf-8');
    header('X-DigiMarker-Chat: 1');

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
      http_response_code(204);
      exit;
    }

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
      http_response_code(405);
      echo json_encode(['error' => 'Method not allowed. Use POST.']);
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

    // Read request JSON
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);
    if (!is_array($data)) {
      http_response_code(400);
      echo json_encode(['error' => 'Invalid JSON body.']);
      exit;
    }

    $question = isset($data['question']) ? trim((string)$data['question']) : '';
    $mode = isset($data['mode']) ? strtolower(trim((string)$data['mode'])) : 'faq';
    $history = $data['history'] ?? null;
    $sessionId = isset($data['session_id']) ? trim((string)$data['session_id']) : '';

    if ($question === '') {
      http_response_code(400);
      echo json_encode(['error' => 'Missing question.']);
      exit;
    }

    // Session management and message logging (non-blocking)
    // If session_id is provided and SessionModel is available, ensure session exists and log user message
    $sessionStatus = null;
    $isAgentMode = false;
    if ($sessionId !== '' && $this->sessionModel !== null) {
      try {
        // Ensure session exists in database
        $this->sessionModel->ensureSession($sessionId);
        
        // Log user message
        // Source is 'faq' since user questions are FAQ-related in this context
        $this->sessionModel->logMessage($sessionId, 'user', $question, 'faq');
        
        // Check session status - if already in agent mode, skip Gemini
        $sessionStatus = $this->sessionModel->getSessionStatus($sessionId);
        if ($sessionStatus !== null && is_array($sessionStatus) && isset($sessionStatus['status'])) {
          $isAgentMode = in_array($sessionStatus['status'], ['agent_requested', 'agent_connected'], true);
        }
      } catch (Throwable $e) {
        // Log error but don't break chat functionality - continue with normal flow
        if ($this->model->getDebugMode()) {
          error_log("Session/message logging error (non-critical): " . $e->getMessage());
        }
      }
    }

    // If session is already in agent mode, don't call Gemini
    if ($isAgentMode) {
      echo json_encode([
        'answer' => '',
        'agent_requested' => true,
      ]);
      exit;
    }

    if (!in_array($mode, ['faq', 'general'], true)) {
      http_response_code(400);
      echo json_encode(['error' => 'Invalid mode. Use "faq" or "general".']);
      exit;
    }

    // Keep prompts bounded
    if (mb_strlen($question) > 2000) $question = mb_substr($question, 0, 2000);

    // Check API key
    $apiKey = $this->model->getApiKey();
    if ($apiKey === '' || $apiKey === 'PASTE_YOUR_GEMINI_API_KEY_HERE') {
      http_response_code(500);
      header('Content-Type: application/json; charset=utf-8');
      
      $localConfig = $this->model->getLocalConfig();
      $configKeyExists = isset($localConfig['GEMINI_API_KEY']);
      $configKeyValue = $configKeyExists ? (strlen($localConfig['GEMINI_API_KEY']) > 0 ? '***set***' : 'empty') : 'not set';
      
      $localConfigFile = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.local.php';
      echo json_encode([
        'error' => 'Gemini API key not configured.',
        'details' => 'Set GEMINI_API_KEY environment variable OR configure in config/config.local.php',
        'diagnostic' => [
          'config_file_exists' => is_file($localConfigFile),
          'config_file_path' => $localConfigFile,
          'config_file_readable' => is_file($localConfigFile) ? is_readable($localConfigFile) : false,
          'env_var_set' => getenv('GEMINI_API_KEY') !== false,
          'env_var_value' => getenv('GEMINI_API_KEY') !== false ? (strlen(getenv('GEMINI_API_KEY')) > 0 ? '***set***' : 'empty') : 'not set',
          'config_key_set' => $configKeyExists,
          'config_key_value' => $configKeyValue,
          'how_to_fix' => [
            'option_1' => 'Set environment variable GEMINI_API_KEY in your hosting control panel (cPanel → Environment Variables)',
            'option_2' => 'Create/update config/config.local.php with: return [\'GEMINI_API_KEY\' => \'your_key_here\'];',
            'note' => 'Get your API key from: https://aistudio.google.com/app/apikey',
          ],
        ],
      ]);
      exit;
    }

    // Check FAQ file if in FAQ mode
    if ($mode === 'faq') {
      $context = $this->model->buildFAQContext();
      if ($context === '') {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        
        $possiblePaths = [
          dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'chatbot_faq.json',
          __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'chatbot_faq.json',
          (isset($_SERVER['DOCUMENT_ROOT']) ? rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . DIRECTORY_SEPARATOR : '') . 'data' . DIRECTORY_SEPARATOR . 'chatbot_faq.json',
          __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'chatbot_faq.json',
        ];
        
        $pathDetails = [];
        foreach ($possiblePaths as $path) {
          $pathDetails[] = [
            'path' => $path,
            'exists' => file_exists($path),
            'is_file' => is_file($path),
            'is_readable' => is_readable($path),
            'permissions' => file_exists($path) ? substr(sprintf('%o', fileperms($path)), -4) : 'N/A',
          ];
        }
        
        echo json_encode([
          'error' => 'FAQ context file missing or not readable.',
          'details' => 'The chatbot_faq.json file could not be found in any expected location.',
          'diagnostic' => [
            'script_dir' => __DIR__,
            'script_file' => __FILE__,
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'not set',
            'path_details' => $pathDetails,
            'resolved_path' => null,
            'how_to_fix' => [
              'step_1' => 'Ensure data/chatbot_faq.json exists in your project root',
              'step_2' => 'Check file permissions: chmod 644 data/chatbot_faq.json',
              'step_3' => 'Verify directory structure matches your hosting setup',
              'note' => 'If using public_html, ensure data/ folder is at the same level as api/',
            ],
          ],
        ]);
        exit;
      }
    }

    // Check if this is an agent request BEFORE calling Gemini
    $result = $this->model->generateAnswer($question, $mode, $history);
    
    // If agent request detected, handle it specially
    if (!empty($result['ok']) && isset($result['model']) && $result['model'] === 'agent-redirect') {
      // This is an agent request - update session status and return special response
      if ($sessionId !== '' && $this->sessionModel !== null) {
        try {
          // Update session to agent_requested status
          $this->sessionModel->requestAgent($sessionId);
          
          // Insert system message about connecting to agent
          $systemMessage = "🧑‍💼 Connecting you to an agent…\nPlease wait while we assign a support representative.";
          $this->sessionModel->logMessage($sessionId, 'system', $systemMessage, 'system');
        } catch (Throwable $e) {
          if ($this->model->getDebugMode()) {
            error_log("Agent request handling error (non-critical): " . $e->getMessage());
          }
        }
      }
      
      echo json_encode([
        'answer' => $result['answer'],
        'agent_requested' => true,
      ]);
      exit;
    }
    
    if (!empty($result['ok'])) {
      $answer = $result['answer'];
      
      // Log bot response (non-blocking)
      if ($sessionId !== '' && $this->sessionModel !== null) {
        try {
          // Determine source: 'gemini' for AI responses, 'faq' for FAQ-based answers
          $source = ($mode === 'faq') ? 'faq' : 'gemini';
          $this->sessionModel->logMessage($sessionId, 'bot', $answer, $source);
        } catch (Throwable $e) {
          // Log error but don't break chat functionality
          if ($this->model->getDebugMode()) {
            error_log("Bot message logging error (non-critical): " . $e->getMessage());
          }
        }
      }
      
      echo json_encode([
        'answer' => $answer,
      ]);
      exit;
    }

    // Handle errors - log system error messages
    $errorMessage = '';
    $httpCode = 502;
    
    if (($result['type'] ?? '') === 'transport') {
      $errorMessage = 'Gemini request failed: ' . ($result['details'] ?? 'Unknown error');
      $response = ['error' => 'Gemini request failed', 'details' => $result['details'] ?? 'Unknown error'];
    } elseif (($result['type'] ?? '') === 'empty') {
      $errorMessage = 'Empty response from Gemini (model: ' . ($result['model'] ?? 'unknown') . ')';
      $response = ['error' => 'Empty response from Gemini', 'model' => $result['model'] ?? 'unknown'];
    } elseif (($result['type'] ?? '') === 'http') {
      $errorMessage = 'Gemini HTTP error: Status ' . ($result['status'] ?? 0);
      $response = [
        'error' => 'Gemini returned an error',
        'status' => (int)($result['status'] ?? 0),
        'body' => $result['body'] ?? '',
        'model' => $result['model'] ?? 'unknown',
      ];
    } else {
      $errorMessage = 'No supported Gemini model found for this API key.';
      $response = [
        'error' => 'No supported Gemini model found for this API key.',
        'last_error' => $result['last_error'] ?? null,
      ];
    }
    
    // Log system error message (non-blocking)
    if ($sessionId !== '' && $this->sessionModel !== null && $errorMessage !== '') {
      try {
        $this->sessionModel->logMessage($sessionId, 'system', $errorMessage, 'system');
      } catch (Throwable $e) {
        // Log error but don't break error response
        if ($this->model->getDebugMode()) {
          error_log("System error message logging error (non-critical): " . $e->getMessage());
        }
      }
    }
    
    http_response_code($httpCode);
    echo json_encode($response);
    exit;
  }
}

