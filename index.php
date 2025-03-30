<?php
session_start();

// Проверяем, авторизован ли пользователь
if (!isset($_SESSION['user_id'])) {
    // Если нет, отправляем на авторизацию
    header('Location: pages/login.php');
    exit();
} else {
    // Если да, отправляем сразу на карту
    header('Location: pages/map.php');
    exit();
}
