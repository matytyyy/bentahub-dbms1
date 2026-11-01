USE bentahub_db;
-- Member DINGLASAN: RIGHT JOIN Report (Categories & Active Price Drop Alerts)
SELECT 
    c.category_name,
    l.title AS item_title,
    l.price AS current_price,
    p.target_price
FROM price_drop_alerts p
RIGHT JOIN listings l ON p.listing_id = l.id
RIGHT JOIN categories c ON l.category_id = c.id
ORDER BY c.id ASC;