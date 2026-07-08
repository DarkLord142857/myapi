<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../../../config/database.php';
include_once '../../Middleware/authorizeLandlord.php';

$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    http_response_code(500);
    echo json_encode(array("status" => "error", "message" => "Không thể kết nối cơ sở dữ liệu."));
    exit();
}

// Xác thực quyền Admin
$headers = getallheaders();
$adminId = $headers['X-User-Id'] ?? $headers['x-user-id'] ?? ($_GET['admin_id'] ?? 0);
$caller = ensureLandlordOrAdmin($db, $adminId);

if (!isset($caller['Role']) || $caller['Role'] !== 'Admin') {
    http_response_code(403);
    echo json_encode(array("status" => "error", "message" => "Forbidden: Admin only."));
    exit();
}

// Lấy tham số
$thang   = isset($_GET['thang']) ? intval($_GET['thang']) : 0;
$nam     = isset($_GET['nam']) ? intval($_GET['nam']) : date('Y');
$houseId = isset($_GET['house_id']) ? intval($_GET['house_id']) : 0;

if ($nam < 1900 || $nam > 9999) {
    http_response_code(400);
    echo json_encode(array("status" => "error", "message" => "Tham số năm không hợp lệ."));
    exit();
}

$cheDo = ($thang >= 1 && $thang <= 12) ? 'thang' : 'nam';

