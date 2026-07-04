<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, X-Caller-Id, caller-id");

$headers = getallheaders();

// Bóc tách ID người thực hiện xóa từ Header HTTP (Ví dụ truyền lên X-Caller-Id là 1 hoặc 2)
$nguoiXoaId = isset($headers['X-Caller-Id']) ? intval($headers['X-Caller-Id']) : 
              (isset($headers['caller-id']) ? intval($headers['caller-id']) : null);

include_once '../../config/database.php';
include_once 'Tenant.php';
include_once '../Middleware/authorizeLandlord.php';

$database = new Database();
$db = $database->getConnection();
$tenant = new Tenant($db);

$data = json_decode(file_get_contents("php://input"), true);

// Nếu không tìm thấy ở Header, tìm tiếp trong thuộc tính JSON body gửi lên
if (!$nguoiXoaId && isset($data['NguoiXoaId'])) {
    $nguoiXoaId = intval($data['NguoiXoaId']);
}

// Kiểm tra nếu hoàn toàn không truyền ID thì báo lỗi chặn lại luôn
if (!$nguoiXoaId) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Thiếu ID người thực hiện xóa (X-Caller-Id hoặc NguoiXoaId)."], JSON_UNESCAPED_UNICODE);
    exit();
}

if(!empty($data['KhachHangId'])) {
    // Xác thực quyền (Chỉ cho phép Admin[1] hoặc ChuTro[2] đi tiếp)
    ensureLandlordOrAdmin($db, $nguoiXoaId);

    // Tiến hành gọi hàm xóa kèm theo ID cụ thể của người xóa
    if($tenant->delete($data['KhachHangId'], $nguoiXoaId)) {
        http_response_code(200);
        echo json_encode([
            "status" => "success", 
            "message" => "Đã xóa khách thuê thành công.",
            "NguoiXoaId_GhiNhand" => $nguoiXoaId // Trả về thông tin ID đã ghi nhận để tiện kiểm tra
        ], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Lỗi hệ thống không thể xóa khách thuê."], JSON_UNESCAPED_UNICODE);
    }
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Thiếu mã khách hàng KhachHangId."], JSON_UNESCAPED_UNICODE);
}
?>