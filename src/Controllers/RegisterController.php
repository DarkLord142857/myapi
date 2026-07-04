<?php
// Cấu hình các Header cho phép ứng dụng di động truy cập xuyên miền (CORS)
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../../config/database.php';
include_once '../Models/RegisterUser.php';

$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    http_response_code(500);
    echo json_encode(array("status" => "error", "message" => "Không thể kết nối đến cơ sở dữ liệu."));
    exit();
}

$registerUser = new RegisterUser($db);
$data = json_decode(file_get_contents("php://input"));

// Bổ sung kiểm tra !empty($data->Username)
if (
    !empty($data->Username) &&
    !empty($data->FullName) &&
    !empty($data->Email) &&
    !empty($data->PhoneNumber) &&
    !empty($data->Password)
) {
    $registerUser->Username = $data->Username; // GÁN THÊM GIÁ TRỊ USERNAME
    $registerUser->FullName = $data->FullName;
    $registerUser->Email = $data->Email;
    $registerUser->PhoneNumber = $data->PhoneNumber;
    $registerUser->Password = md5($data->Password);
    
    $registerUser->Role = "KhachHang"; 
    $registerUser->IsApproved = 1;     

    if ($registerUser->isAccountExists()) {
        http_response_code(400);
        // Sửa lại câu thông báo lỗi chi tiết hơn
        echo json_encode(array("status" => "error", "message" => "Tên đăng nhập, Email hoặc Số điện thoại đã tồn tại trên hệ thống."));
    } else {
        if ($registerUser->create()) {
            http_response_code(201);
            echo json_encode(array("status" => "success", "message" => "Tạo tài khoản người thuê thành công!"));
        } else {
            http_response_code(500);
            echo json_encode(array("status" => "error", "message" => "Không thể lưu dữ liệu. Vui lòng thử lại sau."));
        }
    }
} else {
    http_response_code(400);
    echo json_encode(array("status" => "error", "message" => "Dữ liệu gửi lên không hợp lệ hoặc bị thiếu thông tin."));
}
?>