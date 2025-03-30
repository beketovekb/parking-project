<?php
// config.php

require_once __DIR__ . '/dotenv_loader.php';

// Забираем переменные окружения
$db_host = getenv('DB_HOST');
$db_port = getenv('DB_PORT');
$db_name = getenv('DB_NAME');
$db_user = getenv('DB_USER');
$db_pass = getenv('DB_PASS');

try {
    $dsn = "mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4";
    $pdo = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    // В рабочем окружении желательно писать в лог, а не выводить
    die("Ошибка подключения к БД: " . $e->getMessage());
}
