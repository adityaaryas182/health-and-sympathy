<?php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

require_once '../config/database.php';
require_once '../config/bkt.php';

/** @var mysqli $conn */

mysqli_set_charset($conn, "utf8mb4");

// Semua error query dilempar sebagai exception supaya bisa di-rollback di satu tempat.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

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

$input = json_decode(file_get_contents("php://input"), true);

$id_siswa = isset($input['id_siswa']) ? intval($input['id_siswa']) : 0;
$id_soal = isset($input['id_soal']) ? intval($input['id_soal']) : 0;
$jawaban_siswa = isset($input['jawaban_siswa']) ? strtoupper(trim($input['jawaban_siswa'])) : '';

// Kode unik per jawaban dari game. Jika request yang sama terkirim ulang
// (misal koneksi putus setelah server menyimpan), jawaban tidak dicatat dua kali.
$kode_kirim = isset($input['kode_kirim']) ? trim($input['kode_kirim']) : '';
if ($kode_kirim !== '' && !preg_match('/^[A-Za-z0-9-]{8,36}$/', $kode_kirim)) {
    sendJson([
        "success" => false,
        "message" => "kode_kirim tidak valid."
    ]);
}
$kode_kirim = ($kode_kirim === '') ? null : $kode_kirim;

/**
 * Jawaban dengan kode_kirim ini sudah pernah disimpan:
 * kirim ulang hasil yang sama tanpa mengubah data.
 */
function kirimHasilDuplikat($conn, $kode_kirim)
{
    $stmt = mysqli_prepare(
        $conn,
        "SELECT j.id_siswa, j.id_soal, j.id_skill, j.jawaban_siswa, j.jawaban_benar, j.benar,
                s.pembahasan, b.p_know
         FROM jawaban_siswa j
         LEFT JOIN soal s ON s.id_soal = j.id_soal
         LEFT JOIN bkt_siswa b ON b.id_siswa = j.id_siswa AND b.id_skill = j.id_skill
         WHERE j.kode_kirim = ?
         LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, "s", $kode_kirim);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$row) {
        return;
    }

    $pKnow = floatval($row['p_know']);

    sendJson([
        "success" => true,
        "message" => "Jawaban ini sudah tersimpan sebelumnya.",
        "duplikat" => true,
        "id_siswa" => (int) $row['id_siswa'],
        "id_soal" => (int) $row['id_soal'],
        "id_skill" => (int) $row['id_skill'],
        "jawaban_siswa" => $row['jawaban_siswa'],
        "jawaban_benar" => $row['jawaban_benar'],
        "benar" => (int) $row['benar'] === 1,
        "p_know" => $pKnow,
        "mastery_tercapai" => $pKnow >= BKT_MASTERY,
        "pembahasan" => (string) $row['pembahasan']
    ]);
}

if ($id_siswa <= 0 || $id_soal <= 0 || $jawaban_siswa === '') {
    sendJson([
        "success" => false,
        "message" => "Data tidak lengkap."
    ]);
}

// Waktu habis dikirim dari game sebagai "TIMEOUT" dan dihitung sebagai jawaban salah.
// Disimpan sebagai "-" karena kolom jawaban_siswa hanya varchar(5).
if ($jawaban_siswa === 'TIMEOUT') {
    $jawaban_siswa = '-';
}

if (!in_array($jawaban_siswa, ['A', 'B', 'C', 'D', '-'])) {
    sendJson([
        "success" => false,
        "message" => "Jawaban siswa harus A, B, C, D, atau TIMEOUT."
    ]);
}

