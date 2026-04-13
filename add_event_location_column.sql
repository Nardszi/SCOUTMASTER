-- Add event_location column to events table
ALTER TABLE `events` ADD COLUMN `event_location` VARCHAR(255) NULL AFTER `event_description`;

-- Update existing events to have a default location
UPDATE `events` SET `event_location` = 'To be announced' WHERE `event_location` IS NULL OR `event_location` = '';
