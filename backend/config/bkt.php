<?php

/*
|--------------------------------------------------------------------------
| Bayesian Knowledge Tracing (Corbett & Anderson, 1995)
|--------------------------------------------------------------------------
| Nilai default di bawah dipakai jika kolom parameter di tabel `skill`
| kosong. Parameter per skill diatur di kolom p_init, p_learn, p_guess,
| p_slip pada tabel `skill`.
*/

define('BKT_DEFAULT_INIT', 0.20);   // P(L0): peluang awal sudah menguasai (materi baru, kisaran pretest)
define('BKT_DEFAULT_LEARN', 0.10);  // P(T) : peluang belajar setelah 1 kesempatan
define('BKT_DEFAULT_GUESS', 0.25);  // P(G) : peluang menebak benar (4 pilihan)
define('BKT_DEFAULT_SLIP', 0.10);   // P(S) : peluang salah walau sudah menguasai

// Ambang penguasaan standar BKT.
define('BKT_MASTERY', 0.95);

// Batas bawah kategori "Cukup Menguasai". Di bawah ini = "Belum Menguasai".
// Dipakai dashboard guru dan aturan nyawa di game.
define('BKT_CUKUP', 0.50);

/*
| Aturan trap (mastery learning):
| - 1 level = 1 materi = 2 skill (urut id_skill pada level tsb).
| - Trap 1 & 2 = skill pertama (tingkat dasar, lalu lanjut),
|   trap 3 & 4 = skill kedua (dasar, lalu lanjut),
|   trap 5     = remedial: skill dengan P(L) terendah di level tsb.
| - Satu trap selesai jika P(L) >= BKT_MASTERY setelah minimal
|   TRAP_MIN_SOAL soal, atau sudah TRAP_MAX_SOAL soal.
| - Kategori penguasaan memakai DUA syarat: P(L) BKT dan nilai (% jawaban benar).
|     Menguasai       : P(L) >= BKT_MASTERY dan nilai >= NILAI_MENGUASAI
|     Cukup Menguasai : P(L) >= BKT_CUKUP   dan nilai >= NILAI_CUKUP
|     Belum Menguasai : selain itu
|   Untuk trap, nilai = benar / soal di trap itu. Untuk rekap skill di dashboard,
|   nilai = benar / jawab seluruh jawaban skill itu.
|   Alasannya: P(L) membawa riwayat sebelumnya, sehingga tanpa syarat nilai
|   siswa yang P(L)-nya 0,999 tetap "Menguasai" walau salah 2 dari 3 soal.
| - Sesi trap berhenti lebih awal jika sudah Menguasai (minimal TRAP_MIN_SOAL
|   soal), atau setelah TRAP_MAX_SOAL soal.
| - Akibat hasil trap: Menguasai / Cukup -> lanjut, nyawa tetap;
|   Belum Menguasai -> nyawa -1.
*/
define('TRAP_MIN_SOAL', 3);
define('TRAP_MAX_SOAL', 5);
define('NILAI_MENGUASAI', 80);
define('NILAI_CUKUP', 60);
define('TRAP_JUMLAH', 5);
define('TRAP_REMEDIAL', 5);

/*
| Urutan pemilihan soal di dalam satu skill (dipakai api/get_soal.php):
|   1. Tingkat sesuai trap (trap 1 & 3 dasar, trap 2 & 4 lanjut; remedial semua tingkat).
|   2. Bukan soal yang barusan dijawab (soal tidak diulang berturut-turut), dan
|      bukan soal yang sudah muncul di sesi trap ini (parameter "kecuali" dari game),
|      selama masih ada soal lain.
|   3. Khusus trap remedial: soal yang jawaban TERAKHIRNYA SALAH didahulukan,
|      supaya siswa bertemu lagi dengan soal yang pernah ia salahi dan bisa
|      memperbaikinya. Soal yang sudah dijawab benar setelahnya tidak termasuk.
|   4. Soal yang belum pernah dijawab.
|   5. Soal yang jawaban terakhirnya salah, lalu soal yang paling lama tidak dijawab.
|   6. Acak.
| Trap 1-4 sengaja mendahulukan soal baru (variasi); pengulangan soal salah
| dipusatkan di trap remedial.
*/
define('REMEDIAL_ULANG_SOAL_SALAH', true);

