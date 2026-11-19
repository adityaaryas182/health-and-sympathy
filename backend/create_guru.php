<?php
/*
| Buat akun guru baru, atau ganti password akun guru yang sudah ada.
| HANYA bisa dijalankan dari command line, tidak bisa dibuka lewat browser.
|
|   php create_guru.php <username> <password> ["Nama Guru"]
|
| Contoh ganti password admin:
|   php create_guru.php admin PasswordBaruYangKuat
*/

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if ($argc < 3) {
    fwrite(STDERR, "Pakai: php create_guru.php <username> <password> [\"Nama Guru\"]\n");
    exit(1);
}

$username = trim($argv[1]);
$password_plain = $argv[2];
$nama_guru = isset($argv[3]) ? trim($argv[3]) : "Admin Guru";

if ($username === '' || strlen($password_plain) < 8) {
    fwrite(STDERR, "Username wajib diisi dan password minimal 8 karakter.\n");
    exit(1);
}

require_once __DIR__ . '/config/database.php';

/** @var mysqli $conn */

$password_hash = password_hash($password_plain, PASSWORD_DEFAULT);

// Username sudah ada -> password diganti (nama tidak diubah).
$stmt = mysqli_prepare(
    $conn,
    "INSERT INTO guru (nama_guru, username, password) VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE password = VALUES(password)"
);
mysqli_stmt_bind_param($stmt, "sss", $nama_guru, $username, $password_hash);

if (!mysqli_stmt_execute($stmt)) {
    fwrite(STDERR, "Gagal menyimpan akun guru: " . mysqli_error($conn) . "\n");
    exit(1);
}

// affected_rows: 1 = akun baru, 2 = password akun lama diganti.
echo (mysqli_stmt_affected_rows($stmt) === 1)
    ? "Akun guru '$username' berhasil dibuat.\n"
    : "Password akun guru '$username' berhasil diganti.\n";
