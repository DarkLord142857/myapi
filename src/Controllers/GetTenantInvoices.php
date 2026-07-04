<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

include_once '../../config/database.php';
$database = new Database();
$db = $database->getConnection();

$landlord_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($landlord_id <= 0) {
    echo json_encode(["status" => "error", "message" => "Thiếu mã chủ trọ."], JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    // 1. Tìm NhaTroId mà người thuê này đang ở dựa trên hợp đồng thuê còn hiệu lực (IsActive = 1)
    $queryHouse = "SELECT pt.NhaTroId 
                   FROM hopdongthue hdt
                   INNER JOIN phongtro pt ON hdt.PhongTroId = pt.Id
                   WHERE hdt.KhachHangId = :userId AND hdt.IsActive = 1 AND hdt.IsDeleted = 0 
                   LIMIT 1";
    $stmtHouse = $db->prepare($queryHouse);
    $stmtHouse->execute([':userId' => $user_id]);
    $houseRow = $stmtHouse->fetch(PDO::FETCH_ASSOC);
    
    // Nếu khách chưa có hợp đồng thì mặc định mã nhà bằng 0 để tránh lỗi SQL
    $myNhaTroId = $houseRow ? intval($houseRow['NhaTroId']) : 0;

    // 2. Lấy danh sách thông báo: Khớp mã nhà trọ họ đang ở HOẶC đích danh ID của họ trong ThongBao_User
    $query = "SELECT DISTINCT tb.Id, tb.TieuDe, tb.NoiDung, tb.CreatedDate, u.FullName as TenChuTro,
                     IF(tbu.TrangThai IS NULL, 0, tbu.TrangThai) as PersonalTrangThai
              FROM ThongBao tb
              LEFT JOIN Users u ON tb.NguoiGuiId = u.id
              LEFT JOIN ThongBao_User tbu ON tb.Id = tbu.ThongBaoId
              WHERE 
                -- Trường hợp 1: Thông báo dịch vụ lẻ gửi đích danh cho tài khoản này
                (tbu.UserId = :userId)
                
                -- Trường hợp 2: Thông báo chung của tòa nhà trọ mà khách này đang ở (và không gửi riêng ai)
                OR (tb.NhaTroId = :nhaTroId AND tbu.UserId IS NULL)
              ORDER BY tb.CreatedDate DESC";

    $stmt = $db->prepare($query);
    $stmt->execute([
        ':userId' => $user_id,
        ':nhaTroId' => $myNhaTroId
    ]);
    
    $notifications = [];
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $notifications[] = [
            "id" => (int)$row['Id'],
            "tieuDe" => $row['TieuDe'],
            "noiDung" => $row['NoiDung'],
            "tenChuTro" => $row['TenChuTro'] ?? "Chủ nhà trọ",
            "trangThai" => (int)$row['PersonalTrangThai'], 
            "createdDate" => $row['CreatedDate']
        ];
    }

    echo json_encode(["status" => "success", "data" => $notifications], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Lỗi hệ thống: " . $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>