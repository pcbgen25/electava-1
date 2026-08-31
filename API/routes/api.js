'use strict';

const express  = require('express');
const bcrypt   = require('bcryptjs');
const crypto   = require('crypto');
const router   = express.Router();
const db       = require('../config/db');
const { authenticateToken, requireRole, signToken } = require('../middleware/auth');

// ─── Helpers ──────────────────────────────────────────────────────────────────

function normalizeEmail(email) {
    return String(email || '').trim().toLowerCase();
}

function buildUsername(email) {
    const localPart = normalizeEmail(email).split('@')[0] || 'user';
    return localPart.replace(/[^a-z0-9_]+/gi, '_').replace(/^_+|_+$/g, '').toLowerCase() || 'user';
}

function splitFullName(fullName) {
    const parts = String(fullName || '').trim().split(/\s+/).filter(Boolean);
    return {
        firstName: parts[0] || 'Marketplace',
        lastName:  parts.slice(1).join(' ') || 'User',
    };
}

/** Safe public user object — never includes password_hash or internal fields */
function mapUser(row) {
    const { firstName, lastName } = splitFullName(row.full_name);
    return {
        id:          row.id,
        email:       row.email,
        username:    row.username,
        firstName,
        lastName,
        fullName:    row.full_name || `${firstName} ${lastName}`,
        company:     row.notes || 'Electava Marketplace',
        phone:       row.phone || '',
        title:       row.job_title || 'Marketplace User',
        role:        row.role,
        memberSince: row.created_at,
    };
}

// ─── Password helpers (bcryptjs — no PHP process spawning) ───────────────────

const BCRYPT_ROUNDS = 12;

async function hashPassword(password) {
    return bcrypt.hash(password, BCRYPT_ROUNDS);
}

async function verifyPassword(password, hash) {
    return bcrypt.compare(password, hash);
}

/** Username deduplication — only ever queries the `users` table (hardcoded) */
async function getAvailableUsername(baseUsername) {
    let username = baseUsername;
    for (let suffix = 2; suffix <= 100; suffix++) {
        const [rows] = await db.query(
            'SELECT id FROM users WHERE username = ? LIMIT 1',
            [username]
        );
        if (rows.length === 0) return username;
        username = `${baseUsername}${suffix}`;
    }
    return `${baseUsername}_${crypto.randomBytes(3).toString('hex')}`;
}

// Map a database component row to the frontend mock data structure
function mapComponent(row) {
    let specs = {};
    let assetLinks = { documents: [], images: [], cad: [] };
    if (row.specifications) {
        try {
            specs = typeof row.specifications === 'string' ? JSON.parse(row.specifications) : row.specifications;
        } catch (e) {}
    }
    if (row.asset_links) {
        try {
            const parsed = typeof row.asset_links === 'string' ? JSON.parse(row.asset_links) : row.asset_links;
            assetLinks = {
                documents: Array.isArray(parsed?.documents) ? parsed.documents : [],
                images: Array.isArray(parsed?.images) ? parsed.images : [],
                cad: Array.isArray(parsed?.cad) ? parsed.cad : [],
            };
        } catch (e) {}
    }
    
    let priceTiers = [];
    if (row.quantity_breaks) {
        try {
            priceTiers = typeof row.quantity_breaks === 'string' ? JSON.parse(row.quantity_breaks) : row.quantity_breaks;
        } catch (e) {}
    }
    
    if (priceTiers.length === 0) {
        priceTiers = [ { qty: 1, price: parseFloat(row.price) } ];
    }

    return {
        id: `prod-${String(row.id).padStart(3, '0')}`,
        db_id: row.id,
        name: row.name,
        manufacturer: row.manufacturer_name || 'Unknown',
        partNumber: row.part_number,
        electavaPartNumber: row.electava_part_number || row.part_number,
        category: (row.parent_category_name ? row.parent_category_name.toLowerCase().replace(/\s+/g, '-') : 'general'),
        subcategory: (row.category_name ? row.category_name.toLowerCase().replace(/\s+/g, '-') : 'general'),
        description: row.description,
        price: parseFloat(row.price),
        priceTiers: priceTiers,
        stock: row.stock,
        specs: specs,
        assetLinks: assetLinks,
        image: row.image_url || '/images/ic.svg',
        datasheet: row.datasheet_url || '#'
    };
}

