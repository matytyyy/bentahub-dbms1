USE bentahub_db;

SET FOREIGN_KEY_CHECKS = 0;

-- Clear existing data if re-running seed
TRUNCATE TABLE audit_logs;
TRUNCATE TABLE seller_trust_scores;
TRUNCATE TABLE meetup_schedules;
TRUNCATE TABLE chat_messages;
TRUNCATE TABLE chat_threads;
TRUNCATE TABLE price_drop_alerts;
TRUNCATE TABLE listing_images;
TRUNCATE TABLE listings;
TRUNCATE TABLE safe_meetup_zones;
TRUNCATE TABLE item_conditions;
TRUNCATE TABLE categories;
TRUNCATE TABLE user_roles;
TRUNCATE TABLE roles;
TRUNCATE TABLE users;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- MEMBER 1: IJAN (Core Authentication, Users & Roles Slice)
-- ============================================================================

-- 1. Roles
INSERT INTO roles (id, role_name, description) VALUES
(1, 'Admin', 'System Administrator with full management permissions'),
(2, 'Seller', 'Verified user capable of posting marketplace listings'),
(3, 'Buyer', 'Standard user capable of browsing and purchasing items');

-- 2. Users (Default password: password123 hashed via bcrypt)
INSERT INTO users (id, full_name, email, password_hash, phone_number, is_verified) VALUES
(1, 'Matt D. Ijan', 'matt.ijan@bentahub.ph', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe1T9mR.zKj7K/v1d.lT3B1B1B1B1B1B1', '09171234567', 1),
(2, 'Member Ib-Ib', 'ibib@bentahub.ph', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe1T9mR.zKj7K/v1d.lT3B1B1B1B1B1B1', '09181234568', 1),
(3, 'Member Dinglasan', 'dinglasan@bentahub.ph', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe1T9mR.zKj7K/v1d.lT3B1B1B1B1B1B1', '09191234569', 1),
(4, 'Member Espiritu', 'espiritu@bentahub.ph', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe1T9mR.zKj7K/v1d.lT3B1B1B1B1B1B1', '09201234570', 1),
(5, 'Sigrid Delvo', 'sigrid.delvo@bentahub.ph', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe1T9mR.zKj7K/v1d.lT3B1B1B1B1B1B1', '09211234571', 1);

-- 3. User Roles Mapping
INSERT INTO user_roles (user_id, role_id) VALUES
(1, 1), -- Matt: Admin
(1, 2), -- Matt: Seller
(2, 2), -- Ib-Ib: Seller
(3, 3), -- Dinglasan: Buyer
(4, 3), -- Espiritu: Buyer
(5, 2); -- Sigrid Delvo: Seller

-- ============================================================================
-- MEMBER 3: DINGLASAN (Categories & Catalog Slice)
-- ============================================================================

-- 4. Categories
INSERT INTO categories (id, category_name, description) VALUES
(1, 'Electronics & Gadgets', 'Smartphones, laptops, accessories, and audio gear'),
(2, 'Computer Hardware', 'PC components, monitors, and peripherals'),
(3, 'Textbooks & Books', 'College textbooks, study materials, and fiction'),
(4, 'Fashion & Apparel', 'Clothing, footwear, bags, and accessories'),
(5, 'Home & Dorm Goods', 'Furniture, appliances, and room essentials');

-- ============================================================================
-- MEMBER 2: IB-IB (Safe Zones, Item Conditions & Product Listings Slice)
-- ============================================================================

-- 5. Item Conditions
INSERT INTO item_conditions (id, condition_name, description) VALUES
(1, 'Brand New', 'Unopened in original factory packaging'),
(2, 'Like New', 'Used briefly with zero physical blemishes or scratches'),
(3, 'Used - Good', 'Minor cosmetic wear but fully functional'),
(4, 'Used - Fair', 'Visible signs of wear but works as expected');

-- 6. Safe Meetup Zones
INSERT INTO safe_meetup_zones (id, zone_name, address, city, is_geofenced) VALUES
(1, 'Dumaguete City Police Station', 'Locsin St, Poblacion', 'Dumaguete City', 1),
(2, 'NORSU Campus Security Office', 'Kagawasan Ave, Main Campus 1', 'Dumaguete City', 1),
(3, 'Robinsons Place Main Entrance', 'Real St, Barangay Calindagan', 'Dumaguete City', 1);

-- 7. Listings
INSERT INTO listings (id, seller_id, category_id, condition_id, title, description, price, status) VALUES
(1, 1, 1, 2, 'Dynabook Satellite Pro S73 Laptop', 'Core i3 11th Gen, 16GB DDR4 RAM, 256GB NVMe SSD. Perfect for IT students.', 18500.00, 'active'),
(2, 2, 2, 1, 'Logitech G304 Wireless Mouse', 'Brand new sealed in box. Black color.', 1450.00, 'active'),
(3, 1, 3, 3, 'Database System Concepts 7th Edition', 'Used for DBMS 1 course. Good condition with minimal highlights.', 850.00, 'reserved'),
(4, 5, 1, 2, 'Mechanical Keyboard RGB (Blue Switch)', 'Hot-swappable tactile keyboard in clean condition.', 1200.00, 'active');

-- 8. Listing Images
INSERT INTO listing_images (id, listing_id, image_url, is_primary) VALUES
(1, 1, 'images/dynabook.jpg', 1),
(2, 2, 'images/g304.jpg', 1),
(3, 3, 'images/dbms_book.jpg', 1),
(4, 4, 'images/keyboard.jpg', 1);

-- ============================================================================
-- MEMBER 3: DINGLASAN (Price Drop Alerts Module)
-- ============================================================================

-- 9. Price Drop Alerts
INSERT INTO price_drop_alerts (id, user_id, listing_id, target_price) VALUES
(1, 3, 1, 17000.00),
(2, 4, 2, 1200.00),
(3, 3, 4, 1000.00);

-- ============================================================================
-- MEMBER 4: ESPIRITU (Chat Threads, Messages & Meetup Schedules Slice)
-- ============================================================================

-- 10. Chat Threads
INSERT INTO chat_threads (id, listing_id, buyer_id, seller_id) VALUES
(1, 1, 3, 1), -- Dinglasan messaging Matt about Dynabook
(2, 2, 4, 2), -- Espiritu messaging Ib-Ib about Mouse
(3, 4, 3, 5); -- Dinglasan messaging Sigrid Delvo about Keyboard

-- 11. Chat Messages
INSERT INTO chat_messages (id, thread_id, sender_id, message_body) VALUES
(1, 1, 3, 'Hi Matt, is the Dynabook still available?'),
(2, 1, 1, 'Yes it is! Available for meetup at NORSU safe zone.'),
(3, 3, 3, 'Hello Sigrid, can you do ₱1,000 for the keyboard?'),
(4, 3, 5, 'Sure! Let us schedule a meetup at Police Station Precinct 1.');

-- 12. Meetup Schedules
INSERT INTO meetup_schedules (id, thread_id, zone_id, scheduled_time, status) VALUES
(1, 1, 2, '2026-11-05 14:00:00', 'confirmed'),
(2, 3, 1, '2026-11-06 10:30:00', 'pending');

-- ============================================================================
-- MEMBER 5: DELVO / SIGRID (Seller Trust Scores & System Audit Logs Slice)
-- ============================================================================

-- 13. Seller Trust Scores
INSERT INTO seller_trust_scores (id, seller_id, rating_score, total_reviews, trust_tier) VALUES
(1, 1, 4.95, 18, 'Gold'),
(2, 2, 4.80, 12, 'Silver'),
(3, 5, 5.00, 25, 'Platinum');

-- 14. Audit Logs
INSERT INTO audit_logs (id, user_id, action, details) VALUES
(1, 1, 'Verified Phone Number', 'Completed SMS OTP verification'),
(2, 2, 'Created Listing', 'Posted listing #2: Logitech G304 Wireless Mouse'),
(3, 5, 'Completed Safe Meetup', 'Successfully completed transaction at Police Station Zone'),
(4, 3, 'Set Price Alert', 'Created price alert for Dynabook Laptop at ₱17,000.00');