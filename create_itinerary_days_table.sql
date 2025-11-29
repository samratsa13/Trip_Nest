-- SQL command to create the itinerary_days table
-- This table stores day-wise information for popular itineraries

CREATE TABLE IF NOT EXISTS itinerary_days (
    id INT AUTO_INCREMENT PRIMARY KEY,
    itinerary_id INT NOT NULL,
    day_number INT NOT NULL,
    day_title VARCHAR(255) NOT NULL,
    day_description TEXT,
    activities TEXT,
    accommodation VARCHAR(255),
    meals VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (itinerary_id) REFERENCES popular_itineraries(id) ON DELETE CASCADE,
    INDEX idx_itinerary_id (itinerary_id),
    UNIQUE KEY unique_day (itinerary_id, day_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

