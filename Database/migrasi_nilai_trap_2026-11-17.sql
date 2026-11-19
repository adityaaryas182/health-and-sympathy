-- Migrasi 2026-11-17: nilai per level + riwayat hasil trap untuk dashboard guru.
-- Jalankan SEKALI di database Health_and_Sympathy (lokal lalu hosting).

-- 1. nilai_quiz: level, status akhir (selesai / game over), poin game, dan
--    kode_kirim supaya kiriman ulang dari game tidak tercatat dua kali.
ALTER TABLE nilai_quiz
    ADD COLUMN level_game TINYINT NULL AFTER id_siswa,
    ADD COLUMN status ENUM('Selesai', 'Game Over') NOT NULL DEFAULT 'Selesai' AFTER level_game,
    ADD COLUMN poin_game INT NULL AFTER skor,
    ADD COLUMN kode_kirim VARCHAR(36) NULL,
    ADD UNIQUE KEY uq_nilai_kode_kirim (kode_kirim),
    ADD KEY idx_nilai_level (level_game);

-- 2. hasil_trap: satu baris per trap yang selesai dimainkan.
--    p_akhir dan kategori ditentukan server dari bkt_siswa (bukan dari game).
CREATE TABLE hasil_trap (
    id_hasil INT NOT NULL AUTO_INCREMENT,
    id_siswa INT NOT NULL,
    level_game TINYINT NOT NULL,
    nomor_trap TINYINT NOT NULL,
    remedial TINYINT(1) NOT NULL DEFAULT 0,
    id_skill INT NOT NULL,
    jumlah_soal INT NOT NULL,
    jumlah_benar INT NOT NULL,
    p_awal FLOAT NOT NULL,
    p_akhir FLOAT NOT NULL,
    kategori ENUM('Menguasai', 'Cukup Menguasai', 'Belum Menguasai') NOT NULL,
    nyawa_berkurang TINYINT(1) NOT NULL DEFAULT 0,
    kode_kirim VARCHAR(36) NULL,
    waktu DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_hasil),
    UNIQUE KEY uq_trap_kode_kirim (kode_kirim),
    KEY idx_trap_siswa_level (id_siswa, level_game),
    CONSTRAINT fk_hasil_trap_siswa FOREIGN KEY (id_siswa)
        REFERENCES siswa (id_siswa) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
