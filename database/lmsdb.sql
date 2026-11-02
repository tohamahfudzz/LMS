-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 02, 2026 at 03:25 AM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.1.17

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `lmsdb`
--

-- --------------------------------------------------------

--
-- Table structure for table `absensi`
--

CREATE TABLE `absensi` (
  `id_absensi` int(10) UNSIGNED NOT NULL,
  `id_kelas` int(10) UNSIGNED NOT NULL,
  `id_pengajar` int(10) UNSIGNED NOT NULL,
  `tanggal` date NOT NULL,
  `waktu_buka` datetime NOT NULL DEFAULT current_timestamp(),
  `waktu_tutup` datetime DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'terbuka'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `absensi`
--

INSERT INTO `absensi` (`id_absensi`, `id_kelas`, `id_pengajar`, `tanggal`, `waktu_buka`, `waktu_tutup`, `status`) VALUES
(1, 1, 1, '2026-10-16', '2026-10-16 20:01:28', NULL, 'terbuka'),
(2, 1, 1, '2026-10-18', '2026-10-18 19:30:21', '2026-10-18 19:57:58', 'tertutup');

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `id_admin` int(10) UNSIGNED NOT NULL,
  `kode_admin` varchar(20) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`id_admin`, `kode_admin`, `nama`, `password`, `email`) VALUES
