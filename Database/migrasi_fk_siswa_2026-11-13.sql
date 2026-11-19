-- =====================================================================
-- Migrasi 2026-11-13 - Health and Sympathy
-- Hubungkan jawaban_siswa dan bkt_siswa ke tabel siswa (ON DELETE CASCADE),
-- supaya data jawaban & BKT ikut terhapus saat siswa dihapus guru.
-- Jalankan SEKALI, di lokal dan di database hosting.
-- =====================================================================

-- 1. Bersihkan data yatim (siswa sudah tidak ada). Wajib sebelum FK dibuat.
DELETE j FROM jawaban_siswa j
  LEFT JOIN siswa s ON s.id_siswa = j.id_siswa
  WHERE s.id_siswa IS NULL;

DELETE b FROM bkt_siswa b
  LEFT JOIN siswa s ON s.id_siswa = b.id_siswa
  WHERE s.id_siswa IS NULL;

-- 2. Index (id_siswa, id_soal): dipakai FK dan juga mempercepat
--    hitungan "kali_dijawab" di get_soal.php mode adaptif.
ALTER TABLE jawaban_siswa
  ADD INDEX idx_jawaban_siswa_soal (id_siswa, id_soal);

-- 3. Foreign key ke siswa.
ALTER TABLE jawaban_siswa
  ADD CONSTRAINT fk_jawaban_siswa_siswa
  FOREIGN KEY (id_siswa) REFERENCES siswa (id_siswa)
  ON DELETE CASCADE ON UPDATE CASCADE;

-- bkt_siswa sudah punya UNIQUE (id_siswa, id_skill) yang bisa dipakai FK.
ALTER TABLE bkt_siswa
  ADD CONSTRAINT fk_bkt_siswa_siswa
  FOREIGN KEY (id_siswa) REFERENCES siswa (id_siswa)
  ON DELETE CASCADE ON UPDATE CASCADE;
