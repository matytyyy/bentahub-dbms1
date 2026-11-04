USE bentahub_db;
-- IB-IB (Master Data) | LEFT JOIN | safe_meetup_zones, meetup_schedules
-- Purpose: every safe zone with its meetup count, including zones with zero meetups.
SELECT z.id AS zone_id, z.zone_name, z.address, z.city,
       COUNT(m.id) AS total_scheduled_meetups,
       MAX(m.scheduled_time) AS latest_meetup
FROM safe_meetup_zones z
LEFT JOIN meetup_schedules m ON z.id = m.zone_id
GROUP BY z.id, z.zone_name, z.address, z.city
ORDER BY total_scheduled_meetups DESC, z.zone_name;
