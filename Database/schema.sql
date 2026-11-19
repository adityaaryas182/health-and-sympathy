
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bkt_siswa` (
  `id_bkt` int NOT NULL AUTO_INCREMENT,
  `id_siswa` int NOT NULL,
  `id_skill` int NOT NULL,
  `p_know` float NOT NULL DEFAULT '0.2',
  `jumlah_jawab` int NOT NULL DEFAULT '0',
  `jumlah_benar` int NOT NULL DEFAULT '0',
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_bkt`),
  UNIQUE KEY `unique_siswa_skill` (`id_siswa`,`id_skill`),
  CONSTRAINT `fk_bkt_siswa_siswa` FOREIGN KEY (`id_siswa`) REFERENCES `siswa` (`id_siswa`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `guru` (
  `id_guru` int NOT NULL AUTO_INCREMENT,
  `nama_guru` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_guru`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hasil_trap` (
  `id_hasil` int NOT NULL AUTO_INCREMENT,
  `id_siswa` int NOT NULL,
  `level_game` tinyint NOT NULL,
  `nomor_trap` tinyint NOT NULL,
  `remedial` tinyint(1) NOT NULL DEFAULT '0',
  `id_skill` int NOT NULL,
  `jumlah_soal` int NOT NULL,
  `jumlah_benar` int NOT NULL,
  `p_awal` float NOT NULL,
  `p_akhir` float NOT NULL,
  `kategori` enum('Menguasai','Cukup Menguasai','Belum Menguasai') NOT NULL,
  `nyawa_berkurang` tinyint(1) NOT NULL DEFAULT '0',
  `kode_kirim` varchar(36) DEFAULT NULL,
  `waktu` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_hasil`),
  UNIQUE KEY `uq_trap_kode_kirim` (`kode_kirim`),
  KEY `idx_trap_siswa_level` (`id_siswa`,`level_game`),
  CONSTRAINT `fk_hasil_trap_siswa` FOREIGN KEY (`id_siswa`) REFERENCES `siswa` (`id_siswa`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jawaban_siswa` (
  `id_jawaban` int NOT NULL AUTO_INCREMENT,
  `id_siswa` int NOT NULL,
  `id_soal` int NOT NULL,
  `id_skill` int NOT NULL,
  `jawaban_siswa` varchar(5) NOT NULL,
  `jawaban_benar` varchar(5) NOT NULL,
  `benar` tinyint(1) NOT NULL,
  `kode_kirim` varchar(36) DEFAULT NULL,
  `waktu_jawab` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_jawaban`),
  UNIQUE KEY `uq_jawaban_kode_kirim` (`kode_kirim`),
  KEY `idx_jawaban_siswa_soal` (`id_siswa`,`id_soal`),
  CONSTRAINT `fk_jawaban_siswa_siswa` FOREIGN KEY (`id_siswa`) REFERENCES `siswa` (`id_siswa`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nilai_quiz` (
  `id_nilai` int NOT NULL AUTO_INCREMENT,
  `id_siswa` int NOT NULL,
  `level_game` tinyint DEFAULT NULL,
  `status` enum('Selesai','Game Over') NOT NULL DEFAULT 'Selesai',
  `skor` int NOT NULL,
  `poin_game` int DEFAULT NULL,
  `jumlah_soal` int NOT NULL,
  `jumlah_benar` int NOT NULL,
  `jumlah_salah` int NOT NULL,
  `tanggal` datetime DEFAULT CURRENT_TIMESTAMP,
  `kode_kirim` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id_nilai`),
  UNIQUE KEY `uq_nilai_kode_kirim` (`kode_kirim`),
  KEY `id_siswa` (`id_siswa`),
  KEY `idx_nilai_level` (`level_game`),
  CONSTRAINT `nilai_quiz_ibfk_1` FOREIGN KEY (`id_siswa`) REFERENCES `siswa` (`id_siswa`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `siswa` (
  `id_siswa` int NOT NULL AUTO_INCREMENT,
  `nis` varchar(30) DEFAULT NULL,
  `nama_siswa` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `kelas` varchar(50) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_siswa`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `nis` (`nis`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `skill` (
  `id_skill` int NOT NULL AUTO_INCREMENT,
  `nama_skill` varchar(100) NOT NULL,
  `level_game` tinyint NOT NULL DEFAULT '1' COMMENT 'Level game tempat skill ini muncul',
  `materi` varchar(100) DEFAULT NULL COMMENT 'Nama materi',
  `p_init` float NOT NULL DEFAULT '0.5' COMMENT 'P(L0) peluang awal menguasai',
  `p_learn` float NOT NULL DEFAULT '0.1' COMMENT 'P(T) peluang belajar',
  `p_guess` float NOT NULL DEFAULT '0.25' COMMENT 'P(G) peluang menebak benar',
  `p_slip` float NOT NULL DEFAULT '0.1' COMMENT 'P(S) peluang salah walau menguasai',
  PRIMARY KEY (`id_skill`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `soal` (
  `id_soal` int NOT NULL AUTO_INCREMENT,
  `pertanyaan` text NOT NULL,
  `pilihan_a` varchar(255) NOT NULL,
  `pilihan_b` varchar(255) NOT NULL,
  `pilihan_c` varchar(255) NOT NULL,
  `pilihan_d` text,
  `jawaban_benar` enum('A','B','C','D') NOT NULL,
  `pembahasan` text,
  `status` enum('aktif','nonaktif') DEFAULT 'aktif',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `id_skill` int DEFAULT '1',
  `level_game` int NOT NULL DEFAULT '1',
  `tingkat` tinyint NOT NULL DEFAULT '1' COMMENT '1 = dasar, 2 = lanjut',
  PRIMARY KEY (`id_soal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tes_siswa` (
  `id_siswa` int NOT NULL,
  `pretest_benar` int DEFAULT NULL,
  `pretest_soal` int DEFAULT NULL,
  `posttest_benar` int DEFAULT NULL,
  `posttest_soal` int DEFAULT NULL,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_siswa`),
  CONSTRAINT `fk_tes_siswa_siswa` FOREIGN KEY (`id_siswa`) REFERENCES `siswa` (`id_siswa`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `cek_posttest` CHECK (((`posttest_benar` is null) or ((`posttest_soal` > 0) and (`posttest_benar` between 0 and `posttest_soal`)))),
  CONSTRAINT `cek_pretest` CHECK (((`pretest_benar` is null) or ((`pretest_soal` > 0) and (`pretest_benar` between 0 and `pretest_soal`))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

