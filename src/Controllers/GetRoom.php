<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once __DIR__ . '/../../config/database.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Không thể kết nối cơ sở dữ liệu."], JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    // 🟢 ĐÃ SỬA: Lấy trực tiếp pthi.HinhAnhUrl (Không cần CAST hay giải mã HEX như cũ nữa)
    $query = "SELECT
                p.Id AS PhongId,
                p.NhaTroId,
                p.SoPhong,
                p.SoNguoiToiDa,
                p.SoLuongXeToiDa,
                p.GiaPhong,
                p.TrangThai,
                pt.GiaTriThucTe,
                ttp.Id AS ThuocTinhId,
                ttp.TenThuocTinh,
                ttp.DonVi,
                ttp.KieuDuLieu,
                pthi.Id AS HinhAnhId,
                pthi.HinhAnhUrl
              FROM PhongTro p
              LEFT JOIN phongtro_thuoctinh pt ON p.Id = pt.PhongTroId AND pt.IsDeleted = 0
              LEFT JOIN thuoctinhphong ttp ON pt.ThuocTinhPhongId = ttp.Id
              LEFT JOIN phongtrohinhanh pthi ON p.Id = pthi.PhongTroId
              WHERE p.IsDeleted = 0
              ORDER BY p.Id DESC";

    $stmt = $db->prepare($query);
    $stmt->execute();

    $rooms = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $roomId = $row['PhongId'];

        if (!isset($rooms[$roomId])) {
            $rooms[$roomId] = [
                "PhongId" => (int)$row['PhongId'],
                "NhaTroId" => (int)$row['NhaTroId'],
                "SoPhong" => $row['SoPhong'],
                "SoNguoiToiDa" => (int)$row['SoNguoiToiDa'],
                "SoLuongXeToiDa" => (int)$row['SoLuongXeToiDa'],
                "GiaPhong" => (double)$row['GiaPhong'],
                "TrangThai" => (int)$row['TrangThai'],
                "HinhAnhDaiDien" => null,
                "DanhSachHinhAnh" => [],
                "DanhSachThuocTinh" => []
            ];
        }

        // 🟢 ĐÃ SỬA: Đọc text link trực tiếp gán vào mảng
        if ($row['HinhAnhId'] !== null && !empty($row['HinhAnhUrl'])) {
            $imgId = (int)$row['HinhAnhId'];
            if (!isset($rooms[$roomId]['DanhSachHinhAnh'][$imgId])) {
                $rooms[$roomId]['DanhSachHinhAnh'][$imgId] = $row['HinhAnhUrl'];
            }
        }

        if ($row['ThuocTinhId'] !== null) {
            $exists = false;
            foreach ($rooms[$roomId]['DanhSachThuocTinh'] as $attr) {
                if ($attr['ThuocTinhId'] === (int)$row['ThuocTinhId']) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $rooms[$roomId]['DanhSachThuocTinh'][] = [
                    "ThuocTinhId" => (int)$row['ThuocTinhId'],
                    "TenThuocTinh" => $row['TenThuocTinh'],
                    "GiaTriThucTe" => $row['GiaTriThucTe'],
                    "DonVi" => $row['DonVi'],
                    "KieuDuLieu" => (int)$row['KieuDuLieu']
                ];
            }
        }
    }

    // Định dạng lại danh sách hình ảnh thành mảng tuần tự số nguyên index sạch
    foreach ($rooms as $id => $room) {
        $rooms[$id]['DanhSachHinhAnh'] = array_values($room['DanhSachHinhAnh']);
        // Lấy ảnh đầu tiên làm ảnh đại diện
        $rooms[$id]['HinhAnhDaiDien'] = !empty($rooms[$id]['DanhSachHinhAnh']) ? $rooms[$id]['DanhSachHinhAnh'][0] : null;
    }

    $resultData = array_values($rooms);

    http_response_code(200);
    echo json_encode([
        "status" => "success",
        "message" => "Lấy danh sách phòng trọ thành công.",
        "data" => $resultData
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Lỗi truy vấn: " . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>