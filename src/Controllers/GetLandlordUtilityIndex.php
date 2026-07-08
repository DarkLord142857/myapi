<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
include_once '../../config/database.php';
include_once '../Middleware/authorizeLandlord.php';
$database = new Database();
$db = $database->getConnection();
if ($db === null) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Không thể kết nối cơ sở dữ liệu."], JSON_UNESCAPED_UNICODE);
    exit();
}
$landlord_id = isset($_GET['landlord_id']) ? intval($_GET['landlord_id']) : 0;
$caller = ensureLandlordOrAdmin($db, $landlord_id);
$isAdmin = (isset($caller['Role']) && $caller['Role'] === 'Admin') ? 1 : 0;
if ($isAdmin === 0 && $landlord_id <= 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Thiếu mã định danh chủ trọ."], JSON_UNESCAPED_UNICODE);
    exit();
}
$phong_tro_id = isset($_GET['phong_tro_id']) ? intval($_GET['phong_tro_id']) : 0;
$ky_hoa_don  = isset($_GET['ky_hoa_don']) ? trim($_GET['ky_hoa_don']) : '';
try {
    $query = "SELECT
                hd.Id AS HoaDonId,
                hd.KyHoaDon,
                pt.SoPhong,
                nt.TenNha,
                cthd.DichVuId,
                cthd.TenMuc,
                cthd.SoLuong AS TieuThu,
                MAX(CASE WHEN gt.ThuocTinhHoaDonId = 1 THEN gt.GiaTriSo END) AS ChiSoCu,
                MAX(CASE WHEN gt.ThuocTinhHoaDonId = 2 THEN gt.GiaTriSo END) AS ChiSoMoi
              FROM HoaDon hd
              INNER JOIN PhongTro pt ON hd.PhongTroId = pt.Id
              INNER JOIN NhaTro nt ON pt.NhaTroId = nt.Id
              INNER JOIN ChiTietHoaDon cthd ON hd.Id = cthd.HoaDonId
              INNER JOIN GiaTriThuocTinhHoaDon gt ON cthd.Id = gt.ChiTietHoaDonId
              WHERE ( :is_admin = 1 OR nt.MaQL = :landlord_id )
                AND cthd.DichVuId IN (1, 2)
                AND hd.DeletedDate IS NULL";
    if ($phong_tro_id > 0) {
        $query .= " AND pt.Id = :phong_tro_id";
    }
    if (!empty($ky_hoa_don)) {
        $query .= " AND hd.KyHoaDon = :ky_hoa_don";
    }
    $query .= " GROUP BY
                  hd.Id,
                  hd.KyHoaDon,
                  pt.SoPhong,
                  nt.TenNha,
                  cthd.DichVuId,
                  cthd.TenMuc,
                  cthd.SoLuong
              ORDER BY hd.Id DESC, cthd.DichVuId ASC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':is_admin', $isAdmin, PDO::PARAM_INT);
    $stmt->bindParam(':landlord_id', $landlord_id, PDO::PARAM_INT);
    if ($phong_tro_id > 0) {
        $stmt->bindParam(':phong_tro_id', $phong_tro_id, PDO::PARAM_INT);
    }
    if (!empty($ky_hoa_don)) {
        $stmt->bindParam(':ky_hoa_don', $ky_hoa_don, PDO::PARAM_STR);
    }
    $stmt->execute();
    $utilityData = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $utilityData[] = [
            "HoaDonId"   => (int)$row['HoaDonId'],
            "Period"     => $row['KyHoaDon'],
            "HouseName"  => $row['TenNha'],
            "RoomNumber" => $row['SoPhong'],
            "ServiceId"  => (int)$row['DichVuId'],
            "ServiceName"=> $row['TenMuc'],
            "OldIndex"   => (float)$row['ChiSoCu'],
            "NewIndex"   => (float)$row['ChiSoMoi'],
            "Consumption"=> (float)$row['TieuThu']
        ];
    }
    http_response_code(200);
    echo json_encode([
        "status" => "success",
        "count"  => count($utilityData),
        "data"   => $utilityData
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Lỗi hệ thống: " . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>
