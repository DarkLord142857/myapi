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
$ghiChu = isset($data['ghiChu']) ? $data['ghiChu'] : '';

try {
    // Bắt đầu Transaction để bảo toàn dữ liệu
    $db->beginTransaction();

    // 1. Kiểm tra hóa đơn và lấy cột `CongNo`, `TrangThai` thực tế từ DB
    $queryHD = "SELECT TongTienHoaDon, CongNo, TrangThaiThanhToan FROM hoadon WHERE Id = :hoadon_id FOR UPDATE";
    $stmtHD = $db->prepare($queryHD);
    $stmtHD->bindParam(":hoadon_id", $hoaDonId);
    $stmtHD->execute();
    $hoaDon = $stmtHD->fetch(PDO::FETCH_ASSOC);

    if (!$hoaDon) {
        $db->rollBack();
        echo json_encode(["status" => "error", "message" => "Hóa đơn không tồn tại."]);
        exit();
    }

    // 2. Tính toán số tiền Còn Nợ mới
    $conNoHienTai = floatval($hoaDon['CongNo']);
    $conNoMoi = $conNoHienTai - $soTienThanhToan;

    // Xác định trạng thái dựa theo ENUM ('ChuaThanhToan', 'DaThanhToan') trong SQL
    $trangThaiMoi = 'ChuaThanhToan';
    if ($conNoMoi <= 0) {
        $conNoMoi = 0; // Tránh tiền nợ bị âm
        $trangThaiMoi = 'DaThanhToan';
    }

    // 3. Thêm lịch sử giao dịch vào bảng `thanhtoan`
    $queryInsertTT = "INSERT INTO thanhtoan (Id, HoaDonId, NgayThanhToan, SoTienThanhToan, PhuongThucThanhToan, MaGiaoDich, NguoiNhanId, GhiChu, IsDeleted) 
                      VALUES (NULL, :hoadon_id, NOW(), :so_tien, :phuong_thuc, :ma_gd, :nguoi_nhan, :ghi_chu, 0)";
    $stmtInsertTT = $db->prepare($queryInsertTT);
    $stmtInsertTT->bindParam(":hoadon_id", $hoaDonId);
    $stmtInsertTT->bindParam(":so_tien", $soTienThanhToan);
    $stmtInsertTT->bindParam(":phuong_thuc", $phuongThuc);
    $stmtInsertTT->bindParam(":ma_gd", $maGiaoDich);
    $stmtInsertTT->bindParam(":nguoi_nhan", $nguoiNhanId);
    $stmtInsertTT->bindParam(":ghi_chu", $ghiChu);
    $stmtInsertTT->execute();

    // 4. Cập nhật lại cột `CongNo` và `TrangThaiThanhToan` trong bảng `hoadon`
    $queryUpdateHD = "UPDATE hoadon 
                      SET CongNo = :con_no_moi, TrangThaiThanhToan = :trang_thai_moi 
                      WHERE Id = :hoadon_id";
    $stmtUpdateHD = $db->prepare($queryUpdateHD);
    $stmtUpdateHD->bindParam(":con_no_moi", $conNoMoi);
    $stmtUpdateHD->bindParam(":trang_thai_moi", $trangThaiMoi);
    $stmtUpdateHD->bindParam(":hoadon_id", $hoaDonId);
    $stmtUpdateHD->execute();

    // Xác nhận hoàn tất Transaction
    $db->commit();

    echo json_encode([
        "status" => "success",
        "message" => "Thanh toán hóa đơn thành công.",
        "data" => [
            "hoadon_id" => $hoaDonId,
            "so_tien_da_dong" => $soTienThanhToan,
            "con_no_lai" => $conNoMoi,
            "trang_thai_moi" => $trangThaiMoi
        ]
    ]);

} catch (Exception $e) {
    $db->rollBack();
    echo json_encode([
        "status" => "error",
        "message" => "Lỗi xử lý thanh toán: " . $e->getMessage()
    ]);
}
?>