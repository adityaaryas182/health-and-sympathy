<?php
require_once '../config/database.php';
require_once '../config/bkt.php';
require_once 'cek_login.php';
require_once 'nilai_fungsi.php';

// Padatkan spasi template supaya halaman ringan (isi teks tidak berubah).
ob_start(function ($html) {
    return preg_replace('/\s{2,}/', ' ', $html);
});

/** @var mysqli $conn */

if (!isset($conn)) {
    die("Variabel \$conn tidak ditemukan. Cek file config/database.php");
}

/*
|--------------------------------------------------------------------------
| Filter: siswa, level, kelas, urutan rekap
|--------------------------------------------------------------------------
*/
$daftar_kelas = [];
$res = mysqli_query($conn, "SELECT DISTINCT kelas FROM siswa WHERE kelas IS NOT NULL AND kelas <> '' ORDER BY kelas");
while ($row = mysqli_fetch_assoc($res)) {
    $daftar_kelas[] = $row['kelas'];
}

$filter_siswa = isset($_GET['id_siswa']) ? intval($_GET['id_siswa']) : 0;
$filter_level = isset($_GET['level']) ? intval($_GET['level']) : 0;
$filter_kelas = (isset($_GET['kelas']) && in_array($_GET['kelas'], $daftar_kelas, true)) ? $_GET['kelas'] : '';
$urut = (isset($_GET['urut']) && $_GET['urut'] === 'perhatian') ? 'perhatian' : 'nama';

/** Alamat halaman ini dengan filter tertentu. */
function urlNilai($idSiswa, $level, $kelas, $urut = 'nama', $pesan = '')
{
    $param = [];
    if ($idSiswa > 0) $param['id_siswa'] = $idSiswa;
    if ($level > 0) $param['level'] = $level;
    if ($kelas !== '') $param['kelas'] = $kelas;
    if ($urut !== 'nama') $param['urut'] = $urut;
    if ($pesan !== '') $param['pesan'] = $pesan;

    return 'nilai.php' . ($param ? '?' . http_build_query($param) : '');
}

/*
|--------------------------------------------------------------------------
| Aksi POST (token CSRF dicek di cek_login.php)
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $k_siswa = intval($_POST['filter_siswa'] ?? 0);
    $k_level = intval($_POST['filter_level'] ?? 0);
    $k_kelas = in_array($_POST['filter_kelas'] ?? '', $daftar_kelas, true) ? $_POST['filter_kelas'] : '';
    $k_urut = ($_POST['filter_urut'] ?? '') === 'perhatian' ? 'perhatian' : 'nama';

    // Hapus satu baris nilai level.
    if (isset($_POST['hapus_nilai'])) {
        $id_nilai = intval($_POST['hapus_nilai']);

        if ($id_nilai > 0) {
            $stmt = mysqli_prepare($conn, "DELETE FROM nilai_quiz WHERE id_nilai = ?");
            mysqli_stmt_bind_param($stmt, "i", $id_nilai);
            mysqli_stmt_execute($stmt);
        }

        header("Location: " . urlNilai($k_siswa, $k_level, $k_kelas, $k_urut));
        exit;
    }

    // Reset SEMUA data quiz satu siswa: nilai, hasil trap, riwayat jawaban, dan P(L).
    // Akun siswa tetap ada; P(L) mulai lagi dari P(L0) saat siswa bermain lagi.
    if (isset($_POST['reset_siswa'])) {
        $id_reset = intval($_POST['reset_siswa']);
        $pesan = 'gagal';

        if ($id_reset > 0) {
            mysqli_begin_transaction($conn);
            $ok = true;

            foreach (['nilai_quiz', 'hasil_trap', 'jawaban_siswa', 'bkt_siswa'] as $tabel) {
                $stmt = mysqli_prepare($conn, "DELETE FROM $tabel WHERE id_siswa = ?");
                $ok = $ok && $stmt && mysqli_stmt_bind_param($stmt, "i", $id_reset) && mysqli_stmt_execute($stmt);
            }

            if ($ok) {
                mysqli_commit($conn);
                $pesan = 'reset';
            } else {
                mysqli_rollback($conn);
            }
        }

        if (($_POST['kembali'] ?? '') === 'detail') {
            header("Location: detail_siswa.php?id=" . $id_reset . "&pesan=" . $pesan);
        } else {
            header("Location: " . urlNilai($k_siswa, $k_level, $k_kelas, $k_urut, $pesan));
        }
        exit;
    }
}

$pesan = $_GET['pesan'] ?? '';

/*
|--------------------------------------------------------------------------
| Data dasar
|--------------------------------------------------------------------------
*/
$daftar_level = ambilLevel($conn);
$skill_tampil = ambilSkill($conn, $filter_level);

