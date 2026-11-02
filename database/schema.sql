DROP DATABASE IF EXISTS bentahub_db;
CREATE DATABASE bentahub_db;
USE bentahub_db;

-- ============================================================================
-- MEMBER 1: IJAN (Core Authentication, Users & Roles Slice)
-- Primary Tables: users, roles, user_roles
-- ============================================================================

-- 1. Users Table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(120) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone_number VARCHAR(20),
    is_verified TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Roles Table
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255)
);

-- 3. User Roles Junction Table
CREATE TABLE user_roles (
    user_id INT NOT NULL,
    role_id INT NOT NULL,
    PRIMARY KEY (user_id, role_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
);

-- ============================================================================
-- MEMBER 3: DINGLASAN (Categories & Catalog Slice)
-- Primary Tables: categories
-- ============================================================================

-- 4. Categories Table
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255)
);

-- ============================================================================
-- MEMBER 2: IB-IB (Safe Zones, Item Conditions & Product Listings Slice)
-- Primary Tables: item_conditions, safe_meetup_zones, listings, listing_images
-- ============================================================================

-- 5. Item Conditions Table
CREATE TABLE item_conditions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    condition_name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255)
);

-- 6. Safe Meetup Zones Table
CREATE TABLE safe_meetup_zones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    zone_name VARCHAR(100) NOT NULL,
    address VARCHAR(255) NOT NULL,
    city VARCHAR(100) NOT NULL,
    is_geofenced TINYINT(1) DEFAULT 1
);

-- 7. Listings Table
CREATE TABLE listings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT NOT NULL,
    category_id INT NOT NULL,
    condition_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    status ENUM('active', 'reserved', 'sold', 'archived') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT,
    FOREIGN KEY (condition_id) REFERENCES item_conditions(id) ON DELETE RESTRICT
);

-- 8. Listing Images Table
CREATE TABLE listing_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    listing_id INT NOT NULL,
    image_url VARCHAR(255) NOT NULL,
    is_primary TINYINT(1) DEFAULT 0,
    FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
);

-- ============================================================================
-- MEMBER 3: DINGLASAN (Price Drop Alerts Module)
-- Primary Tables: price_drop_alerts
-- ============================================================================

-- 9. Price Drop Alerts Table
CREATE TABLE price_drop_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    listing_id INT NOT NULL,
    target_price DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
);

-- ============================================================================
-- MEMBER 4: ESPIRITU (Chat Threads, Messages & Meetup Schedules Slice)
-- Primary Tables: chat_threads, chat_messages, meetup_schedules
-- ============================================================================

-- 10. Chat Threads Table
CREATE TABLE chat_threads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    listing_id INT NOT NULL,
    buyer_id INT NOT NULL,
    seller_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
    FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 11. Chat Messages Table
CREATE TABLE chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    thread_id INT NOT NULL,
    sender_id INT NOT NULL,
    message_body TEXT NOT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (thread_id) REFERENCES chat_threads(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 12. Meetup Schedules Table
CREATE TABLE meetup_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    thread_id INT NOT NULL,
    zone_id INT NOT NULL,
    scheduled_time DATETIME NOT NULL,
    status ENUM('pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending',
    FOREIGN KEY (thread_id) REFERENCES chat_threads(id) ON DELETE CASCADE,
    FOREIGN KEY (zone_id) REFERENCES safe_meetup_zones(id) ON DELETE RESTRICT
);

-- ============================================================================
-- MEMBER 5: DELVO / SIGRID (Seller Trust Scores & System Audit Logs Slice)
-- Primary Tables: seller_trust_scores, audit_logs
-- ============================================================================

-- 13. Seller Trust Scores Table
CREATE TABLE seller_trust_scores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT NOT NULL UNIQUE,
    rating_score DECIMAL(3, 2) DEFAULT 5.00,
    total_reviews INT DEFAULT 0,
    trust_tier VARCHAR(50) DEFAULT 'Bronze',
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 14. Audit Logs Table
CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);