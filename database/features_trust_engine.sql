-- ============================================================================
-- BentaHub - Trust engine   (owner: DELVO)
-- Run order: schema.sql -> features_deal_workflow.sql -> features_trust_engine.sql
--            -> features_price_alerts.sql -> seed.sql
-- Reviews unlock after completed meetups; every review recomputes the seller tier.
-- ============================================================================
USE bentahub_db;
DROP TRIGGER   IF EXISTS trg_review_before_insert;
DROP TRIGGER   IF EXISTS trg_review_before_update;
DROP TRIGGER   IF EXISTS trg_review_after_insert;
DROP TRIGGER   IF EXISTS trg_review_after_update;
DROP TRIGGER   IF EXISTS trg_review_after_delete;
DROP PROCEDURE IF EXISTS sp_recalc_trust;
DROP FUNCTION  IF EXISTS fn_trust_tier;

DELIMITER $$

-- ============================================================================
-- PART B - TRUST ENGINE (DELVO)
--   Tier rules (fn_trust_tier):  Gold   = 4+ reviews AND average >= 4.50
--                                Silver = 2+ reviews AND average >= 4.00
--                                Bronze = everyone else
--   EDGE CASE: a single 5-star review stays Bronze (too little evidence).
-- ============================================================================
CREATE FUNCTION fn_trust_tier(p_avg DECIMAL(3,2), p_count INT)
RETURNS VARCHAR(50) DETERMINISTIC NO SQL
BEGIN
    IF p_count >= 4 AND p_avg >= 4.50 THEN RETURN 'Gold'; END IF;
    IF p_count >= 2 AND p_avg >= 4.00 THEN RETURN 'Silver'; END IF;
    RETURN 'Bronze';
END$$

CREATE PROCEDURE sp_recalc_trust(IN p_seller_id INT)
BEGIN
    DECLARE v_avg DECIMAL(3,2);
    DECLARE v_cnt INT;
    DECLARE v_old VARCHAR(50);
    DECLARE v_new VARCHAR(50);
    SELECT COALESCE(ROUND(AVG(rating), 2), 0), COUNT(*) INTO v_avg, v_cnt
      FROM reviews WHERE seller_id = p_seller_id;
    SET v_old = (SELECT trust_tier FROM seller_trust_scores WHERE seller_id = p_seller_id);
    SET v_new = fn_trust_tier(v_avg, v_cnt);
    INSERT INTO seller_trust_scores (seller_id, rating_score, total_reviews, trust_tier)
    VALUES (p_seller_id, v_avg, v_cnt, v_new)
    ON DUPLICATE KEY UPDATE rating_score = v_avg, total_reviews = v_cnt, trust_tier = v_new;
    IF v_old IS NULL OR v_old <> v_new THEN
        INSERT INTO audit_logs (user_id, action, details)
        VALUES (p_seller_id, 'Trust Tier Changed',
                CONCAT(COALESCE(v_old, 'none'), ' -> ', v_new, ' (avg ', v_avg, ' from ', v_cnt, ' review(s))'));
    END IF;
END$$

CREATE TRIGGER trg_review_before_insert BEFORE INSERT ON reviews
FOR EACH ROW
BEGIN
    DECLARE v_status VARCHAR(20);
    DECLARE v_buyer INT;
    DECLARE v_seller INT;
    SELECT m.status, t.buyer_id, t.seller_id INTO v_status, v_buyer, v_seller
      FROM meetup_schedules m JOIN chat_threads t ON t.id = m.thread_id
     WHERE m.id = NEW.meetup_id;
    IF v_status IS NULL OR v_status <> 'completed' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Reviews are only allowed after the meetup is completed.';
    END IF;
    IF NEW.reviewer_id <> v_buyer THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only the buyer of this deal can review the seller.';
    END IF;
    IF NEW.seller_id <> v_seller THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The reviewed seller does not match this meetup.';
    END IF;
END$$

CREATE TRIGGER trg_review_before_update BEFORE UPDATE ON reviews
FOR EACH ROW
BEGIN
    IF NEW.meetup_id <> OLD.meetup_id OR NEW.seller_id <> OLD.seller_id
       OR NEW.reviewer_id <> OLD.reviewer_id THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only the rating and comment of a review can be edited.';
    END IF;
END$$

CREATE TRIGGER trg_review_after_insert AFTER INSERT ON reviews
FOR EACH ROW CALL sp_recalc_trust(NEW.seller_id)$$

CREATE TRIGGER trg_review_after_update AFTER UPDATE ON reviews
FOR EACH ROW CALL sp_recalc_trust(NEW.seller_id)$$

CREATE TRIGGER trg_review_after_delete AFTER DELETE ON reviews
FOR EACH ROW CALL sp_recalc_trust(OLD.seller_id)$$

DELIMITER ;
