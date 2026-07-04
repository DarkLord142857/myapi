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
        // Sử dụng INSERT IGNORE để nếu khách trọ bấm lại nhiều lần vào 1 thông báo cũng không bị lỗi trùng lặp khóa (Unique Key)
        $query = "INSERT IGNORE INTO ThongBao_User (ThongBaoId, UserId, TrangThai, NgayXem) 
                  VALUES (:thongBaoId, :userId, 1, NOW())";
        
        $stmt = $db->prepare($query);
        
        if ($stmt->execute([
            ':thongBaoId' => $data['id'],
            ':userId' => $data['user_id']
        ])) {
            http_response_code(200);
            echo json_encode(["status" => "success", "message" => "Đã ghi nhận xem thông báo cá nhân thành công!"]);
        } else {
            throw new Exception("Không thể cập nhật trạng thái vào cơ sở dữ liệu.");
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Thiếu thông tin ID thông báo hoặc ID người dùng."]);
}
?>