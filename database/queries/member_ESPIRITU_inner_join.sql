USE bentahub_db;
-- ESPIRITU (Chats & Meetups) | INNER JOIN | chat_threads, listings, users, chat_messages, meetup_schedules, safe_meetup_zones
-- Purpose: chats that have a listing, a LAST message and a meetup (one row per meetup).
SELECT t.id AS thread_id, l.title AS listing_title, l.status AS listing_status,
       bu.full_name AS buyer, se.full_name AS seller,
       m.message_body AS last_message, s.scheduled_time, s.status AS meetup_status, z.zone_name
FROM chat_threads t
INNER JOIN listings l          ON t.listing_id = l.id
INNER JOIN users bu            ON bu.id = t.buyer_id
INNER JOIN users se            ON se.id = t.seller_id
INNER JOIN chat_messages m     ON m.id = (SELECT MAX(cm.id) FROM chat_messages cm WHERE cm.thread_id = t.id)
INNER JOIN meetup_schedules s  ON t.id = s.thread_id
INNER JOIN safe_meetup_zones z ON z.id = s.zone_id
ORDER BY s.scheduled_time ASC;
