USE bentahub_db;

-- ===================================================
-- Member A (Core & Auth): INNER JOIN Report
-- Target Tables: users, user_roles, roles
-- Purpose: Retrieve verified sellers and their assigned system roles
-- ===================================================

SELECT 
    u.id AS user_id,
    u.full_name,
    u.email,
    u.phone_number,
    u.is_verified,
    r.role_name
FROM users u
INNER JOIN user_roles ur ON u.id = ur.user_id
INNER JOIN roles r ON ur.role_id = r.id
WHERE r.role_name = 'Seller' AND u.is_verified = 1
ORDER BY u.id ASC;