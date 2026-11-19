<?php

/*
|--------------------------------------------------------------------------
| Simpan hasil satu trap (dipanggil game setelah sesi trap selesai)
|--------------------------------------------------------------------------
| Game mengirim: id_siswa, level_game, nomor_trap, id_skill, jumlah_soal,
| jumlah_benar, p_awal, kode_kirim.
| P(L) akhir diambil dari bkt_siswa dan kategori dihitung di sini dengan
| batas yang sama seperti game & dashboard (config/bkt.php).
*/

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

require_once '../config/database.php';
require_once '../config/bkt.php';

/** @var mysqli $conn */

function sendJson($data)
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function tolak($pesan)
{
    sendJson(["success" => false, "message" => $pesan]);
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    tolak("Method harus POST.");
}

$input = json_decode(file_get_contents("php://input"), true);
if (!is_array($input)) {
    $input = $_POST;
}

$id_siswa = isset($input['id_siswa']) ? intval($input['id_siswa']) : 0;
$level_game = isset($input['level_game']) ? intval($input['level_game']) : 0;
$nomor_trap = isset($input['nomor_trap']) ? intval($input['nomor_trap']) : 0;
$id_skill = isset($input['id_skill']) ? intval($input['id_skill']) : 0;
$jumlah_soal = isset($input['jumlah_soal']) ? intval($input['jumlah_soal']) : 0;
$jumlah_benar = isset($input['jumlah_benar']) ? intval($input['jumlah_benar']) : -1;
$p_awal = isset($input['p_awal']) ? floatval($input['p_awal']) : -1;

$kode_kirim = isset($input['kode_kirim']) ? trim((string) $input['kode_kirim']) : '';
if ($kode_kirim !== '' && !preg_match('/^[A-Za-z0-9-]{8,36}$/', $kode_kirim)) {
    tolak("kode_kirim tidak valid.");
}
$kode_kirim = ($kode_kirim === '') ? null : $kode_kirim;

/* VALIDASI DASAR */
if ($id_siswa <= 0 || $level_game <= 0 || $id_skill <= 0) {
    tolak("Data tidak lengkap.");
}

if ($nomor_trap < 1 || $nomor_trap > TRAP_JUMLAH) {
    tolak("nomor_trap harus 1 sampai " . TRAP_JUMLAH . ".");
}

if ($jumlah_soal < 1 || $jumlah_soal > TRAP_MAX_SOAL) {
    tolak("jumlah_soal harus 1 sampai " . TRAP_MAX_SOAL . ".");
}

if ($jumlah_benar < 0 || $jumlah_benar > $jumlah_soal) {
    tolak("jumlah_benar tidak valid.");
}

if ($p_awal < 0 || $p_awal > 1) {
    tolak("p_awal harus 0 sampai 1.");
}

/* KIRIMAN ULANG */
if ($kode_kirim !== null) {
    $stmt = mysqli_prepare($conn, "SELECT id_hasil, kategori, p_akhir FROM hasil_trap WHERE kode_kirim = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "s", $kode_kirim);
    mysqli_stmt_execute($stmt);
    $lama = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if ($lama) {
        sendJson([
            "success" => true,
            "message" => "Hasil trap ini sudah tersimpan sebelumnya.",
            "duplikat" => true,
            "id_hasil" => (int) $lama['id_hasil'],
            "kategori" => $lama['kategori'],
            "p_akhir" => (float) $lama['p_akhir']
        ]);
    }
}

/* SKILL HARUS COCOK DENGAN LEVEL & NOMOR TRAP */
$stmt = mysqli_prepare($conn, "SELECT id_skill FROM skill WHERE level_game = ? ORDER BY id_skill ASC");
mysqli_stmt_bind_param($stmt, "i", $level_game);
mysqli_stmt_execute($stmt);
$hasilSkill = mysqli_stmt_get_result($stmt);

$skillLevel = [];
while ($row = mysqli_fetch_assoc($hasilSkill)) {
    $skillLevel[] = (int) $row['id_skill'];
}

if (!in_array($id_skill, $skillLevel, true)) {
    tolak("Skill " . $id_skill . " bukan milik level " . $level_game . ".");
}

$remedial = ($nomor_trap == TRAP_REMEDIAL) ? 1 : 0;

if (!$remedial) {
    $peta = petaTrap();
    $indeks = $peta[$nomor_trap][0];

    if (!isset($skillLevel[$indeks]) || $skillLevel[$indeks] !== $id_skill) {
        tolak("Skill " . $id_skill . " tidak sesuai dengan trap " . $nomor_trap . ".");
    }
}

/* P(L) AKHIR DARI SERVER */
$stmt = mysqli_prepare(
    $conn,
    "SELECT p_know FROM bkt_siswa WHERE id_siswa = ? AND id_skill = ? LIMIT 1"
);
mysqli_stmt_bind_param($stmt, "ii", $id_siswa, $id_skill);
mysqli_stmt_execute($stmt);
$bkt = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$bkt) {
    tolak("Belum ada jawaban siswa ini untuk skill " . $id_skill . ".");
}

