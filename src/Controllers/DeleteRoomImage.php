<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, X-Caller-Id, X-User-Id");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 🟢 Lấy ID người gọi từ header (đồng bộ với các controller khác)
$headers = getallheaders();
$normalized_headers = [];
foreach ($headers as $key => $value) {
    $normalized_headers[strtolower($key)] = $value;
}
$nguoiXoaId = null;
if (isset($normalized_headers['x-user-id'])) {
    $nguoiXoaId = intval($normalized_headers['x-user-id']);
} elseif (isset($normalized_headers['x-caller-id'])) {
    $nguoiXoaId = intval($normalized_headers['x-caller-id']);
} elseif (isset($_POST['NguoiXoaId'])) {
    $nguoiXoaId = intval($_POST['NguoiXoaId']);
}
// Mặc định test local
if (!$nguoiXoaId) { $nguoiXoaId = 1; }

include_once __DIR__ . '/../../config/database.php';
include_once __DIR__ . '/../Middleware/authorizeLandlord.php';

$database = new Database();
$db = $database->getConnection();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 1. Xác thực quyền: Chỉ ChuTro hoặc Admin mới được phép
        $caller = ensureLandlordOrAdmin($db, $nguoiXoaId);
        $callerRole = $caller['Role'] ?? '';
        $callerId = intval($caller['id']);

        // 2. Validate input
        $hinhAnhId = intval($_POST['HinhAnhId'] ?? 0);
        if ($hinhAnhId <= 0) {
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "message" => "Thiếu hoặc sai HinhAnhId."
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }

        // 3. Lấy thông tin ảnh + kiểm tra quyền sở hữu
        $checkQuery = "SELECT pthi.Id, pthi.PhongTroId, pthi.HinhAnhUrl,
                              pt.ChuTroId, pt.IsDeleted AS PhongIsDeleted
                       FROM phongtrohinhanh pthi
                       INNER JOIN PhongTro pt ON pt.Id = pthi.PhongTroId
                       WHERE pthi.Id = :Id
                       LIMIT 1";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bindParam(":Id", $hinhAnhId, PDO::PARAM_INT);
        $checkStmt->execute();
        $image = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$image) {
            http_response_code(404);
            echo json_encode([
                "status" => "error",
                "message" => "Hình ảnh không tồn tại."
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }

        // 4. Nếu là ChuTro, phải là chủ sở hữu phòng đó (Admin bỏ qua)
        if ($callerRole === 'ChuTro') {
            if (intval($image['ChuTroId']) !== $callerId) {
                http_response_code(403);
                echo json_encode([
                    "status" => "error",
                    "message" => "Bạn không có quyền xoá ảnh của phòng trọ này."
                ], JSON_UNESCAPED_UNICODE);
                exit();
            }
        }

        // 5. Bắt đầu transaction
        $db->beginTransaction();

        // 6. Xoá record trong DB
        $delQuery = "DELETE FROM phongtrohinhanh WHERE Id = :Id";
        $delStmt = $db->prepare($delQuery);
        $delStmt->bindParam(":Id", $hinhAnhId, PDO::PARAM_INT);
        $delStmt->execute();

        // 7. Xoá file vật lý trong thư mục uploads/ (nếu có)
        $fileDeleted = false;
        $filePath = null;
        if (!empty($image['HinhAnhUrl'])) {
            $url = $image['HinhAnhUrl'];
            $parsedPath = parse_url($url, PHP_URL_PATH);
            if ($parsedPath) {
                $filePath = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . $parsedPath;
                // Bảo mật: chỉ cho phép xoá file nằm trong thư mục uploads/
                $uploadsDir = realpath(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/myapi/uploads');
                $targetFile = realpath($filePath);
                if ($uploadsDir && $targetFile && strpos($targetFile, $uploadsDir) === 0 && is_file($targetFile)) {
                    if (@unlink($targetFile)) {
                        $fileDeleted = true;
                    }
                }
            }
        }

        $db->commit();

        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "message" => "Đã xoá hình ảnh thành công.",
            "data" => [
                "HinhAnhId" => $hinhAnhId,
                "PhongTroId" => intval($image['PhongTroId']),
                "fileDeleted" => $fileDeleted
            ]
        ], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        if ($db->inTransaction()) { $db->rollBack(); }
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Lỗi hệ thống: " . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
} else {
    http_response_code(405);
    echo json_encode([
        "status" => "error",
        "message" => "Phương thức không được hỗ trợ. Hãy dùng POST."
    ], JSON_UNESCAPED_UNICODE);
}
?>
