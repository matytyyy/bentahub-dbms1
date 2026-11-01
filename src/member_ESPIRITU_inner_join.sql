USE bentahub_db;

-- Member 4 (Espiritu): Chats & Social Slice
-- Assigned JOIN Type: INNER JOIN
-- Target Tables: chat_threads, listings, chat_messages, meetup_schedules
-- Purpose: Retrieve active chat threads with listing info and confirmed meetups

SELECT 
    t.id AS thread_id,
    l.title AS listing_title,
    m.message_body AS last_message,
    s.scheduled_time,
    s.status AS meetup_status
FROM chat_threads t
INNER JOIN listings l ON t.listing_id = l.id
INNER JOIN chat_messages m ON t.id = m.thread_id
INNER JOIN meetup_schedules s ON t.id = s.thread_id
ORDER BY s.scheduled_time ASC;
