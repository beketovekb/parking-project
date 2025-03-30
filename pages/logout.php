<?php
session_start();

// Очистка массива сессий
$_SESSION = [];

// Удаляем cookie сессии (для безопасности, если нужно)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Уничтожаем саму сессию
session_destroy();

// Перенаправляем
header('Location: login.php');
exit();