// POST /api/auth/register  — marketplace registrations always go to `users` table
router.post('/auth/register', async (req, res) => {
    try {
        const firstName = String(req.body.firstName || '').trim().slice(0, 100);
        const lastName  = String(req.body.lastName  || '').trim().slice(0, 100);
        const email     = normalizeEmail(req.body.email);
        const password  = String(req.body.password  || '');
        const company   = String(req.body.company   || '').trim().slice(0, 200);

        if (!firstName || !lastName || !email || !password) {
            return res.status(400).json({ error: 'All required fields must be filled.' });
        }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            return res.status(400).json({ error: 'Please enter a valid email address.' });
        }
        if (password.length < 8 || password.length > 128) {
            return res.status(400).json({ error: 'Password must be 8–128 characters.' });
        }

        // Check both tables so workspace staff can't re-register on marketplace
        const [existingUsers]     = await db.query('SELECT id FROM users     WHERE email = ? LIMIT 1', [email]);
        const [existingEmployees] = await db.query('SELECT id FROM employees WHERE email = ? LIMIT 1', [email]);
        if (existingUsers.length > 0 || existingEmployees.length > 0) {
            return res.status(409).json({ error: 'An account with this email already exists. Please sign in.' });
        }

        const username     = await getAvailableUsername(buildUsername(email));
        const passwordHash = await hashPassword(password);
        const fullName     = `${firstName} ${lastName}`.trim();

        // Always insert into `users` with role = 'marketplace_user'
        const [result] = await db.query(
            `INSERT INTO users (email, username, password_hash, full_name, role, status, phone, job_title, notes)
             VALUES (?, ?, ?, ?, 'marketplace_user', 'active', '', 'Marketplace User', ?)`,
            [email, username, passwordHash, fullName, company || 'Electava Marketplace']
        );

        const [rows] = await db.query(
            'SELECT id, email, username, full_name, role, status, phone, job_title, notes, created_at FROM users WHERE id = ? LIMIT 1',
            [result.insertId]
        );
        const user  = mapUser(rows[0]);
        const token = signToken(rows[0]);

        res.status(201).json({ success: true, message: 'Account created successfully.', token, user });
    } catch (error) {
        if (error.code === 'ER_DUP_ENTRY' || error.errno === 1062) {
            return res.status(409).json({ error: 'An account with this email already exists.' });
        }
        console.error(`[register] ${error.message}`);
        res.status(500).json({ error: 'Unable to create account right now. Please try again.' });
    }
});

// POST /api/auth/login  — marketplace login always checks `users` table
router.post('/auth/login', async (req, res) => {
    try {
        const login    = normalizeEmail(req.body.email || req.body.login);
        const password = String(req.body.password || '');

        if (!login || !password) {
            return res.status(400).json({ error: 'Email and password are required.' });
        }

        const [rows] = await db.query(
            `SELECT id, email, username, password_hash, full_name, role, status, phone, job_title, notes, created_at
             FROM users
             WHERE (email = ? OR username = ?)
             LIMIT 1`,
            [login, login]
        );

        // Constant-time: always run bcrypt compare even on no-match to prevent timing attacks
        const dummyHash = '$2a$12$invalidhashfortimingprotectiononly000000000000000000000';
        const hash      = rows.length > 0 ? rows[0].password_hash : dummyHash;
        const valid     = await verifyPassword(password, hash);

        if (!valid || rows.length === 0) {
            return res.status(401).json({ error: 'Invalid email or password.' });
        }
        if (rows[0].status !== 'active') {
            return res.status(403).json({ error: 'Your account is inactive. Please contact support.' });
        }

        await db.query('UPDATE users SET last_login_at = NOW() WHERE id = ?', [rows[0].id]);

        const user  = mapUser(rows[0]);
        const token = signToken(rows[0]);

        res.json({ success: true, message: 'Signed in successfully.', token, user });
    } catch (error) {
        console.error(`[login] ${error.message}`);
        res.status(500).json({ error: 'Unable to sign in right now. Please try again.' });
    }
});


