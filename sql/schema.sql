-- Run once in phpMyAdmin or MySQL CLI for the MUBASA campaign feedback form.
-- Database: ssendi_mubasa

CREATE TABLE IF NOT EXISTS feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NULL,
    email VARCHAR(255) NULL,
    rank_role VARCHAR(100) NULL,
    campus VARCHAR(100) NULL,
    pillar VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
