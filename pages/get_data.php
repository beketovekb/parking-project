<?php
session_start();
// if (!isset($_SESSION['user_id'])) {
//   http_response_code(401);
//   echo json_encode(["error" => "Unauthorized"]);
//   exit();
// }

require_once '../db/config.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $sql = "SELECT camera_name, coords, spot_id, is_busy
              FROM camera_spots
          ORDER BY camera_name, spot_id";
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Группируем по coords
    $grouped = [];
    foreach ($rows as $row) {
        $coords = $row['coords'] ?? '';
        if (!$coords) continue;

        if (!isset($grouped[$coords])) {
            $grouped[$coords] = [
                'coords' => $coords,
                'spots'  => []
            ];
        }
        $grouped[$coords]['spots'][] = [
            'spot_id' => $row['spot_id'],
            'is_busy' => (bool)$row['is_busy']
        ];
    }

    // Подсчитываем total, free
    foreach ($grouped as &$item) {
        $item['total'] = count($item['spots']);
        $freeCount = 0;
        foreach ($item['spots'] as $s) {
            if (!$s['is_busy']) {
                $freeCount++;
            }
        }
        $item['free'] = $freeCount;
    }

    // Делать array_values, чтобы ключи были индексными
    $data = array_values($grouped);

    echo json_encode($data);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "DB Error: " . $e->getMessage()]);
}
