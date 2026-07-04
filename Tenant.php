<?php
class Tenant {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

// 1. LẤY DANH SÁCH KHÁCH THUÊ - ĐÃ TỐI ƯU TỐC ĐỘ 
    public function read() {
        $query = "SELECT 
                    u.id AS KhachHangId, 
                    u.Username, 
                    u.FullName, 
                    u.IdentityCard, 
                    u.PhoneNumber, 
                    u.Email, 
                    u.IsApproved,
                    p.Id AS PhongTroId, 
                    p.SoPhong, 
                    p.GiaPhong,
                    n.TenNha,
                    hd.Id AS HopDongId, 
                    hd.NgayBatDau, 
                    hd.NgayKetThuc, 
                    hd.TienCoc, 
                    hd.IsActive
                  FROM Users u
                  INNER JOIN (
                      SELECT id FROM Users 
                      WHERE Role = 'KhachHang' AND IsDeleted = 0
                  ) active_u ON u.id = active_u.id
                  LEFT JOIN HopDongThue hd ON u.id = hd.KhachHangId AND hd.IsActive = 1 AND hd.IsDeleted = 0
                  LEFT JOIN PhongTro p ON hd.PhongTroId = p.Id AND p.IsDeleted = 0
                  LEFT JOIN NhaTro n ON p.NhaTroId = n.Id
                  ORDER BY u.id DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

   // 2. THÊM KHÁCH THUÊ VÀ TẠO HỢP ĐỒNG (Dùng Transaction)
    public function create($data) {
        try {
            $this->conn->beginTransaction();

            // Kiểm tra trạng thái phòng
            $checkRoom = $this->conn->prepare("SELECT TrangThai FROM PhongTro WHERE Id = ? AND IsDeleted = 0");
            $checkRoom->execute([$data['PhongTroId']]);
            $room = $checkRoom->fetch(PDO::FETCH_ASSOC);
            if (!$room) {
                throw new Exception("Phòng trọ không tồn tại.");
            }
            if ($room['TrangThai'] == 1) {
                throw new Exception("Phòng này đã có người thuê.");
            }

            $khachHangId = $data['KhachHangId'] ?? null;

            // NẾU CHƯA CÓ KHÁCH HÀNG ID -> TẠO TÀI KHOẢN MỚI
            if (empty($khachHangId)) {
                if (empty($data['Username']) || empty($data['Password'])) {
                    throw new Exception("Vui lòng nhập tài khoản và mật khẩu cho khách hàng mới.");
                }

                // Kiểm tra username trùng lặp
                $checkUser = $this->conn->prepare("SELECT id FROM Users WHERE Username = ?");
                $checkUser->execute([$data['Username']]);
                if ($checkUser->rowCount() > 0) {
                    throw new Exception("Tên tài khoản (Username) đã tồn tại.");
                }

                // Thêm User khách thuê mới
                $password_hash = md5($data['Password']);
                $insertUser = $this->conn->prepare("INSERT INTO Users (Username, Password, FullName, IdentityCard, PhoneNumber, Email, Role, IsApproved) VALUES (?, ?, ?, ?, ?, ?, 'KhachHang', 1)");
                $insertUser->execute([
                    $data['Username'], $password_hash, $data['FullName'],
                    $data['IdentityCard'] ?? null, $data['PhoneNumber'] ?? null, $data['Email'] ?? null
                ]);
                $khachHangId = $this->conn->lastInsertId();
            } else {
                // TRƯỜNG HỢP ĐÃ CÓ TÀI KHOẢN: Kiểm tra tài khoản có thực sự tồn tại không
                $checkUserExist = $this->conn->prepare("SELECT id FROM Users WHERE id = ? AND Role = 'KhachHang' AND IsDeleted = 0");
                $checkUserExist->execute([$khachHangId]);
                if ($checkUserExist->rowCount() == 0) {
                    throw new Exception("Tài khoản khách hàng không tồn tại hoặc đã bị xóa.");
                }
            }

            // Tạo Hợp đồng
            $tienCoc = $data['TienCoc'] ?? 0;
            $insertContract = $this->conn->prepare("INSERT INTO HopDongThue (PhongTroId, KhachHangId, NgayBatDau, NgayKetThuc, TienCoc, IsActive) VALUES (?, ?, ?, ?, ?, 1)");
            $insertContract->execute([$data['PhongTroId'], $khachHangId, $data['NgayBatDau'], $data['NgayKetThuc'], $tienCoc]);

            // Cập nhật trạng thái phòng -> Đã thuê (1)
            $updateRoom = $this->conn->prepare("UPDATE PhongTro SET TrangThai = 1 WHERE Id = ?");
            $updateRoom->execute([$data['PhongTroId']]);

            $this->conn->commit();
            return ["status" => true, "id" => $khachHangId];

        } catch (Exception $e) {
            $this->conn->rollBack();
            return ["status" => false, "message" => $e->getMessage()];
        }
    }

    // 3. CẬP NHẬT THÔNG TIN CÁ NHÂN KHÁCH THUÊ
    public function update($data) {
        $query = "UPDATE Users SET 
                    FullName = ?, IdentityCard = ?, PhoneNumber = ?, Email = ?, UpdatedDate = CURRENT_TIMESTAMP
                  WHERE id = ? AND Role = 'KhachHang' AND IsDeleted = 0";
                  
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            $data['FullName'], $data['IdentityCard'] ?? null, 
            $data['PhoneNumber'] ?? null, $data['Email'] ?? null, $data['id']
        ]);
        return $stmt->rowCount() > 0;
    }

   
    // 4. XÓA KHÁCH THUÊ / THANH LÝ HỢP ĐỒNG (Dùng Transaction)
    public function delete($khachHangId, $nguoiXoaId) {
        try {
            $this->conn->beginTransaction();

            // 1. Tìm phòng mà khách đang thuê từ hợp đồng còn hiệu lực
            $getContract = $this->conn->prepare("SELECT Id, PhongTroId FROM HopDongThue WHERE KhachHangId = ? AND IsActive = 1 AND IsDeleted = 0");
            $getContract->execute([$khachHangId]);
            $contract = $getContract->fetch(PDO::FETCH_ASSOC);

            if ($contract) {
                // 2. Chuyển hợp đồng về hết hiệu lực, gán IsDeleted = 1 và ghi nhận NguoiXoaId
                $updateContract = $this->conn->prepare("UPDATE HopDongThue SET IsActive = 0, IsDeleted = 1, NguoiXoaId = ? WHERE Id = ?");
                $updateContract->execute([$nguoiXoaId, $contract['Id']]);

                // 3. Trả phòng về trạng thái trống (0)
                $updateRoom = $this->conn->prepare("UPDATE PhongTro SET TrangThai = 0 WHERE Id = ?");
                $updateRoom->execute([$contract['PhongTroId']]);
            }

            // 4. Cập nhật IsDeleted = 1 VÀ ghi nhận chính xác NguoiXoaId cho tài khoản của khách thuê
            $deleteUser = $this->conn->prepare("UPDATE Users SET IsDeleted = 1, NguoiXoaId = ? WHERE id = ? AND Role = 'KhachHang'");
            
            // Thực thi gán: Tham số 1 ứng với NguoiXoaId, Tham số 2 ứng với id khách hàng
            $deleteUser->execute([$nguoiXoaId, $khachHangId]);

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            return false;
        }
    }
}
?>