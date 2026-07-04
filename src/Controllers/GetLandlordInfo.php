<?php
// Cấu hình Headers cho API JSON
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Import file kết nối database của bạn
include_once '../../config/database.php';
include_once '../Middleware/authorizeLandlord.php';

// Khởi tạo đối tượng Database và kết nối
$database = new Database();
$db = $database->getConnection();

if (!$db) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Không thể kết nối cơ sở dữ liệu."]);
    exit();
}

// Lấy tham số id của chủ trọ truyền lên từ URL Postman (?id=2)
$landlordId = isset($_GET['id']) ? trim($_GET['id']) : '';

if ($landlordId === '') {
    http_response_code(400); // Bad Request
    echo json_encode([
        "success" => false,
        "message" => "Thiếu tham số bắt buộc: id của chủ trọ trên URL (Ví dụ: ?id=2)"
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// Ép kiểu biến về số nguyên (int) để khớp với trường id kiểu BIGINT trong DB
// Authorize caller (Admin allowed)
$landlordIdInt = (int)$landlordId;
ensureLandlordOrAdmin($db, $landlordIdInt);

try {
    // Câu lệnh SQL lấy thông tin chủ trọ (Bỏ cột Password để đảm bảo bảo mật)
    // Lọc theo id và có thể bổ sung điều kiện Role nếu hệ thống của bạn phân quyền chặt chẽ
    $query = "SELECT id, Username, FullName, PhoneNumber, Email, Role, IsApproved, IdentityCard
              FROM Users 
              WHERE id = :id LIMIT 1";

    $stmt = $db->prepare($query);
    
    // Bind số nguyên bảo mật
    $stmt->bindParam(":id", $landlordIdInt, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        // Lấy ra dòng dữ liệu duy nhất tìm được
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // Đóng gói dữ liệu trả về theo đúng định dạng sạch
        $landlord_data = [
            "id" => (int)$row['id'],
            "Username" => $row['Username'],
            "FullName" => $row['FullName'],
            "PhoneNumber" => $row['PhoneNumber'],
            "Email" => $row['Email'],
            "Role" => $row['Role'],
            "IsApproved" => (int)$row['IsApproved'],
            "IdentityCard" => $row['IdentityCard']
        ];

        http_response_code(200);
        echo json_encode([
            "success" => true,
            "message" => "Tải thông tin chủ trọ thành công.",
            "data" => $landlord_data
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    } else {
        http_response_code(200); // Trả về 200 kèm mảng null thông báo không tìm thấy
        echo json_encode([
            "success" => false,
            "message" => "Không tìm thấy tài khoản tài khoản nào khớp với ID: " . $landlordIdInt,
            "data" => null
        ], JSON_UNESCAPED_UNICODE);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Lỗi truy vấn hệ thống: " . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>