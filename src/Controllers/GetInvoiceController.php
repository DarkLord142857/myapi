<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    http_response_code(500);
    echo json_encode(array("status" => "error", "message" => "Không thể kết nối cơ sở dữ liệu."));
    exit();
}

// Đón nhận tham số user_id truyền từ ứng dụng Flutter lên (?user_id=3)
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($user_id <= 0) {
    http_response_code(400);
    echo json_encode(array("status" => "error", "message" => "Thiếu hoặc sai mã ID người thuê."));
    exit();
}

try {
    // 🛠️ ĐỒNG BỘ CHUẨN XÁC THEO FILE SQL:
    // Kết nối từ HopDongThue sang bảng HoaDon, liên kết qua PhongTro và NhaTro để lấy thông tin hiển thị
    $query = "SELECT hd.Id AS HoaDonId, hd.KyHoaDon, hd.TongTienHoaDon, hd.CongNo, hd.TrangThaiThanhToan, hd.CreatedDate,
                     pt.SoPhong, nt.TenNha
              FROM HoaDon hd
              INNER JOIN HopDongThue hdt ON hd.PhongTroId = hdt.PhongTroId
              INNER JOIN PhongTro pt ON hdt.PhongTroId = pt.Id
              INNER JOIN NhaTro nt ON pt.NhaTroId = nt.Id
              WHERE hdt.KhachHangId = :user_id 
                AND hdt.IsActive = 1 
                AND hdt.IsDeleted = 0
                AND hd.DeletedDate IS NULL
              ORDER BY hd.Id DESC";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();

    $invoices = array();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $invoices[] = array(
            "InvoiceId" => (int)$row['HoaDonId'],
            "Period" => $row['KyHoaDon'],                       // Ví dụ: "06/2026"
            "TotalPrice" => (float)$row['TongTienHoaDon'],     // Tổng tiền hóa đơn
            "Debt" => (float)$row['CongNo'],                    // Khoản tiền còn nợ lại
            "Status" => $row['TrangThaiThanhToan'],             // DaThanhToan, ThanhToanMotPhan...
            "CreatedDate" => $row['CreatedDate'],
            "RoomNumber" => $row['SoPhong'],                    // Ví dụ: "P.101"
            "HouseName" => $row['TenNha']                       // Ví dụ: "Chung Cư Mini Lan Anh"
        );
    }

    http_response_code(200);
    echo json_encode(array(
        "status" => "success",
        "data" => $invoices
    ));

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array("status" => "error", "message" => "Lỗi hệ thống: " . $e->getMessage()));
}
?>