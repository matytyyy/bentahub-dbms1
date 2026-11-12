// Authentication (who are you?) and authorization (are you allowed?).
require('../config/env');
const jwt = require('jsonwebtoken');
const { fail } = require('../utils/respond');
const SECRET = process.env.JWT_SECRET || 'bentahub-dev-secret';

exports.sign = (u) => jwt.sign({ id: u.id, name: u.full_name, roles: u.roles }, SECRET, { expiresIn: '8h' });

exports.requireAuth = (req, res, next) => {
  const h = req.headers.authorization || '';
  const token = h.startsWith('Bearer ') ? h.slice(7) : null;
  if (!token) return fail(res, 401, 'Login required.');
  try { req.user = jwt.verify(token, SECRET); next(); }
  catch (e) { return fail(res, 401, 'Invalid or expired token. Please log in again.'); }
};

exports.requireRole = (...roles) => (req, res, next) =>
  req.user.roles.some((r) => roles.includes(r)) ? next() : fail(res, 403, 'You do not have permission to do that.');
