<?php
declare(strict_types=1);

/**
 * Agent Queue API
 * GET /api/agent/queue.php
 * Returns waiting sessions that need agent assignment
 */

require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'controllers' . DIRECTORY_SEPARATOR . 'AgentController.php';

$controller = new AgentController();
$controller->handleQueue();

