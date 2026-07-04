<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../../config/database.php';
$database = new Database();
$db = $database->getConnection();

$data = json_decode(file_get_contents("php://input"));

// Authorize caller (allow Admin). Middleware will read X-User-Id header or use provided landlord_id
include_once '../Middleware/authorizeLandlord.php';
ensureLandlordOrAdmin($db, isset($data->landlord_id) ? intval($data->landlord_id) : 0);

if (!empty($data->id) && isset($data->TrangThai)) {
    try {
        $db->beginTransaction();

        // 1. Cập nhật trạng thái yêu cầu dịch vụ (Ví dụ: TrangThai = 1: Đang xử lý)
        $query = "UPDATE yeucaudichvu 
                  SET TrangThai = :trang_thai, 
                      DichVuId = :dich_vu_id
                  WHERE Id = :id";
                  
        $stmt = $db->prepare($query);
        $stmt->bindParam(":trang_thai", $data->TrangThai, PDO::PARAM_INT);
        $stmt->bindParam(":dich_vu_id", $data->DichVuId, PDO::PARAM_INT);
        $stmt->bindParam(":id", $data->id, PDO::PARAM_INT);
        $stmt->execute();

        // 2. Lấy thông tin khách hàng từ yêu cầu để bắn thông báo
        $queryGet = "SELECT KhachHangId, TieuDe FROM yeucaudichvu WHERE Id = :id LIMIT 1";
        $stmtGet = $db->prepare($queryGet);
        $stmtGet->execute([':id' => $data->id]);
        $request = $stmtGet->fetch(PDO::FETCH_ASSOC);

        if ($request) {
            // 3. Tạo thông báo tổng
            $queryNoti = "INSERT INTO ThongBao (NguoiGuiId, NhaTroId, TieuDe, NoiDung, TrangThai) 
                          VALUES (:nguoi_gui, 1, 'Yêu cầu dịch vụ được tiếp nhận', :noi_dung, 1)";
            $stmtNoti = $db->prepare($queryNoti);
            $noiDungText = "Yêu cầu '" . $request['TieuDe'] . "' của bạn đã được chủ trọ tiếp nhận và bắt đầu xử lý.";
            $stmtNoti->execute([
                ':nguoi_gui' => $data->landlord_id ?? 2, // Mặc định ID chủ trọ nếu không truyền
                ':noi_dung' => $noiDungText
            ]);
            $thongBaoId = $db->lastInsertId();

            // 4. Liên kết thông báo tới đích danh khách thuê (TrangThai 0 = Chưa xem)
            $queryUserNoti = "INSERT INTO ThongBao_User (ThongBaoId, UserId, TrangThai) VALUES (:tb_id, :user_id, 0)";
            $stmtUserNoti = $db->prepare($queryUserNoti);
            $stmtUserNoti->execute([
                ':tb_id' => $thongBaoId,
                ':user_id' => $request['KhachHangId']
            ]);
        }

        $db->commit();
        echo json_encode(["success" => true, "message" => "Đã tiếp nhận yêu cầu dịch vụ và gửi thông báo!"]);
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(["success" => false, "message" => "Lỗi: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Dữ liệu đầu vào không hợp lệ."]);
}
?>