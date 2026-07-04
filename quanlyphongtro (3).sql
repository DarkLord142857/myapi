-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jul 02, 2026 at 06:16 PM
-- Server version: 8.4.3
-- PHP Version: 8.3.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `quanlyphongtro`
--
CREATE DATABASE IF NOT EXISTS `quanlyphongtro` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
USE `quanlyphongtro`;

-- --------------------------------------------------------

--
-- Table structure for table `chitiethoadon`
--

CREATE TABLE `chitiethoadon` (
  `Id` int NOT NULL,
  `HoaDonId` int DEFAULT NULL,
  `PhongTroId` int DEFAULT NULL,
  `DichVuId` int DEFAULT NULL,
  `YeuCauId` int DEFAULT NULL,
  `TenMuc` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `SoLuong` int DEFAULT NULL,
  `DonGia` decimal(18,2) DEFAULT NULL,
  `ThanhTien` decimal(18,2) DEFAULT NULL,
  `CreatedDate` datetime DEFAULT CURRENT_TIMESTAMP,
  `IsDeleted` int DEFAULT '0',
  `NguoiXoaId` bigint DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `chitiethoadon`
--

INSERT INTO `chitiethoadon` (`Id`, `HoaDonId`, `PhongTroId`, `DichVuId`, `YeuCauId`, `TenMuc`, `SoLuong`, `DonGia`, `ThanhTien`, `CreatedDate`, `IsDeleted`, `NguoiXoaId`) VALUES
(1, 1, 1, NULL, NULL, 'Tiền thuê phòng tháng 06/2026', 1, 3500000.00, 3500000.00, '2026-06-01 19:30:00', 0, NULL),
(2, 1, 1, 1, NULL, 'Điện tiêu thụ (Chỉ số: 1100 -> 1250)', 150, 3500.00, 525000.00, '2026-06-01 19:30:00', 0, NULL),
(3, 1, 1, 2, NULL, 'Nước sinh hoạt (Chỉ số: 300 -> 304)', 4, 25000.00, 100000.00, '2026-06-01 19:30:00', 0, NULL),
(4, 1, 1, 3, NULL, 'Phí dịch vụ Internet trọn gói', 1, 100000.00, 100000.00, '2026-06-01 19:30:00', 0, NULL),
(5, 2, 3, NULL, NULL, 'Tiền thuê phòng P.201 tháng 06', 1, 3600000.00, 3600000.00, '2026-07-01 21:43:01', 0, NULL),
(6, 2, 3, 1, NULL, 'Điện sinh hoạt', 100, 3500.00, 350000.00, '2026-07-01 21:43:01', 0, NULL),
(7, 2, 3, 2, NULL, 'Nước sạch sinh hoạt', 5, 25000.00, 125000.00, '2026-07-01 21:43:01', 0, NULL),
(8, 2, 3, 3, NULL, 'Internet cáp quang trọn gói', 1, 100000.00, 100000.00, '2026-07-01 21:43:01', 0, NULL),
(9, 3, 4, NULL, NULL, 'Tiền thuê phòng P.202 tháng 06', 1, 2900000.00, 2900000.00, '2026-07-01 21:43:01', 0, NULL),
(10, 3, 4, 1, NULL, 'Điện sinh hoạt', 80, 3500.00, 280000.00, '2026-07-01 21:43:01', 0, NULL),
(11, 3, 4, 2, NULL, 'Nước sạch sinh hoạt', 3, 25000.00, 75000.00, '2026-07-01 21:43:01', 0, NULL),
(12, 3, 4, 3, NULL, 'Internet cáp quang trọn gói', 1, 100000.00, 100000.00, '2026-07-01 21:43:01', 0, NULL),
(13, 4, 5, NULL, NULL, 'Tiền thuê phòng P.301 tháng 06', 1, 3700000.00, 3700000.00, '2026-07-01 21:43:01', 0, NULL),
(14, 4, 5, 1, NULL, 'Điện năng tiêu thụ mạnh (Chạy 2 điều hòa)', 200, 3500.00, 700000.00, '2026-07-01 21:43:01', 0, NULL),
(15, 4, 5, 2, NULL, 'Nước tiêu thụ gia đình', 8, 25000.00, 200000.00, '2026-07-01 21:43:01', 0, NULL),
(16, 4, 5, 3, NULL, 'Internet cáp quang trọn gói', 1, 100000.00, 100000.00, '2026-07-01 21:43:01', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `dichvu`
--

CREATE TABLE `dichvu` (
  `Id` int NOT NULL,
  `TenDichVu` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ChiPhi` decimal(18,2) DEFAULT NULL,
  `MoTa` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `IsDeleted` int DEFAULT '0',
  `NguoiXoaId` bigint DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `dichvu`
--

INSERT INTO `dichvu` (`Id`, `TenDichVu`, `ChiPhi`, `MoTa`, `IsDeleted`, `NguoiXoaId`) VALUES
(1, 'Tiền Điện', 3500.00, 'Tính theo chỉ số công tơ kinh doanh hộ gia đình (đơn giá/kWh)', 0, NULL),
(2, 'Tiền Nước', 25000.00, 'Tính theo khối tiêu thụ thực tế của phòng (đơn giá/m3)', 0, NULL),
(3, 'Internet Cáp Quang', 100000.00, 'Trọn gói băng thông mạng tốc độ cao theo từng phòng / tháng', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `giatrithuoctinhhoadon`
--

CREATE TABLE `giatrithuoctinhhoadon` (
  `Id` int NOT NULL,
  `ChiTietHoaDonId` int DEFAULT NULL,
  `ThuocTinhHoaDonId` int DEFAULT NULL,
  `GiaTriSo` decimal(18,2) DEFAULT NULL,
  `GhiChu` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `IsDeleted` int DEFAULT '0',
  `NguoiXoaId` bigint DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `giatrithuoctinhhoadon`
--

INSERT INTO `giatrithuoctinhhoadon` (`Id`, `ChiTietHoaDonId`, `ThuocTinhHoaDonId`, `GiaTriSo`, `GhiChu`, `IsDeleted`, `NguoiXoaId`) VALUES
(1, 2, 1, 1100.00, 'Chỉ số công tơ điện ngày 01/05', 0, NULL),
(2, 2, 2, 1250.00, 'Chỉ số công tơ điện chốt ngày 31/05', 0, NULL),
(3, 3, 1, 300.00, 'Chỉ số đồng hồ nước ngày 01/05', 0, NULL),
(4, 3, 2, 304.00, 'Chỉ số đồng hồ nước chốt ngày 31/05', 0, NULL),
(5, 6, 1, 0.00, 'Số điện bàn giao nhận phòng', 0, NULL),
(6, 6, 2, 100.00, 'Chốt điện cuối tháng 5', 0, NULL),
(7, 7, 1, 10.00, 'Số nước bàn giao', 0, NULL),
(8, 7, 2, 15.00, 'Chốt nước cuối tháng 5', 0, NULL),
(9, 10, 1, 450.00, 'Số cũ đầu tháng', 0, NULL),
(10, 10, 2, 530.00, 'Số mới chốt', 0, NULL),
(11, 11, 1, 85.00, 'Số cũ đầu tháng', 0, NULL),
(12, 11, 2, 88.00, 'Số mới chốt', 0, NULL),
(13, 14, 1, 2100.00, 'Số cũ đầu tháng', 0, NULL),
(14, 14, 2, 2300.00, 'Số mới chốt', 0, NULL),
(15, 15, 1, 412.00, 'Số cũ đầu tháng', 0, NULL),
(16, 15, 2, 420.00, 'Số mới chốt', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `hoadon`
--

CREATE TABLE `hoadon` (
  `Id` int NOT NULL,
  `NguoiLapId` bigint DEFAULT NULL,
  `PhongTroId` int DEFAULT NULL,
  `KyHoaDon` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `TongTienHoaDon` decimal(18,2) DEFAULT NULL,
  `CongNo` decimal(18,2) DEFAULT NULL,
  `TrangThaiThanhToan` varchar(28) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `CreatedDate` datetime DEFAULT CURRENT_TIMESTAMP,
  `DeletedDate` datetime DEFAULT NULL,
  `NguoiXoaId` bigint DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `hoadon`
--

INSERT INTO `hoadon` (`Id`, `NguoiLapId`, `PhongTroId`, `KyHoaDon`, `TongTienHoaDon`, `CongNo`, `TrangThaiThanhToan`, `CreatedDate`, `DeletedDate`, `NguoiXoaId`) VALUES
(1, 2, 1, '06/2026', 4225000.00, 1225000.00, 'ThanhToanMotPhan', '2026-06-01 19:30:00', NULL, NULL),
(2, 2, 3, '06/2026', 4175000.00, 0.00, 'DaThanhToan', '2026-06-01 19:35:00', NULL, NULL),
(3, 2, 4, '06/2026', 3355000.00, 3550000.00, 'ThanhToanMotPhan', '2026-06-01 19:40:00', NULL, NULL),
(4, 2, 5, '06/2026', 4700000.00, -300000.00, 'DaThanhToan', '2026-06-01 19:45:00', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `hopdongthue`
--

CREATE TABLE `hopdongthue` (
  `Id` int NOT NULL,
  `PhongTroId` int DEFAULT NULL,
  `KhachHangId` bigint DEFAULT NULL,
  `NgayBatDau` date DEFAULT NULL,
  `NgayKetThuc` date DEFAULT NULL,
  `TienCoc` decimal(15,2) DEFAULT '0.00',
  `IsActive` int DEFAULT '1',
  `IsDeleted` int DEFAULT '0',
  `NguoiXoaId` bigint DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `hopdongthue`
--

INSERT INTO `hopdongthue` (`Id`, `PhongTroId`, `KhachHangId`, `NgayBatDau`, `NgayKetThuc`, `TienCoc`, `IsActive`, `IsDeleted`, `NguoiXoaId`) VALUES
(1, 1, 3, '2026-05-01', '2027-04-30', 500000.00, 1, 0, NULL),
(2, 3, 4, '2026-05-01', '2027-04-30', 300000.00, 1, 0, NULL),
(3, 4, 5, '2026-05-05', '2026-11-04', 200000.00, 1, 0, NULL),
(4, 5, 6, '2026-05-12', '2027-05-11', 0.00, 1, 0, NULL),
(5, 6, 7, '2026-05-20', '2027-05-19', 100000.00, 1, 0, NULL),
(6, 7, 8, '2026-06-01', '2027-05-31', 1000000.00, 1, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `nhatro`
--

CREATE TABLE `nhatro` (
  `Id` int NOT NULL,
  `TenNha` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `DiaChi` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `GiayToPhapLy` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `MaQL` bigint DEFAULT NULL,
  `IsApproved` int DEFAULT '0',
  `NgayDuyet` datetime DEFAULT NULL,
  `NgaySua` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `IsDeleted` int DEFAULT '0',
  `NguoiXoaId` bigint DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `nhatro`
--

INSERT INTO `nhatro` (`Id`, `TenNha`, `DiaChi`, `GiayToPhapLy`, `MaQL`, `IsApproved`, `NgayDuyet`, `NgaySua`, `IsDeleted`, `NguoiXoaId`) VALUES
(1, 'Chung Cư Mini Lan Anh', 'Số 123 Ngõ 45, Đường Láng, Phường Láng Thượng, Quận Đống Đa, Hà Nội', 'Giấy phép xây dựng số 456/GPXD-ĐĐ và Sổ đỏ số BD12345', 2, 1, '2026-01-12 10:00:00', NULL, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` bigint NOT NULL,
  `NoiDung` text NOT NULL,
  `TrangThai` int DEFAULT '0',
  `CreatedDate` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int NOT NULL,
  `identifier` varchar(100) NOT NULL,
  `otp_code` varchar(6) NOT NULL,
  `expires_at` datetime NOT NULL,
  `is_verified` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `phongtro`
--

CREATE TABLE `phongtro` (
  `Id` int NOT NULL,
  `NhaTroId` int DEFAULT NULL,
  `SoPhong` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `SoNguoiToiDa` int DEFAULT NULL,
  `SoLuongXeToiDa` int DEFAULT NULL,
  `GiaPhong` decimal(18,2) DEFAULT NULL,
  `TrangThai` int DEFAULT '0',
  `IsDeleted` int DEFAULT '0',
  `NgaySua` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `NguoiXoaId` bigint DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `phongtro`
--

INSERT INTO `phongtro` (`Id`, `NhaTroId`, `SoPhong`, `SoNguoiToiDa`, `SoLuongXeToiDa`, `GiaPhong`, `TrangThai`, `IsDeleted`, `NgaySua`, `NguoiXoaId`) VALUES
(1, 1, 'P.101', 3, 2, 3500000.00, 1, 0, '2026-01-15 11:00:00', NULL),
(2, 1, 'P.102', 2, 1, 2800000.00, 0, 0, '2026-01-15 11:00:00', NULL),
(3, 1, 'P.201', 3, 2, 3600000.00, 1, 0, '2026-01-15 11:00:00', NULL),
(4, 1, 'P.202', 2, 1, 2900000.00, 1, 0, '2026-01-15 11:00:00', NULL),
(5, 1, 'P.301', 3, 2, 3700000.00, 1, 0, '2026-01-15 11:00:00', NULL),
(6, 1, 'P.302', 2, 2, 3000000.00, 1, 0, '2026-01-15 11:00:00', NULL),
(7, 1, 'P.401', 4, 3, 4500000.00, 1, 0, '2026-01-15 11:00:00', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `phongtrohinhanh`
--

CREATE TABLE `phongtrohinhanh` (
  `Id` int NOT NULL,
  `PhongTroId` int DEFAULT NULL,
  `HinhAnhUrl` varbinary(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `phongtrohinhanh`
--

INSERT INTO `phongtrohinhanh` (`Id`, `PhongTroId`, `HinhAnhUrl`) VALUES
(1, 1, 0x68747470733a2f2f696d616765732e756e73706c6173682e636f6d2f70686f746f2d313532323737313733393834342d3661396636643566313461663f6175746f3d666f726d6174266669743d63726f7026773d3132303026713d3830),
(2, 1, 0x68747470733a2f2f696d616765732e756e73706c6173682e636f6d2f70686f746f2d313539383932383530363331312d6335356465643931613230633f6175746f3d666f726d6174266669743d63726f7026773d3132303026713d3830),
(3, 2, 0x68747470733a2f2f696d616765732e756e73706c6173682e636f6d2f70686f746f2d313530353639313933383839352d3137353864376665623531313f6175746f3d666f726d6174266669743d63726f7026773d3132303026713d3830),
(4, 3, 0x68747470733a2f2f696d616765732e756e73706c6173682e636f6d2f70686f746f2d313536303434383230342d6530326631316333643065323f6175746f3d666f726d6174266669743d63726f7026773d3132303026713d3830);

-- --------------------------------------------------------

--
-- Table structure for table `phongtro_thuoctinh`
--

CREATE TABLE `phongtro_thuoctinh` (
  `Id` int NOT NULL,
  `PhongTroId` int DEFAULT NULL,
  `ThuocTinhPhongId` int DEFAULT NULL,
  `GiaTriThucTe` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `IsDeleted` int DEFAULT '0',
  `NguoiXoaId` bigint DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `phongtro_thuoctinh`
--

INSERT INTO `phongtro_thuoctinh` (`Id`, `PhongTroId`, `ThuocTinhPhongId`, `GiaTriThucTe`, `IsDeleted`, `NguoiXoaId`) VALUES
(1, 1, 1, 'Hướng Đông Nam (mát mẻ, đón nắng sáng)', 0, NULL),
(2, 1, 2, '1', 0, NULL),
(3, 1, 3, '32', 0, NULL),
(4, 2, 1, 'Không có ban công (Cửa sổ thông gió hướng Bắc)', 0, NULL),
(5, 2, 2, '1', 0, NULL),
(6, 2, 3, '25', 0, NULL),
(7, 3, 1, 'Hướng Tây (Hơi nóng chiều, có rèm chắn nắng)', 0, NULL),
(8, 3, 2, '1', 0, NULL),
(9, 3, 3, '35', 0, NULL),
(10, 4, 1, 'Hướng Chính Nam (Rất mát mẻ)', 0, NULL),
(11, 4, 2, '1', 0, NULL),
(12, 4, 3, '26', 0, NULL),
(13, 5, 1, 'Hướng Đông Nam thoáng đãng', 0, NULL),
(14, 5, 2, '2', 0, NULL),
(15, 5, 3, '38', 0, NULL),
(16, 7, 1, 'View toàn thành phố, hướng Đông', 0, NULL),
(17, 7, 2, '2', 0, NULL),
(18, 7, 3, '50', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `thanhtoan`
--

CREATE TABLE `thanhtoan` (
  `Id` int NOT NULL,
  `HoaDonId` int DEFAULT NULL,
  `NgayThanhToan` datetime DEFAULT CURRENT_TIMESTAMP,
  `SoTienThanhToan` decimal(18,2) DEFAULT NULL,
  `PhuongThucThanhToan` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `MaGiaoDich` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `NguoiNhanId` bigint DEFAULT NULL,
  `GhiChu` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `IsDeleted` int DEFAULT '0',
  `NguoiXoaId` bigint DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `thanhtoan`
--

INSERT INTO `thanhtoan` (`Id`, `HoaDonId`, `NgayThanhToan`, `SoTienThanhToan`, `PhuongThucThanhToan`, `MaGiaoDich`, `NguoiNhanId`, `GhiChu`, `IsDeleted`, `NguoiXoaId`) VALUES
(1, 1, '2026-06-05 10:15:22', 3000000.00, 'ChuyenKhoanNH', 'MBBANK_FT26156998213', 2, 'Khách Trần Hoài Nam chuyển khoản đóng trước 3 triệu tiền phòng. Hẹn ngày 15 đóng nốt tiền dịch vụ.', 0, NULL),
(2, 2, '2026-06-03 08:30:00', 4175000.00, 'ViMomo', 'MOMO_603889123', 2, 'Chị Nguyễn Thị Hoa quét mã thanh toán trọn gói tiền phòng và dịch vụ.', 0, NULL),
(3, 3, '2026-06-04 11:20:15', 3000000.00, 'ChuyenKhoanNH', 'VCB_FT99238123', 2, 'Anh Phạm Anh Dũng trả trước 3 triệu, hẹn ngày 20 đóng nốt phần nợ.', 0, NULL),
(4, 4, '2026-06-02 15:45:10', 5000000.00, 'ChuyenKhoanNH', 'BIDV_FT88123094', 2, 'Chị Vũ Thùy Linh chuyển khoản 5 triệu. Hóa đơn gốc 4.7tr, dư 300k chuyển vào công nợ âm để cấn trừ tháng sau.', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `thongbao`
--

CREATE TABLE `thongbao` (
  `Id` int NOT NULL,
  `NguoiGuiId` bigint NOT NULL,
  `NhaTroId` int NOT NULL DEFAULT '1',
  `TieuDe` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `NoiDung` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `TrangThai` int DEFAULT NULL,
  `CreatedDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `thongbao`
--

INSERT INTO `thongbao` (`Id`, `NguoiGuiId`, `NhaTroId`, `TieuDe`, `NoiDung`, `TrangThai`, `CreatedDate`) VALUES
(1, 2, 1, 'Thông báo thử nghiệm 01: Nhắc nhở dọn vệ sinh chung', 'Yêu cầu tất cả các phòng thực hiện gom rác đúng nơi quy định trước 18h00 hàng ngày và không để túi rác ở hành lang lối đi chung.', 1, '2026-06-29 14:43:01'),
(2, 2, 1, 'Thông báo thử nghiệm 02: Bảo trì hệ thống điện tòa nhà', 'Hệ thống điện tổng của khu trọ sẽ được bảo trì định kỳ vào sáng Thứ Bảy tuần này, thời gian mất điện dự kiến từ 08h00 đến 10h00. Mong quý khách thông cảm.', 1, '2026-06-30 14:43:01'),
(3, 2, 1, 'Thông báo thử nghiệm 03: Đã có hóa đơn tiền phòng tháng này', 'Hệ thống đã cập nhật hóa đơn tiền phòng, tiền điện và nước của tháng này. Vui lòng kiểm tra lại số liệu và nhấn nút \"Đóng tiền ngay\" trên ứng dụng trước ngày mùng 5 nhé.', 0, '2026-07-01 14:43:01');

-- --------------------------------------------------------

--
-- Table structure for table `thongbao_user`
--

CREATE TABLE `thongbao_user` (
  `Id` int NOT NULL,
  `ThongBaoId` int DEFAULT NULL,
  `UserId` bigint DEFAULT NULL,
  `TrangThai` int DEFAULT '1',
  `NgayXem` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `thuoctinhhoadon`
--

CREATE TABLE `thuoctinhhoadon` (
  `Id` int NOT NULL,
  `TenThuocTinh` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `KieuDuLieu` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `DonVi` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `IsDeleted` int DEFAULT '0',
  `NguoiXoaId` bigint DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `thuoctinhhoadon`
--

INSERT INTO `thuoctinhhoadon` (`Id`, `TenThuocTinh`, `KieuDuLieu`, `DonVi`, `IsDeleted`, `NguoiXoaId`) VALUES
(1, 'Chỉ Số Đầu Kỳ (Cũ)', 'KieuSo', 'Số điện/nước', 0, NULL),
(2, 'Chỉ Số Cuối Kỳ (Mới)', 'KieuSo', 'Số điện/nước', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `thuoctinhphong`
--

CREATE TABLE `thuoctinhphong` (
  `Id` int NOT NULL,
  `TenThuocTinh` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `KieuDuLieu` int DEFAULT NULL,
  `DonVi` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `thuoctinhphong`
--

INSERT INTO `thuoctinhphong` (`Id`, `TenThuocTinh`, `KieuDuLieu`, `DonVi`) VALUES
(1, 'Hướng Ban Công', -1, NULL),
(2, 'Số Lượng Điều Hòa', 0, 'Cái'),
(3, 'Diện Tích Sử Dụng', 1, 'm2');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint NOT NULL,
  `Username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `FullName` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `IdentityCard` varchar(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `PhoneNumber` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Role` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `IsApproved` int DEFAULT '0',
  `IsDeleted` int DEFAULT '0',
  `CreatedDate` datetime DEFAULT CURRENT_TIMESTAMP,
  `UpdatedDate` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `NguoiXoaId` bigint DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `Username`, `Password`, `FullName`, `IdentityCard`, `PhoneNumber`, `Email`, `Role`, `IsApproved`, `IsDeleted`, `CreatedDate`, `UpdatedDate`, `NguoiXoaId`) VALUES
(1, 'admin_system', '827ccb0eea8a706c4c34a16891f84e7b', 'Nguyễn Toàn Thắng', '030093001234', '0912345678', 'thang.nguyen@hethong.com', 'Admin', 1, 0, '2026-01-01 08:00:00', '2026-07-01 21:43:01', NULL),
(2, 'chutro_phuc', '827ccb0eea8a706c4c34a16891f84e7b', 'Đỗ Hồng Phúc', '030093005678', '0899756805', 'dohongphuc2k3@gmail.com', 'ChuTro', 1, 0, '2026-01-10 09:30:00', '2026-07-01 21:43:01', NULL),
(3, 'khach_nam', '827ccb0eea8a706c4c34a16891f84e7b', 'Trần Hoài Nam', '030093009999', '0901234567', 'nam.tran98@gmail.com', 'KhachHang', 1, 0, '2026-04-25 14:00:00', '2026-07-01 21:43:01', NULL),
(4, 'khach_hoa', '827ccb0eea8a706c4c34a16891f84e7b', 'Nguyễn Thị Hoa', '030096001122', '0922334455', 'hoa.nguyen@gmail.com', 'KhachHang', 1, 0, '2026-05-01 09:00:00', '2026-07-01 21:43:01', NULL),
(5, 'khach_dung', '827ccb0eea8a706c4c34a16891f84e7b', 'Phạm Anh Dũng', '030095002233', '0933445566', 'dung.pham@gmail.com', 'KhachHang', 1, 0, '2026-05-02 10:15:00', '2026-07-01 21:43:01', NULL),
(6, 'khach_linh', '827ccb0eea8a706c4c34a16891f84e7b', 'Vũ Thùy Linh', '030097004455', '0944556677', 'linh.vu@gmail.com', 'KhachHang', 1, 0, '2026-05-10 14:20:00', '2026-07-01 21:43:01', NULL),
(7, 'khach_tuong', '827ccb0eea8a706c4c34a16891f84e7b', 'Lê Văn Tường', '030092005566', '0955667788', 'tuong.le@gmail.com', 'KhachHang', 1, 0, '2026-05-15 11:00:00', '2026-07-01 21:43:01', NULL),
(8, 'khach_vy', '827ccb0eea8a706c4c34a16891f84e7b', 'Đỗ Thúy Vy', '030098007788', '0966778899', 'vy.do@gmail.com', 'KhachHang', 0, 0, '2026-05-20 16:45:00', '2026-07-01 21:43:01', NULL),
(9, 'nhanvien_quan', '827ccb0eea8a706c4c34a16891f84e7b', 'Hoàng Minh Quân', '030091008899', '0977889900', 'quan.hoang@phongtro.com', 'Admin', 1, 0, '2026-01-20 08:30:00', '2026-07-01 21:43:01', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users_log`
--

CREATE TABLE `users_log` (
  `id` bigint NOT NULL,
  `UserId` bigint DEFAULT NULL,
  `AdminId` bigint DEFAULT NULL,
  `HanhDong` varchar(50) NOT NULL,
  `GhiChu` text,
  `ThoiGian` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `yeucaudichvu`
--

CREATE TABLE `yeucaudichvu` (
  `Id` int NOT NULL,
  `KhachHangId` bigint DEFAULT NULL,
  `DichVuId` int DEFAULT NULL,
  `TieuDe` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `MoTa` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `TrangThai` int DEFAULT NULL,
  `CreatedDate` datetime DEFAULT CURRENT_TIMESTAMP,
  `DeletedDate` datetime DEFAULT NULL,
  `NguoiXoaId` bigint DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `yeucaudichvu`
--

INSERT INTO `yeucaudichvu` (`Id`, `KhachHangId`, `DichVuId`, `TieuDe`, `MoTa`, `TrangThai`, `CreatedDate`, `DeletedDate`, `NguoiXoaId`) VALUES
(1, 3, 1, 'Hỏng vòi nước', 'Vòi nước bồn rửa mặt bị rỉ nước, cần kiểm tra và thay ron.', 0, '2026-07-01 21:43:01', NULL, NULL),
(2, 4, 1, 'Bóng đèn hành lang bị cháy', 'Đèn trước phòng 202 không sáng, cần thay bóng mới.', 0, '2026-07-01 21:43:01', NULL, NULL),
(3, 5, 1, 'Vệ sinh máy lạnh', 'Yêu cầu vệ sinh định kỳ máy lạnh phòng 302.', 1, '2026-06-20 09:30:00', NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `chitiethoadon`
--
ALTER TABLE `chitiethoadon`
  ADD PRIMARY KEY (`Id`),
  ADD KEY `HoaDonId` (`HoaDonId`),
  ADD KEY `PhongTroId` (`PhongTroId`),
  ADD KEY `DichVuId` (`DichVuId`),
  ADD KEY `YeuCauId` (`YeuCauId`),
  ADD KEY `NguoiXoaId` (`NguoiXoaId`);

--
-- Indexes for table `dichvu`
--
ALTER TABLE `dichvu`
  ADD PRIMARY KEY (`Id`),
  ADD KEY `NguoiXoaId` (`NguoiXoaId`);

--
-- Indexes for table `giatrithuoctinhhoadon`
--
ALTER TABLE `giatrithuoctinhhoadon`
  ADD PRIMARY KEY (`Id`),
  ADD KEY `ChiTietHoaDonId` (`ChiTietHoaDonId`),
  ADD KEY `ThuocTinhHoaDonId` (`ThuocTinhHoaDonId`),
  ADD KEY `NguoiXoaId` (`NguoiXoaId`);

--
-- Indexes for table `hoadon`
--
ALTER TABLE `hoadon`
  ADD PRIMARY KEY (`Id`),
  ADD KEY `NguoiLapId` (`NguoiLapId`),
  ADD KEY `PhongTroId` (`PhongTroId`),
  ADD KEY `NguoiXoaId` (`NguoiXoaId`);

--
-- Indexes for table `hopdongthue`
--
ALTER TABLE `hopdongthue`
  ADD PRIMARY KEY (`Id`),
  ADD KEY `PhongTroId` (`PhongTroId`),
  ADD KEY `KhachHangId` (`KhachHangId`),
  ADD KEY `NguoiXoaId` (`NguoiXoaId`);

--
-- Indexes for table `nhatro`
--
ALTER TABLE `nhatro`
  ADD PRIMARY KEY (`Id`),
  ADD KEY `MaQL` (`MaQL`),
  ADD KEY `NguoiXoaId` (`NguoiXoaId`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `phongtro`
--
ALTER TABLE `phongtro`
  ADD PRIMARY KEY (`Id`),
  ADD KEY `NhaTroId` (`NhaTroId`),
  ADD KEY `NguoiXoaId` (`NguoiXoaId`);

--
-- Indexes for table `phongtrohinhanh`
--
ALTER TABLE `phongtrohinhanh`
  ADD PRIMARY KEY (`Id`),
  ADD KEY `PhongTroId` (`PhongTroId`);

--
-- Indexes for table `phongtro_thuoctinh`
--
ALTER TABLE `phongtro_thuoctinh`
  ADD PRIMARY KEY (`Id`),
  ADD KEY `PhongTroId` (`PhongTroId`),
  ADD KEY `ThuocTinhPhongId` (`ThuocTinhPhongId`),
  ADD KEY `NguoiXoaId` (`NguoiXoaId`);

--
-- Indexes for table `thanhtoan`
--
ALTER TABLE `thanhtoan`
  ADD PRIMARY KEY (`Id`),
  ADD KEY `HoaDonId` (`HoaDonId`),
  ADD KEY `NguoiNhanId` (`NguoiNhanId`),
  ADD KEY `NguoiXoaId` (`NguoiXoaId`);

--
-- Indexes for table `thongbao`
--
ALTER TABLE `thongbao`
  ADD PRIMARY KEY (`Id`),
  ADD KEY `FK_ThongBao_NguoiGui` (`NguoiGuiId`);

--
-- Indexes for table `thongbao_user`
--
ALTER TABLE `thongbao_user`
  ADD PRIMARY KEY (`Id`),
  ADD UNIQUE KEY `unique_user_noti` (`ThongBaoId`,`UserId`),
  ADD KEY `UserId` (`UserId`);

--
-- Indexes for table `thuoctinhhoadon`
--
ALTER TABLE `thuoctinhhoadon`
  ADD PRIMARY KEY (`Id`),
  ADD KEY `NguoiXoaId` (`NguoiXoaId`);

--
-- Indexes for table `thuoctinhphong`
--
ALTER TABLE `thuoctinhphong`
  ADD PRIMARY KEY (`Id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `Username` (`Username`),
  ADD KEY `NguoiXoaId` (`NguoiXoaId`);

--
-- Indexes for table `users_log`
--
ALTER TABLE `users_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `UserId` (`UserId`),
  ADD KEY `AdminId` (`AdminId`);

--
-- Indexes for table `yeucaudichvu`
--
ALTER TABLE `yeucaudichvu`
  ADD PRIMARY KEY (`Id`),
  ADD KEY `KhachHangId` (`KhachHangId`),
  ADD KEY `DichVuId` (`DichVuId`),
  ADD KEY `NguoiXoaId` (`NguoiXoaId`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `chitiethoadon`
--
ALTER TABLE `chitiethoadon`
  MODIFY `Id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `dichvu`
--
ALTER TABLE `dichvu`
  MODIFY `Id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `giatrithuoctinhhoadon`
--
ALTER TABLE `giatrithuoctinhhoadon`
  MODIFY `Id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `hoadon`
--
ALTER TABLE `hoadon`
  MODIFY `Id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `hopdongthue`
--
ALTER TABLE `hopdongthue`
  MODIFY `Id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `nhatro`
--
ALTER TABLE `nhatro`
  MODIFY `Id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `phongtro`
--
ALTER TABLE `phongtro`
  MODIFY `Id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `phongtrohinhanh`
--
ALTER TABLE `phongtrohinhanh`
  MODIFY `Id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `phongtro_thuoctinh`
--
ALTER TABLE `phongtro_thuoctinh`
  MODIFY `Id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `thanhtoan`
--
ALTER TABLE `thanhtoan`
  MODIFY `Id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `thongbao`
--
ALTER TABLE `thongbao`
  MODIFY `Id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `thongbao_user`
--
ALTER TABLE `thongbao_user`
  MODIFY `Id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `thuoctinhhoadon`
--
ALTER TABLE `thuoctinhhoadon`
  MODIFY `Id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `thuoctinhphong`
--
ALTER TABLE `thuoctinhphong`
  MODIFY `Id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `users_log`
--
ALTER TABLE `users_log`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `yeucaudichvu`
--
ALTER TABLE `yeucaudichvu`
  MODIFY `Id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `chitiethoadon`
--
ALTER TABLE `chitiethoadon`
  ADD CONSTRAINT `chitiethoadon_ibfk_1` FOREIGN KEY (`HoaDonId`) REFERENCES `hoadon` (`Id`),
  ADD CONSTRAINT `chitiethoadon_ibfk_2` FOREIGN KEY (`PhongTroId`) REFERENCES `phongtro` (`Id`),
  ADD CONSTRAINT `chitiethoadon_ibfk_3` FOREIGN KEY (`DichVuId`) REFERENCES `dichvu` (`Id`),
  ADD CONSTRAINT `chitiethoadon_ibfk_4` FOREIGN KEY (`YeuCauId`) REFERENCES `yeucaudichvu` (`Id`),
  ADD CONSTRAINT `chitiethoadon_ibfk_5` FOREIGN KEY (`NguoiXoaId`) REFERENCES `users` (`id`);

--
-- Constraints for table `dichvu`
--
ALTER TABLE `dichvu`
  ADD CONSTRAINT `dichvu_ibfk_1` FOREIGN KEY (`NguoiXoaId`) REFERENCES `users` (`id`);

--
-- Constraints for table `giatrithuoctinhhoadon`
--
ALTER TABLE `giatrithuoctinhhoadon`
  ADD CONSTRAINT `giatrithuoctinhhoadon_ibfk_1` FOREIGN KEY (`ChiTietHoaDonId`) REFERENCES `chitiethoadon` (`Id`),
  ADD CONSTRAINT `giatrithuoctinhhoadon_ibfk_2` FOREIGN KEY (`ThuocTinhHoaDonId`) REFERENCES `thuoctinhhoadon` (`Id`),
  ADD CONSTRAINT `giatrithuoctinhhoadon_ibfk_3` FOREIGN KEY (`NguoiXoaId`) REFERENCES `users` (`id`);

--
-- Constraints for table `hoadon`
--
ALTER TABLE `hoadon`
  ADD CONSTRAINT `hoadon_ibfk_1` FOREIGN KEY (`NguoiLapId`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `hoadon_ibfk_2` FOREIGN KEY (`PhongTroId`) REFERENCES `phongtro` (`Id`),
  ADD CONSTRAINT `hoadon_ibfk_3` FOREIGN KEY (`NguoiXoaId`) REFERENCES `users` (`id`);

--
-- Constraints for table `hopdongthue`
--
ALTER TABLE `hopdongthue`
  ADD CONSTRAINT `hopdongthue_ibfk_1` FOREIGN KEY (`PhongTroId`) REFERENCES `phongtro` (`Id`),
  ADD CONSTRAINT `hopdongthue_ibfk_2` FOREIGN KEY (`KhachHangId`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `hopdongthue_ibfk_3` FOREIGN KEY (`NguoiXoaId`) REFERENCES `users` (`id`);

--
-- Constraints for table `nhatro`
--
ALTER TABLE `nhatro`
  ADD CONSTRAINT `nhatro_ibfk_1` FOREIGN KEY (`MaQL`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `nhatro_ibfk_2` FOREIGN KEY (`NguoiXoaId`) REFERENCES `users` (`id`);

--
-- Constraints for table `phongtro`
--
ALTER TABLE `phongtro`
  ADD CONSTRAINT `phongtro_ibfk_1` FOREIGN KEY (`NhaTroId`) REFERENCES `nhatro` (`Id`),
  ADD CONSTRAINT `phongtro_ibfk_2` FOREIGN KEY (`NguoiXoaId`) REFERENCES `users` (`id`);

--
-- Constraints for table `phongtrohinhanh`
--
ALTER TABLE `phongtrohinhanh`
  ADD CONSTRAINT `phongtrohinhanh_ibfk_1` FOREIGN KEY (`PhongTroId`) REFERENCES `phongtro` (`Id`);

--
-- Constraints for table `phongtro_thuoctinh`
--
ALTER TABLE `phongtro_thuoctinh`
  ADD CONSTRAINT `phongtro_thuoctinh_ibfk_1` FOREIGN KEY (`PhongTroId`) REFERENCES `phongtro` (`Id`),
  ADD CONSTRAINT `phongtro_thuoctinh_ibfk_2` FOREIGN KEY (`ThuocTinhPhongId`) REFERENCES `thuoctinhphong` (`Id`),
  ADD CONSTRAINT `phongtro_thuoctinh_ibfk_3` FOREIGN KEY (`NguoiXoaId`) REFERENCES `users` (`id`);

--
-- Constraints for table `thanhtoan`
--
ALTER TABLE `thanhtoan`
  ADD CONSTRAINT `thanhtoan_ibfk_1` FOREIGN KEY (`HoaDonId`) REFERENCES `hoadon` (`Id`),
  ADD CONSTRAINT `thanhtoan_ibfk_2` FOREIGN KEY (`NguoiNhanId`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `thanhtoan_ibfk_3` FOREIGN KEY (`NguoiXoaId`) REFERENCES `users` (`id`);

--
-- Constraints for table `thongbao`
--
ALTER TABLE `thongbao`
  ADD CONSTRAINT `FK_ThongBao_NguoiGui` FOREIGN KEY (`NguoiGuiId`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `thongbao_user`
--
ALTER TABLE `thongbao_user`
  ADD CONSTRAINT `thongbao_user_ibfk_1` FOREIGN KEY (`ThongBaoId`) REFERENCES `thongbao` (`Id`),
  ADD CONSTRAINT `thongbao_user_ibfk_2` FOREIGN KEY (`UserId`) REFERENCES `users` (`id`);

--
-- Constraints for table `thuoctinhhoadon`
--
ALTER TABLE `thuoctinhhoadon`
  ADD CONSTRAINT `thuoctinhhoadon_ibfk_1` FOREIGN KEY (`NguoiXoaId`) REFERENCES `users` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`NguoiXoaId`) REFERENCES `users` (`id`);

--
-- Constraints for table `users_log`
--
ALTER TABLE `users_log`
  ADD CONSTRAINT `users_log_ibfk_1` FOREIGN KEY (`UserId`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `users_log_ibfk_2` FOREIGN KEY (`AdminId`) REFERENCES `users` (`id`);

--
-- Constraints for table `yeucaudichvu`
--
ALTER TABLE `yeucaudichvu`
  ADD CONSTRAINT `yeucaudichvu_ibfk_1` FOREIGN KEY (`KhachHangId`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `yeucaudichvu_ibfk_2` FOREIGN KEY (`DichVuId`) REFERENCES `dichvu` (`Id`),
  ADD CONSTRAINT `yeucaudichvu_ibfk_3` FOREIGN KEY (`NguoiXoaId`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
