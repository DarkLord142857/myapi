<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: PUT");
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
    echo json_encode(["status" => "error", "message" => "Missing Id to update."], JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    $fields = [];
    $params = [':id' => intval($data['Id'])];
    if (isset($data['TenNha'])) { $fields[] = 'TenNha = :ten'; $params[':ten'] = $data['TenNha']; }
    if (isset($data['DiaChi'])) { $fields[] = 'DiaChi = :diachi'; $params[':diachi'] = $data['DiaChi']; }
    if (isset($data['GiayToPhapLy'])) { $fields[] = 'GiayToPhapLy = :giayto'; $params[':giayto'] = $data['GiayToPhapLy']; }
    if (isset($data['MaQL'])) { $fields[] = 'MaQL = :maql'; $params[':maql'] = intval($data['MaQL']); }
    if (isset($data['IsApproved'])) { $fields[] = 'IsApproved = :isapproved'; $params[':isapproved'] = intval($data['IsApproved']); }

    if (empty($fields)) {
        echo json_encode(["status" => "info", "message" => "No fields to update."], JSON_UNESCAPED_UNICODE);
        exit();
    }

    $setClause = implode(', ', $fields) . ", NgaySua = NOW()";
    $query = "UPDATE NhaTro SET " . $setClause . " WHERE Id = :id";
    $stmt = $db->prepare($query);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->execute();

    echo json_encode(["status" => "success", "message" => "House updated.", "affected" => $stmt->rowCount()], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

?>
