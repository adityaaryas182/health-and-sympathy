<?php

header('Content-Type: application/json; charset=utf-8');

require_once '../config/database.php';

/** @var mysqli $conn */

function sendJson($data)
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Hanya menerima request POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJson([
        'success' => false,
        'message' => 'Permintaan tidak valid.',
        'data' => null
    ]);
}

$username = trim($_POST['username'] ?? '');
$nis      = trim($_POST['nis'] ?? '');

if ($username === '' || $nis === '') {
    sendJson([
        'success' => false,
        'message' => 'Username dan NIS wajib diisi.',
        'data' => null
    ]);
}

/*
 * Login siswa cukup memakai username + NIS (keputusan desain;
 * kolom password siswa tidak dipakai untuk login game).
 */
$stmt = mysqli_prepare(
    $conn,
    "SELECT id_siswa, nis, nama_siswa, username, kelas
     FROM siswa
     WHERE username = ? AND nis = ?
     LIMIT 1"
);

if (!$stmt) {
    error_log("login_siswa: " . mysqli_error($conn));
    sendJson([
        'success' => false,
        'message' => 'Query login gagal dipersiapkan.',
        'data' => null
    ]);
}

mysqli_stmt_bind_param($stmt, 'ss', $username, $nis);
mysqli_stmt_execute($stmt);
$siswa = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$siswa) {
    sendJson([
        'success' => false,
        'message' => 'Username atau NIS tidak ditemukan.',
        'data' => null
    ]);
}

sendJson([
    'success' => true,
    'message' => 'Login berhasil.',
    'data' => [
        'id_siswa'   => (int) $siswa['id_siswa'],
        'nis'        => $siswa['nis'],
        'nama_siswa' => $siswa['nama_siswa'],
        'username'   => $siswa['username'],
        'kelas'      => $siswa['kelas']
    ]
]);
