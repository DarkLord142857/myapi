<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    http_response_code(500);
    echo json_encode(array("status" => "error", "message" => "Không thể kết nối cơ sở dữ liệu."));
    exit();
}

// Lấy Id người thuê từ Flutter truyền lên (?user_id=3)
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($user_id <= 0) {
    http_response_code(400);
    echo json_encode(array("status" => "error", "message" => "Thiếu hoặc sai mã ID người thuê."));
    exit();
}

try {
    // Truy vấn lấy kỳ hóa đơn, loại dịch vụ (điện/nước), chỉ số cũ, chỉ số mới và lượng tiêu thụ
    $query = "SELECT 
            hd.Id AS HoaDonId,
            hd.KyHoaDon,
            pt.SoPhong,
            nt.TenNha,
            cthd.DichVuId,
            cthd.TenMuc,
            cthd.SoLuong AS TieuThu,
            MAX(CASE WHEN gtthhd.ThuocTinhHoaDonId = 1 THEN gtthhd.GiaTriSo END) AS ChiSoCu,
            MAX(CASE WHEN gtthhd.ThuocTinhHoaDonId = 2 THEN gtthhd.GiaTriSo END) AS ChiSoMoi
          FROM HoaDon hd
          INNER JOIN HopDongThue hdt ON hd.PhongTroId = hdt.PhongTroId
          INNER JOIN PhongTro pt ON hdt.PhongTroId = pt.Id
          INNER JOIN NhaTro nt ON pt.NhaTroId = nt.Id
          INNER JOIN ChiTietHoaDon cthd ON hd.Id = cthd.HoaDonId
          INNER JOIN GiaTriThuocTinhHoaDon gtthhd ON cthd.Id = gtthhd.ChiTietHoaDonId
          WHERE hdt.KhachHangId = :user_id 
            AND hdt.IsActive = 1 
            AND hdt.IsDeleted = 0
            AND cthd.DichVuId IN (1, 2)
          GROUP BY 
            hd.Id,            -- 🌟 BẮT BUỘC PHẢI THÊM CỘT NÀY VÀO ĐÂY
            hd.KyHoaDon, 
            pt.SoPhong, 
            nt.TenNha, 
            cthd.DichVuId, 
            cthd.TenMuc, 
            cthd.SoLuong
          ORDER BY hd.Id DESC, cthd.DichVuId ASC";
          
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();

    $utilityData = array();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $utilityData[] = array(
            "Period" => $row['KyHoaDon'],
            "HouseName" => $row['TenNha'],
            "RoomNumber" => $row['SoPhong'],
            "ServiceId" => (int)$row['DichVuId'], // 1: Điện, 2: Nước
            "ServiceName" => $row['TenMuc'],
            "OldIndex" => (float)$row['ChiSoCu'],
            "NewIndex" => (float)$row['ChiSoMoi'],
            "Consumption" => (float)$row['TieuThu']
        );
    }

    http_response_code(200);
    echo json_encode(array(
        "status" => "success",
        "data" => $utilityData
    ));

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array("status" => "error", "message" => "Lỗi hệ thống: " . $e->getMessage()));
}
?>