$p_akhir = round((float) $bkt['p_know'], 4);

/* JAWABAN DI TRAP INI DARI SERVER: jumlah_soal jawaban terakhir siswa untuk skill ini */
$stmt = mysqli_prepare(
    $conn,
    "SELECT benar FROM jawaban_siswa
     WHERE id_siswa = ? AND id_skill = ?
     ORDER BY id_jawaban DESC
     LIMIT ?"
);
mysqli_stmt_bind_param($stmt, "iii", $id_siswa, $id_skill, $jumlah_soal);
mysqli_stmt_execute($stmt);
$hasilJawab = mysqli_stmt_get_result($stmt);

$jawabanTrap = [];
while ($row = mysqli_fetch_assoc($hasilJawab)) {
    $jawabanTrap[] = ((int) $row['benar']) === 1;
}
$jawabanTrap = array_reverse($jawabanTrap);

if (count($jawabanTrap) < $jumlah_soal) {
    tolak("Jawaban trap ini belum lengkap tersimpan.");
}

// Jumlah benar mengikuti data server, bukan kiriman game.
$jumlah_benar = count(array_filter($jawabanTrap));
$kategori = kategoriTrap($p_akhir, $jawabanTrap);

$nyawa_berkurang = ($kategori === 'Belum Menguasai') ? 1 : 0;

/* SIMPAN (FK ke siswa: id_siswa yang tidak ada akan ditolak) */
$stmt = mysqli_prepare(
    $conn,
    "INSERT INTO hasil_trap
        (id_siswa, level_game, nomor_trap, remedial, id_skill, jumlah_soal, jumlah_benar,
         p_awal, p_akhir, kategori, nyawa_berkurang, kode_kirim)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
);
mysqli_stmt_bind_param(
    $stmt,
    "iiiiiiiddsis",
    $id_siswa,
    $level_game,
    $nomor_trap,
    $remedial,
    $id_skill,
    $jumlah_soal,
    $jumlah_benar,
    $p_awal,
    $p_akhir,
    $kategori,
    $nyawa_berkurang,
    $kode_kirim
);

if (!mysqli_stmt_execute($stmt)) {
    $errno = mysqli_errno($conn);

    if ($errno == 1062) {
        sendJson(["success" => true, "message" => "Hasil trap ini sudah tersimpan sebelumnya.", "duplikat" => true]);
    }

    tolak($errno == 1452 ? "Siswa tidak ditemukan." : "Gagal menyimpan hasil trap.");
}

sendJson([
    "success" => true,
    "message" => "Hasil trap berhasil disimpan.",
    "id_hasil" => mysqli_insert_id($conn),
    "remedial" => $remedial == 1,
    "p_akhir" => $p_akhir,
    "kategori" => $kategori,
    "nyawa_berkurang" => $nyawa_berkurang == 1
]);
