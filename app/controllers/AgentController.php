<?php
declare(strict_types=1);

/**
 * AgentController - Handles agent API requests
 * Provides endpoints for agent queue, chat assignment, and messaging
 */
class AgentController
{
  private AgentModel $model;
  private bool $debugMode;

  private array $localConfig;

  public function __construct()
  {
    require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'AgentModel.php';
    $this->model = new AgentModel();
    $this->debugMode = (bool)(getenv('DIGIMARKER_DEBUG') !== false ? getenv('DIGIMARKER_DEBUG') : false);
    
    // Load config file
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
  }

  /**
   * Authenticate agent request
   * Checks for API key or token (can be extended for IP whitelist, etc.)
   * @return bool True if authenticated
   */
  private function authenticate(): bool
  {
    // Get API key from environment variable or config file
    $envApiKey = getenv('DIGIMARKER_AGENT_API_KEY');
    $requiredApiKey = (string)($envApiKey !== false ? $envApiKey : ($this->localConfig['DIGIMARKER_AGENT_API_KEY'] ?? ''));
    
    // If no API key is configured, allow access (for development)
    if ($requiredApiKey === '') {
      return true;
    }
    
    // Check for API key in header
    $providedApiKey = (string)($_SERVER['HTTP_X_API_KEY'] ?? $_SERVER['HTTP_X_AGENT_API_KEY'] ?? '');
    if (!hash_equals($requiredApiKey, $providedApiKey)) {
      return false;
    }

    // TODO: Add IP whitelist check if needed
    // $allowedIPs = ['127.0.0.1', '::1'];
    // $clientIP = $_SERVER['REMOTE_ADDR'] ?? '';
    // if (!in_array($clientIP, $allowedIPs, true)) {
    //   return false;
    // }

    return true;
  }

  /**
   * Handle GET /api/agent/queue.php
   * Returns waiting sessions
   */
  public function handleQueue(): void
  {
    header('Content-Type: application/json; charset=utf-8');

    if (!$this->authenticate()) {
      http_response_code(401);
      echo json_encode(['error' => 'Unauthorized']);
      exit;
    }

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
      http_response_code(405);
      echo json_encode(['error' => 'Method not allowed. Use GET.']);
      exit;
    }

