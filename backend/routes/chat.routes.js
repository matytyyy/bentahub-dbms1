const router = require('express').Router();
const c = require('../controllers/chat.controller');

router.get('/threads', c.listThreads);
router.post('/threads', c.createThread);
router.delete('/threads/:id', c.removeThread);

router.get('/messages', c.listMessages);          // optional ?thread_id=
router.post('/messages', c.createMessage);
router.patch('/messages/:id', c.updateMessage);
router.delete('/messages/:id', c.removeMessage);

router.get('/reports/threads-message-counts', c.report);
module.exports = router;
