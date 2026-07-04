<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// 1. Nhúng file cấu hình Database đồng bộ theo cấu trúc dự án của bạn
// Hãy chỉnh sửa lại số lượng dấu '../' cho đúng với vị trí thực tế của file database.php từ thư mục Controllers
include_once '../../config/database.php'; 
include_once '../Middleware/authorizeLandlord.php';

$database = new Database();
$db = $database->getConnection(); // Sử dụng biến $db làm biến kết nối thay vì $conn rời rạc

if (!$db) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Hệ thống không thể kết nối cơ sở dữ liệu."], JSON_UNESCAPED_UNICODE);
    exit();
}

// 2. Tiếp nhận mã chủ trọ truyền từ Flutter lên (?landlord_id=2)
$landlord_id = isset($_GET['landlord_id']) ? intval($_GET['landlord_id']) : 0;

// Authorize caller and get caller info
$caller = ensureLandlordOrAdmin($db, $landlord_id);
$isAdmin = (isset($caller['Role']) && $caller['Role'] === 'Admin') ? 1 : 0;

if ($isAdmin === 0 && $landlord_id <= 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Thiếu mã định danh chủ trọ hợp lệ."], JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    // 3. Câu lệnh SQL liên kết dữ liệu lịch sử dòng tiền từ bảng ThanhToan sang HoaDon và PhongTro
    $query = "SELECT 
                tt.Id AS PaymentId,
                tt.NgayThanhToan AS PaymentDate,
                tt.SoTienThanhToan AS AmountPaid,
                tt.PhuongThucThanhToan AS PaymentMethod,
                tt.MaGiaoDich AS TransactionCode,
                tt.GhiChu AS Note,
                hd.KyHoaDon AS Period,
                hd.TongTienHoaDon AS TotalInvoiceAmount,
                pt.SoPhong AS RoomNumber,
                nt.TenNha AS HouseName,
                u_khach.FullName AS CustomerName
              FROM ThanhToan tt
              INNER JOIN HoaDon hd ON tt.HoaDonId = hd.Id -- Lưu ý: sửa lại thành tt.InvoiceId hoặc tt.HoaDonId dựa theo chuẩn cột trong DB của bạn
              INNER JOIN PhongTro pt ON hd.PhongTroId = pt.Id
              INNER JOIN NhaTro nt ON pt.NhaTroId = nt.Id
              INNER JOIN HopDongThue hdt ON pt.Id = hdt.PhongTroId AND hdt.IsActive = 1 AND hdt.IsDeleted = 0
              INNER JOIN Users u_khach ON hdt.KhachHangId = u_khach.id
                            WHERE ( :is_admin = 1 OR nt.MaQL = :landlord_id ) 
                AND tt.IsDeleted = 0 
                AND hd.DeletedDate IS NULL
              ORDER BY tt.NgayThanhToan DESC";

    // Thay đổi toàn bộ $conn->prepare thành $db->prepare để gọi đúng thực thể PDO kết nối
    $stmt = $db->prepare($query);
    $stmt->bindParam(":is_admin", $isAdmin, PDO::PARAM_INT);
    $stmt->bindParam(":landlord_id", $landlord_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $num = $stmt->rowCount();

    if ($num > 0) {
        $payment_list = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $payment_list[] = [
                "payment_id" => intval($row['PaymentId']),
                "room_number" => $row['RoomNumber'],
                "house_name" => $row['HouseName'],
                "period" => $row['Period'],
                "customer_name" => $row['CustomerName'],
                "amount_paid" => floatval($row['AmountPaid']),
                "total_invoice_amount" => floatval($row['TotalInvoiceAmount']),
                "payment_date" => $row['PaymentDate'],
                "payment_method" => $row['PaymentMethod'],
                "transaction_code" => $row['TransactionCode'] ?? "Không có",
                "note" => $row['Note'] ?? ""
            ];
        }
        
        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "count" => $num,
            "data" => $payment_list
        ], JSON_UNESCAPED_UNICODE);
        
    } else {
        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "count" => 0,
            "message" => "Chưa có lịch sử giao dịch thanh toán nào.",
            "data" => []
        ], JSON_UNESCAPED_UNICODE);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Lỗi xử lý câu lệnh SQL: " . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>