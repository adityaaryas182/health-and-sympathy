<?php
/*
|--------------------------------------------------------------------------
| SIMULASI: semua siswa memainkan Level 1-3 (untuk mengecek tampilan dashboard)
|--------------------------------------------------------------------------
| Memakai API yang sama dengan game (get_soal, submit_jawaban, submit_trap,
| submit_nilai) dan aturan game yang sama: 5 trap per level, 3 nyawa,
| nyawa -1 jika hasil trap Belum Menguasai, nyawa habis = Game Over lalu
| level diulang (maks. 2 kali percobaan per level), poin 10 per benar
| + bonus 5 tiap 3 benar beruntun.
|
| Setiap siswa diberi kemampuan acak (kuat / sedang / lemah) yang naik
| sedikit setiap kali menjawab, supaya hasil di dashboard bervariasi.
|
| SEMUA data simulasi diberi kode_kirim berawalan "sim-", sehingga bisa
| dihapus bersih dengan:  php hapus_simulasi.php --ya
|
| Jalankan (command line, Apache + MySQL Laragon harus menyala):
|   php simulasi_siswa.php                 -> semua siswa
|   php simulasi_siswa.php 6 7 8           -> hanya id_siswa tertentu
*/

if (PHP_SAPI !== 'cli') {
    exit("Script ini hanya untuk command line.\n");
}

$backend = 'C:/laragon/www/backend';
$api = 'http://localhost/backend/api';

require $backend . '/config/database.php';
require $backend . '/config/bkt.php';

/** @var mysqli $conn */

mt_srand(20261117); // hasil sama setiap dijalankan ulang (setelah dihapus)

// ---------------------------------------------------------------------------
// Bantuan
// ---------------------------------------------------------------------------

function http($metode, $url, $json = null)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    if ($metode === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }

    $teks = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($teks === false) {
        throw new RuntimeException("HTTP gagal: $url ($err)");
    }

    $data = json_decode($teks, true);
    if (!is_array($data) || empty($data['success'])) {
        throw new RuntimeException("Ditolak server: $url -> $teks");
    }

    return $data;
}

function kodeSim()
{
    return 'sim-' . bin2hex(random_bytes(16)); // 36 karakter
}

function acak()
{
    return mt_rand() / mt_getrandmax();
}

// ---------------------------------------------------------------------------
// Data awal
// ---------------------------------------------------------------------------

$kunci = [];
foreach (mysqli_fetch_all(mysqli_query($conn, "SELECT id_soal, jawaban_benar FROM soal"), MYSQLI_ASSOC) as $r) {
    $kunci[(int) $r['id_soal']] = strtoupper($r['jawaban_benar']);
}

$filter = array_map('intval', array_slice($argv, 1));
$siswaList = mysqli_fetch_all(mysqli_query($conn, "SELECT id_siswa, nama_siswa FROM siswa ORDER BY id_siswa"), MYSQLI_ASSOC);
if ($filter) {
    $siswaList = array_values(array_filter($siswaList, fn($s) => in_array((int) $s['id_siswa'], $filter, true)));
}

$sudahAda = (int) mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM jawaban_siswa WHERE kode_kirim LIKE 'sim-%'"))[0];
if ($sudahAda > 0) {
    exit("Sudah ada $sudahAda jawaban simulasi. Hapus dulu: php hapus_simulasi.php --ya\n");
}

// ---------------------------------------------------------------------------
// Satu sesi trap (sama dengan QuizManager.JalankanSesiTrap)
// ---------------------------------------------------------------------------

/**
 * @return array [kategori, benar, salah] ; $st = state skor level (by reference)
 */
function mainTrap($api, $kunci, $idSiswa, $level, $trap, &$profil, &$st)
{
    $idSkill = 0;
    $min = 2;
    $max = 5;
    $pAwal = 0.5;
    $dijawab = 0;
    $benarSesi = 0;
    $soalSesi = []; // sama seperti game: soal yang sudah muncul di sesi trap ini
    $jawabanSesi = []; // benar/salah tiap soal di trap ini (aturan Menguasai, config/bkt.php)

    while (true) {
        $url = "$api/get_soal.php?mode=trap&id_siswa=$idSiswa&level=$level&trap=$trap" . ($idSkill ? "&id_skill=$idSkill" : "")
             . ($soalSesi ? "&kecuali=" . implode(',', $soalSesi) : "");
        $resp = http('GET', $url);

        if ($dijawab === 0) {
            $idSkill = (int) $resp['trap']['id_skill'];
            $min = (int) $resp['trap']['min_soal'];
            $max = (int) $resp['trap']['max_soal'];
            $pAwal = (float) $resp['trap']['p_know'];
        }

        $soal = $resp['data'][0];
        $soalSesi[] = (int) $soal['id_soal'];
        $benarKunci = $kunci[(int) $soal['id_soal']];

        // Peluang benar = kemampuan skill + latihan (naik tiap menjawab).
        $kemampuan = $profil['skill'][$idSkill] ?? $profil['dasar'];
        $peluang = min(0.97, $kemampuan + 0.04 * ($profil['latihan'][$idSkill] ?? 0));
        $profil['latihan'][$idSkill] = ($profil['latihan'][$idSkill] ?? 0) + 1;

        if (acak() < $profil['timeout']) {
            $jawab = 'TIMEOUT';
        } elseif (acak() < $peluang) {
            $jawab = $benarKunci;
        } else {
            $salah = array_values(array_diff(['A', 'B', 'C', 'D'], [$benarKunci]));
            $jawab = $salah[mt_rand(0, 2)];
        }

        $hasil = http('POST', "$api/submit_jawaban.php", [
            'id_siswa' => $idSiswa,
            'id_soal' => (int) $soal['id_soal'],
            'jawaban_siswa' => $jawab,
            'kode_kirim' => kodeSim(),
        ]);

        $dijawab++;

        // Skor game (ScoreManager): 10 per benar + bonus 5 tiap 3 benar beruntun.
        if ($hasil['benar']) {
            $benarSesi++;
            $st['benar']++;
            $st['streak']++;
            $st['poin'] += 10 + ($st['streak'] % 3 === 0 ? 5 : 0);
        } else {
            $st['salah']++;
            $st['streak'] = 0;
        }

        $jawabanSesi[] = (bool) $hasil['benar'];

        if (($dijawab >= $min && trapMenguasai((float) $hasil['p_know'], $jawabanSesi)) || $dijawab >= $max) {
            break;
        }
    }

    $hasilTrap = http('POST', "$api/submit_trap.php", [
        'id_siswa' => $idSiswa,
        'level_game' => $level,
        'nomor_trap' => $trap,
        'id_skill' => $idSkill,
        'jumlah_soal' => $dijawab,
        'jumlah_benar' => $benarSesi,
        'p_awal' => $pAwal,
        'kode_kirim' => kodeSim(),
    ]);

    return $hasilTrap['kategori'];
}