// GET /api/components — public catalog, active products only, paginated
router.get('/components', async (req, res) => {
    try {
        const page  = Math.max(1, parseInt(req.query.page,  10) || 1);
        const limit = Math.min(100, Math.max(1, parseInt(req.query.limit, 10) || 50));
        const offset = (page - 1) * limit;

        const [rows] = await db.query(`
            SELECT
                c.*,
                m.name   AS manufacturer_name,
                cat.name AS category_name,
                p_cat.name AS parent_category_name
            FROM components c
            LEFT JOIN manufacturers m    ON c.manufacturer_id = m.id
            LEFT JOIN categories cat     ON c.category_id = cat.id
            LEFT JOIN categories p_cat   ON cat.parent_id = p_cat.id
            WHERE c.status = 'active'
            LIMIT ? OFFSET ?
        `, [limit, offset]);

        res.json(rows.map(mapComponent));
    } catch (error) {
        console.error(`[components] ${error.message}`);
        res.status(500).json({ error: 'Failed to fetch components.' });
    }
});

// GET /api/components/:id
router.get('/components/:id', async (req, res) => {
    try {
        let dbId = req.params.id;
        // if they passed 'prod-001', parse out the number
        if (typeof dbId === 'string' && dbId.startsWith('prod-')) {
            dbId = dbId.split('-')[1];
        }

        const numericId = parseInt(dbId, 10);
        if (!numericId || isNaN(numericId) || numericId <= 0) {
            return res.status(400).json({ error: 'Invalid component ID.' });
        }

        const query = `
            SELECT 
                c.*, 
                m.name AS manufacturer_name,
                cat.name AS category_name,
                p_cat.name AS parent_category_name
            FROM components c
            LEFT JOIN manufacturers m ON c.manufacturer_id = m.id
            LEFT JOIN categories cat ON c.category_id = cat.id
            LEFT JOIN categories p_cat ON cat.parent_id = p_cat.id
            WHERE c.id = ? AND c.status = 'active'
        `;
        const [rows] = await db.query(query, [numericId]);
        if (rows.length === 0) return res.status(404).json({ error: 'Component not found' });
        
        res.json(mapComponent(rows[0]));
    } catch (error) {
        console.error(error);
        res.status(500).json({ error: 'Failed to fetch component details.' });
    }
});

// GET /api/categories
router.get('/categories', async (req, res) => {
    try {
        const [rows] = await db.query('SELECT * FROM categories');
        // Build hierarchy map
        const catMap = {};
        const parents = [];
        
        // Find roots
        rows.forEach(r => {
            if (!r.parent_id) {
                const mapObj = {
                    id: r.name.toLowerCase().replace(/\s+/g, '-'),
                    db_id: r.id,
                    name: r.name,
                    icon: '📦',
                    description: '',
                    subcategories: []
                };
                catMap[r.id] = mapObj;
                parents.push(mapObj);
            }
        });
        
        // Find children
        rows.forEach(r => {
            if (r.parent_id && catMap[r.parent_id]) {
                catMap[r.parent_id].subcategories.push({
                    id: r.name.toLowerCase().replace(/\s+/g, '-'),
                    db_id: r.id,
                    name: r.name
                });
            }
        });
        
        res.json(parents);
    } catch (error) {
        console.error(error);
        res.status(500).json({ error: 'Failed to fetch categories.' });
    }
});

// GET /api/manufacturers
router.get('/manufacturers', async (req, res) => {
    try {
        const [rows] = await db.query('SELECT * FROM manufacturers');
        res.json(rows);
    } catch (error) {
        console.error(error);
        res.status(500).json({ error: 'Failed to fetch manufacturers.' });
    }
});

// POST /api/tracking
router.post('/tracking', async (req, res) => {
    try {
        const sessionId   = String(req.body.sessionId   || '').slice(0, 64);
        const deviceType  = String(req.body.deviceType  || '').slice(0, 50);
        const browser     = String(req.body.browser     || '').slice(0, 100);
        const pageVisited = String(req.body.pageVisited || '').slice(0, 500);
        // Use socket address only — do NOT trust x-forwarded-for without verified proxy config
        const ipAddress = req.ip || req.socket.remoteAddress || '';
        const userAgent = String(req.headers['user-agent'] || '').slice(0, 500);

        await db.query(
            `INSERT INTO marketplace_tracking
             (session_id, ip_address, user_agent, device_type, browser, page_visited)
             VALUES (?, ?, ?, ?, ?, ?)`,
            [sessionId, ipAddress, userAgent, deviceType, browser, pageVisited]
        );
        res.json({ success: true });
    } catch (error) {
        console.error(`[tracking] ${error.message}`);
        res.status(500).json({ error: 'Failed to record tracking.' });
    }
});

