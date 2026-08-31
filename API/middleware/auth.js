/**
 * auth.js — JWT authentication & authorization middleware
 *
 * Usage:
 *   router.get('/protected', authenticateToken, handler);
 *   router.get('/admin-only', authenticateToken, requireRole('admin', 'core_admin'), handler);
 */

const jwt = require('jsonwebtoken');
const db  = require('../config/db');

const JWT_SECRET = process.env.JWT_SECRET;

if (!JWT_SECRET || JWT_SECRET.length < 32) {
    console.error('[FATAL] JWT_SECRET must be at least 32 characters. Refusing to start.');
    process.exit(1);
}

/**
 * Verify the Bearer token, load the account's current status from DB,
 * and attach req.user = { id, email, role }.
 */
async function authenticateToken(req, res, next) {
    const authHeader = req.headers['authorization'];
    const token = authHeader && authHeader.startsWith('Bearer ')
        ? authHeader.slice(7)
        : null;

    if (!token) {
        return res.status(401).json({ error: 'Authentication required.' });
    }

    let payload;
    try {
        payload = jwt.verify(token, JWT_SECRET, {
            algorithms: ['HS256'],
            issuer: 'electava-api'
        });
    } catch (err) {
        if (err.name === 'TokenExpiredError') {
            return res.status(401).json({ error: 'Session expired. Please sign in again.' });
        }
        return res.status(401).json({ error: 'Invalid token.' });
    }

    // Re-check account status on every request — catches deactivated accounts mid-session
    try {
        const [rows] = await db.query(
            "SELECT id, email, role, status FROM users WHERE id = ? LIMIT 1",
            [payload.id]
        );
        if (rows.length === 0 || rows[0].status !== 'active') {
            return res.status(403).json({ error: 'Account is inactive or does not exist.' });
        }
        req.user = { id: rows[0].id, email: rows[0].email, role: rows[0].role };
        next();
    } catch (err) {
        console.error(`[${req.id}] Auth DB error:`, err.message);
        return res.status(500).json({ error: 'Internal server error.' });
    }
}

/**
 * Role-based access control middleware factory.
 * Call after authenticateToken.
 *
 * @param {...string} allowedRoles
 */
function requireRole(...allowedRoles) {
    return (req, res, next) => {
        if (!req.user) {
            return res.status(401).json({ error: 'Authentication required.' });
        }
        if (!allowedRoles.includes(req.user.role)) {
            return res.status(403).json({ error: 'Insufficient permissions.' });
        }
        next();
    };
}

/**
 * Sign a short-lived JWT for a user row from the database.
 */
function signToken(userRow) {
    return jwt.sign(
        { id: userRow.id, email: userRow.email, role: userRow.role },
        JWT_SECRET,
        { expiresIn: '1h', issuer: 'electava-api' }
    );
}

module.exports = { authenticateToken, requireRole, signToken };
