<?php
require_once '../config/database.php';
require_once '../config/bkt.php';
require_once 'cek_login.php';
require_once 'nilai_fungsi.php';

/** @var mysqli $conn */

/*
|--------------------------------------------------------------------------
| Pretest & Posttest tertulis + N-Gain (Hake, 1998)
|--------------------------------------------------------------------------
| Guru memasukkan JUMLAH BENAR tiap siswa; nilai dan N-Gain dihitung otomatis
| dengan rumus yang sama seperti naskah (nilai_fungsi.php).
*/

$daftar_kelas = [];
$res = mysqli_query($conn, "SELECT DISTINCT kelas FROM siswa WHERE kelas IS NOT NULL AND kelas <> '' ORDER BY kelas");
while ($row = mysqli_fetch_assoc($res)) {
    $daftar_kelas[] = $row['kelas'];
}

$filter_kelas = (isset($_GET['kelas']) && in_array($_GET['kelas'], $daftar_kelas, true)) ? $_GET['kelas'] : '';

function urlTes($kelas, $pesan = '')
{
    $p = [];
    if ($kelas !== '') $p['kelas'] = $kelas;
    if ($pesan !== '') $p['pesan'] = $pesan;
    return 'pretest.php' . ($p ? '?' . http_build_query($p) : '');
}

/*
|--------------------------------------------------------------------------
| Simpan (POST + CSRF dari cek_login.php)
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_tes'])) {
    $kelas_kembali = in_array($_POST['filter_kelas'] ?? '', $daftar_kelas, true) ? $_POST['filter_kelas'] : '';
    $soal_pre = intval($_POST['pretest_soal'] ?? 0);
    $soal_post = intval($_POST['posttest_soal'] ?? 0);

    if ($soal_pre < 1 || $soal_pre > 100 || $soal_post < 1 || $soal_post > 100) {
        header("Location: " . urlTes($kelas_kembali, 'soal'));
        exit;
    }

    $pre = is_array($_POST['pre'] ?? null) ? $_POST['pre'] : [];
    $post = is_array($_POST['post'] ?? null) ? $_POST['post'] : [];
    $ids = array_unique(array_map('intval', array_merge(array_keys($pre), array_keys($post))));

    /** '' -> null; selain itu harus bilangan bulat 0..$maks, kalau tidak -> false. */
    $baca = function ($nilai, $maks) {
        $nilai = trim((string) $nilai);
        if ($nilai === '') return null;
        if (!preg_match('/^\d+$/', $nilai) || (int) $nilai > $maks) return false;
        return (int) $nilai;
    };

    $stmt = mysqli_prepare($conn, "
        INSERT INTO tes_siswa (id_siswa, pretest_benar, pretest_soal, posttest_benar, posttest_soal)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            pretest_benar = VALUES(pretest_benar), pretest_soal = VALUES(pretest_soal),
            posttest_benar = VALUES(posttest_benar), posttest_soal = VALUES(posttest_soal)
    ");

    $salah = 0;
    $tersimpan = 0;
    mysqli_begin_transaction($conn);

    foreach ($ids as $id) {
        if ($id <= 0) continue;

        $b_pre = $baca($pre[$id] ?? '', $soal_pre);
        $b_post = $baca($post[$id] ?? '', $soal_post);

        if ($b_pre === false || $b_post === false) {
            $salah++;
            continue;
        }

        $s_pre = $b_pre === null ? null : $soal_pre;
        $s_post = $b_post === null ? null : $soal_post;

        mysqli_stmt_bind_param($stmt, "iiiii", $id, $b_pre, $s_pre, $b_post, $s_post);
        if (mysqli_stmt_execute($stmt)) {
            $tersimpan++;
        }
    }

    mysqli_commit($conn);

    header("Location: " . urlTes($kelas_kembali, $salah > 0 ? 'sebagian-' . $salah : 'tersimpan'));
    exit;
}

/*
|--------------------------------------------------------------------------
| Data
|--------------------------------------------------------------------------
*/
$where = $filter_kelas !== '' ? "WHERE s.kelas = '" . mysqli_real_escape_string($conn, $filter_kelas) . "'" : "";

