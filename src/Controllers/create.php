<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../../config/database.php';
include_once 'Tenant.php';

$database = new Database();
$db = $database->getConnection();
$tenant = new Tenant($db);   

$data = json_decode(file_get_contents("php://input"), true);

// Sửa điều kiện: Hoặc có KhachHangId (Đã có tk) hoặc có cụm Username/Password/FullName (Chưa có tk)
$hasExistingAccount = !empty($data['KhachHangId']);
$hasNewAccountInfo = !empty($data['Username']) && !empty($data['Password']) && !empty($data['FullName']);

if(
    ($hasExistingAccount || $hasNewAccountInfo) &&
    !empty($data['PhongTroId']) && !empty($data['NgayBatDau']) && !empty($data['NgayKetThuc'])
) {
    $result = $tenant->create($data);
    if($result['status']) {
        http_response_code(201);
        echo json_encode(["status" => "success", "message" => "Thao tác thành công.", "KhachHangId" => $result['id']]);
    } else {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => $result['message']]);
    }
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Không thể xử lý. Dữ liệu đầu vào không đầy đủ."]);
}
?>