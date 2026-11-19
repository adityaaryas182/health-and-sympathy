<?php
require_once '../config/database.php';
require_once 'cek_login.php';

/** @var mysqli $conn */

if (!isset($conn)) {
    die("Variabel \$conn tidak ditemukan. Cek file config/database.php");
}

mysqli_set_charset($conn, "utf8mb4");

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function levelLabel($level)
{
    $level = intval($level);

    if ($level === 1) {
        return "Level 1 / Map 1";
    }

    if ($level === 2) {
        return "Level 2 / Map 2";
    }

    if ($level === 3) {
        return "Level 3 / Map 3";
    }

    return "Level " . $level;
}

$success = "";
$error = "";

$id_edit = 0;
$id_skill = "";
$tingkat = 1;
$pembahasan = "";
$pertanyaan = "";
$pilihan_a = "";
$pilihan_b = "";
$pilihan_c = "";
$pilihan_d = "";
$jawaban_benar = "";
$status = "aktif";

/*
|--------------------------------------------------------------------------
| Hapus soal
|--------------------------------------------------------------------------
*/
// POST + token CSRF (dicek di cek_login.php).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus'])) {
    $id_hapus = intval($_POST['hapus']);

    if ($id_hapus > 0) {
        $stmt = mysqli_prepare($conn, "DELETE FROM soal WHERE id_soal = ?");
        mysqli_stmt_bind_param($stmt, "i", $id_hapus);

        if (mysqli_stmt_execute($stmt)) {
            header("Location: soal.php?success=hapus");
            exit;
        } else {
            $error = "Gagal menghapus soal.";
        }
    }
}

/*
|--------------------------------------------------------------------------
| Pesan sukses dari redirect
|--------------------------------------------------------------------------
*/
if (isset($_GET['success'])) {
    if ($_GET['success'] === "tambah") {
        $success = "Soal berhasil ditambahkan.";
    } elseif ($_GET['success'] === "edit") {
        $success = "Soal berhasil diperbarui.";
    } elseif ($_GET['success'] === "hapus") {
        $success = "Soal berhasil dihapus.";
    }
}

/*
|--------------------------------------------------------------------------
| Ambil data untuk edit
|--------------------------------------------------------------------------
*/
if (isset($_GET['edit'])) {
    $id_edit = intval($_GET['edit']);

    if ($id_edit > 0) {
        $stmt = mysqli_prepare($conn, "SELECT * FROM soal WHERE id_soal = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "i", $id_edit);
        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);

        if ($result && mysqli_num_rows($result) > 0) {
            $data = mysqli_fetch_assoc($result);

            $id_skill = isset($data['id_skill']) ? intval($data['id_skill']) : "";
            $tingkat = isset($data['tingkat']) ? intval($data['tingkat']) : 1;
            $pembahasan = $data['pembahasan'] ?? "";
            $pertanyaan = $data['pertanyaan'] ?? "";
            $pilihan_a = $data['pilihan_a'] ?? "";
            $pilihan_b = $data['pilihan_b'] ?? "";
            $pilihan_c = $data['pilihan_c'] ?? "";
            $pilihan_d = $data['pilihan_d'] ?? "";
            $jawaban_benar = $data['jawaban_benar'] ?? "";
            $status = $data['status'] ?? "aktif";
        } else {
            $error = "Data soal tidak ditemukan.";
            $id_edit = 0;
        }
    }
}

