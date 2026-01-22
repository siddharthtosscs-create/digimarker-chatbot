<?php
declare(strict_types=1);

/**
 * Create Agent API
 * POST /api/agent/create.php
 * Creates a new agent with name and email
 */

require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'controllers' . DIRECTORY_SEPARATOR . 'AgentController.php';

$controller = new AgentController();
$controller->handleCreateAgent();

