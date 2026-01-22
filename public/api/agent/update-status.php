<?php
declare(strict_types=1);

/**
 * Agent Status Update API
 * POST /api/agent/update-status.php
 * Body: { "agent_id": 1, "status": "online" }
 * Updates agent status (online/offline/busy)
 */

require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'controllers' . DIRECTORY_SEPARATOR . 'AgentController.php';

$controller = new AgentController();
$controller->handleUpdateStatus();

