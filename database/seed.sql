USE bentahub_db;

-- 1. Insert System Roles
INSERT INTO roles (id, role_name, description) VALUES
(1, 'Admin', 'System administrator with full system access'),
(2, 'Seller', 'Marketplace seller authorized to list items'),
(3, 'Buyer', 'Marketplace buyer allowed to purchase and send messages');

-- 2. Insert Initial Users
INSERT INTO users (id, full_name, email, password_hash, phone_number, is_verified) VALUES
(1, 'Juan Dela Cruz', 'juan@example.ph', 'hash_juan_123', '09171112222', 1),
(2, 'Maria Santos', 'maria@example.ph', 'hash_maria_123', '09183334444', 1),
(3, 'Pedro Penduko', 'pedro@example.ph', 'hash_pedro_123', '09195556666', 0),
(4, 'Ana Reyes', 'ana@example.ph', 'hash_ana_123', '09207778888', 1),
(5, 'Mark Ramos', 'mark@example.ph', 'hash_mark_123', '09219990000', 0);

-- 3. Map Users to Roles
INSERT INTO user_roles (user_id, role_id) VALUES
(1, 1), (1, 2),
(2, 2),
(3, 3),
(4, 2), (4, 3),
(5, 3);

-- 4. Categories
INSERT INTO categories (id, category_name, description) VALUES
(1, 'Electronics', 'Gadgets and hardware'),
(2, 'Furniture', 'Home items and furniture'),
(3, 'Vehicles', 'Motorcycles and cars');

-- 5. Item Conditions
INSERT INTO item_conditions (id, condition_name, description) VALUES
(1, 'Brand New', 'Unopened item'),
(2, 'Like New', 'Used lightly without signs of wear'),
(3, 'Well Used', 'Functional with obvious wear');

-- 6. Safe Meetup Zones
INSERT INTO safe_meetup_zones (id, zone_name, address, city, is_geofenced) VALUES
(1, 'Bacolod Police Station 1', 'Araneta St', 'Bacolod City', 1),
(2, 'SM City Bacolod Lobby', 'Rizal St', 'Bacolod City', 1),
(3, 'Ayala Malls Capitol Central', 'Gatuslao St', 'Bacolod City', 1);

-- 7. Listings
INSERT INTO listings (id, seller_id, category_id, condition_id, title, description, price, status) VALUES
(1, 1, 1, 2, 'iPhone 13 128GB', 'Slightly used iPhone 13 in excellent condition.', 25000.00, 'active'),
(2, 2, 2, 1, 'Ergonomic Office Chair', 'Brand new chair with lumbar support.', 4500.00, 'active'),
(3, 4, 1, 3, 'Mechanical Keyboard', 'Custom RGB keyboard with blue switches.', 1800.00, 'reserved');

-- 8. Listing Images
INSERT INTO listing_images (id, listing_id, image_url, is_primary) VALUES
(1, 1, 'https://storage.bentahub.ph/iphone13_front.jpg', 1),
(2, 2, 'https://storage.bentahub.ph/chair_1.jpg', 1),
(3, 3, 'https://storage.bentahub.ph/keyboard.jpg', 1);

-- 9. Price Drop Alerts
INSERT INTO price_drop_alerts (id, user_id, listing_id, target_price) VALUES
(1, 3, 1, 23000.00),
(2, 5, 2, 4000.00);

-- 10. Chat Threads
INSERT INTO chat_threads (id, listing_id, buyer_id, seller_id) VALUES
(1, 1, 3, 1),
(2, 2, 5, 2);

-- 11. Chat Messages
INSERT INTO chat_messages (id, thread_id, sender_id, message_body) VALUES
(1, 1, 3, 'Hi Juan, is the iPhone 13 still available?'),
(2, 1, 1, 'Yes Pedro, it is available. Can meet at SM Bacolod.'),
(3, 2, 5, 'Is the chair price negotiable?');

-- 12. Meetup Schedules
INSERT INTO meetup_schedules (id, thread_id, zone_id, scheduled_time, status) VALUES
(1, 1, 2, '2026-11-05 14:00:00', 'confirmed'),
(2, 2, 1, '2026-11-06 10:00:00', 'pending');

-- 13. Seller Trust Scores
INSERT INTO seller_trust_scores (id, seller_id, rating_score, total_reviews, trust_tier) VALUES
(1, 1, 4.95, 24, 'Gold'),
(2, 2, 4.80, 10, 'Silver'),
(3, 4, 5.00, 3, 'Bronze');

-- 14. Audit Logs
INSERT INTO audit_logs (id, user_id, action, details) VALUES
(1, 1, 'USER_LOGIN', 'Juan logged in successfully'),
(2, 1, 'CREATE_LISTING', 'Created listing ID 1: iPhone 13 128GB'),
(3, 2, 'CREATE_LISTING', 'Created listing ID 2: Ergonomic Office Chair');