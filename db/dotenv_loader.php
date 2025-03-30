<?php
// dotenv_loader.php
// Загружаем переменные окружения из .env в $_ENV и через putenv().

$envPath = __DIR__ . '/.env'; 
// Если .env лежит не тут, скорректируйте путь (например, на уровень выше).

if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Пропускаем комментарии
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        $pair = explode('=', $line, 2);
        if (count($pair) === 2) {
            $key = trim($pair[0]);
            $value = trim($pair[1]);
            // Ставим в $_ENV и в окружение
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}
