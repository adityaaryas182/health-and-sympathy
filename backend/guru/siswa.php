<?php
require_once 'cek_login.php';
require_once '../config/database.php';

/** @var mysqli $conn */

$error = "";
$success = "";

$id_edit = "";
$nis = "";
$nama_siswa = "";
$username = "";
$kelas = "";

/* HAPUS SISWA (POST + token CSRF, dicek di cek_login.php) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus'])) {
    $id_siswa = intval($_POST['hapus']);

    $stmt = mysqli_prepare($conn, "DELETE FROM siswa WHERE id_siswa = ?");

    if (!$stmt) {
        die("Query error: " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($stmt, "i", $id_siswa);

    if (mysqli_stmt_execute($stmt)) {
        header("Location: siswa.php?success=hapus");
        exit;
    } else {
        $error = "Gagal menghapus siswa.";
    }

    mysqli_stmt_close($stmt);
}

/* AMBIL DATA UNTUK EDIT */
if (isset($_GET['edit'])) {
    $id_siswa = intval($_GET['edit']);

    $stmt = mysqli_prepare($conn, "SELECT id_siswa, nis, nama_siswa, username, kelas FROM siswa WHERE id_siswa = ?");

    if (!$stmt) {
        die("Query error: " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($stmt, "i", $id_siswa);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    if ($data = mysqli_fetch_assoc($result)) {
        $id_edit = $data['id_siswa'];
        $nis = $data['nis'];
        $nama_siswa = $data['nama_siswa'];
        $username = $data['username'];
        $kelas = $data['kelas'];
    }

    mysqli_stmt_close($stmt);
}

/* TAMBAH / UPDATE SISWA */
if (isset($_POST['simpan'])) {
    $id_edit = isset($_POST['id_siswa']) ? intval($_POST['id_siswa']) : 0;

    $nis = trim($_POST['nis']);
    $nama_siswa = trim($_POST['nama_siswa']);
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $kelas = trim($_POST['kelas']);

    $nis_db = $nis == "" ? null : $nis;

    if ($nama_siswa == "" || $username == "" || $kelas == "") {
        $error = "Nama siswa, username, dan kelas wajib diisi.";
    } elseif ($id_edit == 0 && $password == "") {
        $error = "Password wajib diisi saat menambah siswa baru.";
    } else {
        /* CEK USERNAME DUPLIKAT */
        if ($id_edit > 0) {
            $stmt_check = mysqli_prepare($conn, "SELECT id_siswa FROM siswa WHERE username = ? AND id_siswa != ?");
            mysqli_stmt_bind_param($stmt_check, "si", $username, $id_edit);
        } else {
            $stmt_check = mysqli_prepare($conn, "SELECT id_siswa FROM siswa WHERE username = ?");
            mysqli_stmt_bind_param($stmt_check, "s", $username);
        }

        mysqli_stmt_execute($stmt_check);
        mysqli_stmt_store_result($stmt_check);

        if (mysqli_stmt_num_rows($stmt_check) > 0) {
            $error = "Username sudah digunakan oleh siswa lain.";
        }

        mysqli_stmt_close($stmt_check);

        /* CEK NIS DUPLIKAT JIKA DIISI */
        if ($error == "" && $nis != "") {
            if ($id_edit > 0) {
                $stmt_check_nis = mysqli_prepare($conn, "SELECT id_siswa FROM siswa WHERE nis = ? AND id_siswa != ?");
                mysqli_stmt_bind_param($stmt_check_nis, "si", $nis, $id_edit);
            } else {
                $stmt_check_nis = mysqli_prepare($conn, "SELECT id_siswa FROM siswa WHERE nis = ?");
                mysqli_stmt_bind_param($stmt_check_nis, "s", $nis);
            }

            mysqli_stmt_execute($stmt_check_nis);
            mysqli_stmt_store_result($stmt_check_nis);

            if (mysqli_stmt_num_rows($stmt_check_nis) > 0) {
                $error = "NIS sudah digunakan oleh siswa lain.";
            }

            mysqli_stmt_close($stmt_check_nis);
        }

        if ($error == "") {
            if ($id_edit > 0) {
                if ($password != "") {
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);

                    $query = "UPDATE siswa 
                              SET nis = ?, 
                                  nama_siswa = ?, 
                                  username = ?, 
                                  password = ?, 
                                  kelas = ?
                              WHERE id_siswa = ?";

                    $stmt = mysqli_prepare($conn, $query);

                    if (!$stmt) {
                        die("Query error: " . mysqli_error($conn));
                    }

                    mysqli_stmt_bind_param(
                        $stmt,
                        "sssssi",
                        $nis_db,
                        $nama_siswa,
                        $username,
                        $password_hash,
                        $kelas,
                        $id_edit
                    );
                } else {
                    $query = "UPDATE siswa 
                              SET nis = ?, 
                                  nama_siswa = ?, 
                                  username = ?, 
                                  kelas = ?
                              WHERE id_siswa = ?";

                    $stmt = mysqli_prepare($conn, $query);

                    if (!$stmt) {
                        die("Query error: " . mysqli_error($conn));
                    }

                    mysqli_stmt_bind_param(
                        $stmt,
                        "ssssi",
                        $nis_db,
                        $nama_siswa,
                        $username,
                        $kelas,
                        $id_edit
                    );
                }

                if (mysqli_stmt_execute($stmt)) {
                    header("Location: siswa.php?success=update");
                    exit;
                } else {
                    $error = "Gagal memperbarui data siswa.";
                }

                mysqli_stmt_close($stmt);
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);

                $query = "INSERT INTO siswa 
                          (nis, nama_siswa, username, password, kelas)
                          VALUES (?, ?, ?, ?, ?)";

                $stmt = mysqli_prepare($conn, $query);

                if (!$stmt) {
                    die("Query error: " . mysqli_error($conn));
                }

                mysqli_stmt_bind_param(
                    $stmt,
                    "sssss",
                    $nis_db,
                    $nama_siswa,
                    $username,
                    $password_hash,
                    $kelas
                );

                if (mysqli_stmt_execute($stmt)) {
                    header("Location: siswa.php?success=tambah");
                    exit;
                } else {
                    $error = "Gagal menambahkan siswa.";
                }

                mysqli_stmt_close($stmt);
            }
        }
    }
}

