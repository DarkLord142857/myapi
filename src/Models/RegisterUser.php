<?php
class RegisterUser {
    private $conn;
    private $table_name = "users";

    // Các thuộc tính khớp với DB của bạn
    public $FullName;
    public $Username; // Nếu bạn có trường này trong DB, nếu không có thì có thể bỏ qua
    public $Email;
    public $PhoneNumber;
    public $Password;
    public $Role;
    public $IsApproved;

   public function __construct($db) {
        $this->conn = $db;
    }

    // Sửa hàm kiểm tra: Quét xem trùng Username HOẶC Email HOẶC Số điện thoại
    public function isAccountExists() {
        $query = "SELECT id FROM " . $this->table_name . " 
                  WHERE Username = :username 
                     OR Email = :email 
                     OR PhoneNumber = :phone 
                  LIMIT 0,1";
                  
        $stmt = $this->conn->prepare($query);

        $this->Username = htmlspecialchars(strip_tags($this->Username));
        $this->Email = htmlspecialchars(strip_tags($this->Email));
        $this->PhoneNumber = htmlspecialchars(strip_tags($this->PhoneNumber));

        $stmt->bindParam(':username', $this->Username);
        $stmt->bindParam(':email', $this->Email);
        $stmt->bindParam(':phone', $this->PhoneNumber);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    // Sửa hàm tạo: Thêm Username vào câu lệnh INSERT INTO
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                  SET Username = :username, 
                      FullName = :fullname, 
                      Email = :email, 
                      PhoneNumber = :phone, 
                      Password = :password, 
                      Role = :role, 
                      IsApproved = :is_approved,
                      CreatedDate = NOW()";

        $stmt = $this->conn->prepare($query);

        $this->Username = htmlspecialchars(strip_tags($this->Username));
        $this->FullName = htmlspecialchars(strip_tags($this->FullName));
        $this->Email = htmlspecialchars(strip_tags($this->Email));
        $this->PhoneNumber = htmlspecialchars(strip_tags($this->PhoneNumber));
        $this->Password = htmlspecialchars(strip_tags($this->Password));
        $this->Role = htmlspecialchars(strip_tags($this->Role));
        $this->IsApproved = (int)$this->IsApproved;

        $stmt->bindParam(':username', $this->Username);
        $stmt->bindParam(':fullname', $this->FullName);
        $stmt->bindParam(':email', $this->Email);
        $stmt->bindParam(':phone', $this->PhoneNumber);
        $stmt->bindParam(':password', $this->Password);
        $stmt->bindParam(':role', $this->Role);
        $stmt->bindParam(':is_approved', $this->IsApproved);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }
}
?>