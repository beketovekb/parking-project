<?php
session_start();
require_once '../db/config.php';

// Генерация CSRF-токена (храним его в сессии)
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
// Проверка CSRF-токена
function checkCsrfToken($token) {
    return (isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token));
}

// Если пользователь уже авторизован, переходим на карту
if (isset($_SESSION['user_id'])) {
    header('Location: map.php');
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Проверяем токен
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!checkCsrfToken($csrfToken)) {
        $error = "Неверный CSRF-токен!";
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        // Ищем пользователя в БД
        $stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE username = :u");
        $stmt->execute([':u' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Проверяем пароль (через password_verify)
            if (password_verify($password, $user['password_hash'])) {
                // Генерируем новый идентификатор сессии (защита от фиксации)
                session_regenerate_id(true);

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
}

// Генерируем CSRF для формы
$token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8"/>
  <title>Авторизация | ParkEasy</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no" />
  <link 
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
  />
</head>
<body class="bg-light d-flex align-items-center" style="height: 100vh;">
<div class="container">
  <div class="row justify-content-center">
    <div class="col-12 col-sm-8 col-md-6 col-lg-4">
      <div class="card shadow-sm">
        <div class="card-body">
          <h2 class="text-center mb-4">ParkEasy</h2>

          <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
              <?= htmlspecialchars($error) ?>
            </div>
          <?php endif; ?>

          <form method="post">
            <!-- CSRF-токен -->
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token) ?>">

            <div class="mb-3">
              <label for="username" class="form-label">Логин</label>
              <input 
                type="text" 
                class="form-control" 
                id="username"
                name="username"
                required
                autofocus
              />
            </div>
            <div class="mb-3">
              <label for="password" class="form-label">Пароль</label>
              <input
                type="password"
                class="form-control"
                id="password"
                name="password"
                required
              />
            </div>
            <button type="submit" class="btn btn-primary w-100">Войти</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<script 
  src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js">
</script>
</body>
</html>
