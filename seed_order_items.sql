INSERT INTO order_items (order_id, component_id, quantity, unit_price) VALUES 
((SELECT id FROM orders ORDER BY id DESC LIMIT 5,1), 1, 5, 10.00),
((SELECT id FROM orders ORDER BY id DESC LIMIT 4,1), 1, 10, 5.00),
((SELECT id FROM orders ORDER BY id DESC LIMIT 3,1), 1, 50, 0.50);
