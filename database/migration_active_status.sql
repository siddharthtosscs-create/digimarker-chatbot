-- Migration Script: Update chat_sessions status from 'bot_active' to 'active'
-- Run this script if you have an existing database with the old 'bot_active' status
-- This script:
-- 1. Updates existing 'bot_active' status records to 'active'
-- 2. Updates the status ENUM to replace 'bot_active' with 'active'

-- Step 1: Update existing 'bot_active' status to 'active'
UPDATE `chat_sessions` 
SET `status` = 'active' 
WHERE `status` = 'bot_active';

-- Step 2: Modify the ENUM to replace 'bot_active' with 'active'
-- Note: MySQL doesn't support direct ENUM modification, so we need to:
-- 1. Change the column to VARCHAR temporarily
-- 2. Update values
-- 3. Change back to ENUM

-- Backup current data (optional but recommended)
CREATE TABLE IF NOT EXISTS `chat_sessions_backup` AS SELECT * FROM `chat_sessions`;

-- Modify column to VARCHAR
ALTER TABLE `chat_sessions` 
MODIFY COLUMN `status` VARCHAR(20) NOT NULL DEFAULT 'active';

-- Update any remaining 'bot_active' values
UPDATE `chat_sessions` 
SET `status` = 'active' 
WHERE `status` = 'bot_active';

-- Change back to ENUM with new values (active instead of bot_active)
ALTER TABLE `chat_sessions` 
MODIFY COLUMN `status` ENUM('active', 'agent_requested', 'agent_connected', 'closed') NOT NULL DEFAULT 'active';

-- Verify migration
SELECT `status`, COUNT(*) as count 
FROM `chat_sessions` 
GROUP BY `status`;

-- If everything looks good, you can drop the backup table:
-- DROP TABLE IF EXISTS `chat_sessions_backup`;

