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

        if (empty($_POST['NhaTroId']) || empty($_POST['SoPhong']) || empty($_POST['GiaPhong'])) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Thiếu thông tin bắt buộc (NhaTroId, SoPhong hoặc GiaPhong)."], JSON_UNESCAPED_UNICODE);
            exit();
        }

        $nhaTroId = intval($_POST['NhaTroId']);
        $soPhongMoi = trim($_POST['SoPhong']);

        // 🟢 BƯỚC THÊM MỚI: KIỂM TRA TRÙNG SỐ PHÒNG NÂNG CAO (Ví dụ: 126 trùng P.126)
        // Xóa hết chữ, khoảng trắng, chỉ giữ lại số của các phòng thuộc cùng khu nhà để so sánh
        $dupQuery = "SELECT SoPhong FROM PhongTro 
                     WHERE REGEXP_REPLACE(SoPhong, '[^0-9]', '') = REGEXP_REPLACE(:SoPhong, '[^0-9]', '') 
                       AND NhaTroId = :NhaTroId 
                       AND IsDeleted = 0";
                       
        $dupStmt = $db->prepare($dupQuery);
        $dupStmt->bindParam(":SoPhong", $soPhongMoi);
        $dupStmt->bindParam(":NhaTroId", $nhaTroId);
        $dupStmt->execute();

        if ($dupStmt->rowCount() > 0) {
            $dupRoom = $dupStmt->fetch(PDO::FETCH_ASSOC);
            http_response_code(400);
            echo json_encode([
                "status" => "error", 
                "message" => "Số phòng bạn nhập trùng với phòng '" . ($dupRoom ? $dupRoom['SoPhong'] : $soPhongMoi) . "' đã có sẵn trong khu nhà này!"
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }

        // TIẾN HÀNH TẠO PHÒNG MỚI KHI ĐÃ HỢP LỆ
        $db->beginTransaction();

        $query = "INSERT INTO PhongTro (NhaTroId, SoPhong, SoNguoiToiDa, SoLuongXeToiDa, GiaPhong, TrangThai, IsDeleted) 
                  VALUES (:NhaTroId, :SoPhong, :SoNguoiToiDa, :SoLuongXeToiDa, :GiaPhong, :TrangThai, 0)";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(":NhaTroId", $nhaTroId);
        $stmt->bindParam(":SoPhong", $soPhongMoi);
        
        $soNguoiToiDa = isset($_POST['SoNguoiToiDa']) ? intval($_POST['SoNguoiToiDa']) : 0;
        $soLuongXeToiDa = isset($_POST['SoLuongXeToiDa']) ? intval($_POST['SoLuongXeToiDa']) : 0;
        $giaPhong = doubleval($_POST['GiaPhong']);
        $trangThai = isset($_POST['TrangThai']) ? intval($_POST['TrangThai']) : 0;

        $stmt->bindParam(":SoNguoiToiDa", $soNguoiToiDa);
        $stmt->bindParam(":SoLuongXeToiDa", $soLuongXeToiDa);
        $stmt->bindParam(":GiaPhong", $giaPhong);
        $stmt->bindParam(":TrangThai", $trangThai);

        if ($stmt->execute()) {
            $roomId = $db->lastInsertId();

            // Xử lý upload danh sách hình ảnh dạng TEXT thuần túy
            if (isset($_FILES['images'])) {
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
                            $imgStmt->bindParam(":PhongTroId", $roomId);
                            $imgStmt->bindParam(":HinhAnhUrl", $imageUrl);
                            $imgStmt->execute();
                        }
                    }
                }
            }

            $db->commit();
            http_response_code(201);
            echo json_encode(["status" => "success", "message" => "Tạo phòng trọ mới thành công!", "roomId" => $roomId], JSON_UNESCAPED_UNICODE);
        } else {
            throw new Exception("Lỗi lưu dữ liệu phòng.");
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) { $db->rollBack(); } 
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Lỗi hệ thống: " . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}
?>