try {
    $chiTietTheoNhaTro = tinhDoanhThuTheoNhaTro($db, $thang, $nam, $houseId);

    if ($cheDo === 'thang') {
        $tongQuan = array(
            "tongTienCoc"    => 0,
            "tongTienNha"    => 0,
            "tongTienDichVu" => 0,
            "tongDoanhThu"   => 0
        );
        foreach ($chiTietTheoNhaTro as $row) {
            $tongQuan['tongTienCoc']    += $row['tienCoc'];
            $tongQuan['tongTienNha']    += $row['tienNha'];
            $tongQuan['tongTienDichVu'] += $row['tienDichVu'];
            $tongQuan['tongDoanhThu']   += $row['tongDoanhThu'];
        }
        $tongQuan['tongTienCoc']    = round($tongQuan['tongTienCoc'], 2);
        $tongQuan['tongTienNha']    = round($tongQuan['tongTienNha'], 2);
        $tongQuan['tongTienDichVu'] = round($tongQuan['tongTienDichVu'], 2);
        $tongQuan['tongDoanhThu']   = round($tongQuan['tongDoanhThu'], 2);

        // So sánh với tháng trước (chỉ khi không lọc riêng 1 nhà hoặc logic so sánh vẫn áp dụng được)
        $thangTruoc = $thang - 1;
        $namTruoc   = $nam;
        if ($thangTruoc < 1) { $thangTruoc = 12; $namTruoc = $nam - 1; }

        $ctThangTruoc = tinhDoanhThuTheoNhaTro($db, $thangTruoc, $namTruoc, $houseId);
        $doanhThuTruoc = 0;
        foreach ($ctThangTruoc as $row) { $doanhThuTruoc += $row['tongDoanhThu']; }

        $doanhThuNay = $tongQuan['tongDoanhThu'];
        $chenhLech   = $doanhThuNay - $doanhThuTruoc;
        $tiLe = $doanhThuTruoc > 0 ? round(($chenhLech / $doanhThuTruoc) * 100, 2) : null;
        $tiLeText = $tiLe === null ? "N/A" : (($tiLe >= 0 ? "+" : "") . $tiLe . "%");

        $soSanhThangTruoc = array(
            "thangSoSanh"  => $thangTruoc,
            "namSoSanh"    => $namTruoc,
            "doanhThuKyTruoc" => round($doanhThuTruoc, 2),
            "chenhLech"    => round($chenhLech, 2),
            "tiLeTangGiam" => $tiLeText
        );

        echo json_encode(array(
            "status" => "success",
            "cheDo"  => "thang",
            "data"   => array(
                "thang" => $thang,
                "nam"   => $nam,
                "tongQuan" => $tongQuan,
                "chiTietTheoNhaTro" => $chiTietTheoNhaTro,
                "soSanhThangTruoc"  => $soSanhThangTruoc
            )
        ), JSON_UNESCAPED_UNICODE);

    } else {
        // Chế độ năm
        $chiTietTheoThang = array();
        $tongTienCoc = 0; $tongTienNha = 0; $tongTienDichVu = 0;

        for ($m = 1; $m <= 12; $m++) {
            $ctThang = tinhDoanhThuTheoNhaTro($db, $m, $nam, $houseId);
            $sumCoc = 0; $sumNha = 0; $sumDV = 0;
            foreach ($ctThang as $row) {
                $sumCoc += $row['tienCoc'];
                $sumNha += $row['tienNha'];
                $sumDV  += $row['tienDichVu'];
            }
            $tongThang = $sumCoc + $sumNha + $sumDV;
            $tongTienCoc    += $sumCoc;
            $tongTienNha    += $sumNha;
            $tongTienDichVu += $sumDV;

            $chiTietTheoThang[] = array(
                "thang"       => $m,
                "tienCoc"     => round($sumCoc, 2),
                "tienNha"     => round($sumNha, 2),
                "tienDichVu"  => round($sumDV, 2),
                "tongDoanhThu"=> round($tongThang, 2)
            );
        }

        $tongDoanhThuNam = $tongTienCoc + $tongTienNha + $tongTienDichVu;
        $tongQuan = array(
            "tongTienCoc"    => round($tongTienCoc, 2),
            "tongTienNha"    => round($tongTienNha, 2),
            "tongTienDichVu" => round($tongTienDichVu, 2),
            "tongDoanhThu"   => round($tongDoanhThuNam, 2),
            "trungBinhThang" => round($tongDoanhThuNam / 12, 2)
        );

        echo json_encode(array(
            "status" => "success",
            "cheDo"  => "nam",
            "data"   => array(
                "nam"   => $nam,
                "tongQuan" => $tongQuan,
                "chiTietTheoThang"   => $chiTietTheoThang,
                "chiTietTheoNhaTro"  => $chiTietTheoNhaTro
            )
        ), JSON_UNESCAPED_UNICODE);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(array("status" => "error", "message" => "Lỗi cơ sở dữ liệu: " . $e->getMessage()));
}

function tinhDoanhThuTheoNhaTro(PDO $db, int $thang, int $nam, int $houseId): array {
    $qNhaTro = "SELECT Id, TenNha FROM NhaTro WHERE IsDeleted = 0";
    if ($houseId > 0) $qNhaTro .= " AND Id = :houseId";
    $qNhaTro .= " ORDER BY TenNha ASC";

    $stmtNhaTro = $db->prepare($qNhaTro);
    if ($houseId > 0) $stmtNhaTro->bindParam(':houseId', $houseId, PDO::PARAM_INT);
    $stmtNhaTro->execute();
    $nhaTroList = $stmtNhaTro->fetchAll(PDO::FETCH_ASSOC);

    // Tiền cọc
    $tienCocMap = [];
    $qTienCoc = "SELECT pt.NhaTroId, SUM(hdt.TienCoc) AS TongTienCoc
                 FROM hopdongthue hdt
                 INNER JOIN phongtro pt ON hdt.PhongTroId = pt.Id
                 WHERE hdt.IsDeleted = 0 AND YEAR(hdt.NgayBatDau) = :nam";
    if ($thang > 0) $qTienCoc .= " AND MONTH(hdt.NgayBatDau) = :thang";
    if ($houseId > 0) $qTienCoc .= " AND pt.NhaTroId = :houseId";
    $qTienCoc .= " GROUP BY pt.NhaTroId";

    $stmtTienCoc = $db->prepare($qTienCoc);
    $stmtTienCoc->bindParam(':nam', $nam, PDO::PARAM_INT);
    if ($thang > 0) $stmtTienCoc->bindParam(':thang', $thang, PDO::PARAM_INT);
    if ($houseId > 0) $stmtTienCoc->bindParam(':houseId', $houseId, PDO::PARAM_INT);
    $stmtTienCoc->execute();
    while ($row = $stmtTienCoc->fetch(PDO::FETCH_ASSOC)) {
        $tienCocMap[(int)$row['NhaTroId']] = (float)$row['TongTienCoc'];
    }

    // Doanh thu (Tiền nhà + Dịch vụ)
    $doanhThuMap = [];
    $qDoanhThu = "SELECT pt.NhaTroId, SUM(tt.SoTienThanhToan) as DaThu,
                         SUM(CASE WHEN cthd.DichVuId IS NULL THEN cthd.ThanhTien ELSE 0 END) as TienNha,
                         SUM(CASE WHEN cthd.DichVuId IS NOT NULL THEN cthd.ThanhTien ELSE 0 END) as TienDV,
                         hd.TongTienHoaDon
                  FROM hoadon hd
                  JOIN phongtro pt ON hd.PhongTroId = pt.Id
                  JOIN chitiethoadon cthd ON cthd.HoaDonId = hd.Id
                  JOIN thanhtoan tt ON tt.HoaDonId = hd.Id
                  WHERE hd.DeletedDate IS NULL AND YEAR(tt.NgayThanhToan) = :nam";
    if ($thang > 0) $qDoanhThu .= " AND MONTH(tt.NgayThanhToan) = :thang";
    if ($houseId > 0) $qDoanhThu .= " AND pt.NhaTroId = :houseId";
    $qDoanhThu .= " GROUP BY pt.NhaTroId, hd.Id";

    $stmtDoanhThu = $db->prepare($qDoanhThu);
    $stmtDoanhThu->bindParam(':nam', $nam, PDO::PARAM_INT);
    if ($thang > 0) $stmtDoanhThu->bindParam(':thang', $thang, PDO::PARAM_INT);
    if ($houseId > 0) $stmtDoanhThu->bindParam(':houseId', $houseId, PDO::PARAM_INT);
    $stmtDoanhThu->execute();

    while ($row = $stmtDoanhThu->fetch(PDO::FETCH_ASSOC)) {
        $id = (int)$row['NhaTroId'];
        if (!isset($doanhThuMap[$id])) $doanhThuMap[$id] = ['nha' => 0, 'dv' => 0];
        // Tính tỷ lệ đã thu so với tổng hóa đơn để phân bổ tiền nhà/dịch vụ
        $ratio = $row['TongTienHoaDon'] > 0 ? ($row['DaThu'] / $row['TongTienHoaDon']) : 0;
        $doanhThuMap[$id]['nha'] += $row['TienNha'] * $ratio;
        $doanhThuMap[$id]['dv']  += $row['TienDV'] * $ratio;
    }

    $result = [];
    foreach ($nhaTroList as $nt) {
        $id = (int)$nt['Id'];
        $coc = $tienCocMap[$id] ?? 0;
        $nha = $doanhThuMap[$id]['nha'] ?? 0;
        $dv  = $doanhThuMap[$id]['dv'] ?? 0;
        $result[] = [
            "nhaTroId" => $id,
            "tenNha"   => $nt['TenNha'],
            "tienCoc"  => round($coc, 2),
            "tienNha"  => round($nha, 2),
            "tienDichVu" => round($dv, 2),
            "tongDoanhThu" => round($coc + $nha + $dv, 2)
        ];
    }
    return $result;
}
?>
