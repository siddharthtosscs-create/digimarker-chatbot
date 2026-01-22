<?php
declare(strict_types=1);

/**
 * Agent Chat Session API
 * GET /api/agent/chat-session.php?session_id=...
 * Returns full chat transcript for a session
 */

require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'controllers' . DIRECTORY_SEPARATOR . 'AgentController.php';

$controller = new AgentController();
$controller->handleChatSession();

