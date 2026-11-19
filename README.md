# Health and Sympathy

Game edukasi Bahasa Inggris kelas 5 MI Islamadina (tema *Health and Sympathy*) berbasis
**Bayesian Knowledge Tracing (BKT)**. Dibuat dengan Unity 6 (6000.2.13f1) untuk Android,
dengan backend PHP + MySQL untuk penyimpanan jawaban, perhitungan BKT, dan dashboard guru.

## Struktur

| Folder | Isi |
|---|---|
| `Assets/` | Proyek Unity: scene `Level 01`–`Level 03`, script di `Assets/Script/` |
| `Assets/Script/Quiz/QuizManager.cs` | Alur kuis per trap, layar pembahasan, aturan berhenti |
| `backend/api/` | API untuk game: `get_soal`, `submit_jawaban`, `submit_trap`, `submit_nilai`, `login_siswa` |
| `backend/config/bkt.php` | Parameter BKT dan semua aturan trap (satu sumber kebenaran) |
| `backend/guru/` | Dashboard guru: rekap penguasaan, nilai, detail siswa, pretest/posttest, kelola soal & siswa |
| `Database/schema.sql` | Struktur tabel (tanpa data) |
| `Database/bank_soal/seed_bank_soal.sql` | Isi awal 6 skill dan 72 soal |

## Desain BKT

- 3 level x 5 trap. Level 1 *Ask and Give Information about One's Health*,
  Level 2 *Expressing Sympathy*, Level 3 *Simple Present Tense* (urutan sesuai saran guru).
- Trap 1–2 = skill pertama (dasar, lanjut), trap 3–4 = skill kedua, trap 5 = remedial.
- Parameter: P(L0) = 0,2, P(T) = 0,1, P(G) = 0,25, P(S) = 0,1.
- Satu trap 3–5 soal. **Menguasai** = P(L) >= 0,95 dan nilai trap >= 80;
  **Cukup** = P(L) >= 0,50 dan nilai >= 60 (nyawa tetap); selain itu **Belum** (nyawa -1).

## Clone dan menjalankan di komputer lain (Windows + Laragon)

Folder `Library/`, `Temp/`, `Logs/`, `UserSettings/` dan file `.csproj`/`.sln`
sengaja tidak ada di repo; Unity membuatnya ulang otomatis saat proyek dibuka.

1. **Clone**
   ```
   git clone https://github.com/adityaaryas182/health-and-sympathy.git
   ```
2. **Backend**: salin folder `backend/` ke `C:\laragon\www\backend`, sehingga
   `http://localhost/backend/api/get_soal.php` bisa dibuka. Jalankan Apache dan MySQL di Laragon.
3. **Database**: buat database `Health_and_Sympathy` (utf8mb4) di HeidiSQL/phpMyAdmin, lalu impor
   berurutan:
   1. `Database/schema.sql` (struktur tabel)
   2. `Database/bank_soal/seed_bank_soal.sql` (6 skill + 72 soal)
4. **Akun guru** (dari folder `backend`):
   ```
   php create_guru.php admin PasswordAnda "Nama Guru"
   ```
   Lalu login di `http://localhost/backend/guru/login.php` dan tambahkan data siswa di menu Siswa.
5. **Unity**: Unity Hub > Add > pilih folder hasil clone, buka dengan Unity **6000.2.13f1**.
   Pembukaan pertama agak lama karena `Library/` dibuat ulang. Jalankan dari scene `Login`.

Alamat server (default `http://localhost/backend/api/...`) diatur di Inspector komponen
`LoginSiswa`, `QuizManager` (`Get Soal Url`, `Submit Jawaban Url`), dan `FinishLine`.

Untuk hosting, isi kredensial database di `backend/config/database.local.php`
(tidak ikut ke git), lihat keterangan di `backend/config/database.php`.
