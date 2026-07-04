<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, X-User-Id");

include_once '../../../config/database.php';
include_once '../../Middleware/authorizeLandlord.php';

$database = new Database();
$db = $database->getConnection();

// Lấy caller ID từ Header hoặc GET
$headers = getallheaders();
$checkId = $_GET['user_id'] ?? $headers['X-User-Id'] ?? $headers['x-user-id'] ?? 0;

$caller = ensureLandlordOrAdmin($db, $checkId);

if (!isset($caller['Role']) || $caller['Role'] !== 'Admin') {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Forbidden: Admin only."], JSON_UNESCAPED_UNICODE);
    exit();
}

// Xử lý tham số include_deleted
$includeDeleted = isset($_GET['include_deleted']) && ($_GET['include_deleted'] === '1' || $_GET['include_deleted'] === 'true');

try {
    $query = "SELECT Id, TenNha, DiaChi, GiayToPhapLy, MaQL, IsApproved, IsDeleted FROM NhaTro WHERE 1=1";

    if (!$includeDeleted) {
        // Nếu không yêu cầu bao gồm đồ đã xóa, mặc định chỉ hiện đồ đang hoạt động
        $query .= " AND IsDeleted = 0";
    }

    $query .= " ORDER BY Id DESC";

    $stmt = $db->prepare($query);
    $stmt->execute();

    $houses = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $houses[] = [
            'Id' => (int)$row['Id'],
            'TenNha' => $row['TenNha'],
            'DiaChi' => $row['DiaChi'],
            'GiayToPhapLy' => $row['GiayToPhapLy'],
            'MaQL' => (int)$row['MaQL'],
            'IsApproved' => (int)$row['IsApproved'],
            'IsDeleted' => (int)$row['IsDeleted']
        ];
    }

    echo json_encode(["status" => "success", "data" => $houses], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>
