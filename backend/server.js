require('./config/env');
const path = require('path');
const express = require('express');
const { requireAuth } = require('./middleware/auth');
const { fail } = require('./utils/respond');

const app = express();
app.use(express.json());

app.use('/api/auth', require('./routes/auth.routes'));   // public: login
app.use('/api', requireAuth);                              // everything below needs a valid token
app.use('/api', require('./routes/user.routes'));          // Ijan
app.use('/api', require('./routes/safeZone.routes'));      // Ib-Ib
app.use('/api', require('./routes/listing.routes'));       // Dinglasan
app.use('/api', require('./routes/meetup.routes'));        // Espiritu
app.use('/api', require('./routes/review.routes'));        // Gemina
app.use('/api', require('./routes/chat.routes'));          // Cabugnason
app.use('/api', (req, res) => fail(res, 404, 'Endpoint not found.'));

app.use(express.static(path.join(__dirname, '..', 'frontend')));   // the frontend

// last resort: bad JSON bodies and anything unexpected
app.use((err, req, res, next) => {
  if (err.type === 'entity.parse.failed') return fail(res, 400, 'Request body is not valid JSON.');
  console.error(err);
  return fail(res, 500, 'Server error.');
});

const port = Number(process.env.PORT || 3000);
app.listen(port, () => console.log(`BentaHub API + frontend on http://localhost:${port}`));
