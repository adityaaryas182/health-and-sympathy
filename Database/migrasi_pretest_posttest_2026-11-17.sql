-- Migrasi 2026-11-17: nilai pretest & posttest tertulis per siswa (untuk analisis N-Gain).
-- Jalankan SEKALI di database Health_and_Sympathy (lokal lalu hosting).
-- Nilai = jumlah benar / jumlah soal x 100 (dihitung di halaman guru, tidak disimpan).

CREATE TABLE tes_siswa (
    id_siswa INT NOT NULL,
    pretest_benar INT NULL,
    pretest_soal INT NULL,
    posttest_benar INT NULL,
    posttest_soal INT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_siswa),
    CONSTRAINT fk_tes_siswa_siswa FOREIGN KEY (id_siswa)
        REFERENCES siswa (id_siswa) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT cek_pretest CHECK (pretest_benar IS NULL OR (pretest_soal > 0 AND pretest_benar BETWEEN 0 AND pretest_soal)),
    CONSTRAINT cek_posttest CHECK (posttest_benar IS NULL OR (posttest_soal > 0 AND posttest_benar BETWEEN 0 AND posttest_soal))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
