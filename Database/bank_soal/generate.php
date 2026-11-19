<?php
/*
| Membaca bank_soal.txt lalu menghasilkan:
|   - seed_bank_soal.sql : isi ulang tabel skill & soal
|   - review_bank_soal.md : daftar soal + kunci untuk dicek guru/dosen
|
| Jalankan: php generate.php
| Posisi kunci dibuat merata (A/B/C/D masing-masing 1/4) dan deterministik.
*/

if (PHP_SAPI !== 'cli') {
    exit;
}

$skill = [
    'S1' => [1, 'Expressing Sympathy', 2, 'Sympathy Expressions'],
    'S2' => [2, 'Responding to Sympathy', 2, 'Sympathy Expressions'],
    'S3' => [3, 'Asking about Health', 1, 'Asking and Giving Information about Health'],
    'S4' => [4, 'Giving Information about Health', 1, 'Asking and Giving Information about Health'],
    'S5' => [5, 'Simple Present: Positive Sentences', 3, 'Simple Present Tense'],
    'S6' => [6, 'Simple Present: Negative Sentences and Questions', 3, 'Simple Present Tense'],
];
$namaTingkat = [1 => 'Dasar', 2 => 'Lanjut'];

// ---------- parse ----------
$bucket = null;
$soal = [];
$cur = null;
$error = [];

$simpan = function () use (&$cur, &$soal, &$error) {
    if ($cur === null) {
        return;
    }
    $label = "{$cur['skill']}/{$cur['tingkat']} \"" . substr($cur['q'], 0, 40) . "\"";
    if ($cur['benar'] === null) $error[] = "$label: tidak ada jawaban benar";
    if (count($cur['pengecoh']) !== 3) $error[] = "$label: pengecoh harus 3, ada " . count($cur['pengecoh']);
    if ($cur['e'] === '') $error[] = "$label: pembahasan kosong";
    $semua = array_merge([$cur['benar']], $cur['pengecoh']);
    if (count(array_unique($semua)) !== count($semua)) $error[] = "$label: ada pilihan kembar";
    $soal[] = $cur;
    $cur = null;
};

foreach (file(__DIR__ . '/bank_soal.txt', FILE_IGNORE_NEW_LINES) as $no => $baris) {
    $baris = rtrim($baris);
    if (preg_match('/^## (S[1-6]) ([12])$/', $baris, $m)) {
        $simpan();
        $bucket = [$m[1], (int) $m[2]];
        continue;
    }
    if ($baris === '' || $baris[0] === '#') {
        continue;
    }
    if ($bucket === null) {
        $error[] = "baris " . ($no + 1) . ": di luar blok ## skill";
        continue;
    }
    $tag = substr($baris, 0, 2);
    $isi = trim(substr($baris, 2));
    if ($tag === 'Q:') {
        if ($cur !== null && $cur['benar'] !== null) {
            $simpan();
        }
        if ($cur === null) {
            $cur = ['skill' => $bucket[0], 'tingkat' => $bucket[1], 'q' => $isi, 'benar' => null, 'pengecoh' => [], 'e' => ''];
        } else {
            $cur['q'] .= "\n" . $isi;
        }
    } elseif ($tag === '+ ') {
        $cur['benar'] = $isi;
    } elseif ($tag === '- ') {
        $cur['pengecoh'][] = $isi;
    } elseif ($tag === 'E:') {
        $cur['e'] = $isi;
    } else {
        $error[] = "baris " . ($no + 1) . ": format tidak dikenal: $baris";
    }
}
$simpan();

// cek jumlah per blok
$hitung = [];
foreach ($soal as $s) {
    $hitung[$s['skill'] . '/' . $s['tingkat']] = ($hitung[$s['skill'] . '/' . $s['tingkat']] ?? 0) + 1;
}
foreach ($skill as $kode => $_) {
    foreach ([1, 2] as $t) {
        if (($hitung["$kode/$t"] ?? 0) < 5) {
            $error[] = "$kode tingkat $t: minimal 5 soal, ada " . ($hitung["$kode/$t"] ?? 0);
        }
    }
}

if ($error) {
    fwrite(STDERR, "GAGAL:\n- " . implode("\n- ", $error) . "\n");
    exit(1);
}

