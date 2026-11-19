<?php

header("Content-Type: application/json; charset=UTF-8");

// Untuk testing dari Unity / Postman.
// Saat online nanti bisa dibatasi sesuai domain.
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

require_once '../config/database.php';
require_once '../config/bkt.php';

/** @var mysqli $conn */

mysqli_set_charset($conn, "utf8mb4");
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function sendJson($data)
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function gagal($pesan)
{
    sendJson(["success" => false, "message" => $pesan]);
}

/** Jalankan prepared statement dan kembalikan semua baris. */
function ambil($conn, $sql, $tipe = "", ...$nilai)
{
    $stmt = mysqli_prepare($conn, $sql);
    if ($tipe !== "") {
        mysqli_stmt_bind_param($stmt, $tipe, ...$nilai);
    }
    mysqli_stmt_execute($stmt);
    return mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
}

function formatSoal($row)
{
    return [
        "id_soal" => (int) $row['id_soal'],
        "id_skill" => (int) $row['id_skill'],
        "tingkat" => (int) $row['tingkat'],
        "pertanyaan" => $row['pertanyaan'],
        "pilihan_a" => $row['pilihan_a'],
        "pilihan_b" => $row['pilihan_b'],
        "pilihan_c" => $row['pilihan_c'],
        "pilihan_d" => $row['pilihan_d']
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    gagal("Method harus GET.");
}

/*
| Cara pakai:
|   get_soal.php?mode=trap&id_siswa=6&level=1&trap=3
|       -> 1 soal untuk trap ke-3 di level 1 (dipakai game).
|          Trap remedial (trap=5) boleh ditambah &id_skill=..., supaya
|          skill tidak berganti di tengah sesi trap.
|   get_soal.php?mode=adaptif&id_siswa=5  -> 1 soal dipilih dari semua skill (mode lama)
|   get_soal.php?id=3                     -> soal dengan id_soal = 3
|   get_soal.php?id_skill=1&limit=10      -> soal acak (filter opsional)
|
| Kunci jawaban & pembahasan TIDAK dikirim; keduanya baru dikirim
| submit_jawaban.php setelah siswa menjawab.
*/
$mode = isset($_GET['mode']) ? strtolower(trim($_GET['mode'])) : '';
$id_siswa = isset($_GET['id_siswa']) ? max(0, intval($_GET['id_siswa'])) : 0;
$id_soal = isset($_GET['id']) ? max(0, intval($_GET['id'])) : 0;
$id_skill = isset($_GET['id_skill']) ? max(0, intval($_GET['id_skill'])) : 0;
$limit = isset($_GET['limit']) ? max(0, intval($_GET['limit'])) : 0;
$level = isset($_GET['level']) ? intval($_GET['level']) : 0;
$trap = isset($_GET['trap']) ? intval($_GET['trap']) : 0;

// Soal yang sudah muncul di sesi trap ini (dikirim game, mis. "12,7,15").
// Hanya angka positif, maksimal 20; diubah jadi daftar bersih untuk FIND_IN_SET.
$kecuali = [];
foreach (explode(',', (string) ($_GET['kecuali'] ?? '')) as $v) {
    $v = intval($v);
    if ($v > 0) {
        $kecuali[$v] = true;
    }
}
$kecuali = implode(',', array_slice(array_keys($kecuali), 0, 20));

$kolom = "s.id_soal, s.id_skill, s.tingkat, s.pertanyaan, s.pilihan_a, s.pilihan_b, s.pilihan_c, s.pilihan_d";

try {
    /*
    |--------------------------------------------------------------------------
    | MODE TRAP
    |--------------------------------------------------------------------------
    */
    if ($mode === 'trap') {
        if ($id_siswa <= 0) {
            gagal("Mode trap butuh id_siswa.");
        }
        if ($trap < 1 || $trap > TRAP_JUMLAH) {
            gagal("Nomor trap harus 1 sampai " . TRAP_JUMLAH . ".");
        }

        if (!ambil($conn, "SELECT id_siswa FROM siswa WHERE id_siswa = ?", "i", $id_siswa)) {
            gagal("Siswa tidak ditemukan.");
        }

        // Skill pada level ini beserta P(L) siswa (belum pernah menjawab = P(L0) skill).
        $skillLevel = ambil(
            $conn,
            "SELECT k.id_skill, k.nama_skill,
                    COALESCE(b.p_know, k.p_init, ?) AS p_know
             FROM skill k
             LEFT JOIN bkt_siswa b ON b.id_skill = k.id_skill AND b.id_siswa = ?
             WHERE k.level_game = ?
             ORDER BY k.id_skill ASC",
            "dii",
            BKT_DEFAULT_INIT,
            $id_siswa,
            $level
        );

        if (count($skillLevel) < 2) {
            gagal("Level $level belum punya 2 skill. Cek kolom level_game di tabel skill.");
        }

        $remedial = ($trap === TRAP_REMEDIAL);
        $tingkat = 0; // 0 = semua tingkat (remedial)

        if ($remedial) {
            $dipilih = null;

            // Lanjutan sesi remedial: skill tetap sama seperti soal pertama.
            if ($id_skill > 0) {
                foreach ($skillLevel as $s) {
                    if ((int) $s['id_skill'] === $id_skill) {
                        $dipilih = $s;
                    }
                }
                if ($dipilih === null) {
                    gagal("Skill $id_skill bukan bagian dari level $level.");
                }
            } else {
                // Awal sesi remedial: skill dengan P(L) terendah.
                foreach ($skillLevel as $s) {
                    if ($dipilih === null || floatval($s['p_know']) < floatval($dipilih['p_know'])) {
                        $dipilih = $s;
                    }
                }
            }
        } else {
            [$indeks, $tingkat] = petaTrap()[$trap];
            $dipilih = $skillLevel[$indeks];
        }

        $id_skill = (int) $dipilih['id_skill'];

        // Soal yang terakhir kali dijawab siswa ini, supaya tidak langsung diulang.
        $terakhir = ambil(
            $conn,
            "SELECT id_soal FROM jawaban_siswa WHERE id_siswa = ? ORDER BY id_jawaban DESC LIMIT 1",
            "i",
            $id_siswa
        );
        $id_soal_terakhir = $terakhir ? (int) $terakhir[0]['id_soal'] : 0;

        // Urutan pemilihan soal: lihat penjelasan di config/bkt.php (REMEDIAL_ULANG_SOAL_SALAH).
        $utamakan_salah = ($remedial && REMEDIAL_ULANG_SOAL_SALAH) ? 1 : 0;
        $soal = ambil(
            $conn,
            "SELECT $kolom,
                    (SELECT COUNT(*) FROM jawaban_siswa j
                      WHERE j.id_siswa = ? AND j.id_soal = s.id_soal) AS kali_dijawab,
                    (SELECT j.benar FROM jawaban_siswa j
                      WHERE j.id_siswa = ? AND j.id_soal = s.id_soal
                      ORDER BY j.id_jawaban DESC LIMIT 1) AS benar_terakhir,
                    (SELECT MAX(j.id_jawaban) FROM jawaban_siswa j
                      WHERE j.id_siswa = ? AND j.id_soal = s.id_soal) AS urutan_terakhir
             FROM soal s
             WHERE s.status = 'aktif' AND s.id_skill = ?
             ORDER BY (? = 0 OR s.tingkat = ?) DESC,
                      (s.id_soal = ?) ASC,
                      (FIND_IN_SET(s.id_soal, ?) > 0) ASC,
                      (? = 1 AND COALESCE(benar_terakhir = 0, 0)) DESC,
                      (kali_dijawab = 0) DESC,
                      COALESCE(benar_terakhir, 1) ASC,
                      COALESCE(urutan_terakhir, 0) ASC,
                      RAND()
             LIMIT 1",
            "iiiiiiisi",
            $id_siswa,
            $id_siswa,
            $id_siswa,
            $id_skill,
            $tingkat,
            $tingkat,
            $id_soal_terakhir,
            $kecuali,
            $utamakan_salah
        );

        if (!$soal) {
            gagal("Belum ada soal aktif untuk skill " . $dipilih['nama_skill'] . ".");
        }

        sendJson([
            "success" => true,
            "message" => "Data soal berhasil diambil.",
            "total" => 1,
            "data" => [formatSoal($soal[0])],
            "trap" => [
                "level" => $level,
                "nomor_trap" => $trap,
                "remedial" => $remedial,
                "id_skill" => $id_skill,
                "nama_skill" => $dipilih['nama_skill'],
                "tingkat" => $tingkat,
                "p_know" => round(floatval($dipilih['p_know']), 4),
                "min_soal" => TRAP_MIN_SOAL,
                "max_soal" => TRAP_MAX_SOAL,
                "nilai_menguasai" => NILAI_MENGUASAI,
                "nilai_cukup" => NILAI_CUKUP,
                "batas_mastery" => BKT_MASTERY,
                "batas_cukup" => BKT_CUKUP
            ]
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | MODE LAMA (tetap dipertahankan)
    |--------------------------------------------------------------------------
    */
    if ($mode === 'adaptif') {
        if ($id_siswa <= 0) {
            gagal("Mode adaptif butuh id_siswa.");
        }

        $rows = ambil(
            $conn,
            "SELECT $kolom,
                    (SELECT COUNT(*) FROM jawaban_siswa j
                      WHERE j.id_siswa = ? AND j.id_soal = s.id_soal) AS kali_dijawab,
                    COALESCE(b.p_know, k.p_init, ?) AS p_know_skill
             FROM soal s
             JOIN skill k ON k.id_skill = s.id_skill
             LEFT JOIN bkt_siswa b ON b.id_siswa = ? AND b.id_skill = s.id_skill
             WHERE s.status = 'aktif'
             ORDER BY kali_dijawab ASC,
                      (COALESCE(b.p_know, k.p_init, ?) >= ?) ASC,
                      p_know_skill ASC,
                      RAND()
             LIMIT 1",
            "ididd",
            $id_siswa,
            BKT_DEFAULT_INIT,
            $id_siswa,
            BKT_DEFAULT_INIT,
            BKT_MASTERY
        );
    } elseif ($id_soal > 0) {
        $rows = ambil(
            $conn,
            "SELECT $kolom FROM soal s WHERE s.id_soal = ? AND s.status = 'aktif' LIMIT 1",
            "i",
            $id_soal
        );
    } else {
        $sql = "SELECT $kolom FROM soal s WHERE s.status = 'aktif'";
        $tipe = "";
        $nilai = [];

        if ($id_skill > 0) {
            $sql .= " AND s.id_skill = ?";
            $tipe .= "i";
            $nilai[] = $id_skill;
        }

        $sql .= " ORDER BY RAND()";

        if ($limit > 0) {
            $sql .= " LIMIT ?";
            $tipe .= "i";
            $nilai[] = $limit;
        }

        $rows = ambil($conn, $sql, $tipe, ...$nilai);
    }

    sendJson([
        "success" => true,
        "message" => "Data soal berhasil diambil.",
        "total" => count($rows),
        "data" => array_map('formatSoal', $rows)
    ]);
} catch (mysqli_sql_exception $e) {
    gagal("Query error: " . $e->getMessage());
}
