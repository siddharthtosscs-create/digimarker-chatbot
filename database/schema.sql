-- DigiMarker Chatbot Database Schema
-- Run this SQL script to create the required tables for chat session persistence

-- Table: agents
-- Stores human support agents and capacity settings
-- MUST be created before chat_sessions (foreign key dependency)
CREATE TABLE IF NOT EXISTS `agents` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100),
  `email` VARCHAR(150),
  `status` ENUM('online','offline','busy') DEFAULT 'offline',
  `max_active_chats` INT DEFAULT 3,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: chat_sessions
-- Stores chat session metadata
CREATE TABLE IF NOT EXISTS `chat_sessions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_id` VARCHAR(64) NOT NULL UNIQUE,
  `status` ENUM('active', 'agent_requested', 'agent_connected', 'closed') NOT NULL DEFAULT 'active',
  `agent_id` BIGINT UNSIGNED NULL,
  `agent_assigned` TINYINT(1) NOT NULL DEFAULT 0,
  `agent_disconnected_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_session_id` (`session_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_agent_id` (`agent_id`),
  CONSTRAINT `fk_chat_sessions_agent` FOREIGN KEY (`agent_id`) REFERENCES `agents` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: chat_messages
-- Stores all chat messages (user, bot, system, and agent messages)
CREATE TABLE IF NOT EXISTS `chat_messages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_id` VARCHAR(64) NOT NULL,
  `sender` ENUM('user', 'bot', 'system', 'agent') NOT NULL,
  `message` TEXT NOT NULL,
  `source` ENUM('faq', 'gemini', 'system', 'agent') NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_session_id` (`session_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_sender` (`sender`),
  CONSTRAINT `fk_chat_messages_session` FOREIGN KEY (`session_id`) REFERENCES `chat_sessions` (`session_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


