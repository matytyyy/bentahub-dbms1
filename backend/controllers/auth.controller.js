const model = require('../models/auth.model');
const { sign } = require('../middleware/auth');
const { ok, handle } = require('../utils/respond');

exports.login = handle(async (req, res) => {
  const user = await model.login(req.body.email, req.body.password);
  ok(res, { token: sign(user), user });
});
exports.me = handle(async (req, res) => ok(res, req.user));
