<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('vendor');

$headers = ['part_number', 'name', 'description', 'manufacturer', 'category', 'price', 'stock', 'datasheet_url'];
$rows = [
    ['LM7805CT', '5V Voltage Regulator', 'TO-220 linear regulator for 5V output', 'STMicroelectronics', 'Power Management', '12.50', '500', 'https://example.com/datasheet-lm7805.pdf'],
    ['ESP32-WROOM-32E', 'Wi-Fi MCU Module', '2.4 GHz Wi-Fi and Bluetooth module', 'Espressif', 'Wireless Modules', '185.00', '120', 'https://example.com/datasheet-esp32.pdf'],
];

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="electava_vendor_product_template.csv"');

$output = fopen('php://output', 'wb');
fputcsv($output, $headers);
foreach ($rows as $row) {
    fputcsv($output, $row);
}
fclose($output);
exit;
