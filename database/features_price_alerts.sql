-- ============================================================================
-- BentaHub - Price alerts & listing audit   (owner: DINGLASAN)
-- Run order: schema.sql -> features_deal_workflow.sql -> features_trust_engine.sql
--            -> features_price_alerts.sql -> seed.sql
-- Logs listing status changes and price drops; view lists alerts whose target was reached.
-- ============================================================================
USE bentahub_db;
DROP VIEW    IF EXISTS v_triggered_price_alerts;
DROP TRIGGER IF EXISTS trg_listing_after_update;

DELIMITER $$

-- Audit + price-drop trail for listings (feeds DELVO's audit report)
CREATE TRIGGER trg_listing_after_update AFTER UPDATE ON listings
FOR EACH ROW
BEGIN
    IF NEW.status <> OLD.status THEN
        INSERT INTO audit_logs (user_id, action, details)
        VALUES (NEW.seller_id, 'Listing Status Changed',
                CONCAT('Listing #', NEW.id, ' (', NEW.title, '): ', OLD.status, ' -> ', NEW.status));
    END IF;
    IF NEW.price < OLD.price THEN
        INSERT INTO audit_logs (user_id, action, details)
        VALUES (NEW.seller_id, 'Price Dropped',
                CONCAT('Listing #', NEW.id, ': ', OLD.price, ' -> ', NEW.price, ' | alerts now hit: ',
                       (SELECT COUNT(*) FROM price_drop_alerts
                         WHERE listing_id = NEW.id AND target_price >= NEW.price)));
    END IF;
END$$

DELIMITER ;

-- DINGLASAN: alerts whose target price has been reached by an active listing
CREATE VIEW v_triggered_price_alerts AS
SELECT p.id AS alert_id, u.full_name AS watcher, l.id AS listing_id, l.title,
       l.price AS current_price, p.target_price,
       (p.target_price - l.price) AS below_target_by
  FROM price_drop_alerts p
  JOIN users u    ON u.id = p.user_id
  JOIN listings l ON l.id = p.listing_id
 WHERE l.price <= p.target_price AND l.status = 'active';