/*
| Simpan riwayat jawaban dan update BKT dalam SATU transaksi.
| Jika salah satu langkah gagal, semuanya dibatalkan, sehingga
| jawaban_siswa dan bkt_siswa selalu sinkron.
*/
try {
    if ($kode_kirim !== null) {
        kirimHasilDuplikat($conn, $kode_kirim);
    }

    mysqli_begin_transaction($conn);

    // 1. Ambil data soal
    $stmt = mysqli_prepare(
        $conn,
        "SELECT id_skill, jawaban_benar, pembahasan FROM soal WHERE id_soal = ? LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, "i", $id_soal);
    mysqli_stmt_execute($stmt);
    $soal = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$soal) {
        mysqli_rollback($conn);
        sendJson([
            "success" => false,
            "message" => "Soal tidak ditemukan."
        ]);
    }

    $id_skill = intval($soal['id_skill']);
    $jawaban_benar = strtoupper(trim($soal['jawaban_benar']));
    $pembahasan = (string) $soal['pembahasan'];

    if (!in_array($jawaban_benar, ['A', 'B', 'C', 'D'])) {
        mysqli_rollback($conn);
        sendJson([
            "success" => false,
            "message" => "Jawaban benar pada soal tidak valid."
        ]);
    }

    $paramBKT = bktParamSkill($conn, $id_skill);
    $benar = ($jawaban_siswa === $jawaban_benar) ? 1 : 0;

    // 2. Simpan riwayat jawaban
    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO jawaban_siswa
            (id_siswa, id_soal, id_skill, jawaban_siswa, jawaban_benar, benar, kode_kirim)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    mysqli_stmt_bind_param(
        $stmt,
        "iiissis",
        $id_siswa,
        $id_soal,
        $id_skill,
        $jawaban_siswa,
        $jawaban_benar,
        $benar,
        $kode_kirim
    );
    mysqli_stmt_execute($stmt);

    // 3. Pastikan baris BKT siswa-skill ada (p_know awal = P(L0)),
    //    lalu kunci baris itu agar dua jawaban bersamaan tidak saling menimpa.
    $pInit = $paramBKT['p_init'];
    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO bkt_siswa (id_siswa, id_skill, p_know, jumlah_jawab, jumlah_benar)
         VALUES (?, ?, ?, 0, 0)
         ON DUPLICATE KEY UPDATE id_bkt = id_bkt"
    );
    mysqli_stmt_bind_param($stmt, "iid", $id_siswa, $id_skill, $pInit);
    mysqli_stmt_execute($stmt);

    $stmt = mysqli_prepare(
        $conn,
        "SELECT p_know, jumlah_jawab, jumlah_benar
         FROM bkt_siswa
         WHERE id_siswa = ? AND id_skill = ?
         FOR UPDATE"
    );
    mysqli_stmt_bind_param($stmt, "ii", $id_siswa, $id_skill);
    mysqli_stmt_execute($stmt);
    $bkt = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    // 4. Hitung dan simpan P(L) baru
    $pKnowBaru = hitungBKT(floatval($bkt['p_know']), $benar == 1, $paramBKT);
    $jumlahJawab = intval($bkt['jumlah_jawab']) + 1;
    $jumlahBenar = intval($bkt['jumlah_benar']) + $benar;

    $stmt = mysqli_prepare(
        $conn,
        "UPDATE bkt_siswa
         SET p_know = ?, jumlah_jawab = ?, jumlah_benar = ?
         WHERE id_siswa = ? AND id_skill = ?"
    );
    mysqli_stmt_bind_param(
        $stmt,
        "diiii",
        $pKnowBaru,
        $jumlahJawab,
        $jumlahBenar,
        $id_siswa,
        $id_skill
    );
    mysqli_stmt_execute($stmt);

    mysqli_commit($conn);
} catch (mysqli_sql_exception $e) {
    mysqli_rollback($conn);

    // 1062 = kode_kirim kembar: request yang sama masuk bersamaan dan yang
    // lain sudah tersimpan duluan. Kirim hasil yang sudah tersimpan itu.
    if ($e->getCode() === 1062 && $kode_kirim !== null) {
        kirimHasilDuplikat($conn, $kode_kirim);
    }

    // 1452 = foreign key gagal: id_siswa tidak ada di tabel siswa.
    $pesan = ($e->getCode() === 1452)
        ? "Siswa tidak ditemukan."
        : "Gagal menyimpan jawaban: " . $e->getMessage();

    sendJson([
        "success" => false,
        "message" => $pesan
    ]);
}

sendJson([
    "success" => true,
    "message" => "Jawaban berhasil disimpan.",
    "id_siswa" => $id_siswa,
    "id_soal" => $id_soal,
    "id_skill" => $id_skill,
    "jawaban_siswa" => $jawaban_siswa,
    "jawaban_benar" => $jawaban_benar,
    "benar" => $benar == 1,
    "p_know" => $pKnowBaru,
    "mastery_tercapai" => $pKnowBaru >= BKT_MASTERY,
    "pembahasan" => $pembahasan
]);