$data = mysqli_fetch_all(mysqli_query($conn, "
    SELECT s.id_siswa, s.nama_siswa, s.nis, s.kelas,
           t.pretest_benar, t.pretest_soal, t.posttest_benar, t.posttest_soal
    FROM siswa s
    LEFT JOIN tes_siswa t ON t.id_siswa = s.id_siswa
    $where
    ORDER BY s.nama_siswa ASC
"), MYSQLI_ASSOC);

// Jumlah soal bawaan: yang terakhir dipakai, atau 10 (jumlah soal pretest di naskah).
$soal_default = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT
        (SELECT pretest_soal FROM tes_siswa WHERE pretest_soal IS NOT NULL ORDER BY updated_at DESC LIMIT 1) AS pre,
        (SELECT posttest_soal FROM tes_siswa WHERE posttest_soal IS NOT NULL ORDER BY updated_at DESC LIMIT 1) AS post
"));
$soal_pre_default = $soal_default['pre'] ? (int) $soal_default['pre'] : 10;
$soal_post_default = $soal_default['post'] ? (int) $soal_default['post'] : 10;

// Hitung nilai & N-Gain per siswa + ringkasan.
$ringkas = ['pre' => [], 'post' => [], 'lengkap_pre' => [], 'lengkap_post' => [], 'gain' => [],
            'Tinggi' => 0, 'Sedang' => 0, 'Rendah' => 0, 'acuan_pre' => 0, 'acuan_post' => 0];

foreach ($data as &$d) {
    $d['nilai_pre'] = nilaiTes($d['pretest_benar'], $d['pretest_soal']);
    $d['nilai_post'] = nilaiTes($d['posttest_benar'], $d['posttest_soal']);
    $d['gain'] = hitungNGain($d['nilai_pre'], $d['nilai_post']);

    if ($d['nilai_pre'] !== null) {
        $ringkas['pre'][] = $d['nilai_pre'];
        if ($d['nilai_pre'] >= NILAI_ACUAN) $ringkas['acuan_pre']++;
    }
    if ($d['nilai_post'] !== null) {
        $ringkas['post'][] = $d['nilai_post'];
        if ($d['nilai_post'] >= NILAI_ACUAN) $ringkas['acuan_post']++;
    }
    if ($d['nilai_pre'] !== null && $d['nilai_post'] !== null) {
        $ringkas['lengkap_pre'][] = $d['nilai_pre'];
        $ringkas['lengkap_post'][] = $d['nilai_post'];
    }
    if ($d['gain'] !== null) {
        $ringkas['gain'][] = $d['gain'];
        $ringkas[kategoriNGain($d['gain'])]++;
    }
}
unset($d);

function rata($arr)
{
    return $arr ? array_sum($arr) / count($arr) : null;
}

$jumlah_siswa = count($data);
$jumlah_lengkap = count($ringkas['lengkap_pre']);
$rata_pre = rata($ringkas['pre']);
$rata_post = rata($ringkas['post']);
$rata_gain = rata($ringkas['gain']);
// N-Gain dari rata-rata kelas (Hake): hanya siswa yang punya pretest DAN posttest.
$gain_kelas = hitungNGain(rata($ringkas['lengkap_pre']), rata($ringkas['lengkap_post']));

/*
|--------------------------------------------------------------------------
| Unduh CSV (untuk lampiran / olah data di Excel atau SPSS)
|--------------------------------------------------------------------------
*/
if (($_GET['unduh'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="pretest_posttest' . ($filter_kelas !== '' ? '_' . $filter_kelas : '') . '_' . date('Ymd') . '.csv"');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM supaya Excel membaca UTF-8
    // Pemisah ";" dan desimal koma: cocok untuk Excel berbahasa Indonesia.
    fputcsv($out, ['No', 'NIS', 'Nama Siswa', 'Kelas', 'Pretest Benar', 'Pretest Soal', 'Nilai Pretest',
                   'Posttest Benar', 'Posttest Soal', 'Nilai Posttest', 'N-Gain', 'Kategori N-Gain'], ';');

    foreach ($data as $i => $d) {
        fputcsv($out, [
            $i + 1, $d['nis'], $d['nama_siswa'], $d['kelas'],
            $d['pretest_benar'], $d['pretest_soal'], $d['nilai_pre'] === null ? '' : angka($d['nilai_pre']),
            $d['posttest_benar'], $d['posttest_soal'], $d['nilai_post'] === null ? '' : angka($d['nilai_post']),
            $d['gain'] === null ? '' : angka($d['gain']), kategoriNGain($d['gain']),
        ], ';');
    }

    fclose($out);
    exit;
}

// Padatkan spasi template supaya halaman ringan (isi teks tidak berubah).
ob_start(function ($html) {
    return preg_replace('/\s{2,}/', ' ', $html);
});

$pesan = $_GET['pesan'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Pretest & Posttest - Health & Sympathy</title>
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
            <a href="nilai.php">Nilai Siswa</a>
            <a href="pretest.php" class="active">Pretest &amp; Posttest</a>
            <a href="../logout.php" class="logout-link">Logout</a>
        </nav>
    </aside>

    <main class="main-content">

        <section class="topbar">
            <div>
                <h1>Pretest &amp; Posttest</h1>
                <p>Masukkan jumlah jawaban benar tes tertulis; nilai dan N-Gain dihitung otomatis.</p>
            </div>
            <div class="user-pill">Halo, <?= e($_SESSION['nama_guru'] ?? 'Guru'); ?></div>
        </section>

        <?php if ($pesan === 'tersimpan'): ?>
            <div class="alert alert-success">Nilai pretest dan posttest berhasil disimpan.</div>
        <?php elseif (strpos($pesan, 'sebagian-') === 0): ?>
            <div class="alert alert-danger">
                <?= e(intval(substr($pesan, 9))); ?> siswa TIDAK disimpan karena isiannya bukan angka bulat
                atau melebihi jumlah soal. Isian siswa tersebut kembali ke nilai sebelumnya; periksa dan isi ulang.
            </div>
        <?php elseif ($pesan === 'soal'): ?>
            <div class="alert alert-danger">Jumlah soal harus antara 1 dan 100. Tidak ada data yang disimpan.</div>
        <?php endif; ?>

        <section class="stats-grid stats-grid-4">
            <div class="stat-card">
                <p>Data Lengkap (Pre &amp; Post)</p>
                <h3><?= e($jumlah_lengkap); ?> / <?= e($jumlah_siswa); ?> siswa</h3>
            </div>
            <div class="stat-card">
                <p>Rata-rata Pretest &rarr; Posttest</p>
                <h3><?= e(angka($rata_pre, 1)); ?> &rarr; <?= e(angka($rata_post, 1)); ?></h3>
            </div>
            <div class="stat-card">
                <p>Rata-rata N-Gain</p>
                <h3>
                    <?= e(angka($rata_gain)); ?>
                    <?php if ($rata_gain !== null): ?>
                        <small class="stat-sub">(<?= e(kategoriNGain($rata_gain)); ?>)</small>
                    <?php endif; ?>
                </h3>
            </div>
            <div class="stat-card">
                <p>Mencapai Nilai Acuan <?= e(NILAI_ACUAN); ?></p>
                <h3><?= e($ringkas['acuan_pre']); ?> &rarr; <?= e($ringkas['acuan_post']); ?> siswa</h3>
            </div>
        </section>

        <section class="card section-gap">
            <div class="section-header">
                <div>
                    <h2>Rumus</h2>
                    <p>
                        Nilai = jumlah benar &divide; jumlah soal &times; 100.
                        N-Gain = (posttest &minus; pretest) &divide; (100 &minus; pretest) (Hake, 1998).
                        Kategori: <span class="badge badge-success">Tinggi &ge; 0,7</span>
                        <span class="badge badge-warning">Sedang 0,3&ndash;&lt;0,7</span>
                        <span class="badge badge-danger">Rendah &lt; 0,3</span>
                    </p>
                    <p class="student-meta">
                        Sebaran N-Gain: Tinggi <?= e($ringkas['Tinggi']); ?> siswa, Sedang <?= e($ringkas['Sedang']); ?> siswa,
                        Rendah <?= e($ringkas['Rendah']); ?> siswa.
                        <?php if ($gain_kelas !== null): ?>
                            N-Gain dari rata-rata kelas (siswa dengan data lengkap) = <?= e(angka($gain_kelas)); ?> (<?= e(kategoriNGain($gain_kelas)); ?>).
                        <?php endif; ?>
                        Siswa dengan pretest 100 tidak punya N-Gain (pembagi nol).
                    </p>
                </div>
            </div>

            <form method="GET" action="pretest.php" class="filter-inline">
                <div>
                    <label for="kelas">Kelas</label>
                    <select name="kelas" id="kelas" onchange="this.form.submit()">
                        <option value="">Semua Kelas</option>
                        <?php foreach ($daftar_kelas as $k): ?>
                            <option value="<?= e($k); ?>" <?= $filter_kelas === $k ? 'selected' : ''; ?>>Kelas <?= e($k); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <a class="filter-inline-link" href="<?= e(urlTes($filter_kelas)) . ($filter_kelas !== '' ? '&' : '?'); ?>unduh=csv">Unduh CSV (Excel) &darr;</a>
            </form>
        </section>

        <form method="POST" action="pretest.php" class="card section-gap" id="form-tes">
            <?= csrf_field(); ?>
            <input type="hidden" name="simpan_tes" value="1">
            <input type="hidden" name="filter_kelas" value="<?= e($filter_kelas); ?>">

            <div class="section-header">
                <div>
                    <h2>Input Jumlah Benar</h2>
                    <p>Kosongkan jika siswa belum mengikuti tes. Nilai &amp; N-Gain di tabel langsung berubah saat diketik.</p>
                </div>
                <button type="submit" class="btn-primary tombol-simpan">Simpan</button>
            </div>

            <div class="filter-inline jumlah-soal">
                <div>
                    <label for="pretest_soal">Jumlah soal pretest</label>
                    <input type="number" min="1" max="100" name="pretest_soal" id="pretest_soal" value="<?= e($soal_pre_default); ?>" required>
                </div>
                <div>
                    <label for="posttest_soal">Jumlah soal posttest</label>
                    <input type="number" min="1" max="100" name="posttest_soal" id="posttest_soal" value="<?= e($soal_post_default); ?>" required>
                </div>
            </div>

            <div class="table-responsive">
                <table class="tes-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Siswa</th>
                            <th>Kelas</th>
                            <th>Pretest<br><small>benar</small></th>
                            <th>Nilai<br>Pretest</th>
                            <th>Posttest<br><small>benar</small></th>
                            <th>Nilai<br>Posttest</th>
                            <th>N-Gain</th>
                            <th>Kategori</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($data): ?>
                            <?php foreach ($data as $i => $d): ?>
                                <tr data-baris>
                                    <td><?= e($i + 1); ?></td>
                                    <td>
                                        <?= linkSiswa($d['id_siswa'], $d['nama_siswa']); ?>
                                        <span class="student-meta">NIS: <?= e($d['nis']); ?></span>
                                    </td>
                                    <td><?= e($d['kelas']); ?></td>
                                    <td>
                                        <input type="number" min="0" class="input-tes" data-jenis="pre"
                                               name="pre[<?= e($d['id_siswa']); ?>]" value="<?= e($d['pretest_benar'] ?? ''); ?>">
                                    </td>
                                    <td class="nilai-pre"><?= e(angka($d['nilai_pre'], 1)); ?></td>
                                    <td>
                                        <input type="number" min="0" class="input-tes" data-jenis="post"
                                               name="post[<?= e($d['id_siswa']); ?>]" value="<?= e($d['posttest_benar'] ?? ''); ?>">
                                    </td>
                                    <td class="nilai-post"><?= e(angka($d['nilai_post'], 1)); ?></td>
                                    <td class="nilai-gain"><strong><?= e(angka($d['gain'])); ?></strong></td>
                                    <td class="kategori-gain">
                                        <?php if ($d['gain'] !== null): ?>
                                            <span class="badge <?= e(badgeNGain($d['gain'])); ?>"><?= e(kategoriNGain($d['gain'])); ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="9" class="empty-state">Tidak ada siswa.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="form-bawah">
                <button type="submit" class="btn-primary tombol-simpan">Simpan</button>
            </div>
        </form>

    </main>
</div>

<script>
// Hitung ulang nilai & N-Gain di tabel saat guru mengetik (rumus sama dengan server).
(function () {
    var soalPre = document.getElementById('pretest_soal');
    var soalPost = document.getElementById('posttest_soal');

    function format(x, d) {
        return x === null ? '–' : x.toFixed(d).replace('.', ',');
    }

    function nilai(input, soal) {
        var v = input.value.trim(), n = parseInt(soal.value, 10);
        input.classList.remove('input-salah');
        if (v === '') return null;
        if (!/^\d+$/.test(v) || !(n > 0) || parseInt(v, 10) > n) {
            input.classList.add('input-salah');
            return null;
        }
        return parseInt(v, 10) / n * 100;
    }

    function hitung(tr) {
        var pre = nilai(tr.querySelector('[data-jenis=pre]'), soalPre);
        var post = nilai(tr.querySelector('[data-jenis=post]'), soalPost);
        var g = (pre === null || post === null || pre >= 100) ? null : Math.round((post - pre) / (100 - pre) * 100) / 100;
        var kat = g === null ? '' : (g >= 0.7 ? 'Tinggi' : (g >= 0.3 ? 'Sedang' : 'Rendah'));
        var kelas = g === null ? '' : (g >= 0.7 ? 'badge-success' : (g >= 0.3 ? 'badge-warning' : 'badge-danger'));

        tr.querySelector('.nilai-pre').textContent = format(pre, 1);
        tr.querySelector('.nilai-post').textContent = format(post, 1);
        tr.querySelector('.nilai-gain').innerHTML = '<strong>' + format(g, 2) + '</strong>';
        tr.querySelector('.kategori-gain').innerHTML = kat ? '<span class="badge ' + kelas + '">' + kat + '</span>' : '';
    }

    var baris = document.querySelectorAll('tr[data-baris]');
    baris.forEach(function (tr) {
        tr.querySelectorAll('.input-tes').forEach(function (inp) {
            inp.addEventListener('input', function () { hitung(tr); });
        });
    });

    [soalPre, soalPost].forEach(function (inp) {
        inp.addEventListener('input', function () { baris.forEach(hitung); });
    });
})();
</script>

</body>
</html>