// POST /api/service-token — cryptographically secure token generation
router.post('/service-token', async (req, res) => {
    try {
        const userEmail   = normalizeEmail(req.body.userEmail);
        const serviceType = String(req.body.serviceType || '').slice(0, 100);
        const details     = typeof req.body.details === 'object'
            ? JSON.stringify(req.body.details)
            : String(req.body.details || '').slice(0, 5000);

        if (!userEmail || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(userEmail)) {
            return res.status(400).json({ error: 'Valid email is required.' });
        }
        if (!serviceType) {
            return res.status(400).json({ error: 'Service type is required.' });
        }

        const ALLOWED_SERVICES = ['pcb-design', 'assembly', 'component-sourcing', 'testing', 'general-inquiry'];
        const serviceTypeLower = String(serviceType || '').trim().toLowerCase();
        if (!serviceTypeLower || !ALLOWED_SERVICES.includes(serviceTypeLower)) {
            return res.status(400).json({
                error: `Invalid service type. Must be one of: ${ALLOWED_SERVICES.join(', ')}`
            });
        }

        // Generate unique token with cryptographically secure random bytes + collision retry
        let tokenNumber, attempts = 0;
        while (attempts < 5) {
            const rand = crypto.randomBytes(6).toString('hex').toUpperCase();
            tokenNumber = `SRV-${new Date().getFullYear()}-${rand}`;
            const [existing] = await db.query(
                'SELECT id FROM service_tokens WHERE token_number = ? LIMIT 1',
                [tokenNumber]
            );
            if (existing.length === 0) break;
            attempts++;
        }
        if (attempts >= 5) throw new Error('Token generation collision limit reached');

        await db.query(
            `INSERT INTO service_tokens (token_number, user_email, service_type, details)
             VALUES (?, ?, ?, ?)`,
            [tokenNumber, userEmail, serviceType, details]
        );

        res.json({ success: true, token: tokenNumber });
    } catch (error) {
        console.error(`[service-token] ${error.message}`);
        res.status(500).json({ error: 'Failed to generate token.' });
    }
});

// GET /api/careers
router.get('/careers', async (req, res) => {
    try {
        const [rows] = await db.query(
            'SELECT id, title, team, location, type, summary, highlights_json FROM careers WHERE status = "active" ORDER BY created_at DESC'
        );
        res.json(rows.map(r => ({
            id:         r.id,
            title:      r.title,
            team:       r.team,
            location:   r.location,
            type:       r.type,
            summary:    r.summary,
            highlights: (() => { try { return JSON.parse(r.highlights_json || '[]'); } catch { return []; } })(),
        })));
    } catch (error) {
        console.error(`[careers] ${error.message}`);
        res.status(500).json({ error: 'Failed to fetch careers.' });
    }
});

// GET /api/account/orders — PROTECTED: requires valid JWT
// BOLA fix: userId is read from the verified token, never from query params
router.get('/account/orders', authenticateToken, async (req, res) => {
    try {
        const userId = req.user.id; // From verified JWT only — not from req.query

        const [rows] = await db.query(`
            SELECT o.*,
                   COUNT(oi.id) AS items_count,
                   GROUP_CONCAT(CONCAT(c.name, ' (x', oi.quantity, ')') SEPARATOR ', ') AS items_summary
            FROM orders o
            LEFT JOIN order_items oi ON o.id = oi.order_id
            LEFT JOIN components c   ON oi.component_id = c.id
            WHERE o.customer_id = ?
            GROUP BY o.id
            ORDER BY o.created_at DESC
            LIMIT 100
        `, [userId]);

        res.json(rows.map(r => ({
            id:           `ELV-SO-${10000 + r.id}`,
            db_id:        r.id,
            date:         r.created_at ? new Date(r.created_at).toISOString().split('T')[0] : 'N/A',
            itemsCount:   r.items_count  || 0,
            itemsSummary: r.items_summary || 'No items',
            total:        parseFloat(r.total) || 0,
            status:       r.status ? (r.status.charAt(0).toUpperCase() + r.status.slice(1)) : 'Pending',
            trackingNumber: (r.status === 'shipped' || r.status === 'delivered')
                ? `TRK-${100000 + r.id}-IN` : null,
            carrier: (r.status === 'shipped' || r.status === 'delivered') ? 'BlueDart Express' : null,
        })));
    } catch (error) {
        console.error(`[account/orders] ${error.message}`);
        res.status(500).json({ error: 'Failed to fetch orders.' });
    }
});

module.exports = router;
