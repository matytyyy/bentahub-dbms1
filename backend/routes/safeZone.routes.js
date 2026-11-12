const router = require('express').Router();
const c = require('../controllers/safeZone.controller');

router.get('/safe-zones', c.list);
router.get('/safe-zones/:id', c.get);
router.post('/safe-zones', c.create);
router.put('/safe-zones/:id', c.update);
router.delete('/safe-zones/:id', c.remove);
router.get('/reports/zone-meetups', c.report);
module.exports = router;
