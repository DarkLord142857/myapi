<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
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

$landlord_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$nha_tro_id = isset($_GET['NhaTroId']) ? intval($_GET['NhaTroId']) : 0;

// Authorize caller and get caller info
$caller = ensureLandlordOrAdmin($db, $landlord_id);
$isAdmin = (isset($caller['Role']) && $caller['Role'] === 'Admin') ? 1 : 0;

// If caller is not Admin, landlord_id must be provided
if ($isAdmin === 0 && $landlord_id <= 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Thiếu mã định danh chủ trọ."], JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    // Truy vấn tất cả hóa đơn thuộc các tòa nhà do chủ trọ quản lý (MaQL = landlord_id)
    $query = "SELECT hd.Id AS HoaDonId, hd.KyHoaDon, hd.TongTienHoaDon, hd.CongNo, hd.TrangThaiThanhToan, hd.CreatedDate,
                     pt.SoPhong, nt.TenNha, u.FullName AS TenKhachHang
              FROM HoaDon hd
              INNER JOIN PhongTro pt ON hd.PhongTroId = pt.Id
              INNER JOIN NhaTro nt ON pt.NhaTroId = nt.Id
              LEFT JOIN HopDongThue hdt ON pt.Id = hdt.PhongTroId AND hdt.IsActive = 1 AND hdt.IsDeleted = 0
              LEFT JOIN Users u ON hdt.KhachHangId = u.id
              WHERE ( :is_admin = 1 OR nt.MaQL = :landlord_id ) AND hd.DeletedDate IS NULL AND hd.KyHoaDon Not like '%(DV)%'";
    
    if ($nha_tro_id > 0) {
        $query .= " AND nt.Id = :nha_tro_id";
    }
    
    $query .= " ORDER BY hd.Id DESC";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':is_admin', $isAdmin, PDO::PARAM_INT);
    $stmt->bindParam(':landlord_id', $landlord_id, PDO::PARAM_INT);
    if ($nha_tro_id > 0) {
        $stmt->bindParam(':nha_tro_id', $nha_tro_id, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    $invoices = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $invoices[] = [
            "InvoiceId" => (int)$row['HoaDonId'],
            "Period" => $row['KyHoaDon'],
            "TotalPrice" => (float)$row['TongTienHoaDon'],
            "Debt" => (float)$row['CongNo'],
            "Status" => $row['TrangThaiThanhToan'],
            "CreatedDate" => $row['CreatedDate'],
            "RoomNumber" => $row['SoPhong'],
            "HouseName" => $row['TenNha'],
            "CustomerName" => $row['TenKhachHang'] ?? "Phòng trống/Chưa gán khách"
        ];
    }

    http_response_code(200);
    echo json_encode([
        "status" => "success",
        "data" => $invoices
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Lỗi hệ thống: " . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>