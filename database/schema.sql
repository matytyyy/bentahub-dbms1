-- ============================================================================
-- BentaHub - schema.sql  (run FIRST, then the three features_*.sql files, then seed.sql)
-- Target: MySQL 8.0 / 8.4 LTS   |   15 tables, PK + FK + CHECK + UNIQUE
-- ============================================================================
DROP DATABASE IF EXISTS bentahub_db;
CREATE DATABASE bentahub_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bentahub_db;

-- ============================================================================
-- IJAN - Core, Users & Roles          tables: users, roles, user_roles
-- ============================================================================
CREATE TABLE users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    full_name     VARCHAR(100) NOT NULL,
    email         VARCHAR(120) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone_number  VARCHAR(20),
    is_verified   TINYINT(1) NOT NULL DEFAULT 0,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE roles (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    role_name   VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255)
);

CREATE TABLE user_roles (
    user_id INT NOT NULL,
    role_id INT NOT NULL,
    PRIMARY KEY (user_id, role_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
);

-- ============================================================================
-- IB-IB - Master Data                 tables: categories, item_conditions,
--                                             safe_meetup_zones
-- ============================================================================
CREATE TABLE categories (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL UNIQUE,
    description   VARCHAR(255)
);

CREATE TABLE item_conditions (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    condition_name VARCHAR(50) NOT NULL UNIQUE,
    description    VARCHAR(255)
);

CREATE TABLE safe_meetup_zones (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    zone_name    VARCHAR(100) NOT NULL,
    address      VARCHAR(255) NOT NULL,
    city         VARCHAR(100) NOT NULL,
    is_geofenced TINYINT(1) NOT NULL DEFAULT 1
);

-- ============================================================================
-- DINGLASAN - Item Catalog            tables: listings, listing_images,
--                                             price_drop_alerts
-- ============================================================================
CREATE TABLE listings (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    seller_id    INT NOT NULL,
    category_id  INT NOT NULL,
    condition_id INT NOT NULL,
    title        VARCHAR(150) NOT NULL,
    description  TEXT,
    price        DECIMAL(10,2) NOT NULL CHECK (price > 0),
    status       ENUM('active','reserved','sold','archived') NOT NULL DEFAULT 'active',
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id)    REFERENCES users(id)           ON DELETE CASCADE,
    FOREIGN KEY (category_id)  REFERENCES categories(id)      ON DELETE RESTRICT,
    FOREIGN KEY (condition_id) REFERENCES item_conditions(id) ON DELETE RESTRICT
);

CREATE TABLE listing_images (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    listing_id INT NOT NULL,
    image_url  VARCHAR(255) NOT NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
);

CREATE TABLE price_drop_alerts (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT NOT NULL,
    listing_id   INT NOT NULL,
    target_price DECIMAL(10,2) NOT NULL CHECK (target_price > 0),
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_alert_user_listing (user_id, listing_id),
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
);

-- ============================================================================
-- ESPIRITU - Inquiries & Chat         tables: chat_threads, chat_messages,
--                                             meetup_schedules
-- ============================================================================
CREATE TABLE chat_threads (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    listing_id INT NOT NULL,
    buyer_id   INT NOT NULL,
    seller_id  INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_thread_listing_buyer (listing_id, buyer_id),
    FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
    FOREIGN KEY (buyer_id)   REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (seller_id)  REFERENCES users(id)    ON DELETE CASCADE
);

CREATE TABLE chat_messages (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    thread_id    INT NOT NULL,
    sender_id    INT NOT NULL,
    message_body TEXT NOT NULL,
    sent_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (thread_id) REFERENCES chat_threads(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id)        ON DELETE CASCADE
);

CREATE TABLE meetup_schedules (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    thread_id      INT NOT NULL,
    zone_id        INT NOT NULL,
    scheduled_time DATETIME NOT NULL,
    status         ENUM('pending','confirmed','completed','cancelled') NOT NULL DEFAULT 'pending',
    FOREIGN KEY (thread_id) REFERENCES chat_threads(id)      ON DELETE CASCADE,
    FOREIGN KEY (zone_id)   REFERENCES safe_meetup_zones(id) ON DELETE RESTRICT
);

-- ============================================================================
-- DELVO - Trust & Audit Engine        tables: seller_trust_scores, reviews,
--                                             audit_logs
-- seller_trust_scores is COMPUTED by triggers (see features_trust_engine.sql), never typed.
-- ============================================================================
CREATE TABLE seller_trust_scores (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    seller_id     INT NOT NULL UNIQUE,
    rating_score  DECIMAL(3,2) NOT NULL DEFAULT 0.00 CHECK (rating_score BETWEEN 0 AND 5),
    total_reviews INT NOT NULL DEFAULT 0 CHECK (total_reviews >= 0),
    trust_tier    VARCHAR(50) NOT NULL DEFAULT 'Bronze',
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE reviews (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    meetup_id   INT NOT NULL UNIQUE,            -- one review per completed meetup
    reviewer_id INT NOT NULL,                   -- the buyer
    seller_id   INT NOT NULL,                   -- the reviewed seller
    rating      TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment     VARCHAR(255),
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (meetup_id)   REFERENCES meetup_schedules(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewer_id) REFERENCES users(id)            ON DELETE CASCADE,
    FOREIGN KEY (seller_id)   REFERENCES users(id)            ON DELETE CASCADE
);

CREATE TABLE audit_logs (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NULL,
    action     VARCHAR(100) NOT NULL,
    details    TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);