/*
|--------------------------------------------------------------------------
| Simpan tambah/edit soal
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['hapus'])) {
    $id_edit = isset($_POST['id_soal']) ? intval($_POST['id_soal']) : 0;
    $id_skill = isset($_POST['id_skill']) ? intval($_POST['id_skill']) : 0;
    $tingkat = isset($_POST['tingkat']) ? intval($_POST['tingkat']) : 0;
    $pembahasan = isset($_POST['pembahasan']) ? trim($_POST['pembahasan']) : "";

    $pertanyaan = isset($_POST['pertanyaan']) ? trim($_POST['pertanyaan']) : "";
    $pilihan_a = isset($_POST['pilihan_a']) ? trim($_POST['pilihan_a']) : "";
    $pilihan_b = isset($_POST['pilihan_b']) ? trim($_POST['pilihan_b']) : "";
    $pilihan_c = isset($_POST['pilihan_c']) ? trim($_POST['pilihan_c']) : "";
    $pilihan_d = isset($_POST['pilihan_d']) ? trim($_POST['pilihan_d']) : "";
    $jawaban_benar = isset($_POST['jawaban_benar']) ? strtoupper(trim($_POST['jawaban_benar'])) : "";
    $status = isset($_POST['status']) ? strtolower(trim($_POST['status'])) : "aktif";

    $tingkat_valid = [1, 2];
    $jawaban_valid = ['A', 'B', 'C', 'D'];
    $status_valid = ['aktif', 'nonaktif'];

    // Level game soal selalu mengikuti level skill-nya (1 level = 1 materi).
    $level = 0;
    if ($id_skill > 0) {
        $stmt_level = mysqli_prepare($conn, "SELECT level_game FROM skill WHERE id_skill = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt_level, "i", $id_skill);
        mysqli_stmt_execute($stmt_level);
        $row_level = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_level));
        $level = $row_level ? intval($row_level['level_game']) : 0;
    }

    if (
        $id_skill <= 0 ||
        !in_array($tingkat, $tingkat_valid) ||
        $pertanyaan === "" ||
        $pilihan_a === "" ||
        $pilihan_b === "" ||
        $pilihan_c === "" ||
        $pilihan_d === "" ||
        $jawaban_benar === "" ||
        $status === ""
    ) {
        $error = "Semua field wajib diisi.";
    } elseif ($level <= 0) {
        $error = "Skill tidak ditemukan.";
    } elseif (!in_array($jawaban_benar, $jawaban_valid)) {
        $error = "Jawaban benar harus A, B, C, atau D.";
    } elseif (!in_array($status, $status_valid)) {
        $error = "Status tidak valid.";
    } else {
        if ($id_edit > 0) {
            $query = "UPDATE soal 
                SET id_skill = ?,
                    level_game = ?,
                    tingkat = ?,
                    pertanyaan = ?,
                    pilihan_a = ?,
                    pilihan_b = ?,
                    pilihan_c = ?,
                    pilihan_d = ?,
                    jawaban_benar = ?,
                    pembahasan = ?,
                    status = ?
                WHERE id_soal = ?";

            $stmt = mysqli_prepare($conn, $query);

            if (!$stmt) {
                $error = "Query error: " . mysqli_error($conn);
            } else {
                mysqli_stmt_bind_param(
                    $stmt,
                    "iiissssssssi",
                    $id_skill,
                    $level,
                    $tingkat,
                    $pertanyaan,
                    $pilihan_a,
                    $pilihan_b,
                    $pilihan_c,
                    $pilihan_d,
                    $jawaban_benar,
                    $pembahasan,
                    $status,
                    $id_edit
                );

                if (mysqli_stmt_execute($stmt)) {
                    header("Location: soal.php?success=edit");
                    exit;
                } else {
                    $error = "Gagal memperbarui soal: " . mysqli_error($conn);
                }
            }
        } else {
            $query = "INSERT INTO soal 
                (id_skill, level_game, tingkat, pertanyaan, pilihan_a, pilihan_b, pilihan_c, pilihan_d, jawaban_benar, pembahasan, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = mysqli_prepare($conn, $query);

            if (!$stmt) {
                $error = "Query error: " . mysqli_error($conn);
            } else {
                mysqli_stmt_bind_param(
                    $stmt,
                    "iiissssssss",
                    $id_skill,
                    $level,
                    $tingkat,
                    $pertanyaan,
                    $pilihan_a,
                    $pilihan_b,
                    $pilihan_c,
                    $pilihan_d,
                    $jawaban_benar,
                    $pembahasan,
                    $status
                );

                if (mysqli_stmt_execute($stmt)) {
                    header("Location: soal.php?success=tambah");
                    exit;
                } else {
                    $error = "Gagal menambahkan soal: " . mysqli_error($conn);
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Ambil data skill
|--------------------------------------------------------------------------
*/
$query_skill = "SELECT id_skill, nama_skill, level_game FROM skill ORDER BY level_game ASC, id_skill ASC";
$result_skill = mysqli_query($conn, $query_skill);

/*
|--------------------------------------------------------------------------
| Ambil daftar soal
|--------------------------------------------------------------------------
*/
$query_soal = "
    SELECT 
        so.id_soal,
        so.id_skill,
        COALESCE(sk.level_game, so.level_game) AS level_game,
        so.tingkat,
        so.pertanyaan,
        so.pilihan_a,
        so.pilihan_b,
        so.pilihan_c,
        so.pilihan_d,
        so.jawaban_benar,
        so.pembahasan,
        so.status,
        sk.nama_skill
    FROM soal so
    LEFT JOIN skill sk ON so.id_skill = sk.id_skill
    ORDER BY level_game ASC, so.id_skill ASC, so.tingkat ASC, so.id_soal ASC
";

$result_soal = mysqli_query($conn, $query_soal);

