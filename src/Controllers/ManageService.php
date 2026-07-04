<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch($method) {
    case 'GET':
        // 🟢 CHỈ LẤY CÁC DỊCH VỤ CHƯA BỊ XÓA (IsDeleted = 0)
        try {
            $query = "SELECT id, TenDichVu, ChiPhi, MoTa FROM dichvu WHERE IsDeleted = 0 ORDER BY id DESC";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $list = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(["success" => true, "data" => $list], JSON_UNESCAPED_UNICODE);
        } catch(Exception $e) {
            echo json_encode(["success" => false, "message" => "Lỗi: " . $e->getMessage()]);
        }
        break;

    case 'POST':
        // Thêm dịch vụ mới
        $data = json_decode(file_get_contents("php://input"));
        if(!empty($data->TenDichVu) && isset($data->ChiPhi)) {
            try {
                $query = "INSERT INTO dichvu (TenDichVu, ChiPhi, MoTa, IsDeleted) VALUES (:ten, :chi_phi, :mota, 0)";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    ':ten' => $data->TenDichVu,
                    ':chi_phi' => $data->ChiPhi,
                    ':mota' => $data->MoTa ?? ''
                ]);
                echo json_encode(["success" => true, "message" => "Thêm dịch vụ thành công!"]);
            } catch(Exception $e) {
                echo json_encode(["success" => false, "message" => $e->getMessage()]);
            }
        } else {
            echo json_encode(["success" => false, "message" => "Dữ liệu không đầy đủ."]);
        }
        break;

    case 'PUT':
        // Cập nhật thông tin dịch vụ
        $data = json_decode(file_get_contents("php://input"));
        if(!empty($data->id) && !empty($data->TenDichVu) && isset($data->ChiPhi)) {
            try {
                $query = "UPDATE dichvu SET TenDichVu = :ten, ChiPhi = :chi_phi, MoTa = :mota WHERE id = :id AND IsDeleted = 0";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    ':ten' => $data->TenDichVu,
                    ':chi_phi' => $data->ChiPhi,
                    ':mota' => $data->MoTa ?? '',
                    ':id' => $data->id
                ]);
                echo json_encode(["success" => true, "message" => "Cập nhật thành công!"]);
            } catch(Exception $e) {
                echo json_encode(["success" => false, "message" => $e->getMessage()]);
            }
        } else {
            echo json_encode(["success" => false, "message" => "Dữ liệu không đầy đủ."]);
        }
        break;

    case 'DELETE':
        // 🟢 LOGIC XÓA MỀM: Cập nhật IsDeleted = 1 và ghi nhận NguoiXoaId
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $nguoi_xoa_id = isset($_GET['nguoi_xoa_id']) ? intval($_GET['nguoi_xoa_id']) : 0;

        if($id > 0 && $nguoi_xoa_id > 0) {
            try {
                $query = "UPDATE dichvu SET IsDeleted = 1, NguoiXoaId = :nguoi_xoa_id WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    ':nguoi_xoa_id' => $nguoi_xoa_id,
                    ':id' => $id
                ]);
                echo json_encode(["success" => true, "message" => "Xóa dịch vụ thành công (Đã đưa vào thùng rác)!"]);
            } catch(Exception $e) {
                echo json_encode(["success" => false, "message" => "Lỗi hệ thống khi thực hiện xóa: " . $e->getMessage()]);
            }
        } else {
            echo json_encode(["success" => false, "message" => "Thiếu ID dịch vụ hoặc ID người thực hiện xóa."]);
        }
        break;
}
?>