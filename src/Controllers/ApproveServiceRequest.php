<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../../config/database.php';
include_once '../Middleware/authorizeLandlord.php';

$database = new Database();
$db = $database->getConnection();

// Đọc dữ liệu JSON gửi từ Flutter lên (chứa ID yêu cầu)
$data = json_decode(file_get_contents("php://input"), true);

// Authorize caller (allow Admin). Middleware will read X-User-Id header or use provided caller_id
ensureLandlordOrAdmin($db, isset($data['caller_id']) ? intval($data['caller_id']) : 0);

if (!empty($data['id'])) {
    try {
        // 🔥 CẬP NHẬT: Chuyển trạng thái từ 0 sang 1 trong bảng YeuCauDichVu
        $query = "UPDATE YeuCauDichVu SET TrangThai = 1 WHERE Id = :id";
        $stmt = $db->prepare($query);
        
        if ($stmt->execute([':id' => $data['id']])) {
            http_response_code(200);
            echo json_encode(["status" => "success", "message" => "Đã ghi nhận giải quyết yêu cầu!"]);
        } else {
            throw new Exception("Không thể cập nhật cơ sở dữ liệu.");
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Thiếu ID yêu cầu dịch vụ."]);
}
?>