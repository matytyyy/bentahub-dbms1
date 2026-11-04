-- ============================================================================
-- BentaHub - seed.sql  (run LAST, after schema.sql and the three features_*.sql files)
-- Demo login password for every seeded user: password123
-- The triggers from the features_*.sql files fire while this file loads, so the trust
-- scores, listing statuses and audit entries below are COMPUTED, not typed.
-- ============================================================================
SET NAMES utf8mb4;
USE bentahub_db;

SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE audit_logs;
TRUNCATE TABLE reviews;
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

-- IJAN ------------------------------------------------------------------------
INSERT INTO roles (id, role_name, description) VALUES
(1, 'Admin',  'System Administrator with full management permissions'),
(2, 'Seller', 'Verified user capable of posting marketplace listings'),
(3, 'Buyer',  'Standard user capable of browsing and purchasing items');

INSERT INTO users (id, full_name, email, password_hash, phone_number, is_verified) VALUES
(1, 'Matt D. Ijan',      'matt.ijan@bentahub.ph',    '$2y$10$jF3mV8RuToiCYEVl18ag0uCKlUrsra4y.XoGbt9Qjehkbvq5ZvmqW', '09171234567', 1),
(2, 'Member Ib-Ib',      'ibib@bentahub.ph',         '$2y$10$jF3mV8RuToiCYEVl18ag0uCKlUrsra4y.XoGbt9Qjehkbvq5ZvmqW', '09181234568', 1),
(3, 'Member Dinglasan',  'dinglasan@bentahub.ph',    '$2y$10$jF3mV8RuToiCYEVl18ag0uCKlUrsra4y.XoGbt9Qjehkbvq5ZvmqW', '09191234569', 1),
(4, 'Member Espiritu',   'espiritu@bentahub.ph',     '$2y$10$jF3mV8RuToiCYEVl18ag0uCKlUrsra4y.XoGbt9Qjehkbvq5ZvmqW', '09201234570', 1),
(5, 'Sigrid Delvo',      'sigrid.delvo@bentahub.ph', '$2y$10$jF3mV8RuToiCYEVl18ag0uCKlUrsra4y.XoGbt9Qjehkbvq5ZvmqW', '09211234571', 1);

INSERT INTO user_roles (user_id, role_id) VALUES
(1, 1), (1, 2), (2, 2), (3, 3), (4, 3), (5, 2);

-- IB-IB -----------------------------------------------------------------------
INSERT INTO categories (id, category_name, description) VALUES
(1, 'Electronics & Gadgets', 'Smartphones, laptops, accessories, and audio gear'),
(2, 'Computer Hardware',     'PC components, monitors, and peripherals'),
(3, 'Textbooks & Books',     'College textbooks, study materials, and fiction'),
(4, 'Fashion & Apparel',     'Clothing, footwear, bags, and accessories'),
(5, 'Home & Dorm Goods',     'Furniture, appliances, and room essentials');

INSERT INTO item_conditions (id, condition_name, description) VALUES
(1, 'Brand New',   'Unopened in original factory packaging'),
(2, 'Like New',    'Used briefly with zero physical blemishes or scratches'),
(3, 'Used - Good', 'Minor cosmetic wear but fully functional'),
(4, 'Used - Fair', 'Visible signs of wear but works as expected');

INSERT INTO safe_meetup_zones (id, zone_name, address, city, is_geofenced) VALUES
(1, 'Dumaguete City Police Station',  'Locsin St, Poblacion',          'Dumaguete City', 1),
(2, 'NORSU Campus Security Office',   'Kagawasan Ave, Main Campus 1',  'Dumaguete City', 1),
(3, 'Robinsons Place Main Entrance',  'Real St, Barangay Calindagan',  'Dumaguete City', 1),
(4, 'Silliman Hall Lobby',            'Hibbard Ave, Silliman Campus',  'Dumaguete City', 1);

-- DINGLASAN -------------------------------------------------------------------
-- 1-4 are open listings; 5-11 are finished deals (sold) that feed the trust engine
INSERT INTO listings (id, seller_id, category_id, condition_id, title, description, price, status) VALUES
(1, 1, 1, 2, 'Dynabook Satellite Pro S73 Laptop',   'Core i3 11th Gen, 16GB DDR4 RAM, 256GB NVMe SSD. Perfect for IT students.', 18500.00, 'active'),
(2, 2, 2, 1, 'Logitech G304 Wireless Mouse',        'Brand new sealed in box. Black color.', 1450.00, 'active'),
(3, 1, 3, 3, 'Database System Concepts 7th Edition','Used for DBMS 1 course. Good condition with minimal highlights.', 850.00, 'active'),
(4, 5, 1, 2, 'Mechanical Keyboard RGB (Blue Switch)','Hot-swappable tactile keyboard in clean condition.', 1200.00, 'active'),
(5, 1, 2, 3, 'Arduino Uno Starter Kit',              'Board, breadboard, jumper wires, LEDs.', 650.00, 'sold'),
(6, 1, 3, 3, 'IT Fundamentals Reviewer Notes',       'Printed and bound reviewer, 120 pages.', 150.00, 'sold'),
(7, 1, 1, 2, 'USB-C Hub 7-in-1',                     'HDMI, SD reader, 3x USB-A.', 900.00, 'sold'),
(8, 1, 1, 3, 'Scientific Calculator fx-991',         'Works perfectly, new batteries.', 600.00, 'sold'),
(9, 2, 5, 2, 'Dorm Desk Lamp',                       'Clip-on LED lamp, 3 brightness levels.', 300.00, 'sold'),
(10,2, 1, 3, 'Gaming Headset',                       'Over-ear, detachable mic.', 700.00, 'sold'),
(11,5, 3, 3, 'Calculus Textbook',                    'Early Transcendentals, light highlights.', 400.00, 'sold');

