<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, X-Caller-Id, X-User-Id");

// 🟢 ĐỒNG BỘ KHÔNG PHÂN BIỆT HOA THƯỜNG / LOẠI HEADER
$headers = getallheaders();
$normalized_headers = [];
foreach ($headers as $key => $value) {
    $normalized_headers[strtolower($key)] = $value;
}

// Kiểm tra toàn bộ các trường hợp Header hoặc Body có thể gửi ID lên
$nguoiXoaId = null;
if (isset($normalized_headers['x-caller-id'])) {
    $nguoiXoaId = intval($normalized_headers['x-caller-id']);
} elseif (isset($normalized_headers['x-user-id'])) {
    $nguoiXoaId = intval($normalized_headers['x-user-id']);
} elseif (isset($_POST['NguoiXoaId'])) {
    $nguoiXoaId = intval($_POST['NguoiXoaId']);
} elseif (isset($_POST['NguoiGuiId'])) {
    $nguoiXoaId = intval($_POST['NguoiGuiId']);
}

// Nếu test local không truyền gì, mặc định giả lập là tài khoản ID = 1
if (!$nguoiXoaId) { 
    $nguoiXoaId = 1; 
}

include_once '../../config/database.php';
include_once '../Middleware/authorizeLandlord.php';

$database = new Database();
$db = $database->getConnection();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Xác thực quyền (Chủ trọ hoặc Admin mới được phép xóa phòng)
        ensureLandlordOrAdmin($db, $nguoiXoaId);

        if (empty($_POST['Id'])) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Thiếu ID phòng trọ cần xóa."], JSON_UNESCAPED_UNICODE);
            exit();
        }

        $id = intval($_POST['Id']);

        // 1. KIỂM TRA XEM PHÒNG CÓ ĐANG CÓ KHÁCH THUÊ KHÔNG (TrangThai = 1 là đang thuê)
        $checkQuery = "SELECT TrangThai, SoPhong FROM PhongTro WHERE Id = :Id AND IsDeleted = 0";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bindParam(":Id", $id);
        $checkStmt->execute();
        $room = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$room) {
            http_response_code(404);
            echo json_encode(["status" => "error", "message" => "Phòng trọ không tồn tại hoặc đã bị xóa trước đó."], JSON_UNESCAPED_UNICODE);
            exit();
        }

        if (intval($room['TrangThai']) === 1) {
            http_response_code(400);
            echo json_encode([
                "status" => "error", 
                "message" => "Không thể xóa phòng '" . $room['SoPhong'] . "' vì đang có khách thuê hoạt động!"
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }

        // BẮT ĐẦU TRANSACTION ĐỂ ĐẢM BẢO AN TOÀN DỮ LIỆU
        $db->beginTransaction();

        // 2. THỰC HIỆN XÓA MỀM PHÒNG TRỌ
        $query = "UPDATE PhongTro 
                  SET IsDeleted = 1, 
                      NguoiXoaId = :NguoiXoaId,
                      NgaySua = NOW()
                  WHERE Id = :Id";
                  
        $stmt = $db->prepare($query);
        $stmt->bindParam(":Id", $id);
        $stmt->bindParam(":NguoiXoaId", $nguoiXoaId);
        $stmt->execute();

        // 3. 🟢 TÙY CHỌN TỐI ƯU: Giải phóng hoặc xóa liên kết hình ảnh trong bảng phụ nếu cần
        // Vì đã xóa mềm phòng nên các thuộc tính liên quan cũng nên ẩn đi để tránh rác truy vấn
        $cleanAttributes = "UPDATE phongtro_thuoctinh SET IsDeleted = 1, NguoiXoaId = :NguoiXoaId WHERE PhongTroId = :PhongTroId";
        $cleanAttrStmt = $db->prepare($cleanAttributes);
        $cleanAttrStmt->bindParam(":PhongTroId", $id);
        $cleanAttrStmt->bindParam(":NguoiXoaId", $nguoiXoaId);
        $cleanAttrStmt->execute();

        $db->commit();

        http_response_code(200);
        echo json_encode([
            "status" => "success", 
            "message" => "Xóa phòng trọ '" . $room['SoPhong'] . "' thành công!"
        ], JSON_UNESCAPED_UNICODE);

    } catch (Exception $e) {
        if ($db->inTransaction()) { $db->rollBack(); }
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Lỗi hệ thống: " . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
} else {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Phương thức không được hỗ trợ."], JSON_UNESCAPED_UNICODE);
}
?>