const pool = require('../config/db');
const { HttpError } = require('../utils/respond');
const v = require('../utils/validate');
const audit = require('../utils/audit');

const STATUS = ['pending', 'confirmed', 'completed', 'cancelled'];
// The row also returns listing_status, so the response shows what the MySQL trigger just did to the listing
const SELECT = `SELECT s.id, s.thread_id, l.title AS listing, l.status AS listing_status,
       z.zone_name, s.zone_id, s.scheduled_time, s.status
  FROM meetup_schedules s
  JOIN chat_threads t        ON t.id = s.thread_id
  JOIN listings l            ON l.id = t.listing_id
  JOIN safe_meetup_zones z   ON z.id = s.zone_id`;

function when(x) {
  const s = String(x || '').trim().replace('T', ' ');
  if (!/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/.test(s)) throw new HttpError(400, 'scheduled_time must look like 2026-11-20 14:00:00.');
  return s.length === 16 ? s + ':00' : s;
}
const clean = (b) => [v.id(b.thread_id, 'thread_id'), v.id(b.zone_id, 'zone_id'), when(b.scheduled_time), v.oneOf(b.status || 'pending', STATUS, 'Status')];

exports.list = async () => (await pool.query(SELECT + ' ORDER BY s.scheduled_time DESC'))[0];
exports.get = async (id) => (await pool.execute(SELECT + ' WHERE s.id = ?', [id]))[0][0] || null;

// Business rules (one buyer per item, status -> listing status) live in MySQL triggers; a blocked write returns 409.
exports.create = async (b, actor) => {
  const [r] = await pool.execute('INSERT INTO meetup_schedules (thread_id, zone_id, scheduled_time, status) VALUES (?,?,?,?)', clean(b));
  audit(actor.id, 'Created Meetup', `Meetup #${r.insertId}`);
  return exports.get(r.insertId);
};
exports.update = async (id, b, actor) => {
  const [r] = await pool.execute('UPDATE meetup_schedules SET thread_id=?, zone_id=?, scheduled_time=?, status=? WHERE id=?', [...clean(b), id]);
  if (!r.affectedRows) return null;
  audit(actor.id, 'Updated Meetup', `Meetup #${id}`);
  return exports.get(id);
};
exports.setStatus = async (id, status, actor) => {            // PATCH = partial update (status only)
  const [r] = await pool.execute('UPDATE meetup_schedules SET status=? WHERE id=?', [v.oneOf(status, STATUS, 'Status'), id]);
  if (!r.affectedRows) return null;
  audit(actor.id, 'Meetup Status', `Meetup #${id} -> ${status}`);
  return exports.get(id);
};
exports.remove = async (id, actor) => {
  const [r] = await pool.execute('DELETE FROM meetup_schedules WHERE id = ?', [id]);
  if (r.affectedRows) audit(actor.id, 'Deleted Meetup', `Meetup #${id}`);
  return r.affectedRows;
};

// INNER JOIN report: only chats that really led to a meetup (and have a message)
exports.report = async () => (await pool.query(
  `SELECT t.id AS thread_id, l.title AS listing_title, l.status AS listing_status,
          bu.full_name AS buyer, se.full_name AS seller, m.message_body AS last_message,
          s.scheduled_time, s.status AS meetup_status, z.zone_name
   FROM chat_threads t
   INNER JOIN listings l          ON t.listing_id = l.id
   INNER JOIN users bu            ON bu.id = t.buyer_id
   INNER JOIN users se            ON se.id = t.seller_id
   INNER JOIN chat_messages m     ON m.id = (SELECT MAX(cm.id) FROM chat_messages cm WHERE cm.thread_id = t.id)
   INNER JOIN meetup_schedules s  ON t.id = s.thread_id
   INNER JOIN safe_meetup_zones z ON z.id = s.zone_id
   ORDER BY s.scheduled_time ASC`))[0];
