<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../../config/database.php';

include_once '../Middleware/authorizeLandlord.php';

$database = new Database();
$db = $database->getConnection();

// Lấy dữ liệu dạng JSON từ Flutter gửi lên
$data = json_decode(file_get_contents("php://input"), true);

if (!empty($data['NguoiGuiId']) && !empty($data['TieuDe']) && !empty($data['NoiDung'])) {
    // Authorize caller (allow Admin)
    ensureLandlordOrAdmin($db, intval($data['NguoiGuiId']));
    try {
        $query = "INSERT INTO ThongBao (NguoiGuiId, NhaTroId, TieuDe, NoiDung, CreatedDate) 
                  VALUES (:NguoiGuiId, :NhaTroId, :TieuDe, :NoiDung, NOW())";
                  
        $stmt = $db->prepare($query);
        
        $stmt->bindParam(":NguoiGuiId", $data['NguoiGuiId']);
        
        // 🔥 ĐÃ CẬP NHẬT: Ép buộc gán NhaTroId = 1 nếu giá trị gửi lên trống hoặc bằng null
        $nhaTroId = (!empty($data['NhaTroId']) && $data['NhaTroId'] !== null) ? intval($data['NhaTroId']) : 1;
        $stmt->bindParam(":NhaTroId", $nhaTroId, PDO::PARAM_INT);
        
        $stmt->bindParam(":TieuDe", $data['TieuDe']);
        $stmt->bindParam(":NoiDung", $data['NoiDung']);

        if ($stmt->execute()) {
            http_response_code(200);
            echo json_encode([
                "status" => "success",
                "message" => "Phát thông báo tới khách thuê thành công!"
            ], JSON_UNESCAPED_UNICODE);
        } else {
            throw new PDOException("Không thể thực thi câu lệnh.");
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Lỗi cơ sở dữ liệu: " . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
} else {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Dữ liệu gửi lên không đầy đủ (Thiếu Người gửi, Tiêu đề hoặc Nội dung)."
    ], JSON_UNESCAPED_UNICODE);
}
?>