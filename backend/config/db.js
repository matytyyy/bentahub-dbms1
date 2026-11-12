// One shared MySQL connection pool (like PDO in the PHP version).
require('./env');
const mysql = require('mysql2/promise');

module.exports = mysql.createPool({
  host: process.env.DB_HOST || 'localhost',
  port: Number(process.env.DB_PORT || 3306),
  user: process.env.DB_USER || 'root',
  password: process.env.DB_PASSWORD || '',
  database: process.env.DB_NAME || 'bentahub_db',
  waitForConnections: true,
  connectionLimit: 10,
  dateStrings: true,
});
