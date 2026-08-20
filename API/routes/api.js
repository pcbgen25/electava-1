const express = require('express');
const { spawnSync } = require('child_process');
const router = express.Router();
const db = require('../config/db');

const PHP_BIN = process.env.PHP_BIN || (process.platform === 'win32' ? 'C:\\xampp\\php\\php.exe' : 'php');

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
        lastName: parts.slice(1).join(' ') || 'User',
    };
}

function mapUser(row) {
    const { firstName, lastName } = splitFullName(row.full_name);

    return {
        id: row.id,
        email: row.email,
        username: row.username,
        firstName,
        lastName,
        fullName: row.full_name || `${firstName} ${lastName}`,
        company: row.notes || 'Electava Marketplace',
        phone: row.phone || '',
        title: row.job_title || 'Marketplace User',
        role: row.role,
        memberSince: row.created_at,
    };
}

async function getAuthStore() {
    const [userTables] = await db.query("SHOW TABLES LIKE 'users'");
    if (userTables.length > 0) {
        return {
            table: 'users',
            passwordColumn: 'password_hash',
            activeCheck: "status = 'active'",
            activeValues: { status: 'active', is_active: 1 },
        };
    }

    const [employeeTables] = await db.query("SHOW TABLES LIKE 'employees'");
    if (employeeTables.length > 0) {
        return {
            table: 'employees',
            passwordColumn: 'password',
            activeCheck: "(status = 'active' OR is_active = 1)",
            activeValues: { status: 'active', is_active: 1 },
        };
    }

    throw new Error('No users or employees table found in the configured database.');
}

function runPhp(code, args) {
    const result = spawnSync(PHP_BIN, ['-r', code, ...args], {
        encoding: 'utf8',
        windowsHide: true,
        timeout: 5000,
    });

    if (result.error || result.status !== 0) {
        const message = result.error?.message || result.stderr || 'PHP command failed';
        throw new Error(message);
    }

    return result.stdout.trim();
}

function hashPassword(password) {
    return runPhp('echo password_hash($argv[1], PASSWORD_BCRYPT);', [password]);
}

function verifyPassword(password, hash) {
    return runPhp('echo password_verify($argv[1], $argv[2]) ? "1" : "0";', [password, hash]) === '1';
}

async function getAvailableUsername(baseUsername, table) {
    let username = baseUsername;
    let suffix = 2;

    while (true) {
        const [rows] = await db.query(`SELECT id FROM ${table} WHERE username = ? LIMIT 1`, [username]);
        if (rows.length === 0) return username;
        username = `${baseUsername}${suffix}`;
        suffix += 1;
    }
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

// POST /api/auth/register
router.post('/auth/register', async (req, res) => {
    try {
        const firstName = String(req.body.firstName || '').trim();
        const lastName = String(req.body.lastName || '').trim();
        const email = normalizeEmail(req.body.email);
        const password = String(req.body.password || '');
        const company = String(req.body.company || '').trim();

        if (!firstName || !lastName || !email || !password) {
            return res.status(400).json({ error: 'All required fields must be filled.' });
        }

        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            return res.status(400).json({ error: 'Please enter a valid email address.' });
        }

        if (password.length < 8) {
            return res.status(400).json({ error: 'Password must be at least 8 characters.' });
        }

        const authStore = await getAuthStore();
        const [existing] = await db.query(`SELECT id, email FROM ${authStore.table} WHERE email = ? LIMIT 1`, [email]);
        if (existing.length > 0) {
            return res.status(409).json({ error: 'An account with this email already exists. Please sign in.' });
        }

        const username = await getAvailableUsername(buildUsername(email), authStore.table);
        const passwordHash = hashPassword(password);
        const fullName = `${firstName} ${lastName}`.trim();

        const [result] = authStore.table === 'employees'
            ? await db.query(`
                INSERT INTO employees (email, username, password, full_name, role, status, is_active, phone, job_title, notes)
                VALUES (?, ?, ?, ?, 'vendor', 'active', 1, '', 'Marketplace User', ?)
            `, [email, username, passwordHash, fullName, company || 'Electava Marketplace'])
            : await db.query(`
                INSERT INTO users (email, username, password_hash, full_name, role, status, phone, job_title, notes)
                VALUES (?, ?, ?, ?, 'vendor', 'active', '', 'Marketplace User', ?)
            `, [email, username, passwordHash, fullName, company || 'Electava Marketplace']);

        const [vendorTables] = await db.query("SHOW TABLES LIKE 'vendors'");
        if (vendorTables.length > 0) {
            await db.query(`
                INSERT INTO vendors (user_id, company_name, contact_person, is_approved)
                VALUES (?, ?, ?, 0)
            `, [result.insertId, company || `${fullName}'s Company`, fullName]);
        }

        const [rows] = await db.query(`SELECT * FROM ${authStore.table} WHERE id = ? LIMIT 1`, [result.insertId]);
        const user = mapUser(rows[0]);

        res.status(201).json({
            success: true,
            message: 'Account created successfully. You can now sign in.',
            user,
        });
    } catch (error) {
        console.error('Register Error:', error);
        res.status(500).json({ error: 'Unable to create account right now. Please try again.' });
    }
});

