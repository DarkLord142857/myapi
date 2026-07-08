<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../../../config/database.php';
include_once './User.php';
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
$adminId = $headers['X-User-Id'] ?? $headers['x-user-id'] ?? ($_GET['admin_id'] ?? 0);

if ($adminId <= 0) {
    http_response_code(400);
    echo json_encode(array("status" => "error", "message" => "Thiếu tham số admin_id hoặc header X-User-Id"));
    exit();
}

$caller = ensureLandlordOrAdmin($db, $adminId);

if (!isset($caller['Role']) || $caller['Role'] !== 'Admin') {
    http_response_code(403);
    echo json_encode(array("status" => "error", "message" => "Forbidden: Admin only."));
    exit();
}

try {
    $user = new User($db);
    $stmt = $user->readAll();
    $num = $stmt->rowCount();

    if($num > 0) {
        $users_arr = array("status" => "success", "data" => array());

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Lấy lịch sử log của user này từ bảng Users_Log
            $log_query = "SELECT ul.HanhDong, ul.GhiChu, ul.ThoiGian, a.FullName as AdminName
                          FROM Users_Log ul
                          LEFT JOIN Users a ON ul.AdminId = a.id
                          WHERE ul.UserId = :userId
                          ORDER BY ul.ThoiGian DESC LIMIT 5";
            $log_stmt = $db->prepare($log_query);
            $log_stmt->bindParam(':userId', $row['id'], PDO::PARAM_INT);
            $log_stmt->execute();

            $user_logs = array();
            while ($log_row = $log_stmt->fetch(PDO::FETCH_ASSOC)) {
                array_push($user_logs, array(
                    "hanhDong" => $log_row['HanhDong'],
                    "ghiChu" => $log_row['GhiChu'],
                    "thoiGian" => $log_row['ThoiGian'],
                    "adminName" => $log_row['AdminName']
                ));
            }

            $user_item = array(
                "id" => (int)$row['id'],
                "username" => $row['Username'],
                "fullName" => $row['FullName'],
                "identityCard" => $row['IdentityCard'],
                "phoneNumber" => $row['PhoneNumber'],
                "email" => $row['Email'],
                "role" => $row['Role'],
                "isApproved" => (int)$row['IsApproved'],
                "isDeleted" => (int)$row['IsDeleted'],
                "createdDate" => $row['CreatedDate'],
                "updatedDate" => $row['UpdatedDate'],
                "isApprovedDate" => $row['IsApprovedDate'],
                "isDeletedDate" => $row['IsDeletedDate'],
                "nguoiSuaName" => $row['NguoiSuaName'],
                "nguoiDuyetName" => $row['NguoiDuyetName'],
                "nguoiXoaName" => $row['NguoiXoaName'],
                "activityLogs" => $user_logs
            );
            array_push($users_arr["data"], $user_item);
        }
        http_response_code(200);
        echo json_encode($users_arr, JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(404);
        echo json_encode(array("status" => "error", "message" => "Không có người dùng nào."));
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(array("status" => "error", "message" => "Lỗi cơ sở dữ liệu: " . $e->getMessage()));
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array("status" => "error", "message" => "Lỗi: " . $e->getMessage()));
}
?>