<?php
session_start();
require_once '../db/config.php';

// Если уже залогинен, перенаправляем на карту
if (isset($_SESSION['user_id'])) {
    header('Location: map.php');
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Ищем пользователя
    $stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE username = :u");
    $stmt->execute([':u' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Проверяем пароль (через password_verify)
        if (password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $username;
            header('Location: map.php');
            exit();
        } else {
            $error = "Неверный пароль";
        }
    } else {
        $error = "Пользователь не найден";
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8"/>
  <title>Авторизация</title>
</head>
<body>
<h1>Авторизация</h1>
<?php if ($error): ?>
  <p style="color:red;"><?= $error ?></p>
<?php endif; ?>
<form method="post">
  <div>
    <label>Логин: <input type="text" name="username" required></label>
  </div>
  <div>
    <label>Пароль: <input type="password" name="password" required></label>
  </div>
  <button type="submit">Войти</button>
</form>
</body>
</html>
