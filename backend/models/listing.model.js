const pool = require('../config/db');
const { HttpError } = require('../utils/respond');
const v = require('../utils/validate');
const audit = require('../utils/audit');

const STATUS = ['active', 'reserved', 'sold', 'archived'];
const LIST = `SELECT l.id, l.title, l.price, l.status, l.seller_id, u.full_name AS seller,
       c.category_name, ic.condition_name
  FROM listings l
  JOIN users u            ON u.id = l.seller_id
  JOIN categories c       ON c.id = l.category_id
  JOIN item_conditions ic ON ic.id = l.condition_id`;

const clean = (b, actor) => [
  v.id(b.seller_id ?? actor.id, 'seller_id'), v.id(b.category_id, 'category_id'), v.id(b.condition_id, 'condition_id'),
  v.str(b.title, 'Title', 3, 150), String(b.description || '').trim() || null,
  v.num(b.price, 'Price', 0.01, 99999999), v.oneOf(b.status || 'active', STATUS, 'Status'),
];
// Authorization: only the seller or an Admin may change a listing (403 otherwise)
async function assertOwner(id, actor) {
  const row = await exports.get(id);
  if (!row) return null;
  if (row.seller_id !== actor.id && !actor.roles.includes('Admin')) throw new HttpError(403, 'Only the seller or an Admin can change this listing.');
  return row;
}

exports.list = async () => (await pool.query(LIST + ' ORDER BY l.id DESC'))[0];
exports.get = async (id) => (await pool.execute('SELECT * FROM listings WHERE id = ?', [id]))[0][0] || null;

exports.create = async (b, actor) => {
  const [r] = await pool.execute(
    'INSERT INTO listings (seller_id, category_id, condition_id, title, description, price, status) VALUES (?,?,?,?,?,?,?)',
    clean(b, actor));
  audit(actor.id, 'Created Listing', `Listing #${r.insertId}`);
  return exports.get(r.insertId);
};
exports.update = async (id, b, actor) => {
  if (!(await assertOwner(id, actor))) return null;
  // the MySQL trigger trg_listing_after_update logs price drops by itself
  await pool.execute(
    'UPDATE listings SET seller_id=?, category_id=?, condition_id=?, title=?, description=?, price=?, status=? WHERE id=?',
    [...clean(b, actor), id]);
  audit(actor.id, 'Updated Listing', `Listing #${id}`);
  return exports.get(id);
};
exports.remove = async (id, actor) => {
  if (!(await assertOwner(id, actor))) return 0;
  const [r] = await pool.execute('DELETE FROM listings WHERE id = ?', [id]);
  audit(actor.id, 'Deleted Listing', `Listing #${id}`);
  return r.affectedRows;
};

// RIGHT JOIN report: EVERY category (even empty ones) and every listing (even ones nobody watches)
exports.report = async () => (await pool.query(
  `SELECT c.category_name, l.title AS item_title, l.price AS current_price, p.target_price, u.full_name AS watcher
   FROM price_drop_alerts p
   RIGHT JOIN listings l   ON p.listing_id = l.id
   RIGHT JOIN categories c ON l.category_id = c.id
   LEFT JOIN  users u      ON u.id = p.user_id
   ORDER BY c.category_name, l.title`))[0];