// ---------- posisi kunci merata ----------
$huruf = ['A', 'B', 'C', 'D'];
// Tiap blok mendapat jatah posisi yang seimbang (diputar antar blok),
// lalu urutannya diacak dengan seed tetap supaya tidak berpola ABCD.
$jatah = [];
$urutBlok = 0;
foreach ($soal as $s) {
    $k = $s['skill'] . '/' . $s['tingkat'];
    if (!isset($jatah[$k])) {
        $posisi = [];
        for ($n = 0; $n < $hitung[$k]; $n++) {
            $posisi[] = ($n + $urutBlok) % 4;
        }
        mt_srand(crc32($k));
        shuffle($posisi);
        $jatah[$k] = $posisi;
        $urutBlok++;
    }
}
foreach ($soal as $i => $s) {
    $pos = array_shift($jatah[$s['skill'] . '/' . $s['tingkat']]);

    $pilihan = $s['pengecoh'];
    array_splice($pilihan, $pos, 0, [$s['benar']]);
    $soal[$i]['pilihan'] = $pilihan;
    $soal[$i]['kunci'] = $huruf[$pos];
}

// ---------- SQL ----------
$q = fn($v) => "'" . str_replace("'", "''", $v) . "'";

$sql = "-- DIHASILKAN OTOMATIS oleh bank_soal/generate.php dari bank_soal.txt. Jangan edit manual.\n";
$sql .= "-- Jalankan SETELAH migrasi_bank_soal_2026-11-13.sql.\n";
$sql .= "-- PERHATIAN: menghapus semua soal, skill, jawaban_siswa, dan bkt_siswa lama.\n\n";
$sql .= "SET FOREIGN_KEY_CHECKS = 0;\nDELETE FROM jawaban_siswa;\nDELETE FROM bkt_siswa;\nDELETE FROM soal;\nDELETE FROM skill;\n";
$sql .= "ALTER TABLE jawaban_siswa AUTO_INCREMENT = 1;\nALTER TABLE bkt_siswa AUTO_INCREMENT = 1;\nALTER TABLE soal AUTO_INCREMENT = 1;\nALTER TABLE skill AUTO_INCREMENT = 1;\nSET FOREIGN_KEY_CHECKS = 1;\n\n";

$sql .= "INSERT INTO skill (id_skill, nama_skill, level_game, materi, p_init, p_learn, p_guess, p_slip) VALUES\n";
$baris = [];
foreach ($skill as [$id, $nama, $level, $materi]) {
    $baris[] = "($id, {$q($nama)}, $level, {$q($materi)}, 0.20, 0.10, 0.25, 0.10)";
}
$sql .= implode(",\n", $baris) . ";\n\n";

$sql .= "INSERT INTO soal (id_skill, level_game, tingkat, pertanyaan, pilihan_a, pilihan_b, pilihan_c, pilihan_d, jawaban_benar, pembahasan, status) VALUES\n";
$baris = [];
foreach ($soal as $s) {
    [$id, , $level] = $skill[$s['skill']];
    $p = array_map($q, $s['pilihan']);
    $baris[] = "($id, $level, {$s['tingkat']}, {$q($s['q'])}, {$p[0]}, {$p[1]}, {$p[2]}, {$p[3]}, '{$s['kunci']}', {$q($s['e'])}, 'aktif')";
}
$sql .= implode(",\n", $baris) . ";\n";

file_put_contents(__DIR__ . '/seed_bank_soal.sql', $sql);

// ---------- review ----------
$md = "# Bank Soal Health and Sympathy\n\n";
$md .= "Dihasilkan dari `bank_soal.txt`. Total **" . count($soal) . " soal**, 6 skill, 2 tingkat (Dasar/Lanjut).\n\n";
$kunciTotal = array_count_values(array_column($soal, 'kunci'));
ksort($kunciTotal);
$md .= "Sebaran kunci: " . implode(', ', array_map(fn($h, $n) => "$h = $n", array_keys($kunciTotal), $kunciTotal)) . "\n\n";

$no = 0;
$levelSebelum = 0;
$blokSebelum = '';
foreach ($soal as $s) {
    [$id, $nama, $level, $materi] = $skill[$s['skill']];
    if ($level !== $levelSebelum) {
        $md .= "\n## Level $level: $materi\n";
        $levelSebelum = $level;
    }
    $blok = $s['skill'] . $s['tingkat'];
    if ($blok !== $blokSebelum) {
        $md .= "\n### Skill $id: $nama (tingkat {$namaTingkat[$s['tingkat']]})\n\n";
        $blokSebelum = $blok;
    }
    $no++;
    $md .= "**$no.** " . str_replace("\n", "  \n", $s['q']) . "\n\n";
    foreach ($s['pilihan'] as $j => $teks) {
        $tanda = ($huruf[$j] === $s['kunci']) ? ' ✅' : '';
        $md .= "- {$huruf[$j]}. $teks$tanda\n";
    }
    $md .= "\n> Pembahasan: {$s['e']}\n\n";
}

file_put_contents(__DIR__ . '/review_bank_soal.md', $md);

echo "OK: " . count($soal) . " soal. Kunci: " . json_encode($kunciTotal) . "\n";
foreach ($hitung as $k => $n) {
    echo "  $k = $n\n";
}
