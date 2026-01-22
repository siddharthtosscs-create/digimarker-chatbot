<?php
declare(strict_types=1);

/**
 * AgentModel - Handles agent operations and chat assignment
 * Manages agent queue, chat assignment, and message sending
 */
class AgentModel
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
   * Get waiting sessions (agent queue)
   * Returns sessions with status = 'agent_requested' and agent_assigned = 0
   * Only returns sessions if at least one agent is online
   * @return array Array of session records
   */
  public function getWaitingSessions(): array
  {
    try {
      // First check if any agent is online
      $onlineCheck = $this->db->prepare('SELECT COUNT(*) as count FROM agents WHERE status = "online"');
      $onlineCheck->execute();
      $result = $onlineCheck->fetch();
      
      if ($result === false || (int)$result['count'] === 0) {
        return []; // No online agents, return empty queue
      }

      $stmt = $this->db->prepare('
        SELECT session_id, created_at
        FROM chat_sessions
        WHERE status = "agent_requested"
          AND agent_assigned = 0
        ORDER BY created_at ASC
      ');
      $stmt->execute();
      return $stmt->fetchAll();
    } catch (PDOException $e) {
      if ($this->debugMode) {
        error_log("Get waiting sessions error: " . $e->getMessage());
      }
      return [];
    }
  }

  /**
   * Get chat transcript for a session
   * @return array|null Session data with messages or null if not found
   */
  public function getChatTranscript(string $sessionId): ?array
  {
    try {
      // Get session info
      $sessionStmt = $this->db->prepare('
        SELECT session_id, status, agent_id, agent_assigned, created_at
        FROM chat_sessions
        WHERE session_id = :session_id
        LIMIT 1
      ');
      $sessionStmt->execute(['session_id' => $sessionId]);
      $session = $sessionStmt->fetch();

      if ($session === false) {
        return null;
      }

      // Get messages with IDs
      $messagesStmt = $this->db->prepare('
        SELECT id, sender, message, created_at
        FROM chat_messages
        WHERE session_id = :session_id
        ORDER BY created_at ASC
      ');
      $messagesStmt->execute(['session_id' => $sessionId]);
      $messages = $messagesStmt->fetchAll();

      return [
        'session_id' => $session['session_id'],
        'status' => $session['status'],
        'agent_id' => $session['agent_id'],
        'agent_assigned' => (bool)$session['agent_assigned'],
        'created_at' => $session['created_at'],
        'messages' => $messages,
      ];
    } catch (PDOException $e) {
      if ($this->debugMode) {
        error_log("Get chat transcript error: " . $e->getMessage());
      }
      return null;
    }
  }

  /**
   * Agent accepts a chat (atomic assignment with locking)
   * Uses WHERE agent_assigned = 0 to ensure only one agent can take it
   * Validates agent is online before accepting
   * @return bool True if assignment was successful (rows affected = 1)
   */
  public function acceptChat(string $sessionId, int $agentId): bool
  {
    try {
      // First, verify agent is online and get name
      $agentStmt = $this->db->prepare('SELECT status, name FROM agents WHERE id = :agent_id LIMIT 1');
      $agentStmt->execute(['agent_id' => $agentId]);
      $agent = $agentStmt->fetch();
      
      if ($agent === false || $agent['status'] !== 'online') {
        return false; // Agent not found or not online
      }

      // Atomic update: only succeeds if agent_assigned = 0 and status is agent_requested
      $stmt = $this->db->prepare('
        UPDATE chat_sessions
        SET agent_id = :agent_id,
            agent_assigned = 1,
            status = "agent_connected",
            updated_at = NOW()
        WHERE session_id = :session_id
          AND agent_assigned = 0
          AND status = "agent_requested"
      ');
      $stmt->execute([
        'session_id' => $sessionId,
        'agent_id' => $agentId,
      ]);

      // If rows affected = 1, assignment was successful
      if ($stmt->rowCount() === 1) {
        // Get agent name for system message
        $agentName = $agent['name'] ?? 'Agent';

        // Insert system message about agent connection
        $systemMessage = "🧑‍💼 Agent {$agentName} has connected. How can I help you today?";
        $msgStmt = $this->db->prepare('
          INSERT INTO chat_messages (session_id, sender, message, source, created_at)
          VALUES (:session_id, "system", :message, "system", NOW())
        ');
        $msgStmt->execute([
          'session_id' => $sessionId,
          'message' => $systemMessage,
        ]);

        return true;
      }

      return false;
    } catch (PDOException $e) {
      if ($this->debugMode) {
        error_log("Accept chat error: " . $e->getMessage());
      }
      return false;
    }
  }

  /**
   * Agent sends a message
   * @return bool True if message was sent successfully
   */
  public function sendAgentMessage(string $sessionId, int $agentId, string $message): bool
  {
    try {
      // Verify agent is assigned to this session
      $verifyStmt = $this->db->prepare('
        SELECT id FROM chat_sessions
        WHERE session_id = :session_id
          AND agent_id = :agent_id
          AND agent_assigned = 1
        LIMIT 1
      ');
      $verifyStmt->execute([
        'session_id' => $sessionId,
        'agent_id' => $agentId,
      ]);

      if ($verifyStmt->fetch() === false) {
        return false; // Agent not assigned to this session
      }

      // Insert agent message
      $insertStmt = $this->db->prepare('
        INSERT INTO chat_messages (session_id, sender, message, source, created_at)
        VALUES (:session_id, "agent", :message, "agent", NOW())
      ');
      $insertStmt->execute([
        'session_id' => $sessionId,
        'message' => $message,
      ]);

      return true;
    } catch (PDOException $e) {
      if ($this->debugMode) {
        error_log("Send agent message error: " . $e->getMessage());
      }
      return false;
    }
  }

  /**
   * Get new messages for a session since last_message_id
   * Used for polling
   * IMPORTANT: Only returns agent messages if session status is agent_connected or agent_requested
   * @return array Array of new messages
   */
  public function getNewMessages(string $sessionId, int $lastMessageId = 0): array
  {
    try {
      // First, get the current session status
      $sessionStmt = $this->db->prepare('
        SELECT status FROM chat_sessions
        WHERE session_id = :session_id
        LIMIT 1
      ');
      $sessionStmt->execute(['session_id' => $sessionId]);
      $session = $sessionStmt->fetch();
      
      if ($session === false) {
        return []; // Session not found
      }
      
      $sessionStatus = $session['status'] ?? '';
      
      // Build query - filter agent messages if session is not in agent mode
      if (in_array($sessionStatus, ['agent_connected', 'agent_requested'], true)) {
        // Session is in agent mode - return all messages including agent messages
        $stmt = $this->db->prepare('
          SELECT id, sender, message, created_at
          FROM chat_messages
          WHERE session_id = :session_id
            AND id > :last_message_id
          ORDER BY created_at ASC
        ');
      } else {
        // Session is NOT in agent mode - exclude agent messages
        $stmt = $this->db->prepare('
          SELECT id, sender, message, created_at
          FROM chat_messages
          WHERE session_id = :session_id
            AND id > :last_message_id
            AND sender != "agent"
          ORDER BY created_at ASC
        ');
      }
      
      $stmt->execute([
        'session_id' => $sessionId,
        'last_message_id' => $lastMessageId,
      ]);

      return $stmt->fetchAll();
    } catch (PDOException $e) {
      if ($this->debugMode) {
        error_log("Get new messages error: " . $e->getMessage());
      }
      return [];
    }
  }

  /**
   * Validate agent ID exists
   * @return bool True if agent exists
   */
  public function validateAgentId(int $agentId): bool
  {
    try {
      $stmt = $this->db->prepare('SELECT id FROM agents WHERE id = :agent_id LIMIT 1');
      $stmt->execute(['agent_id' => $agentId]);
      return $stmt->fetch() !== false;
    } catch (PDOException $e) {
      if ($this->debugMode) {
        error_log("Validate agent ID error: " . $e->getMessage());
      }
      return false;
    }
  }

  /**
   * Get all agents with their active chat counts
   * @return array Array of agents with active_chats count
   */
  public function getAllAgents(): array
  {
    try {
      $stmt = $this->db->prepare('
        SELECT 
          a.id,
          a.name,
          a.email,
          a.status,
          a.max_active_chats,
          COUNT(DISTINCT cs.session_id) as active_chats
        FROM agents a
        LEFT JOIN chat_sessions cs ON cs.agent_id = a.id 
          AND cs.status = "agent_connected"
        GROUP BY a.id, a.name, a.email, a.status, a.max_active_chats
        ORDER BY a.name ASC
      ');
      $stmt->execute();
      $agents = $stmt->fetchAll();
      
      // Convert active_chats to int
      foreach ($agents as &$agent) {
        $agent['active_chats'] = (int)$agent['active_chats'];
      }
      
      return $agents;
    } catch (PDOException $e) {
      if ($this->debugMode) {
        error_log("Get all agents error: " . $e->getMessage());
      }
      return [];
    }
  }

  /**
   * Update agent status
   * @param int $agentId Agent ID
   * @param string $status New status ('online', 'offline', 'busy')
   * @return bool True if update was successful
   */
  public function updateAgentStatus(int $agentId, string $status): bool
  {
    if (!in_array($status, ['online', 'offline', 'busy'], true)) {
      return false;
    }

    try {
      $stmt = $this->db->prepare('
        UPDATE agents
        SET status = :status
        WHERE id = :agent_id
      ');
      $stmt->execute([
        'agent_id' => $agentId,
        'status' => $status,
      ]);
      return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
      if ($this->debugMode) {
        error_log("Update agent status error: " . $e->getMessage());
      }
      return false;
    }
  }

  /**
   * Get chat transcript with message IDs for polling
   * @return array|null Session data with messages or null if not found
   */
  public function getChatTranscriptWithIds(string $sessionId): ?array
  {
    try {
      // Get session info
      $sessionStmt = $this->db->prepare('
        SELECT session_id, status, agent_id, agent_assigned, created_at
        FROM chat_sessions
        WHERE session_id = :session_id
        LIMIT 1
      ');
      $sessionStmt->execute(['session_id' => $sessionId]);
      $session = $sessionStmt->fetch();

      if ($session === false) {
        return null;
      }

      // Get messages with IDs
      $messagesStmt = $this->db->prepare('
        SELECT id, sender, message, created_at
        FROM chat_messages
        WHERE session_id = :session_id
        ORDER BY created_at ASC
      ');
      $messagesStmt->execute(['session_id' => $sessionId]);
      $messages = $messagesStmt->fetchAll();

      return [
        'session_id' => $session['session_id'],
        'status' => $session['status'],
        'agent_id' => $session['agent_id'],
        'agent_assigned' => (bool)$session['agent_assigned'],
        'created_at' => $session['created_at'],
        'messages' => $messages,
      ];
    } catch (PDOException $e) {
      if ($this->debugMode) {
        error_log("Get chat transcript with IDs error: " . $e->getMessage());
      }
      return null;
    }
  }

  /**
   * Create a new agent
   * @param string $name Agent name
   * @param string $email Agent email
   * @return int|false Agent ID on success, false on failure
   */
  public function createAgent(string $name, string $email)
  {
    try {
      $stmt = $this->db->prepare('
        INSERT INTO agents (name, email, status, created_at)
        VALUES (:name, :email, "offline", NOW())
      ');
      $stmt->execute([
        'name' => $name,
        'email' => $email,
      ]);
      return (int)$this->db->lastInsertId();
    } catch (PDOException $e) {
      if ($this->debugMode) {
        error_log("Create agent error: " . $e->getMessage());
      }
      return false;
    }
  }

  /**
   * Disconnect agent from a chat session (STATUS-BASED ONLY)
   * ROLE SWITCH ONLY – the conversation MUST remain intact for the user.
   *
   * Behaviour:
   * - Does NOT delete any messages
   * - Does NOT clear or reset the chat history
   * - Updates the session back to bot/AI-only mode
   * - Inserts a clear system message for the end user, e.g.:
   *     "John has disconnected. You can continue chatting with our AI assistant."
   *
   * NOTE: We treat "active" as the AI-only state in the current schema.
   * @param string $sessionId Session ID (required)
   * @return bool True if session exists and was reset successfully
   */
  public function disconnectChat(string $sessionId): bool
  {
    try {
      // Check if session exists (no agent_id validation)
      $verifyStmt = $this->db->prepare('
        SELECT id, status, agent_id FROM chat_sessions
        WHERE session_id = :session_id
        LIMIT 1
      ');
      $verifyStmt->execute(['session_id' => $sessionId]);
      $session = $verifyStmt->fetch();

      if ($session === false) {
        return false; // Session not found
      }

      // Look up agent name (if any) so we can show "John has disconnected..."
      $agentName = 'The agent';
      if (!empty($session['agent_id'])) {
        try {
          $agentStmt = $this->db->prepare('SELECT name FROM agents WHERE id = :agent_id LIMIT 1');
          $agentStmt->execute(['agent_id' => $session['agent_id']]);
          $agentRow = $agentStmt->fetch();
          if ($agentRow !== false && isset($agentRow['name']) && $agentRow['name'] !== '') {
            $agentName = (string)$agentRow['name'];
          }
        } catch (PDOException $e) {
          if ($this->debugMode) {
            error_log("Lookup agent name on disconnect error: " . $e->getMessage());
          }
        }
      }

      // Reset session state to AI/bot-only mode ("active" in current schema)
      // IMPORTANT: Do NOT delete or clear any messages here.
      $updateStmt = $this->db->prepare('
        UPDATE chat_sessions
        SET status = "active",
            agent_assigned = 0,
            agent_id = NULL,
            updated_at = NOW()
        WHERE session_id = :session_id
      ');
      $updateStmt->execute(['session_id' => $sessionId]);

      // Check if update was successful
      if ($updateStmt->rowCount() === 0) {
        return false; // No rows updated
      }

      // Insert system message about agent disconnection (IMPORTANT)
      // This message appears ONCE and explains transition to the user.
      // Example: "John has disconnected. You can continue chatting with our AI assistant."
      $systemMessage = sprintf(
        '%s has disconnected. You can continue chatting with our AI assistant.',
        $agentName
      );
      $msgStmt = $this->db->prepare('
        INSERT INTO chat_messages (session_id, sender, message, source, created_at)
        VALUES (:session_id, "system", :message, "system", NOW())
      ');
      $msgStmt->execute([
        'session_id' => $sessionId,
        'message' => $systemMessage,
      ]);

      return true;
    } catch (PDOException $e) {
      if ($this->debugMode) {
        error_log("Disconnect chat error: " . $e->getMessage());
      }
      return false;
    }
  }
}

