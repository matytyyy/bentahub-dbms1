const pool = require('../config/db');
const bcrypt = require('bcryptjs');
const { HttpError } = require('../utils/respond');
const v = require('../utils/validate');
const audit = require('../utils/audit');

// LEFT JOINs so a user with no role still appears in the list
const BASE = `SELECT u.id, u.full_name, u.email, u.phone_number, u.is_verified, u.created_at,
       GROUP_CONCAT(r.role_name ORDER BY r.id) AS roles
  FROM users u
  LEFT JOIN user_roles ur ON ur.user_id = u.id
  LEFT JOIN roles r       ON r.id = ur.role_id`;

function clean(b) {
  const email = String(b.email || '').trim();
  if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) throw new HttpError(400, 'Enter a valid email address.');
  const phone = String(b.phone_number || '').trim();
  if (phone && !/^[0-9+\-\s]{7,20}$/.test(phone)) throw new HttpError(400, 'Phone may only contain digits, spaces, + and -.');
  return { full_name: v.str(b.full_name, 'Full name', 2, 100), email, phone: phone || null, verified: v.flag(b.is_verified) };
}
async function setRoles(conn, uid, ids) {
  await conn.execute('DELETE FROM user_roles WHERE user_id = ?', [uid]);
  for (const rid of [...new Set(ids.map(Number))]) {
    await conn.execute('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)', [uid, rid]);
  }
}

exports.list = async () => (await pool.query(BASE + ' GROUP BY u.id ORDER BY u.id'))[0];
exports.get = async (id) => (await pool.execute(BASE + ' WHERE u.id = ? GROUP BY u.id', [id]))[0][0] || null;

exports.create = async (b, actor) => {
  const c = clean(b);
  const pw = String(b.password || '');
  if (pw.length < 8) throw new HttpError(400, 'Password must be at least 8 characters.');
  const hash = await bcrypt.hash(pw, 10);
  const roleIds = Array.isArray(b.role_ids) && b.role_ids.length ? b.role_ids : [3];
  const conn = await pool.getConnection();
  try {
    await conn.beginTransaction();             // user + roles are saved together or not at all
    const [r] = await conn.execute(
      'INSERT INTO users (full_name, email, password_hash, phone_number, is_verified) VALUES (?,?,?,?,?)',
      [c.full_name, c.email, hash, c.phone, c.verified]);
    await setRoles(conn, r.insertId, roleIds);
    await conn.commit();
    audit(actor.id, 'Created User', `User #${r.insertId}: ${c.full_name}`);
    return exports.get(r.insertId);
  } catch (e) { await conn.rollback(); throw e; } finally { conn.release(); }
};

exports.update = async (id, b, actor) => {
  const c = clean(b);
  const conn = await pool.getConnection();
  try {
    await conn.beginTransaction();
    const [r] = await conn.execute(
      'UPDATE users SET full_name=?, email=?, phone_number=?, is_verified=? WHERE id=?',
      [c.full_name, c.email, c.phone, c.verified, id]);
    if (!r.affectedRows) { await conn.rollback(); return null; }
    if (b.password) {
      if (String(b.password).length < 8) throw new HttpError(400, 'New password must be at least 8 characters.');
      await conn.execute('UPDATE users SET password_hash=? WHERE id=?', [await bcrypt.hash(String(b.password), 10), id]);
    }
    if (Array.isArray(b.role_ids)) await setRoles(conn, id, b.role_ids);
    await conn.commit();
    audit(actor.id, 'Updated User', `User #${id}: ${c.full_name}`);
    return exports.get(id);
  } catch (e) { await conn.rollback(); throw e; } finally { conn.release(); }
};

exports.remove = async (id, actor) => {
  const [r] = await pool.execute('DELETE FROM users WHERE id = ?', [id]);   // children go via ON DELETE CASCADE
  if (r.affectedRows) audit(actor.id, 'Deleted User', `User #${id}`);
  return r.affectedRows;
};

// INNER JOIN report: only users that match in ALL three tables (verified sellers)
exports.report = async () => (await pool.query(
  `SELECT u.id AS user_id, u.full_name, u.email, u.phone_number, u.is_verified, r.role_name
   FROM users u
   INNER JOIN user_roles ur ON u.id = ur.user_id
   INNER JOIN roles r       ON ur.role_id = r.id
   WHERE r.role_name = 'Seller' AND u.is_verified = 1
   ORDER BY u.id ASC`))[0];
