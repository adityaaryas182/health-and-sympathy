<?php
/*
| Fungsi bersama halaman nilai.php dan detail_siswa.php.
| Batas kategori mengikuti config/bkt.php (sama dengan game).
*/

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Status penguasaan skill: P-Know DAN nilai (benar / jawab seluruh jawaban skill itu).
 * Aturan sama dengan hasil trap di game (kategoriPenguasaan() di config/bkt.php).
 */
function statusSkill($pKnow, $benar, $jawab)
{
    return kategoriPenguasaan((float) $pKnow, nilaiBenar((int) $benar, (int) $jawab));
}

/** Kelas warna sel rekap dari kategori; null = belum dimainkan. */
function kelasKategori($kategori)
{
    if ($kategori === null) {
        return 'sel-kosong';
    }

    if ($kategori === 'Menguasai') {
        return 'sel-menguasai';
    }

    if ($kategori === 'Cukup Menguasai') {
        return 'sel-cukup';
    }

    return 'sel-belum';
}

/** Teks syarat kategori untuk legenda dashboard. */
function teksSyaratKategori()
{
    return [
        'Menguasai' => 'P-Know ≥ ' . persen(BKT_MASTERY) . '% dan nilai ≥ ' . NILAI_MENGUASAI,
        'Cukup Menguasai' => 'P-Know ≥ ' . persen(BKT_CUKUP) . '% dan nilai ≥ ' . NILAI_CUKUP,
        'Belum Menguasai' => 'selain itu',
    ];
}

function badgeKategoriClass($kategori)
{
    if ($kategori === 'Menguasai') {
        return 'badge-success';
    }

    if ($kategori === 'Cukup Menguasai') {
        return 'badge-warning';
    }

    return 'badge-danger';
}

/** Kelas warna untuk angka P-Know saja (bar & perjalanan P-Know), batas P-Know BKT. */
function kelasSel($pKnow)
{
    if ($pKnow === null) {
        return 'sel-kosong';
    }

    if ($pKnow >= BKT_MASTERY) {
        return 'sel-menguasai';
    }

    if ($pKnow >= BKT_CUKUP) {
        return 'sel-cukup';
    }

    return 'sel-belum';
}

function persen($p)
{
    return round($p * 100);
}

/** Daftar skill urut level, dengan kode pendek S1..Sn. */
function ambilSkill($conn, $level = 0)
{
    $sql = "SELECT id_skill, nama_skill, materi, level_game FROM skill";
    if ($level > 0) {
        $sql .= " WHERE level_game = " . intval($level);
    }
    $sql .= " ORDER BY level_game ASC, id_skill ASC";

    $hasil = [];
    $semua = mysqli_query($conn, "SELECT id_skill FROM skill ORDER BY level_game ASC, id_skill ASC");
    $kode = [];
    $i = 1;
    while ($row = mysqli_fetch_assoc($semua)) {
        $kode[(int) $row['id_skill']] = 'S' . $i++;
    }

    $res = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($res)) {
        $row['kode'] = $kode[(int) $row['id_skill']] ?? ('S' . $row['id_skill']);
        $hasil[(int) $row['id_skill']] = $row;
    }

    return $hasil;
}

/** Level => nama materi. */
function ambilLevel($conn)
{
    $hasil = [];
    $res = mysqli_query($conn, "
        SELECT level_game, MIN(materi) AS materi
        FROM skill
        WHERE level_game IS NOT NULL
        GROUP BY level_game
        ORDER BY level_game ASC
    ");
    while ($row = mysqli_fetch_assoc($res)) {
        $hasil[(int) $row['level_game']] = $row['materi'];
    }

    return $hasil;
}

/** Gabungkan syarat WHERE (nilai sudah aman: intval / whitelist). */
function gabungWhere($dasar, $tambahan = [])
{
    $semua = array_merge($dasar, $tambahan);
    return $semua ? "WHERE " . implode(" AND ", $semua) : "";
}

/** Nama siswa yang bisa diklik ke halaman detail. */
function linkSiswa($id, $nama)
{
    return '<a class="student-link" href="detail_siswa.php?id=' . e($id) . '">' . e($nama) . '</a>';
}

/*
| Pretest & posttest (naskah Bab 2: Analisis Pretest dan Posttest)
| Nilai  = jumlah benar / jumlah soal x 100
| N-Gain = (posttest - pretest) / (100 - pretest)   (Hake, 1998)
| Kategori: tinggi >= 0,7 ; sedang 0,3 - < 0,7 ; rendah < 0,3
*/
define('NILAI_ACUAN', 80); // nilai acuan guru (naskah Bab 1)

function nilaiTes($benar, $soal)
{
    if ($benar === null || $soal === null || (int) $soal <= 0) {
        return null;
    }

    return round((int) $benar / (int) $soal * 100, 2);
}

/** null jika salah satu nilai kosong atau pretest = 100 (tidak bisa dihitung). */
function hitungNGain($pre, $post)
{
    if ($pre === null || $post === null || $pre >= 100) {
        return null;
    }

    return round(($post - $pre) / (100 - $pre), 2);
}

function kategoriNGain($g)
{
    if ($g === null) {
        return '';
    }

    if ($g >= 0.7) {
        return 'Tinggi';
    }

    if ($g >= 0.3) {
        return 'Sedang';
    }

    return 'Rendah';
}

function badgeNGain($g)
{
    if ($g === null) {
        return 'badge-muted';
    }

    return $g >= 0.7 ? 'badge-success' : ($g >= 0.3 ? 'badge-warning' : 'badge-danger');
}

/** Angka gaya Indonesia: 0,75 */
function angka($x, $desimal = 2)
{
    return $x === null ? '–' : number_format($x, $desimal, ',', '.');
}

/** Teks jawaban siswa: "-" berarti waktu habis. */
function teksJawaban($huruf, $soal)
{
    if ($huruf === '-' || $huruf === '') {
        return 'Waktu habis';
    }

    $kolom = 'pilihan_' . strtolower($huruf);
    return $huruf . '. ' . ($soal[$kolom] ?? '');
}
