<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Đọc dữ liệu JSON gửi từ Flutter lên
$data = json_decode(file_get_contents("php://input"), true);

// Phải nhận được cả id thông báo và user_id của khách thuê
if (!empty($data['id']) && !empty($data['user_id'])) {
    try {
        // 🔥 ĐÃ SỬA: Sử dụng ON DUPLICATE KEY UPDATE dựa trên cặp Unique (ThongBaoId, UserId)
        // Đảm bảo trạng thái đã xem của mỗi khách thuê là hoàn toàn biệt lập với nhau
        $query = "INSERT INTO ThongBao_User (ThongBaoId, UserId, TrangThai, NgayXem) 
                  VALUES (:thongBaoId, :userId, 1, NOW())
                  ON DUPLICATE KEY UPDATE TrangThai = 1, NgayXem = NOW()";
        
        $stmt = $db->prepare($query);
        
        if ($stmt->execute([
            ':thongBaoId' => intval($data['id']),
            ':userId' => intval($data['user_id'])
        ])) {
            http_response_code(200);
            echo json_encode([
                "status" => "success", 
                "message" => "Đã ghi nhận xem thông báo cá nhân thành công!"
            ], JSON_UNESCAPED_UNICODE);
        } else {
            throw new Exception("Không thể cập nhật trạng thái vào cơ sở dữ liệu.");
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Lỗi xử lý Database: " . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
} else {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Dữ liệu gửi lên không hợp lệ. Yêu cầu truyền 'id' và 'user_id'."
    ], JSON_UNESCAPED_UNICODE);
}
?>