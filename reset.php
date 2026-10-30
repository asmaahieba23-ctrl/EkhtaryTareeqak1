<?php
$hasResetToken = isset($_GET['token']) || isset($_POST['token']);

if ($hasResetToken) {
    require __DIR__ . '/reset_password.php';
} else {
    require __DIR__ . '/forgot_password.php';
}
