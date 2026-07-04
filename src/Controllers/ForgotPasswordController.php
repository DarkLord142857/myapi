<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Thay thế đoạn code gọi autoload của Composer bằng 3 dòng này:
require '../../vendor/PHPMailer/src/Exception.php';
require '../../vendor/PHPMailer/src/PHPMailer.php';
require '../../vendor/PHPMailer/src/SMTP.php';

// 🔥 NHÚNG THƯ VIỆN PHPMAILER
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Đặt múi giờ chuẩn Việt Nam để tính thời hạn OTP chính xác
date_default_timezone_set('Asia/Ho_Chi_Minh'); 

include_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    http_response_code(500);
    echo json_encode(array("status" => "error", "message" => "Không thể kết nối cơ sở dữ liệu."));
    exit();
}

$data = json_decode(file_get_contents("php://input"));

if (empty($data->action)) {
    http_response_code(400);
    echo json_encode(array("status" => "error", "message" => "Hành động (action) không hợp lệ."));
    exit();
}

$action = $data->action;

// =============================================================================
// HÀNH ĐỘNG 1: GỬI MÃ OTP (send_otp)
// =============================================================================
if ($action == "send_otp") {
    if (empty($data->Identifier)) {
        http_response_code(400);
        echo json_encode(array("status" => "error", "message" => "Vui lòng nhập Email hoặc Số điện thoại."));
        exit();
    }

    $identifier = trim($data->Identifier);

    // 🛠️ ĐÃ CẢI TIẾN: Lấy thêm cột Email từ bảng Users
    $checkUser = "SELECT id, Email FROM Users WHERE (Email = :idnt OR PhoneNumber = :idnt) AND IsDeleted = 0 LIMIT 1";
    $stmtUser = $db->prepare($checkUser);
    $stmtUser->bindParam(':idnt', $identifier);
    $stmtUser->execute();

    if ($stmtUser->rowCount() == 0) {
        http_response_code(404);
        echo json_encode(array("status" => "error", "message" => "Email hoặc Số điện thoại không tồn tại trên hệ thống."));
        exit();
    }

    // Lấy thông tin User để lấy ra Gmail chính xác
    $userRow = $stmtUser->fetch(PDO::FETCH_ASSOC);
    $targetEmail = $userRow['Email'];

    if (empty($targetEmail) || !filter_var($targetEmail, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(array("status" => "error", "message" => "Tài khoản này chưa cập nhật địa chỉ Email hợp lệ để nhận OTP."));
        exit();
    }

    // Tạo mã OTP ngẫu nhiên 6 chữ số
    $otp = rand(100000, 999999);
    $expires_at = date("Y-m-d H:i:s", strtotime("+90 seconds"));

    // Thay thế cột 'otp' thành 'otp_code' cho đúng database
    $insertOtp = "INSERT INTO password_resets (identifier, otp_code, expires_at, is_verified) 
                  VALUES (:idnt, :otp, :expires, 0)";
    $stmtInsert = $db->prepare($insertOtp);
    $stmtInsert->bindParam(':idnt', $identifier);
    $stmtInsert->bindParam(':otp', $otp);
    $stmtInsert->bindParam(':expires', $expires_at);

    if ($stmtInsert->execute()) {
        
        // ---------------------------------------------------------------------
        // 🔥 TIẾN TRÌNH GỬI OTP QUA GMAIL BẰNG PHPMAILER
        // ---------------------------------------------------------------------
        $mail = new PHPMailer(true);
        try {
            // Cấu hình máy chủ SMTP của Google
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'dohongphuc2k3@gmail.com';       // 🔴 Thay bằng Gmail tổng đài của bạn
            $mail->Password   = 'gxjg hoqp andc nuws';           // 🔴 Thay bằng 16 ký tự "Mật khẩu ứng dụng" (App Password) từ Google
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->CharSet    = 'UTF-8';

            // Cấu hình người gửi và người nhận
            $mail->setFrom('dohongphuc2k3@gmail.com', 'Hệ Thống Phòng Trọ');
            $mail->addAddress($targetEmail);                      // Gửi tới Gmail của tài khoản tìm được

            // Nội dung thư bằng HTML viết giao diện đẹp mắt
            $mail->isHTML(true);
            $mail->Subject = 'Mã OTP xác thực đặt lại mật khẩu';
            $mail->Body    = "
                <div style='font-family: Arial, sans-serif; max-width: 500px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 10px;'>
                    <h2 style='color: #2563EB; text-align: center;'>Khôi phục mật khẩu</h2>
                    <p>Chào bạn,</p>
                    <p>Bạn đã yêu cầu cấp lại mật khẩu. Mã xác thực OTP của bạn là:</p>
                    <div style='text-align: center; margin: 20px 0;'>
                        <span style='font-size: 28px; font-weight: bold; color: #2563EB; background-color: #f1f5f9; padding: 10px 20px; border-radius: 6px; letter-spacing: 4px;'>$otp</span>
                    </div>
                    <p style='color: #ef4444; font-size: 13px;'>* Mã này có hiệu lực trong vòng 90 giây. Vui lòng không chia sẻ mã này cho bất kỳ ai.</p>
                </div>
            ";

            // Thực hiện lệnh gửi
            $mail->send();

            http_response_code(200);
            echo json_encode(array(
                "status" => "success", 
                "message" => "Mã OTP đã được gửi vào hòm thư Gmail của bạn.",
                // "otp" => $otp // 🔒 Hãy ẩn/xóa dòng này khi đưa vào chạy thực tế để bảo mật
            ));

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(array(
                "status" => "error", 
                "message" => "Mã OTP đã lưu nhưng hệ thống không thể gửi Email. Chi tiết lỗi: " . $mail->ErrorInfo
            ));
        }
        // ---------------------------------------------------------------------

    } else {
        http_response_code(500);
        echo json_encode(array("status" => "error", "message" => "Lỗi không thể lưu mã OTP trên máy chủ."));
    }
}

// =============================================================================
// HÀNH ĐỘNG 2: XÁC THỰC MÃ OTP (verify_otp)
// =============================================================================
if ($action == "verify_otp") {
    if (empty($data->Identifier) || empty($data->otp)) {
        http_response_code(400);
        echo json_encode(array("status" => "error", "message" => "Vui lòng nhập tài khoản và mã OTP."));
        exit();
    }

    $identifier = trim($data->Identifier);
    $otp = trim($data->otp);
    $now = date("Y-m-d H:i:s");

    $checkOtp = "SELECT id, expires_at FROM password_resets 
                 WHERE identifier = :idnt AND otp_code = :otp AND is_verified = 0 
                 ORDER BY id DESC LIMIT 1";
    $stmtOtp = $db->prepare($checkOtp);
    $stmtOtp->bindParam(':idnt', $identifier);
    $stmtOtp->bindParam(':otp', $otp);
    $stmtOtp->execute();

    if ($stmtOtp->rowCount() > 0) {
        $row = $stmtOtp->fetch(PDO::FETCH_ASSOC);
        
        if (strtotime($row['expires_at']) < strtotime($now)) {
            http_response_code(400);
            echo json_encode(array("status" => "error", "message" => "Mã OTP này đã hết hạn."));
            exit();
        }

        $updateStatus = "UPDATE password_resets SET is_verified = 1 WHERE id = :id";
        $stmtUpdate = $db->prepare($updateStatus);
        $stmtUpdate->bindParam(':id', $row['id']);
        $stmtUpdate->execute();

        http_response_code(200);
        echo json_encode(array("status" => "success", "message" => "Mã OTP hợp lệ."));
    } else {
        http_response_code(400);
        echo json_encode(array("status" => "error", "message" => "Mã OTP không chính xác."));
    }
}

// =============================================================================
// HÀNH ĐỘNG 3: CẬP NHẬT MẬT KHẨU MỚI (reset_password)
// =============================================================================
if ($action == "reset_password") {
    if (empty($data->Identifier) || empty($data->NewPassword)) {
        http_response_code(400);
        echo json_encode(array("status" => "error", "message" => "Vui lòng điền mật khẩu mới."));
        exit();
    }

    $identifier = trim($data->Identifier);
    $new_password = md5($data->NewPassword); 

    $checkVerified = "SELECT id FROM password_resets WHERE identifier = :idnt AND is_verified = 1 ORDER BY id DESC LIMIT 1";
    $stmtCheck = $db->prepare($checkVerified);
    $stmtCheck->bindParam(':idnt', $identifier);
    $stmtCheck->execute();

    if ($stmtCheck->rowCount() > 0) {
        $updatePasswordQuery = "UPDATE Users SET Password = :pass WHERE (Email = :idnt OR PhoneNumber = :idnt) AND IsDeleted = 0";
        $stmtPass = $db->prepare($updatePasswordQuery);
        $stmtPass->bindParam(':pass', $new_password);
        $stmtPass->bindParam(':idnt', $identifier);

        if ($stmtPass->execute()) {
            $cleanQuery = "DELETE FROM password_resets WHERE identifier = :idnt";
            $stmtClean = $db->prepare($cleanQuery);
            $stmtClean->bindParam(':idnt', $identifier);
            $stmtClean->execute();

            http_response_code(200);
            echo json_encode(array("status" => "success", "message" => "Thay đổi mật khẩu thành công!"));
        } else {
            http_response_code(500);
            echo json_encode(array("status" => "error", "message" => "Lỗi cập nhật mật khẩu."));
        }
    } else {
        http_response_code(403);
        echo json_encode(array("status" => "error", "message" => "Yêu cầu bị từ chối. Bạn chưa xác thực OTP."));
    }
}
?>