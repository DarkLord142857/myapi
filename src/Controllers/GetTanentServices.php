<?php
// Cấu hình Headers cho API JSON
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Import file kết nối database
include_once 'database.php';

// Khởi tạo đối tượng Database và kết nối
$database = new Database();
$db = $database->getConnection();

if (!$db) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Không thể kết nối cơ sở dữ liệu."]);
    exit();
}

// Kiểm tra tham số KhachHangId trên URL công cụ Postman
$KhachHangId = isset($_GET['KhachHangId']) ? trim($_GET['KhachHangId']) : '';

if ($KhachHangId === '') {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Thiếu tham số bắt buộc: KhachHangId trên URL"
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// ÉP KIỂU BIẾN VỀ SỐ NGUYÊN (INT) để khớp với định dạng BIGINT của Database
$KhachHangIdInt = (int)$KhachHangId;

try {
    // Câu lệnh SQL: Ép kiểu cột KhachHangId sang số nguyên khi so sánh bằng CAST(... AS UNSIGNED)
    $query = "SELECT * FROM YeuCauDichVu WHERE CAST(KhachHangId AS UNSIGNED) = :khach_hang_id";

    $stmt = $db->prepare($query);
    
    // ÉP KIỂU PARAMETER SANG DẠNG SỐ NGUYÊN PDO::PARAM_INT
    $stmt->bindParam(":khach_hang_id", $KhachHangIdInt, PDO::PARAM_INT);
    $stmt->execute();

    $num = $stmt->rowCount();

    $requests_arr = [];
    $requests_arr["success"] = true;
    $requests_arr["KhachHangId_TruyenVao"] = $KhachHangIdInt;
    $requests_arr["total"] = $num;
    $requests_arr["data"] = [];

    if ($num > 0) {
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $trang_thai_text = ($row['TrangThai'] == 1) ? "Đang xử lý / Đã hoàn thành" : "Chưa xử lý";

            $request_item = [
                "Id" => (int)$row['Id'],
                "KhachHangId" => (int)$row['KhachHangId'],
                "DichVuId" => $row['DichVuId'] ? (int)$row['DichVuId'] : null,
                "TieuDe" => $row['TieuDe'],
                "MoTa" => $row['MoTa'],
                "TrangThai_So" => (int)$row['TrangThai'],
                "TrangThai_Chu" => $trang_thai_text,
                "CreatedDate" => $row['CreatedDate']
            ];
            array_push($requests_arr["data"], $request_item);
        }
        
        http_response_code(200);
        echo json_encode($requests_arr, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    } else {
        http_response_code(200);
        echo json_encode([
            "success" => true,
            "total" => 0,
            "data" => [],
            "message" => "Không tìm thấy dữ liệu. Hãy đảm bảo bạn đã chạy file SQL để tạo dữ liệu mẫu."
        ], JSON_UNESCAPED_UNICODE);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Lỗi hệ thống: " . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>