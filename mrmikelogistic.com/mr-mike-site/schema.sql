CREATE DATABASE IF NOT EXISTS mr_mike_logistics CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mr_mike_logistics;

CREATE TABLE IF NOT EXISTS admins (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS bookings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reference_code VARCHAR(20) NOT NULL UNIQUE,
  customer_name VARCHAR(140) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  email VARCHAR(190) NULL,
  service_type ENUM('Airport transfer','City ride','Interstate ride','Outstation trip','Errand / business trip') NOT NULL,
  pickup_location VARCHAR(255) NOT NULL,
  dropoff_location VARCHAR(255) NOT NULL,
  travel_date DATE NOT NULL,
  travel_time TIME NOT NULL,
  passengers TINYINT UNSIGNED NOT NULL DEFAULT 1,
  notes TEXT NULL,
  status ENUM('New','Confirmed','In progress','Completed','Cancelled') NOT NULL DEFAULT 'New',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_bookings_status_date (status, travel_date),
  INDEX idx_bookings_created (created_at)
) ENGINE=InnoDB;
