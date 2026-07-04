<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

include_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Câu lệnh SQL chỉ lấy những phòng chưa có người ở (TrangThai = 0) và chưa bị xóa mềm
$query = "SELECT p.Id, p.SoPhong, n.TenNha 
          FROM PhongTro p
          LEFT JOIN NhaTro n ON p.NhaTroId = n.Id
          WHERE p.TrangThai = 0 AND p.IsDeleted = 0
          ORDER BY n.TenNha ASC, p.SoPhong ASC";

$stmt = $db->prepare($query);
$stmt->execute();
$num = $stmt->rowCount();

if($num > 0) {
    $rooms_arr = array();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $rooms_arr[] = array(
            "Id" => (int)$row['Id'],
            "SoPhong" => $row['SoPhong'],
            "TenNha" => $row['TenNha']
        );
    }
    http_response_code(200);
    echo json_encode(["status" => "success", "data" => $rooms_arr]);
} else {
    http_response_code(200);
    echo json_encode(["status" => "success", "data" => [], "message" => "Hết phòng trống."]);
}
?>