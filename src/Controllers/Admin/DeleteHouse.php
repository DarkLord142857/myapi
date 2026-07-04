<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: DELETE");
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

if (empty($data['Id'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing Id to delete."], JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    $adminId = isset($caller['id']) ? intval($caller['id']) : 0;
    $query = "UPDATE NhaTro SET IsDeleted = 1, NguoiXoaId = :adminId WHERE Id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':adminId', $adminId, PDO::PARAM_INT);
    $stmt->bindParam(':id', $data['Id'], PDO::PARAM_INT);
    $stmt->execute();

    echo json_encode(["status" => "success", "message" => "House soft-deleted.", "affected" => $stmt->rowCount()], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

?>
