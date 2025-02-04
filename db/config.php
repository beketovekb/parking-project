<?php
$db_host = 'localhost';
$db_port = 3306;
$db_name = 'p-347274_easypark';
$db_user = 'p-347274_admin';
$db_pass = '341833@Bekzat';

try {
    // DSN для MariaDB (MySQL)
    $dsn = "mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4";
    $pdo = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    die("Ошибка подключения к БД: " . $e->getMessage());
}
