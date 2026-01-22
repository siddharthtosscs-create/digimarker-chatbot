<?php
declare(strict_types=1);

/**
 * ChatModel - Business logic for chatbot functionality
 * Handles Gemini API calls, FAQ processing, and context building
 */
class ChatModel
{
  private string $apiKey;
  private string $apiBase;
  private array $localConfig;
  private bool $debugMode;

  public function __construct()
  {
    $this->debugMode = (bool)(getenv('DIGIMARKER_DEBUG') !== false ? getenv('DIGIMARKER_DEBUG') : true);
    $this->apiBase = "https://generativelanguage.googleapis.com/v1beta/models";
    
    // Load config
    $localConfigFile = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.local.php';
    $this->localConfig = [];
    if (is_file($localConfigFile)) {
      try {
        $loaded = require $localConfigFile;
        if (is_array($loaded)) {
          $this->localConfig = $loaded;
        }
      } catch (Throwable $e) {
        if ($this->debugMode) {
          error_log("Config file error: " . $e->getMessage());
        }
      }
    }

    // Get API key
    $envApiKey = getenv('GEMINI_API_KEY');
    $this->apiKey = (string)($envApiKey !== false ? $envApiKey : ($this->localConfig['GEMINI_API_KEY'] ?? ''));
  }

  public function getApiKey(): string
  {
    return $this->apiKey;
  }

  public function getLocalConfig(): array
  {
    return $this->localConfig;
  }

  public function getDebugMode(): bool
  {
    return $this->debugMode;
  }

  /**
   * Build FAQ context from chatbot_faq.json
   */
  public function buildFAQContext(): string
  {
    $possiblePaths = [
      dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'chatbot_faq.json',
      __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'chatbot_faq.json',
      (isset($_SERVER['DOCUMENT_ROOT']) ? rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . DIRECTORY_SEPARATOR : '') . 'data' . DIRECTORY_SEPARATOR . 'chatbot_faq.json',
      __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'chatbot_faq.json',
    ];

    $faqPath = null;
    foreach ($possiblePaths as $path) {
      if (is_file($path) && is_readable($path)) {
        $faqPath = $path;
        break;
      }
    }

    if ($faqPath === null) {
      return '';
    }

    $faqRaw = @file_get_contents($faqPath);
    if ($faqRaw === false) {
      return '';
    }

    $faqJson = json_decode($faqRaw ?: '', true);
    $jsonError = json_last_error();
    if ($jsonError !== JSON_ERROR_NONE || !is_array($faqJson) || !isset($faqJson['chatbot_faq']) || !is_array($faqJson['chatbot_faq'])) {
      return '';
    }

    $lines = [];
    foreach ($faqJson['chatbot_faq'] as $category) {
      if (!is_array($category)) continue;
      $catQ = isset($category['question']) ? trim((string)$category['question']) : '';
      $catA = isset($category['answer']) ? trim((string)$category['answer']) : '';
      if ($catQ !== '' && $catA !== '') {
        $lines[] = "Q: {$catQ}\nA: {$catA}";
      }
      if (isset($category['sub_faqs']) && is_array($category['sub_faqs'])) {
        foreach ($category['sub_faqs'] as $sub) {
          if (!is_array($sub)) continue;
          $q = isset($sub['question']) ? trim((string)$sub['question']) : '';
          $a = isset($sub['answer']) ? trim((string)$sub['answer']) : '';
          if ($q !== '' && $a !== '') {
            $lines[] = "Q: {$q}\nA: {$a}";
          }
        }
      }
    }

    $context = implode("\n\n", $lines);
    if (mb_strlen($context) > 20000) $context = mb_substr($context, 0, 20000);
    
    return $context;
  }

  /**
   * Get model candidates from config or defaults
   */
  public function getModelCandidates(): array
  {
    $modelFromEnv = (string)(getenv('DIGIMARKER_GEMINI_MODEL') ?: ($this->localConfig['DIGIMARKER_GEMINI_MODEL'] ?? ''));
    if ($modelFromEnv !== '') {
      return [$modelFromEnv];
    }
    
    return [
      'gemini-2.0-flash',
      'gemini-2.0-flash-lite',
      'gemini-1.5-flash-latest',
      'gemini-1.5-flash-001',
      'gemini-1.5-pro-latest',
      'gemini-1.5-pro-001',
    ];
  }

