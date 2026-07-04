<?php
// Cấu hình các Header cho phép phản hồi dữ liệu dạng JSON công khai
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Nhúng file kết nối cơ sở dữ liệu
include_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

try {
    // KIỂM TRA KẾT NỐI DATABASE TRƯỚC
    if ($db === null) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Không thể kết nối tới cơ sở dữ liệu."
        ]);
        exit();
    }

    // 🟢 SỬA LỖI SQL: Chuyển INNER JOIN thành LEFT JOIN để phòng trống (chưa có hợp đồng/khách) vẫn hiển thị đầy đủ
    // Đồng thời thêm điều kiện pt.IsDeleted = 0 để ẩn phòng đã xóa và ORDER BY pt.Id DESC để phòng mới lên đầu
    $query = "SELECT 
                pt.Id as PhongTroId, 
                pt.SoPhong as SoPhong, 
                pt.GiaPhong as GiaPhong,
                pt.TrangThai as TrangThai,         
                pt.SoNguoiToiDa as SoNguoiToiDa,   
                pt.SoLuongXeToiDa as SoLuongXeToiDa,
                u.FullName as TenKhachThue,
                u.PhoneNumber as SoDienThoaiKhach
              FROM PhongTro pt
              LEFT JOIN HopDongThue hd ON pt.Id = hd.PhongTroId AND hd.IsActive = 1 AND hd.IsDeleted = 0
              LEFT JOIN Users u ON hd.KhachHangId = u.id AND u.IsDeleted = 0
              WHERE pt.IsDeleted = 0
              ORDER BY pt.Id DESC";

    $stmt = $db->prepare($query);
    $stmt->execute();
    $num = $stmt->rowCount();

    $roomList = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $khachThue = null;
        
        // Nếu phòng đang hoạt động (TrangThai = 1) và có thông tin người thuê
        if ((int)$row['TrangThai'] === 1 && !empty($row['TenKhachThue'])) {
            $khachThue = [
                "hoTen" => $row['TenKhachThue'],
                "soDienThoai" => $row['SoDienThoaiKhach'] ?? ''
            ];
        }

        // 🟢 SỬA LỖI KEY JSON: Chuẩn hóa khớp chính xác 100% với các thuộc tính trong Model Flutter của bạn
        $roomList[] = [
            "phongId" => (int)$row['PhongTroId'],
            "soPhong" => $row['SoPhong'],
            "giaPhong" => (double)$row['GiaPhong'],
            "isActive" => (int)$row['TrangThai'], // Đổi từ trangThai thành isActive cho khớp landlord_room_screen.dart dòng 248
            "soNguoiToiDa" => (int)($row['SoNguoiToiDa'] ?? 2),   
            "soLuongXeToiDa" => (int)($row['SoLuongXeToiDa'] ?? 2), 
            "khachThue" => $khachThue
        ];
    }
    
    http_response_code(200);
    echo json_encode([
        "status" => "success",
        "message" => "Lấy danh sách phòng thành công! Tìm thấy " . $num . " phòng trọ.",
        "total_rooms" => $num,
        "data" => $roomList
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Lỗi hệ thống: " . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>