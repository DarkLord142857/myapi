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
    echo json_encode(array("status" => "error", "message" => "Hệ thống mất kết nối cơ sở dữ liệu."), JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    // Câu lệnh SQL lấy phòng trống và chưa bị xóa mềm
    $query = "SELECT 
                pt.id AS PhongTroId, 
                pt.SoPhong, 
                pt.GiaPhong, 
                pt.SoNguoiToiDa, 
                nt.TenNha, 
                nt.DiaChi,
                (SELECT ha.HinhAnhUrl FROM phongtrohinhanh ha WHERE ha.PhongTroId = pt.id LIMIT 1) AS HinhAnh
              FROM PhongTro pt
              INNER JOIN NhaTro nt ON pt.NhaTroId = nt.id
              WHERE pt.IsDeleted = 0
              AND NOT EXISTS (
                  SELECT 1 
                  FROM HopDongThue hd 
                  WHERE hd.PhongTroId = pt.id 
                    AND hd.IsActive = 1 
                    AND hd.IsDeleted = 0
                    AND CURRENT_DATE() BETWEEN hd.NgayBatDau AND hd.NgayKetThuc
              )
              ORDER BY pt.id DESC";

    $stmt = $db->prepare($query);
    $stmt->execute();

    $rooms = array();

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $image = "";
        if (!empty($row['HinhAnh'])) {
            $image = $row['HinhAnh'];
            // Gán URL chuẩn tuyệt đối nếu database chỉ lưu tên file hoặc link tương đối
            if (!preg_match('/^https?:\/\//', $image)) {
                $image = "http://10.0.2.2/myapi/uploads/" . $image;
            }
        }

        $rooms[] = array(
            "RoomId" => (int)$row['PhongTroId'],
            "RoomNumber" => $row['SoPhong'],
            "Price" => (float)$row['GiaPhong'],
            "MaxPeople" => (int)$row['SoNguoiToiDa'],
            "HouseName" => $row['TenNha'],
            "Address" => $row['DiaChi'],
            "ThumbnailUrl" => $image
        );
    }

    http_response_code(200);
    // Trả về định dạng bọc đối tượng { "status": "success", "data": [...] }
    echo json_encode(array("status" => "success", "data" => $rooms), JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array("status" => "error", "message" => "Lỗi hệ thống: " . $e->getMessage()), JSON_UNESCAPED_UNICODE);
}
?>