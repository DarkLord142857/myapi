<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

include_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

try {
    // Câu lệnh SQL lấy trạng thái cá nhân từ bảng trung gian ThongBao_User
    $query = "SELECT tb.Id, tb.TieuDe, tb.NoiDung, tb.CreatedDate, u.FullName as TenChuTro,
                     IF(tbu.TrangThai IS NULL, 0, tbu.TrangThai) as PersonalTrangThai
              FROM ThongBao tb
              LEFT JOIN Users u ON tb.NguoiGuiId = u.id
              LEFT JOIN ThongBao_User tbu ON tb.Id = tbu.ThongBaoId
              WHERE 
                -- Trường hợp 1: Được đích danh gửi riêng cho người dùng này
                (tbu.UserId = :userId)
                -- Trường hợp 2: Thông báo chung của hệ thống / tòa nhà (không bị gán riêng cho ai)
                OR (tb.NhaTroId IS NOT NULL AND tbu.UserId IS NULL)
              ORDER BY tb.CreatedDate DESC";

    $stmt = $db->prepare($query);
    $stmt->execute([':userId' => $user_id]);
    
    $notifications = [];
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $notifications[] = [
            "id" => (int)$row['Id'],
            "tieuDe" => $row['TieuDe'],
            "noiDung" => $row['NoiDung'],
            "tenChuTro" => $row['TenChuTro'] ?? "Chủ nhà trọ",
            "trangThai" => (int)$row['PersonalTrangThai'], 
            "createdDate" => $row['CreatedDate'] // 🔥 Viết chính xác chữ "createdDate" cho Flutter đọc
        ];
    }

    http_response_code(200);
    echo json_encode([
        "status" => "success",
        "data" => $notifications
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>