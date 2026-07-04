<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

include_once '../../config/database.php';
$include_line = '';
include_once '../Middleware/authorizeLandlord.php';
$database = new Database();
$db = $database->getConnection();

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->request_id) && !empty($data->don_gia)) {
    try {
        $db->beginTransaction();
        
        $queryGet = "SELECT ycdv.*, hdt.PhongTroId, dv.TenDichVu, pt.NhaTroId 
                     FROM yeucaudichvu ycdv
                     INNER JOIN hopdongthue hdt ON ycdv.KhachHangId = hdt.KhachHangId
                     INNER JOIN phongtro pt ON hdt.PhongTroId = pt.Id
                     LEFT JOIN dichvu dv ON ycdv.DichVuId = dv.Id
                     WHERE ycdv.Id = :req_id AND hdt.IsActive = 1 LIMIT 1";
        $stmtGet = $db->prepare($queryGet);
        $stmtGet->execute([':req_id' => $data->request_id]);
        $request = $stmtGet->fetch(PDO::FETCH_ASSOC);

        if (!$request) {
            echo json_encode(["success" => false, "message" => "Không tìm thấy hợp đồng đang hoạt động của khách thuê này."]);
            $db->rollBack();
            exit();
        }
        
        $landlordId = $data->landlord_id ?? 2;

        // Authorize caller (allow Admin)
        ensureLandlordOrAdmin($db, intval($landlordId));
        $currentPeriod = date("m/Y"). " (DV)";
        $tongTien = doubleval($data->don_gia);

        // 2. Tạo hóa đơn mới phát sinh cho dịch vụ này
        $queryInvoice = "INSERT INTO HoaDon (NguoiLapId, PhongTroId, KyHoaDon, TongTienHoaDon, CongNo, TrangThaiThanhToan) 
                         VALUES (:nguoi_lap, :phong_id, :ky_hd, :tong_tien, :cong_no, 'ChuaThanhToan')";
        $stmtInvoice = $db->prepare($queryInvoice);
        $stmtInvoice->execute([
            ':nguoi_lap' => $landlordId,
            ':phong_id' => $request['PhongTroId'],
            ':ky_hd' => $currentPeriod,
            ':tong_tien' => $tongTien,
            ':cong_no' => $tongTien
        ]);
        $invoiceId = $db->lastInsertId();

        // 3. Chèn vào bảng chi tiết hóa đơn dựa trên cấu trúc database của bạn
        $queryDetail = "INSERT INTO ChiTietHoaDon (HoaDonId, PhongTroId, DichVuId, YeuCauId, TenMuc, SoLuong, DonGia, ThanhTien) 
                        VALUES (:hd_id, :phong_id, :dv_id, :yc_id, :ten_muc, 1, :don_gia, :thanh_tien)";
        $stmtDetail = $db->prepare($queryDetail);
        $tenMucThu = "Phát sinh sửa chữa: " . ($request['TenDichVu'] ?? $request['TieuDe']);
        $stmtDetail->execute([
            ':hd_id' => $invoiceId,
            ':phong_id' => $request['PhongTroId'],
            ':dv_id' => $request['DichVuId'],
            ':yc_id' => $data->request_id,
            ':ten_muc' => $tenMucThu,
            ':don_gia' => $tongTien,
            ':thanh_tien' => $tongTien
        ]);

        // 4. Cập nhật yêu cầu dịch vụ sang trạng thái hoàn tất (TrangThai = 2)
        $queryUpdateReq = "UPDATE yeucaudichvu SET TrangThai = 2 WHERE Id = :req_id";
        $stmtUpdateReq = $db->prepare($queryUpdateReq);
        $stmtUpdateReq->execute([':req_id' => $data->request_id]);

        // 5. Bắn thông báo nợ tiền dịch vụ về cho khách thuê
        $queryNoti = "INSERT INTO ThongBao (NguoiGuiId, NhaTroId, TieuDe, NoiDung, TrangThai) 
                      VALUES (:nguoi_gui, :nha_tro_id, 'Hóa đơn dịch vụ mới phát sinh', :noi_dung, 1)";
        $stmtNoti = $db->prepare($queryNoti);
        $tienDinhDang = number_format($tongTien, 0, ',', '.') . ' đ';
        $noiDungNợ = "Chủ trọ đã lập hóa đơn cho yêu cầu '" . $request['TieuDe'] . "'. Số tiền cần thanh toán: " . $tienDinhDang . ". Vui lòng vào mục hóa đơn để kiểm tra.";
        $stmtNoti->execute([
            ':nguoi_gui' => $landlordId,
            ':nha_tro_id' => $request['NhaTroId'], // Gán NhaTroId thực tế của phòng, tránh lỗi NULL
            ':noi_dung' => $noiDungNợ
        ]);
        $thongBaoId = $db->lastInsertId();

        // Đồng bộ gửi riêng cho khách hàng vào bảng liên kết cá nhân
        $queryUserNoti = "INSERT INTO ThongBao_User (ThongBaoId, UserId, TrangThai) VALUES (:tb_id, :user_id, 0)";
        $stmtUserNoti = $db->prepare($queryUserNoti);
        $stmtUserNoti->execute([':tb_id' => $thongBaoId, ':user_id' => $request['KhachHangId']]);

        $db->commit();
        echo json_encode(["success" => true, "message" => "Đã xuất hóa đơn dịch vụ thành công và phát thông báo!"]);
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(["success" => false, "message" => "Thất bại: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Thiếu mã yêu cầu hoặc số tiền báo giá."]);
}
?>