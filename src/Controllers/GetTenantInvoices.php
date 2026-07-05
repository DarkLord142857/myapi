<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

include_once '../../config/database.php';
$database = new Database();
$db = $database->getConnection();

// 1. Lấy chính xác user_id của khách thuê từ URL
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($user_id <= 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Thiếu mã khách thuê phòng (user_id)."], JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    // 2. Truy vấn lấy danh sách hóa đơn dựa trên bảng hợp đồng thuê của khách hàng
    // Giữ nguyên KyHoaDon và dùng STR_TO_DATE để sắp xếp theo thời gian giảm dần
    $query = "SELECT 
                hd.Id, 
                hd.TongTienHoaDon, 
                hd.CongNo, 
                hd.TrangThaiThanhToan,
                hd.KyHoaDon,
                pt.NhaTroId,
                nt.MaQL AS NguoiNhanId
              FROM hoadon hd
              INNER JOIN phongtro pt ON hd.PhongTroId = pt.Id
              INNER JOIN nhatro nt ON pt.NhaTroId = nt.Id
              INNER JOIN hopdongthue hdt ON pt.Id = hdt.PhongTroId
              WHERE hdt.KhachHangId = :userId 
                AND hdt.IsActive = 1 
                AND hdt.IsDeleted = 0
              ORDER BY STR_TO_DATE(CONCAT('01/', hd.KyHoaDon), '%d/%m/%Y') DESC";

    $stmt = $db->prepare($query);
    $stmt->execute([':userId' => $user_id]);
    
    $invoices = [];
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $invoices[] = [
            "Id" => (int)$row['Id'],
            "TongTienHoaDon" => (double)$row['TongTienHoaDon'],
            "CongNo" => (double)$row['CongNo'],
            "TrangThaiThanhToan" => $row['TrangThaiThanhToan'],
            "KyHoaDon" => $row['KyHoaDon'],
            "NguoiNhanId" => (int)$row['NguoiNhanId']
        ];
    }

    http_response_code(200);
    echo json_encode([
        "status" => "success", 
        "data" => $invoices
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error", 
        "message" => "Lỗi hệ thống: " . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}