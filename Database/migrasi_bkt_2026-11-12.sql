-- =====================================================================
-- Migrasi perbaikan BKT - Health and Sympathy (2026-11-12)
-- Jalankan SEKALI di database Health_and_Sympathy.
-- Setelah ini, jalankan hitung_ulang_bkt.php agar bkt_siswa dihitung
-- ulang dari riwayat jawaban.
-- =====================================================================

-- 1. Kunci jawaban boleh D (sebelumnya enum hanya A/B/C).
ALTER TABLE soal MODIFY jawaban_benar ENUM('A','B','C','D') NOT NULL;

-- 2. Parameter BKT per skill (default = nilai yang dipakai sebelumnya).
ALTER TABLE skill
  ADD COLUMN p_init  FLOAT NOT NULL DEFAULT 0.50 COMMENT 'P(L0) peluang awal menguasai',
  ADD COLUMN p_learn FLOAT NOT NULL DEFAULT 0.10 COMMENT 'P(T) peluang belajar',
  ADD COLUMN p_guess FLOAT NOT NULL DEFAULT 0.25 COMMENT 'P(G) peluang menebak benar',
  ADD COLUMN p_slip  FLOAT NOT NULL DEFAULT 0.10 COMMENT 'P(S) peluang salah walau menguasai';

-- 3. Default p_know disamakan dengan P(L0) = 0.50 (sebelumnya 0.3).
ALTER TABLE bkt_siswa MODIFY p_know FLOAT NOT NULL DEFAULT 0.50;

-- 4. Perbaiki 5 soal lama: skill yang tertukar, pilihan D placeholder,
--    dan kunci jawaban yang semuanya A.
--    Skill: 1 Kebersihan Tangan, 2 Kebersihan Lingkungan,
--           3 Etika Batuk dan Bersin, 4 Pola Hidup Sehat.
UPDATE soal SET id_skill = 1,
  pilihan_a = 'Membuat tangan menjadi berat',
  pilihan_b = 'Mencegah penyebaran kuman',
  pilihan_c = 'Mengurangi kebutuhan tidur',
  pilihan_d = 'Membuat kuku cepat panjang',
  jawaban_benar = 'B'
WHERE id_soal = 1;

UPDATE soal SET id_skill = 1,
  pilihan_a = 'Membuat tangan menjadi berat',
  pilihan_b = 'Membuat makanan lebih pedas',
  pilihan_c = 'Mencegah kuman masuk ke tubuh',
  pilihan_d = 'Agar makanan cepat habis',
  jawaban_benar = 'C'
WHERE id_soal = 2;

UPDATE soal SET id_skill = 1,
  pilihan_a = 'Hanya saat tangan terlihat bersih',
  pilihan_b = 'Hanya saat mau tidur',
  pilihan_c = 'Cukup seminggu sekali',
  pilihan_d = 'Setelah bermain dan sebelum makan',
  jawaban_benar = 'D'
WHERE id_soal = 3;

UPDATE soal SET id_skill = 2,
  pilihan_a = 'Agar lingkungan bersih dan sehat',
  pilihan_b = 'Agar sampah semakin banyak',
  pilihan_c = 'Agar lantai menjadi kotor',
  pilihan_d = 'Agar lalat semakin banyak',
  jawaban_benar = 'A'
WHERE id_soal = 4;

UPDATE soal SET id_skill = 3,
  pilihan_a = 'Berteriak sekencang mungkin',
  pilihan_b = 'Menutup mulut dan hidung',
  pilihan_c = 'Membuka mulut lebar-lebar',
  pilihan_d = 'Menghadap ke arah teman',
  jawaban_benar = 'B'
WHERE id_soal = 5;

-- 5. Soal baru: total 5 soal per skill (20 soal = jumlah trap di 4 level).
--    Sebaran kunci total: A=5, B=5, C=5, D=5.
INSERT INTO soal (id_skill, level_game, pertanyaan, pilihan_a, pilihan_b, pilihan_c, pilihan_d, jawaban_benar, status) VALUES
-- Skill 1: Kebersihan Tangan
(1, 1, 'Apa yang sebaiknya dipakai saat mencuci tangan?',
  'Sabun dan air mengalir', 'Hanya air di dalam ember', 'Air dan pasir', 'Cukup dilap ke baju', 'A', 'aktif'),