  /**
   * Call Gemini API
   */
  public function callGemini(string $model, array $payload): array
  {
    $endpoint = "{$this->apiBase}/{$model}:generateContent?key=" . rawurlencode($this->apiKey);
    $ch = curl_init($endpoint);
    
    if ($ch === false) {
      return ['ok' => false, 'type' => 'transport', 'status' => 0, 'details' => 'Failed to initialize cURL', 'body' => '', 'model' => $model];
    }

    $sslVerify = true;
    $caBundlePath = null;
    
    $caBundlePaths = [
      '/etc/ssl/certs/ca-certificates.crt',
      '/etc/pki/tls/certs/ca-bundle.crt',
      '/usr/local/share/certs/ca-root-nss.crt',
      '/etc/ssl/cert.pem',
    ];
    
    foreach ($caBundlePaths as $path) {
      if (file_exists($path) && is_readable($path)) {
        $caBundlePath = $path;
        break;
      }
    }
    
    $curlOptions = [
      CURLOPT_POST => true,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
      CURLOPT_POSTFIELDS => json_encode($payload),
      CURLOPT_TIMEOUT => 30,
      CURLOPT_CONNECTTIMEOUT => 15,
      CURLOPT_SSL_VERIFYPEER => $sslVerify,
      CURLOPT_SSL_VERIFYHOST => $sslVerify ? 2 : 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_MAXREDIRS => 3,
      CURLOPT_USERAGENT => 'DigiMarker-ChatBot/1.0',
    ];
    
    if ($caBundlePath !== null) {
      $curlOptions[CURLOPT_CAINFO] = $caBundlePath;
    }
    
    curl_setopt_array($ch, $curlOptions);

    $resp = curl_exec($ch);
    $errno = curl_errno($ch);
    $err = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlInfo = curl_getinfo($ch);
    curl_close($ch);

    if ($errno) {
      $errorDetails = $err;
      $suggestions = [];
      
      if ($errno === CURLE_SSL_CONNECT_ERROR || $errno === CURLE_SSL_CERTPROBLEM) {
        $errorDetails .= ' (SSL/TLS issue - check if hosting allows outbound HTTPS)';
        $suggestions = [
          'Check if your hosting provider allows outbound HTTPS connections',
          'Some shared hosts block external API calls - contact support',
          'Consider using a VPS or dedicated server for API access',
          'Alternative: Use a proxy server if allowed by hosting',
        ];
      } elseif ($errno === CURLE_COULDNT_CONNECT) {
        $errorDetails .= ' (Connection failed - check firewall/outbound access)';
        $suggestions = [
          'Check firewall settings in cPanel/WHM',
          'Verify outbound connections are allowed on port 443',
          'Contact hosting support to whitelist generativelanguage.googleapis.com',
        ];
      } elseif ($errno === CURLE_OPERATION_TIMEOUTED) {
        $errorDetails .= ' (Timeout - hosting may be blocking or slow)';
        $suggestions = [
          'Increase timeout values (already set to 30s)',
          'Check network speed and stability',
          'Some shared hosts throttle external requests',
        ];
      } elseif ($errno === CURLE_COULDNT_RESOLVE_HOST) {
        $errorDetails .= ' (DNS resolution failed)';
        $suggestions = [
          'Check DNS settings on your server',
          'Verify internet connectivity from server',
        ];
      }
      
      return [
        'ok' => false, 
        'type' => 'transport', 
        'status' => 0, 
        'details' => $errorDetails, 
        'body' => $resp, 
        'model' => $model, 
        'curl_errno' => $errno,
        'curl_errno_name' => 'CURLE_' . $errno,
        'suggestions' => $suggestions,
      ];
    }

    if ($status < 200 || $status >= 300) {
      return ['ok' => false, 'type' => 'http', 'status' => $status, 'body' => $resp, 'model' => $model];
    }

    $json = json_decode($resp ?: '', true);
    $text = '';
    if (is_array($json)
      && isset($json['candidates'][0]['content']['parts'][0]['text'])
      && is_string($json['candidates'][0]['content']['parts'][0]['text'])
    ) {
      $text = $json['candidates'][0]['content']['parts'][0]['text'];
    }

    if (trim($text) === '') {
      return ['ok' => false, 'type' => 'empty', 'status' => 502, 'body' => $resp, 'model' => $model];
    }

    return ['ok' => true, 'text' => $text, 'model' => $model];
  }

