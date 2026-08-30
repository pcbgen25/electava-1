-- Step 1: Add 'marketplace_user' role to users table
ALTER TABLE users MODIFY COLUMN role ENUM('core_admin','admin','employee','vendor','marketplace_user') NOT NULL;

-- Step 2: Update pcbgen25@gmail.com in users to be a marketplace_user
UPDATE users SET role = 'marketplace_user', job_title = 'Marketplace User' WHERE email = 'pcbgen25@gmail.com';

-- Step 3: Move existing orders from employee id 9 to user id 2 (both are pcbgen25@gmail.com)
UPDATE orders SET customer_id = 2 WHERE customer_id = 9;

-- Step 4: Fix orders FK — now point it to users table
ALTER TABLE orders ADD CONSTRAINT orders_fk_user FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE SET NULL;
