-- ============================================================================
-- BentaHub - Deal workflow   (owner: ESPIRITU)
-- Run order: schema.sql -> features_deal_workflow.sql -> features_trust_engine.sql
--            -> features_price_alerts.sql -> seed.sql
-- A meetup status automatically drives the listing status and blocks double-selling.
-- ============================================================================
USE bentahub_db;
DROP TRIGGER   IF EXISTS trg_thread_before_insert;
DROP TRIGGER   IF EXISTS trg_meetup_before_insert;
DROP TRIGGER   IF EXISTS trg_meetup_before_update;
DROP TRIGGER   IF EXISTS trg_meetup_after_insert;
DROP TRIGGER   IF EXISTS trg_meetup_after_update;
DROP TRIGGER   IF EXISTS trg_meetup_after_delete;
DROP PROCEDURE IF EXISTS sp_assert_listing_free;
DROP PROCEDURE IF EXISTS sp_sync_listing_status;

DELIMITER $$

-- ============================================================================
-- PART A - DEAL WORKFLOW (ESPIRITU)
--   confirmed meetup -> listing 'reserved'   | completed -> 'sold'
--   cancelled / deleted -> back to 'active'  | 'archived' is never touched
--   EDGE CASE: a 2nd buyer cannot confirm a meetup for an already reserved item
-- ============================================================================
CREATE PROCEDURE sp_sync_listing_status(IN p_thread_id INT)
BEGIN
    DECLARE v_listing INT;
    DECLARE v_new VARCHAR(20);
    SELECT listing_id INTO v_listing FROM chat_threads WHERE id = p_thread_id;
    IF v_listing IS NOT NULL THEN
        SELECT CASE WHEN SUM(m.status = 'completed') > 0 THEN 'sold'
                    WHEN SUM(m.status = 'confirmed') > 0 THEN 'reserved'
                    ELSE 'active' END
          INTO v_new
          FROM chat_threads t
          LEFT JOIN meetup_schedules m ON m.thread_id = t.id
         WHERE t.listing_id = v_listing;
        UPDATE listings SET status = v_new
         WHERE id = v_listing AND status <> 'archived' AND status <> v_new;
    END IF;
END$$

CREATE PROCEDURE sp_assert_listing_free(IN p_thread_id INT, IN p_status VARCHAR(20))
BEGIN
    DECLARE v_clash INT DEFAULT 0;
    IF p_status IN ('confirmed','completed') THEN
        SELECT COUNT(*) INTO v_clash
          FROM chat_threads me
          JOIN chat_threads other ON other.listing_id = me.listing_id AND other.id <> me.id
          JOIN meetup_schedules m ON m.thread_id = other.id
         WHERE me.id = p_thread_id AND m.status IN ('confirmed','completed');
        IF v_clash > 0 THEN
            SIGNAL SQLSTATE '45000'
               SET MESSAGE_TEXT = 'This item is already reserved or sold to another buyer.';
        END IF;
    END IF;
END$$

CREATE TRIGGER trg_thread_before_insert BEFORE INSERT ON chat_threads
FOR EACH ROW
BEGIN
    DECLARE v_owner INT;
    IF NEW.buyer_id = NEW.seller_id THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'A seller cannot start a chat on their own listing.';
    END IF;
    SELECT seller_id INTO v_owner FROM listings WHERE id = NEW.listing_id;
    IF v_owner IS NULL OR v_owner <> NEW.seller_id THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The thread seller must be the owner of the listing.';
    END IF;
END$$

CREATE TRIGGER trg_meetup_before_insert BEFORE INSERT ON meetup_schedules
FOR EACH ROW CALL sp_assert_listing_free(NEW.thread_id, NEW.status)$$

CREATE TRIGGER trg_meetup_before_update BEFORE UPDATE ON meetup_schedules
FOR EACH ROW CALL sp_assert_listing_free(NEW.thread_id, NEW.status)$$

CREATE TRIGGER trg_meetup_after_insert AFTER INSERT ON meetup_schedules
FOR EACH ROW CALL sp_sync_listing_status(NEW.thread_id)$$

CREATE TRIGGER trg_meetup_after_update AFTER UPDATE ON meetup_schedules
FOR EACH ROW
BEGIN
    CALL sp_sync_listing_status(NEW.thread_id);
    IF OLD.thread_id <> NEW.thread_id THEN
        CALL sp_sync_listing_status(OLD.thread_id);
    END IF;
END$$

CREATE TRIGGER trg_meetup_after_delete AFTER DELETE ON meetup_schedules
FOR EACH ROW CALL sp_sync_listing_status(OLD.thread_id)$$

DELIMITER ;
