const pool = require('../config/db');
const { HttpError } = require('../utils/respond');
const v = require('../utils/validate');
const audit = require('../utils/audit');

const SELECT = `SELECT r.id, r.meetup_id, l.title AS listing, r.reviewer_id, bu.full_name AS reviewer,
       r.seller_id, se.full_name AS seller, r.rating, r.comment, r.created_at
  FROM reviews r
  JOIN users bu           ON bu.id = r.reviewer_id
  JOIN users se           ON se.id = r.seller_id
  JOIN meetup_schedules s ON s.id = r.meetup_id
  JOIN chat_threads t     ON t.id = s.thread_id
  JOIN listings l         ON l.id = t.listing_id`;
const trustOf = async (sellerId) =>
  (await pool.execute('SELECT rating_score, total_reviews, trust_tier FROM seller_trust_scores WHERE seller_id = ?', [sellerId]))[0][0] || null;
// Only the reviewer or an Admin may change a review
async function assertOwner(id, actor) {
  const row = await exports.get(id);
  if (!row) return null;
  if (row.reviewer_id !== actor.id && !actor.roles.includes('Admin')) throw new HttpError(403, 'Only the reviewer or an Admin can change this review.');
  return row;
}

exports.list = async () => (await pool.query(SELECT + ' ORDER BY r.id DESC'))[0];
exports.get = async (id) => (await pool.execute(SELECT + ' WHERE r.id = ?', [id]))[0][0] || null;

exports.create = async (b, actor) => {
  const meetupId = v.id(b.meetup_id, 'meetup_id');
  const [[deal]] = await pool.execute(
    'SELECT t.seller_id FROM meetup_schedules m JOIN chat_threads t ON t.id = m.thread_id WHERE m.id = ?', [meetupId]);
  if (!deal) throw new HttpError(404, 'Meetup not found.');
  // reviewer = the logged-in user. MySQL triggers enforce: meetup completed, reviewer is the buyer, one review per meetup (409 otherwise)
  const [r] = await pool.execute(
    'INSERT INTO reviews (meetup_id, reviewer_id, seller_id, rating, comment) VALUES (?,?,?,?,?)',
    [meetupId, actor.id, deal.seller_id, v.num(b.rating, 'Rating', 1, 5), String(b.comment || '').trim().slice(0, 255) || null]);
  audit(actor.id, 'Created Review', `Review #${r.insertId}`);
  return { review: await exports.get(r.insertId), trust: await trustOf(deal.seller_id) };   // trust tier was recalculated by the trigger
};
exports.update = async (id, b, actor) => {
  const row = await assertOwner(id, actor);
  if (!row) return null;
  await pool.execute('UPDATE reviews SET rating=?, comment=? WHERE id=?',
    [v.num(b.rating, 'Rating', 1, 5), String(b.comment || '').trim().slice(0, 255) || null, id]);
  audit(actor.id, 'Updated Review', `Review #${id}`);
  return { review: await exports.get(id), trust: await trustOf(row.seller_id) };
};
exports.remove = async (id, actor) => {
  if (!(await assertOwner(id, actor))) return 0;
  const [r] = await pool.execute('DELETE FROM reviews WHERE id = ?', [id]);
  audit(actor.id, 'Deleted Review', `Review #${id}`);
  return r.affectedRows;
};

exports.trustScores = async () => (await pool.query(
  `SELECT s.seller_id, u.full_name, s.rating_score, s.total_reviews, s.trust_tier
   FROM seller_trust_scores s JOIN users u ON u.id = s.seller_id
   ORDER BY s.rating_score DESC, s.total_reviews DESC`))[0];

// FULL OUTER JOIN report. MySQL has no FULL OUTER JOIN, so it is LEFT JOIN UNION RIGHT JOIN.
// UNION (not UNION ALL) removes the matched rows that appear in both halves.
exports.report = async () => (await pool.query(
  `SELECT x.user_id, u.full_name, x.trust_tier, x.rating_score, x.action, x.logged_at,
          CASE WHEN x.rating_score IS NULL THEN 'Audit log only (no trust score)'
               WHEN x.action IS NULL       THEN 'Trust score only (no audit activity)'
               ELSE 'Matched' END AS match_type
   FROM (
       SELECT s.seller_id AS user_id, s.trust_tier, s.rating_score, a.action, a.created_at AS logged_at
       FROM seller_trust_scores s LEFT JOIN audit_logs a ON a.user_id = s.seller_id
     UNION
       SELECT a.user_id, s.trust_tier, s.rating_score, a.action, a.created_at
       FROM seller_trust_scores s RIGHT JOIN audit_logs a ON a.user_id = s.seller_id
   ) x
   LEFT JOIN users u ON u.id = x.user_id
   ORDER BY u.full_name, x.logged_at`))[0];
