-- =====================================================================
-- Migrasi 2026-11-13 - struktur untuk 6 skill / 3 materi
-- Jalankan SEKALI (lokal & hosting), lalu jalankan bank_soal/seed_bank_soal.sql.
-- =====================================================================

-- Level game (= materi) disimpan di skill. 1 level = 1 materi = 2 skill.
ALTER TABLE skill
  ADD COLUMN level_game TINYINT NOT NULL DEFAULT 1 COMMENT 'Level game tempat skill ini muncul' AFTER nama_skill,
  ADD COLUMN materi VARCHAR(100) NULL COMMENT 'Nama materi' AFTER level_game;

-- Tingkat soal di dalam skill (trap pertama = dasar, trap kedua = lanjut),
-- dan pembahasan yang ditampilkan setelah siswa menjawab.
ALTER TABLE soal
  ADD COLUMN tingkat TINYINT NOT NULL DEFAULT 1 COMMENT '1 = dasar, 2 = lanjut' AFTER level_game,
  ADD COLUMN pembahasan TEXT NULL AFTER jawaban_benar;
