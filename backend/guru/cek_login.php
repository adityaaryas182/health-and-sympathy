<?php
session_start();

if (!isset($_SESSION['id_guru'])) {
    header("Location: login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Perlindungan CSRF
|--------------------------------------------------------------------------
| Setiap aksi yang mengubah data (tambah/edit/hapus) wajib lewat POST dan
| menyertakan token sesi ini. Tanpa token, permintaan ditolak, sehingga
| link/halaman lain tidak bisa diam-diam menghapus data saat guru login.
*/
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/** Input tersembunyi berisi token CSRF; taruh di dalam setiap <form method="POST">. */
function csrf_field()
{
    return '<input type="hidden" name="csrf_token" value="' .
        htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') . '">';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        exit("Sesi tidak valid atau sudah kedaluwarsa. Kembali, muat ulang halaman, lalu coba lagi.");
    }
}
