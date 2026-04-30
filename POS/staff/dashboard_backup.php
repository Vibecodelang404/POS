<?php
require_once __DIR__ . '/../app/config.php';
requireLogin();

header('Location: ' . BASE_URL . 'staff/manage_product.php');
exit();
?>
