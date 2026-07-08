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
    empty($data->fullName) ||
    empty($data->phoneNumber) ||
    empty($data->role) ||
    empty($data->adminId) ||
    empty($data->adminName)
) {
    http_response_code(400);
    echo json_encode(array("status" => "error", "message" => "Dữ liệu truyền lên thiếu trường bắt buộc (id, fullName, phoneNumber, role, adminId, adminName)."));
    exit();
}

try {
    // 1. Cập nhật thông tin vào bảng Users
    $query = "UPDATE Users 
              SET FullName = :fullName, 
                  PhoneNumber = :phoneNumber, 
                  Role = :role,
                  NguoiSuaId = :adminId,
                  UpdatedDate = CURRENT_TIMESTAMP
              WHERE id = :id";
              
    $stmt = $db->prepare($query);
    
    $stmt->bindParam(":fullName", $data->fullName);
    $stmt->bindParam(":phoneNumber", $data->phoneNumber);
    $stmt->bindParam(":role", $data->role);
    $stmt->bindParam(":adminId", $data->adminId, PDO::PARAM_INT);
    $stmt->bindParam(":id", $data->id, PDO::PARAM_INT);
    
    if($stmt->execute()) {
        // 2. GHI LOG VÀO BẢNG USERS_LOG
        $logQuery = "INSERT INTO Users_Log (UserId, AdminId, HanhDong, GhiChu) 
                     VALUES (:userId, :adminId, 'SuaThongTin', :ghiChu)";
        $logStmt = $db->prepare($logQuery);
        
        $ghiChuLog = "Thay đổi thông tin: Tên, Số điện thoại, Vai trò";
        $logStmt->bindParam(":userId", $data->id, PDO::PARAM_INT);
        $logStmt->bindParam(":adminId", $data->adminId, PDO::PARAM_INT);
        $logStmt->bindParam(":ghiChu", $ghiChuLog);
        $logStmt->execute();

        // 3. TỰ ĐỘNG SINH THÔNG BÁO VÀO BẢNG NOTIFICATIONS
        $notifQuery = "INSERT INTO Notifications (NoiDung, TrangThai) VALUES (:noiDung, 0)";
        $noiDungNotif = "Tài khoản " . $data->fullName . " đã được sửa lại thông tin bởi Admin " . $data->adminName . " vào ngày " . date('d/m/Y H:i');
        
        $notifStmt = $db->prepare($notifQuery);
        $notifStmt->bindParam(":noiDung", $noiDungNotif);
        $notifStmt->execute();

        http_response_code(200);
        echo json_encode(array("status" => "success", "message" => "Cập nhật thông tin thành viên và tạo lịch sử thành công."));
    } else {
        http_response_code(500);
        echo json_encode(array("status" => "error", "message" => "Không thể cập nhật thông tin vào Cơ sở dữ liệu."));
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(array("status" => "error", "message" => "Lỗi cơ sở dữ liệu: " . $e->getMessage()));
}
?>