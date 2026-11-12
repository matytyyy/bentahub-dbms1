// Loads the SAME .env the PHP app uses (project root). JWT_SECRET and PORT are optional extras.
const path = require('path');
require('dotenv').config({ path: path.join(__dirname, '..', '..', '.env') });
