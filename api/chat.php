<?php
declare(strict_types=1);

/**
 * DigiMarker Chat API (reusable)
 *
 * Request (POST JSON):
 * {
 *   "question": "string",
 *   "mode": "faq" | "general",          // optional (default: "faq")
 *   "history": [                         // optional
 *     { "role": "user", "content": "..." },
 *     { "role": "assistant", "content": "..." }
 *   ]
 * }
 *
 * Response (JSON):
 * { "answer": "string", "mode": "faq|general", "model": "..." }
 */

require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'controllers' . DIRECTORY_SEPARATOR . 'ChatController.php';

$controller = new ChatController();
$controller->handleRequest();