(1, 1, 'Berapa lama sebaiknya kita menggosok tangan dengan sabun?',
  'Sekitar 1 detik', 'Tidak perlu digosok', 'Sekitar 20 detik', 'Sampai sabunnya habis', 'C', 'aktif'),
-- Skill 2: Kebersihan Lingkungan
(2, 1, 'Daun kering dan sisa makanan termasuk jenis sampah apa?',
  'Sampah anorganik', 'Sampah organik', 'Sampah elektronik', 'Sampah logam', 'B', 'aktif'),
(2, 1, 'Apa yang bisa terjadi jika selokan tersumbat sampah?',
  'Air menjadi lebih jernih', 'Udara menjadi lebih segar', 'Bisa menyebabkan banjir dan penyakit', 'Ikan menjadi lebih banyak', 'C', 'aktif'),
(2, 1, 'Gerakan 3M untuk mencegah demam berdarah adalah menguras, menutup, dan ...',
  'Menumpuk barang bekas', 'Membiarkan air tergenang', 'Membuang sampah ke sungai', 'Mendaur ulang barang bekas', 'D', 'aktif'),
(2, 1, 'Bagaimana cara menjaga kebersihan kelas?',
  'Piket membersihkan kelas bersama-sama', 'Membiarkan sampah di laci meja', 'Mencoret-coret dinding kelas', 'Menumpuk sampah di pojok kelas', 'A', 'aktif'),
-- Skill 3: Etika Batuk dan Bersin
(3, 1, 'Jika tidak ada tisu saat bersin, kita menutup mulut dengan ...',
  'Telapak tangan, lalu bersalaman', 'Buku milik teman', 'Tidak perlu ditutup', 'Lengan bagian dalam atau siku', 'D', 'aktif'),
(3, 1, 'Tisu yang sudah dipakai untuk bersin sebaiknya ...',
  'Disimpan di saku', 'Dipakai lagi nanti', 'Dibuang ke tempat sampah', 'Diberikan kepada teman', 'C', 'aktif'),
(3, 1, 'Saat sedang batuk dan pilek, sebaiknya kita memakai ...',
  'Masker', 'Topi', 'Kacamata hitam', 'Sarung tangan', 'A', 'aktif'),
(3, 1, 'Setelah batuk atau bersin ke tangan, apa yang harus kita lakukan?',
  'Langsung makan', 'Mencuci tangan dengan sabun', 'Bersalaman dengan teman', 'Mengusap mata', 'B', 'aktif'),
-- Skill 4: Pola Hidup Sehat
(4, 1, 'Berapa kali sebaiknya kita menggosok gigi dalam sehari?',
  'Tidak perlu menggosok gigi', 'Seminggu sekali', 'Minimal 2 kali, pagi dan sebelum tidur', 'Hanya saat sakit gigi', 'C', 'aktif'),
(4, 1, 'Makanan yang sehat untuk tubuh adalah ...',
  'Sayur dan buah', 'Permen setiap hari', 'Minuman bersoda', 'Gorengan setiap saat', 'A', 'aktif'),
(4, 1, 'Mengapa kita perlu tidur yang cukup?',
  'Agar bisa begadang', 'Agar tubuh segar dan sehat', 'Agar malas belajar', 'Agar cepat lapar', 'B', 'aktif'),
(4, 1, 'Minuman terbaik untuk tubuh setiap hari adalah ...',
  'Minuman bersoda', 'Sirup manis', 'Kopi', 'Air putih', 'D', 'aktif'),
(4, 1, 'Apa manfaat berolahraga secara teratur?',
  'Membuat tubuh lemas', 'Membuat cepat sakit', 'Membuat malas bergerak', 'Menjaga tubuh tetap kuat dan sehat', 'D', 'aktif');

-- 6. Samakan id_skill di riwayat jawaban dengan skill soal yang sudah benar.
UPDATE jawaban_siswa j
JOIN soal s ON s.id_soal = j.id_soal
SET j.id_skill = s.id_skill;
