const router = require('express').Router();
const c = require('../controllers/listing.controller');

router.get('/listings', c.list);
router.get('/listings/:id', c.get);
router.post('/listings', c.create);
router.put('/listings/:id', c.update);
router.delete('/listings/:id', c.remove);
router.get('/reports/category-alerts', c.report);
module.exports = router;
