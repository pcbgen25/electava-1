<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=electava_workspace', 'root', '');
$res = $pdo->query("SELECT id, email, username, password, is_active FROM employees ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
print_r($res);
