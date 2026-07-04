<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../../config/database.php';
include_once '../Middleware/authorizeLandlord.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Không thể kết nối cơ sở dữ liệu."], JSON_UNESCAPED_UNICODE);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['nguoi_lap_id']) || empty($data['phong_tro_id']) || empty($data['ky_hoa_don'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Thiếu thông tin bắt buộc để tạo hóa đơn."], JSON_UNESCAPED_UNICODE);
    exit();
}

// Authorize caller (nguoi_lap_id should be ChuTro or Admin)
ensureLandlordOrAdmin($db, intval($data['nguoi_lap_id']));

try {
    $db->beginTransaction(); // Khởi động transaction an toàn dữ liệu

    // 1. Tính toán chi phí tiêu thụ thực tế
    $soDien = floatval($data['dien']['moi']) - floatval($data['dien']['cu']);
    $tienDien = $soDien * floatval($data['dien']['don_gia']);

    $soNuoc = floatval($data['nuoc']['moi']) - floatval($data['nuoc']['cu']);
    $tienNuoc = $soNuoc * floatval($data['nuoc']['don_gia']);

    $tongTien = floatval($data['tien_phong']) + $tienDien + $tienNuoc + floatval($data['internet']);

    // 2. Chèn dữ liệu vào bảng tổng HoaDon
    $queryHD = "INSERT INTO HoaDon (NguoiLapId, PhongTroId, KyHoaDon, TongTienHoaDon, CongNo, TrangThaiThanhToan) 
                VALUES (:nguoi_lap, :phong, :ky, :tong, :cong_no, 'ChuaThanhToan')";
    $stmtHD = $db->prepare($queryHD);
    $stmtHD->execute([
        ':nguoi_lap' => $data['nguoi_lap_id'],
        ':phong' => $data['phong_tro_id'],
        ':ky' => $data['ky_hoa_don'],
        ':tong' => $tongTien,
        ':cong_no' => $tongTien
    ]);
    $hoaDonId = $db->lastInsertId();

    // Chuẩn bị sẵn các câu lệnh chèn chi tiết để tái sử dụng
    $queryCT = "INSERT INTO ChiTietHoaDon (HoaDonId, PhongTroId, DichVuId, TenMuc, SoLuong, DonGia, ThanhTien) 
                VALUES (:hd_id, :pt_id, :dv_id, :ten, :sl, :dg, :tt)";
    $stmtCT = $db->prepare($queryCT);
    
    $queryTT = "INSERT INTO GiaTriThuocTinhHoaDon (ChiTietHoaDonId, ThuocTinhHoaDonId, GiaTriSo, GhiChu) 
                VALUES (:ct_id, :tt_id, :gia_tri, :ghi_chu)";
    $stmtTT = $db->prepare($queryTT);

    // 3. Thêm mục: Tiền Phòng
    $stmtCT->execute([
        ':hd_id' => $hoaDonId, ':pt_id' => $data['phong_tro_id'], ':dv_id' => null,
        ':ten' => "Tiền thuê phòng tháng " . $data['ky_hoa_don'], ':sl' => 1, ':dg' => $data['tien_phong'], ':tt' => $data['tien_phong']
    ]);

    // 4. Thêm mục: Tiền Điện + Chỉ số điện cũ/mới
    $stmtCT->execute([
        ':hd_id' => $hoaDonId, ':pt_id' => $data['phong_tro_id'], ':dv_id' => 1,
        ':ten' => "Điện tiêu thụ (Chỉ số: ".$data['dien']['cu']." -> ".$data['dien']['moi'].")", ':sl' => $soDien, ':dg' => $data['dien']['don_gia'], ':tt' => $tienDien
    ]);
    $ctDienId = $db->lastInsertId();
    $stmtTT->execute([':ct_id' => $ctDienId, ':tt_id' => 1, ':gia_tri' => $data['dien']['cu'], ':ghi_chu' => 'Chỉ số điện cũ đầu kỳ']);
    $stmtTT->execute([':ct_id' => $ctDienId, ':tt_id' => 2, ':gia_tri' => $data['dien']['moi'], ':ghi_chu' => 'Chỉ số điện mới cuối kỳ']);

    // 5. Thêm mục: Tiền Nước + Chỉ số nước cũ/mới
    $stmtCT->execute([
        ':hd_id' => $hoaDonId, ':pt_id' => $data['phong_tro_id'], ':dv_id' => 2,
        ':ten' => "Nước sinh hoạt (Chỉ số: ".$data['nuoc']['cu']." -> ".$data['nuoc']['moi'].")", ':sl' => $soNuoc, ':dg' => $data['nuoc']['don_gia'], ':tt' => $tienNuoc
    ]);
    $ctNuocId = $db->lastInsertId();
    $stmtTT->execute([':ct_id' => $ctNuocId, ':tt_id' => 1, ':gia_tri' => $data['nuoc']['cu'], ':ghi_chu' => 'Chỉ số nước cũ đầu kỳ']);
    $stmtTT->execute([':ct_id' => $ctNuocId, ':tt_id' => 2, ':gia_tri' => $data['nuoc']['moi'], ':ghi_chu' => 'Chỉ số nước mới cuối kỳ']);

    // 6. Thêm mục: Internet cố định
    $stmtCT->execute([
        ':hd_id' => $hoaDonId, ':pt_id' => $data['phong_tro_id'], ':dv_id' => 3,
        ':ten' => "Phí dịch vụ Internet trọn gói", ':sl' => 1, ':dg' => $data['internet'], ':tt' => $data['internet']
    ]);

    $db->commit(); // Hoàn tất giao dịch lưu xuống DB
    
    http_response_code(201);
    echo json_encode(["status" => "success", "message" => "Tạo hóa đơn tháng mới thành công!", "InvoiceId" => (int)$hoaDonId], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    $db->rollBack(); // Hoàn tác hủy bỏ nếu có lỗi xảy ra
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Lỗi xử lý nghiệp vụ DB: " . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>