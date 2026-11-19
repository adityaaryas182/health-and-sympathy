<?php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

require_once '../config/database.php';

/** @var mysqli $conn */

function sendJson($data)
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJson([
        "success" => false,
        "message" => "Method harus POST."
    ]);
}

/* Ambil input dari POST (form) atau JSON */
$data = [];

foreach ($_POST as $key => $value) {
    $data[trim($key)] = is_string($value) ? trim($value) : $value;
}

$json = json_decode(file_get_contents("php://input"), true);

if (is_array($json)) {
    foreach ($json as $key => $value) {
        $data[trim($key)] = $value;
    }
}

$id_siswa = isset($data['id_siswa']) ? intval($data['id_siswa']) : 0;
$level_game = isset($data['level_game']) ? intval($data['level_game']) : 0;
$status = isset($data['status']) ? trim((string) $data['status']) : 'Selesai';
$skor = isset($data['skor']) ? intval($data['skor']) : 0;
$poin_game = (isset($data['poin_game']) && $data['poin_game'] !== '') ? intval($data['poin_game']) : null;
$jumlah_soal = isset($data['jumlah_soal']) ? intval($data['jumlah_soal']) : 0;
$jumlah_benar = isset($data['jumlah_benar']) ? intval($data['jumlah_benar']) : 0;
$jumlah_salah = isset($data['jumlah_salah']) ? intval($data['jumlah_salah']) : 0;

// Kode unik per kiriman dari game: kiriman ulang (koneksi putus) tidak dicatat dua kali.
$kode_kirim = isset($data['kode_kirim']) ? trim((string) $data['kode_kirim']) : '';
if ($kode_kirim !== '' && !preg_match('/^[A-Za-z0-9-]{8,36}$/', $kode_kirim)) {
    sendJson(["success" => false, "message" => "kode_kirim tidak valid."]);
}
$kode_kirim = ($kode_kirim === '') ? null : $kode_kirim;

/* VALIDASI */
if ($id_siswa <= 0) {
    sendJson(["success" => false, "message" => "id_siswa tidak valid."]);
}

if (!in_array($status, ['Selesai', 'Game Over'], true)) {
    sendJson(["success" => false, "message" => "status harus 'Selesai' atau 'Game Over'."]);
}

if ($jumlah_soal <= 0) {
    sendJson(["success" => false, "message" => "jumlah_soal tidak valid."]);
}

if ($jumlah_benar < 0 || $jumlah_salah < 0) {
    sendJson(["success" => false, "message" => "Jumlah benar atau salah tidak valid."]);
}

if (($jumlah_benar + $jumlah_salah) != $jumlah_soal) {
    sendJson(["success" => false, "message" => "Jumlah benar dan salah tidak sesuai dengan jumlah soal."]);
}

if ($skor < 0 || $skor > 100) {
    sendJson(["success" => false, "message" => "Skor harus berada di antara 0 sampai 100."]);
}

if ($poin_game !== null && $poin_game < 0) {
    sendJson(["success" => false, "message" => "poin_game tidak valid."]);
}

/* CEK SISWA */
$stmt = mysqli_prepare($conn, "SELECT id_siswa FROM siswa WHERE id_siswa = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $id_siswa);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);

if (mysqli_stmt_num_rows($stmt) == 0) {
    sendJson(["success" => false, "message" => "Siswa tidak ditemukan."]);
}
mysqli_stmt_close($stmt);

/* CEK LEVEL: harus level yang punya skill (level 0/kosong = data lama, disimpan NULL) */
if ($level_game > 0) {
    $stmt = mysqli_prepare($conn, "SELECT 1 FROM skill WHERE level_game = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $level_game);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);

    if (mysqli_stmt_num_rows($stmt) == 0) {
        sendJson(["success" => false, "message" => "Level " . $level_game . " tidak ada."]);
    }
    mysqli_stmt_close($stmt);
}
$level_simpan = $level_game > 0 ? $level_game : null;

/* KIRIMAN ULANG: kembalikan data yang sudah tersimpan */
if ($kode_kirim !== null) {
    $stmt = mysqli_prepare($conn, "SELECT id_nilai FROM nilai_quiz WHERE kode_kirim = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "s", $kode_kirim);
    mysqli_stmt_execute($stmt);
    $lama = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if ($lama) {
        sendJson([
            "success" => true,
            "message" => "Nilai ini sudah tersimpan sebelumnya.",
            "duplikat" => true,
            "data" => ["id_nilai" => (int) $lama['id_nilai']]
        ]);
    }
}

/* SIMPAN NILAI */
$stmt = mysqli_prepare(
    $conn,
    "INSERT INTO nilai_quiz
        (id_siswa, level_game, status, skor, poin_game, jumlah_soal, jumlah_benar, jumlah_salah, kode_kirim)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
);

if (!$stmt) {
    sendJson(["success" => false, "message" => "Query error: " . mysqli_error($conn)]);
}

mysqli_stmt_bind_param(
    $stmt,
    "iisiiiiis",
    $id_siswa,
    $level_simpan,
    $status,
    $skor,
    $poin_game,
    $jumlah_soal,
    $jumlah_benar,
    $jumlah_salah,
    $kode_kirim
);

if (mysqli_stmt_execute($stmt)) {
    sendJson([
        "success" => true,
        "message" => "Nilai berhasil disimpan.",
        "data" => [
            "id_nilai" => mysqli_insert_id($conn),
            "id_siswa" => $id_siswa,
            "level_game" => $level_simpan,
            "status" => $status,
            "skor" => $skor,
            "poin_game" => $poin_game,
            "jumlah_soal" => $jumlah_soal,
            "jumlah_benar" => $jumlah_benar,
            "jumlah_salah" => $jumlah_salah
        ]
    ]);
}

// 1062 = kode_kirim kembar karena dua kiriman sama masuk bersamaan; yang lain sudah tersimpan.
if (mysqli_errno($conn) == 1062) {
    sendJson(["success" => true, "message" => "Nilai ini sudah tersimpan sebelumnya.", "duplikat" => true]);
}

sendJson([
    "success" => false,
    "message" => "Gagal menyimpan nilai."
]);
