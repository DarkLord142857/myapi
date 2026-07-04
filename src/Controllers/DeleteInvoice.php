<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Không thể kết nối cơ sở dữ liệu."], JSON_UNESCAPED_UNICODE);
    exit();
}

// Đọc dữ liệu đầu vào từ Body JSON hoặc URL
$data = json_decode(file_get_contents("php://input"), true);
$hoa_don_id = isset($data['hoa_don_id']) ? intval($data['hoa_don_id']) : (isset($_GET['hoa_don_id']) ? intval($_GET['hoa_don_id']) : 0);
$nguoi_xoa_id = isset($data['nguoi_xoa_id']) ? intval($data['nguoi_xoa_id']) : 0; // ID của chủ trọ thực hiện xóa

if ($hoa_don_id <= 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Vui lòng cung cấp mã hóa đơn cần xóa hợp lệ."], JSON_UNESCAPED_UNICODE);
    exit();
}

if ($nguoi_xoa_id <= 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Thiếu mã định danh người thực hiện xóa (nguoi_xoa_id)."], JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    $db->beginTransaction(); // Khởi động transaction để đảm bảo an toàn dữ liệu liên kết

    // 1. Kiểm tra xem hóa đơn mục tiêu có tồn tại và chưa từng bị xóa hay không
    $queryCheck = "SELECT Id FROM HoaDon WHERE Id = :hd_id AND DeletedDate IS NULL";
    $stmtCheck = $db->prepare($queryCheck);
    $stmtCheck->execute([':hd_id' => $hoa_don_id]);
    
    if (!$stmtCheck->fetch()) {
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Hóa đơn không tồn tại hoặc đã bị xóa từ trước."], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // 2. Cập nhật bảng cha 'HoaDon': Điền dữ liệu vào DeletedDate và NguoiXoaId
    $queryDeleteHD = "UPDATE HoaDon 
                      SET DeletedDate = NOW(), 
                          NguoiXoaId = :nguoi_xoa_id 
                      WHERE Id = :hd_id";
    $stmtDeleteHD = $db->prepare($queryDeleteHD);
    $stmtDeleteHD->execute([
        ':nguoi_xoa_id' => $nguoi_xoa_id,
        ':hd_id' => $hoa_don_id
    ]);

    // 3. Cập nhật bảng con 'ChiTietHoaDon': Đổi trạng thái xóa IsDeleted = 1 và cập nhật NguoiXoaId
    $queryDeleteCT = "UPDATE ChiTietHoaDon 
                      SET IsDeleted = 1, 
                          NguoiXoaId = :nguoi_xoa_id 
                      WHERE HoaDonId = :hd_id AND IsDeleted = 0";
    $stmtDeleteCT = $db->prepare($queryDeleteCT);
    $stmtDeleteCT->execute([
        ':nguoi_xoa_id' => $nguoi_xoa_id,
        ':hd_id' => $hoa_don_id
    ]);

    // 4. Cập nhật bảng cháu 'GiaTriThuocTinhHoaDon' (chứa chỉ số điện nước cũ/mới liên kết qua ChiTietHoaDon)
    // Sử dụng INNER JOIN để tìm và cập nhật IsDeleted = 1 cùng NguoiXoaId cho toàn bộ thuộc tính của hóa đơn này
    $queryDeleteGT = "UPDATE GiaTriThuocTinhHoaDon gt
                      INNER JOIN ChiTietHoaDon ct ON gt.ChiTietHoaDonId = ct.Id
                      SET gt.IsDeleted = 1, 
                          gt.NguoiXoaId = :nguoi_xoa_id
                      WHERE ct.HoaDonId = :hd_id AND gt.IsDeleted = 0";
    $stmtDeleteGT = $db->prepare($queryDeleteGT);
    $stmtDeleteGT->execute([
        ':nguoi_xoa_id' => $nguoi_xoa_id,
        ':hd_id' => $hoa_don_id
    ]);

    // 5. Cập nhật bảng 'ThanhToan': Nếu hóa đơn này từng có lịch sử đóng tiền trước đó, ta cũng gán IsDeleted = 1
    $queryDeleteTT = "UPDATE ThanhToan 
                      SET IsDeleted = 1, 
                          NguoiXoaId = :nguoi_xoa_id 
                      WHERE HoaDonId = :hd_id AND IsDeleted = 0";
    $stmtDeleteTT = $db->prepare($queryDeleteTT);
    $stmtDeleteTT->execute([
        ':nguoi_xoa_id' => $nguoi_xoa_id,
        ':hd_id' => $hoa_don_id
    ]);

    $db->commit(); // Hoàn tất thành công toàn bộ chuỗi cập nhật liên kết

    http_response_code(200);
    echo json_encode([
        "status" => "success", 
        "message" => "Xóa hóa đơn và toàn bộ dữ liệu chi tiết liên quan thành công."
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    $db->rollBack(); // Hoàn tác hủy bỏ mọi thao tác nếu xảy ra bất kỳ lỗi SQL nào
    http_response_code(500);
    echo json_encode([
        "status" => "error", 
        "message" => "Lỗi hệ thống khi xử lý xóa liên kết: " . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>