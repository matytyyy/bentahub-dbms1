const { HttpError } = require('./respond');
exports.id = (v, name = 'id') => {
  const n = Number(v);
  if (!Number.isInteger(n) || n < 1) throw new HttpError(400, `${name} must be a positive whole number.`);
  return n;
};
exports.str = (v, name, min, max) => {
  const s = String(v ?? '').trim();
  if (s.length < min || s.length > max) throw new HttpError(400, `${name} must be ${min} to ${max} characters.`);
  return s;
};
exports.num = (v, name, min, max) => {
  const n = Number(v);
  if (!Number.isFinite(n) || n < min || n > max) throw new HttpError(400, `${name} must be between ${min} and ${max}.`);
  return n;
};
exports.oneOf = (v, list, name) => {
  if (!list.includes(v)) throw new HttpError(400, `${name} must be one of: ${list.join(', ')}.`);
  return v;
};
exports.flag = (v) => (v === true || String(v) === '1' || String(v) === 'true' ? 1 : 0);
