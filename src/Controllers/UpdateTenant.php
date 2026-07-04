
<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: PUT");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, X-Caller-Id, caller-id");

$headers = getallheaders();
// 🟢 BƯỚC 1: Tìm ID người thực hiện từ Header
$callerId = isset($headers['X-Caller-Id']) ? intval($headers['X-Caller-Id']) : 
            (isset($headers['caller-id']) ? intval($headers['caller-id']) : null);

include_once '../../config/database.php';
include_once 'Tenant.php';
include_once '../Middleware/authorizeLandlord.php';

$database = new Database();
$db = $database->getConnection();
$tenant = new Tenant($db);

$data = json_decode(file_get_contents("php://input"), true);

// 🟢 BƯỚC 2: Nếu Header không có, tìm trong JSON body 'caller_id' hoặc 'NguoiGuiId'
if (!$callerId && isset($data['caller_id'])) {
    $callerId = intval($data['caller_id']);
} elseif (!$callerId && isset($data['NguoiGuiId'])) {
    $callerId = intval($data['NguoiGuiId']);
}
if (!$callerId) {
    $callerId = 1;
}

if(!empty($data['id']) && !empty($data['FullName'])) {
    // 🟢 BƯỚC 3: Chạy auth check cho chủ trọ
    ensureLandlordOrAdmin($db, $callerId);

    if($tenant->update($data)) {
        http_response_code(200);
        echo json_encode(["status" => "success", "message" => "Thông tin khách thuê đã được cập nhật."], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(200);
        echo json_encode(["status" => "info", "message" => "Không có thay đổi nào được thực hiện."], JSON_UNESCAPED_UNICODE);
    }
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Thiếu mã khách hàng (id) hoặc Họ tên (FullName)."], JSON_UNESCAPED_UNICODE);
}
?>