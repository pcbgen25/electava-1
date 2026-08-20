<?php
// Electava Workspace - Mock Data Generator for Full Testing
// This script populates the database with realistic sample data using Faker.

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/db.php';

use Faker\Factory;

$faker = Factory::create();

echo "--- Generating Mock Data for Full Test ---\n";

// 1. Ensure we have users of each role
$roles = ['employee', 'vendor', 'sub_core'];
$domain_ids = [1, 2, 3, 4]; // Marketplace, PCB, Vendor, System

// Create 10 more employees
echo "Creating 10 sample employees...\n";
for ($i = 0; $i < 10; $i++) {
    $username = $faker->unique()->userName;
    $password = password_hash('password', PASSWORD_DEFAULT);
    $domain_id = $faker->randomElement($domain_ids);
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, password, role, domain_id) VALUES (?, ?, 'employee', ?)");
    $stmt->execute([$username, $password, $domain_id]);
}

// Create 10 more vendors
echo "Creating 10 sample vendors & vendor profiles...\n";
for ($i = 0; $i < 10; $i++) {
    $username = $faker->unique()->userName;
    $password = password_hash('password', PASSWORD_DEFAULT);
    
    // Create User
    $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, password, role, domain_id) VALUES (?, ?, 'vendor', 3)");
    $stmt->execute([$username, $password]);
    $user_id = $pdo->lastInsertId();
    
    if ($user_id) {
        // Create Vendor Profile
        $stmt = $pdo->prepare("INSERT IGNORE INTO vendors (user_id, company_name, contact_person, payment_terms, rating) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $user_id, 
            $faker->company, 
            $faker->name, 
            $faker->randomElement(['Net 30', 'Net 60', 'Due on Receipt']), 
            $faker->randomFloat(2, 3, 5)
        ]);
    }
}

// 2. Generate 50 Tasks
echo "Generating 50 sample tasks...\n";
$all_users = $pdo->query("SELECT id FROM users")->fetchAll(PDO::FETCH_COLUMN);
$core_users = $pdo->query("SELECT id FROM users WHERE role IN ('core', 'sub_core')")->fetchAll(PDO::FETCH_COLUMN);

for ($i = 0; $i < 50; $i++) {
    $assigned_to = $faker->randomElement($all_users);
    $created_by = $faker->randomElement($core_users);
    $status = $faker->randomElement(['pending', 'in_progress', 'completed']);
    
    $stmt = $pdo->prepare("INSERT INTO tasks (assigned_to, created_by, title, description, type, status, due_date, priority) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $assigned_to,
        $created_by,
        $faker->sentence(5),
        $faker->paragraph(2),
        $faker->randomElement(['Design Review', 'Component Sourcing', 'Quality Audit', 'Shipping Update']),
        $status,
        $faker->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
        $faker->randomElement(['low', 'medium', 'high', 'critical'])
    ]);
}

// 3. Generate Purchase Orders
echo "Generating 20 sample purchase orders...\n";
$vendor_ids = $pdo->query("SELECT id FROM vendors")->fetchAll(PDO::FETCH_COLUMN);

for ($i = 0; $i < 20; $i++) {
    $stmt = $pdo->prepare("INSERT INTO purchase_orders (vendor_id, order_id, status, tracking_number, shipped_at) VALUES (?, ?, ?, ?, ?)");
    $status = $faker->randomElement(['pending', 'shipped', 'delivered']);
    $stmt->execute([
        $faker->randomElement($vendor_ids),
        $faker->numberBetween(1000, 9999),
        $status,
        ($status !== 'pending') ? $faker->bothify('TRACK-####-????') : null,
        ($status !== 'pending') ? $faker->dateTimeBetween('-10 days', 'now')->format('Y-m-d H:i:s') : null
    ]);
}

echo "\n--- Mock Data Generation Complete! ---\n";
?>
