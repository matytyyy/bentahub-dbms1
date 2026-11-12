const m = require('../models/meetup.model');
const v = require('../utils/validate');
const { ok, created, fail, handle } = require('../utils/respond');

exports.list = handle(async (req, res) => ok(res, await m.list(req.query)));
exports.get = handle(async (req, res) => {
  const row = await m.get(v.id(req.params.id));
  return row ? ok(res, row) : fail(res, 404, 'Meetup not found.');
});
exports.create = handle(async (req, res) => created(res, await m.create(req.body, req.user)));
exports.update = handle(async (req, res) => {
  const row = await m.update(v.id(req.params.id), req.body, req.user);
  return row ? ok(res, row) : fail(res, 404, 'Meetup not found.');
});
exports.remove = handle(async (req, res) => {
  const id = v.id(req.params.id);
  return (await m.remove(id, req.user)) ? ok(res, { deleted: id }) : fail(res, 404, 'Meetup not found.');
});
exports.report = handle(async (req, res) => ok(res, await m.report()));
exports.setStatus = handle(async (req, res) => {
  const row = await m.setStatus(v.id(req.params.id), req.body.status, req.user);
  return row ? ok(res, row) : fail(res, 404, 'Meetup not found.');
});
