const pool = require('../config/db');
const { HttpError } = require('../utils/respond');
const v = require('../utils/validate');
const audit = require('../utils/audit');

const THREADS = `SELECT t.id, t.listing_id, l.title AS listing, bu.full_name AS buyer, se.full_name AS seller,
       t.buyer_id, t.seller_id,
       (SELECT COUNT(*) FROM chat_messages cm WHERE cm.thread_id = t.id) AS messages
  FROM chat_threads t
  JOIN listings l ON l.id = t.listing_id
  JOIN users bu   ON bu.id = t.buyer_id
  JOIN users se   ON se.id = t.seller_id`;
const MESSAGES = `SELECT m.id, m.thread_id, m.sender_id, u.full_name AS sender, m.message_body, m.sent_at
  FROM chat_messages m JOIN users u ON u.id = m.sender_id`;
const isAdmin = (actor) => actor.roles.includes('Admin');

exports.listThreads = async () => (await pool.query(THREADS + ' ORDER BY t.id DESC'))[0];
exports.getThread = async (id) => (await pool.execute(THREADS + ' WHERE t.id = ?', [id]))[0][0] || null;

exports.createThread = async (b, actor) => {
  const listingId = v.id(b.listing_id, 'listing_id');
  const [[l]] = await pool.execute('SELECT seller_id FROM listings WHERE id = ?', [listingId]);
  if (!l) throw new HttpError(404, 'Listing not found.');
  // buyer = logged-in user. The MySQL trigger blocks a seller chatting on their own listing (409); UNIQUE blocks duplicates (409)
  const [r] = await pool.execute('INSERT INTO chat_threads (listing_id, buyer_id, seller_id) VALUES (?,?,?)', [listingId, actor.id, l.seller_id]);
  audit(actor.id, 'Started Chat', `Thread #${r.insertId}`);
  return exports.getThread(r.insertId);
};
exports.removeThread = async (id, actor) => {
  const t = await exports.getThread(id);
  if (!t) return 0;
  if (![t.buyer_id, t.seller_id].includes(actor.id) && !isAdmin(actor)) throw new HttpError(403, 'Only the buyer, the seller or an Admin can delete this chat.');
  const [r] = await pool.execute('DELETE FROM chat_threads WHERE id = ?', [id]);
  audit(actor.id, 'Deleted Chat', `Thread #${id}`);
  return r.affectedRows;
};

exports.listMessages = async (q = {}) => {
  if (q.thread_id) return (await pool.execute(MESSAGES + ' WHERE m.thread_id = ? ORDER BY m.id DESC', [v.id(q.thread_id, 'thread_id')]))[0];
  return (await pool.query(MESSAGES + ' ORDER BY m.id DESC LIMIT 200'))[0];
};
exports.getMessage = async (id) => (await pool.execute(MESSAGES + ' WHERE m.id = ?', [id]))[0][0] || null;

exports.createMessage = async (b, actor) => {
  const tid = v.id(b.thread_id, 'thread_id');
  const t = await exports.getThread(tid);
  if (!t) throw new HttpError(404, 'Chat not found.');
  if (![t.buyer_id, t.seller_id].includes(actor.id)) throw new HttpError(403, 'Only the buyer or the seller can write in this chat.');
  const [r] = await pool.execute('INSERT INTO chat_messages (thread_id, sender_id, message_body) VALUES (?,?,?)',
    [tid, actor.id, v.str(b.message_body, 'Message', 1, 1000)]);
  return exports.getMessage(r.insertId);
};
exports.updateMessage = async (id, b, actor) => {              // PATCH: only the body can change, only by its sender
  const msg = await exports.getMessage(id);
  if (!msg) return null;
  if (msg.sender_id !== actor.id) throw new HttpError(403, 'You can only edit your own messages.');
  await pool.execute('UPDATE chat_messages SET message_body = ? WHERE id = ?', [v.str(b.message_body, 'Message', 1, 1000), id]);
  return exports.getMessage(id);
};
exports.removeMessage = async (id, actor) => {
  const msg = await exports.getMessage(id);
  if (!msg) return 0;
  if (msg.sender_id !== actor.id && !isAdmin(actor)) throw new HttpError(403, 'You can only delete your own messages.');
  const [r] = await pool.execute('DELETE FROM chat_messages WHERE id = ?', [id]);
  return r.affectedRows;
};

// LEFT JOIN report: every thread, including threads with no messages yet (INNER JOIN would drop them)
exports.report = async () => (await pool.query(
  `SELECT t.id AS thread_id, l.title, bu.full_name AS buyer, COUNT(m.id) AS message_count
   FROM chat_threads t
   JOIN listings l ON l.id = t.listing_id
   JOIN users bu   ON bu.id = t.buyer_id
   LEFT JOIN chat_messages m ON m.thread_id = t.id
   GROUP BY t.id, l.title, bu.full_name
   ORDER BY message_count DESC, t.id`))[0];