INSERT INTO listing_images (listing_id, image_url, is_primary) VALUES
(1, 'images/dynabook.jpg', 1), (2, 'images/g304.jpg', 1),
(3, 'images/dbms_book.jpg', 1), (4, 'images/keyboard.jpg', 1),
(4, 'images/keyboard_side.jpg', 0);

INSERT INTO price_drop_alerts (user_id, listing_id, target_price) VALUES
(3, 1, 17000.00),
(4, 2, 1200.00),
(3, 4, 1000.00),
(4, 4, 1250.00);   -- already reached: listing 4 costs 1200 <= 1250

-- ESPIRITU --------------------------------------------------------------------
INSERT INTO chat_threads (id, listing_id, buyer_id, seller_id) VALUES
(1, 1, 3, 1), (2, 2, 4, 2), (3, 4, 3, 5),
(4, 5, 3, 1), (5, 6, 4, 1), (6, 7, 3, 1), (7, 8, 4, 1),
(8, 9, 3, 2), (9, 10, 4, 2), (10, 11, 4, 5);

INSERT INTO chat_messages (thread_id, sender_id, message_body) VALUES
(1, 3, 'Hi Matt, is the Dynabook still available?'),
(1, 1, 'Yes it is! Available for meetup at NORSU safe zone.'),
(2, 4, 'Is the mouse really sealed?'),
(3, 3, 'Hello Sigrid, can you do ₱1,000 for the keyboard?'),
(3, 5, 'Sure! Let us schedule a meetup at the Police Station.'),
(4, 3, 'Deal on the Arduino kit, see you Monday.'),
(5, 4, 'Thanks for the reviewer notes!'),
(6, 3, 'USB-C hub received, works great.'),
(7, 4, 'Calculator confirmed, thanks.'),
(8, 3, 'Lamp pickup done.'),
(9, 4, 'Headset is perfect.'),
(10, 4, 'Textbook in good shape, thanks Sigrid.');

INSERT INTO meetup_schedules (id, thread_id, zone_id, scheduled_time, status) VALUES
(1, 1, 2, '2026-11-05 14:00:00', 'confirmed'),   -- reserves listing 1 (trigger)
(2, 3, 1, '2026-11-06 10:30:00', 'pending'),
(3, 4, 2, '2026-10-12 15:00:00', 'completed'),
(4, 5, 4, '2026-10-14 13:00:00', 'completed'),
(5, 6, 3, '2026-10-17 16:30:00', 'completed'),
(6, 7, 2, '2026-10-20 11:00:00', 'completed'),
(7, 8, 1, '2026-10-22 09:30:00', 'completed'),
(8, 9, 4, '2026-10-24 14:15:00', 'completed'),
(9, 10, 1, '2026-10-26 10:00:00', 'completed');

-- DELVO -----------------------------------------------------------------------
-- Matt: 4 reviews avg 4.75 -> Gold | Ib-Ib: 2 reviews avg 4.00 -> Silver
-- Sigrid: ONE 5-star review -> stays Bronze (edge case: too little evidence)
INSERT INTO reviews (meetup_id, reviewer_id, seller_id, rating, comment) VALUES
(3, 3, 1, 5, 'Fast and honest, kit was complete.'),
(4, 4, 1, 5, 'Neat notes, exactly as described.'),
(5, 3, 1, 4, 'Good hub, arrived a bit late.'),
(6, 4, 1, 5, 'Calculator like new.'),
(7, 3, 2, 4, 'Lamp works, small scratch.'),
(8, 4, 2, 4, 'Headset fine, mic a bit quiet.'),
(9, 4, 5, 5, 'Great textbook, quick meetup.');

INSERT INTO audit_logs (user_id, action, details) VALUES
(1, 'Verified Phone Number', 'Completed SMS OTP verification'),
(2, 'Created Listing',       'Posted listing #2: Logitech G304 Wireless Mouse'),
(3, 'Set Price Alert',       'Created price alert for Dynabook Laptop at 17000.00');
-- Deliberately NO audit entry for user 4: it shows up as "score/log only" rows
-- in the FULL OUTER JOIN report.