$semua_siswa = mysqli_fetch_all(
    mysqli_query($conn, "SELECT id_siswa, nama_siswa, nis, kelas FROM siswa ORDER BY nama_siswa ASC"),
    MYSQLI_ASSOC
);

$nama_siswa_filter = '';
foreach ($semua_siswa as $row) {
    if (intval($row['id_siswa']) === $filter_siswa) {
        $nama_siswa_filter = $row['nama_siswa'];
    }
}

/*
|--------------------------------------------------------------------------
| WHERE per tabel (angka sudah di-intval, kelas sudah di-whitelist)
|--------------------------------------------------------------------------
*/
$syarat_siswa = [];
if ($filter_siswa > 0) {
    $syarat_siswa[] = "s.id_siswa = " . $filter_siswa;
}
if ($filter_kelas !== '') {
    $syarat_siswa[] = "s.kelas = '" . mysqli_real_escape_string($conn, $filter_kelas) . "'";
}

$lvl = $filter_level > 0 ? [$filter_level] : [];
$where_siswa = gabungWhere($syarat_siswa);
$where_nilai = gabungWhere($syarat_siswa, $lvl ? ["nq.level_game = " . $filter_level] : []);
$where_trap = gabungWhere($syarat_siswa, $lvl ? ["ht.level_game = " . $filter_level] : []);
$where_jawab = gabungWhere($syarat_siswa, $lvl ? ["sk.level_game = " . $filter_level] : []);

/*
|--------------------------------------------------------------------------
| Statistik nilai level
|--------------------------------------------------------------------------
*/
$stat = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT
        COUNT(*) AS total_data,
        COALESCE(AVG(nq.skor), 0) AS rata_nilai,
        COALESCE(MAX(nq.skor), 0) AS nilai_tertinggi,
        COALESCE(SUM(nq.status = 'Game Over'), 0) AS total_game_over
    FROM nilai_quiz nq
    JOIN siswa s ON nq.id_siswa = s.id_siswa
    $where_nilai
"));

$total_data = intval($stat['total_data'] ?? 0);
$rata_nilai = round(floatval($stat['rata_nilai'] ?? 0), 1);
$nilai_tertinggi = intval($stat['nilai_tertinggi'] ?? 0);
$total_game_over = intval($stat['total_game_over'] ?? 0);

