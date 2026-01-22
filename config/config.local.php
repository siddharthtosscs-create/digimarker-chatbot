<?php
/**
 * Copy this file to `api/config.local.php` and fill your secrets locally.
 * IMPORTANT: Do NOT commit `config.local.php` to a public repo.
 */

return [
  // Get a key from Google AI Studio.
  'GEMINI_API_KEY' => 'AIzaSyBMp_GBY69XK6GLgYJjP8miAdzPUlEj1Kw',

  // Optional: protect your chat API with an internal key.
  // 'DIGIMARKER_CHAT_API_KEY' => 'YOUR_INTERNAL_KEY',

  // Agent Panel API Key - Required for admin/agent panel access
  // Set this to a secure random string (e.g., generate with: openssl rand -hex 32)
  'DIGIMARKER_AGENT_API_KEY' => 'my_secure_key_12345',

  // Database configuration for chat session persistence
  'DB_HOST' => 'localhost',
  'DB_NAME' => 'digimarker_chat',  // Update with your database name
  'DB_USER' => 'root',              // Update with your database username
  'DB_PASS' => '',                  // Update with your database password
  'DB_CHARSET' => 'utf8mb4',
];


