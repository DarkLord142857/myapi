<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, X-Caller-Id, caller-id");

$headers = getallheaders();
// 🟢 BƯỚC 1: Tìm ID người gửi từ Header trước (Giống CreateRoom.php)
$callerId = isset($headers['X-Caller-Id']) ? intval($headers['X-Caller-Id']) : 
            (isset($headers['caller-id']) ? intval($headers['caller-id']) : null);

include_once '../../config/database.php';
include_once 'Tenant.php';
include_once '../Middleware/authorizeLandlord.php';

$database = new Database();
$db = $database->getConnection();
$tenant = new Tenant($db);

$data = json_decode(file_get_contents("php://input"), true);

// 🟢 BƯỚC 2: Nếu Header không có, bổ sung tìm trong mảng JSON Body $data gửi từ Flutter lên
if (!$callerId && isset($data['NguoiGuiId'])) {
    $callerId = intval($data['NguoiGuiId']);
}
if (!$callerId) { 
    $callerId = 1; // Fallback mặc định là ID 1 nếu không truyền gì lên để tránh lỗi sập
}

if(
    !empty($data['Username']) && !empty($data['Password']) && !empty($data['FullName']) &&
    !empty($data['PhongTroId']) && !empty($data['NgayBatDau']) && !empty($data['NgayKetThuc'])
) {
    // 🟢 BƯỚC 3: Truyền $callerId đã bóc tách sạch sẽ vào Middleware kiểm tra quyền chủ trọ
    ensureLandlordOrAdmin($db, $callerId);

    $result = $tenant->create($data);
    if($result['status']) {
        http_response_code(201);
        echo json_encode(["status" => "success", "message" => "Thêm khách thuê thành công.", "KhachHangId" => $result['id']], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => $result['message']]);
    }
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Thiếu thông tin bắt buộc."], JSON_UNESCAPED_UNICODE);
}
?>