<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Import file cấu hình kết nối database đồng bộ theo dự án
include_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Không thể kết nối cơ sở dữ liệu."], JSON_UNESCAPED_UNICODE);
    exit();
}

// Nhận UserId (Khách thuê) từ ứng dụng gửi lên qua phương thức GET
$userId = isset($_GET['UserId']) ? intval($_GET['UserId']) : 0;

if ($userId > 0) {
    try {
        // 🔥 ĐÃ CẬP NHẬT: Bắc cầu thông qua bảng HopDongThue để lọc theo UserId của khách thuê
        $query = "SELECT 
                    tt.Id AS ThanhToanId,
                    tt.SoTienThanhToan,
                    tt.PhuongThucThanhToan,
                    tt.MaGiaoDich,
                    tt.NgayThanhToan,
                    tt.GhiChu,
                    hd.Id AS HoaDonId,
                    hd.KyHoaDon
                  FROM ThanhToan tt
                  INNER JOIN HoaDon hd ON tt.HoaDonId = hd.Id
                  INNER JOIN HopDongThue hd_thue ON hd.PhongTroId = hd_thue.PhongTroId
                  WHERE hd_thue.KhachHangId = :UserId 
                    AND tt.IsDeleted = 0 
                    AND hd_thue.IsDeleted = 0
                  ORDER BY tt.NgayThanhToan DESC";

        $stmt = $db->prepare($query);
        $stmt->bindParam(":UserId", $userId, PDO::PARAM_INT);
        $stmt->execute();

        $historyList = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $historyList[] = [
                "ThanhToanId" => intval($row['ThanhToanId']),
                "HoaDonId" => intval($row['HoaDonId']),
                "KyHoaDon" => $row['KyHoaDon'], 
                "SoTienDaDong" => floatval($row['SoTienThanhToan']),
                "PhuongThuc" => $row['PhuongThucThanhToan'],
                "MaGiaoDich" => !empty($row['MaGiaoDich']) ? $row['MaGiaoDich'] : "N/A",
                "NgayGiaoDich" => $row['NgayThanhToan'],
                "GhiChu" => $row['GhiChu']
            ];
        }

        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "message" => "Lấy lịch sử thanh toán thành công.",
            "data" => $historyList
        ], JSON_UNESCAPED_UNICODE);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Lỗi truy vấn cơ sở dữ liệu: " . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
} else {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Mã người dùng UserId không hợp lệ hoặc không được cung cấp."
    ], JSON_UNESCAPED_UNICODE);
}
?>