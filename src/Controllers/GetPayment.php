<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Import file cấu hình kết nối database đồng bộ theo hệ thống
include_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Không thể kết nối cơ sở dữ liệu."], JSON_UNESCAPED_UNICODE);
    exit();
}

// Lấy tham số HoaDonId truyền từ phương thức GET (Ví dụ: GetPayment.php?HoaDonId=3)
$hoaDonId = isset($_GET['HoaDonId']) ? intval($_GET['HoaDonId']) : (isset($_REQUEST['HoaDonId']) ? intval($_REQUEST['HoaDonId']) : 0);

if ($hoaDonId > 0) {
    try {
        // 1. Lấy thông tin tổng quan về Hóa đơn và Số dư công nợ hiện tại
        $invoiceQuery = "SELECT Id, KyHoaDon, TongTienHoaDon, CongNo, TrangThaiThanhToan
                         FROM HoaDon 
                         WHERE Id = :HoaDonId AND DeletedDate IS NULL LIMIT 1";
        
        $stmtInvoice = $db->prepare($invoiceQuery);
        $stmtInvoice->bindParam(":HoaDonId", $hoaDonId, PDO::PARAM_INT);
        $stmtInvoice->execute();

        if ($stmtInvoice->rowCount() == 0) {
            http_response_code(404);
            echo json_encode(["status" => "error", "message" => "Không tìm thấy thông tin hóa đơn trọ này."], JSON_UNESCAPED_UNICODE);
            exit();
        }

        $invoiceData = $stmtInvoice->fetch(PDO::FETCH_ASSOC);

        // 2. Lấy danh sách lịch sử tất cả các lần khách đã từng đóng tiền cho hóa đơn này (nếu có)
        $historyQuery = "SELECT Id, SoTienThanhToan, PhuongThucThanhToan, MaGiaoDich, NgayThanhToan, GhiChu 
                         FROM ThanhToan 
                         WHERE HoaDonId = :HoaDonId 
                         ORDER BY NgayThanhToan DESC";
        
        $stmtHistory = $db->prepare($historyQuery);
        $stmtHistory->bindParam(":HoaDonId", $hoaDonId, PDO::PARAM_INT);
        $stmtHistory->execute();

        $paymentHistory = [];
        while ($row = $stmtHistory->fetch(PDO::FETCH_ASSOC)) {
            $paymentHistory[] = [
                "ThanhToanId" => intval($row['Id']),
                "SoTienDaDong" => floatval($row['SoTienThanhToan']),
                "PhuongThuc" => $row['PhuongThucThanhToan'],
                "MaGiaoDich" => $row['MaGiaoDich'],
                "NgayGiaoDich" => $row['NgayThanhToan'],
                "GhiChu" => $row['GhiChu']
            ];
        }

        // 3. Đóng gói kết quả trả về cấu trúc phân tầng JSON rõ ràng
        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "message" => "Lấy thông tin thanh toán hóa đơn thành công.",
            "data" => [
                "HoaDonId" => intval($invoiceData['Id']),
                "KyHoaDon" => "Tháng " . $invoiceData['Thang'] . "/" . $invoiceData['Nam'],
                "TongTienHoaDon" => floatval($invoiceData['TongTienHoaDon']),
                "CongNoHienTai" => floatval($invoiceData['CongNo']),
                "TrangThaiThanhToan" => $invoiceData['TrangThaiThanhToan'],
                "LichSuThanhToan" => $paymentHistory
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Lỗi hệ thống: " . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Vui lòng cung cấp mã HoaDonId hợp lệ."], JSON_UNESCAPED_UNICODE);
}
?>