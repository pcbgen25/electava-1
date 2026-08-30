INSERT INTO orders (customer_id, total, status, created_at, updated_at) VALUES 
(9, 342.50, 'processing', '2026-08-18 10:00:00', '2026-08-18 10:00:00'),
(9, 89.20, 'shipped', '2026-08-12 11:30:00', '2026-08-12 11:30:00'),
(9, 45.00, 'delivered', '2026-07-29 14:15:00', '2026-07-29 14:15:00'),
(9, 128.75, 'delivered', '2026-07-15 09:20:00', '2026-07-15 09:20:00'),
(9, 75.00, 'cancelled', '2026-06-30 16:45:00', '2026-06-30 16:45:00'),
(9, 612.00, 'delivered', '2026-06-10 08:10:00', '2026-06-10 08:10:00');

-- Let's just create some dummy items for order 1
INSERT INTO order_items (order_id, component_id, quantity, unit_price) VALUES 
((SELECT id FROM orders ORDER BY id DESC LIMIT 5,1), 1, 5, 10.00),
((SELECT id FROM orders ORDER BY id DESC LIMIT 5,1), 2, 10, 5.00),
((SELECT id FROM orders ORDER BY id DESC LIMIT 5,1), 3, 50, 0.50);
