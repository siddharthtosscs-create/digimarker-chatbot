<?php
declare(strict_types=1);

/**
 * ModelsModel - Business logic for listing Gemini models
 */
class ModelsModel
{
  private string $apiKey;
  private array $localConfig;

  public function __construct()
  {
    $localConfigFile = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.local.php';
    $this->localConfig = [];
    if (is_file($localConfigFile)) {
      $loaded = require $localConfigFile;
      if (is_array($loaded)) $this->localConfig = $loaded;
    }

    $this->apiKey = (string)(getenv('GEMINI_API_KEY') ?: ($this->localConfig['GEMINI_API_KEY'] ?? ''));
  }

  public function getApiKey(): string
  {
    return $this->apiKey;
  }

  /**
   * Fetch available Gemini models
   */
  public function fetchModels(): array
  {
    if ($this->apiKey === '') {
      return ['ok' => false, 'error' => 'Gemini API key not configured. Set environment variable GEMINI_API_KEY in Apache/PHP.'];
    }

    $endpoint = "https://generativelanguage.googleapis.com/v1beta/models?key=" . rawurlencode($this->apiKey);

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT => 20,
      CURLOPT_CONNECTTIMEOUT => 10,
    ]);

    $resp = curl_exec($ch);
    $errno = curl_errno($ch);
    $err = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno) {
      return ['ok' => false, 'error' => 'Gemini request failed', 'details' => $err];
    }

    if ($status < 200 || $status >= 300) {
      return ['ok' => false, 'error' => 'Gemini returned an error', 'status' => $status, 'body' => $resp];
    }

    $json = json_decode($resp ?: '', true);
    if (!is_array($json) || !isset($json['models']) || !is_array($json['models'])) {
      return ['ok' => true, 'raw' => $resp];
    }

    $out = [];
    foreach ($json['models'] as $m) {
      if (!is_array($m)) continue;
      $name = isset($m['name']) && is_string($m['name']) ? $m['name'] : '';
      $methods = isset($m['supportedGenerationMethods']) && is_array($m['supportedGenerationMethods'])
        ? $m['supportedGenerationMethods']
        : [];
      if ($name === '') continue;
      if (!in_array('generateContent', $methods, true)) continue;

      $id = str_starts_with($name, 'models/') ? substr($name, 7) : $name;
      $out[] = [
        'id' => $id,
        'name' => $name,
        'supportedGenerationMethods' => array_values($methods),
      ];
    }

    return [
      'ok' => true,
      'models' => $out,
      'count' => count($out),
    ];
  }
}

