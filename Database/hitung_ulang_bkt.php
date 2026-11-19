<?php
/*
| Hitung ulang tabel bkt_siswa dari riwayat jawaban_siswa (urut waktu),
| memakai parameter BKT per skill dari config/bkt.php.
|
| Jalankan dari command line (JANGAN taruh di folder web):
|   php hitung_ulang_bkt.php C:/laragon/www/backend
*/

if (PHP_SAPI !== 'cli') {
    exit("Script ini hanya untuk command line.\n");
}

$backend = rtrim($argv[1] ?? 'C:/laragon/www/backend', '/\\');

require $backend . '/config/database.php';
require $backend . '/config/bkt.php';

/** @var mysqli $conn */

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
        $hasil[$key] = [
            'id_siswa' => $siswa,
            'id_skill' => $skill,
            'p_know' => $param[$skill]['p_init'],
            'jawab' => 0,
            'benar' => 0,
        ];
    }

    $b = (int) $row['benar'] === 1;
    $hasil[$key]['p_know'] = hitungBKT($hasil[$key]['p_know'], $b, $param[$skill]);
    $hasil[$key]['jawab']++;
    $hasil[$key]['benar'] += $b ? 1 : 0;
}

mysqli_begin_transaction($conn);
mysqli_query($conn, "DELETE FROM bkt_siswa");

$stmt = mysqli_prepare(
    $conn,
    "INSERT INTO bkt_siswa (id_siswa, id_skill, p_know, jumlah_jawab, jumlah_benar) VALUES (?, ?, ?, ?, ?)"
);

foreach ($hasil as $h) {
    mysqli_stmt_bind_param($stmt, "iidii", $h['id_siswa'], $h['id_skill'], $h['p_know'], $h['jawab'], $h['benar']);
    mysqli_stmt_execute($stmt);
    printf("siswa %-3d skill %d  p_know %.4f  (%d jawab, %d benar)\n",
        $h['id_siswa'], $h['id_skill'], $h['p_know'], $h['jawab'], $h['benar']);
}

mysqli_commit($conn);
echo count($hasil) . " baris bkt_siswa dihitung ulang.\n";
