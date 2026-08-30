#!/usr/bin/env node
/**
 * setup-admin.js — First-run admin account setup
 *
 * Creates a core_admin account with a cryptographically random password
 * and sets force_password_change=1 so the admin must change it on first login.
 *
 * Usage:
 *   node API/scripts/setup-admin.js
 *
 * Set environment variables before running:
 *   DB_HOST, DB_USER, DB_PASS, DB_NAME
 */

'use strict';

require('dotenv').config({ path: require('path').join(__dirname, '../.env') });

const mysql  = require('mysql2/promise');
const bcrypt = require('bcryptjs');
const crypto = require('crypto');

async function main() {
    const db = await mysql.createConnection({
        host:     process.env.DB_HOST || '127.0.0.1',
        user:     process.env.DB_USER,
        password: process.env.DB_PASS,
        database: process.env.DB_NAME || 'electava_workspace',
    });

    // Check if admin already exists
    const [existing] = await db.query(
        "SELECT id FROM users WHERE role = 'core_admin' LIMIT 1"
    );
    if (existing.length > 0) {
        console.log('[setup-admin] A core_admin account already exists. Skipping creation.');
        console.log('[setup-admin] To reset: use the workspace UI password-reset flow.');
        await db.end();
        return;
    }

    // Generate random secure credentials
    const tempPassword = crypto.randomBytes(12).toString('base64url');
    const passwordHash = await bcrypt.hash(tempPassword, 12);
    const adminEmail   = process.env.ADMIN_EMAIL || 'admin@electava.com';

    await db.query(
        `INSERT INTO users (email, username, password_hash, full_name, role, status, force_password_change)
         VALUES (?, 'admin', ?, 'Core Administrator', 'core_admin', 'active', 1)`,
        [adminEmail, passwordHash]
    );

    console.log('\n✅ Core admin account created successfully.\n');
    console.log('   Email:    ', adminEmail);
    console.log('   Password: ', tempPassword);
    console.log('\n⚠️  This password is shown ONCE. Save it securely now.');
    console.log('   You will be forced to change it on first login.\n');

    await db.end();
}

main().catch(err => {
    console.error('[setup-admin] Error:', err.message);
    process.exit(1);
});
