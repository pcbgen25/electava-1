'use strict';

const mysql  = require('mysql2/promise');
const dotenv = require('dotenv');

dotenv.config();

// NOTE: DB credentials must be set via environment variables.
// Validation is done at startup in server.js after dotenv.config() runs.

const pool = mysql.createPool({
    host:             process.env.DB_HOST     || '127.0.0.1',
    port:             parseInt(process.env.DB_PORT || '3306', 10),
    user:             process.env.DB_USER,      // No fallback — must be set
    password:         process.env.DB_PASS,      // No fallback — must be set
    database:         process.env.DB_NAME       || 'electava_workspace',
    waitForConnections: true,
    connectionLimit:  10,
    queueLimit:       50,
    connectTimeout:   10000,
    ssl: process.env.DB_SSL === 'true' ? { rejectUnauthorized: true } : undefined,
    // Enforce parameterized queries (no emulated prepares)
    namedPlaceholders: false,
});

module.exports = pool;