/**
 * Peta nomor trap -> [indeks skill di level (0 atau 1), tingkat soal].
 * Trap remedial tidak ada di peta ini.
 */
function petaTrap()
{
    return [
        1 => [0, 1],
        2 => [0, 2],
        3 => [1, 1],
        4 => [1, 2],
    ];
}

/** Nilai 0-100 dari jumlah benar / jumlah soal (null jika belum ada jawaban). */
function nilaiBenar($benar, $jumlah)
{
    return $jumlah > 0 ? round($benar * 100 / $jumlah, 2) : null;
}

/**
 * Kategori penguasaan dari P(L) dan nilai (0-100). Dipakai trap, game, dan dashboard.
 */
function kategoriPenguasaan($pKnow, $nilai)
{
    if ($nilai === null) {
        return 'Belum Menguasai';
    }

    if ($pKnow >= BKT_MASTERY && $nilai >= NILAI_MENGUASAI) {
        return 'Menguasai';
    }

    if ($pKnow >= BKT_CUKUP && $nilai >= NILAI_CUKUP) {
        return 'Cukup Menguasai';
    }

    return 'Belum Menguasai';
}

/**
 * Kategori hasil satu trap. $jawaban = daftar benar/salah (bool) di trap itu.
 */
function kategoriTrap($pAkhir, $jawaban)
{
    return kategoriPenguasaan($pAkhir, nilaiBenar(count(array_filter($jawaban)), count($jawaban)));
}

/** Sesi trap boleh berhenti lebih awal jika sudah Menguasai. */
function trapMenguasai($pAkhir, $jawaban)
{
    return kategoriTrap($pAkhir, $jawaban) === 'Menguasai';
}

function bktParamDefault()
{
    return [
        'p_init'  => BKT_DEFAULT_INIT,
        'p_learn' => BKT_DEFAULT_LEARN,
        'p_guess' => BKT_DEFAULT_GUESS,
        'p_slip'  => BKT_DEFAULT_SLIP,
    ];
}

/**
 * Ambil parameter BKT sebuah skill. Kolom kosong diganti nilai default.
 */
function bktParamSkill($conn, $id_skill)
{
    $param = bktParamDefault();

    $stmt = mysqli_prepare(
        $conn,
        "SELECT p_init, p_learn, p_guess, p_slip FROM skill WHERE id_skill = ? LIMIT 1"
    );

    if (!$stmt) {
        return $param;
    }

    mysqli_stmt_bind_param($stmt, "i", $id_skill);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if ($row) {
        foreach ($param as $kolom => $nilai) {
            if ($row[$kolom] !== null) {
                $param[$kolom] = floatval($row[$kolom]);
            }
        }
    }

    return $param;
}

/**
 * Update P(L) setelah satu jawaban (posterior + transisi belajar).
 */
function hitungBKT($pKnow, $isCorrect, $param)
{
    $T = $param['p_learn'];
    $G = $param['p_guess'];
    $S = $param['p_slip'];

    if ($isCorrect) {
        $numerator = $pKnow * (1 - $S);
        $denominator = ($pKnow * (1 - $S)) + ((1 - $pKnow) * $G);
    } else {
        $numerator = $pKnow * $S;
        $denominator = ($pKnow * $S) + ((1 - $pKnow) * (1 - $G));
    }

    $posterior = ($denominator == 0) ? $pKnow : $numerator / $denominator;

    return round($posterior + ((1 - $posterior) * $T), 4);
}
