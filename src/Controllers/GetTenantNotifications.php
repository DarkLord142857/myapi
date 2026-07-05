<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

if (!empty($_GET['user_id'])) {
    $userId = intval($_GET['user_id']);

    try {
        // Bước 1: Tìm NhaTroId của khách thuê đang có hợp đồng kích hoạt (Active)
        $houseQuery = "SELECT p.NhaTroId 
                       FROM HopDongThue hd
                       JOIN PhongTro p ON hd.PhongTroId = p.Id
                       WHERE hd.KhachHangId = :userId AND hd.IsActive = 1 AND hd.IsDeleted = 0 
                       LIMIT 1";
        $stmtHouse = $db->prepare($houseQuery);
        $stmtHouse->execute([':userId' => $userId]);
        $house = $stmtHouse->fetch(PDO::FETCH_ASSOC);
        
        $nhaTroId = $house ? intval($house['NhaTroId']) : -1;

        // Bước 2: Lấy tất cả thông báo
        // 🔥 ĐIỂM CỐT LÕI: Phải đưa "tbu.UserId = :userId" vào mệnh đề ON của LEFT JOIN
        // Để ngăn chặn việc Khách B lấy nhầm dòng trạng thái Đã Đọc của Khách A
       $query = "SELECT 
            tb.Id as id, 
            tb.TieuDe as tieuDe, 
            tb.NoiDung as noiDung, 
            tb.CreatedDate as createdDate,
            u.FullName as tenNguoiGui,
            u.Role as vaiTroNguoiGui,
            COALESCE(tbu.TrangThai, 0) as trangThai
          FROM ThongBao tb
          LEFT JOIN Users u ON tb.NguoiGuiId = u.id
          LEFT JOIN ThongBao_User tbu ON tb.Id = tbu.ThongBaoId AND tbu.UserId = :userId
          WHERE (tb.NhaTroId = :nhaTroId AND :nhaTroId != -1) 
             OR (tb.NhaTroId = 0 AND tbu.UserId = :userId)
          ORDER BY tb.CreatedDate DESC";

        $stmt = $db->prepare($query);
        $stmt->execute([
            ':userId' => $userId,
            ':nhaTroId' => $nhaTroId
        ]);

        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Chuyển kiểu số nguyên chuẩn hóa cho Flutter map dữ liệu
        foreach ($notifications as &$noti) {
            $noti['id'] = (int)$noti['id'];
            $noti['trangThai'] = (int)$noti['trangThai'];
        }

        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "data" => $notifications
        ], JSON_UNESCAPED_UNICODE);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Lỗi hệ thống: " . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
} else {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Thiếu tham số user_id bắt buộc."
    ], JSON_UNESCAPED_UNICODE);
}
?>