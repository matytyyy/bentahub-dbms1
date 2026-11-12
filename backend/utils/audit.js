// Writes to audit_logs (same table the PHP app uses). Never breaks the request if logging fails.
const pool = require('../config/db');
module.exports = (userId, action, details) =>
  pool.execute('INSERT INTO audit_logs (user_id, action, details) VALUES (?,?,?)', [userId ?? null, action, details ?? null])
      .catch(() => {});