(1, 'ADM001', 'Administrator LMS', '$2y$12$LKEhpTf5yQY4YJDzw4Yl.EpG/pgAfr9fGe2huuSBbSeJEUddgA/a', NULL),
(2, '7777', 'admin', '$2y$10$mrXsZkzsblWhRCHRP8lAe.8Lf4rIR/AgjZtdliDi/ZTPR92d01Eqy', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `jawaban_kuis`
--

CREATE TABLE `jawaban_kuis` (
  `id_jawaban` int(10) UNSIGNED NOT NULL,
  `id_percobaan` int(10) UNSIGNED NOT NULL,
  `id_soal` int(10) UNSIGNED NOT NULL,
  `jawaban` text DEFAULT NULL,
  `nilai_diperoleh` decimal(6,2) UNSIGNED DEFAULT NULL,
  `status_penilaian` varchar(20) NOT NULL DEFAULT 'menunggu',
  `id_penilai` int(10) UNSIGNED DEFAULT NULL,
  `waktu_dinilai` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `kehadiran`
--

CREATE TABLE `kehadiran` (
  `id_kehadiran` int(10) UNSIGNED NOT NULL,
  `id_absensi` int(10) UNSIGNED NOT NULL,
  `id_siswa` int(10) UNSIGNED NOT NULL,
  `waktu_absen` datetime NOT NULL DEFAULT current_timestamp(),
  `status` varchar(20) NOT NULL DEFAULT 'hadir'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `kehadiran`
--

INSERT INTO `kehadiran` (`id_kehadiran`, `id_absensi`, `id_siswa`, `waktu_absen`, `status`) VALUES
(1, 1, 1, '2026-10-16 20:06:58', 'hadir');

-- --------------------------------------------------------

--
-- Table structure for table `kelas`
--

CREATE TABLE `kelas` (
  `id_kelas` int(10) UNSIGNED NOT NULL,
  `nama_kelas` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `kelas`
--

INSERT INTO `kelas` (`id_kelas`, `nama_kelas`) VALUES
(1, 'kelas 1');

-- --------------------------------------------------------

--
-- Table structure for table `kelas_pengajar`
--

CREATE TABLE `kelas_pengajar` (
  `id_kelas_pengajar` int(10) UNSIGNED NOT NULL,
  `id_kelas` int(10) UNSIGNED NOT NULL,
  `id_pengajar` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `kelas_pengajar`
--

INSERT INTO `kelas_pengajar` (`id_kelas_pengajar`, `id_kelas`, `id_pengajar`) VALUES
(1, 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `kelas_siswa`
--

CREATE TABLE `kelas_siswa` (
  `id_kelas_siswa` int(10) UNSIGNED NOT NULL,
  `id_kelas` int(10) UNSIGNED NOT NULL,
  `id_siswa` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `kelas_siswa`
--

INSERT INTO `kelas_siswa` (`id_kelas_siswa`, `id_kelas`, `id_siswa`) VALUES
(1, 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `kodetb`
--

CREATE TABLE `kodetb` (
  `id` int(11) NOT NULL,
  `role` enum('admin','pengajar','siswa') NOT NULL,
  `kode` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `kodetb`
--

INSERT INTO `kodetb` (`id`, `role`, `kode`) VALUES
(1, 'admin', '77'),
(2, 'pengajar', '44'),
(3, 'siswa', '33');

-- --------------------------------------------------------

--
-- Table structure for table `kuis`
--

CREATE TABLE `kuis` (
  `id_kuis` int(10) UNSIGNED NOT NULL,
  `id_kelas` int(10) UNSIGNED NOT NULL,
  `id_pengajar` int(10) UNSIGNED NOT NULL,
  `judul` varchar(200) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `waktu_mulai` datetime DEFAULT NULL,
  `waktu_selesai` datetime DEFAULT NULL,
  `durasi_menit` smallint(5) UNSIGNED NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `waktu_dibuat` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `kuis`
--

INSERT INTO `kuis` (`id_kuis`, `id_kelas`, `id_pengajar`, `judul`, `deskripsi`, `waktu_mulai`, `waktu_selesai`, `durasi_menit`, `status`, `waktu_dibuat`) VALUES
(1, 1, 1, 'kuis 1', 'uwxjnjkxsnjnakjn', '2026-10-16 20:04:00', '2026-10-16 21:05:00', 30, 'terbit', '2026-10-16 20:05:24'),
(2, 1, 1, 'gvjvhvhjvh', NULL, '2026-11-01 20:04:00', '2026-11-01 23:04:00', 30, 'draft', '2026-11-01 20:04:53'),
(3, 1, 1, 'besok', NULL, '2026-11-03 08:23:00', '2026-11-05 08:24:00', 30, 'terbit', '2026-11-02 08:24:23');

-- --------------------------------------------------------

--
-- Table structure for table `materi`
--

CREATE TABLE `materi` (
  `id_materi` int(10) UNSIGNED NOT NULL,
  `id_kelas` int(10) UNSIGNED NOT NULL,
  `id_pengajar` int(10) UNSIGNED NOT NULL,
  `judul` varchar(200) NOT NULL,
  `tipe` varchar(30) NOT NULL,
  `file` varchar(255) DEFAULT NULL,
  `link` text DEFAULT NULL,
  `waktu_upload` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `materi`
--

INSERT INTO `materi` (`id_materi`, `id_kelas`, `id_pengajar`, `judul`, `tipe`, `file`, `link`, `waktu_upload`) VALUES
(1, 1, 1, 'materi 1', 'file', 'materi/45868871430c816ca71a59b1530df6a8.pdf', NULL, '2026-10-16 20:03:00');

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(11) NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  `executed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`, `executed_at`) VALUES
(1, '001_tugas_kolom_waktu_mulai.sql', 1, '2026-09-26 03:12:48'),
(2, '002_create_kuis.sql', 2, '2026-10-13 02:11:34'),
(3, '003_create_soal_kuis.sql', 3, '2026-10-13 02:13:03'),
(4, '004_create_percobaan_kuis.sql', 4, '2026-10-13 02:13:07'),
(5, '005_create_jawaban_kuis.sql', 5, '2026-10-13 02:13:09'),
(6, '006_unik_key_percobaan_kuis.sql', 6, '2026-10-13 02:43:50'),
(7, '007_email_diadmin_pengajar_siswa.sql', 7, '2026-10-20 02:39:17'),
(8, '008_create_password_resets.sql', 8, '2026-10-24 12:04:08'),
(9, '009_unique_key_email.sql', 9, '2026-10-24 12:23:03'),
(10, '010_buat_tabel_kode.sql', 10, '2026-11-01 06:37:32');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pengajar`
--

CREATE TABLE `pengajar` (
  `id_pengajar` int(10) UNSIGNED NOT NULL,
  `kode_pengajar` varchar(20) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pengajar`
--

INSERT INTO `pengajar` (`id_pengajar`, `kode_pengajar`, `nama`, `password`, `email`) VALUES
(1, '4444', 'pengajar', '$2y$10$F1cEW2EPQLQekyvneuQ79eAKRwNrzc6a0J5LAsUZa7RsPJ/pT64pC', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `pengumpulan_tugas`
--

CREATE TABLE `pengumpulan_tugas` (
  `id_pengumpulan` int(10) UNSIGNED NOT NULL,
  `id_tugas` int(10) UNSIGNED NOT NULL,
  `id_siswa` int(10) UNSIGNED NOT NULL,
  `file` varchar(255) DEFAULT NULL,
  `teks` text DEFAULT NULL,
  `waktu_upload` datetime NOT NULL DEFAULT current_timestamp(),
  `waktu_pengumpulan` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `percobaan_kuis`
--

CREATE TABLE `percobaan_kuis` (
  `id_percobaan` int(10) UNSIGNED NOT NULL,
  `id_kuis` int(10) UNSIGNED NOT NULL,
  `id_siswa` int(10) UNSIGNED NOT NULL,
  `nomor_percobaan` smallint(5) UNSIGNED NOT NULL DEFAULT 1,
  `waktu_mulai` datetime NOT NULL DEFAULT current_timestamp(),
  `waktu_selesai` datetime DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'berlangsung',
  `nilai_otomatis` decimal(8,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `nilai_manual` decimal(8,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `nilai_total` decimal(8,2) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `siswa`
--

CREATE TABLE `siswa` (
  `id_siswa` int(10) UNSIGNED NOT NULL,
  `kode_siswa` varchar(20) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `siswa`
--

INSERT INTO `siswa` (`id_siswa`, `kode_siswa`, `nama`, `password`, `email`) VALUES
(1, '3333', 'siswa', '$2y$10$BPA7xi3p1AkyqQ9T6gEa6eym4w0T/m57Wael2LDvCK7K8YLh284Fi', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `soal_kuis`
--

CREATE TABLE `soal_kuis` (
  `id_soal` int(10) UNSIGNED NOT NULL,
  `id_kuis` int(10) UNSIGNED NOT NULL,
  `tipe_soal` varchar(20) NOT NULL,
  `soal` text NOT NULL,
  `pilihan_a` text DEFAULT NULL,
  `pilihan_b` text DEFAULT NULL,
  `pilihan_c` text DEFAULT NULL,
  `pilihan_d` text DEFAULT NULL,
  `kunci_jawaban` char(1) DEFAULT NULL,
  `poin` decimal(6,2) UNSIGNED NOT NULL DEFAULT 1.00,
  `urutan` smallint(5) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `soal_kuis`
--

INSERT INTO `soal_kuis` (`id_soal`, `id_kuis`, `tipe_soal`, `soal`, `pilihan_a`, `pilihan_b`, `pilihan_c`, `pilihan_d`, `kunci_jawaban`, `poin`, `urutan`) VALUES
(1, 1, 'pilihan_ganda', 'vbhn knjknkln bjkk', 'ajxjla', 'xaxa', 'xqxsx', 'xqsxsxs', 'A', 1.00, 1),
(2, 2, 'pilihan_ganda', 'bjbjnbjnjnnnnnnnnnnkj', 'aaa', 'xsxs', 'sxsxsx', 'xsxsxxcww', 'D', 1.00, 1),
(3, 2, 'isian', 'jbjbkjbhbhvgvhj', NULL, NULL, NULL, NULL, NULL, 1.00, 2),
(4, 3, 'pilihan_ganda', 'qqqqq', 'qqq', 'qqqq', 'qqq', 'qqqq', 'A', 1.00, 1);

-- --------------------------------------------------------

--
-- Table structure for table `tugas`
--

CREATE TABLE `tugas` (
  `id_tugas` int(10) UNSIGNED NOT NULL,
  `id_kelas` int(10) UNSIGNED NOT NULL,
  `id_pengajar` int(10) UNSIGNED NOT NULL,
  `judul` varchar(200) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `file` varchar(255) DEFAULT NULL,
  `deadline` datetime NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'terbuka',
  `waktu_dibuat` datetime NOT NULL DEFAULT current_timestamp(),
  `waktu_mulai` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tugas`
--

INSERT INTO `tugas` (`id_tugas`, `id_kelas`, `id_pengajar`, `judul`, `deskripsi`, `file`, `deadline`, `status`, `waktu_dibuat`, `waktu_mulai`) VALUES
(1, 1, 1, 'tugas 1', 'hbzhkbzkbzjnabja', NULL, '2026-10-16 21:03:00', 'terbuka', '2026-10-16 20:04:29', '2026-10-16 20:03:00');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `absensi`
--
ALTER TABLE `absensi`
  ADD PRIMARY KEY (`id_absensi`),
  ADD KEY `fk_absensi_kelas` (`id_kelas`),
  ADD KEY `fk_absensi_pengajar` (`id_pengajar`);

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id_admin`),
  ADD UNIQUE KEY `kode_admin` (`kode_admin`),
  ADD UNIQUE KEY `unique_email_admin` (`email`);

--
-- Indexes for table `jawaban_kuis`
--
ALTER TABLE `jawaban_kuis`
  ADD PRIMARY KEY (`id_jawaban`),
  ADD UNIQUE KEY `uq_jawaban_per_soal` (`id_percobaan`,`id_soal`),
  ADD KEY `idx_jawaban_penilai` (`id_penilai`),
  ADD KEY `fk_jawaban_soal` (`id_soal`);

--
-- Indexes for table `kehadiran`
--
ALTER TABLE `kehadiran`
  ADD PRIMARY KEY (`id_kehadiran`),
  ADD UNIQUE KEY `uq_kehadiran_absensi_siswa` (`id_absensi`,`id_siswa`),
  ADD KEY `fk_kehadiran_siswa` (`id_siswa`);

--
-- Indexes for table `kelas`
--
ALTER TABLE `kelas`
  ADD PRIMARY KEY (`id_kelas`);

--
-- Indexes for table `kelas_pengajar`
--
ALTER TABLE `kelas_pengajar`
  ADD PRIMARY KEY (`id_kelas_pengajar`),
  ADD UNIQUE KEY `uq_kelas_pengajar` (`id_kelas`,`id_pengajar`),
  ADD KEY `fk_kelas_pengajar_pengajar` (`id_pengajar`);

--
-- Indexes for table `kelas_siswa`
--
ALTER TABLE `kelas_siswa`
  ADD PRIMARY KEY (`id_kelas_siswa`),
  ADD UNIQUE KEY `uq_kelas_siswa` (`id_kelas`,`id_siswa`),
  ADD KEY `fk_kelas_siswa_siswa` (`id_siswa`);

--
-- Indexes for table `kodetb`
--
ALTER TABLE `kodetb`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode` (`kode`);

--
-- Indexes for table `kuis`
--
ALTER TABLE `kuis`
  ADD PRIMARY KEY (`id_kuis`),
  ADD KEY `idx_kuis_kelas` (`id_kelas`),
  ADD KEY `idx_kuis_pengajar` (`id_pengajar`);

--
-- Indexes for table `materi`
--
ALTER TABLE `materi`
  ADD PRIMARY KEY (`id_materi`),
  ADD KEY `fk_materi_kelas` (`id_kelas`),
  ADD KEY `fk_materi_pengajar` (`id_pengajar`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `migration` (`migration`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `email` (`email`);

--
-- Indexes for table `pengajar`
--
ALTER TABLE `pengajar`
  ADD PRIMARY KEY (`id_pengajar`),
  ADD UNIQUE KEY `kode_pengajar` (`kode_pengajar`),
  ADD UNIQUE KEY `unique_email_pengajar` (`email`);

--
-- Indexes for table `pengumpulan_tugas`
--
ALTER TABLE `pengumpulan_tugas`
  ADD PRIMARY KEY (`id_pengumpulan`),
  ADD UNIQUE KEY `uq_pengumpulan_siswa_tugas` (`id_tugas`,`id_siswa`),
  ADD KEY `fk_pengumpulan_siswa` (`id_siswa`);

--
-- Indexes for table `percobaan_kuis`
--
ALTER TABLE `percobaan_kuis`
  ADD PRIMARY KEY (`id_percobaan`),
  ADD UNIQUE KEY `uq_percobaan_siswa` (`id_kuis`,`id_siswa`,`nomor_percobaan`),
  ADD UNIQUE KEY `uq_kuis_siswa` (`id_kuis`,`id_siswa`),
  ADD KEY `idx_percobaan_siswa` (`id_siswa`);

--
-- Indexes for table `siswa`
--
ALTER TABLE `siswa`
  ADD PRIMARY KEY (`id_siswa`),
  ADD UNIQUE KEY `kode_siswa` (`kode_siswa`),
  ADD UNIQUE KEY `unique_email_siswa` (`email`);

--
-- Indexes for table `soal_kuis`
--
ALTER TABLE `soal_kuis`
  ADD PRIMARY KEY (`id_soal`),
  ADD UNIQUE KEY `uq_soal_kuis_urutan` (`id_kuis`,`urutan`);

--
-- Indexes for table `tugas`
--
ALTER TABLE `tugas`
  ADD PRIMARY KEY (`id_tugas`),
  ADD KEY `fk_tugas_kelas` (`id_kelas`),
  ADD KEY `fk_tugas_pengajar` (`id_pengajar`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `absensi`
--
ALTER TABLE `absensi`
  MODIFY `id_absensi` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `id_admin` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `jawaban_kuis`
--
ALTER TABLE `jawaban_kuis`
  MODIFY `id_jawaban` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `kehadiran`
--
ALTER TABLE `kehadiran`
  MODIFY `id_kehadiran` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `kelas`
--
ALTER TABLE `kelas`
  MODIFY `id_kelas` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `kelas_pengajar`
--
ALTER TABLE `kelas_pengajar`
  MODIFY `id_kelas_pengajar` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `kelas_siswa`
--
ALTER TABLE `kelas_siswa`
  MODIFY `id_kelas_siswa` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `kodetb`
--
ALTER TABLE `kodetb`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `kuis`
--
ALTER TABLE `kuis`
  MODIFY `id_kuis` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `materi`
--
ALTER TABLE `materi`
  MODIFY `id_materi` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pengajar`
--
ALTER TABLE `pengajar`
  MODIFY `id_pengajar` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `pengumpulan_tugas`
--
ALTER TABLE `pengumpulan_tugas`
  MODIFY `id_pengumpulan` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `percobaan_kuis`
--
ALTER TABLE `percobaan_kuis`
  MODIFY `id_percobaan` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `siswa`
--
ALTER TABLE `siswa`
  MODIFY `id_siswa` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `soal_kuis`
--
ALTER TABLE `soal_kuis`
  MODIFY `id_soal` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `tugas`
--
ALTER TABLE `tugas`
  MODIFY `id_tugas` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `absensi`
--
ALTER TABLE `absensi`
  ADD CONSTRAINT `fk_absensi_kelas` FOREIGN KEY (`id_kelas`) REFERENCES `kelas` (`id_kelas`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_absensi_pengajar` FOREIGN KEY (`id_pengajar`) REFERENCES `pengajar` (`id_pengajar`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `jawaban_kuis`
--
ALTER TABLE `jawaban_kuis`
  ADD CONSTRAINT `fk_jawaban_penilai` FOREIGN KEY (`id_penilai`) REFERENCES `pengajar` (`id_pengajar`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_jawaban_percobaan` FOREIGN KEY (`id_percobaan`) REFERENCES `percobaan_kuis` (`id_percobaan`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_jawaban_soal` FOREIGN KEY (`id_soal`) REFERENCES `soal_kuis` (`id_soal`) ON UPDATE CASCADE;

--
-- Constraints for table `kehadiran`
--
ALTER TABLE `kehadiran`
  ADD CONSTRAINT `fk_kehadiran_absensi` FOREIGN KEY (`id_absensi`) REFERENCES `absensi` (`id_absensi`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_kehadiran_siswa` FOREIGN KEY (`id_siswa`) REFERENCES `siswa` (`id_siswa`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `kelas_pengajar`
--
ALTER TABLE `kelas_pengajar`
  ADD CONSTRAINT `fk_kelas_pengajar_kelas` FOREIGN KEY (`id_kelas`) REFERENCES `kelas` (`id_kelas`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_kelas_pengajar_pengajar` FOREIGN KEY (`id_pengajar`) REFERENCES `pengajar` (`id_pengajar`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `kelas_siswa`
--
ALTER TABLE `kelas_siswa`
  ADD CONSTRAINT `fk_kelas_siswa_kelas` FOREIGN KEY (`id_kelas`) REFERENCES `kelas` (`id_kelas`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_kelas_siswa_siswa` FOREIGN KEY (`id_siswa`) REFERENCES `siswa` (`id_siswa`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `kuis`
--
ALTER TABLE `kuis`
  ADD CONSTRAINT `fk_kuis_kelas` FOREIGN KEY (`id_kelas`) REFERENCES `kelas` (`id_kelas`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_kuis_pengajar` FOREIGN KEY (`id_pengajar`) REFERENCES `pengajar` (`id_pengajar`) ON UPDATE CASCADE;

--
-- Constraints for table `materi`
--
ALTER TABLE `materi`
  ADD CONSTRAINT `fk_materi_kelas` FOREIGN KEY (`id_kelas`) REFERENCES `kelas` (`id_kelas`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_materi_pengajar` FOREIGN KEY (`id_pengajar`) REFERENCES `pengajar` (`id_pengajar`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `pengumpulan_tugas`
--
ALTER TABLE `pengumpulan_tugas`
  ADD CONSTRAINT `fk_pengumpulan_siswa` FOREIGN KEY (`id_siswa`) REFERENCES `siswa` (`id_siswa`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pengumpulan_tugas` FOREIGN KEY (`id_tugas`) REFERENCES `tugas` (`id_tugas`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `percobaan_kuis`
--
ALTER TABLE `percobaan_kuis`
  ADD CONSTRAINT `fk_percobaan_kuis` FOREIGN KEY (`id_kuis`) REFERENCES `kuis` (`id_kuis`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_percobaan_siswa` FOREIGN KEY (`id_siswa`) REFERENCES `siswa` (`id_siswa`) ON UPDATE CASCADE;

--
-- Constraints for table `soal_kuis`
--
ALTER TABLE `soal_kuis`
  ADD CONSTRAINT `fk_soal_kuis` FOREIGN KEY (`id_kuis`) REFERENCES `kuis` (`id_kuis`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tugas`
--
ALTER TABLE `tugas`
  ADD CONSTRAINT `fk_tugas_kelas` FOREIGN KEY (`id_kelas`) REFERENCES `kelas` (`id_kelas`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tugas_pengajar` FOREIGN KEY (`id_pengajar`) REFERENCES `pengajar` (`id_pengajar`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
