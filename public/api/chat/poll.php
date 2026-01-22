<?php
declare(strict_types=1);

/**
 * Chat Polling API (for users)
 * GET /api/chat/poll.php?session_id=...&last_message_id=45
 * Returns new messages since last_message_id (for agent messages)
 */

require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'AgentModel.php';

header('Content-Type: application/json; charset=utf-8');

// CORS
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
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
  http_response_code(204);
  exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
  http_response_code(405);
  echo json_encode(['error' => 'Method not allowed. Use GET.']);
  exit;
}

$sessionId = isset($_GET['session_id']) ? trim((string)$_GET['session_id']) : '';
$lastMessageId = isset($_GET['last_message_id']) ? (int)$_GET['last_message_id'] : 0;

if ($sessionId === '') {
  http_response_code(400);
  echo json_encode(['error' => 'Missing session_id parameter.']);
  exit;
}

try {
  $model = new AgentModel();
  $messages = $model->getNewMessages($sessionId, $lastMessageId);
  
  // Format messages for frontend
  $formattedMessages = [];
  foreach ($messages as $msg) {
    $formattedMessages[] = [
      'id' => (int)$msg['id'],
      'sender' => $msg['sender'],
      'message' => $msg['message'],
      'created_at' => $msg['created_at'],
    ];
  }
  
  echo json_encode(['messages' => $formattedMessages]);
  exit;
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Internal server error.']);
  exit;
}

