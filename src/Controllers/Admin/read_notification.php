<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../../../config/database.php';
include_once '../../Middleware/authorizeLandlord.php';

$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    http_response_code(500);
    echo json_encode(array("status" => "error", "message" => "Không thể kết nối cơ sở dữ liệu."));
    exit();
}

// Kiểm tra quyền Admin
$headers = getallheaders();
$adminId = $headers['X-User-Id'] ?? $headers['x-user-id'] ?? 0;
$caller = ensureLandlordOrAdmin($db, $adminId);

if (!isset($caller['Role']) || $caller['Role'] !== 'Admin') {
    http_response_code(403);
    echo json_encode(array("status" => "error", "message" => "Forbidden: Admin only."));
    exit();
}

$data = json_decode(file_get_contents("php://input"));

if(empty($data->id)) {
    http_response_code(400);
    echo json_encode(array("status" => "error", "message" => "Thiếu ID thông báo."));
    exit();
}

try {
    // Kiểm tra notification có tồn tại không
    $checkQuery = "SELECT id FROM Notifications WHERE id = :id LIMIT 1";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(':id', $data->id, PDO::PARAM_INT);
    $checkStmt->execute();
    
    if ($checkStmt->rowCount() == 0) {
        http_response_code(404);
        echo json_encode(array("status" => "error", "message" => "Thông báo không tồn tại."));
        exit();
    }
    
    $query = "UPDATE Notifications SET TrangThai = 1 WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $data->id, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        http_response_code(200);
        echo json_encode(array("status" => "success", "message" => "Đánh dấu đã đọc thành công."));
    } else {
        http_response_code(500);
        echo json_encode(array("status" => "error", "message" => "Không thể cập nhật thông báo."));
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(array("status" => "error", "message" => "Lỗi cơ sở dữ liệu: " . $e->getMessage()));
}
?>