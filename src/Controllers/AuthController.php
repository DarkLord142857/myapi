<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../../config/database.php';
include_once '../Models/User.php';

$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    http_response_code(500);
    echo json_encode(array("status" => "error", "message" => "Hệ thống mất kết nối cơ sở dữ liệu."));
    exit();
}

$user = new User($db);
$data = json_decode(file_get_contents("php://input"));

if (!empty($data->Identifier) && !empty($data->Password)) {
    
    $user->LoginIdentifier = $data->Identifier;
    
    if ($user->findByUserIdentifier()) {
        if ($user->IsApproved != 1) {
            http_response_code(401);
            echo json_encode(array("status" => "error", "message" => "Tài khoản chưa được duyệt hoặc đã bị khóa."));
            exit();
        }

        if (md5($data->Password) === $user->Password) {
            http_response_code(200);
            echo json_encode(array(
                "status" => "success",
                "message" => "Đăng nhập thành công.",
                "user" => array(
                    "id" => $user->id,
                    "fullname" => $user->FullName,
                    "role" => $user->Role
                )
            ));
        } else {
            http_response_code(401);
            echo json_encode(array("status" => "error", "message" => "Mật khẩu không chính xác."));
        }
    } else {
        http_response_code(401);
        echo json_encode(array("status" => "error", "message" => "Tài khoản, Gmail hoặc Số điện thoại không tồn tại."));
    }
} else {
    http_response_code(400);
    echo json_encode(array("status" => "error", "message" => "Vui lòng điền đầy đủ thông tin."));
}
?>