    $sessions = $this->model->getWaitingSessions();
    echo json_encode(['sessions' => $sessions]);
    exit;
  }

  /**
   * Handle GET /api/agent/chat-session.php?session_id=...
   * Returns chat transcript for a session
   */
  public function handleChatSession(): void
  {
    header('Content-Type: application/json; charset=utf-8');

    if (!$this->authenticate()) {
      http_response_code(401);
      echo json_encode(['error' => 'Unauthorized']);
      exit;
    }

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
      http_response_code(405);
      echo json_encode(['error' => 'Method not allowed. Use GET.']);
      exit;
    }

    $sessionId = isset($_GET['session_id']) ? trim((string)$_GET['session_id']) : '';
    if ($sessionId === '') {
      http_response_code(400);
      echo json_encode(['error' => 'Missing session_id parameter.']);
      exit;
    }

    $transcript = $this->model->getChatTranscriptWithIds($sessionId);
    if ($transcript === null) {
      http_response_code(404);
      echo json_encode(['error' => 'Session not found.']);
      exit;
    }

    echo json_encode($transcript);
    exit;
  }

  /**
   * Handle POST /api/agent/accept.php
   * Agent accepts a chat (atomic assignment)
   */
  public function handleAccept(): void
  {
    header('Content-Type: application/json; charset=utf-8');

    if (!$this->authenticate()) {
      http_response_code(401);
      echo json_encode(['error' => 'Unauthorized']);
      exit;
    }

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
      http_response_code(405);
      echo json_encode(['error' => 'Method not allowed. Use POST.']);
      exit;
    }

    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);
    if (!is_array($data)) {
      http_response_code(400);
      echo json_encode(['error' => 'Invalid JSON body.']);
      exit;
    }

    $sessionId = isset($data['session_id']) ? trim((string)$data['session_id']) : '';
    $agentId = isset($data['agent_id']) ? (int)$data['agent_id'] : 0;

    if ($sessionId === '' || $agentId <= 0) {
      http_response_code(400);
      echo json_encode(['error' => 'Missing or invalid session_id or agent_id.']);
      exit;
    }

    // Validate agent exists
    if (!$this->model->validateAgentId($agentId)) {
      http_response_code(404);
      echo json_encode(['error' => 'Agent not found.']);
      exit;
    }

    $success = $this->model->acceptChat($sessionId, $agentId);
    if (!$success) {
      http_response_code(409); // Conflict - another agent already took it
      echo json_encode(['error' => 'Chat already assigned to another agent.']);
      exit;
    }

    echo json_encode(['success' => true, 'message' => 'Chat accepted successfully.']);
    exit;
  }

  /**
   * Handle POST /api/agent/send-message.php
   * Agent sends a message to a chat
   */
  public function handleSendMessage(): void
  {
    header('Content-Type: application/json; charset=utf-8');

    if (!$this->authenticate()) {
      http_response_code(401);
      echo json_encode(['error' => 'Unauthorized']);
      exit;
    }

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
      http_response_code(405);
      echo json_encode(['error' => 'Method not allowed. Use POST.']);
      exit;
    }

    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);
    if (!is_array($data)) {
      http_response_code(400);
      echo json_encode(['error' => 'Invalid JSON body.']);
      exit;
    }

    $sessionId = isset($data['session_id']) ? trim((string)$data['session_id']) : '';
    $agentId = isset($data['agent_id']) ? (int)$data['agent_id'] : 0;
    $message = isset($data['message']) ? trim((string)$data['message']) : '';

    if ($sessionId === '' || $agentId <= 0 || $message === '') {
      http_response_code(400);
      echo json_encode(['error' => 'Missing or invalid session_id, agent_id, or message.']);
      exit;
    }

    // Truncate message if too long
    if (mb_strlen($message) > 50000) {
      $message = mb_substr($message, 0, 50000) . ' [truncated]';
    }

    $success = $this->model->sendAgentMessage($sessionId, $agentId, $message);
    if (!$success) {
      http_response_code(403);
      echo json_encode(['error' => 'Agent not assigned to this session or session not found.']);
      exit;
    }

    echo json_encode(['success' => true, 'message' => 'Message sent successfully.']);
    exit;
  }

  /**
   * Handle GET /api/agent/agents.php
   * Returns all agents with their status and active chat counts
   */
  public function handleGetAgents(): void
  {
    header('Content-Type: application/json; charset=utf-8');

    if (!$this->authenticate()) {
      http_response_code(401);
      echo json_encode(['error' => 'Unauthorized']);
      exit;
    }

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
      http_response_code(405);
      echo json_encode(['error' => 'Method not allowed. Use GET.']);
      exit;
    }

    $agents = $this->model->getAllAgents();
    echo json_encode(['agents' => $agents]);
    exit;
  }

  /**
   * Handle POST /api/agent/update-status.php
   * Updates agent status (online/offline/busy)
   */
  public function handleUpdateStatus(): void
  {
    header('Content-Type: application/json; charset=utf-8');

    if (!$this->authenticate()) {
      http_response_code(401);
      echo json_encode(['error' => 'Unauthorized']);
      exit;
    }

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
      http_response_code(405);
      echo json_encode(['error' => 'Method not allowed. Use POST.']);
      exit;
    }

    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);
    if (!is_array($data)) {
      http_response_code(400);
      echo json_encode(['error' => 'Invalid JSON body.']);
      exit;
    }

    $agentId = isset($data['agent_id']) ? (int)$data['agent_id'] : 0;
    $status = isset($data['status']) ? trim((string)$data['status']) : '';

    if ($agentId <= 0 || $status === '') {
      http_response_code(400);
      echo json_encode(['error' => 'Missing or invalid agent_id or status.']);
      exit;
    }

    $success = $this->model->updateAgentStatus($agentId, $status);
    if (!$success) {
      http_response_code(400);
      echo json_encode(['error' => 'Failed to update agent status.']);
      exit;
    }

    echo json_encode(['success' => true, 'message' => 'Agent status updated successfully.']);
    exit;
  }

  /**
   * Handle POST /api/agent/create.php
   * Creates a new agent
   */
  public function handleCreateAgent(): void
  {
    header('Content-Type: application/json; charset=utf-8');

    if (!$this->authenticate()) {
      http_response_code(401);
      echo json_encode(['error' => 'Unauthorized']);
      exit;
    }

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
      http_response_code(405);
      echo json_encode(['error' => 'Method not allowed. Use POST.']);
      exit;
    }

    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);
    if (!is_array($data)) {
      http_response_code(400);
      echo json_encode(['error' => 'Invalid JSON body.']);
      exit;
    }

    $name = isset($data['name']) ? trim((string)$data['name']) : '';
    $email = isset($data['email']) ? trim((string)$data['email']) : '';

    if ($name === '' || $email === '') {
      http_response_code(400);
      echo json_encode(['error' => 'Missing or invalid name or email.']);
      exit;
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      http_response_code(400);
      echo json_encode(['error' => 'Invalid email format.']);
      exit;
    }

    $agentId = $this->model->createAgent($name, $email);
    if ($agentId === false) {
      http_response_code(500);
      echo json_encode(['error' => 'Failed to create agent.']);
      exit;
    }

    echo json_encode(['success' => true, 'agent_id' => $agentId, 'message' => 'Agent created successfully.']);
    exit;
  }

  /**
   * Handle POST /api/agent/disconnect.php
   * Disconnects agent from a chat session (STATUS-BASED, NO PERMISSION CHECKS)
   * Input: { "session_id": "uuid", "source": "agent" | "system" }
   * 
   * Core Rule: Disconnect is NOT permission-based — it is STATE-RESET based
   * If a session exists → it can be disconnected safely.
   */
  public function handleDisconnect(): void
  {
    header('Content-Type: application/json; charset=utf-8');

    if (!$this->authenticate()) {
      http_response_code(401);
      echo json_encode(['error' => 'Unauthorized']);
      exit;
    }

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
      http_response_code(405);
      echo json_encode(['error' => 'Method not allowed. Use POST.']);
      exit;
    }

    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);
    if (!is_array($data)) {
      http_response_code(400);
      echo json_encode(['error' => 'Invalid JSON body.']);
      exit;
    }

    $sessionId = isset($data['session_id']) ? trim((string)$data['session_id']) : '';
    // source is optional, for logging purposes only
    $source = isset($data['source']) ? trim((string)$data['source']) : 'agent';

    if ($sessionId === '') {
      http_response_code(400);
      echo json_encode(['error' => 'Missing session_id parameter.']);
      exit;
    }

    // NO agent_id validation - disconnect is status-based only
    // NO 403 errors - if session exists, disconnect succeeds
    $success = $this->model->disconnectChat($sessionId);
    if (!$success) {
      // Only fail if session doesn't exist (404, not 403)
      http_response_code(404);
      echo json_encode(['error' => 'Session not found.']);
      exit;
    }

    echo json_encode(['success' => true, 'message' => 'Chat disconnected successfully.']);
    exit;
  }
}

