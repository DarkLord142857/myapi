<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

include_once '../../config/database.php';
include_once 'Tenant.php';

$database = new Database();
$db = $database->getConnection();

$tenant = new Tenant($db);
$stmt = $tenant->read();
$num = $stmt->rowCount();

if($num > 0) {
    $tenants_arr = array();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        extract($row);
        $tenant_item = array(
            "KhachHangId" => $KhachHangId,
            "Username" => $Username,
            "FullName" => $FullName,
            "IdentityCard" => $IdentityCard,
            "PhoneNumber" => $PhoneNumber,
            "Email" => $Email,
            "IsApproved" => $IsApproved,
            "PhongTroId" => $PhongTroId,
            "SoPhong" => $SoPhong,
            "GiaPhong" => $GiaPhong,
            "TenNha" => $TenNha,
            "HopDongId" => $HopDongId,
            "NgayBatDau" => $NgayBatDau,
            "NgayKetThuc" => $NgayKetThuc,
            "TienCoc" => $TienCoc,
            "IsActive" => $IsActive
        );
        array_push($tenants_arr, $tenant_item);
    }
    http_response_code(200);
    echo json_encode(["status" => "success", "data" => $tenants_arr]);
} else {
    http_response_code(200);
    echo json_encode(["status" => "success", "data" => []]);
}
?>