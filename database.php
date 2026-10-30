
<?php

$host = "localhost";
$dbname = "ekhtary_tareeqak";
$username = "root";
$password = "";

try {
    $conn = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );

    $conn->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

} catch (PDOException $e) {
    die("تعذر الاتصال بقاعدة البيانات.");
}

require_once __DIR__ . '/analytics.php';
record_site_analytics($conn);