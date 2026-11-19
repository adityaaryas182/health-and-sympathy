<?php
require_once 'cek_login.php';
require_once '../config/database.php';
require_once '../config/bkt.php';
require_once 'nilai_fungsi.php';

/** @var mysqli $conn */

// Padatkan spasi template supaya halaman ringan (isi teks tidak berubah).
ob_start(function ($html) {
    return preg_replace('/\s{2,}/', ' ', $html);
});

$total_soal = intval(mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM soal"))[0]);
$total_siswa = intval(mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM siswa"))[0]);

/*
|--------------------------------------------------------------------------
| Filter: kelas, level, urutan rekap
|--------------------------------------------------------------------------
*/
$daftar_kelas = [];
$res = mysqli_query($conn, "SELECT DISTINCT kelas FROM siswa WHERE kelas IS NOT NULL AND kelas <> '' ORDER BY kelas");
while ($row = mysqli_fetch_assoc($res)) {
    $daftar_kelas[] = $row['kelas'];
}

$filter_siswa = 0;
$filter_level = isset($_GET['level']) ? intval($_GET['level']) : 0;
$filter_kelas = (isset($_GET['kelas']) && in_array($_GET['kelas'], $daftar_kelas, true)) ? $_GET['kelas'] : '';
$urut = (isset($_GET['urut']) && $_GET['urut'] === 'perhatian') ? 'perhatian' : 'nama';

function urlDashboard($level, $kelas, $urut = 'nama')
{
    $param = [];
    if ($level > 0) $param['level'] = $level;
    if ($kelas !== '') $param['kelas'] = $kelas;
    if ($urut !== 'nama') $param['urut'] = $urut;

    return 'dashboard.php' . ($param ? '?' . http_build_query($param) : '');
}

$daftar_level = ambilLevel($conn);
$skill_tampil = ambilSkill($conn, $filter_level);

$syarat_siswa = [];
if ($filter_kelas !== '') {
    $syarat_siswa[] = "s.kelas = '" . mysqli_real_escape_string($conn, $filter_kelas) . "'";
}
$where_siswa = gabungWhere($syarat_siswa);
$where_nilai = gabungWhere($syarat_siswa, $filter_level > 0 ? ["nq.level_game = " . $filter_level] : []);

/*
|--------------------------------------------------------------------------
| REKAP PENGUASAAN: 1 baris = 1 siswa, kolom = skill
|--------------------------------------------------------------------------
*/
$siswa_rekap = mysqli_fetch_all(mysqli_query($conn, "
    SELECT s.id_siswa, s.nama_siswa, s.nis, s.kelas
    FROM siswa s
    $where_siswa
    ORDER BY s.nama_siswa ASC
"), MYSQLI_ASSOC);

// P(L), nilai, dan kategori per siswa-skill (kategori = P-Know DAN nilai, config/bkt.php).
$peta_bkt = [];
$res = mysqli_query($conn, "
    SELECT b.id_siswa, b.id_skill, b.p_know, b.jumlah_jawab, b.jumlah_benar
    FROM bkt_siswa b
    JOIN siswa s ON b.id_siswa = s.id_siswa
    $where_siswa
");
while ($row = mysqli_fetch_assoc($res)) {
    $p = (float) $row['p_know'];
    $peta_bkt[(int) $row['id_siswa']][(int) $row['id_skill']] = [
        'p' => $p,
        'jawab' => (int) $row['jumlah_jawab'],
        'benar' => (int) $row['jumlah_benar'],
        'nilai' => nilaiBenar((int) $row['jumlah_benar'], (int) $row['jumlah_jawab']),
        'kategori' => statusSkill($p, $row['jumlah_benar'], $row['jumlah_jawab']),
    ];
}

// Ringkasan nilai per siswa-level.
$peta_level = [];
$res = mysqli_query($conn, "
    SELECT nq.id_siswa, nq.level_game,
           SUM(nq.status = 'Selesai') AS selesai,
           SUM(nq.status = 'Game Over') AS game_over
    FROM nilai_quiz nq
    JOIN siswa s ON nq.id_siswa = s.id_siswa
    $where_nilai
    GROUP BY nq.id_siswa, nq.level_game
");
while ($row = mysqli_fetch_assoc($res)) {
    $peta_level[(int) $row['id_siswa']][(int) $row['level_game']] = [
        'selesai' => (int) $row['selesai'],
        'game_over' => (int) $row['game_over'],
    ];
}

$level_tampil = $filter_level > 0 ? [$filter_level => $daftar_level[$filter_level] ?? ''] : $daftar_level;
$jumlah_skill = count($skill_tampil);

$rekap = [];
$footer = [];
foreach ($skill_tampil as $idSkill => $sk) {
    $footer[$idSkill] = ['menguasai' => 0, 'main' => 0, 'total_p' => 0.0, 'total_nilai' => 0.0];
}

foreach ($siswa_rekap as $s) {
    $id = (int) $s['id_siswa'];
    $baris = $s + ['sel' => [], 'dikuasai' => 0, 'dimainkan' => 0, 'p_min' => 1.0,
                   'belum' => [], 'cukup' => [], 'level_selesai' => 0, 'game_over' => 0];

    foreach ($skill_tampil as $idSkill => $sk) {
        $d = $peta_bkt[$id][$idSkill] ?? null;
        $baris['sel'][$idSkill] = $d;

        if ($d === null) {
            continue;
        }

        $p = $d['p'];
        $baris['dimainkan']++;
        $baris['p_min'] = min($baris['p_min'], $p);
        $footer[$idSkill]['main']++;
        $footer[$idSkill]['total_p'] += $p;
        $footer[$idSkill]['total_nilai'] += (float) $d['nilai'];

        $teks = $sk['kode'] . ' ' . $sk['nama_skill'] . ' (nilai ' . round((float) $d['nilai']) . ', ' . $d['benar'] . '/' . $d['jawab'] . ' benar)';

        if ($d['kategori'] === 'Menguasai') {
            $baris['dikuasai']++;
            $footer[$idSkill]['menguasai']++;
        } elseif ($d['kategori'] === 'Cukup Menguasai') {
            $baris['cukup'][] = $teks;
        } else {
            $baris['belum'][] = $teks;
        }
    }

    foreach ($level_tampil as $lv => $materi) {
        $info = $peta_level[$id][$lv] ?? null;
        if ($info) {
            if ($info['selesai'] > 0) $baris['level_selesai']++;
            $baris['game_over'] += $info['game_over'];
        }
    }

    $rekap[] = $baris;
}

if ($urut === 'perhatian') {
    // Yang paling perlu dibantu di atas: skill dikuasai paling sedikit,
    // lalu P-Know terendah, lalu game over terbanyak. Yang belum main di bawah.
    usort($rekap, function ($a, $b) {
        if (($a['dimainkan'] === 0) !== ($b['dimainkan'] === 0)) {
            return $a['dimainkan'] === 0 ? 1 : -1;
        }
        return [$a['dikuasai'], $a['p_min'], -$a['game_over']] <=> [$b['dikuasai'], $b['p_min'], -$b['game_over']];
    });
}

$siswa_main = 0;
$siswa_kuasai_semua = 0;
foreach ($rekap as $r) {
    if ($r['dimainkan'] > 0) $siswa_main++;
    if ($jumlah_skill > 0 && $r['dikuasai'] === $jumlah_skill) $siswa_kuasai_semua++;
}

/*
|--------------------------------------------------------------------------
| PERLU PERHATIAN
|--------------------------------------------------------------------------
| - ada skill Belum Menguasai (kategori P-Know + nilai, config/bkt.php), atau
| - game over 2 kali atau lebih, atau
| - ada skill Cukup Menguasai (belum mencapai mastery).
*/
$perhatian = [];
foreach ($rekap as $r) {
    $prioritas = 0;
    if ($r['belum']) $prioritas += 100 + 10 * count($r['belum']);
    if ($r['game_over'] >= 2) $prioritas += 50 + $r['game_over'];
    if ($r['cukup']) $prioritas += 10 + count($r['cukup']);

    if ($prioritas > 0) {
        $r['prioritas'] = $prioritas;
        $perhatian[] = $r;
    }
}
usort($perhatian, fn($a, $b) => $b['prioritas'] <=> $a['prioritas']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Guru - Health & Sympathy</title>
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
            <a href="dashboard.php" class="active">Dashboard</a>
            <a href="soal.php">Kelola Soal</a>
            <a href="siswa.php">Kelola Siswa</a>
            <a href="nilai.php">Nilai Siswa</a>
            <a href="pretest.php">Pretest &amp; Posttest</a>
            <a href="../logout.php" class="logout-link">Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <section class="topbar">
            <div>
                <h1>Dashboard Guru</h1>
                <p>Ringkasan penguasaan materi siswa berdasarkan Bayesian Knowledge Tracing (BKT).</p>
            </div>

            <div class="user-pill">Halo, <?= e($_SESSION['nama_guru'] ?? 'Guru'); ?></div>
        </section>

        <section class="stats-grid stats-grid-4">
            <div class="stat-card">
                <p>Total Soal</p>
                <h3><?= e($total_soal); ?></h3>
            </div>

            <div class="stat-card">
                <p>Siswa Sudah Bermain</p>
                <h3><?= e($siswa_main); ?> / <?= e(count($rekap)); ?></h3>
            </div>

            <div class="stat-card">
                <p>Menguasai Semua Skill<?= $filter_level > 0 ? ' Level ' . e($filter_level) : ''; ?></p>
                <h3><?= e($siswa_kuasai_semua); ?> siswa</h3>
            </div>

            <div class="stat-card">
                <p>Perlu Perhatian</p>
                <h3><?= e(count($perhatian)); ?> siswa</h3>
            </div>
        </section>

        <section class="card section-gap">
            <form method="GET" action="dashboard.php" class="filter-inline">
                <div>
                    <label for="kelas">Kelas</label>
                    <select name="kelas" id="kelas">
                        <option value="">Semua Kelas</option>
                        <?php foreach ($daftar_kelas as $k): ?>
                            <option value="<?= e($k); ?>" <?= $filter_kelas === $k ? 'selected' : ''; ?>>Kelas <?= e($k); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="level">Level</label>
                    <select name="level" id="level">
                        <option value="0">Semua Level</option>
                        <?php foreach ($daftar_level as $lv => $materi): ?>
                            <option value="<?= e($lv); ?>" <?= $filter_level == $lv ? 'selected' : ''; ?>>
                                Level <?= e($lv); ?> - <?= e($materi); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($urut !== 'nama'): ?>
                    <input type="hidden" name="urut" value="<?= e($urut); ?>">
                <?php endif; ?>

                <div class="filter-inline-aksi">
                    <button type="submit" class="btn-primary">Terapkan</button>
                    <a href="dashboard.php" class="btn-secondary">Reset</a>
                </div>

                <a class="filter-inline-link" href="nilai.php">Analisis soal &amp; nilai lengkap &rarr;</a>
            </form>
        </section>

        <!-- ============================ PERLU PERHATIAN ============================ -->
        <section class="card section-gap" id="perhatian">
            <h2>Perlu Perhatian</h2>
            <p>
                Siswa yang masih punya skill <strong>Belum Menguasai</strong> (P-Know &lt; <?= e(persen(BKT_CUKUP)); ?>% atau nilai &lt; <?= e(NILAI_CUKUP); ?>),
                <strong>game over 2 kali atau lebih</strong>, atau skill yang baru <strong>Cukup Menguasai</strong>.
                Urut dari yang paling perlu dibantu.
            </p>

            <?php if ($perhatian): ?>
                <div class="perhatian-list">
                    <?php foreach ($perhatian as $r): ?>
                        <div class="perhatian-item <?= $r['belum'] ? 'perhatian-merah' : ($r['game_over'] >= 2 ? 'perhatian-oranye' : 'perhatian-kuning'); ?>">
                            <div class="perhatian-nama">
                                <?= linkSiswa($r['id_siswa'], $r['nama_siswa']); ?>
                                <span class="student-meta">Kelas <?= e($r['kelas']); ?> &middot; <?= e($r['dikuasai']); ?>/<?= e($jumlah_skill); ?> skill dikuasai</span>
                            </div>
                            <div class="perhatian-alasan">
                                <?php foreach ($r['belum'] as $t): ?>
                                    <span class="badge badge-danger">Belum: <?= e($t); ?></span>
                                <?php endforeach; ?>
                                <?php if ($r['game_over'] >= 2): ?>
                                    <span class="badge badge-orange">Game over <?= e($r['game_over']); ?>&times;</span>
                                <?php endif; ?>
                                <?php foreach ($r['cukup'] as $t): ?>
                                    <span class="badge badge-warning">Cukup: <?= e($t); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php elseif ($siswa_main > 0): ?>
                <div class="empty-state">Semua siswa sudah menguasai skill yang dimainkan. 🎉</div>
            <?php else: ?>
                <div class="empty-state">Belum ada siswa yang bermain.</div>
            <?php endif; ?>
        </section>

        <!-- ============================ REKAP PENGUASAAN ============================ -->
        <section class="card section-gap" id="rekap">
            <div class="section-header">
                <div>
                    <h2>Rekap Penguasaan</h2>
                    <p>
                        Satu baris per siswa. Angka di sel = nilai skill (% jawaban benar di skill itu);
                        arahkan kursor ke sel untuk melihat P-Know. Warna = status penguasaan
                        (P-Know <strong>dan</strong> nilai). Klik nama untuk melihat detail.
                    </p>
                </div>
                <div class="urut-pilihan">
                    Urutkan:
                    <a class="<?= $urut === 'nama' ? 'aktif' : ''; ?>" href="<?= e(urlDashboard($filter_level, $filter_kelas, 'nama')); ?>#rekap">Nama</a>
                    <a class="<?= $urut === 'perhatian' ? 'aktif' : ''; ?>" href="<?= e(urlDashboard($filter_level, $filter_kelas, 'perhatian')); ?>#rekap">Paling perlu dibantu</a>
                </div>
            </div>

            <div class="legenda">
                <?php $syarat = teksSyaratKategori(); ?>
                <span><i class="sel-menguasai"></i> Menguasai (<?= e($syarat['Menguasai']); ?>)</span>
                <span><i class="sel-cukup"></i> Cukup (<?= e($syarat['Cukup Menguasai']); ?>)</span>
                <span><i class="sel-belum"></i> Belum (<?= e($syarat['Belum Menguasai']); ?>)</span>
                <span><i class="sel-kosong"></i> Belum dimainkan</span>
            </div>

            <div class="table-responsive">
                <table class="rekap-table">
                    <thead>
                        <tr>
                            <th rowspan="2" class="kolom-nama">Siswa</th>
                            <th rowspan="2">Kelas</th>
                            <?php foreach ($level_tampil as $lv => $materi): ?>
                                <?php $n = count(array_filter($skill_tampil, fn($sk) => (int) $sk['level_game'] === $lv)); ?>
                                <?php if ($n > 0): ?>
                                    <th colspan="<?= e($n); ?>" class="kolom-level" title="<?= e($materi); ?>">
                                        Level <?= e($lv); ?><span class="student-meta"><?= e($materi); ?></span>
                                    </th>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <th rowspan="2">Skill<br>Dikuasai</th>
                            <th rowspan="2">Level<br>Selesai</th>
                            <th rowspan="2">Game<br>Over</th>
                        </tr>
                        <tr>
                            <?php foreach ($skill_tampil as $sk): ?>
                                <th class="kolom-skill" title="<?= e($sk['nama_skill']); ?>"><?= e($sk['kode']); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if ($rekap): ?>
                            <?php foreach ($rekap as $r): ?>
                                <tr>
                                    <td class="kolom-nama">
                                        <?= linkSiswa($r['id_siswa'], $r['nama_siswa']); ?>
                                        <span class="student-meta">NIS: <?= e($r['nis']); ?></span>
                                    </td>
                                    <td><?= e($r['kelas']); ?></td>
                                    <?php foreach ($skill_tampil as $idSkill => $sk): ?>
                                        <?php $d = $r['sel'][$idSkill]; ?>
                                        <td class="sel <?= e(kelasKategori($d ? $d['kategori'] : null)); ?>"
                                            title="<?= e($sk['nama_skill'] . ': ' . ($d === null ? 'belum dimainkan'
                                                : $d['kategori'] . ' | nilai ' . round((float) $d['nilai'], 1) . ' (' . $d['benar'] . '/' . $d['jawab'] . ' benar) | P-Know ' . number_format($d['p'], 4))); ?>">
                                            <?= $d === null ? '&ndash;' : e(round((float) $d['nilai'])); ?>
                                        </td>
                                    <?php endforeach; ?>
                                    <td>
                                        <strong class="<?= $jumlah_skill > 0 && $r['dikuasai'] === $jumlah_skill ? 'teks-hijau' : ''; ?>">
                                            <?= e($r['dikuasai']); ?>/<?= e($jumlah_skill); ?>
                                        </strong>
                                    </td>
                                    <td><?= e($r['level_selesai']); ?>/<?= e(count($level_tampil)); ?></td>
                                    <td>
                                        <?php if ($r['game_over'] > 0): ?>
                                            <span class="badge <?= $r['game_over'] >= 2 ? 'badge-orange' : 'badge-muted'; ?>"><?= e($r['game_over']); ?>&times;</span>
                                        <?php else: ?>
                                            &ndash;
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?= e($jumlah_skill + 5); ?>" class="empty-state">Tidak ada siswa untuk filter ini.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>

                    <?php if ($rekap): ?>
                        <tfoot>
                            <tr>
                                <td class="kolom-nama"><strong>Siswa yang menguasai</strong></td>
                                <td></td>
                                <?php foreach ($skill_tampil as $idSkill => $sk): ?>
                                    <?php $f = $footer[$idSkill]; ?>
                                    <td title="<?= e($sk['nama_skill']); ?>">
                                        <strong><?= e($f['menguasai']); ?>/<?= e($f['main']); ?></strong>
                                    </td>
                                <?php endforeach; ?>
                                <td colspan="3"></td>
                            </tr>
                            <tr>
                                <td class="kolom-nama"><strong>Rata-rata P-Know</strong></td>
                                <td></td>
                                <?php foreach ($skill_tampil as $idSkill => $sk): ?>
                                    <?php $f = $footer[$idSkill]; $rata = $f['main'] > 0 ? $f['total_p'] / $f['main'] : null; ?>
                                    <td class="sel <?= e(kelasSel($rata)); ?>"><?= $rata === null ? '&ndash;' : e(persen($rata)); ?></td>
                                <?php endforeach; ?>
                                <td colspan="3"></td>
                            </tr>
                            <tr>
                                <td class="kolom-nama"><strong>Rata-rata nilai</strong></td>
                                <td></td>
                                <?php foreach ($skill_tampil as $idSkill => $sk): ?>
                                    <?php $f = $footer[$idSkill]; ?>
                                    <td><?= $f['main'] > 0 ? e(round($f['total_nilai'] / $f['main'])) : '&ndash;'; ?></td>
                                <?php endforeach; ?>
                                <td colspan="3"></td>
                            </tr>
                        </tfoot>
                    <?php endif; ?>
                </table>
            </div>

            <div class="kode-skill">
                <?php foreach ($skill_tampil as $sk): ?>
                    <span><strong><?= e($sk['kode']); ?></strong> = <?= e($sk['nama_skill']); ?> <em>(Level <?= e($sk['level_game']); ?>)</em></span>
                <?php endforeach; ?>
            </div>
        </section>
    </main>
</div>

</body>
</html>
