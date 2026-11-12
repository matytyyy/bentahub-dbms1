// Consistent JSON format + one place that maps errors to HTTP status codes.
class HttpError extends Error {
  constructor(status, message) { super(message); this.status = status; }
}
const send = (res, status, body) => res.status(status).json(body);
const ok = (res, data, status = 200) => send(res, status, { success: true, data });
const created = (res, data) => ok(res, data, 201);
const fail = (res, status, message) => send(res, status, { success: false, message });

function fromError(res, err) {
  if (err instanceof HttpError) return fail(res, err.status, err.message);
  switch (err.errno) {
    case 1644: return fail(res, 409, err.sqlMessage);            // rule raised by a MySQL trigger (SIGNAL)
    case 1062: return fail(res, 409, 'That value already exists (duplicate).');
    case 1451: return fail(res, 409, 'Cannot delete: other records still depend on this one.');
    case 1452: return fail(res, 400, 'Invalid selection: the related record does not exist.');
    case 3819: case 4025: return fail(res, 400, 'A value is outside the allowed range.');
    case 1048: return fail(res, 400, 'A required field is empty.');
  }
  console.error(err);
  return fail(res, 500, 'Server error.');
}
// Wraps a controller so any thrown error becomes a proper JSON response.
const handle = (fn) => async (req, res) => { try { await fn(req, res); } catch (e) { fromError(res, e); } };

module.exports = { HttpError, ok, created, fail, fromError, handle };
