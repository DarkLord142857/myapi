<?php
/**
 * Simple Router cho myapi
 * - Phong cách từng controller có thể chạy độc lập
 * - Hỗ trợ route: POST /api/rooms/{id}/images/{imageId}/delete
 */
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$basePath = '/myapi';
if (strpos($path, $basePath) === 0) {
    $path = substr($path, strlen($basePath));
}
if (strpos($path, '/public') === 0) {
    $path = substr($path, strlen('/public'));
}
if ($path === '' || $path === '/') {
    http_response_code(200);
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode([
        "status" => "success",
        "message" => "myapi is running. Use /api/... endpoints."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$routes = [
    [
        'method'  => 'GET',
        'pattern' => '#^/api/rooms/?$#',
        'file'    => __DIR__ . '/../src/Controllers/GetRoom.php',
        'pre'     => null
    ],
    [
        'method'  => 'POST',
        'pattern' => '#^/api/rooms/(\d+)/images/(\d+)/delete$#',
        'file'    => __DIR__ . '/../src/Controllers/DeleteRoomImage.php',
        'pre'     => function ($matches) {
            $_POST['HinhAnhId'] = intval($matches[2]);
        }
    ],
    [
        'method'  => 'POST',
        'pattern' => '#^/api/delete-room-image$#',
        'file'    => __DIR__ . '/../src/Controllers/DeleteRoomImage.php',
        'pre'     => null
    ],
];

foreach ($routes as $route) {
    if ($route['method'] === $method && preg_match($route['pattern'], $path, $matches)) {
        if (is_callable($route['pre'])) {
            $route['pre']($matches);
        }
        require_once $route['file'];
        exit;
    }
}

http_response_code(404);
header("Content-Type: application/json; charset=UTF-8");
echo json_encode([
    "status" => "error",
    "message" => "Endpoint không tồn tại: $method $path"
], JSON_UNESCAPED_UNICODE);