/*
|--------------------------------------------------------------------------
| Statistik
|--------------------------------------------------------------------------
*/
$query_stat = "
    SELECT
        COUNT(*) AS total_soal,
        SUM(CASE WHEN status = 'aktif' THEN 1 ELSE 0 END) AS total_aktif,
        SUM(CASE WHEN status = 'nonaktif' THEN 1 ELSE 0 END) AS total_nonaktif
    FROM soal
";

$result_stat = mysqli_query($conn, $query_stat);
$stat = $result_stat ? mysqli_fetch_assoc($result_stat) : null;

$total_soal = $stat ? intval($stat['total_soal']) : 0;
$total_aktif = $stat ? intval($stat['total_aktif']) : 0;
$total_nonaktif = $stat ? intval($stat['total_nonaktif']) : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kelola Soal - Health & Sympathy</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="../assets/css/style.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="../assets/css/soal.css?v=<?= time(); ?>">
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
            <a href="soal.php" class="active">Kelola Soal</a>
            <a href="siswa.php">Kelola Siswa</a>
            <a href="nilai.php">Nilai Siswa</a>
            <a href="pretest.php">Pretest &amp; Posttest</a>
            <a href="../logout.php" class="logout-link">Logout</a>
        </nav>
    </aside>

    <main class="main-content">

        <section class="topbar">
            <div>
                <h1>Kelola Soal</h1>
                <p>Tambah, edit, dan atur soal quiz berdasarkan level game.</p>
            </div>

            <div class="user-pill">Halo, Admin Guru</div>
        </section>

        <?php if ($success !== ""): ?>
            <div class="alert alert-success">
                <?= e($success); ?>
            </div>
        <?php endif; ?>

        <?php if ($error !== ""): ?>
            <div class="alert alert-danger">
                <?= e($error); ?>
            </div>
        <?php endif; ?>

        <section class="stats-grid soal-stats-grid">
            <div class="stat-card">
                <p>Total Soal</p>
                <h3><?= e($total_soal); ?></h3>
            </div>

            <div class="stat-card">
                <p>Soal Aktif</p>
                <h3><?= e($total_aktif); ?></h3>
            </div>

            <div class="stat-card">
                <p>Soal Nonaktif</p>
                <h3><?= e($total_nonaktif); ?></h3>
            </div>
        </section>

        <section class="card soal-form-card">
            <div class="section-heading">
                <div>
                    <h2><?= $id_edit > 0 ? "Edit Soal" : "Tambah Soal"; ?></h2>
                    <p>
                        <?= $id_edit > 0 ? "Perbarui data soal yang dipilih." : "Input soal baru untuk game Unity."; ?>
                    </p>
                </div>

                <?php if ($id_edit > 0): ?>
                    <a href="soal.php" class="btn-secondary">Batal Edit</a>
                <?php endif; ?>
            </div>

            <form method="POST" action="soal.php" class="soal-form">
                <?= csrf_field(); ?>
                <input type="hidden" name="id_soal" value="<?= e($id_edit); ?>">

                <div class="form-grid">
                    <div class="form-group">
                        <label>Skill / Materi</label>
                        <select name="id_skill" required>
                            <option value="">Pilih Skill</option>

                            <?php if ($result_skill && mysqli_num_rows($result_skill) > 0): ?>
                                <?php while ($skill = mysqli_fetch_assoc($result_skill)): ?>
                                    <option 
                                        value="<?= e($skill['id_skill']); ?>"
                                        <?= intval($id_skill) === intval($skill['id_skill']) ? 'selected' : ''; ?>
                                    >
                                        Level <?= e($skill['level_game']); ?> - <?= e($skill['nama_skill']); ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Tingkat Soal</label>
                        <select name="tingkat" required>
                            <option value="1" <?= intval($tingkat) === 1 ? 'selected' : ''; ?>>Dasar (trap 1 &amp; 3)</option>
                            <option value="2" <?= intval($tingkat) === 2 ? 'selected' : ''; ?>>Lanjut (trap 2 &amp; 4)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Jawaban Benar</label>
                        <select name="jawaban_benar" required>
                            <option value="">Pilih Jawaban</option>
                            <option value="A" <?= $jawaban_benar === "A" ? 'selected' : ''; ?>>A</option>
                            <option value="B" <?= $jawaban_benar === "B" ? 'selected' : ''; ?>>B</option>
                            <option value="C" <?= $jawaban_benar === "C" ? 'selected' : ''; ?>>C</option>
                            <option value="D" <?= $jawaban_benar === "D" ? 'selected' : ''; ?>>D</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" required>
                            <option value="aktif" <?= $status === "aktif" ? 'selected' : ''; ?>>Aktif</option>
                            <option value="nonaktif" <?= $status === "nonaktif" ? 'selected' : ''; ?>>Nonaktif</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Pertanyaan</label>
                    <textarea name="pertanyaan" rows="4" placeholder="Masukkan pertanyaan" required><?= e($pertanyaan); ?></textarea>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Pilihan A</label>
                        <input type="text" name="pilihan_a" value="<?= e($pilihan_a); ?>" placeholder="Masukkan pilihan A" required>
                    </div>

                    <div class="form-group">
                        <label>Pilihan B</label>
                        <input type="text" name="pilihan_b" value="<?= e($pilihan_b); ?>" placeholder="Masukkan pilihan B" required>
                    </div>

                    <div class="form-group">
                        <label>Pilihan C</label>
                        <input type="text" name="pilihan_c" value="<?= e($pilihan_c); ?>" placeholder="Masukkan pilihan C" required>
                    </div>

                    <div class="form-group">
                        <label>Pilihan D</label>
                        <input type="text" name="pilihan_d" value="<?= e($pilihan_d); ?>" placeholder="Masukkan pilihan D" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Pembahasan (ditampilkan di game setelah siswa menjawab salah)</label>
                    <textarea name="pembahasan" rows="3" placeholder="Jelaskan mengapa jawaban yang benar itu benar. Jangan menyebut huruf pilihan."><?= e($pembahasan); ?></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary">
                        <?= $id_edit > 0 ? "Update Soal" : "Simpan Soal"; ?>
                    </button>

                    <?php if ($id_edit > 0): ?>
                        <a href="soal.php" class="btn-secondary">Batal</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="card">
            <h2>Daftar Soal</h2>
            <p>Daftar soal yang tersedia untuk setiap level game.</p>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Level</th>
                            <th>Skill</th>
                            <th>Pertanyaan</th>
                            <th>Pilihan</th>
                            <th>Jawaban</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php
                        $no = 1;

                        if ($result_soal && mysqli_num_rows($result_soal) > 0):
                            while ($soal = mysqli_fetch_assoc($result_soal)):
                                $statusSoal = strtolower($soal['status']);
                                ?>
                                <tr>
                                    <td><?= e($no++); ?></td>

                                    <td>
                                    <span class="level-badge level-<?= e($soal['level_game']); ?>">
                                        <?= e(levelLabel($soal['level_game'])); ?>
                                    </span>
                                    <div><small><?= intval($soal['tingkat']) === 2 ? 'Lanjut' : 'Dasar'; ?></small></div>
                                    </td>

                                    <td>
                                        <span class="skill-name">
                                            <?= e($soal['nama_skill'] ?? "Tanpa Skill"); ?>
                                        </span>
                                    </td>

                                    <td class="question-cell">
                                        <?= nl2br(e($soal['pertanyaan'])); ?>
                                        <?php if (!empty($soal['pembahasan'])): ?>
                                            <div><small><em>Pembahasan: <?= e($soal['pembahasan']); ?></em></small></div>
                                        <?php endif; ?>
                                    </td>

                                    <td class="option-cell">
                                        <div><strong>A.</strong> <?= e($soal['pilihan_a']); ?></div>
                                        <div><strong>B.</strong> <?= e($soal['pilihan_b']); ?></div>
                                        <div><strong>C.</strong> <?= e($soal['pilihan_c']); ?></div>
                                        <div><strong>D.</strong> <?= e($soal['pilihan_d']); ?></div>
                                    </td>

                                    <td>
                                        <span class="answer-badge">
                                            <?= e($soal['jawaban_benar']); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?php if ($statusSoal === "aktif"): ?>
                                            <span class="status-badge status-active">Aktif</span>
                                        <?php else: ?>
                                            <span class="status-badge status-inactive">Nonaktif</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <div class="action-group">
                                            <a href="soal.php?edit=<?= e($soal['id_soal']); ?>" class="edit-btn">
                                                Edit
                                            </a>

                                            <form
                                                method="POST"
                                                action="soal.php"
                                                class="form-hapus"
                                                onsubmit="return confirm('Yakin ingin menghapus soal ini? Riwayat jawaban siswa tetap tersimpan. Jika hanya ingin menyembunyikan soal, pilih Edit lalu ubah status menjadi Nonaktif.')"
                                            >
                                                <?= csrf_field(); ?>
                                                <input type="hidden" name="hapus" value="<?= e($soal['id_soal']); ?>">
                                                <button type="submit" class="delete-btn">Hapus</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="empty-state">
                                    Belum ada data soal.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

    </main>
</div>

</body>
</html>