<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../../config/database.php';
include_once '../Middleware/authorizeLandlord.php';

$database = new Database();
$db = $database->getConnection();

$data = json_decode(file_get_contents("php://input"));

// Authorize caller (allow Admin)
ensureLandlordOrAdmin($db, isset($data->id) ? intval($data->id) : 0);

if (empty($data->id) || empty($data->FullName) || empty($data->PhoneNumber)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Thiếu dữ liệu bắt buộc."], JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    // 🔥 CẬP NHẬT CÂU LỆNH SQL: Thêm IdentityCard = :identity_card
    $query = "UPDATE Users 
              SET FullName = :fullname, 
                  PhoneNumber = :phone, 
                  Email = :email,
                  IdentityCard = :identity_card 
              WHERE id = :id";

    $stmt = $db->prepare($query);

    $id = (int)$data->id;
    $fullName = trim($data->FullName);
    $phoneNumber = trim($data->PhoneNumber);
    $email = isset($data->Email) ? trim($data->Email) : '';
    $identityCard = isset($data->IdentityCard) ? trim($data->IdentityCard) : ''; // 🔥 LẤY ĐƯỢC CCCD TỪ FLUTTER GỬI LÊN

    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->bindParam(":fullname", $fullName, PDO::PARAM_STR);
    $stmt->bindParam(":phone", $phoneNumber, PDO::PARAM_STR);
    $stmt->bindParam(":email", $email, PDO::PARAM_STR);
    $stmt->bindParam(":identity_card", $identityCard, PDO::PARAM_STR); // 🔥 BIND THAM SỐ CCCD

    if ($stmt->execute()) {
        http_response_code(200);
        echo json_encode(["success" => true, "message" => "Cập nhật thông tin chủ trọ thành công!"], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Không thể cập nhật dữ liệu."], JSON_UNESCAPED_UNICODE);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Lỗi: " . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>