const router = require('express').Router();
const c = require('../controllers/meetup.controller');

router.get('/meetups', c.list);
router.get('/meetups/:id', c.get);
router.post('/meetups', c.create);
router.put('/meetups/:id', c.update);
router.delete('/meetups/:id', c.remove);
router.patch('/meetups/:id/status', c.setStatus);
router.get('/reports/chats-meetups', c.report);
module.exports = router;
