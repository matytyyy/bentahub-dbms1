USE bentahub_db;


-- Member 5 (DELVO): Trust & Audit Slice
-- Assigned JOIN Type: FULL OUTER JOIN (via UNION for MySQL)
-- Target Tables: users, seller_trust_scores, audit_logs
-- Purpose: Combine seller trust score records with system audit logs


SELECT 
    u.full_name,
    s.trust_tier,
    s.rating_score,
    'Trust Score Record' AS record_type
FROM users u
LEFT JOIN seller_trust_scores s ON u.id = s.seller_id

UNION

SELECT 
    u.full_name,
    a.action AS trust_tier,
    NULL AS rating_score,
    'Audit Log Record' AS record_type
FROM users u
RIGHT JOIN audit_logs a ON u.id = a.user_id;
