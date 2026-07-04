<?php
class User {
    private $conn;
    private $table_name = "users";

    public $id;
    public $LoginIdentifier; // Biến đa năng nhận Username, Email hoặc SĐT từ Flutter
    public $Password;
    public $FullName;
    public $Role;
    public $IsApproved;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function findByUserIdentifier() {
        // Kiểm tra trùng khớp 1 trong 3 trường dữ liệu
        $query = "SELECT id, Username, Password, FullName, Role, IsApproved 
                  FROM " . $this->table_name . " 
                  WHERE Username = :identifier 
                     OR Email = :identifier 
                     OR PhoneNumber = :identifier 
                  LIMIT 0,1";

        $stmt = $this->conn->prepare($query);
        
        $this->LoginIdentifier = htmlspecialchars(strip_tags($this->LoginIdentifier));
        $stmt->bindParam(':identifier', $this->LoginIdentifier);
        $stmt->execute();

        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->id = $row['id'];
            $this->Password = $row['Password'];
            $this->FullName = $row['FullName'];
            $this->Role = $row['Role'];
            $this->IsApproved = $row['IsApproved'];
            return true;
        }
        return false;
    }
}
?>