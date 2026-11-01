USE bentahub_db;


-- Member 2 (Ib-ib): Master Data Slice
-- Assigned JOIN Type: LEFT JOIN
-- Target Tables: safe_meetup_zones, meetup_schedules
-- Purpose: Display all safe meetup zones, including those with zero meetups


SELECT 
    z.id AS zone_id,
    z.zone_name,
    z.address,
    z.city,
    COUNT(m.id) AS total_scheduled_meetups
FROM safe_meetup_zones z
LEFT JOIN meetup_schedules m ON z.id = m.zone_id
GROUP BY z.id, z.zone_name, z.address, z.city
ORDER BY total_scheduled_meetups DESC;