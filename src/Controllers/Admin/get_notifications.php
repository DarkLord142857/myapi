<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../../../config/database.php';
include_once '../../Middleware/authorizeLandlord.php';

$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    http_response_code(500);
    echo json_encode(array("status" => "error", "message" => "Không thể kết nối cơ sở dữ liệu."));
    exit();
}

// Kiểm tra quyền Admin
$headers = getallheaders();
$adminId = $headers['X-User-Id'] ?? $headers['x-user-id'] ?? ($_GET['admin_id'] ?? 0);
$caller = ensureLandlordOrAdmin($db, $adminId);

if (!isset($caller['Role']) || $caller['Role'] !== 'Admin') {
    http_response_code(403);
    echo json_encode(array("status" => "error", "message" => "Forbidden: Admin only."));
    exit();
}

try {
    $query = "SELECT id, NoiDung, TrangThai, CreatedDate FROM Notifications ORDER BY CreatedDate DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();

    $arr = array("status" => "success", "data" => array());
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        array_push($arr["data"], array(
            "id" => (int)$row['id'],
            "noiDung" => $row['NoiDung'],
            "trangThai" => (int)$row['TrangThai'],
            "createdDate" => $row['CreatedDate']
        ));
    }
    http_response_code(200);
    echo json_encode($arr, JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(array("status" => "error", "message" => "Lỗi cơ sở dữ liệu: " . $e->getMessage()));
}
?>