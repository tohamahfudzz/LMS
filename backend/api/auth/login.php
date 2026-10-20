
<?php
/*
================================================================================
LOKASI FILE : backend/api/auth/login.php

GUNA FILE :
    1. Menerima request login dari frontend/assets/js/auth.js.
    2. Memvalidasi token Cloudflare Turnstile melalui Siteverify.
    3. Memeriksa kode, password, dan role pengguna di database.
    4. Membuat session PHP jika login berhasil.
    5. Mengembalikan hasil dalam format JSON.

CATATAN :
    Ganti TURNSTILE_SECRET_KEY dengan secret key yang sesuai.
    Jangan gunakan test key untuk website produksi.
================================================================================
*/

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "../../config/database.php";
require_once "../../functions/auth.php";

/*
|--------------------------------------------------------------------------
| Konfigurasi Cloudflare Turnstile
|--------------------------------------------------------------------------
*/

// Secret key pengujian Cloudflare untuk development lokal.
// Ganti dengan secret key produksi ketika website sudah online.
const TURNSTILE_SECRET_KEY =
    "1x0000000000000000000000000000000AA";

const TURNSTILE_VERIFY_URL =
    "https://challenges.cloudflare.com/turnstile/v0/siteverify";

/*
|--------------------------------------------------------------------------
| Fungsi untuk mengirim respons JSON
|--------------------------------------------------------------------------
*/

function sendJsonResponse($statusCode, array $response)
{
    http_response_code($statusCode);

    echo json_encode(
        $response,
        JSON_UNESCAPED_UNICODE
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| Fungsi memverifikasi token CAPTCHA ke Cloudflare
|--------------------------------------------------------------------------
*/

function verifyTurnstileToken($token)
{
    if (!function_exists("curl_init")) {
        error_log("CAPTCHA: Ekstensi PHP cURL tidak tersedia.");

        return [
            "success" => false,
            "error" => "service_unavailable"
        ];
    }

    $postData = [
        "secret" => TURNSTILE_SECRET_KEY,
        "response" => $token
    ];

    $curl = curl_init(TURNSTILE_VERIFY_URL);

    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($postData),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ]);

    $response = curl_exec($curl);
    $curlError = curl_error($curl);
    $httpStatus = curl_getinfo(
        $curl,
        CURLINFO_HTTP_CODE
    );

    curl_close($curl);

    // Jika Cloudflare tidak dapat dihubungi, tolak login.
    if ($response === false || $httpStatus !== 200) {
        error_log(
            "CAPTCHA Siteverify gagal: " .
            ($curlError ?: "HTTP " . $httpStatus)
        );

        return [
            "success" => false,
            "error" => "service_unavailable"
        ];
    }

    $result = json_decode($response, true);

    if (!is_array($result) || !isset($result["success"])) {
        error_log("CAPTCHA: Respons Siteverify tidak valid.");

        return [
            "success" => false,
            "error" => "service_unavailable"
        ];
    }

    return [
        "success" => $result["success"] === true,
        "error" => null
    ];
}

/*
|--------------------------------------------------------------------------
| 1. Pastikan request menggunakan metode POST
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Allow: POST");

    sendJsonResponse(405, [
        "success" => false,
        "message" => "Metode request tidak diizinkan."
    ]);
}

/*
|--------------------------------------------------------------------------
| 2. Baca data JSON dari auth.js
|--------------------------------------------------------------------------
*/

$rawInput = file_get_contents("php://input");
$data = json_decode($rawInput);

if (
    !is_object($data) ||
    !isset($data->kode, $data->password, $data->role) ||
    !is_string($data->kode) ||
    !is_string($data->password) ||
    !is_string($data->role)
) {
    sendJsonResponse(400, [
        "success" => false,
        "message" => "Data login tidak lengkap atau tidak valid."
    ]);
}

$kode = trim($data->kode);
$password = $data->password;
$role = strtolower(trim($data->role));

if (
    $kode === "" ||
    strlen($kode) < 2 ||
    strlen($password) < 4
) {
    sendJsonResponse(400, [
        "success" => false,
        "message" => "Kode atau password tidak memenuhi ketentuan."
    ]);
}

/*
|--------------------------------------------------------------------------
| 3. Pastikan token CAPTCHA tersedia
|--------------------------------------------------------------------------
*/

$captchaToken = isset($data->captchaToken)
    && is_string($data->captchaToken)
        ? trim($data->captchaToken)
        : "";

if ($captchaToken === "") {
    sendJsonResponse(400, [
        "success" => false,
        "message" => "Silakan selesaikan verifikasi CAPTCHA."
    ]);
}

/*
|--------------------------------------------------------------------------
| 4. Verifikasi CAPTCHA sebelum memeriksa akun
|--------------------------------------------------------------------------
*/

$captchaResult = verifyTurnstileToken($captchaToken);

if (!$captchaResult["success"]) {
    if ($captchaResult["error"] === "service_unavailable") {
        sendJsonResponse(503, [
            "success" => false,
            "message" =>
                "Layanan verifikasi sedang tidak tersedia. Silakan coba lagi."
        ]);
    }

    sendJsonResponse(403, [
        "success" => false,
        "message" =>
            "Verifikasi CAPTCHA gagal atau kedaluwarsa. Silakan coba lagi."
    ]);
}

/*
|--------------------------------------------------------------------------
| 5. Tentukan tabel berdasarkan role
|--------------------------------------------------------------------------
*/

$roleConfig = [
    "admin" => [
        "table" => "admin",
        "kode_column" => "kode_admin",
        "id_column" => "id_admin"
    ],
    "pengajar" => [
        "table" => "pengajar",
        "kode_column" => "kode_pengajar",
        "id_column" => "id_pengajar"
    ],
    "siswa" => [
        "table" => "siswa",
        "kode_column" => "kode_siswa",
        "id_column" => "id_siswa"
    ]
];

if (!isset($roleConfig[$role])) {
    sendJsonResponse(400, [
        "success" => false,
        "message" =>
            "Role tidak valid. Pilih admin, pengajar, atau siswa."
    ]);
}

$config = $roleConfig[$role];

/*
|--------------------------------------------------------------------------
| 6. Periksa akun dan password ke database
|--------------------------------------------------------------------------
*/

try {
    $database = new Database();
    $db = $database->getConnection();

    $query = "SELECT * FROM {$config['table']}
              WHERE {$config['kode_column']} = :kode
              LIMIT 1";

    $stmt = $db->prepare($query);

    $stmt->execute([
        ":kode" => $kode
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (
        !$user ||
        !isset($user["password"]) ||
        !password_verify($password, $user["password"])
    ) {
        sendJsonResponse(401, [
            "success" => false,
            "message" => "Kode atau password salah."
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | 7. Buat session backend
    |--------------------------------------------------------------------------
    */

    session_regenerate_id(true);

    $_SESSION["id"] = $user[$config["id_column"]];
    $_SESSION["role"] = $role;
    $_SESSION["nama"] = $user["nama"];

    /*
    |--------------------------------------------------------------------------
    | 8. Kirim respons login berhasil ke auth.js
    |--------------------------------------------------------------------------
    */

    sendJsonResponse(200, [
        "success" => true,
        "message" => "Login berhasil.",
        "session" => [
            "id" => $user[$config["id_column"]],
            "role" => $role,
            "nama" => $user["nama"]
        ]
    ]);

} catch (PDOException $e) {
    error_log("Login database error: " . $e->getMessage());

    sendJsonResponse(500, [
        "success" => false,
        "message" => "Terjadi kesalahan server. Silakan coba lagi."
    ]);
}
