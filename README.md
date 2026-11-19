# Health and Sympathy

Game edukasi Bahasa Inggris kelas 5 MI Islamadina (tema *Health and Sympathy*) berbasis
**Bayesian Knowledge Tracing (BKT)**. Dibuat dengan Unity 6 (6000.2.13f1) untuk Android,
dengan backend PHP + MySQL untuk penyimpanan jawaban, perhitungan BKT, dan dashboard guru.

## Struktur

| Folder | Isi |
|---|---|
| `Assets/` | Proyek Unity: scene `Level 01`–`Level 03`, script di `Assets/Script/` |
| `Assets/Script/Quiz/QuizManager.cs` | Alur kuis per trap, layar pembahasan, aturan berhenti |
| `Assets/Editor/TesQuizOtomatis.cs` | Tes otomatis di Editor (menu Tools > Tes Quiz Otomatis) |
| `backend/api/` | API untuk game: `get_soal`, `submit_jawaban`, `submit_trap`, `submit_nilai`, `login_siswa` |
| `backend/config/bkt.php` | Parameter BKT dan semua aturan trap (satu sumber kebenaran) |
| `backend/guru/` | Dashboard guru: rekap penguasaan, nilai, detail siswa, pretest/posttest |
| `Database/schema.sql` | Struktur tabel (tanpa data) |
| `Database/bank_soal/` | Bank soal: `bank_soal.txt` (sumber) -> `generate.php` -> `seed_bank_soal.sql` |
| `Database/migrasi_*.sql` | Riwayat migrasi database |

## Desain BKT

- 3 level x 5 trap. Level 1 *Ask and Give Information about One's Health*,
  Level 2 *Expressing Sympathy*, Level 3 *Simple Present Tense* (urutan sesuai saran guru).
- Trap 1–2 = skill pertama (dasar, lanjut), trap 3–4 = skill kedua, trap 5 = remedial.
- Parameter: P(L0) = 0,2, P(T) = 0,1, P(G) = 0,25, P(S) = 0,1.
- Satu trap 3–5 soal. **Menguasai** = P(L) >= 0,95 dan nilai trap >= 80;
  **Cukup** = P(L) >= 0,50 dan nilai >= 60 (nyawa tetap); selain itu **Belum** (nyawa -1).

## Menjalankan secara lokal

1. Salin isi `backend/` ke `C:\laragon\www\backend` (atau web root lain).
2. Buat database `Health_and_Sympathy`, lalu impor `Database/schema.sql` dan
   `Database/bank_soal/seed_bank_soal.sql`.
3. Buat akun guru: `php backend/create_guru.php`.
4. Buka folder ini di Unity 6000.2.13f1, jalankan scene `Login`.

Untuk hosting, isi kredensial database di `backend/config/database.local.php`
(tidak ikut ke git), lihat keterangan di `backend/config/database.php`.
