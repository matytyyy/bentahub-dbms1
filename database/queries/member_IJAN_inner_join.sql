USE bentahub_db;
-- IJAN (Core & Auth) | INNER JOIN | users, user_roles, roles
-- Purpose: active, verified sellers with their role. Users with no matching role row are dropped.
SELECT u.id AS user_id, u.full_name, u.email, u.phone_number, u.is_verified, r.role_name
FROM users u
INNER JOIN user_roles ur ON u.id = ur.user_id
INNER JOIN roles r       ON ur.role_id = r.id
WHERE r.role_name = 'Seller' AND u.is_verified = 1
ORDER BY u.id ASC;
