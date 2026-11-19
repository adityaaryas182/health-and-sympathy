<?php
session_start();
require_once '../config/database.php';

/** @var mysqli $conn */

if (isset($_SESSION['id_guru'])) {
    header("Location: dashboard.php");
    exit;
}

$error = "";

if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if ($username == "" || $password == "") {
        $error = "Username dan password wajib diisi.";
    } else {
        $query = "SELECT id_guru, nama_guru, username, password FROM guru WHERE username = ?";

        $stmt = mysqli_prepare($conn, $query);

        if (!$stmt) {
            die("Query error: " . mysqli_error($conn));
        }

        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);

        if ($guru = mysqli_fetch_assoc($result)) {
            if (password_verify($password, $guru['password'])) {
                $_SESSION['id_guru'] = $guru['id_guru'];
                $_SESSION['nama_guru'] = $guru['nama_guru'];
                $_SESSION['username_guru'] = $guru['username'];

                header("Location: dashboard.php");
                exit;
            } else {
                $error = "Username atau password salah.";
            }
        } else {
            $error = "Username atau password salah.";
        }

        mysqli_stmt_close($stmt);
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Login Guru</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="auth-page">

<div class="auth-wrapper">
    <div class="auth-hero">
        <div class="brand-chip">Health and Sympathy</div>
        <h1>Dashboard Guru untuk <span>Health & Sympathy</span></h1>
        <p>
            Kelola soal, data siswa, dan pantau hasil quiz dengan tampilan yang
            lebih nyaman, bersih, dan modern.
        </p>

        <div class="auth-features">
            <span>Kelola Soal</span>
            <span>Kelola Siswa</span>
            <span>Lihat Nilai Quiz</span>
        </div>
    </div>

    <div class="login-box">
        <div class="login-header">
            <div class="login-badge">Guru Access</div>
            <h2>Login Guru</h2>
            <p>Silakan masuk untuk mengakses dashboard admin.</p>
        </div>

        <?php if ($error != "") : ?>
            <div class="error-message">
                <?= htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label>Username</label>
                <input
                    type="text"
                    name="username"
                    placeholder="Masukkan username"
                    required
                >
            </div>

            <div class="form-group">
                <label>Password</label>
                <input
                    type="password"
                    name="password"
                    placeholder="Masukkan password"
                    required
                >
            </div>

            <button type="submit" name="login" class="btn-primary">Login</button>
        </form>
    </div>
</div>

</body>
</html>