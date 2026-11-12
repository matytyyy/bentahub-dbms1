const router = require('express').Router();
const c = require('../controllers/user.controller');
const { requireRole } = require('../middleware/auth');

router.get('/users', c.list);
router.get('/users/:id', c.get);
router.post('/users', c.create);
router.put('/users/:id', c.update);
router.delete('/users/:id', requireRole('Admin'), c.remove);   // only Admins may delete (403 otherwise)
router.get('/reports/verified-sellers', c.report);
module.exports = router;