  /**
   * Generate answer from question
   */
  public function generateAnswer(string $question, string $mode = 'faq', ?array $history = null): array
  {
    // Detect requests to talk to a human agent / customer care
    $agentKeywords = [
      'agent',
      'human',
      'customer care',
      'customer support',
      'talk to someone',
      'connect me',
      'real person',
      'employee',
      'call me',
      'support executive',
    ];

    $lowerQuestion = mb_strtolower($question);
    foreach ($agentKeywords as $keyword) {
      if ($keyword !== '' && mb_strpos($lowerQuestion, mb_strtolower($keyword)) !== false) {
        return [
          'ok' => true,
          'answer' => "🧑‍💼 Connecting you to an agent…\nPlease wait while we assign a support representative.",
          'model' => 'agent-redirect',
        ];
      }
    }

    $context = '';
    if ($mode === 'faq') {
      $context = $this->buildFAQContext();
    }

    $systemInstruction = $mode === 'faq'
      ? "You are DigiMarker Assistant. Answer ONLY using the provided FAQ context. " .
        "If the answer is not present in the context, do NOT say \"I don't have that information in the DigiMarker FAQ.\" " .
        "Instead, briefly suggest that the user can clear or update the current context, provide more detail about their question, " .
        "or review the available FAQ tags/categories to see if they help. " .
        "Keep the answer short, step-by-step when applicable."
      : "You are DigiMarker Assistant. Be accurate and concise. " .
        "If you are unsure, ask one clarifying question.";

    $contents = [];

    if (is_array($history)) {
      $maxTurns = 10;
      $turns = array_slice($history, -$maxTurns);
      foreach ($turns as $turn) {
        if (!is_array($turn)) continue;
        $role = isset($turn['role']) ? strtolower(trim((string)$turn['role'])) : '';
        $content = isset($turn['content']) ? trim((string)$turn['content']) : '';
        if (!in_array($role, ['user', 'assistant'], true)) continue;
        if ($content === '') continue;
        if (mb_strlen($content) > 5000) $content = mb_substr($content, 0, 5000);
        $contents[] = [
          'role' => $role,
          'parts' => [
            ['text' => $content],
          ],
        ];
      }
    }

    $userText = $mode === 'faq'
      ? "FAQ CONTEXT:\n" . $context . "\n\nUSER QUESTION:\n" . $question
      : $question;

    $contents[] = [
      'role' => 'user',
      'parts' => [
        ['text' => $userText],
      ],
    ];

    $payload = [
      'contents' => $contents,
      'systemInstruction' => [
        'parts' => [
          ['text' => $systemInstruction],
        ],
      ],
      'generationConfig' => [
        'temperature' => $mode === 'faq' ? 0.2 : 0.4,
        'maxOutputTokens' => 500,
      ],
    ];

    $modelCandidates = $this->getModelCandidates();
    $lastHttpError = null;
    
    foreach ($modelCandidates as $candidate) {
      $result = $this->callGemini($candidate, $payload);
      if (!empty($result['ok'])) {
        return ['ok' => true, 'answer' => $result['text'], 'model' => $result['model']];
      }

      if (($result['type'] ?? '') === 'http' && (int)($result['status'] ?? 0) === 404) {
        $lastHttpError = $result;
        continue;
      }

      return $result;
    }

    return [
      'ok' => false,
      'error' => 'No supported Gemini model found for this API key.',
      'last_error' => $lastHttpError,
    ];
  }
}

