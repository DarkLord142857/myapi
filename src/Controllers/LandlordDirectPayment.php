<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Nhúng kết nối CSDL (Đúng theo cấu trúc thư mục Controllers của bạn)
include_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Phương thức không được hỗ trợ."], JSON_UNESCAPED_UNICODE);
    exit();
}

// Tiếp nhận dữ liệu do chủ trọ thao tác từ app gửi lên (hỗ trợ cả x-www-form-urlencoded và form-data)
$hoadon_id   = isset($_POST['hoadon_id']) ? intval($_POST['hoadon_id']) : 0;
$sotien_nhan = isset($_POST['sotien_nhan']) ? doubleval($_POST['sotien_nhan']) : 0.0;

if ($hoadon_id <= 0 || $sotien_nhan <= 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Dữ liệu gửi lên không hợp lệ (Mã hóa đơn hoặc Số tiền nhận phải lớn hơn 0)."], JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    $db->beginTransaction();

    // 1. Kiểm tra sự tồn tại của hóa đơn mục tiêu (Bỏ qua điều kiện trạng thái để duyệt được cả hóa đơn 'ChoDuyet')
    $checkQuery = "SELECT Id, CongNo, TongTienHoaDon FROM hoadon WHERE Id = :hoaDonId FOR UPDATE";
    $stmtCheck = $db->prepare($checkQuery);
    $stmtCheck->bindParam(':hoaDonId', $hoadon_id);
    $stmtCheck->execute();
    $invoice = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Hóa đơn không tồn tại trên hệ thống."], JSON_UNESCAPED_UNICODE);
        exit();
    }

    $cong_no_hien_tai = doubleval($invoice['CongNo']);
    
    // 2. Tính toán số công nợ mới thực tế sau khi chủ trọ nhấn duyệt thu tiền
    $congNoMoi = $cong_no_hien_tai - $sotien_nhan;
    if ($congNoMoi < 0) {
        $congNoMoi = 0; // Chặn trường hợp tiền thu vượt quá tiền nợ lẻ
    }

    // 3. Phân định trạng thái thanh toán mới dựa vào số nợ còn lại để khớp ENUM database
    $trangThaiMoi = ($congNoMoi == 0) ? 'DaThanhToan' : 'ThanhToanMotPhan';

    // 4. Cập nhật trực tiếp số công nợ thực và gạch trạng thái chính thức vào bảng hoadon
    $updateQuery = "UPDATE hoadon 
                    SET 
                        CongNo = :congNoMoi,
                        TrangThaiThanhToan = :trangThaiMoi
                    WHERE Id = :hoaDonId";
                    
    $stmtUpdate = $db->prepare($updateQuery);
    $stmtUpdate->execute([
        ':congNoMoi'   => $congNoMoi,
        ':trangThaiMoi'=> $trangThaiMoi,
        ':hoaDonId'    => $hoadon_id
    ]);

    $db->commit();
    
    // Trả về thông báo JSON thành công rõ ràng cho App nhận diện
    http_response_code(200);
    echo json_encode([
        "status" => "success",
        "message" => "Xác nhận thu tiền thành công! Trạng thái hóa đơn đã chuyển thành: " . ($trangThaiMoi == 'DaThanhToan' ? "Đã thanh toán" : "Thanh toán một phần")
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Có lỗi hệ thống phát sinh: " . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>