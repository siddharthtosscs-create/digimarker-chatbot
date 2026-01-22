-- Migration Script: Update chat_sessions status from 'active' to 'bot_active'
-- Run this script if you have an existing database with the old 'active' status
-- This script:
-- 1. Adds the agent_disconnected_at column
-- 2. Updates the status ENUM to include 'bot_active' and remove 'active'
-- 3. Migrates existing 'active' status records to 'bot_active'

-- Step 1: Add agent_disconnected_at column if it doesn't exist
ALTER TABLE `chat_sessions` 
ADD COLUMN IF NOT EXISTS `agent_disconnected_at` DATETIME NULL AFTER `agent_assigned`;

-- Step 2: Update existing 'active' status to 'bot_active'
UPDATE `chat_sessions` 
SET `status` = 'bot_active' 
WHERE `status` = 'active';

-- Step 3: Modify the ENUM to replace 'active' with 'bot_active'
-- Note: MySQL doesn't support direct ENUM modification, so we need to:
-- 1. Change the column to VARCHAR temporarily
-- 2. Update values
-- 3. Change back to ENUM

-- Backup current data
CREATE TABLE IF NOT EXISTS `chat_sessions_backup` AS SELECT * FROM `chat_sessions`;

-- Modify column to VARCHAR
ALTER TABLE `chat_sessions` 
MODIFY COLUMN `status` VARCHAR(20) NOT NULL DEFAULT 'bot_active';

-- Update any remaining 'active' values
UPDATE `chat_sessions` 
SET `status` = 'bot_active' 
WHERE `status` = 'active';

-- Change back to ENUM with new values
ALTER TABLE `chat_sessions` 
MODIFY COLUMN `status` ENUM('bot_active', 'agent_requested', 'agent_connected', 'closed') NOT NULL DEFAULT 'bot_active';

-- Verify migration
SELECT `status`, COUNT(*) as count 
FROM `chat_sessions` 
GROUP BY `status`;

-- If everything looks good, you can drop the backup table:
-- DROP TABLE IF EXISTS `chat_sessions_backup`;

