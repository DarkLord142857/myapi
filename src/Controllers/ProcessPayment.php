<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Import file kết nối database đồng bộ theo dự án của bạn
include_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Không thể kết nối cơ sở dữ liệu."], JSON_UNESCAPED_UNICODE);
    exit();
}

// Đọc dữ liệu thô gửi lên từ Body (Raw JSON từ Flutter hoặc Postman)
$data = json_decode(file_get_contents("php://input"), true);

// Khai báo và ép kiểu dữ liệu đầu vào nhận được
$hoaDonId = isset($data['HoaDonId']) ? intval($data['HoaDonId']) : 0;
$soTienThanhToan = isset($data['SoTienThanhToan']) ? floatval($data['SoTienThanhToan']) : 0.0;
$phuongThucThanhToan = isset($data['PhuongThucThanhToan']) ? trim($data['PhuongThucThanhToan']) : ''; // Ví dụ: 'ViMomo', 'ChuyenKhoanNH'
$maGiaoDich = isset($data['MaGiaoDich']) ? trim($data['MaGiaoDich']) : ''; // Mã đối soát từ MoMo Sandbox/Ngân hàng
$nguoiNhanId = isset($data['NguoiNhanId']) ? intval($data['NguoiNhanId']) : null; // ID chủ trọ quản lý (nếu có)
$ghiChu = isset($data['GhiChu']) ? trim($data['GhiChu']) : '';

// Kiểm tra tính hợp lệ tối thiểu của dữ liệu đầu vào
if ($hoaDonId > 0 && $soTienThanhToan > 0 && !empty($phuongThucThanhToan)) {
    try {
        // 🔥 KHỞI TẠO TRANSACTION: Bảo vệ toàn vẹn dữ liệu tránh lỗi mất tiền mà không trừ công nợ
        $db->beginTransaction();

        // 1. Kiểm tra hóa đơn có tồn tại không và khóa dòng dữ liệu để tránh xung đột luồng xử lý đồng thời (FOR UPDATE)
        $checkInvoiceQuery = "SELECT Id, TongTienHoaDon, CongNo FROM HoaDon WHERE Id = :HoaDonId AND DeletedDate IS NULL LIMIT 1 FOR UPDATE";
        $stmtCheck = $db->prepare($checkInvoiceQuery);
        $stmtCheck->bindParam(":HoaDonId", $hoaDonId, PDO::PARAM_INT);
        $stmtCheck->execute();

        if ($stmtCheck->rowCount() == 0) {
            http_response_code(404);
            echo json_encode(["status" => "error", "message" => "Hóa đơn này không tồn tại hoặc đã bị xóa trước đó."], JSON_UNESCAPED_UNICODE);
            $db->rollBack();
            exit();
        }

        $invoice = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        $congNoHienTai = floatval($invoice['CongNo']);
        
        // 2. Tính toán số dư công nợ mới
        $congNoMoi = $congNoHienTai - $soTienThanhToan;
        if ($congNoMoi < 0) {
            $congNoMoi = 0; // Đảm bảo số công nợ không bị âm nếu khách thanh toán thừa hoặc làm tròn số
        }

        // Tự động thiết lập trạng thái hóa đơn mới dựa trên số tiền thực đóng
        $trangThaiMoi = "Thanh toán một phần";
        if ($congNoMoi <= 0) {
            $trangThaiMoi = "Đã thanh toán";
        }

        // 3. Thực hiện chèn bản ghi lịch sử vào bảng ThanhToan
        $insertPaymentQuery = "INSERT INTO ThanhToan (HoaDonId, SoTienThanhToan, PhuongThucThanhToan, MaGiaoDich, NguoiNhanId, GhiChu) 
                               VALUES (:HoaDonId, :SoTienThanhToan, :PhuongThucThanhToan, :MaGiaoDich, :NguoiNhanId, :GhiChu)";
        
        $stmtPayment = $db->prepare($insertPaymentQuery);
        $stmtPayment->bindParam(":HoaDonId", $hoaDonId, PDO::PARAM_INT);
        $stmtPayment->bindParam(":SoTienThanhToan", $soTienThanhToan);
        $stmtPayment->bindParam(":PhuongThucThanhToan", $phuongThucThanhToan, PDO::PARAM_STR);
        $stmtPayment->bindParam(":MaGiaoDich", $maGiaoDich, PDO::PARAM_STR);
        
        if ($nguoiNhanId !== null) {
            $stmtPayment->bindParam(":NguoiNhanId", $nguoiNhanId, PDO::PARAM_INT);
        } else {
            $stmtPayment->bindValue(":NguoiNhanId", null, PDO::PARAM_NULL);
        }
        
        $stmtPayment->bindParam(":GhiChu", $ghiChu, PDO::PARAM_STR);
        $stmtPayment->execute();

        // 4. Thực hiện cập nhật lại bảng HoaDon (Giảm trừ CongNo và đổi TrangThaiThanhToan)
        $updateInvoiceQuery = "UPDATE HoaDon 
                               SET CongNo = :CongNoMoi, TrangThaiThanhToan = :TrangThaiMoi 
                               WHERE Id = :HoaDonId";
        
        $stmtUpdateInvoice = $db->prepare($updateInvoiceQuery);
        $stmtUpdateInvoice->bindParam(":CongNoMoi", $congNoMoi);
        $stmtUpdateInvoice->bindParam(":TrangThaiMoi", $trangThaiMoi, PDO::PARAM_STR);
        $stmtUpdateInvoice->bindParam(":HoaDonId", $hoaDonId, PDO::PARAM_INT);
        $stmtUpdateInvoice->execute();

        // Xác nhận lưu vĩnh viễn các thay đổi vào database
        $db->commit();

        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "message" => "Ghi nhận thanh toán và cập nhật công nợ hóa đơn thành công!",
            "data" => [
                "HoaDonId" => $hoaDonId,
                "SoTienThanhToan" => $soTienThanhToan,
                "CongNoConLai" => $congNoMoi,
                "TrangThaiThanhToan" => $trangThaiMoi
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    } catch (Exception $e) {
        // Hủy bỏ toàn bộ thao tác dở dang nếu xảy ra lỗi hệ thống
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Lỗi hệ thống khi xử lý: " . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Dữ liệu đầu vào không hợp lệ hoặc thiếu thông tin bắt buộc."], JSON_UNESCAPED_UNICODE);
}
?>