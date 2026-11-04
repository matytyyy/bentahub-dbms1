USE bentahub_db;
-- DINGLASAN (Item Catalog) | RIGHT JOIN | price_drop_alerts, listings, categories
-- Purpose: every category (right-most table) with its items and alerts; empty categories still appear.
SELECT c.category_name, l.title AS item_title, l.price AS current_price,
       p.target_price, u.full_name AS watcher
FROM price_drop_alerts p
RIGHT JOIN listings l   ON p.listing_id = l.id
RIGHT JOIN categories c ON l.category_id = c.id
LEFT JOIN  users u      ON u.id = p.user_id
ORDER BY c.category_name, l.title;
