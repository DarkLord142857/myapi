<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../../config/database.php';
include_once '../Middleware/authorizeLandlord.php';

$database = new Database();
$db = $database->getConnection();

// Lấy landlord_id truyền lên từ Flutter
// Allow Admins as well via middleware check. The middleware will read X-User-Id header or use landlord_id.
$landlord_id = isset($_GET['landlord_id']) ? intval($_GET['landlord_id']) : 0;

// Authorize caller and get caller info
$caller = ensureLandlordOrAdmin($db, $landlord_id);
$isAdmin = (isset($caller['Role']) && $caller['Role'] === 'Admin') ? 1 : 0;

if ($landlord_id > 0) {
    try {
        // Câu lệnh SQL tối ưu: Tìm tất cả yêu cầu dịch vụ thuộc các phòng trọ do chủ trọ này quản lý (qua MaQL)
        $query = "SELECT 
                    ycdv.Id, 
                    ycdv.TieuDe, 
                    ycdv.MoTa, 
                    ycdv.TrangThai, 
                    pt.SoPhong as RoomNumber, 
                    u.FullName,
                    dv.TenDichVu,
                    dv.ChiPhi
                  FROM YeuCauDichVu ycdv
                  INNER JOIN Users u ON ycdv.KhachHangId = u.id
                  INNER JOIN hopdongthue hdt ON u.id = hdt.KhachHangId
                  INNER JOIN PhongTro pt ON hdt.PhongTroId = pt.Id
                  INNER JOIN NhaTro nt ON pt.NhaTroId = nt.Id
                  LEFT JOIN dichvu dv ON ycdv.DichVuId = dv.Id
                                    WHERE ( :is_admin = 1 OR nt.MaQL = :landlord_id )
                                        AND hdt.IsActive = 1 
                  ORDER BY ycdv.Id DESC";

        $stmt = $db->prepare($query);
        $stmt->bindParam(":is_admin", $isAdmin, PDO::PARAM_INT);
        $stmt->bindParam(":landlord_id", $landlord_id, PDO::PARAM_INT);
        $stmt->execute();

        $requests = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $requests[] = [
                "Id" => (int)$row['Id'],
                "TieuDe" => $row['TieuDe'],
                "MoTa" => $row['MoTa'],
                "TrangThai" => (int)$row['TrangThai'],
                "RoomNumber" => $row['RoomNumber'] ?? 'Không rõ',
                "FullName" => $row['FullName'] ?? 'Vô danh',
                "TenDichVu" => $row['TenDichVu'],
                "ChiPhi" => $row['ChiPhi']
            ];
        }

        echo json_encode([
            "success" => true,
            "data" => $requests
        ], JSON_UNESCAPED_UNICODE);

    } catch (Exception $e) {
        echo json_encode([
            "success" => false,
            "message" => "Lỗi: " . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        "success" => false,
        "message" => "Thiếu hoặc sai ID chủ trọ."
    ]);
}
?>