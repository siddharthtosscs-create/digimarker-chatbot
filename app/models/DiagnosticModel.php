<?php
declare(strict_types=1);

/**
 * DiagnosticModel - Business logic for diagnostic information
 */
class DiagnosticModel
{
  private array $localConfig;

  public function __construct()
  {
    $localConfigFile = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.local.php';
    $this->localConfig = [];
    if (is_file($localConfigFile)) {
      try {
        $loaded = require $localConfigFile;
        if (is_array($loaded)) {
          $this->localConfig = $loaded;
        }
      } catch (Throwable $e) {
        $this->localConfig['error'] = $e->getMessage();
      }
    }
  }

  /**
   * Get diagnostic information
   */
  public function getDiagnostics(): array
  {
    $diagnostics = [
      'timestamp' => date('Y-m-d H:i:s'),
      'php_version' => PHP_VERSION,
      'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
      'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'not set',
      'script_dir' => __DIR__,
      'script_file' => __FILE__,
    ];

    // Extensions
    $diagnostics['extensions'] = [
      'curl' => [
        'loaded' => extension_loaded('curl'),
        'version' => extension_loaded('curl') ? curl_version()['version'] : null,
        'ssl_support' => extension_loaded('curl') ? (curl_version()['features'] & CURL_VERSION_SSL) !== 0 : false,
      ],
      'json' => [
        'loaded' => extension_loaded('json'),
      ],
      'mbstring' => [
        'loaded' => extension_loaded('mbstring'),
      ],
      'openssl' => [
        'loaded' => extension_loaded('openssl'),
      ],
    ];

    // API Key Configuration
    $localConfigFile = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.local.php';
    $envApiKey = getenv('GEMINI_API_KEY');
    $diagnostics['api_key'] = [
      'config_file_exists' => is_file($localConfigFile),
      'config_file_path' => $localConfigFile,
      'config_file_readable' => is_file($localConfigFile) ? is_readable($localConfigFile) : false,
      'config_file_permissions' => is_file($localConfigFile) ? substr(sprintf('%o', fileperms($localConfigFile)), -4) : null,
      'env_var_set' => $envApiKey !== false,
      'env_var_length' => $envApiKey !== false ? strlen($envApiKey) : 0,
      'config_key_set' => isset($this->localConfig['GEMINI_API_KEY']),
      'config_key_length' => isset($this->localConfig['GEMINI_API_KEY']) ? strlen($this->localConfig['GEMINI_API_KEY']) : 0,
      'api_key_configured' => ($envApiKey !== false && strlen($envApiKey) > 0) || (isset($this->localConfig['GEMINI_API_KEY']) && strlen($this->localConfig['GEMINI_API_KEY']) > 0 && $this->localConfig['GEMINI_API_KEY'] !== 'PASTE_YOUR_GEMINI_API_KEY_HERE'),
    ];

    // FAQ File Path
    $possiblePaths = [
      dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'chatbot_faq.json',
      __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'chatbot_faq.json',
      (isset($_SERVER['DOCUMENT_ROOT']) ? rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . DIRECTORY_SEPARATOR : '') . 'data' . DIRECTORY_SEPARATOR . 'chatbot_faq.json',
      __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'chatbot_faq.json',
    ];

    $faqPathDetails = [];
    $faqFound = false;
    foreach ($possiblePaths as $path) {
      $details = [
        'path' => $path,
        'exists' => file_exists($path),
        'is_file' => is_file($path),
        'is_readable' => is_readable($path),
        'permissions' => file_exists($path) ? substr(sprintf('%o', fileperms($path)), -4) : null,
        'size' => file_exists($path) ? filesize($path) : null,
      ];
      
      if ($details['exists'] && $details['is_file'] && $details['is_readable']) {
        $faqFound = true;
        $content = @file_get_contents($path);
        if ($content !== false) {
          $json = json_decode($content, true);
          $details['json_valid'] = json_last_error() === JSON_ERROR_NONE;
          $details['json_error'] = json_last_error() !== JSON_ERROR_NONE ? json_last_error_msg() : null;
          if ($details['json_valid']) {
            $details['faq_count'] = isset($json['chatbot_faq']) && is_array($json['chatbot_faq']) ? count($json['chatbot_faq']) : 0;
          }
        }
      }
      
      $faqPathDetails[] = $details;
    }

    $diagnostics['faq_file'] = [
      'found' => $faqFound,
      'path_details' => $faqPathDetails,
    ];

    // File Permissions
    $diagnostics['file_permissions'] = [
      'chat_php' => [
        'path' => dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'chat.php',
        'exists' => file_exists(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'chat.php'),
        'permissions' => file_exists(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'chat.php') ? substr(sprintf('%o', fileperms(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'chat.php')), -4) : null,
        'readable' => is_readable(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'chat.php'),
      ],
      'config_local_php' => [
        'path' => $localConfigFile,
        'exists' => is_file($localConfigFile),
        'permissions' => is_file($localConfigFile) ? substr(sprintf('%o', fileperms($localConfigFile)), -4) : null,
        'readable' => is_readable($localConfigFile),
      ],
      'data_directory' => [
        'path' => dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'data',
        'exists' => is_dir(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'data'),
        'permissions' => is_dir(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'data') ? substr(sprintf('%o', fileperms(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'data')), -4) : null,
        'readable' => is_readable(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'data'),
      ],
    ];

    // HTTPS Test
    $diagnostics['https_test'] = [
      'test_url' => 'https://generativelanguage.googleapis.com',
      'tested' => false,
      'success' => false,
      'error' => null,
    ];

    if (extension_loaded('curl')) {
      $testCh = curl_init('https://generativelanguage.googleapis.com');
      if ($testCh !== false) {
        curl_setopt_array($testCh, [
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_TIMEOUT => 10,
          CURLOPT_CONNECTTIMEOUT => 5,
          CURLOPT_SSL_VERIFYPEER => true,
          CURLOPT_SSL_VERIFYHOST => 2,
          CURLOPT_NOBODY => true,
          CURLOPT_USERAGENT => 'DigiMarker-Diagnostic/1.0',
        ]);
        
        $diagnostics['https_test']['tested'] = true;
        $testResult = curl_exec($testCh);
        $testErrno = curl_errno($testCh);
        $testError = curl_error($testCh);
        $testStatus = curl_getinfo($testCh, CURLINFO_HTTP_CODE);
        curl_close($testCh);
        
        if ($testErrno === 0) {
          $diagnostics['https_test']['success'] = true;
          $diagnostics['https_test']['http_status'] = $testStatus;
        } else {
          $diagnostics['https_test']['success'] = false;
          $diagnostics['https_test']['error'] = $testError;
          $diagnostics['https_test']['curl_errno'] = $testErrno;
        }
      }
    }

    // Environment Variables
    $diagnostics['environment'] = [
      'digimarker_debug' => getenv('DIGIMARKER_DEBUG') !== false ? getenv('DIGIMARKER_DEBUG') : 'not set',
      'digimarker_chat_allowed_origins' => getenv('DIGIMARKER_CHAT_ALLOWED_ORIGINS') !== false ? getenv('DIGIMARKER_CHAT_ALLOWED_ORIGINS') : 'not set',
      'digimarker_chat_api_key' => getenv('DIGIMARKER_CHAT_API_KEY') !== false ? '***set***' : 'not set',
      'gemini_api_key' => getenv('GEMINI_API_KEY') !== false ? '***set***' : 'not set',
    ];

    // Summary
    $diagnostics['summary'] = [
      'all_checks_passed' => (
        $diagnostics['extensions']['curl']['loaded'] &&
        $diagnostics['extensions']['json']['loaded'] &&
        $diagnostics['extensions']['mbstring']['loaded'] &&
        $diagnostics['api_key']['api_key_configured'] &&
        $diagnostics['faq_file']['found'] &&
        $diagnostics['https_test']['success']
      ),
      'critical_issues' => [],
    ];

    if (!$diagnostics['extensions']['curl']['loaded']) {
      $diagnostics['summary']['critical_issues'][] = 'cURL extension is not loaded';
    }
    if (!$diagnostics['extensions']['json']['loaded']) {
      $diagnostics['summary']['critical_issues'][] = 'JSON extension is not loaded';
    }
    if (!$diagnostics['extensions']['mbstring']['loaded']) {
      $diagnostics['summary']['critical_issues'][] = 'mbstring extension is not loaded';
    }
    if (!$diagnostics['api_key']['api_key_configured']) {
      $diagnostics['summary']['critical_issues'][] = 'Gemini API key is not configured';
    }
    if (!$diagnostics['faq_file']['found']) {
      $diagnostics['summary']['critical_issues'][] = 'FAQ file (chatbot_faq.json) not found';
    }
    if (!$diagnostics['https_test']['success']) {
      $diagnostics['summary']['critical_issues'][] = 'HTTPS outbound connection test failed';
    }

    return $diagnostics;
  }
}

