<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../../../config/database.php';
include_once '../../Middleware/authorizeLandlord.php';

$database = new Database();
$db = $database->getConnection();

$data = json_decode(file_get_contents('php://input'), true);

// Authorize caller and require Admin
$caller = ensureLandlordOrAdmin($db, 0);
if (!isset($caller['Role']) || $caller['Role'] !== 'Admin') {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Forbidden: Admin only."], JSON_UNESCAPED_UNICODE);
    exit();
}

if (empty($data['TenNha']) || empty($data['DiaChi']) || empty($data['MaQL'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing required fields: TenNha, DiaChi, MaQL."], JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    $query = "INSERT INTO NhaTro (TenNha, DiaChi, GiayToPhapLy, MaQL, IsApproved, NgayDuyet, IsDeleted) VALUES (:ten, :diachi, :giayto, :maql, :isapproved, NOW(), 0)";
    $stmt = $db->prepare($query);
    $isApproved = isset($data['IsApproved']) ? intval($data['IsApproved']) : 1;
    $stmt->bindParam(':ten', $data['TenNha']);
    $stmt->bindParam(':diachi', $data['DiaChi']);
    $stmt->bindParam(':giayto', $data['GiayToPhapLy']);
    $stmt->bindParam(':maql', $data['MaQL'], PDO::PARAM_INT);
    $stmt->bindParam(':isapproved', $isApproved, PDO::PARAM_INT);
    $stmt->execute();
    $id = $db->lastInsertId();

    echo json_encode(["status" => "success", "message" => "House created.", "Id" => (int)$id], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

?>
