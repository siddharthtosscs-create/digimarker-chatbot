<?php
declare(strict_types=1);

/**
 * SessionModel - Handles chat session and message persistence
 * Manages session creation, validation, and message logging
 */
class SessionModel
{
  private PDO $db;
  private bool $debugMode;

  public function __construct()
  {
    require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'Database.php';
    $this->db = Database::getConnection();
    $this->debugMode = (bool)(getenv('DIGIMARKER_DEBUG') !== false ? getenv('DIGIMARKER_DEBUG') : false);
  }

  /**
   * Validate session_id format
   * Accepts UUID v4 or secure random string (32-64 chars, alphanumeric + hyphens)
   */
  public function validateSessionId(string $sessionId): bool
  {
    // UUID v4 format: 8-4-4-4-12 hex digits with hyphens
    $uuidPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
    if (preg_match($uuidPattern, $sessionId)) {
      return true;
    }

    // Secure random string: 32-64 alphanumeric + hyphens/underscores
    if (preg_match('/^[a-zA-Z0-9_-]{32,64}$/', $sessionId)) {
      return true;
    }

    return false;
  }

  /**
   * Ensure session exists in database, create if missing
   * @return bool True if session exists or was created successfully
   */
  public function ensureSession(string $sessionId): bool
  {
    if (!$this->validateSessionId($sessionId)) {
      if ($this->debugMode) {
        error_log("Invalid session_id format: " . substr($sessionId, 0, 20) . "...");
      }
      return false;
    }

    try {
      // Check if session exists
      $stmt = $this->db->prepare('SELECT id FROM chat_sessions WHERE session_id = :session_id LIMIT 1');
      $stmt->execute(['session_id' => $sessionId]);
      $existing = $stmt->fetch();

      if ($existing !== false) {
        // Session exists, update status to active if it was closed
        $updateStmt = $this->db->prepare('UPDATE chat_sessions SET status = "active", updated_at = NOW() WHERE session_id = :session_id AND status = "closed"');
        $updateStmt->execute(['session_id' => $sessionId]);
        return true;
      }

      // Create new session
      $insertStmt = $this->db->prepare('
        INSERT INTO chat_sessions (session_id, status, created_at, updated_at)
        VALUES (:session_id, "active", NOW(), NOW())
      ');
      $insertStmt->execute(['session_id' => $sessionId]);

      return true;
    } catch (PDOException $e) {
      if ($this->debugMode) {
        error_log("Session ensure error: " . $e->getMessage());
      }
      return false;
    }
  }

  /**
   * Log a message to the database
   * @param string $sessionId Session identifier
   * @param string $sender 'user', 'bot', or 'system'
   * @param string $message Message content
   * @param string $source 'faq', 'gemini', or 'system'
   * @return bool True if message was logged successfully
   */
  public function logMessage(string $sessionId, string $sender, string $message, string $source): bool
  {
    if (!$this->validateSessionId($sessionId)) {
      return false;
    }

    // Validate sender and source enums
    if (!in_array($sender, ['user', 'bot', 'system', 'agent'], true)) {
      $sender = 'system';
    }
    if (!in_array($source, ['faq', 'gemini', 'system', 'agent'], true)) {
      $source = 'system';
    }

    // Truncate message if too long (safety limit: 65535 bytes for TEXT field)
    $maxLength = 50000; // Leave some buffer
    if (mb_strlen($message) > $maxLength) {
      $message = mb_substr($message, 0, $maxLength) . ' [truncated]';
    }

    try {
      $stmt = $this->db->prepare('
        INSERT INTO chat_messages (session_id, sender, message, source, created_at)
        VALUES (:session_id, :sender, :message, :source, NOW())
      ');
      $stmt->execute([
        'session_id' => $sessionId,
        'sender' => $sender,
        'message' => $message,
        'source' => $source,
      ]);

      return true;
    } catch (PDOException $e) {
      if ($this->debugMode) {
        error_log("Message log error: " . $e->getMessage());
      }
      return false;
    }
  }

  /**
   * Get all messages for a session (for future use)
   * @return array Array of message records
   */
  public function getSessionMessages(string $sessionId, int $limit = 100): array
  {
    if (!$this->validateSessionId($sessionId)) {
      return [];
    }

    try {
      $stmt = $this->db->prepare('
        SELECT sender, message, source, created_at
        FROM chat_messages
        WHERE session_id = :session_id
        ORDER BY created_at ASC
        LIMIT :limit
      ');
      $stmt->bindValue(':session_id', $sessionId, PDO::PARAM_STR);
      $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
      $stmt->execute();

      return $stmt->fetchAll();
    } catch (PDOException $e) {
      if ($this->debugMode) {
        error_log("Get messages error: " . $e->getMessage());
      }
      return [];
    }
  }

  /**
   * Request agent connection for a session
   * Updates session status to 'agent_requested' and sets agent_assigned = 0
   * Clears any previous agent assignment to ensure fresh request
   * @return bool True if update was successful
   */
  public function requestAgent(string $sessionId): bool
  {
    if (!$this->validateSessionId($sessionId)) {
      return false;
    }

    try {
      // Reset session to agent_requested state, clearing any previous agent assignment
      $stmt = $this->db->prepare('
        UPDATE chat_sessions
        SET status = "agent_requested",
            agent_id = NULL,
            agent_assigned = 0,
            updated_at = NOW()
        WHERE session_id = :session_id
      ');
      $stmt->execute(['session_id' => $sessionId]);
      return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
      if ($this->debugMode) {
        error_log("Agent request error: " . $e->getMessage());
      }
      return false;
    }
  }

  /**
   * Get session status
   * @return array|null Session data or null if not found
   */
  public function getSessionStatus(string $sessionId): ?array
  {
    if (!$this->validateSessionId($sessionId)) {
      return null;
    }

    try {
      $stmt = $this->db->prepare('
        SELECT session_id, status, agent_id, agent_assigned, created_at, updated_at
        FROM chat_sessions
        WHERE session_id = :session_id
        LIMIT 1
      ');
      $stmt->execute(['session_id' => $sessionId]);
      $result = $stmt->fetch();
      return $result !== false ? $result : null;
    } catch (PDOException $e) {
      if ($this->debugMode) {
        error_log("Get session status error: " . $e->getMessage());
      }
      return null;
    }
  }
}

