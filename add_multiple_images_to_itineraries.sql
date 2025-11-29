-- SQL command to add multiple images support to popular_itineraries table
-- This adds a TEXT column to store JSON array of additional images

ALTER TABLE popular_itineraries 
ADD COLUMN IF NOT EXISTS additional_images TEXT COMMENT 'JSON array of additional image paths';

-- Alternative if your MySQL version doesn't support IF NOT EXISTS:
-- ALTER TABLE popular_itineraries ADD COLUMN additional_images TEXT COMMENT 'JSON array of additional image paths';

