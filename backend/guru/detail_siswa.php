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

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$pesan = $_GET['pesan'] ?? '';

$stmt = mysqli_prepare($conn, "SELECT id_siswa, nama_siswa, nis, kelas FROM siswa WHERE id_siswa = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$siswa = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

/** Jalankan query berparameter id siswa, kembalikan semua baris. */
function ambilSiswa($conn, $sql, $id)
{
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    return mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
}

if ($siswa) {
    $daftar_level = ambilLevel($conn);
    $skill = ambilSkill($conn);

    // P-Know per skill.
    $bkt = [];
    foreach (ambilSiswa($conn, "SELECT id_skill, p_know, jumlah_jawab, jumlah_benar FROM bkt_siswa WHERE id_siswa = ?", $id) as $row) {
        $bkt[(int) $row['id_skill']] = $row;
    }

    // Nilai per level (urut waktu).
    $nilai = ambilSiswa($conn, "
        SELECT level_game, status, skor, poin_game, jumlah_soal, jumlah_benar, jumlah_salah, tanggal
        FROM nilai_quiz WHERE id_siswa = ? ORDER BY id_nilai ASC
    ", $id);

    $progres = [];
    foreach ($daftar_level as $lv => $materi) {
        $progres[$lv] = ['materi' => $materi, 'percobaan' => 0, 'game_over' => 0, 'selesai' => false,
                         'terbaik' => null, 'terakhir' => null, 'tanggal' => null];
    }
    foreach ($nilai as $n) {
        $lv = (int) $n['level_game'];
        if (!isset($progres[$lv])) continue;
        $progres[$lv]['percobaan']++;
        $progres[$lv]['terakhir'] = (int) $n['skor'];
        $progres[$lv]['tanggal'] = $n['tanggal'];
        if ($n['status'] === 'Game Over') {
            $progres[$lv]['game_over']++;
        } else {
            $progres[$lv]['selesai'] = true;
            $progres[$lv]['terbaik'] = max($progres[$lv]['terbaik'] ?? 0, (int) $n['skor']);
        }
    }

    // Riwayat trap (urut waktu) + perjalanan P-Know per skill.
    $trap = ambilSiswa($conn, "
        SELECT ht.*, sk.nama_skill
        FROM hasil_trap ht
        LEFT JOIN skill sk ON sk.id_skill = ht.id_skill
        WHERE ht.id_siswa = ?
        ORDER BY ht.id_hasil ASC
    ", $id);

    $perjalanan = [];
    foreach ($trap as $t) {
        $sk = (int) $t['id_skill'];
        if (!isset($perjalanan[$sk])) {
            $perjalanan[$sk] = [(float) $t['p_awal']];
        }
        $perjalanan[$sk][] = (float) $t['p_akhir'];
    }

    // Soal yang pernah dijawab salah.
    $soal_salah = ambilSiswa($conn, "
        SELECT
            so.id_soal, so.pertanyaan, so.pilihan_a, so.pilihan_b, so.pilihan_c, so.pilihan_d,
            so.jawaban_benar, so.pembahasan, so.tingkat,
            MIN(sk.nama_skill) AS nama_skill,
            MIN(sk.level_game) AS level_game,
            SUM(j.benar = 0) AS salah,
            SUM(j.benar = 1) AS benar,
            GROUP_CONCAT(CASE WHEN j.benar = 0 THEN j.jawaban_siswa END ORDER BY j.id_jawaban SEPARATOR ',') AS jawaban_salah
        FROM jawaban_siswa j
        JOIN soal so ON so.id_soal = j.id_soal
        JOIN skill sk ON sk.id_skill = j.id_skill
        WHERE j.id_siswa = ?
        GROUP BY so.id_soal
        HAVING salah > 0
        ORDER BY (SUM(j.benar = 1) = 0) DESC, salah DESC, MIN(sk.level_game) ASC
    ", $id);

    // Ringkasan.
    $dikuasai = 0;
    $dimainkan = 0;
    foreach ($skill as $idSkill => $sk) {
        if (isset($bkt[$idSkill])) {
            $dimainkan++;
            $b = $bkt[$idSkill];
            if (statusSkill($b['p_know'], $b['jumlah_benar'], $b['jumlah_jawab']) === 'Menguasai') $dikuasai++;
        }
    }
    $level_selesai = count(array_filter($progres, fn($p) => $p['selesai']));
    $total_game_over = array_sum(array_column($progres, 'game_over'));
    $belum_bisa = count(array_filter($soal_salah, fn($s) => (int) $s['benar'] === 0));

    // Pretest & posttest tertulis (halaman Pretest & Posttest).
    $tes = ambilSiswa($conn, "SELECT * FROM tes_siswa WHERE id_siswa = ?", $id)[0] ?? null;
    $tes_pre = $tes ? nilaiTes($tes['pretest_benar'], $tes['pretest_soal']) : null;
    $tes_post = $tes ? nilaiTes($tes['posttest_benar'], $tes['posttest_soal']) : null;
    $tes_gain = hitungNGain($tes_pre, $tes_post);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Detail Siswa - Health & Sympathy</title>
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

        <p class="kembali"><a href="dashboard.php#rekap">&larr; Kembali ke Rekap (Dashboard)</a> &middot; <a href="nilai.php">Nilai Siswa</a></p>

        <?php if (!$siswa): ?>

            <section class="card">
                <h2>Siswa tidak ditemukan</h2>
                <p>Data siswa dengan id ini tidak ada. Mungkin sudah dihapus.</p>
            </section>

        <?php else: ?>

        <section class="topbar">
            <div>
                <h1><?= e($siswa['nama_siswa']); ?></h1>
                <p>NIS <?= e($siswa['nis']); ?> &middot; Kelas <?= e($siswa['kelas']); ?></p>
            </div>
            <div class="user-pill">Halo, <?= e($_SESSION['nama_guru'] ?? 'Guru'); ?></div>
        </section>

        <?php if ($pesan === 'reset'): ?>
            <div class="alert alert-success">Data quiz siswa ini berhasil dikosongkan.</div>
        <?php elseif ($pesan === 'gagal'): ?>
            <div class="alert alert-danger">Data quiz gagal dikosongkan. Tidak ada data yang berubah.</div>
        <?php endif; ?>

        <section class="stats-grid stats-grid-4">
            <div class="stat-card">
                <p>Skill Dikuasai</p>
                <h3><?= e($dikuasai); ?> / <?= e(count($skill)); ?></h3>
            </div>
            <div class="stat-card">
                <p>Level Selesai</p>
                <h3><?= e($level_selesai); ?> / <?= e(count($progres)); ?></h3>
            </div>
            <div class="stat-card">
                <p>Game Over</p>
                <h3><?= e($total_game_over); ?>&times;</h3>
            </div>
            <div class="stat-card">
                <p>Soal Belum Pernah Benar</p>
                <h3><?= e($belum_bisa); ?></h3>
            </div>
        </section>

        <!-- Pretest & posttest -->
        <section class="card section-gap tes-ringkas">
            <div>
                <h2>Pretest &amp; Posttest</h2>
                <p>Tes tertulis di luar game. Diisi guru di halaman <a href="pretest.php">Pretest &amp; Posttest</a>.</p>
            </div>
            <div class="tes-angka">
                <div><span class="label-kecil">Pretest</span><strong><?= e(angka($tes_pre, 1)); ?></strong></div>
                <div class="panah">&rarr;</div>
                <div><span class="label-kecil">Posttest</span><strong><?= e(angka($tes_post, 1)); ?></strong></div>
                <div>
                    <span class="label-kecil">N-Gain</span>
                    <strong><?= e(angka($tes_gain)); ?></strong>
                    <?php if ($tes_gain !== null): ?>
                        <span class="badge <?= e(badgeNGain($tes_gain)); ?>"><?= e(kategoriNGain($tes_gain)); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Progres per level -->
        <section class="card section-gap">
            <h2>Progres per Level</h2>
            <p>Nilai = persentase jawaban benar dalam satu kali bermain di level itu.</p>

            <div class="level-grid">
                <?php foreach ($progres as $lv => $p): ?>
                    <?php
                    if ($p['selesai']) {
                        $kelasKartu = 'level-selesai';
                        $label = 'Selesai';
                    } elseif ($p['percobaan'] > 0) {
                        $kelasKartu = 'level-gagal';
                        $label = 'Belum selesai';
                    } else {
                        $kelasKartu = 'level-kosong';
                        $label = 'Belum dimainkan';
                    }
                    ?>
                    <div class="level-kartu <?= e($kelasKartu); ?>">
                        <div class="level-judul">Level <?= e($lv); ?></div>
                        <div class="level-materi"><?= e($p['materi']); ?></div>
                        <div class="level-status"><?= e($label); ?></div>
                        <?php if ($p['percobaan'] > 0): ?>
                            <dl>
                                <dt>Nilai terbaik</dt><dd><?= $p['terbaik'] !== null ? e($p['terbaik']) : '&ndash;'; ?></dd>
                                <dt>Percobaan</dt><dd><?= e($p['percobaan']); ?>&times;</dd>
                                <dt>Game over</dt><dd><?= e($p['game_over']); ?>&times;</dd>
                                <dt>Terakhir main</dt><dd><?= e($p['tanggal']); ?></dd>
                            </dl>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Penguasaan skill -->
        <section class="card section-gap">
            <h2>Penguasaan Skill</h2>
            <p>
                P-Know terakhir dan nilai (% benar dari seluruh jawaban) tiap skill. Status Menguasai butuh
                P-Know &ge; <?= e(persen(BKT_MASTERY)); ?>% <strong>dan</strong> nilai &ge; <?= e(NILAI_MENGUASAI); ?>;
                Cukup butuh P-Know &ge; <?= e(persen(BKT_CUKUP)); ?>% dan nilai &ge; <?= e(NILAI_CUKUP); ?>.
                "Perjalanan" = P-Know di awal trap pertama, lalu setelah setiap trap
                untuk skill itu (termasuk remedial dan pengulangan level).
            </p>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Skill</th>
                            <th>Level</th>
                            <th>P-Know</th>
                            <th>Status</th>
                            <th>Jawab</th>
                            <th>Benar</th>
                            <th>Perjalanan P-Know (%)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($skill as $idSkill => $sk): ?>
                            <?php $b = $bkt[$idSkill] ?? null; $p = $b ? (float) $b['p_know'] : null; ?>
                            <tr>
                                <td>
                                    <span class="student-name"><?= e($sk['kode']); ?> &middot; <?= e($sk['nama_skill']); ?></span>
                                </td>
                                <td>Level <?= e($sk['level_game']); ?></td>
                                <td>
                                    <?php if ($p !== null): ?>
                                        <div class="pknow-wrap">
                                            <span class="pknow-percent"><?= e(round($p * 100, 2)); ?>%</span>
                                            <div class="pknow-bar-bg">
                                                <div class="pknow-bar-fill <?= e(kelasSel($p)); ?>-bar" style="width: <?= e(min(100, $p * 100)); ?>%;"></div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        &ndash;
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($p !== null): ?>
                                        <?php $status = statusSkill($p, $b['jumlah_benar'], $b['jumlah_jawab']); ?>
                                        <span class="badge <?= e(badgeKategoriClass($status)); ?>"><?= e($status); ?></span>
                                        <span class="student-meta">Nilai <?= e(round((float) nilaiBenar((int) $b['jumlah_benar'], (int) $b['jumlah_jawab']))); ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-muted">Belum dimainkan</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $b ? e($b['jumlah_jawab']) : '&ndash;'; ?></td>
                                <td><?= $b ? '<span class="circle-good">' . e($b['jumlah_benar']) . '</span>' : '&ndash;'; ?></td>
                                <td class="perjalanan">
                                    <?php if (!empty($perjalanan[$idSkill])): ?>
                                        <?php foreach ($perjalanan[$idSkill] as $i => $nilaiP): ?>
                                            <?php if ($i > 0): ?><span class="panah">&rarr;</span><?php endif; ?>
                                            <span class="titik <?= e(kelasSel($nilaiP)); ?>"><?= e(persen($nilaiP)); ?></span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        &ndash;
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Soal yang dijawab salah -->
        <section class="card section-gap">
            <h2>Soal yang Pernah Dijawab Salah</h2>
            <p>
                Bahan untuk menjelaskan ulang. Soal yang <strong>belum pernah dijawab benar</strong> ada di atas.
            </p>

            <?php if ($soal_salah): ?>
                <div class="soal-salah-list">
                    <?php foreach ($soal_salah as $so): ?>
                        <?php
                        $salahUnik = array_unique(array_filter(explode(',', (string) $so['jawaban_salah']), 'strlen'));
                        $sudahBisa = (int) $so['benar'] > 0;
                        ?>
                        <div class="soal-salah <?= $sudahBisa ? 'sudah-bisa' : 'belum-bisa'; ?>">
                            <div class="soal-salah-kepala">
                                <span class="student-meta">
                                    Level <?= e($so['level_game']); ?> &middot; <?= e($so['nama_skill']); ?>
                                    &middot; <?= (int) $so['tingkat'] === 2 ? 'Lanjut' : 'Dasar'; ?>
                                </span>
                                <span class="badge <?= $sudahBisa ? 'badge-success' : 'badge-danger'; ?>">
                                    <?= $sudahBisa ? 'Sudah bisa benar (' . e($so['benar']) . '×)' : 'Belum pernah benar'; ?>
                                </span>
                            </div>
                            <div class="soal-salah-teks"><?= e($so['pertanyaan']); ?></div>
                            <div class="soal-salah-jawab">
                                <div>
                                    <span class="label-kecil">Jawaban siswa (salah <?= e($so['salah']); ?>&times;)</span>
                                    <?php foreach ($salahUnik as $h): ?>
                                        <div class="teks-merah">&#10007; <?= e(teksJawaban($h, $so)); ?></div>
                                    <?php endforeach; ?>
                                </div>
                                <div>
                                    <span class="label-kecil">Kunci</span>
                                    <div class="teks-hijau">&#10003; <?= e(teksJawaban($so['jawaban_benar'], $so)); ?></div>
                                </div>
                            </div>
                            <?php if (trim((string) $so['pembahasan']) !== ''): ?>
                                <div class="soal-salah-pembahasan"><span class="label-kecil">Pembahasan</span><?= e($so['pembahasan']); ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">Belum ada soal yang dijawab salah.</div>
            <?php endif; ?>
        </section>

        <!-- Riwayat trap -->
        <details class="card section-gap rinci">
            <summary>
                <h2>Riwayat Hasil Trap</h2>
                <span class="badge badge-muted"><?= e(count($trap)); ?> trap</span>
            </summary>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>No</th>
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
                        <?php if ($trap): ?>
                            <?php foreach ($trap as $i => $t): ?>
                                <?php $pAwal = (float) $t['p_awal']; $pAkhir = (float) $t['p_akhir']; ?>
                                <tr>
                                    <td><?= e($i + 1); ?></td>
                                    <td>Level <?= e($t['level_game']); ?></td>
                                    <td>
                                        Trap <?= e($t['nomor_trap']); ?>
                                        <?php if ((int) $t['remedial'] === 1): ?>
                                            <span class="badge badge-primary">Remedial</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e($t['nama_skill'] ?? ('Skill ' . $t['id_skill'])); ?></td>
                                    <td><?= e($t['jumlah_soal']); ?></td>
                                    <td><span class="circle-good"><?= e($t['jumlah_benar']); ?></span></td>
                                    <td>
                                        <?= e(number_format($pAwal, 4)); ?>
                                        <span class="pknow-arrow <?= $pAkhir >= $pAwal ? 'naik' : 'turun'; ?>">&rarr;</span>
                                        <strong><?= e(number_format($pAkhir, 4)); ?></strong>
                                    </td>
                                    <td><span class="badge <?= e(badgeKategoriClass($t['kategori'])); ?>"><?= e($t['kategori']); ?></span></td>
                                    <td>
                                        <?php if ((int) $t['nyawa_berkurang'] === 1): ?>
                                            <span class="badge badge-danger">-1</span>
                                        <?php else: ?>
                                            <span class="badge badge-muted">Tetap</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e($t['waktu']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="10" class="empty-state">Belum ada data hasil trap.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </details>

        <!-- Kosongkan data -->
        <section class="card section-gap reset-siswa-card">
            <h2>Kosongkan Data Quiz</h2>
            <p>
                Menghapus SEMUA nilai, hasil trap, riwayat jawaban, dan P-Know milik
                <strong><?= e($siswa['nama_siswa']); ?></strong> di semua level. Akun siswa tetap ada,
                dan P-Know mulai lagi dari awal saat siswa bermain lagi. Nilai pretest &amp; posttest tidak ikut dihapus.
                Tidak bisa dibatalkan.
            </p>
            <form method="POST" action="nilai.php"
                  onsubmit="return confirm('Kosongkan SEMUA data quiz <?= e(addslashes($siswa['nama_siswa'])); ?>? Data tidak bisa dikembalikan.')">
                <?= csrf_field(); ?>
                <input type="hidden" name="reset_siswa" value="<?= e($id); ?>">
                <input type="hidden" name="kembali" value="detail">
                <button type="submit" class="delete-btn">Kosongkan data quiz siswa ini</button>
            </form>
        </section>

        <?php endif; ?>

    </main>
</div>

</body>
</html>
