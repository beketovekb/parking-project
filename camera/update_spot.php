<?php
// Для принятия сырых JSON-данных
$input = file_get_contents("php://input");
$_POST = json_decode($input, true) ?? [];

// Подключаем конфиг
require_once '../db/config.php';

// Читаем токен из окружения
$secretToken = getenv('SECRET_TOKEN') ?: '';

// Читаем параметры
$action = $_POST['action'] ?? '';
$token  = $_POST['token']  ?? '';

// Проверяем токен
if ($token !== $secretToken) {
    http_response_code(403); // Forbidden
    echo json_encode(['error'=>'Invalid token']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// ------------------ Проверяем метод запроса ------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error'=>'Use POST method']);
    exit;
}

// ------------------ Читаем POST-параметры ------------------
$camera_name = $_POST['camera_name'] ?? '';
$spot_id     = $_POST['spot_id']     ?? '';
$country     = $_POST['country']     ?? '';
$region      = $_POST['region']      ?? '';
$city        = $_POST['city']        ?? '';
$street      = $_POST['street']      ?? '';
$coords      = $_POST['coords']      ?? '';
$is_busy     = $_POST['is_busy']     ?? '0'; // '0' или '1'

// ------------------ Минимальная проверка ------------------
if ($camera_name === '' || $spot_id === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing camera_name or spot_id']);
    exit;
}

// Дополнительно проверим coords (формат "lat,lng")
if ($coords !== '') {
    $parts = explode(',', $coords);
    if (count($parts) !== 2) {
        http_response_code(400);
        echo json_encode(['error'=>'Invalid coords format']);
        exit;
    }
    $lat = trim($parts[0]);
    $lng = trim($parts[1]);
    if (!is_numeric($lat) || !is_numeric($lng)) {
        http_response_code(400);
        echo json_encode(['error'=>'Coords must be numeric']);
        exit;
    }
}

// ------------------ Создаём таблицу, если нет ------------------
try {
    $create_sql = "
        CREATE TABLE IF NOT EXISTS camera_spots (
            camera_name VARCHAR(255) NOT NULL,
            country     VARCHAR(255),
            region      VARCHAR(255),
            city        VARCHAR(255),
            street      VARCHAR(255),
            coords      VARCHAR(255),
            spot_id     VARCHAR(255) NOT NULL,
            is_busy     TINYINT(1) NOT NULL,
            PRIMARY KEY (camera_name, spot_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $pdo->exec($create_sql);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error'=>'DB error','details'=>$e->getMessage()]);
    exit;
}

// ------------------ Если action=delete => удаляем ------------------
if ($action === 'delete') {
    try {
        $sql = "DELETE FROM camera_spots WHERE camera_name=:camera_name AND spot_id=:spot_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':camera_name' => $camera_name,
            ':spot_id'     => $spot_id
        ]);
        echo json_encode(['status'=>'OK']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error'=>'DB delete error','details'=>$e->getMessage()]);
    }
    exit;
}

// ------------------ Иначе upsert (INSERT ON DUPLICATE KEY UPDATE) ------------------
try {
    // Преобразуем is_busy в 0/1
    $is_busy_val = ($is_busy === '1') ? 1 : 0;

    $sql = "
      INSERT INTO camera_spots (
        camera_name, country, region, city, street, coords,
        spot_id, is_busy
      )
      VALUES (
        :camera_name, :country, :region, :city, :street, :coords,
        :spot_id, :is_busy
      )
      ON DUPLICATE KEY UPDATE
        is_busy  = VALUES(is_busy),
        country  = VALUES(country),
        region   = VALUES(region),
        city     = VALUES(city),
        street   = VALUES(street),
        coords   = VALUES(coords)
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':camera_name' => $camera_name,
        ':country'     => $country,
        ':region'      => $region,
        ':city'        => $city,
        ':street'      => $street,
        ':coords'      => $coords,
        ':spot_id'     => $spot_id,
        ':is_busy'     => $is_busy_val
    ]);

    echo json_encode(['status'=>'OK']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'DB error',
        'details' => $e->getMessage()
    ]);
}
