<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, X-Caller-Id, X-User-Id");

$headers = getallheaders();
$normalized_headers = [];
foreach ($headers as $key => $value) {
    $normalized_headers[strtolower($key)] = $value;
}

$callerId = null;
if (isset($normalized_headers['x-caller-id'])) {
    $callerId = intval($normalized_headers['x-caller-id']);
} elseif (isset($normalized_headers['x-user-id'])) {
    $callerId = intval($normalized_headers['x-user-id']);
} elseif (isset($_POST['NguoiGuiId'])) {
    $callerId = intval($_POST['NguoiGuiId']);
}

if (!$callerId) { 
    $callerId = 1; 
}

include_once '../../config/database.php';
include_once '../Middleware/authorizeLandlord.php';

$database = new Database();
$db = $database->getConnection();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        ensureLandlordOrAdmin($db, $callerId);

        if (empty($_POST['Id']) || empty($_POST['SoPhong'])) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Thiếu ID phòng hoặc Số phòng cập nhật."], JSON_UNESCAPED_UNICODE);
            exit();
        }

        $id = intval($_POST['Id']);
        $soPhongMoi = trim($_POST['SoPhong']);

        // 1. LẤY NHATROID HIỆN TẠI CỦA PHÒNG ĐỂ KIỂM TRA TRÙNG TRONG CÙNG KHU NHÀ
        $checkHomeQuery = "SELECT NhaTroId FROM PhongTro WHERE Id = :Id AND IsDeleted = 0";
        $checkHomeStmt = $db->prepare($checkHomeQuery);
        $checkHomeStmt->bindParam(":Id", $id);
        $checkHomeStmt->execute();
        $currentRoom = $checkHomeStmt->fetch(PDO::FETCH_ASSOC);

        if (!$currentRoom) {
            http_response_code(404);
            echo json_encode(["status" => "error", "message" => "Không tìm thấy phòng trọ hợp lệ."], JSON_UNESCAPED_UNICODE);
            exit();
        }
        $nhaTroId = $currentRoom['NhaTroId'];

        // 2. 🟢 THỰC HIỆN KIỂM TRA TRÙNG SỐ PHÒNG
        // Tìm xem có phòng nào KHÁC phòng này (Id <> :Id) trong cùng khu nhà (NhaTroId = :NhaTroId) trùng SoPhong không
       $dupQuery = "SELECT SoPhong FROM PhongTro 
                     WHERE REGEXP_REPLACE(SoPhong, '[^0-9]', '') = REGEXP_REPLACE(:SoPhong, '[^0-9]', '') 
                       AND NhaTroId = :NhaTroId 
                       AND Id <> :Id 
                       AND IsDeleted = 0";
        $dupStmt = $db->prepare($dupQuery);
        $dupStmt->bindParam(":SoPhong", $soPhongMoi);
        $dupStmt->bindParam(":NhaTroId", $nhaTroId);
        $dupStmt->bindParam(":Id", $id);
        $dupStmt->execute();

        if ($dupStmt->rowCount() > 0) {
            // Nếu tìm thấy tức là số phòng này đã bị phòng khác sử dụng
            // 🟢 ĐÃ SỬA: Phải FETCH dữ liệu trước rồi mới gọi biến $dupRoom
            $dupRoom = $dupStmt->fetch(PDO::FETCH_ASSOC);
            http_response_code(400);
            echo json_encode([
                "status" => "error", 
                "message" => "Số phòng bạn nhập trùng với phòng '" . ($dupRoom ? $dupRoom['SoPhong'] : $soPhongMoi) . "' đã có sẵn trong hệ thống!"
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }

        // 3. TIẾN HÀNH CẬP NHẬT KHI ĐÃ HỢP LỆ
        $db->beginTransaction();

        $query = "UPDATE PhongTro SET 
                    SoPhong = :SoPhong, 
                    SoNguoiToiDa = :SoNguoiToiDa, 
                    SoLuongXeToiDa = :SoLuongXeToiDa, 
                    GiaPhong = :GiaPhong, 
                    TrangThai = :TrangThai,
                    NgaySua = NOW()
                  WHERE Id = :Id AND IsDeleted = 0";
        
        $stmt = $db->prepare($query);
        
        $soNguoiToiDa = isset($_POST['SoNguoiToiDa']) ? intval($_POST['SoNguoiToiDa']) : 0;
        $soLuongXeToiDa = isset($_POST['SoLuongXeToiDa']) ? intval($_POST['SoLuongXeToiDa']) : 0;
        $giaPhong = isset($_POST['GiaPhong']) ? doubleval($_POST['GiaPhong']) : 0.0;
        $trangThai = isset($_POST['TrangThai']) ? intval($_POST['TrangThai']) : 0;

        $stmt->bindParam(":SoPhong", $soPhongMoi);
        $stmt->bindParam(":SoNguoiToiDa", $soNguoiToiDa);
        $stmt->bindParam(":SoLuongXeToiDa", $soLuongXeToiDa);
        $stmt->bindParam(":GiaPhong", $giaPhong);
        $stmt->bindParam(":TrangThai", $trangThai);
        $stmt->bindParam(":Id", $id);

        if ($stmt->execute()) {
            $roomUpdated = $stmt->rowCount() > 0;

            // Xử lý hình ảnh TEXT thuần túy nếu có truyền tệp tin mới lên
            $imagesUpdated = false;
            if (isset($_FILES['images']) && count($_FILES['images']['name']) > 0 && !empty($_FILES['images']['name'][0])) {
                
                $delImgQuery = "DELETE FROM phongtrohinhanh WHERE PhongTroId = :PhongTroId";
                $delImgStmt = $db->prepare($delImgQuery);
                $delImgStmt->bindParam(":PhongTroId", $id);
                $delImgStmt->execute();

                $uploadDir = '../../uploads/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $fileCount = count($_FILES['images']['name']);
                for ($i = 0; $i < $fileCount; $i++) {
                    if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
                        $tmpName = $_FILES['images']['tmp_name'][$i];
                        $fileName = time() . '_' . basename($_FILES['images']['name'][$i]);
                        $targetFilePath = $uploadDir . $fileName;

                        if (move_uploaded_file($tmpName, $targetFilePath)) {
                            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
                            $imageUrl = $protocol . "://" . $_SERVER['HTTP_HOST'] . "/myapi/uploads/" . $fileName;

                            $imgQuery = "INSERT INTO phongtrohinhanh (PhongTroId, HinhAnhUrl) VALUES (:PhongTroId, :HinhAnhUrl)";
                            $imgStmt = $db->prepare($imgQuery);
                            $imgStmt->bindParam(":PhongTroId", $id);
                            $imgStmt->bindParam(":HinhAnhUrl", $imageUrl);
                            $imgStmt->execute();
                            $imagesUpdated = true;
                        }
                    }
                }
            }

            $db->commit();
            http_response_code(200);
            echo json_encode([
                "status" => "success", 
                "message" => "Cập nhật phòng trọ thành công!",
                "details" => [
                    "ThongTinPhongThayDoi" => $roomUpdated,
                    "HinhAnhThayDoi" => $imagesUpdated
                ]
            ], JSON_UNESCAPED_UNICODE);
        } else {
            throw new Exception("Thực thi SQL cập nhật thất bại.");
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) { $db->rollBack(); }
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Lỗi hệ thống: " . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}
?>