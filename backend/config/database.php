<?php

/*
|--------------------------------------------------------------------------
| Konfigurasi database (satu-satunya tempat).
|--------------------------------------------------------------------------
| Nilai di bawah = default Laragon (lokal). Untuk HOSTING jangan ubah file
| ini; buat config/database.local.php (tidak ikut ke git) berisi:
|     <?php
|     $host = "localhost";
|     $user = "abcd_admin";
|     $password = "...";
|     $database = "abcd_health";
| Di cPanel, nama database dan user biasanya berawalan username hosting.
| Nama database di hosting (Linux) peka huruf besar-kecil.
*/
$host = "localhost";
$user = "root";
$password = "";
$database = "Health_and_Sympathy";

if (is_file(__DIR__ . '/database.local.php')) {
    require __DIR__ . '/database.local.php';
}

// PHP 8.1+ (umumnya dipakai hosting) secara default melempar exception untuk
// setiap error mysqli. Disamakan dengan PHP 8.0 (Laragon): error dicek lewat
// nilai return. File API yang butuh exception (get_soal, submit_jawaban)
// menyalakannya sendiri setelah file ini.
mysqli_report(MYSQLI_REPORT_OFF);

$conn = @mysqli_connect($host, $user, $password, $database);

if (!$conn) {
    $detail = mysqli_connect_error();

    // Detail error hanya ke log server, tidak ditampilkan ke pengguna.
    error_log("Koneksi database gagal: " . $detail);

    if (PHP_SAPI === 'cli') {
        exit("Koneksi database gagal: " . $detail . "\n");
    }

    // Endpoint API (dipanggil game) harus selalu menjawab JSON.
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? '');

    if (strpos($script, '/api/') !== false) {
        http_response_code(500);
        header("Content-Type: application/json; charset=UTF-8");
        echo json_encode(["success" => false, "message" => "Koneksi database gagal."]);
        exit;
    }

    die("Koneksi database gagal. Cek pengaturan di config/database.php.");
}

mysqli_set_charset($conn, "utf8mb4");