/* PESAN SUCCESS */
if (isset($_GET['success'])) {
    if ($_GET['success'] == "tambah") {
        $success = "Data siswa berhasil ditambahkan.";
    } elseif ($_GET['success'] == "update") {
        $success = "Data siswa berhasil diperbarui.";
    } elseif ($_GET['success'] == "hapus") {
        $success = "Data siswa berhasil dihapus.";
    }
}

/* AMBIL SEMUA SISWA */
$query_siswa = mysqli_query($conn, "SELECT id_siswa, nis, nama_siswa, username, kelas, created_at FROM siswa ORDER BY id_siswa DESC");

if (!$query_siswa) {
    die("Query error: " . mysqli_error($conn));
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kelola Siswa</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="dashboard-body">

<div class="dashboard-container">
    <aside class="sidebar">
        <div class="brand">
            <small>ADMIN PANEL</small>
            <h2>Health & Sympathy</h2>
        </div>

        <nav>
            <a href="dashboard.php">Dashboard</a>
            <a href="soal.php">Kelola Soal</a>
            <a href="siswa.php" class="active">Kelola Siswa</a>
            <a href="nilai.php">Nilai Siswa</a>
            <a href="pretest.php">Pretest &amp; Posttest</a>
            <a href="../logout.php" class="logout-link">Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <div class="topbar">
            <div>
                <h1>Kelola Siswa</h1>
                <p>Tambah, ubah, dan hapus data siswa untuk akses quiz di Unity.</p>
            </div>

            <div class="user-pill">
                Halo, <?= htmlspecialchars($_SESSION['nama_guru']); ?>
            </div>
        </div>

        <?php if ($success != "") : ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <?php if ($error != "") : ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="content-grid">
            <div class="card">
                <h3><?= $id_edit ? "Edit Siswa" : "Tambah Siswa"; ?></h3>

                <form method="POST" action="">
                    <?= csrf_field(); ?>
                    <input type="hidden" name="id_siswa" value="<?= htmlspecialchars($id_edit); ?>">

                    <div class="form-grid">
                        <div class="form-group">
                            <label>NIS</label>
                            <input 
                                type="text" 
                                name="nis" 
                                value="<?= htmlspecialchars($nis ?? ""); ?>" 
                                placeholder="Contoh: 2026001"
                            >
                        </div>

                        <div class="form-group">
                            <label>Nama Siswa</label>
                            <input 
                                type="text" 
                                name="nama_siswa" 
                                value="<?= htmlspecialchars($nama_siswa); ?>" 
                                placeholder="Masukkan nama siswa"
                                required
                            >
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label>Username</label>
                            <input 
                                type="text" 
                                name="username" 
                                value="<?= htmlspecialchars($username); ?>" 
                                placeholder="Username untuk login Unity"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label>Password</label>
                            <input 
                                type="password" 
                                name="password" 
                                placeholder="<?= $id_edit ? "Kosongkan jika tidak diganti" : "Password untuk login Unity"; ?>"
                                <?= $id_edit ? "" : "required"; ?>
                            >

                            <?php if ($id_edit) : ?>
                                <small class="form-help">
                                    Kosongkan password jika tidak ingin mengganti password lama.
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label>Kelas</label>
                            <input 
                                type="text" 
                                name="kelas" 
                                value="<?= htmlspecialchars($kelas); ?>" 
                                placeholder="Contoh: 7A / 8B / 9C"
                                required
                            >
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="simpan" class="btn-primary">
                            <?= $id_edit ? "Update Siswa" : "Simpan Siswa"; ?>
                        </button>

                        <?php if ($id_edit) : ?>
                            <a href="siswa.php" class="btn-secondary">Batal Edit</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="card">
                <h3>Daftar Siswa</h3>

                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>NIS</th>
                                <th>Nama Siswa</th>
                                <th>Username</th>
                                <th>Kelas</th>
                                <th>Tanggal Dibuat</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php
                            $no = 1;
                            if (mysqli_num_rows($query_siswa) > 0) :
                                while ($row = mysqli_fetch_assoc($query_siswa)) :
                            ?>
                                    <tr>
                                        <td><?= $no++; ?></td>

                                        <td>
                                            <?= $row['nis'] ? htmlspecialchars($row['nis']) : "-"; ?>
                                        </td>

                                        <td>
                                            <strong><?= htmlspecialchars($row['nama_siswa']); ?></strong>
                                        </td>

                                        <td>
                                            <span class="badge badge-primary">
                                                <?= htmlspecialchars($row['username']); ?>
                                            </span>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($row['kelas']); ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($row['created_at']); ?>
                                        </td>

                                        <td>
                                            <div class="table-actions">
                                                <a 
                                                    href="siswa.php?edit=<?= $row['id_siswa']; ?>" 
                                                    class="btn-small btn-edit"
                                                >
                                                    Edit
                                                </a>

                                                <form
                                                    method="POST"
                                                    action="siswa.php"
                                                    class="form-hapus"
                                                    onsubmit="return confirm('Yakin ingin menghapus siswa ini? Semua nilai, jawaban, dan data BKT siswa ini ikut terhapus.')"
                                                >
                                                    <?= csrf_field(); ?>
                                                    <input type="hidden" name="hapus" value="<?= intval($row['id_siswa']); ?>">
                                                    <button type="submit" class="btn-small btn-delete">Hapus</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                            <?php
                                endwhile;
                            else :
                            ?>
                                <tr>
                                    <td colspan="7" class="empty-table">
                                        Belum ada data siswa.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

</body>
</html>