/*
|--------------------------------------------------------------------------
| ANALISIS SOAL: soal paling sering dijawab salah
|--------------------------------------------------------------------------
*/
$min_dijawab = 3;
$soal_sulit = mysqli_fetch_all(mysqli_query($conn, "
    SELECT
        so.id_soal, so.pertanyaan, so.pilihan_a, so.pilihan_b, so.pilihan_c, so.pilihan_d,
        so.jawaban_benar, so.tingkat,
        MIN(sk.nama_skill) AS nama_skill,
        MIN(sk.level_game) AS level_game,
        COUNT(*) AS dijawab,
        SUM(j.benar = 0) AS salah,
        SUM(j.jawaban_siswa = '-') AS timeout,
        SUM(j.benar = 0 AND j.jawaban_siswa = 'A') AS pilih_a,
        SUM(j.benar = 0 AND j.jawaban_siswa = 'B') AS pilih_b,
        SUM(j.benar = 0 AND j.jawaban_siswa = 'C') AS pilih_c,
        SUM(j.benar = 0 AND j.jawaban_siswa = 'D') AS pilih_d
    FROM jawaban_siswa j
    JOIN soal so ON so.id_soal = j.id_soal
    JOIN skill sk ON sk.id_skill = j.id_skill
    JOIN siswa s ON s.id_siswa = j.id_siswa
    $where_jawab
    GROUP BY so.id_soal
    HAVING dijawab >= $min_dijawab
    ORDER BY (SUM(j.benar = 0) / COUNT(*)) DESC, dijawab DESC
    LIMIT 15
"), MYSQLI_ASSOC);

/*
|--------------------------------------------------------------------------
| Grafik, daftar nilai, riwayat trap, data BKT rinci
|--------------------------------------------------------------------------
*/
$result_grafik = mysqli_query($conn, "
    SELECT s.id_siswa, s.nama_siswa, ROUND(AVG(nq.skor), 1) AS rata_skor
    FROM nilai_quiz nq
    JOIN siswa s ON nq.id_siswa = s.id_siswa
    $where_nilai
    GROUP BY s.id_siswa, s.nama_siswa
    ORDER BY rata_skor DESC
    LIMIT 10
");

$data_nilai = mysqli_fetch_all(mysqli_query($conn, "
    SELECT
        nq.id_nilai, nq.id_siswa, nq.level_game, nq.status, nq.skor, nq.poin_game,
        nq.jumlah_soal, nq.jumlah_benar, nq.jumlah_salah, nq.tanggal,
        s.nama_siswa, s.nis, s.kelas
    FROM nilai_quiz nq
    JOIN siswa s ON nq.id_siswa = s.id_siswa
    $where_nilai
    ORDER BY nq.id_nilai DESC
"), MYSQLI_ASSOC);

$batas_trap = 150;
$data_trap = mysqli_fetch_all(mysqli_query($conn, "
    SELECT ht.*, s.nama_siswa, s.nis, s.kelas, sk.nama_skill
    FROM hasil_trap ht
    JOIN siswa s ON ht.id_siswa = s.id_siswa
    LEFT JOIN skill sk ON ht.id_skill = sk.id_skill
    $where_trap
    ORDER BY ht.id_hasil DESC
    LIMIT $batas_trap
"), MYSQLI_ASSOC);

$total_trap = intval(mysqli_fetch_row(mysqli_query($conn, "
    SELECT COUNT(*) FROM hasil_trap ht JOIN siswa s ON ht.id_siswa = s.id_siswa $where_trap
"))[0]);

/** Input tersembunyi filter aktif (untuk form POST). */
function inputFilter($siswa, $level, $kelas, $urut)
{
    return '<input type="hidden" name="filter_siswa" value="' . e($siswa) . '">' .
        '<input type="hidden" name="filter_level" value="' . e($level) . '">' .
        '<input type="hidden" name="filter_kelas" value="' . e($kelas) . '">' .
        '<input type="hidden" name="filter_urut" value="' . e($urut) . '">';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Nilai Siswa - Health & Sympathy</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="../assets/css/style.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="../assets/css/nilai.css?v=<?= time(); ?>">
</head>
<body class="dashboard-body">

<div class="dashboard-container">

    <aside class="sidebar">
        <div class="brand">
            <small>ADMIN PANEL</small>
            <h2>Health &<br>Sympathy</h2>
        </div>

        <nav>
            <a href="dashboard.php">Dashboard</a>
            <a href="soal.php">Kelola Soal</a>
            <a href="siswa.php">Kelola Siswa</a>
            <a href="nilai.php" class="active">Nilai Siswa</a>
            <a href="pretest.php">Pretest &amp; Posttest</a>
            <a href="../logout.php" class="logout-link">Logout</a>
        </nav>
    </aside>

    <main class="main-content">

        <section class="topbar">
            <div>
                <h1>Nilai Siswa</h1>
                <p>Pantau penguasaan materi siswa berdasarkan Bayesian Knowledge Tracing (BKT).</p>
            </div>

            <div class="user-pill">Halo, <?= e($_SESSION['nama_guru'] ?? 'Guru'); ?></div>
        </section>

        <?php if ($pesan === 'reset'): ?>
            <div class="alert alert-success">Data quiz siswa berhasil dikosongkan.</div>
        <?php elseif ($pesan === 'gagal'): ?>
            <div class="alert alert-danger">Data quiz siswa gagal dikosongkan. Tidak ada data yang berubah.</div>
        <?php endif; ?>

        <nav class="page-tabs">
            <a href="dashboard.php#rekap">&larr; Rekap Penguasaan (Dashboard)</a>
            <a href="#analisis-soal">Analisis Soal</a>
            <a href="#daftar-nilai">Nilai per Level</a>
            <a href="#riwayat-trap">Riwayat Trap</a>
        </nav>
        <section class="stats-grid stats-grid-4">
            <div class="stat-card">
                <p>Total Data Nilai</p>
                <h3><?= e($total_data); ?></h3>
            </div>

            <div class="stat-card">
                <p>Rata-rata Nilai</p>
                <h3><?= e($rata_nilai); ?></h3>
            </div>

            <div class="stat-card">
                <p>Nilai Tertinggi</p>
                <h3><?= e($nilai_tertinggi); ?></h3>
            </div>

            <div class="stat-card">
                <p>Game Over</p>
                <h3><?= e($total_game_over); ?></h3>
            </div>
        </section>
        <section class="nilai-grid">
            <div class="card">
                <h2>Grafik Nilai Siswa</h2>
                <p>Rata-rata nilai (persentase jawaban benar) 10 siswa tertinggi.</p>

                <div class="chart-wrapper">
                    <?php if ($result_grafik && mysqli_num_rows($result_grafik) > 0): ?>
                        <div class="nilai-chart">
                            <?php while ($grafik = mysqli_fetch_assoc($result_grafik)): ?>
                                <?php
                                $rata_skor = floatval($grafik['rata_skor']);
                                $height = max(8, min(100, $rata_skor));
                                ?>
                                <div class="nilai-chart-item">
                                    <div class="nilai-chart-value"><?= e($rata_skor); ?></div>
                                    <div class="nilai-chart-bar" style="height: <?= e($height * 2); ?>px;"></div>
                                    <div class="nilai-chart-name"><?= e($grafik['nama_siswa']); ?></div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">Belum ada data nilai untuk grafik.</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <h2>Filter</h2>
                <p>Berlaku untuk semua bagian di halaman ini. Rekap penguasaan per siswa ada di <a href="dashboard.php#rekap">Dashboard</a>.</p>

                <form method="GET" action="nilai.php" class="filter-form">
                    <label for="kelas">Kelas</label>
                    <select name="kelas" id="kelas">
                        <option value="">Semua Kelas</option>
                        <?php foreach ($daftar_kelas as $k): ?>
                            <option value="<?= e($k); ?>" <?= $filter_kelas === $k ? 'selected' : ''; ?>>Kelas <?= e($k); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label for="level" class="filter-label-gap">Level</label>
                    <select name="level" id="level">
                        <option value="0">Semua Level</option>
                        <?php foreach ($daftar_level as $lv => $materi): ?>
                            <option value="<?= e($lv); ?>" <?= $filter_level == $lv ? 'selected' : ''; ?>>
                                Level <?= e($lv); ?> - <?= e($materi); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="id_siswa" class="filter-label-gap">Siswa</label>
                    <select name="id_siswa" id="id_siswa">
                        <option value="0">Semua Siswa</option>
                        <?php foreach ($semua_siswa as $siswa): ?>
                            <option value="<?= e($siswa['id_siswa']); ?>" <?= $filter_siswa == intval($siswa['id_siswa']) ? 'selected' : ''; ?>>
                                <?= e($siswa['nama_siswa']); ?> (<?= e($siswa['kelas']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <?php if ($urut !== 'nama'): ?>
                        <input type="hidden" name="urut" value="<?= e($urut); ?>">
                    <?php endif; ?>

                    <div class="filter-actions">
                        <button type="submit" class="btn-primary">Terapkan Filter</button>
                        <a href="nilai.php" class="btn-secondary">Reset</a>
                    </div>
                </form>

                <?php if ($filter_siswa > 0 && $nama_siswa_filter !== ''): ?>
                    <div class="reset-siswa">
                        <a class="btn-secondary btn-block" href="detail_siswa.php?id=<?= e($filter_siswa); ?>">
                            Lihat detail <?= e($nama_siswa_filter); ?> &rarr;
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- ============================ ANALISIS SOAL ============================ -->
        <section class="card section-gap" id="analisis-soal">
            <h2>Analisis Soal</h2>
            <p>
                15 soal yang paling sering dijawab salah (minimal dijawab <?= e($min_dijawab); ?> kali).
                "Pengecoh terbanyak" = pilihan salah yang paling sering dipilih siswa.
            </p>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Soal</th>
                            <th>Skill</th>
                            <th>Dijawab</th>
                            <th>Salah</th>
                            <th>Tingkat Kesalahan</th>
                            <th>Kunci</th>
                            <th>Pengecoh Terbanyak</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($soal_sulit): ?>
                            <?php foreach ($soal_sulit as $i => $so): ?>
                                <?php
                                $persenSalah = round($so['salah'] / max(1, $so['dijawab']) * 100);
                                $pengecoh = null;
                                $maks = 0;
                                foreach (['A', 'B', 'C', 'D'] as $h) {
                                    $n = (int) $so['pilih_' . strtolower($h)];
                                    if ($h !== $so['jawaban_benar'] && $n > $maks) {
                                        $maks = $n;
                                        $pengecoh = $h;
                                    }
                                }
                                ?>
                                <tr>
                                    <td><?= e($i + 1); ?></td>
                                    <td class="kolom-soal"><?= e($so['pertanyaan']); ?></td>
                                    <td>
                                        <span class="student-name"><?= e($so['nama_skill']); ?></span>
                                        <span class="student-meta">Level <?= e($so['level_game']); ?> &middot; <?= (int) $so['tingkat'] === 2 ? 'Lanjut' : 'Dasar'; ?></span>
                                    </td>
                                    <td><?= e($so['dijawab']); ?></td>
                                    <td><span class="circle-bad"><?= e($so['salah']); ?></span></td>
                                    <td>
                                        <div class="pknow-wrap">
                                            <span class="pknow-percent"><?= e($persenSalah); ?>%</span>
                                            <div class="pknow-bar-bg">
                                                <div class="pknow-bar-fill bar-salah" style="width: <?= e($persenSalah); ?>%;"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="kolom-pilihan"><?= e(teksJawaban($so['jawaban_benar'], $so)); ?></td>
                                    <td class="kolom-pilihan">
                                        <?php if ($pengecoh): ?>
                                            <?= e(teksJawaban($pengecoh, $so)); ?>
                                            <span class="student-meta">dipilih <?= e($maks); ?>&times;</span>
                                        <?php endif; ?>
                                        <?php if ((int) $so['timeout'] > 0): ?>
                                            <span class="student-meta">waktu habis <?= e($so['timeout']); ?>&times;</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="empty-state">Belum cukup data jawaban.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- ============================ DATA RINCI (bisa dibuka-tutup) ============================ -->
        <details class="card section-gap rinci" id="daftar-nilai">
            <summary>
                <h2>Nilai per Level</h2>
                <span class="badge badge-muted"><?= e(count($data_nilai)); ?> data</span>
            </summary>

            <p>
                Dikirim game saat siswa mencapai garis finish (Selesai) atau nyawa habis (Game Over).
                Nilai = persentase jawaban benar di level itu. Poin game = skor yang dilihat siswa di layar
                (10 per jawaban benar + bonus beruntun, terakumulasi antar level).
            </p>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Siswa</th>
                            <th>Kelas</th>
                            <th>Level</th>
                            <th>Status</th>
                            <th>Nilai</th>
                            <th>Poin Game</th>
                            <th>Jumlah Soal</th>
                            <th>Benar</th>
                            <th>Salah</th>
                            <th>Tanggal</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($data_nilai): ?>
                            <?php foreach ($data_nilai as $i => $nilai): ?>
                                <?php $level = $nilai['level_game']; ?>
                                <tr>
                                    <td><?= e($i + 1); ?></td>
                                    <td>
                                        <?= linkSiswa($nilai['id_siswa'], $nilai['nama_siswa']); ?>
                                        <span class="student-meta">NIS: <?= e($nilai['nis']); ?></span>
                                    </td>
                                    <td><?= e($nilai['kelas']); ?></td>
                                    <td>
                                        <?php if ($level !== null): ?>
                                            <span class="student-name">Level <?= e($level); ?></span>
                                            <span class="student-meta"><?= e($daftar_level[intval($level)] ?? ''); ?></span>
                                        <?php else: ?>
                                            &ndash;
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= $nilai['status'] === 'Game Over' ? 'badge-danger' : 'badge-success'; ?>">
                                            <?= e($nilai['status']); ?>
                                        </span>
                                    </td>
                                    <td><span class="score-badge"><?= e($nilai['skor']); ?></span></td>
                                    <td><?= $nilai['poin_game'] !== null ? e($nilai['poin_game']) : '&ndash;'; ?></td>
                                    <td><?= e($nilai['jumlah_soal']); ?></td>
                                    <td><span class="circle-good"><?= e($nilai['jumlah_benar']); ?></span></td>
                                    <td><span class="circle-bad"><?= e($nilai['jumlah_salah']); ?></span></td>
                                    <td><?= e($nilai['tanggal'] ?? '-'); ?></td>
                                    <td>
                                        <form method="POST" action="nilai.php" class="form-hapus"
                                              onsubmit="return confirm('Yakin ingin menghapus data nilai ini?')">
                                            <?= csrf_field(); ?>
                                            <?= inputFilter($filter_siswa, $filter_level, $filter_kelas, $urut); ?>
                                            <input type="hidden" name="hapus_nilai" value="<?= e($nilai['id_nilai']); ?>">
                                            <button type="submit" class="delete-btn">Hapus</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="12" class="empty-state">Belum ada data nilai siswa.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </details>

        <details class="card section-gap rinci" id="riwayat-trap">
            <summary>
                <h2>Riwayat Hasil Trap</h2>
                <span class="badge badge-muted"><?= e($total_trap); ?> trap</span>
            </summary>

            <p>
                Satu baris per trap yang selesai dimainkan. Trap 1-2 = skill pertama (dasar, lanjut),
                trap 3-4 = skill kedua (dasar, lanjut), trap 5 = remedial (skill dengan P-Know terendah).
                Hasil trap memakai P-Know akhir dan nilai trap (% benar): Menguasai jika P-Know &ge; <?= e(BKT_MASTERY); ?>
                dan nilai &ge; <?= e(NILAI_MENGUASAI); ?>, Cukup jika P-Know &ge; <?= e(BKT_CUKUP); ?> dan nilai &ge; <?= e(NILAI_CUKUP); ?>.
                Nyawa berkurang hanya jika hasil trap Belum Menguasai.
            </p>

            <?php if ($total_trap > $batas_trap): ?>
                <div class="alert alert-success">
                    Menampilkan <?= e($batas_trap); ?> hasil trap terbaru dari <?= e($total_trap); ?>.
                    Pilih siswa atau level di Filter untuk melihat semuanya.
                </div>
            <?php endif; ?>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Siswa</th>
                            <th>Level</th>
                            <th>Trap</th>
                            <th>Skill</th>
                            <th>Soal</th>
                            <th>Benar</th>
                            <th>P-Know Awal &rarr; Akhir</th>
                            <th>Hasil</th>
                            <th>Nyawa</th>
                            <th>Waktu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($data_trap): ?>
                            <?php foreach ($data_trap as $i => $trap): ?>
                                <?php $pAwal = floatval($trap['p_awal']); $pAkhir = floatval($trap['p_akhir']); ?>
                                <tr>
                                    <td><?= e($i + 1); ?></td>
                                    <td>
                                        <?= linkSiswa($trap['id_siswa'], $trap['nama_siswa']); ?>
                                        <span class="student-meta">Kelas <?= e($trap['kelas']); ?></span>
                                    </td>
                                    <td>Level <?= e($trap['level_game']); ?></td>
                                    <td>
                                        Trap <?= e($trap['nomor_trap']); ?>
                                        <?php if (intval($trap['remedial']) === 1): ?>
                                            <span class="badge badge-primary">Remedial</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e($trap['nama_skill'] ?? ('Skill ' . $trap['id_skill'])); ?></td>
                                    <td><?= e($trap['jumlah_soal']); ?></td>
                                    <td><span class="circle-good"><?= e($trap['jumlah_benar']); ?></span></td>
                                    <td>
                                        <?= e(number_format($pAwal, 4)); ?>
                                        <span class="pknow-arrow <?= $pAkhir >= $pAwal ? 'naik' : 'turun'; ?>">&rarr;</span>
                                        <strong><?= e(number_format($pAkhir, 4)); ?></strong>
                                    </td>
                                    <td><span class="badge <?= e(badgeKategoriClass($trap['kategori'])); ?>"><?= e($trap['kategori']); ?></span></td>
                                    <td>
                                        <?php if (intval($trap['nyawa_berkurang']) === 1): ?>
                                            <span class="badge badge-danger">-1</span>
                                        <?php else: ?>
                                            <span class="badge badge-muted">Tetap</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e($trap['waktu']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="11" class="empty-state">Belum ada data hasil trap.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </details>

    </main>
</div>

</body>
</html>
