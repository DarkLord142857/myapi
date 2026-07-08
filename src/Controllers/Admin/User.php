<?php
class User {
    private $conn;
    private $table_name = "Users";

    public $id;
    public $username;
    public $password;
    public $fullName;
    public $role;
    public $isApproved;
    public $isDeleted;
    public $createdDate;
    public $updatedDate;
    public $isApprovedDate;
    public $isDeletedDate;
    public $nguoiSuaName;
    public $nguoiDuyetName;
    public $nguoiXoaName;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Hàm kiểm tra thông tin Đăng nhập
    public function login() {
        // Truy vấn kiểm tra Username, mật khẩu mã hóa MD5, tài khoản chưa bị xóa
        $query = "SELECT id, FullName, Role, IsApproved FROM " . $this->table_name . " 
                  WHERE Username = :username AND Password = :password AND IsDeleted = 0 LIMIT 0,1";

        $stmt = $this->conn->prepare($query);

        // Làm sạch dữ liệu đầu vào
        $this->username = htmlspecialchars(strip_tags($this->username));
        $this->password = htmlspecialchars(strip_tags($this->password));

        // Liên kết tham số (Mã hóa MD5 mật khẩu nhận được)
        $stmt->bindParam(":username", $this->username);
        $hashed_password = md5($this->password); 
        $stmt->bindParam(":password", $hashed_password);

        $stmt->execute();
        return $stmt;
    }
    // Hàm 1: Lấy danh sách tất cả người dùng (Lọc ra những người chưa bị xóa)
    public function readAll() {
        $query = "SELECT u.id, u.Username, u.FullName, u.IdentityCard, u.PhoneNumber, u.Email, u.Role, u.IsApproved, u.IsDeleted,
                         u.CreatedDate, u.UpdatedDate, u.IsApprovedDate, u.IsDeletedDate,
                         a1.FullName as NguoiSuaName, 
                         a2.FullName as NguoiDuyetName, 
                         a3.FullName as NguoiXoaName
                  FROM " . $this->table_name . " u
                  LEFT JOIN Users a1 ON u.NguoiSuaId = a1.id
                  LEFT JOIN Users a2 ON u.NguoiDuyetId = a2.id
                  LEFT JOIN Users a3 ON u.NguoiXoaId = a3.id
                  ORDER BY 
                    u.IsDeleted ASC,
                    u.IsApproved ASC,
                    u.CreatedDate ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    // Hàm 2: Cập nhật trạng thái Phê duyệt (Duyệt thành viên)
    public function approve($adminId, $adminName, $targetName) {
        $query = "UPDATE " . $this->table_name . " 
                  SET IsApproved = 1, NguoiDuyetId = :adminId, IsApprovedDate = NOW() 
                  WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":adminId", $adminId);
        $stmt->bindParam(":id", $this->id);
        
        if($stmt->execute()) {
            // Ghi vào bảng Users_Log
            $logQuery = "INSERT INTO Users_Log (UserId, AdminId, HanhDong, GhiChu) 
                         VALUES (:userId, :adminId, 'Duyet', 'Đã duyệt thành viên')";
            $logStmt = $this->conn->prepare($logQuery);
            $logStmt->bindParam(":userId", $this->id);
            $logStmt->bindParam(":adminId", $adminId);
            $logStmt->execute();

            // Ghi vào bảng Notifications
            $notifQuery = "INSERT INTO Notifications (NoiDung, TrangThai) 
                           VALUES (:noiDung, 0)";
            $noiDungNotif = "Khách hàng " . $targetName . " đã được duyệt bởi " . $adminName . " ngày " . date('d/m/Y H:i');
            $notifStmt = $this->conn->prepare($notifQuery);
            $notifStmt->bindParam(":noiDung", $noiDungNotif);
            $notifStmt->execute();

            return true;
        }
        return false;
    }

    // Hàm 3: Khóa tài khoản (Xóa mềm - Soft Delete)
    public function softDelete($adminId, $adminName, $targetName) {
        $query = "UPDATE " . $this->table_name . " 
                  SET IsDeleted = 1, NguoiXoaId = :adminId, IsDeletedDate = NOW() 
                  WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":adminId", $adminId);
        $stmt->bindParam(":id", $this->id);
        
        if($stmt->execute()) {
            // Ghi Log
            $logQuery = "INSERT INTO Users_Log (UserId, AdminId, HanhDong, GhiChu) 
                         VALUES (:userId, :adminId, 'Khoa', 'Đã khóa tài khoản')";
            $logStmt = $this->conn->prepare($logQuery);
            $logStmt->bindParam(":userId", $this->id);
            $logStmt->bindParam(":adminId", $adminId);
            $logStmt->execute();

            // Ghi Thông báo
            $notifQuery = "INSERT INTO Notifications (NoiDung, TrangThai) VALUES (:noiDung, 0)";
            $noiDungNotif = "Khách hàng " . $targetName . " đã bị xóa bởi " . $adminName . " ngày " . date('d/m/Y H:i');
            $notifStmt = $this->conn->prepare($notifQuery);
            $notifStmt->bindParam(":noiDung", $noiDungNotif);
            $notifStmt->execute();

            return true;
        }
        return false;
    }
    // Hàm 4: Mở khóa tài khoản (Đặt lại IsDeleted = 0)
    public function unlock($adminId, $adminName, $targetName) {
        // 1. Cập nhật trạng thái IsDeleted về 0
        $query = "UPDATE " . $this->table_name . " 
                  SET IsDeleted = 0, NguoiXoaId = NULL, IsDeletedDate = NULL 
                  WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);
        
        if($stmt->execute()) {
            // 2. GHI LOG VÀO BẢNG USERS_LOG
            $logQuery = "INSERT INTO Users_Log (UserId, AdminId, HanhDong, GhiChu) 
                         VALUES (:userId, :adminId, 'MoKhoa', 'Đã mở khóa tài khoản')";
            $logStmt = $this->conn->prepare($logQuery);
            $logStmt->bindParam(":userId", $this->id);
            $logStmt->bindParam(":adminId", $adminId);
            $logStmt->execute();

            // 3. GHI THÔNG BÁO VÀO BẢNG NOTIFICATIONS (Đã sửa đổi dùng đúng $this->conn)
            $notifQuery = "INSERT INTO Notifications (NoiDung, TrangThai) VALUES (:noiDung, 0)";
            $noiDungNotif = "Tài khoản " . $targetName . " đã được mở khóa bởi " . $adminName . " ngày " . date('d/m/Y H:i');
            
            $notifStmt = $this->conn->prepare($notifQuery); // Sửa lỗi tại dòng này
            $notifStmt->bindParam(":noiDung", $noiDungNotif);
            $notifStmt->execute();

            return true;
        }
        return false;
    }
}
?>