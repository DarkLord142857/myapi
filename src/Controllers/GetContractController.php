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

// Lấy Id của người thuê trọ từ Flutter truyền lên (?user_id=3)
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($user_id <= 0) {
    http_response_code(400);
    echo json_encode(array("status" => "error", "message" => "Thiếu hoặc sai mã ID người thuê."));
    exit();
}

try {
    // 🛠️ ĐÃ SỬA: Đổi tên bảng từ "HopDong" thành "hopdongthue" trong mệnh đề FROM
    $query = "SELECT hd.id AS HopDongId, hd.NgayBatDau, hd.NgayKetThuc, hd.TienCoc, hd.isActive AS TrangThaiHopDong,
                     pt.SoPhong, pt.GiaPhong, nt.TenNha, nt.DiaChi
              FROM hopdongthue hd
              INNER JOIN PhongTro pt ON hd.PhongTroId = pt.id
              INNER JOIN NhaTro nt ON pt.NhaTroId = nt.id
              WHERE hd.KhachHangId = :user_id
              ORDER BY hd.id DESC";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();

    $contracts = array();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $contracts[] = array(
            "ContractId" => (int)$row['HopDongId'],
            "StartDate" => $row['NgayBatDau'],
            "EndDate" => $row['NgayKetThuc'],
            "Deposit" => (float)$row['TienCoc'],
            "Status" => $row['TrangThaiHopDong'], // Thường là: ConHan, HetHan, DaThanhLy
            "RoomNumber" => $row['SoPhong'],
            "RoomPrice" => (float)$row['GiaPhong'],
            "HouseName" => $row['TenNha'],
            "Address" => $row['DiaChi']
        );
    }

    http_response_code(200);
    echo json_encode(array(
        "status" => "success",
        "data" => $contracts
    ));

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array("status" => "error", "message" => "Lỗi hệ thống: " . $e->getMessage()));
}
?>