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
    if (!isset($_GET['room_id'])) {
        http_response_code(400);
        echo json_encode(array("status" => "error", "message" => "Thiếu mã phòng room_id."), JSON_UNESCAPED_UNICODE);
        exit();
    }

    $roomId = (int)$_GET['room_id'];

    // 1. LẤY THÔNG TIN CƠ BẢN CỦA PHÒNG TRÒ
    $queryRoom = "SELECT pt.Id AS RoomId, pt.SoPhong, pt.GiaPhong, pt.SoNguoiToiDa, pt.SoLuongXeToiDa,
                         nt.TenNha, nt.DiaChi, nt.GiayToPhapLy
                  FROM PhongTro pt
                  INNER JOIN NhaTro nt ON pt.NhaTroId = nt.id
                  WHERE pt.Id = :room_id AND pt.IsDeleted = 0";
                  
    $stmtRoom = $db->prepare($queryRoom);
    $stmtRoom->bindParam(":room_id", $roomId);
    $stmtRoom->execute();
    
    $roomInfo = $stmtRoom->fetch(PDO::FETCH_ASSOC);

    if (!$roomInfo) {
        http_response_code(404);
        echo json_encode(array("status" => "error", "message" => "Không tìm thấy phòng trọ hoặc phòng đã bị xóa mềm."), JSON_UNESCAPED_UNICODE);
        exit();
    }

    // 2. 🟢 ĐÃ CẬP NHẬT: LẤY DANH SÁCH HÌNH ẢNH DẠNG TEXT TRỰC TIẾP (BỎ QUA UNHEX)
    $queryImages = "SELECT HinhAnhUrl FROM phongtrohinhanh WHERE PhongTroId = :room_id";
    $stmtImg = $db->prepare($queryImages);
    $stmtImg->bindParam(":room_id", $roomId);
    $stmtImg->execute();
    
    $images = array();
    while ($rowImg = $stmtImg->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($rowImg['HinhAnhUrl'])) {
            $images[] = $rowImg['HinhAnhUrl'];
        }
    }

    // 3. LẤY DANH SÁCH THUỘC TÍNH ĐI KÈM PHÒNG TRÒ
    $queryAttributes = "SELECT tt.TenThuocTinh, pttt.GiaTriThucTe, tt.DonVi 
                        FROM phongtro_thuoctinh pttt
                        INNER JOIN thuoctinhphong tt ON pttt.ThuocTinhPhongId = tt.Id
                        WHERE pttt.PhongTroId = :room_id";
                
    $stmtAttr = $db->prepare($queryAttributes);
    $stmtAttr->bindParam(":room_id", $roomId);
    $stmtAttr->execute();
    
    $attributes = array();
    while ($rowAttr = $stmtAttr->fetch(PDO::FETCH_ASSOC)) {
        $attributes[] = array(
            "name" => $rowAttr['TenThuocTinh'],
            "value" => $rowAttr['GiaTriThucTe'],
            "unit" => $rowAttr['DonVi'] ?? ""
        );
    }

    // 4. ĐÓNG GÓI DỮ LIỆU ĐỒNG BỘ TRẢ VỀ CHO FLUTTER
    $result = array(
        "RoomId" => (int)$roomInfo['RoomId'],
        "RoomNumber" => $roomInfo['SoPhong'],
        "Price" => (float)$roomInfo['GiaPhong'],
        "MaxPeople" => (int)$roomInfo['SoNguoiToiDa'],
        "MaxVehicles" => (int)$roomInfo['SoLuongXeToiDa'],
        "HouseName" => $roomInfo['TenNha'],
        "Address" => $roomInfo['DiaChi'],
        "LegalDocuments" => $roomInfo['GiayToPhapLy'] ?? "Đang cập nhật",
        "Images" => $images,
        "Attributes" => $attributes
    );

    http_response_code(200);
    echo json_encode(array("status" => "success", "data" => $result), JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array("status" => "error", "message" => "Lỗi hệ thống: " . $e->getMessage()), JSON_UNESCAPED_UNICODE);
}
?>