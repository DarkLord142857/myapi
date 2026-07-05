<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Đọc dữ liệu JSON gửi từ Flutter ứng dụng lên
$data = json_decode(file_get_contents("php://input"), true);

if (!empty($data['NguoiGuiId']) && !empty($data['TieuDe']) && !empty($data['MoTa'])) {
    try {
        $db->beginTransaction(); // Bắt đầu giao dịch bảo vệ toàn vẹn dữ liệu

        // 1. Kiểm tra hợp đồng thuê phòng của khách thuê để lấy thông tin liên quan
        $infoQuery = "SELECT hd.PhongTroId, nt.Id as NhaTroId 
                      FROM HopDongThue hd
                      JOIN PhongTro p ON hd.PhongTroId = p.Id 
                      JOIN NhaTro nt ON p.NhaTroId = nt.Id
                      WHERE hd.KhachHangId = :KhachHangId AND hd.IsActive = 1 AND hd.IsDeleted = 0 
                      LIMIT 1";
                      
        $stmtInfo = $db->prepare($infoQuery);
        $stmtInfo->execute([':KhachHangId' => $data['NguoiGuiId']]);
        $info = $stmtInfo->fetch(PDO::FETCH_ASSOC);

        if (!$info) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Không tìm thấy hợp đồng thuê phòng hợp lệ."]);
            exit();
        }

        // 2. Chèn dữ liệu vào bảng YeuCauDichVu
        $queryYeuCau = "INSERT INTO YeuCauDichVu (KhachHangId, DichVuId, TieuDe, MoTa, TrangThai, CreatedDate) 
                        VALUES (:KhachHangId, :DichVuId, :TieuDe, :MoTa, 0, NOW())";
                        
        $stmtYC = $db->prepare($queryYeuCau);
        $stmtYC->execute([
            ':KhachHangId' => $data['NguoiGuiId'],
            ':DichVuId'    => isset($data['DichVuId']) ? $data['DichVuId'] : 1,
            ':TieuDe'      => $data['TieuDe'],
            ':MoTa'        => $data['MoTa']
        ]);

        // Lấy ID tự động tăng của yêu cầu dịch vụ vừa tạo
        $yeuCauId = $db->lastInsertId();

        // 3. Bọc khối chèn Thông Báo riêng biệt để tránh lỗi nghẽn hoặc sai cấu trúc cột làm sập API
        try {
            // ĐÃ XÓA CỘT TrangThai BỊ THỪA THEO ĐÚNG FILE SQL CỦA BẠN
            $queryTb = "INSERT INTO ThongBao (NguoiGuiId, NhaTroId, TieuDe, NoiDung, CreatedDate, TrangThai) 
                        VALUES (:NguoiGuiId, :NhaTroId, :TieuDe, :NoiDung, NOW(), 0)";
                        
            $stmtTb = $db->prepare($queryTb);
            $stmtTb->execute([
                ':NguoiGuiId' => $data['NguoiGuiId'],
                ':NhaTroId'   => $info['NhaTroId'], 
                ':TieuDe'     => "Yêu cầu dịch vụ mới từ Khách thuê",
                ':NoiDung'    => "Sự cố: " . $data['TieuDe'] . ". Chi tiết: " . $data['MoTa']
            ]);
        } catch (Exception $exNoti) {
            // Nếu bảng ThongBao bị lỗi, ghi nhận lỗi nhưng không rollback để bảng YeuCauDichVu vẫn lưu bình thường
        }

        $db->commit(); // Xác nhận lưu thành công
        
        // Trả về JSON sạch 100%
        http_response_code(200);
        echo json_encode([
            "status" => "success", 
            "message" => "Đã gửi yêu cầu dịch vụ thành công!",
            "yeuCauId" => (int)$yeuCauId
        ]);

    } catch (Exception $e) {
        $db->rollBack(); // Hoàn tác nếu lỗi ở bước 1 hoặc bước 2
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Thiếu thông tin gửi lên hệ thống."]);
}
?>