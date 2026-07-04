<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Khởi tạo kết nối cơ sở dữ liệu đồng bộ theo cấu trúc dự án của bạn
include_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Hệ thống không thể kết nối cơ sở dữ liệu."], JSON_UNESCAPED_UNICODE);
    exit();
}

// Nhận tham số InvoiceId truyền lên từ Flutter hoặc Web qua phương thức GET (?InvoiceId=...)
$invoiceId = isset($_GET['InvoiceId']) ? intval($_GET['InvoiceId']) : 0;

if ($invoiceId > 0) {
    try {
        // BƯỚC 1: TRUY VẤN THÔNG TIN TỔNG QUAN HÓA ĐƠN VÀ PHÒNG TRỌ
        $queryInvoice = "SELECT 
                            hd.Id AS HoaDonId,
                            hd.KyHoaDon,
                            hd.TongTienHoaDon,
                            hd.CongNo,
                            hd.TrangThaiThanhToan,
                            hd.CreatedDate,
                            pt.SoPhong,
                            pt.GiaPhong,
                            nt.TenNha,
                            nt.DiaChi
                         FROM HoaDon hd
                         INNER JOIN PhongTro pt ON hd.PhongTroId = pt.Id
                         INNER JOIN NhaTro nt ON pt.NhaTroId = nt.Id
                         WHERE hd.Id = :InvoiceId AND hd.DeletedDate IS NULL LIMIT 1";

        $stmtInvoice = $db->prepare($queryInvoice);
        $stmtInvoice->bindParam(":InvoiceId", $invoiceId, PDO::PARAM_INT);
        $stmtInvoice->execute();

        if ($stmtInvoice->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(["status" => "error", "message" => "Không tìm thấy dữ liệu hóa đơn."], JSON_UNESCAPED_UNICODE);
            exit();
        }

        $invoiceInfo = $stmtInvoice->fetch(PDO::FETCH_ASSOC);

        // BƯỚC 2: TRUY VẤN CHI TIẾT CÁC MỤC DỊCH VỤ VÀ CHỈ SỐ CŨ/MỚI ĐI KÈM
        // LEFT JOIN sang bảng GiaTriThuocTinhHoaDon để trích xuất chỉ số công tơ dựa trên ThuocTinhHoaDonId
        $queryDetails = "SELECT 
                            ct.Id AS ChiTietId,
                            ct.TenMuc AS TenDichVu,
                            ct.SoLuong,
                            ct.DonGia,
                            ct.ThanhTien,
                            gt.ThuocTinhHoaDonId,
                            gt.GiaTriSo,
                            tthd.DonVi
                         FROM ChiTietHoaDon ct
                         LEFT JOIN GiaTriThuocTinhHoaDon gt ON gt.ChiTietHoaDonId = ct.Id AND gt.IsDeleted = 0
                         LEFT JOIN ThuocTinhHoaDon tthd ON gt.ThuocTinhHoaDonId = tthd.Id AND tthd.IsDeleted = 0
                         WHERE ct.HoaDonId = :InvoiceId AND ct.IsDeleted = 0
                         ORDER BY ct.Id ASC, gt.ThuocTinhHoaDonId ASC";
        
        $stmtDetails = $db->prepare($queryDetails);
        $stmtDetails->bindParam(":InvoiceId", $invoiceId, PDO::PARAM_INT);
        $stmtDetails->execute();

        // BƯỚC 3: THUẬT TOÁN PHP - GỘP CHỈ SỐ CŨ VÀ MỚI VÀO ĐÚNG DÒNG DỊCH VỤ CHÍNH
        $servicesMap = [];

        while ($row = $stmtDetails->fetch(PDO::FETCH_ASSOC)) {
            $chiTietId = $row['ChiTietId'];

            // Nếu dịch vụ này chưa tồn tại trong mảng map, khởi tạo thông tin gốc từ ChiTietHoaDon
            if (!isset($servicesMap[$chiTietId])) {
                $servicesMap[$chiTietId] = [
                    "TenDichVu" => $row['TenDichVu'],
                    "DonVi"     => $row['DonVi'] ?? "Đồng", // Nếu không có chỉ số (như tiền phòng) mặc định đơn vị là Lượt
                    "SoLuong"   => intval($row['SoLuong']),
                    "DonGia"    => floatval($row['DonGia']),
                    "ThanhTien" => floatval($row['ThanhTien']),
                    "ChiSoCu"   => 0, // Mặc định rỗng
                    "ChiSoMoi"  => 0  // Mặc định rỗng
                ];
            }

            // Dựa trên dữ liệu INSERT: ThuocTinhHoaDonId = 1 là số cũ, 2 là số mới
            if ($row['ThuocTinhHoaDonId'] == 1) {
                $servicesMap[$chiTietId]['ChiSoCu'] = floatval($row['GiaTriSo']);
            } elseif ($row['ThuocTinhHoaDonId'] == 2) {
                $servicesMap[$chiTietId]['ChiSoMoi'] = floatval($row['GiaTriSo']);
            }
        }

        // Chuyển mảng Map (Key-Value) thành mảng số tuần tự thuần túy để trả về định dạng mảng JSON [] chuẩn
        $finalServices = array_values($servicesMap);

        // BƯỚC 4: ĐÓNG GÓI JSON PHÂN CẤP SẠCH CHO FLUTTER
        $resultData = [
            "InvoiceId"          => intval($invoiceInfo['InvoiceId'] ?? $invoiceId),
            "KyHoaDon"           => $invoiceInfo['KyHoaDon'],
            "NgayTao"            => $invoiceInfo['CreatedDate'],
            "TrangThai"          => $invoiceInfo['TrangThaiThanhToan'], // Trả về chuỗi: DaThanhToan, ThanhToanMotPhan...
            "TongTienPhaiTra"    => floatval($invoiceInfo['TongTienHoaDon']),
            "CongNoConLai"       => floatval($invoiceInfo['CongNo']),
            "ThongTinPhong"      => [
                "TenNha"   => $invoiceInfo['TenNha'],
                "DiaChi"   => $invoiceInfo['DiaChi'],
                "SoPhong"  => $invoiceInfo['SoPhong'],
                "GiaPhong" => floatval($invoiceInfo['GiaPhong']),
            ],
            "DanhSachDichVu"     => $finalServices
        ];

        http_response_code(200);
        echo json_encode([
            "status"  => "success",
            "message" => "Tải chi tiết hóa đơn thành công.",
            "data"    => $resultData
        ], JSON_UNESCAPED_UNICODE);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            "status"  => "error",
            "message" => "Lỗi truy vấn dữ liệu SQL: " . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
} else {
    http_response_code(400);
    echo json_encode([
        "status"  => "error",
        "message" => "Mã hóa đơn (InvoiceId) không hợp lệ hoặc thiếu."
    ], JSON_UNESCAPED_UNICODE);
}
?>