<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../../config/database.php';
include_once '../Middleware/authorizeLandlord.php';

$database = new Database();
$db = $database->getConnection();

// Lấy id của chủ trọ truyền qua query string từ Flutter (Ví dụ: ?landlord_id=2)
// Allow Admins via middleware check
// read landlord_id param (0 if not supplied)
$landlord_id = isset($_GET['landlord_id']) ? intval($_GET['landlord_id']) : 0;

// authorize and get caller info
$caller = ensureLandlordOrAdmin($db, $landlord_id);
$isAdmin = (isset($caller['Role']) && $caller['Role'] === 'Admin') ? 1 : 0;

if ($isAdmin === 1 || $landlord_id > 0) {
    try {
        // --- TRUY VẤN 1: THỐNG KÊ SỐ LƯỢNG PHÒNG TRỌ (Tổng số, Đang ở, Còn trống) ---
        $qRooms = "SELECT
                    COUNT(pt.Id) as TongSoPhong,
                    SUM(CASE WHEN pt.TrangThai = 1 THEN 1 ELSE 0 END) as DangO,
                    SUM(CASE WHEN pt.TrangThai = 0 THEN 1 ELSE 0 END) as ConTrong
                   FROM PhongTro pt
                   INNER JOIN NhaTro nt ON pt.NhaTroId = nt.Id
                   WHERE ( :is_admin = 1 OR nt.MaQL = :landlord_id ) AND pt.IsDeleted = 0 AND nt.IsDeleted = 0";

        $stmtRoom = $db->prepare($qRooms);
        $stmtRoom->bindParam(":is_admin", $isAdmin, PDO::PARAM_INT);
        $stmtRoom->bindParam(":landlord_id", $landlord_id, PDO::PARAM_INT);
        $stmtRoom->execute();
        $roomStats = $stmtRoom->fetch(PDO::FETCH_ASSOC);

        $tongPhong = (int)($roomStats['TongSoPhong'] ?? 0);
        $dangO = (int)($roomStats['DangO'] ?? 0);
        $conTrong = (int)($roomStats['ConTrong'] ?? 0);


        // --- TRUY VẤN 2: THỐNG KÊ TÀI CHÍNH TỔNG QUAN (Đã thu & Còn nợ) ---
        $qFinance = "SELECT
                        (SELECT IFNULL(SUM(hd.CongNo), 0)
                         FROM HoaDon hd
                         INNER JOIN PhongTro pt ON hd.PhongTroId = pt.Id
                         INNER JOIN NhaTro nt ON pt.NhaTroId = nt.Id
                         WHERE ( :is_admin = 1 OR nt.MaQL = :landlord_id1) ) as ConNo,

                        (SELECT IFNULL(SUM(tt.SoTienThanhToan), 0)
                         FROM ThanhToan tt
                         INNER JOIN HoaDon hd ON tt.HoaDonId = hd.Id
                         INNER JOIN PhongTro pt ON hd.PhongTroId = pt.Id
                         INNER JOIN NhaTro nt ON pt.NhaTroId = nt.Id
                         WHERE ( :is_admin = 1 OR nt.MaQL = :landlord_id2) ) as DaThu";

        $stmtFinance = $db->prepare($qFinance);
        $stmtFinance->bindParam(":is_admin", $isAdmin, PDO::PARAM_INT);
        $stmtFinance->bindParam(":landlord_id1", $landlord_id, PDO::PARAM_INT);
        $stmtFinance->bindParam(":landlord_id2", $landlord_id, PDO::PARAM_INT);
        $stmtFinance->execute();
        $financeStats = $stmtFinance->fetch(PDO::FETCH_ASSOC);

        $daThu = (float)($financeStats['DaThu'] ?? 0.0);
        $conNo = (float)($financeStats['ConNo'] ?? 0.0);


        // --- TRUY VẤN 5: LẤY 5 YÊU CẦU DỊCH VỤ / SỰ CỐ MỚI GẦN ĐÂY ---
        $qRequests = "SELECT yc.Id, yc.TieuDe, yc.MoTa, yc.TrangThai, yc.CreatedDate, u.FullName as TenKhach
                      FROM YeuCauDichVu yc
                      LEFT JOIN Users u ON yc.KhachHangId = u.id
                      ORDER BY yc.CreatedDate DESC
                      LIMIT 5";

        $stmtReq = $db->prepare($qRequests);
        $stmtReq->execute();
        $yeuCauList = [];
        while($rowReq = $stmtReq->fetch(PDO::FETCH_ASSOC)) {
            $yeuCauList[] = [
                "id" => (int)$rowReq['Id'],
                "tieuDe" => $rowReq['TieuDe'],
                "moTa" => $rowReq['MoTa'],
                "trangThai" => (int)$rowReq['TrangThai'], // 0: Chưa xử lý, 1: Đã xử lý
                "tenKhachHang" => $rowReq['TenKhach'] ?? "Khách thuê",
                "ngayTao" => $rowReq['CreatedDate']
            ];
        }

      // --- TRUY VẤN 6: LẤY 5 THÔNG BÁO CHỦ TRỌ ĐÃ PHÁT ĐI ---
        $qNotifications = "SELECT t.Id, t.TieuDe, t.NoiDung, t.CreatedDate, nt.TenNha
                   FROM ThongBao t
                   LEFT JOIN NhaTro nt ON t.NhaTroId = nt.Id
                   WHERE ( :is_admin = 1 OR nt.MaQL = :landlord_id ) OR t.NhaTroId IS NULL
                   ORDER BY t.CreatedDate DESC
                   LIMIT 5";

        $stmtNoti = $db->prepare($qNotifications);
        $stmtNoti->bindParam(":is_admin", $isAdmin, PDO::PARAM_INT);
        $stmtNoti->bindParam(":landlord_id", $landlord_id, PDO::PARAM_INT);
        $stmtNoti->execute();
        $thongBaoList = [];
        while($rowNoti = $stmtNoti->fetch(PDO::FETCH_ASSOC)) {
            $thongBaoList[] = [
                "id" => (int)$rowNoti['Id'],
                "tieuDe" => $rowNoti['TieuDe'],
                "noiDung" => $rowNoti['NoiDung'],
                "tenNhaTro" => $rowNoti['TenNha'] ?? "Tất cả các khu nhà",
                "ngayTao" => $rowNoti['CreatedDate']
            ];
        }

       // --- TRẢ KẾT QUẢ ĐỒNG BỘ JSON VỀ FLUTTER ---
        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "data" => [
                "tongPhong" => $tongPhong,
                "dangO" => $dangO,
                "conTrong" => $conTrong,
                "daThu" => $daThu,
                "conNo" => $conNo,
                "yeuCauMoi" => $yeuCauList,      // Mảng riêng cho Sự cố/Yêu cầu dịch vụ
                "thongBaoDaGui" => $thongBaoList  // Mảng riêng cho Thông báo thuần túy
            ]
        ]);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Lỗi truy vấn cơ sở dữ liệu: " . $e->getMessage()
        ]);
    }
} else {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Tham số mã chủ trọ (landlord_id) không hợp lệ."
    ]);
}
?>