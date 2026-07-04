<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, X-User-Id");

include_once '../../../config/database.php';
include_once '../../Middleware/authorizeLandlord.php';

$database = new Database();
$db = $database->getConnection();

$data = json_decode(file_get_contents('php://input'), true);

// Lấy caller ID từ Header hoặc Body
$headers = getallheaders();
$callerId = $headers['X-User-Id'] ?? $data['user_id'] ?? 0;

// Authorize caller and require Admin
$caller = ensureLandlordOrAdmin($db, $callerId);
if (!isset($caller['Role']) || $caller['Role'] !== 'Admin') {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Forbidden: Admin only."], JSON_UNESCAPED_UNICODE);
    exit();
}

if (empty($data['Id'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing Id to restore."], JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    // Khôi phục bằng cách set IsDeleted = 0
    $query = "UPDATE NhaTro SET IsDeleted = 0, NguoiXoaId = NULL WHERE Id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $data['Id'], PDO::PARAM_INT);
    $stmt->execute();

    echo json_encode(["status" => "success", "message" => "House restored successfully."], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>
