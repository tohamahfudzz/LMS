<?php
class Database
{
    private string $host = "127.0.0.1";
    private string $db_name = "lmsdb";
    private string $username = "root"; 
    private string $password = "AkbarGanteng1.";     
    public ?PDO $conn = null;

    public function getConnection(): ?PDO
    {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $error) {
            error_log("Koneksi Error: " . $error->getMessage());
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Terjadi kesalahan koneksi database."]);
            exit;
        }
        return $this->conn;
    }
}