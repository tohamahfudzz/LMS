
<?php
// jangan ubah username dan password database !!!!!!!!!
// Class Database berfungsi untuk mengatur dan menangani
// koneksi antara aplikasi LMS dengan database MySQL.
class Database
{
    // Properti untuk menyimpan objek koneksi PDO.
    // Nilai awal null karena koneksi belum dibuat.
    public ?PDO $conn = null;

    // Method untuk membuat dan mengembalikan koneksi database.
    public function getConnection(): ?PDO
    {
        // Alamat server MySQL.
        // Menggunakan localhost (127.0.0.1) karena database
        // dijalankan secara lokal melalui XAMPP.
        $host = '127.0.0.1';

        // Nama database yang digunakan oleh aplikasi LMS.
        $databaseName = 'lmsdb';

        // Username default MySQL pada XAMPP adalah root.
        $username = 'root';

        // Password default MySQL pada XAMPP biasanya kosong.
        $password = '';

        try {
            // Membuat koneksi ke database menggunakan PDO.
            // Charset utf8mb4 mendukung berbagai karakter Unicode.
            $this->conn = new PDO(
                "mysql:host={$host};dbname={$databaseName};charset=utf8mb4",
                $username,
                $password,
                [
                    // Mengaktifkan mode exception agar kesalahan
                    // koneksi atau query dapat ditangani melalui try-catch.
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

                    // Menonaktifkan prepared statement emulasi
                    // agar menggunakan prepared statement native MySQL.
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );

        } catch (PDOException $error) {

            // Menyimpan detail kesalahan ke error log server
            // tanpa menampilkan informasi sensitif kepada pengguna.
            error_log("Database connection failed: " . $error->getMessage());

            // Mengirim HTTP status 500 karena terjadi kesalahan server.
            http_response_code(500);

            // Mengirim respons JSON kepada frontend.
            echo json_encode([
                "success" => false,
                "message" => "Terjadi kesalahan koneksi database."
            ]);

            // Menghentikan proses apabila koneksi database gagal.
            exit;
        }

        // Mengembalikan objek koneksi PDO jika berhasil.
        return $this->conn;
    }
}
