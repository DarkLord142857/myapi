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
        $infoQuery = "SELECT hd.PhongTroId, nt.Id as NhaTroId, nt.MaQL as ChuTroId
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

        // 3. Tạo thông báo và phân phối đích danh (Chỉ gửi cho chính khách thuê này)
        try {
            // Chèn vào bảng thông báo chung (NhaTroId gán = 0 hoặc vẫn giữ nguyên nhưng kiểm soát bằng thongbao_user)
            $queryTb = "INSERT INTO ThongBao (NguoiGuiId, NhaTroId, TieuDe, NoiDung, CreatedDate) 
                        VALUES (:NguoiGuiId, :NhaTroId, :TieuDe, :NoiDung, NOW())";
                        
            $stmtTb = $db->prepare($queryTb);
            $stmtTb->execute([
                ':NguoiGuiId' => $data['NguoiGuiId'],
                ':NhaTroId'   => $info['NhaTroId'], 
                ':TieuDe'     => "Đã ghi nhận yêu cầu dịch vụ",
                ':NoiDung'    => "Hệ thống đã tiếp nhận sự cố: " . $data['TieuDe'] . ". Chủ nhà sẽ sớm xử lý."
            ]);
            
            // Lấy ID của thông báo vừa tạo
            $thongBaoId = $db->lastInsertId();
            
            // 🔥 BƯỚC QUAN TRỌNG: Chỉ định danh duy nhất khách thuê này được phép nhìn thấy thông báo
            $queryTargetUser = "INSERT INTO ThongBao_User (ThongBaoId, UserId, TrangThai) 
                                VALUES (:ThongBaoId, :UserId, 0)"; // 0: Chưa đọc
            $stmtTarget = $db->prepare($queryTargetUser);
            $stmtTarget->execute([
                ':ThongBaoId' => $thongBaoId,
                ':UserId'     => $data['NguoiGuiId'] // ID của chính khách thuê gửi yêu cầu
            ]);
            
            // (Tùy chọn) Nếu bạn muốn Chủ trọ cũng nhận được thông báo này trong danh sách của họ:
            if (!empty($info['ChuTroId'])) {
                $stmtTarget->execute([
                    ':ThongBaoId' => $thongBaoId,
                    ':UserId'     => $info['ChuTroId']
                ]);
            }

        } catch (Exception $exNoti) {
            // Nếu gửi thông báo lỗi, ghi log nhưng không làm sập luồng tạo sự cố chính
        }

        $db->commit(); // Xác nhận lưu thành công
        
        http_response_code(200);
        echo json_encode([
            "status" => "success", 
            "message" => "Đã gửi yêu cầu dịch vụ thành công!",
            "yeuCauId" => (int)$yeuCauId
        ]);

    } catch (Exception $e) {
        $db->rollBack(); 
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Thiếu thông tin gửi lên hệ thống."]);
}
?>