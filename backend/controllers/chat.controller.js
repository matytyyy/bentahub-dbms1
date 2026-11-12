const m = require('../models/chat.model');
const v = require('../utils/validate');
const { ok, created, fail, handle } = require('../utils/respond');

exports.listThreads = handle(async (req, res) => ok(res, await m.listThreads()));
exports.createThread = handle(async (req, res) => created(res, await m.createThread(req.body, req.user)));
exports.removeThread = handle(async (req, res) => {
  const id = v.id(req.params.id);
  return (await m.removeThread(id, req.user)) ? ok(res, { deleted: id }) : fail(res, 404, 'Chat not found.');
});

exports.listMessages = handle(async (req, res) => ok(res, await m.listMessages(req.query)));
exports.createMessage = handle(async (req, res) => created(res, await m.createMessage(req.body, req.user)));
exports.updateMessage = handle(async (req, res) => {
  const row = await m.updateMessage(v.id(req.params.id), req.body, req.user);
  return row ? ok(res, row) : fail(res, 404, 'Message not found.');
});
exports.removeMessage = handle(async (req, res) => {
  const id = v.id(req.params.id);
  return (await m.removeMessage(id, req.user)) ? ok(res, { deleted: id }) : fail(res, 404, 'Message not found.');
});
exports.report = handle(async (req, res) => ok(res, await m.report()));
