-- =====================================================================
-- Migrasi 2026-11-13 - sistem trap
-- Jalankan SEKALI (lokal & hosting), setelah migrasi_bank_soal_2026-11-13.sql.
-- =====================================================================

-- Kode unik per jawaban yang dikirim game. Jika game mengirim ulang jawaban
-- yang sama (koneksi putus setelah server menyimpan), jawaban tidak dicatat dua kali.
-- NULL boleh banyak (jawaban lama / dari klien yang tidak mengirim kode).
ALTER TABLE jawaban_siswa
  ADD COLUMN kode_kirim VARCHAR(36) NULL AFTER benar,
  ADD UNIQUE KEY uq_jawaban_kode_kirim (kode_kirim);