function kirimNilai($api, $idSiswa, $level, $status, $st)
{
    $jumlah = $st['benar'] + $st['salah'];
    if ($jumlah <= 0) return;

    http('POST', "$api/submit_nilai.php", [
        'id_siswa' => $idSiswa,
        'level_game' => $level,
        'status' => $status,
        'skor' => (int) round($st['benar'] / $jumlah * 100),
        'poin_game' => $st['poin'],
        'jumlah_soal' => $jumlah,
        'jumlah_benar' => $st['benar'],
        'jumlah_salah' => $st['salah'],
        'kode_kirim' => kodeSim(),
    ]);
}

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------

$ringkas = ['Selesai' => 0, 'Game Over' => 0, 'trap' => 0, 'jawaban' => 0];

foreach ($siswaList as $s) {
    $id = (int) $s['id_siswa'];

    // Profil kemampuan: ~25% kuat, ~45% sedang, ~30% lemah.
    $r = acak();
    if ($r < 0.25) {
        $tipe = 'kuat';
        $dasar = 0.82 + acak() * 0.13;
        $timeout = 0.02;
    } elseif ($r < 0.70) {
        $tipe = 'sedang';
        $dasar = 0.58 + acak() * 0.2;
        $timeout = 0.04;
    } else {
        $tipe = 'lemah';
        $dasar = 0.3 + acak() * 0.2;
        $timeout = 0.08;
    }

    // Tiap skill sedikit berbeda; materi makin sulit di level atas.
    $profil = ['dasar' => $dasar, 'timeout' => $timeout, 'skill' => [], 'latihan' => []];
    for ($sk = 1; $sk <= 6; $sk++) {
        $level = (int) ceil($sk / 2);
        $profil['skill'][$sk] = max(0.15, min(0.97, $dasar + (acak() - 0.5) * 0.2 - 0.04 * ($level - 1)));
    }

    $poinTersimpan = 0; // PlayerPrefs PLAYER_SCORE, di-reset saat login
    $jejak = [];

    for ($level = 1; $level <= 3; $level++) {
        $lulus = false;

        for ($percobaan = 1; $percobaan <= 2 && !$lulus; $percobaan++) {
            $st = ['benar' => 0, 'salah' => 0, 'streak' => 0, 'poin' => $poinTersimpan];
            $nyawa = 3;
            $gameOver = false;

            for ($trap = 1; $trap <= 5; $trap++) {
                $kategori = mainTrap($api, $kunci, $id, $level, $trap, $profil, $st);
                $ringkas['trap']++;

                if ($kategori === 'Belum Menguasai') {
                    $nyawa--;
                    if ($nyawa <= 0) {
                        $gameOver = true;
                        break;
                    }
                }
            }

            $ringkas['jawaban'] += $st['benar'] + $st['salah'];

            if ($gameOver) {
                kirimNilai($api, $id, $level, 'Game Over', $st);
                $ringkas['Game Over']++;
                $jejak[] = "L$level:GameOver";
            } else {
                kirimNilai($api, $id, $level, 'Selesai', $st);
                $ringkas['Selesai']++;
                $poinTersimpan = $st['poin'];
                $lulus = true;
                $jejak[] = "L$level:Selesai(" . round($st['benar'] / max(1, $st['benar'] + $st['salah']) * 100) . ")";
            }
        }

        if (!$lulus) {
            break; // dua kali game over: siswa berhenti di level ini
        }
    }

    printf("%-3d %-32s %-6s %s\n", $id, $s['nama_siswa'], $tipe, implode(' ', $jejak));
}

printf(
    "\nSelesai. Nilai: %d Selesai + %d Game Over | %d hasil trap | %d jawaban (semua kode_kirim 'sim-').\n",
    $ringkas['Selesai'],
    $ringkas['Game Over'],
    $ringkas['trap'],
    $ringkas['jawaban']
);
echo "Hapus nanti dengan: php hapus_simulasi.php --ya\n";
