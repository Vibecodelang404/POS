<?php
require_once __DIR__ . '/../app/config.php';
requireLogin();

header('Content-Type: application/json');
http_response_code(403);
echo json_encode([
    'success' => false,
    'error' => 'Staff role is inventory-only. POS transactions are handled by cashier accounts.'
]);
exit();
?>
