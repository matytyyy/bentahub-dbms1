USE bentahub_db;
-- DELVO (Trust & Audit) | FULL OUTER JOIN (LEFT JOIN UNION RIGHT JOIN) | seller_trust_scores, audit_logs, users
-- Purpose: all trust scores and all audit rows, matched by user; rows present on only one side are flagged.
SELECT x.user_id, u.full_name, x.trust_tier, x.rating_score, x.action, x.logged_at,
       CASE WHEN x.rating_score IS NULL THEN 'Audit log only (no trust score)'
            WHEN x.action IS NULL       THEN 'Trust score only (no audit activity)'
            ELSE 'Matched' END AS match_type
FROM (
    SELECT s.seller_id AS user_id, s.trust_tier, s.rating_score, a.action, a.created_at AS logged_at
    FROM seller_trust_scores s
    LEFT JOIN audit_logs a  ON a.user_id = s.seller_id
  UNION
    SELECT a.user_id, s.trust_tier, s.rating_score, a.action, a.created_at
    FROM seller_trust_scores s
    RIGHT JOIN audit_logs a ON a.user_id = s.seller_id
) x
LEFT JOIN users u ON u.id = x.user_id
ORDER BY u.full_name, x.logged_at;
