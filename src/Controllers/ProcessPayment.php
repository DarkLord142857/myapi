<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../../config/database.php';
$database = new Database();
$db = $database->getConnection();

$data = json_decode(file_get_contents("php://input"), true);

// Kiểm tra dữ liệu đầu vào bắt buộc
if (
    empty($data['hoadon_id']) || 
    empty($data['so_tien_thanh_toan']) || 
    empty($data['phuong_thuc']) || 
    empty($data['ma_giao_dich']) ||
    empty($data['nguoi_nhan_id'])
) {
    echo json_encode(["status" => "error", "message" => "Thiếu thông tin thanh toán bắt buộc."]);
    exit();
}

$hoaDonId = intval($data['hoadon_id']);
$soTienThanhToan = floatval($data['so_tien_thanh_toan']);
$phuongThuc = $data['phuong_thuc']; 
$maGiaoDich = $data['ma_giao_dich'];
$nguoiNhanId = intval($data['nguoi_nhan_id']);
$ghiChu = isset($data['ghi_chu']) ? $data['ghi_chu'] : '';

try {
    // Khởi động một chuỗi Transaction để đảm bảo tính an toàn dữ liệu nguyên khối
    $db->beginTransaction();

    // 1. Kiểm tra sự tồn tại của hóa đơn và khóa dòng dữ liệu đó lại tránh xung đột đồng thời (Race Condition)
    $queryHD = "SELECT Id, CongNo FROM hoadon WHERE Id = :id FOR UPDATE";
    $stmtHD = $db->prepare($queryHD);
    $stmtHD->bindParam(":id", $hoaDonId);
    $stmtHD->execute();
    $invoice = $stmtHD->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        throw new Exception("Hóa đơn mục tiêu không tồn tại trên hệ thống.");
    }

    // 2. Tiến hành thêm bản ghi mới vào lịch sử thanh toán của bảng `thanhtoan` (Dùng bảng gốc của bạn)
    $queryInsertTT = "INSERT INTO thanhtoan (HoaDonId, SoTienThanhToan, PhuongThucThanhToan, MaGiaoDich, NguoiNhanId, GhiChu, NgayThanhToan) 
                      VALUES (:hoadon_id, :so_tien, :phuong_thuc, :ma_gd, :nguoi_nhan, :ghi_chu, NOW())";
    
    $stmtInsertTT = $db->prepare($queryInsertTT);
    $stmtInsertTT->bindParam(":hoadon_id", $hoaDonId);
    $stmtInsertTT->bindParam(":so_tien", $soTienThanhToan);
    $stmtInsertTT->bindParam(":phuong_thuc", $phuongThuc);
    $stmtInsertTT->bindParam(":ma_gd", $maGiaoDich);
    $stmtInsertTT->bindParam(":nguoi_nhan", $nguoiNhanId);
    $stmtInsertTT->bindParam(":ghi_chu", $ghiChu);
    $stmtInsertTT->execute();

    // 3. 🛠️ CẬP NHẬT TRẠNG THÁI TRUNG GIAN: Chuyển sang 'ChoDuyet', giữ nguyên công nợ cho đến khi chủ nhà duyệt
    $trangThaiChoDuyet = 'ChoDuyet'; 
    $queryUpdateHD = "UPDATE hoadon 
                      SET TrangThaiThanhToan = :trang_thai_moi 
                      WHERE Id = :hoadon_id";
    $stmtUpdateHD = $db->prepare($queryUpdateHD);
    $stmtUpdateHD->bindParam(":trang_thai_moi", $trangThaiChoDuyet);
    $stmtUpdateHD->bindParam(":hoadon_id", $hoaDonId);
    $stmtUpdateHD->execute();

    // Xác nhận hoàn tất Transaction
    $db->commit();

    echo json_encode([
        "status" => "success",
        "message" => "Yêu cầu thanh toán của bạn đã được gửi thành công! Vui lòng chờ chủ trọ duyệt."
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    // Trả dữ liệu về trạng thái ban đầu nếu có bất kỳ lỗi nào phát sinh
    $db->rollBack();
    echo json_encode([
        "status" => "error",
        "message" => "Giao dịch thất bại: " . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>