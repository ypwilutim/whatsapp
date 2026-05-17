<?php
class Database {
    private $host = 'localhost';
    private $db_name = 'ypwh2917_whatsapp';
    private $username = 'ypwh2917_akbar';
    private $password = 'akbar22011999';
    private $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            error_log("Database connection established successfully");
        } catch(PDOException $e) {
            // Return null and let the caller handle the error
            error_log("Database connection failed: " . $e->getMessage());
            return null;
        }
        return $this->conn;
    }
}
