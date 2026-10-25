<?php

class Database
{
    public ?PDO $conn = null;

    public function getConnection(): ?PDO
    {
        $host = getenv('LMS_DB_HOST') ?: '127.0.0.1';
        $databaseName = getenv('LMS_DB_NAME') ?: 'lmsdb';
        $username = getenv('LMS_DB_USER');
        $password = getenv('LMS_DB_PASS');

        if ($username === false || $password === false) {
            error_log('Database connection failed: LMS_DB_USER or LMS_DB_PASS is not configured.');
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Terjadi kesalahan koneksi database."
            ]);
            exit;
        }

        try {
            $this->conn = new PDO(
                "mysql:host={$host};dbname={$databaseName};charset=utf8mb4",
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $error) {
            error_log("Database connection failed: " . $error->getMessage());
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Terjadi kesalahan koneksi database."]);
            exit;
        }
        return $this->conn;
    }
}
