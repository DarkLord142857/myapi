<?php
/**
 * Controller: UpdatePaymentStatus.php
 * Mục đích: Cho phép Admin hoặc ChuTro thay đổi trực tiếp
 *           cột `hoadon.TrangThaiThanhToan` (4 trạng thái chuẩn mới
 *           + chấp nhận tương thích ngược với giá trị cũ trong DB).
 *
 * Endpoint : POST /api/UpdatePaymentStatus.php
 * Headers  : X-User-Id: <id người gọi>  (ưu tiên)
 *            hoặc truyền caller_id trong body JSON.
 * Body     : {
 *              "hoa_don_id":   int   (bắt buộc),
 *              "trang_thai":   string (bắt buộc, xem $ALLOWED_STATUS),
 *              "caller_id":    int   (tuỳ chọn, fallback nếu thiếu header),
 *              "ghi_chu":      string (tuỳ chọn, mặc định lý do tự động)
 *            }
 *
 * Trả về  : JSON { status, message, data { HoaDonId, TrangThaiThanhToan, ... } }
 */
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, X-User-Id");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

include_once '../../config/database.php';
include_once '../Middleware/authorizeLandlord.php';

$database = new Database();
$db = $database->getConnection();
if (!$db) {
    http_response_code(500);
    echo json_encode([
        "status"  => "error",
        "message" => "Không thể kết nối cơ sở dữ liệu."
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// ============================================================
// 1. Đọc & validate input
// ============================================================
$raw  = file_get_contents("php://input");
$data = json_decode($raw, true);
if (!is_array($data)) {
    // Fallback cho form-urlencoded
    $data = $_POST;
}

$hoaDonId   = isset($data['hoa_don_id']) ? intval($data['hoa_don_id']) : 0;
$trangThai  = isset($data['trang_thai'])  ? trim($data['trang_thai'])   : '';
$callerIdIn = isset($data['caller_id'])   ? intval($data['caller_id'])  : 0;
$ghiChuUser = isset($data['ghi_chu'])     ? trim($data['ghi_chu'])      : '';

// ============================================================
// 2. Phân quyền: chỉ Admin hoặc ChuTro
//    (đọc X-User-Id header; nếu thiếu thì dùng caller_id từ body)
// ============================================================
$caller = ensureLandlordOrAdmin($db, $callerIdIn);
$role   = $caller['Role']; // 'Admin' | 'ChuTro'

// ============================================================
// 3. Validate các trường bắt buộc
// ============================================================
if ($hoaDonId <= 0) {
    http_response_code(400);
    echo json_encode([
        "status"  => "error",
        "message" => "Thiếu hoặc sai định dạng 'hoa_don_id'."
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// 4 trạng thái chuẩn mới + alias tương thích ngược với dữ liệu cũ
$ALLOWED_STATUS = [
    // Chuẩn mới (lowercase) - theo yêu cầu nghiệp vụ
    'dathanhtoan',
    'chuathanhtoan',
    'choduyet',
    'thanhtoanmotphan',
    // Alias cũ vẫn còn trong DB (DangLei cũ) - tự chuyển sang chuẩn mới
    'DaThanhToan',
    'ThanhToanMotPhan',
    'ChoDuyet',
    'ChuaThanhToan',
];

if ($trangThai === '' || !in_array($trangThai, $ALLOWED_STATUS, true)) {
    http_response_code(400);
    echo json_encode([
        "status"  => "error",
        "message" => "Trạng thái không hợp lệ. Cho phép: dathanhtoan | chuathanhtoan | choduyet | thanhtoanmotphan."
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// Map về giá trị chuẩn mới (lowercase) trước khi ghi DB
$STATUS_NORMALIZE = [
    'dathanhtoan'      => 'dathanhtoan',
    'DaThanhToan'      => 'dathanhtoan',
    'chuathanhtoan'    => 'chuathanhtoan',
    'ChuaThanhToan'    => 'chuathanhtoan',
    'choduyet'         => 'choduyet',
    'ChoDuyet'         => 'choduyet',
    'thanhtoanmotphan' => 'thanhtoanmotphan',
    'ThanhToanMotPhan' => 'thanhtoanmotphan',
];
$trangThaiChuan = $STATUS_NORMALIZE[$trangThai];

// ============================================================
// 4. Kiểm tra hóa đơn tồn tại + quyền sở hữu
//    - Admin: được sửa tất cả
//    - ChuTro: chỉ sửa hóa đơn của phòng thuộc nhà trọ mà họ quản lý (nhatro.MaQL)
// ============================================================
try {
    $db->beginTransaction();

    $sqlCheck = "SELECT hd.Id,
                        hd.PhongTroId,
                        hd.KyHoaDon,
                        hd.TrangThaiThanhToan AS TrangThaiCu,
                        hd.TongTienHoaDon,
                        hd.CongNo,
                        pt.NhaTroId,
                        nt.MaQL
                 FROM hoadon hd
                 INNER JOIN phongtro pt ON hd.PhongTroId = pt.Id
                 INNER JOIN nhatro  nt ON pt.NhaTroId   = nt.Id
                 WHERE hd.Id = :id
                   AND hd.DeletedDate IS NULL
                 FOR UPDATE";

    $stmtCheck = $db->prepare($sqlCheck);
    $stmtCheck->bindParam(':id', $hoaDonId, PDO::PARAM_INT);
    $stmtCheck->execute();
    $hoaDon = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if (!$hoaDon) {
        $db->rollBack();
        http_response_code(404);
        echo json_encode([
            "status"  => "error",
            "message" => "Hóa đơn không tồn tại hoặc đã bị xóa."
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // ChuTro chỉ được sửa hóa đơn thuộc nhà mình quản lý
    if ($role === 'ChuTro') {
        $callerId = intval($caller['id']);
        if (intval($hoaDon['MaQL']) !== $callerId) {
            $db->rollBack();
            http_response_code(403);
            echo json_encode([
                "status"  => "error",
                "message" => "Bạn không có quyền thay đổi trạng thái hóa đơn thuộc nhà trọ này."
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }
    }
    // Admin: bỏ qua kiểm tra MaQL

    $trangThaiCu = $hoaDon['TrangThaiCu'];

    // Không cho cập nhật nếu trạng thái mới trùng trạng thái cũ (idempotent)
    $trangThaiCuChuan = isset($STATUS_NORMALIZE[$trangThaiCu]) ? $STATUS_NORMALIZE[$trangThaiCu] : strtolower($trangThaiCu);
    if ($trangThaiCuChuan === $trangThaiChuan) {
        $db->rollBack();
        http_response_code(200);
        echo json_encode([
            "status"  => "success",
            "message" => "Trạng thái không thay đổi (đã ở giá trị mong muốn).",
            "data"    => [
                "HoaDonId"            => intval($hoaDon['Id']),
                "KyHoaDon"            => $hoaDon['KyHoaDon'],
                "TrangThaiThanhToan"  => $trangThaiChuan,
                "TrangThaiCu"         => $trangThaiCu
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // ============================================================
    // 5. Cập nhật trạng thái trên bảng hoadon
    // ============================================================
    $sqlUpdate = "UPDATE hoadon
                  SET TrangThaiThanhToan = :trang_thai
                  WHERE Id = :id";
    $stmtUpdate = $db->prepare($sqlUpdate);
    $stmtUpdate->bindParam(':trang_thai', $trangThaiChuan, PDO::PARAM_STR);
    $stmtUpdate->bindParam(':id', $hoaDonId, PDO::PARAM_INT);
    $stmtUpdate->execute();

    // ============================================================
    // 6. Ghi log vào thanhtoan.GhiChu (không tạo dòng giao dịch,
    //    chỉ lưu vết thay đổi trạng thái)
    //    Nếu hóa đơn chưa có bất kỳ dòng thanhtoan nào,
    //    dùng INSERT với SoTienThanhToan = 0 để không vỡ NOT NULL.
    // ============================================================
    $callerId  = intval($caller['id']);
    $callerNm  = isset($caller['FullName']) ? $caller['FullName'] : ('User#' . $callerId);
    $kyHoaDon  = $hoaDon['KyHoaDon'];
    $ghiChuLog = sprintf(
        '[Trạng thái] %s đổi "%s" -> "%s" cho hóa đơn tháng %s.%s',
        $callerNm . ' (' . $role . ' #' . $callerId . ')',
        $trangThaiCu,
        $trangThaiChuan,
        $kyHoaDon,
        $ghiChuUser !== '' ? ' Lý do: ' . $ghiChuUser : ''
    );

    $sqlLog = "INSERT INTO thanhtoan
                  (HoaDonId, SoTienThanhToan, PhuongThucThanhToan,
                   MaGiaoDich, NguoiNhanId, GhiChu, NgayThanhToan)
               VALUES
                  (:hoa_don_id, 0, 'DoiTrangThai', :ma_gd, :nguoi_nhan, :ghi_chu, NOW())";
    $maGiaoDich = 'STATUS_LOG_' . $hoaDonId . '_' . date('YmdHis');
    $stmtLog = $db->prepare($sqlLog);
    $stmtLog->bindParam(':hoa_don_id', $hoaDonId, PDO::PARAM_INT);
    $stmtLog->bindParam(':ma_gd', $maGiaoDich, PDO::PARAM_STR);
    $stmtLog->bindParam(':nguoi_nhan', $callerId, PDO::PARAM_INT);
    $stmtLog->bindParam(':ghi_chu', $ghiChuLog, PDO::PARAM_STR);
    $stmtLog->execute();

    $db->commit();

    http_response_code(200);
    echo json_encode([
        "status"  => "success",
        "message" => "Cập nhật trạng thái thanh toán thành công.",
        "data"    => [
            "HoaDonId"           => $hoaDonId,
            "KyHoaDon"           => $kyHoaDon,
            "TrangThaiCu"        => $trangThaiCu,
            "TrangThaiThanhToan" => $trangThaiChuan,
            "NguoiThayDoiId"     => $callerId,
            "NguoiThayDoiRole"   => $role,
            "LogId"              => intval($db->lastInsertId())
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        "status"  => "error",
        "message" => "Lỗi hệ thống: " . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
