<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, PUT");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Không thể kết nối cơ sở dữ liệu."], JSON_UNESCAPED_UNICODE);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['hoa_don_id'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Thiếu mã hóa đơn cần cập nhật."], JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    $db->beginTransaction();

    // 1. Kiểm tra hóa đơn tồn tại và lấy tổng số tiền đã đóng (nếu có)
    $queryCheck = "SELECT TongTienHoaDon, CongNo FROM HoaDon WHERE Id = :hd_id AND DeletedDate IS NULL FOR UPDATE";
    $stmtCheck = $db->prepare($queryCheck);
    $stmtCheck->execute([':hd_id' => $data['hoa_don_id']]);
    $invoice = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        throw new Exception("Hóa đơn không tồn tại hoặc đã bị xóa.");
    }

    // Tính số tiền khách đã thanh toán trước đó (Bằng Tổng tiền cũ trừ đi Công nợ cũ)
    $daThanhToan = floatval($invoice['TongTienHoaDon']) - floatval($invoice['CongNo']);

    // 2. Tính toán lại chi phí mới
    $soDien = floatval($data['dien']['moi']) - floatval($data['dien']['cu']);
    $tienDien = $soDien * floatval($data['dien']['don_gia']);

    $soNuoc = floatval($data['nuoc']['moi']) - floatval($data['nuoc']['cu']);
    $tienNuoc = $soNuoc * floatval($data['nuoc']['don_gia']);

    $tongTienMoi = floatval($data['tien_phong']) + $tienDien + $tienNuoc + floatval($data['internet']);
    
    // Tính lại công nợ mới (Lấy tổng tiền mới trừ đi số tiền khách đã từng đóng)
    $congNoMoi = $tongTienMoi - $daThanhToan;

    // Cập nhật lại trạng thái thanh toán tương ứng
    if ($congNoMoi <= 0) {
        $trangThaiMoi = "DaThanhToan";
    } elseif ($congNoMoi < $tongTienMoi) {
        $trangThaiMoi = "ThanhToanMotPhan";
    } else {
        $trangThaiMoi = "ChuaThanhToan";
    }

    // 3. Cập nhật bảng HoaDon tổng
    $queryUpdateHD = "UPDATE HoaDon SET TongTienHoaDon = :tong_tien, CongNo = :cong_no, TrangThaiThanhToan = :trang_thai WHERE Id = :hd_id";
    $stmtUpdateHD = $db->prepare($queryUpdateHD);
    $stmtUpdateHD->execute([
        ':tong_tien' => $tongTienMoi,
        ':cong_no' => $congNoMoi,
        ':trang_thai' => $trangThaiMoi,
        ':hd_id' => $data['hoa_don_id']
    ]);

    // 4. Cập nhật bảng ChiTietHoaDon & GiaTriThuocTinhHoaDon
    // Để đơn giản và chính xác nhất, ta xóa các mục chi tiết cũ của hóa đơn này đi và chèn lại tập chi tiết mới
    // BƯỚC 4A: Xóa các thuộc tính con (chỉ số điện/nước cũ) trước để tránh lỗi khóa ngoại (Foreign Key)
    $queryDeleteTT = "DELETE gt FROM GiaTriThuocTinhHoaDon gt
                      INNER JOIN ChiTietHoaDon ct ON gt.ChiTietHoaDonId = ct.Id
                      WHERE ct.HoaDonId = :hd_id";
    $stmtDeleteTT = $db->prepare($queryDeleteTT);
    $stmtDeleteTT->execute([':hd_id' => $data['hoa_don_id']]);

    // BƯỚC 4B: Sau khi bảng con đã sạch, tiến hành xóa các mục chi tiết cũ của hóa đơn này
    $queryDeleteCT = "DELETE FROM ChiTietHoaDon WHERE HoaDonId = :hd_id";
    $stmtDeleteCT = $db->prepare($queryDeleteCT);
    $stmtDeleteCT->execute([':hd_id' => $data['hoa_don_id']]);

    // BƯỚC 4C: Chuẩn bị câu lệnh chèn lại tập dữ liệu mới (Giữ nguyên đoạn code chèn phía dưới)
    $queryCT = "INSERT INTO ChiTietHoaDon (HoaDonId, PhongTroId, DichVuId, TenMuc, SoLuong, DonGia, ThanhTien) VALUES (:hd_id, :pt_id, :dv_id, :ten, :sl, :dg, :tt)";
    $stmtCT = $db->prepare($queryCT);
    
    $queryTT = "INSERT INTO GiaTriThuocTinhHoaDon (ChiTietHoaDonId, ThuocTinhHoaDonId, GiaTriSo, GhiChu) VALUES (:ct_id, :tt_id, :gia_tri, :ghi_chu)";
    $stmtTT = $db->prepare($queryTT);

    // Lấy PhongTroId của hóa đơn để gán vào chi tiết
    $stmtGetPt = $db->prepare("SELECT PhongTroId, KyHoaDon FROM HoaDon WHERE Id = :hd_id");
    $stmtGetPt->execute([':hd_id' => $data['hoa_don_id']]);
    $hdInfo = $stmtGetPt->fetch(PDO::FETCH_ASSOC);
    $phongTroId = $hdInfo['PhongTroId'];
    $kyHoaDon = $hdInfo['KyHoaDon'];

    // Chèn lại Tiền phòng
    $stmtCT->execute([':hd_id' => $data['hoa_don_id'], ':pt_id' => $phongTroId, ':dv_id' => null, ':ten' => "Tiền thuê phòng tháng " . $kyHoaDon, ':sl' => 1, ':dg' => $data['tien_phong'], ':tt' => $data['tien_phong']]);

    // Chèn lại Tiền điện + Chỉ số thuộc tính
    $stmtCT->execute([':hd_id' => $data['hoa_don_id'], ':pt_id' => $phongTroId, ':dv_id' => 1, ':ten' => "Điện tiêu thụ (Cập nhật)", ':sl' => $soDien, ':dg' => $data['dien']['don_gia'], ':tt' => $tienDien]);
    $ctDienId = $db->lastInsertId();
    $stmtTT->execute([':ct_id' => $ctDienId, ':tt_id' => 1, ':gia_tri' => $data['dien']['cu'], ':ghi_chu' => 'Chỉ số điện cũ']);
    $stmtTT->execute([':ct_id' => $ctDienId, ':tt_id' => 2, ':gia_tri' => $data['dien']['moi'], ':ghi_chu' => 'Chỉ số điện mới']);

    // Chèn lại Tiền nước + Chỉ số thuộc tính
    $stmtCT->execute([':hd_id' => $data['hoa_don_id'], ':pt_id' => $phongTroId, ':dv_id' => 2, ':ten' => "Nước sinh hoạt (Cập nhật)", ':sl' => $soNuoc, ':dg' => $data['nuoc']['don_gia'], ':tt' => $tienNuoc]);
    $ctNuocId = $db->lastInsertId();
    $stmtTT->execute([':ct_id' => $ctNuocId, ':tt_id' => 1, ':gia_tri' => $data['nuoc']['cu'], ':ghi_chu' => 'Chỉ số nước cũ']);
    $stmtTT->execute([':ct_id' => $ctNuocId, ':tt_id' => 2, ':gia_tri' => $data['nuoc']['moi'], ':ghi_chu' => 'Chỉ số nước mới']);

    // Chèn lại Internet
    $stmtCT->execute([':hd_id' => $data['hoa_don_id'], ':pt_id' => $phongTroId, ':dv_id' => 3, ':ten' => "Phí dịch vụ Internet trọn gói", ':sl' => 1, ':dg' => $data['internet'], ':tt' => $data['internet']]);

    $db->commit();
    http_response_code(200);
    echo json_encode(["status" => "success", "message" => "Cập nhật dữ liệu hóa đơn thành công!", "TongTienMoi" => $tongTienMoi, "CongNoMoi" => $congNoMoi], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Lỗi cập nhật: " . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>