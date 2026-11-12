const pool = require('../config/db');
const bcrypt = require('bcryptjs');
const { HttpError } = require('../utils/respond');

exports.login = async (email, password) => {
  const [rows] = await pool.execute(
    `SELECT u.id, u.full_name, u.email, u.password_hash, GROUP_CONCAT(r.role_name) AS roles
     FROM users u LEFT JOIN user_roles ur ON ur.user_id = u.id LEFT JOIN roles r ON r.id = ur.role_id
     WHERE u.email = ? GROUP BY u.id`, [String(email || '').trim()]);
  const u = rows[0];
  // same message for "no such email" and "wrong password" so attackers learn nothing
  if (!u || !(await bcrypt.compare(String(password || ''), u.password_hash))) throw new HttpError(401, 'Wrong email or password.');
  return { id: u.id, full_name: u.full_name, email: u.email, roles: u.roles ? u.roles.split(',') : [] };
};
