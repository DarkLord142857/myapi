<?php
class Database {
    private $host = "localhost";
    private $db_name = "quanlyphongtro";
    private $username = "root"; 
    private $password = "";     // Nếu bạn dùng Laragon mặc định, mật khẩu là trống "" hoặc "root"
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            // SỬA TẠI ĐÂY: Phải có $this-> trước mỗi biến cấu hình
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name, 
                $this->username, 
                $this->password
            );
            $this->conn->exec("set names utf8");
        } catch(PDOException $exception) {
            echo "Lỗi kết nối Database: " . $exception->getMessage();
        }
        return $this->conn;
    }
}
?>