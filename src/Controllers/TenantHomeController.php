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

// Lấy Id của người thuê trọ truyền từ Flutter lên (Ví dụ qua URL: ?user_id=3)
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($user_id <= 0) {
    http_response_code(400);
    echo json_encode(array("status" => "error", "message" => "Thiếu hoặc sai ID người dùng."));
    exit();
}

try {
    $response_data = array();

    // =============================================================================
    // PHẦN 1: LẤY THÔNG TIN TÀI KHOẢN, PHÒNG TRỌ VÀ HỢP ĐỒNG (Dùng JOIN)
    // =============================================================================
    $roomQuery = "SELECT
                    u.FullName, u.Role,
                    pt.id AS PhongTroId, pt.SoPhong, pt.GiaPhong,
                    nt.TenNha, nt.DiaChi,
                    hd.NgayBatDau, hd.NgayKetThuc, hd.IsActive
                  FROM users u
                  LEFT JOIN HopDongThue hd ON u.id = hd.KhachHangId AND hd.IsActive = 1
                  LEFT JOIN PhongTro pt ON hd.PhongTroId = pt.id
                  LEFT JOIN NhaTro nt ON pt.NhaTroId = nt.id
                  WHERE u.id = :user_id LIMIT 1";

    $stmtRoom = $db->prepare($roomQuery);
    $stmtRoom->bindParam(':user_id', $user_id);
    $stmtRoom->execute();

    if ($stmtRoom->rowCount() > 0) {
        $roomInfo = $stmtRoom->fetch(PDO::FETCH_ASSOC);

        $response_data['user'] = array(
            "FullName" => $roomInfo['FullName'],
            "Role" => $roomInfo['Role']
        );

        // Nếu người này đã được gán phòng và có hợp đồng hoạt động
        if (!empty($roomInfo['PhongTroId'])) {
            $response_data['room_status'] = array(
                "has_room" => true,
                "HouseName" => $roomInfo['TenNha'],
                "RoomNumber" => $roomInfo['SoPhong'],
                "Address" => $roomInfo['DiaChi'],
                "GiaPhong" => (float)$roomInfo['GiaPhong'],
                "ContractStart" => $roomInfo['NgayBatDau'],
                "ContractEnd" => $roomInfo['NgayKetThuc'],
                "IsActive" => (int)$roomInfo['IsActive']
            );
            $phong_tro_id = $roomInfo['PhongTroId'];
        } else {
            $response_data['room_status'] = array(
                "has_room" => false,
                "message" => "Tài khoản của bạn chưa được xếp phòng hoặc hợp đồng đã hết hạn."
            );
            $phong_tro_id = 0;
        }
    } else {
        http_response_code(404);
        echo json_encode(array("status" => "error", "message" => "Không tìm thấy thông tin người thuê này."));
        exit();
    }

    // =============================================================================
    // PHẦN 2: LẤY DANH SÁCH HÓA ĐƠN CHƯA THANH TOÁN HOẶC MỚI PHÁT SINH
    // =============================================================================
    $response_data['invoices_to_pay'] = []; // Đổi từ single object thành một danh sách (Array)

    if ($phong_tro_id > 0) {
        // Lấy tất cả hóa đơn chưa thanh toán hoặc thanh toán một phần, sắp xếp hóa đơn mới nhất lên đầu
        $invoiceQuery = "SELECT id, KyHoaDon, TongTienHoaDon, CongNo, TrangThaiThanhToan
                         FROM HoaDon
                         WHERE PhongTroId = :phong_id AND TrangThaiThanhToan IN ('ChuaThanhToan', 'ThanhToanMotPhan')
                         ORDER BY id DESC";

        $stmtInvoice = $db->prepare($invoiceQuery);
        $stmtInvoice->bindParam(':phong_id', $phong_tro_id);
        $stmtInvoice->execute();

        while ($invoiceInfo = $stmtInvoice->fetch(PDO::FETCH_ASSOC)) {
            $response_data['invoices_to_pay'][] = array(
                "InvoiceId" => (int)$invoiceInfo['id'],
                "Period" => $invoiceInfo['KyHoaDon'],
                "Total" => (float)$invoiceInfo['TongTienHoaDon'],
                "Debt" => (float)$invoiceInfo['CongNo'],
                "Status" => $invoiceInfo['TrangThaiThanhToan']
            );
        }
    }

    // Trả về chuỗi kết quả JSON tổng hợp thành công
    http_response_code(200);
    echo json_encode(array(
        "status" => "success",
        "data" => $response_data
    ));

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(array("status" => "error", "message" => "Lỗi xử lý SQL: " . $e->getMessage()));
}
?>