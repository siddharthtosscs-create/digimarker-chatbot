<?php
declare(strict_types=1);

/**
 * Agent Send Message API
 * POST /api/agent/send-message.php
 * Body: { "session_id": "...", "agent_id": 3, "message": "..." }
 * Sends a message from an agent to a chat session
 */

require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'controllers' . DIRECTORY_SEPARATOR . 'AgentController.php';

$controller = new AgentController();
$controller->handleSendMessage();

