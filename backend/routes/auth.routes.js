const router = require('express').Router();
const c = require('../controllers/auth.controller');
const { requireAuth } = require('../middleware/auth');

router.post('/login', c.login);
router.get('/me', requireAuth, c.me);
module.exports = router;
