<?php
declare(strict_types=1);

/**
 * Agent Disconnect API
 * POST /api/agent/disconnect.php
 * Body: { "session_id": "...", "agent_id": 3 }
 * Agent disconnects from a chat session
 */

require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'controllers' . DIRECTORY_SEPARATOR . 'AgentController.php';

$controller = new AgentController();
$controller->handleDisconnect();