// POST /api/auth/login
router.post('/auth/login', async (req, res) => {
    try {
        const login = normalizeEmail(req.body.email || req.body.login);
        const password = String(req.body.password || '');

        if (!login || !password) {
            return res.status(400).json({ error: 'Email and password are required.' });
        }

        const authStore = await getAuthStore();
        const [rows] = authStore.table === 'employees'
            ? await db.query(`
                SELECT id, email, username, password AS password_hash, full_name, role, status, is_active, phone, job_title, notes, created_at
                FROM employees
                WHERE email = ? OR username = ?
                LIMIT 1
            `, [login, login])
            : await db.query(`
                SELECT id, email, username, password_hash, full_name, role, status, 1 AS is_active, phone, job_title, notes, created_at
                FROM users
                WHERE email = ? OR username = ?
                LIMIT 1
            `, [login, login]);

        if (rows.length === 0 || !verifyPassword(password, rows[0].password_hash)) {
            return res.status(401).json({ error: 'Invalid email or password.' });
        }

        if (rows[0].status !== 'active' && rows[0].is_active !== 1) {
            return res.status(403).json({ error: 'Your account is inactive. Please contact Electava support.' });
        }

        await db.query(`UPDATE ${authStore.table} SET last_login_at = NOW() WHERE id = ?`, [rows[0].id]);

        res.json({
            success: true,
            message: 'Signed in successfully.',
            user: mapUser(rows[0]),
        });
    } catch (error) {
        console.error('Login Error:', error);
        res.status(500).json({ error: 'Unable to sign in right now. Please try again.' });
    }
});

// GET /api/components
router.get('/components', async (req, res) => {
    try {
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
            WHERE c.status = 'active' OR c.status IS NULL OR c.status = 'draft'
        `;
        const [rows] = await db.query(query);
        const mapped = rows.map(mapComponent);
        res.json(mapped);
    } catch (error) {
        console.error(error);
        res.status(500).json({ error: error.message });
    }
});

// GET /api/components/:id
router.get('/components/:id', async (req, res) => {
    try {
        let dbId = req.params.id;
        // if they passed 'prod-001', parse out the number
        if (dbId.startsWith('prod-')) {
            dbId = parseInt(dbId.split('-')[1], 10);
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
            WHERE c.id = ?
        `;
        const [rows] = await db.query(query, [dbId]);
        if (rows.length === 0) return res.status(404).json({ error: 'Component not found' });
        
        res.json(mapComponent(rows[0]));
    } catch (error) {
        console.error(error);
        res.status(500).json({ error: 'Database error' });
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
        res.status(500).json({ error: 'Database error fetching categories' });
    }
});

// GET /api/manufacturers
router.get('/manufacturers', async (req, res) => {
    try {
        const [rows] = await db.query('SELECT * FROM manufacturers');
        res.json(rows);
    } catch (error) {
        console.error(error);
        res.status(500).json({ error: 'Database error' });
    }
});

// POST /api/tracking
router.post('/tracking', async (req, res) => {
    try {
        const { sessionId, deviceType, browser, pageVisited } = req.body;
        const ipAddress = req.headers['x-forwarded-for'] || req.socket.remoteAddress;
        const userAgent = req.headers['user-agent'] || '';
        
        await db.query(`
            INSERT INTO marketplace_tracking 
            (session_id, ip_address, user_agent, device_type, browser, page_visited) 
            VALUES (?, ?, ?, ?, ?, ?)
        `, [sessionId, ipAddress, userAgent, deviceType, browser, pageVisited]);
        
        res.json({ success: true });
    } catch (error) {
        console.error('Tracking Error:', error);
        res.status(500).json({ error: 'Failed to record tracking' });
    }
});

// POST /api/service-token
router.post('/service-token', async (req, res) => {
    try {
        const { userEmail, serviceType, details } = req.body;
        // Generate a random token number like SRV-2026-ABCD
        const randomString = Math.random().toString(36).substring(2, 6).toUpperCase();
        const tokenNumber = `SRV-2026-${randomString}`;
        
        await db.query(`
            INSERT INTO service_tokens (token_number, user_email, service_type, details)
            VALUES (?, ?, ?, ?)
        `, [tokenNumber, userEmail, serviceType, details]);
        
        res.json({ success: true, token: tokenNumber });
    } catch (error) {
        console.error('Service Token Error:', error);
        res.status(500).json({ error: 'Failed to generate token' });
    }
});

// GET /api/careers
router.get('/careers', async (req, res) => {
    try {
        const [rows] = await db.query('SELECT * FROM careers WHERE status = "active" ORDER BY created_at DESC');
        const formatted = rows.map(r => ({
            id: r.id,
            title: r.title,
            team: r.team,
            location: r.location,
            type: r.type,
            summary: r.summary,
            highlights: JSON.parse(r.highlights_json || '[]')
        }));
        res.json(formatted);
    } catch (error) {
        console.error('Careers Error:', error);
        res.status(500).json({ error: 'Failed to fetch careers' });
    }
});

module.exports = router;
