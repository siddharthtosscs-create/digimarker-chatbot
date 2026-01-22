<?php
declare(strict_types=1);

/**
 * Agent Accept Chat API
 * POST /api/agent/accept.php
 * Body: { "session_id": "...", "agent_id": 3 }
 * Atomically assigns a chat to an agent
 */

require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'controllers' . DIRECTORY_SEPARATOR . 'AgentController.php';

$controller = new AgentController();
$controller->handleAccept();

