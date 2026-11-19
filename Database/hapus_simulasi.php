<?php
/*
|--------------------------------------------------------------------------
| Hapus data hasil simulasi_siswa.php
|--------------------------------------------------------------------------
| Menghapus semua baris dengan kode_kirim berawalan "sim-" dari
| jawaban_siswa, hasil_trap, dan nilai_quiz, lalu menghitung ulang
| bkt_siswa dari riwayat jawaban yang TERSISA (data asli tidak berubah).
|
|   php hapus_simulasi.php        -> hanya menampilkan jumlah yang akan dihapus
|   php hapus_simulasi.php --ya   -> benar-benar menghapus
*/

if (PHP_SAPI !== 'cli') {
    exit("Script ini hanya untuk command line.\n");
}

$backend = 'C:/laragon/www/backend';

require $backend . '/config/database.php';
require $backend . '/config/bkt.php';

/** @var mysqli $conn */

$tabel = ['jawaban_siswa', 'hasil_trap', 'nilai_quiz'];

foreach ($tabel as $t) {
    $n = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM $t WHERE kode_kirim LIKE 'sim-%'"))[0];
    printf("%-14s %5d baris simulasi\n", $t, $n);
}

if (!in_array('--ya', $argv, true)) {
    exit("\nBelum ada yang dihapus. Jalankan dengan --ya untuk menghapus.\n");
}

mysqli_begin_transaction($conn);

try {
    foreach ($tabel as $t) {
        if (!mysqli_query($conn, "DELETE FROM $t WHERE kode_kirim LIKE 'sim-%'")) {
            throw new RuntimeException(mysqli_error($conn));
        }
    }

    // Hitung ulang P(L) dari riwayat jawaban yang tersisa (sama dengan hitung_ulang_bkt.php).
    $hasil = [];
    $param = [];
    $riwayat = mysqli_query(
        $conn,
        "SELECT id_siswa, id_skill, benar FROM jawaban_siswa ORDER BY waktu_jawab ASC, id_jawaban ASC"
    );

    while ($row = mysqli_fetch_assoc($riwayat)) {
        $siswa = (int) $row['id_siswa'];
        $skill = (int) $row['id_skill'];
        $key = "$siswa-$skill";

        if (!isset($param[$skill])) {
            $param[$skill] = bktParamSkill($conn, $skill);
        }

        if (!isset($hasil[$key])) {
            $hasil[$key] = ['s' => $siswa, 'k' => $skill, 'p' => $param[$skill]['p_init'], 'j' => 0, 'b' => 0];
        }

        $b = (int) $row['benar'] === 1;
        $hasil[$key]['p'] = hitungBKT($hasil[$key]['p'], $b, $param[$skill]);
        $hasil[$key]['j']++;
        $hasil[$key]['b'] += $b ? 1 : 0;
    }

    if (!mysqli_query($conn, "DELETE FROM bkt_siswa")) {
        throw new RuntimeException(mysqli_error($conn));
    }

    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO bkt_siswa (id_siswa, id_skill, p_know, jumlah_jawab, jumlah_benar) VALUES (?, ?, ?, ?, ?)"
    );

    foreach ($hasil as $h) {
        mysqli_stmt_bind_param($stmt, "iidii", $h['s'], $h['k'], $h['p'], $h['j'], $h['b']);
        if (!mysqli_stmt_execute($stmt)) {
            throw new RuntimeException(mysqli_error($conn));
        }
    }

    mysqli_commit($conn);
    echo "\nData simulasi dihapus. bkt_siswa dihitung ulang: " . count($hasil) . " baris dari jawaban asli.\n";
} catch (Throwable $e) {
    mysqli_rollback($conn);
    exit("\nGAGAL, tidak ada yang berubah: " . $e->getMessage() . "\n");
}
