<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../../../config/database.php';
include_once 'User.php';
include_once '../../Middleware/authorizeLandlord.php';

$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    http_response_code(500);
    echo json_encode(array("status" => "error", "message" => "Không thể kết nối cơ sở dữ liệu."));
    exit();
}

// Kiểm tra quyền Admin
$headers = getallheaders();
$adminId = $headers['X-User-Id'] ?? $headers['x-user-id'] ?? 0;
$caller = ensureLandlordOrAdmin($db, $adminId);

if (!isset($caller['Role']) || $caller['Role'] !== 'Admin') {
    http_response_code(403);
    echo json_encode(array("status" => "error", "message" => "Forbidden: Admin only."));
    exit();
}

$data = json_decode(file_get_contents("php://input"));

// Kiểm tra các trường dữ liệu bắt buộc truyền lên từ Flutter
if(
    empty($data->id) || 
    empty($data->action) || 
    empty($data->adminId) || 
    empty($data->adminName) || 
    empty($data->targetName)
) {
    http_response_code(400);
    echo json_encode(array("status" => "error", "message" => "Thiếu dữ liệu bắt buộc (id, action, adminId, adminName, targetName)."));
    exit();
}

try {
    $user = new User($db);
    $user->id = $data->id;
    
    $action = $data->action;
    $adminId = $data->adminId;
    $adminName = $data->adminName;
    $targetName = $data->targetName;
    
    // Kiểm tra hành động (action) hợp lệ
    $validActions = array('approve', 'delete', 'unlock');
    if (!in_array($action, $validActions)) {
        http_response_code(400);
        echo json_encode(array("status" => "error", "message" => "Hành động không hợp lệ. Chỉ chấp nhận: approve, delete, unlock."));
        exit();
    }
    
    // Kiểm tra user tồn tại
    $checkQuery = "SELECT id FROM Users WHERE id = :id LIMIT 1";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(':id', $data->id, PDO::PARAM_INT);
    $checkStmt->execute();
    
    if ($checkStmt->rowCount() == 0) {
        http_response_code(404);
        echo json_encode(array("status" => "error", "message" => "Người dùng không tồn tại."));
        exit();
    }
    
    // Thực hiện hành động tương ứng
    $success = false;
    $message = "";
    
    if($action == "approve") {
        if($user->approve($adminId, $adminName, $targetName)) {
            $success = true;
            $message = "Đã phê duyệt thành viên thành công.";
        } else {
            $message = "Không thể phê duyệt thành viên.";
        }
    } 
    else if($action == "delete") {
        if($user->softDelete($adminId, $adminName, $targetName)) {
            $success = true;
            $message = "Đã khóa tài khoản thành công.";
        } else {
            $message = "Không thể khóa tài khoản.";
        }
    } 
    else if($action == "unlock") {
        if($user->unlock($adminId, $adminName, $targetName)) {
            $success = true;
            $message = "Đã mở khóa tài khoản thành công.";
        } else {
            $message = "Không thể mở khóa tài khoản.";
        }
    }
    
    if($success) {
        http_response_code(200);
        echo json_encode(array("status" => "success", "message" => $message));
    } else {
        http_response_code(500);
        echo json_encode(array("status" => "error", "message" => $message));
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(array("status" => "error", "message" => "Lỗi cơ sở dữ liệu: " . $e->getMessage()));
}
?>