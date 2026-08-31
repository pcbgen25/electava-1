'use strict';

const express     = require('express');
const cors        = require('cors');
const helmet      = require('helmet');
const rateLimit   = require('express-rate-limit');
const { v4: uuidv4 } = require('uuid');
const dotenv      = require('dotenv');

dotenv.config();

// ─── Fail fast if critical env vars are missing ───────────────────────────────
for (const key of ['JWT_SECRET', 'DB_USER']) {
    if (!process.env[key]) {
        console.error(`[FATAL] Environment variable ${key} is not set. Refusing to start.`);
        process.exit(1);
    }
}
// DB_PASS can be empty string (local dev without MySQL password) but must be defined
if (process.env.DB_PASS === undefined) {
    console.error('[FATAL] Environment variable DB_PASS is not set (it can be empty). Refusing to start.');
    process.exit(1);
}

const app  = express();
const PORT = process.env.PORT || 5000;

// ─── Trust proxy only when explicitly configured ───────────────────────────────
// Set TRUST_PROXY=1 in .env when running behind a known reverse proxy (Nginx).
if (process.env.TRUST_PROXY) {
    app.set('trust proxy', parseInt(process.env.TRUST_PROXY, 10) || 1);
}

// ─── Security headers ─────────────────────────────────────────────────────────
app.disable('x-powered-by');
app.use(helmet());

// ─── CORS — restricted to approved origins ────────────────────────────────────
const allowedOrigins = (process.env.CORS_ORIGIN || 'https://electava.com')
    .split(',')
    .map(o => o.trim())
    .filter(Boolean);

app.use(cors({
    origin: (origin, cb) => {
        // Allow requests with no origin (e.g. server-to-server, curl in dev)
        if (!origin || allowedOrigins.includes(origin)) return cb(null, true);
        return cb(null, false);
    },
    credentials: true,
    methods: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    allowedHeaders: ['Content-Type', 'Authorization'],
}));

// ─── Body parsing — enforce size limit ───────────────────────────────────────
app.use(express.json({ limit: '10kb' }));
app.use(express.urlencoded({ extended: true, limit: '10kb' }));

// ─── Request ID for tracing ───────────────────────────────────────────────────
app.use((req, _res, next) => {
    req.id = uuidv4();
    next();
});

// ─── Global rate limit (100 req / 15 min per IP) ─────────────────────────────
const globalLimiter = rateLimit({
    windowMs: 15 * 60 * 1000,
    max: 100,
    standardHeaders: true,
    legacyHeaders: false,
    message: { error: 'Too many requests. Please try again later.' },
});
app.use(globalLimiter);

// ─── Stricter limits for auth & write endpoints ───────────────────────────────
const authLimiter = rateLimit({
    windowMs: 15 * 60 * 1000,
    max: 10,
    standardHeaders: true,
    legacyHeaders: false,
    message: { error: 'Too many attempts. Please wait 15 minutes and try again.' },
});
app.use('/api/auth/login',          authLimiter);
app.use('/api/auth/register',       authLimiter);

const writeLimiter = rateLimit({
    windowMs: 5 * 60 * 1000,
    max: 20,
    standardHeaders: true,
    legacyHeaders: false,
    message: { error: 'Too many requests. Please slow down.' },
});
app.use('/api/service-token', writeLimiter);
app.use('/api/tracking',      writeLimiter);

// ─── Routes ───────────────────────────────────────────────────────────────────
const apiRoutes = require('./routes/api');
app.use('/api', apiRoutes);

// ─── Health check (no sensitive info) ────────────────────────────────────────
app.get(['/', '/health'], (_req, res) => {
    res.json({ status: 'ok' });
});

// ─── 404 fallback ─────────────────────────────────────────────────────────────
app.use((_req, res) => {
    res.status(404).json({ error: 'Not found.' });
});

// ─── Centralized error handler ────────────────────────────────────────────────
// eslint-disable-next-line no-unused-vars
app.use((err, req, res, _next) => {
    const statusCode = err.status || err.statusCode || 500;
    if (statusCode >= 500) {
        console.error(`[${req.id || 'unknown'}] Unhandled error:`, err.message);
    }
    const message = statusCode < 500
        ? (err.message || 'Invalid request.')
        : 'Internal server error.';
    res.status(statusCode).json({ error: message });
});

// ─── Start ────────────────────────────────────────────────────────────────────
app.listen(PORT, '127.0.0.1', () => {
    console.log(`[Electava API] Listening on 127.0.0.1:${PORT}`);
});
