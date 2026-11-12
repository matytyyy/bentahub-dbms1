const router = require('express').Router();
const c = require('../controllers/review.controller');

router.get('/reviews', c.list);
router.get('/reviews/:id', c.get);
router.post('/reviews', c.create);
router.put('/reviews/:id', c.update);
router.delete('/reviews/:id', c.remove);
router.get('/trust-scores', c.trustScores);
router.get('/reports/trust-vs-audit', c.report);
module.exports = router;
