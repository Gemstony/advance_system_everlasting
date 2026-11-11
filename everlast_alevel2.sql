-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Nov 11, 2026 at 06:57 PM
-- Server version: 5.7.44
-- PHP Version: 8.1.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `everlast_alevel2`
--

-- --------------------------------------------------------

--
-- Table structure for table `event_categories`
--

CREATE TABLE `event_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `color` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `event_categories`
--

INSERT INTO `event_categories` (`id`, `name`, `color`, `created_at`) VALUES
(1, 'Examinations', '#ef4444', '2026-05-14 14:24:10'),
(2, 'Meeting', '#3b82f6', '2026-05-14 14:24:10'),
(3, 'Holiday', '#10b981', '2026-05-14 14:24:10'),
(4, 'Sports', '#f97316', '2026-05-14 14:24:10'),
(5, 'Assembly', '#8b5cf6', '2026-05-14 14:24:10'),
(6, 'General', '#4f46e5', '2026-05-14 14:24:10');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_announcements`
--

CREATE TABLE `tbl_announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(90) NOT NULL,
  `announcement` longtext NOT NULL,
  `create_date` datetime NOT NULL,
  `level` int(11) NOT NULL COMMENT '0 = Teachers, 1 = Student, 2 = Both'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `tbl_announcements`
--

INSERT INTO `tbl_announcements` (`id`, `title`, `announcement`, `create_date`, `level`) VALUES
(9, 'EVERLASTING ACADEMY – OFFICIAL OPENING', '<p>We are delighted to invite you to the official opening of Everlasting Academy on <b>April 7, 2026</b>.</p><p>To ensure a seamless start, we encourage all new and returning members of our community to arrive early for registration, orientation, and a warm welcome from our faculty and staff.</p><p><br></p><p>Doors open early in the morning.</p><p>We look forward to beginning this journey of academic excellence together.</p>', '2026-03-28 07:55:08', 2);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_bank_accounts`
--

CREATE TABLE `tbl_bank_accounts` (
  `id` int(11) NOT NULL,
  `bank_name` varchar(100) NOT NULL,
  `account_name` varchar(100) NOT NULL,
  `account_number` varchar(50) NOT NULL,
  `branch` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `tbl_bank_accounts`
--

INSERT INTO `tbl_bank_accounts` (`id`, `bank_name`, `account_name`, `account_number`, `branch`, `is_active`) VALUES
(1, 'CRDB Bank', 'EVERLASTING ACADEMY', '0150803146400', 'Kibaha', 1),
(2, 'NMB Bank', 'EVERLASTING ACADEMY', '23110083454', 'Kibaha', 1);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_classes`
--

CREATE TABLE `tbl_classes` (
  `id` int(11) NOT NULL,
  `name` varchar(90) NOT NULL,
  `registration_date` datetime NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `tbl_classes`
--

INSERT INTO `tbl_classes` (`id`, `name`, `registration_date`, `status`) VALUES
(28, 'HGK FORM FIVE 2026', '2026-05-24 04:11:27', 1),
(29, 'HGL FORM FIVE 2026', '2026-05-24 04:11:55', 1),
(30, 'PMC FORM FIVE 2026', '2026-05-24 04:12:07', 1),
(31, 'PCB FORM FIVE 2026', '2026-05-24 04:12:20', 1),
(32, 'HKL FORM FIVE 2026', '2026-05-24 04:12:37', 1),
(33, 'PCM FORM FIVE 2026', '2026-06-13 06:05:08', 1),
(34, 'EGM FORM FIVE 2026', '2026-09-09 08:06:42', 1),
(35, 'ECM FORM FIVE 2026', '2026-09-09 08:06:53', 1),
(36, 'EBUAC FORM FIVE 2026', '2026-09-09 08:07:04', 1),
(37, 'HGE FORM FIVE 2026', '2026-09-09 08:07:18', 1);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_division_system`
--

CREATE TABLE `tbl_division_system` (
  `division` varchar(50) NOT NULL,
  `min` int(11) NOT NULL,
  `max` int(11) NOT NULL,
  `min_point` int(11) NOT NULL,
  `max_point` int(11) NOT NULL,
  `points` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `tbl_division_system`
--

INSERT INTO `tbl_division_system` (`division`, `min`, `max`, `min_point`, `max_point`, `points`) VALUES
('0', 0, 0, 17, 21, 0),
('I', 0, 0, 3, 7, 1),
('II', 0, 0, 8, 9, 2),
('III', 0, 0, 10, 13, 3),
('IV', 0, 0, 14, 16, 4);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_events`
--

CREATE TABLE `tbl_events` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `event_date` date NOT NULL,
  `event_time` time DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `audience` enum('teachers','students','both') NOT NULL DEFAULT 'teachers',
  `posted_by` int(11) NOT NULL COMMENT 'academic staff id',
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_exam_results`
--

CREATE TABLE `tbl_exam_results` (
  `id` int(11) NOT NULL,
  `student` varchar(20) NOT NULL,
  `class` int(11) NOT NULL,
  `subject_combination` int(11) NOT NULL,
  `term` int(11) NOT NULL,
  `score` double NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `tbl_exam_results`
--

INSERT INTO `tbl_exam_results` (`id`, `student`, `class`, `subject_combination`, `term`, `score`) VALUES
(1, 'EA-005', 30, 82, 15, 25),
(2, 'EA-005', 30, 81, 15, 34),
(3, 'EA-005', 30, 83, 15, 90),
(4, 'EA-005', 30, 78, 15, 56),
(5, 'EA-001', 29, 79, 16, 83),
(6, 'EA-001', 29, 80, 16, 75),
(7, 'EA-001', 29, 78, 16, 74),
(8, 'EA-001', 29, 84, 16, 72),
(9, 'EA-001', 29, 85, 16, 74),
(10, 'EA-003', 29, 84, 16, 60),
(11, 'EA-003', 29, 79, 16, 74),
(12, 'EA-003', 29, 80, 16, 65),
(13, 'EA-003', 29, 85, 16, 65),
(14, 'EA-003', 29, 78, 16, 79),
(15, 'EA-004', 32, 86, 16, 61),
(16, 'EA-004', 32, 79, 16, 71),
(17, 'EA-004', 32, 80, 16, 0),
(18, 'EA-004', 32, 87, 16, 75),
(19, 'EA-004', 32, 78, 16, 62),
(20, 'EA-004', 32, 88, 16, 60),
(21, 'EA-005', 30, 82, 16, 47),
(22, 'EA-005', 30, 81, 16, 14),
(23, 'EA-005', 30, 83, 16, 36),
(24, 'EA-005', 30, 89, 16, 63),
(25, 'EA-005', 30, 78, 16, 76),
(26, 'EA-006', 31, 81, 16, 26),
(27, 'EA-006', 31, 90, 16, 67),
(28, 'EA-006', 31, 91, 16, 38),
(29, 'EA-006', 31, 92, 16, 81),
(30, 'EA-006', 31, 78, 16, 80),
(31, 'E2101', 33, 96, 16, 65),
(32, 'E2101', 33, 94, 16, 40),
(33, 'E2101', 33, 93, 16, 50),
(34, 'E2101', 33, 95, 16, 68),
(35, 'E2101', 33, 97, 16, 68),
(36, 'E01109', 31, 81, 16, 40),
(37, 'E01109', 31, 90, 16, 75),
(38, 'E01109', 31, 91, 16, 57),
(39, 'E01109', 31, 92, 16, 64),
(40, 'E01109', 31, 78, 16, 72),
(41, 'EA00023', 32, 88, 16, 62),
(42, 'EA00023', 32, 86, 16, 58),
(43, 'EA00023', 32, 79, 16, 81),
(44, 'EA00023', 32, 87, 16, 70),
(45, 'EA00023', 32, 78, 16, 73),
(46, 'EAS001', 31, 81, 17, 90),
(47, 'EAS001', 31, 90, 17, 82),
(48, 'EAS001', 31, 91, 17, 76),
(49, 'EAS001', 31, 92, 17, 85),
(50, 'EAS001', 31, 78, 17, 69),
(51, 'EAS001', 31, 105, 17, 99),
(52, 'EAS002', 31, 81, 17, 41),
(53, 'EAS002', 31, 90, 17, 56),
(54, 'EAS002', 31, 91, 17, 31),
(55, 'EAS002', 31, 92, 17, 50),
(56, 'EAS002', 31, 78, 17, 42),
(57, 'EAS002', 31, 105, 17, 75),
(58, 'EAS003', 31, 81, 17, 43),
(59, 'EAS003', 31, 90, 17, 74),
(60, 'EAS003', 31, 91, 17, 58),
(61, 'EAS003', 31, 92, 17, 81),
(62, 'EAS003', 31, 78, 17, 55),
(63, 'EAS003', 31, 105, 17, 67),
(64, 'EAS004', 31, 81, 17, 38),
(65, 'EAS004', 31, 90, 17, 65),
(66, 'EAS004', 31, 91, 17, 67),
(67, 'EAS004', 31, 92, 17, 63),
(68, 'EAS004', 31, 78, 17, 63),
(69, 'EAS004', 31, 105, 17, 84),
(70, 'EAS005', 31, 81, 17, 24),
(71, 'EAS005', 31, 90, 17, 60),
(72, 'EAS005', 31, 91, 17, 37),
(73, 'EAS005', 31, 92, 17, 60),
(74, 'EAS005', 31, 78, 17, 53),
(75, 'EAS005', 31, 105, 17, 63),
(76, 'EAS006', 31, 81, 17, 76),
(77, 'EAS006', 31, 90, 17, 86),
(78, 'EAS006', 31, 91, 17, 78),
(79, 'EAS006', 31, 92, 17, 63),
(80, 'EAS006', 31, 78, 17, 56),
(81, 'EAS006', 31, 105, 17, 99),
(82, 'EAS008', 31, 81, 17, 20),
(83, 'EAS008', 31, 90, 17, 66),
(84, 'EAS008', 31, 91, 17, 0),
(85, 'EAS008', 31, 92, 17, 53),
(86, 'EAS008', 31, 78, 17, 0),
(87, 'EAS008', 31, 105, 17, 44),
(88, 'EAS009', 33, 96, 17, 50),
(89, 'EAS009', 33, 94, 17, 71),
(90, 'EAS009', 33, 93, 17, 57),
(91, 'EAS009', 33, 95, 17, 77),
(92, 'EAS009', 33, 97, 17, 77),
(93, 'EAS010', 30, 82, 17, 70),
(94, 'EAS010', 30, 81, 17, 64),
(95, 'EAS010', 30, 83, 17, 66),
(96, 'EAS010', 30, 89, 17, 90),
(97, 'EAS010', 30, 78, 17, 53),
(98, 'EAS011', 30, 82, 17, 10),
(99, 'EAS011', 30, 81, 17, 28),
(100, 'EAS011', 30, 83, 17, 50),
(101, 'EAS011', 30, 89, 17, 57),
(102, 'EAS011', 30, 78, 17, 60),
(103, 'EAS024', 35, 101, 17, 10),
(104, 'EAS024', 35, 100, 17, 64),
(105, 'EAS024', 35, 98, 17, 52),
(106, 'EAS024', 35, 104, 17, 0),
(107, 'EAS024', 35, 106, 17, 60),
(108, 'EAS012', 34, 99, 17, 48),
(109, 'EAS012', 34, 107, 17, 61),
(110, 'EAS012', 34, 98, 17, 63),
(111, 'EAS012', 34, 104, 17, 60),
(112, 'EAS012', 34, 108, 17, 23),
(113, 'EAS013', 34, 108, 17, 12),
(114, 'EAS013', 34, 99, 17, 50),
(115, 'EAS013', 34, 107, 17, 62),
(116, 'EAS013', 34, 98, 17, 53),
(117, 'EAS013', 34, 104, 17, 50),
(118, 'EAS015', 36, 98, 17, 19),
(119, 'EAS015', 36, 102, 17, 51),
(120, 'EAS015', 36, 104, 17, 60),
(121, 'EAS015', 36, 103, 17, 59),
(122, 'EAS015', 36, 105, 17, 71),
(123, 'EAS015', 36, 109, 17, 68),
(124, 'EAS016', 36, 109, 17, 81),
(125, 'EAS016', 36, 98, 17, 75),
(126, 'EAS016', 36, 102, 17, 96),
(127, 'EAS016', 36, 104, 17, 97),
(128, 'EAS016', 36, 103, 17, 93),
(129, 'EAS016', 36, 105, 17, 68),
(130, 'EAS017', 36, 109, 17, 80),
(131, 'EAS017', 36, 98, 17, 60),
(132, 'EAS017', 36, 102, 17, 55),
(133, 'EAS017', 36, 104, 17, 87),
(134, 'EAS017', 36, 103, 17, 78),
(135, 'EAS017', 36, 105, 17, 72),
(136, 'EAS018', 37, 112, 17, 48),
(137, 'EAS018', 37, 110, 17, 83),
(138, 'EAS018', 37, 113, 17, 86),
(139, 'EAS018', 37, 98, 17, 67),
(140, 'EAS018', 37, 111, 17, 85),
(141, 'EAS018', 37, 105, 17, 67),
(142, 'EAS019', 37, 112, 17, 60),
(143, 'EAS019', 37, 110, 17, 68),
(144, 'EAS019', 37, 113, 17, 72),
(145, 'EAS019', 37, 98, 17, 44),
(146, 'EAS019', 37, 111, 17, 70),
(147, 'EAS019', 37, 105, 17, 75),
(148, 'EA-003', 29, 84, 17, 48),
(149, 'EA-003', 29, 79, 17, 80),
(150, 'EA-003', 29, 80, 17, 72),
(151, 'EA-003', 29, 85, 17, 73),
(152, 'EA-003', 29, 78, 17, 37),
(153, 'EA-003', 29, 105, 17, 0),
(154, 'EA-001', 29, 84, 17, 70),
(155, 'EA-001', 29, 79, 17, 81),
(156, 'EA-001', 29, 80, 17, 70),
(157, 'EA-001', 29, 85, 17, 75),
(158, 'EA-001', 29, 78, 17, 81),
(159, 'EA-001', 29, 105, 17, 78),
(160, 'EAS023', 29, 84, 17, 47),
(161, 'EAS023', 29, 79, 17, 71),
(162, 'EAS023', 29, 80, 17, 78),
(163, 'EAS023', 29, 85, 17, 0),
(164, 'EAS023', 29, 78, 17, 58),
(165, 'EAS023', 29, 105, 17, 73),
(166, 'E2101', 33, 96, 17, 60),
(167, 'E2101', 33, 94, 17, 50),
(168, 'E2101', 33, 93, 17, 66),
(169, 'E2101', 33, 95, 17, 70),
(170, 'E2101', 33, 97, 17, 74),
(171, 'EA-006', 31, 81, 17, 47),
(172, 'EA-006', 31, 90, 17, 63),
(173, 'EA-006', 31, 91, 17, 90),
(174, 'EA-006', 31, 92, 17, 75),
(175, 'EA-006', 31, 78, 17, 69),
(176, 'EA-006', 31, 105, 17, 78),
(177, 'EA-005', 30, 82, 17, 52),
(178, 'EA-005', 30, 81, 17, 45),
(179, 'EA-005', 30, 83, 17, 85),
(180, 'EA-005', 30, 89, 17, 70),
(181, 'EA-005', 30, 78, 17, 71),
(182, 'E01109', 31, 81, 17, 55),
(183, 'E01109', 31, 90, 17, 74),
(184, 'E01109', 31, 91, 17, 63),
(185, 'E01109', 31, 92, 17, 72),
(186, 'E01109', 31, 78, 17, 60),
(187, 'E01109', 31, 105, 17, 66),
(188, 'EA00023', 32, 88, 17, 75),
(189, 'EA00023', 32, 86, 17, 80),
(190, 'EA00023', 32, 79, 17, 0),
(191, 'EA00023', 32, 87, 17, 71),
(192, 'EA00023', 32, 78, 17, 45),
(193, 'EA00023', 32, 105, 17, 0),
(194, 'EAS020', 29, 84, 17, 40),
(195, 'EAS020', 29, 79, 17, 69),
(196, 'EAS020', 29, 80, 17, 68),
(197, 'EAS020', 29, 85, 17, 51),
(198, 'EAS020', 29, 78, 17, 53),
(199, 'EAS020', 29, 105, 17, 0),
(200, 'EAS021', 29, 84, 17, 45),
(201, 'EAS021', 29, 79, 17, 76),
(202, 'EAS021', 29, 80, 17, 74),
(203, 'EAS021', 29, 85, 17, 78),
(204, 'EAS021', 29, 78, 17, 64),
(205, 'EAS021', 29, 105, 17, 0),
(206, 'EAS022', 29, 84, 17, 30),
(207, 'EAS022', 29, 79, 17, 0),
(208, 'EAS022', 29, 80, 17, 46),
(209, 'EAS022', 29, 85, 17, 70),
(210, 'EAS022', 29, 78, 17, 27),
(211, 'EAS022', 29, 105, 17, 0),
(212, 'E01109', 31, 81, 15, 55),
(213, 'E01109', 31, 90, 15, 74),
(214, 'E01109', 31, 91, 15, 63),
(215, 'E01109', 31, 92, 15, 72),
(216, 'E01109', 31, 78, 15, 60),
(217, 'E01109', 31, 105, 15, 66),
(218, 'EAS014', 36, 109, 17, 80),
(219, 'EAS014', 36, 98, 17, 56),
(220, 'EAS014', 36, 102, 17, 50),
(221, 'EAS014', 36, 104, 17, 95),
(222, 'EAS014', 36, 103, 17, 63),
(223, 'EAS014', 36, 105, 17, 71),
(224, 'EA-004', 32, 88, 17, 64),
(225, 'EA-004', 32, 86, 17, 82),
(226, 'EA-004', 32, 79, 17, 75),
(227, 'EA-004', 32, 87, 17, 68),
(228, 'EA-004', 32, 78, 17, 62),
(229, 'EA-004', 32, 105, 17, 0),
(230, 'EAS007', 31, 81, 17, 57),
(231, 'EAS007', 31, 90, 17, 78),
(232, 'EAS007', 31, 91, 17, 70),
(233, 'EAS007', 31, 92, 17, 61),
(234, 'EAS007', 31, 78, 17, 60),
(235, 'EAS007', 31, 105, 17, 75),
(236, 'EA-005', 30, 82, 18, 61),
(237, 'EA-005', 30, 81, 18, 30),
(238, 'EA-005', 30, 83, 18, 61),
(239, 'EA-005', 30, 89, 18, 90),
(240, 'EA-005', 30, 78, 18, 0),
(241, 'EAS010', 30, 82, 18, 61),
(242, 'EAS010', 30, 81, 18, 30),
(243, 'EAS010', 30, 83, 18, 61),
(244, 'EAS010', 30, 89, 18, 90),
(245, 'EAS010', 30, 78, 18, 0),
(246, 'EAS009', 33, 96, 18, 70),
(247, 'EAS009', 33, 94, 18, 50),
(248, 'EAS009', 33, 93, 18, 76),
(249, 'EAS009', 33, 95, 18, 83),
(250, 'EAS009', 33, 97, 18, 77),
(251, 'EAS001', 31, 81, 18, 52),
(252, 'EAS001', 31, 90, 18, 45),
(253, 'EAS001', 31, 92, 18, 85),
(254, 'EAS002', 31, 81, 18, 20),
(255, 'EAS002', 31, 90, 18, 23),
(256, 'EAS002', 31, 92, 18, 62),
(257, 'EAS004', 31, 81, 18, 30),
(258, 'EAS004', 31, 90, 18, 39),
(259, 'EAS004', 31, 92, 18, 73),
(260, 'EAS005', 31, 92, 18, 75),
(261, 'EAS006', 31, 90, 18, 43),
(262, 'EAS006', 31, 92, 18, 81),
(263, 'EAS007', 31, 81, 18, 36),
(264, 'EAS007', 31, 90, 18, 43),
(265, 'EAS007', 31, 92, 18, 70),
(266, 'EAS008', 31, 81, 18, 30),
(267, 'EAS008', 31, 92, 18, 72),
(268, 'EAS001', 31, 78, 18, 69),
(269, 'EAS001', 31, 91, 18, 88),
(270, 'EAS002', 31, 78, 18, 36),
(271, 'EAS002', 31, 91, 18, 63),
(272, 'EAS003', 31, 78, 18, 55),
(273, 'EAS003', 31, 91, 18, 68),
(274, 'EAS004', 31, 78, 18, 63),
(275, 'EAS004', 31, 91, 18, 63),
(276, 'EAS005', 31, 78, 18, 53),
(277, 'EAS006', 31, 78, 18, 56),
(278, 'EAS006', 31, 91, 18, 89),
(279, 'EAS007', 31, 78, 18, 60),
(280, 'EAS007', 31, 91, 18, 70),
(281, 'EAS008', 31, 91, 18, 65),
(282, 'EAS024', 35, 101, 18, 40),
(283, 'EAS024', 35, 100, 18, 58),
(284, 'EAS024', 35, 106, 18, 80),
(285, 'EAS024', 35, 98, 18, 52),
(286, 'EAS024', 35, 104, 18, 76),
(287, 'EAS014', 36, 98, 18, 56),
(288, 'EAS014', 36, 102, 18, 60),
(289, 'EAS014', 36, 103, 18, 90),
(290, 'EAS014', 36, 104, 18, 91),
(291, 'EAS014', 36, 109, 18, 81),
(292, 'EAS015', 36, 98, 18, 19),
(293, 'EAS015', 36, 102, 18, 54),
(294, 'EAS015', 36, 103, 18, 70),
(295, 'EAS015', 36, 104, 18, 86),
(296, 'EAS015', 36, 109, 18, 74),
(297, 'EAS016', 36, 98, 18, 75),
(298, 'EAS016', 36, 102, 18, 72),
(299, 'EAS016', 36, 103, 18, 90),
(300, 'EAS016', 36, 104, 18, 96),
(301, 'EAS016', 36, 109, 18, 88),
(302, 'EAS017', 36, 98, 18, 60),
(303, 'EAS017', 36, 102, 18, 61),
(304, 'EAS017', 36, 103, 18, 88),
(305, 'EAS017', 36, 104, 18, 75),
(306, 'EAS017', 36, 109, 18, 82),
(307, 'EAS012', 34, 108, 18, 25),
(308, 'EAS012', 34, 99, 18, 0),
(309, 'EAS012', 34, 107, 18, 83),
(310, 'EAS012', 34, 98, 18, 63),
(311, 'EAS012', 34, 104, 18, 77),
(312, 'EAS013', 34, 108, 18, 16),
(313, 'EAS013', 34, 99, 18, 0),
(314, 'EAS013', 34, 107, 18, 71),
(315, 'EAS013', 34, 98, 18, 53),
(316, 'EAS013', 34, 104, 18, 78),
(317, 'EAS018', 37, 112, 18, 0),
(318, 'EAS018', 37, 110, 18, 85),
(319, 'EAS018', 37, 113, 18, 88),
(320, 'EAS018', 37, 98, 18, 67),
(321, 'EAS018', 37, 111, 18, 88),
(322, 'EAS018', 37, 105, 18, 0),
(323, 'EAS019', 37, 112, 18, 0),
(324, 'EAS019', 37, 110, 18, 76),
(325, 'EAS019', 37, 113, 18, 73),
(326, 'EAS019', 37, 98, 18, 44),
(327, 'EAS019', 37, 111, 18, 60),
(328, 'EAS019', 37, 105, 18, 0),
(329, 'EAS020', 29, 84, 18, 0),
(330, 'EAS020', 29, 79, 18, 81),
(331, 'EAS020', 29, 80, 18, 39),
(332, 'EAS020', 29, 85, 18, 80),
(333, 'EAS020', 29, 78, 18, 53),
(334, 'EAS020', 29, 105, 18, 0),
(335, 'EAS021', 29, 84, 18, 0),
(336, 'EAS021', 29, 79, 18, 86),
(337, 'EAS021', 29, 80, 18, 0),
(338, 'EAS021', 29, 85, 18, 78),
(339, 'EAS021', 29, 78, 18, 64),
(340, 'EAS021', 29, 105, 18, 0),
(341, 'EAS022', 29, 84, 18, 0),
(342, 'EAS022', 29, 79, 18, 75),
(343, 'EAS022', 29, 80, 18, 26),
(344, 'EAS022', 29, 85, 18, 70),
(345, 'EAS022', 29, 78, 18, 27),
(346, 'EAS022', 29, 105, 18, 0),
(347, 'EAS023', 29, 84, 18, 0),
(348, 'EAS023', 29, 79, 18, 76),
(349, 'EAS023', 29, 80, 18, 30),
(350, 'EAS023', 29, 85, 18, 77),
(351, 'EAS023', 29, 78, 18, 58),
(352, 'EAS023', 29, 105, 18, 0),
(353, 'EAS014', 36, 98, 19, 44),
(354, 'EAS014', 36, 102, 19, 65),
(355, 'EAS014', 36, 103, 19, 85),
(356, 'EAS014', 36, 104, 19, 82),
(357, 'EAS014', 36, 105, 19, 46),
(358, 'EAS014', 36, 109, 19, 71),
(359, 'EAS015', 36, 98, 19, 13),
(360, 'EAS015', 36, 102, 19, 60),
(361, 'EAS015', 36, 103, 19, 63),
(362, 'EAS015', 36, 104, 19, 72),
(363, 'EAS015', 36, 105, 19, 51),
(364, 'EAS015', 36, 109, 19, 71),
(365, 'EAS016', 36, 98, 19, 62),
(366, 'EAS016', 36, 102, 19, 81),
(367, 'EAS016', 36, 103, 19, 96),
(368, 'EAS016', 36, 104, 19, 96),
(369, 'EAS016', 36, 105, 19, 82),
(370, 'EAS016', 36, 109, 19, 81),
(371, 'EAS024', 35, 98, 19, 40),
(372, 'EAS024', 35, 100, 19, 73),
(373, 'EAS024', 35, 101, 19, 50),
(374, 'EAS024', 35, 104, 19, 54),
(375, 'EAS024', 35, 106, 19, 75),
(376, 'EAS012', 34, 98, 19, 21),
(377, 'EAS012', 34, 99, 19, 60),
(378, 'EAS012', 34, 104, 19, 70),
(379, 'EAS012', 34, 107, 19, 71),
(380, 'EAS012', 34, 108, 19, 36),
(381, 'EAS013', 34, 98, 19, 50),
(382, 'EAS013', 34, 99, 19, 58),
(383, 'EAS013', 34, 104, 19, 76),
(384, 'EAS013', 34, 107, 19, 84),
(385, 'EAS013', 34, 108, 19, 36),
(386, 'EAS018', 37, 98, 19, 35),
(387, 'EAS018', 37, 105, 19, 40),
(388, 'EAS018', 37, 110, 19, 71),
(389, 'EAS018', 37, 111, 19, 70),
(390, 'EAS018', 37, 112, 19, 40),
(391, 'EAS018', 37, 113, 19, 89),
(392, 'EAS019', 37, 98, 19, 36),
(393, 'EAS019', 37, 105, 19, 10),
(394, 'EAS019', 37, 110, 19, 66),
(395, 'EAS019', 37, 111, 19, 60),
(396, 'EAS019', 37, 112, 19, 32),
(397, 'EAS019', 37, 113, 19, 61),
(398, 'EA-004', 32, 78, 19, 84),
(399, 'EA-004', 32, 79, 19, 75),
(400, 'EA-004', 32, 86, 19, 66),
(401, 'EA-004', 32, 87, 19, 65),
(402, 'EA-004', 32, 88, 19, 61),
(403, 'EA00023', 32, 78, 19, 81),
(404, 'EA00023', 32, 79, 19, 71),
(405, 'EA00023', 32, 86, 19, 69),
(406, 'EA00023', 32, 87, 19, 65),
(407, 'EA00023', 32, 88, 19, 58),
(408, 'E01109', 31, 78, 19, 83),
(409, 'E01109', 31, 90, 19, 56),
(410, 'E01109', 31, 91, 19, 52),
(411, 'E01109', 31, 92, 19, 61),
(412, 'E01109', 31, 105, 19, 27),
(413, 'EA-006', 31, 78, 19, 86),
(414, 'EA-006', 31, 90, 19, 43),
(415, 'EA-006', 31, 91, 19, 68),
(416, 'EA-006', 31, 92, 19, 83),
(417, 'EA-006', 31, 105, 19, 61),
(418, 'EAS001', 31, 78, 19, 71),
(419, 'EAS001', 31, 81, 19, 80),
(420, 'EAS001', 31, 90, 19, 58),
(421, 'EAS001', 31, 92, 19, 90),
(422, 'EAS001', 31, 105, 19, 63),
(423, 'EAS002', 31, 78, 19, 22),
(424, 'EAS002', 31, 81, 19, 38),
(425, 'EAS002', 31, 90, 19, 30),
(426, 'EAS002', 31, 92, 19, 45),
(427, 'EAS002', 31, 105, 19, 42),
(428, 'EAS003', 31, 78, 19, 36),
(429, 'EAS003', 31, 81, 19, 36),
(430, 'EAS003', 31, 90, 19, 40),
(431, 'EAS003', 31, 92, 19, 65),
(432, 'EAS003', 31, 105, 19, 50),
(433, 'EAS005', 31, 92, 19, 60),
(434, 'EAS005', 31, 105, 19, 40),
(435, 'EAS006', 31, 78, 19, 63),
(436, 'EAS006', 31, 81, 19, 70),
(437, 'EAS006', 31, 90, 19, 50),
(438, 'EAS006', 31, 92, 19, 73),
(439, 'EAS006', 31, 105, 19, 43),
(440, 'EAS007', 31, 78, 19, 64),
(441, 'EAS007', 31, 81, 19, 66),
(442, 'EAS007', 31, 90, 19, 45),
(443, 'EAS007', 31, 92, 19, 65),
(444, 'EAS007', 31, 105, 19, 61),
(445, 'EAS008', 31, 78, 19, 52),
(446, 'EAS008', 31, 81, 19, 20),
(447, 'EAS008', 31, 90, 19, 33),
(448, 'EAS008', 31, 92, 19, 61),
(449, 'EAS008', 31, 105, 19, 36),
(450, 'E2101', 33, 93, 19, 71),
(451, 'E2101', 33, 95, 19, 55),
(452, 'E2101', 33, 97, 19, 78),
(453, 'EAS009', 33, 94, 19, 50),
(454, 'EAS009', 33, 95, 19, 86),
(455, 'EAS009', 33, 96, 19, 86),
(456, 'EAS009', 33, 97, 19, 85),
(457, 'EA-005', 30, 78, 19, 85),
(458, 'EA-005', 30, 83, 19, 81),
(459, 'EA-005', 30, 89, 19, 70),
(460, 'EAS010', 30, 78, 19, 59),
(461, 'EAS010', 30, 81, 19, 36),
(462, 'EAS010', 30, 82, 19, 66),
(463, 'EAS010', 30, 83, 19, 80),
(464, 'EAS010', 30, 89, 19, 85),
(465, 'EAS011', 30, 78, 19, 41),
(466, 'EAS011', 30, 81, 19, 7),
(467, 'EAS011', 30, 82, 19, 20),
(468, 'EAS011', 30, 83, 19, 35),
(469, 'EAS011', 30, 89, 19, 72),
(470, 'EA-001', 29, 78, 19, 86),
(471, 'EA-001', 29, 79, 19, 84),
(472, 'EA-001', 29, 80, 19, 81),
(473, 'EA-001', 29, 84, 19, 82),
(474, 'EA-001', 29, 85, 19, 70),
(475, 'EA-003', 29, 78, 19, 90),
(476, 'EA-003', 29, 79, 19, 60),
(477, 'EA-003', 29, 80, 19, 38),
(478, 'EA-003', 29, 84, 19, 67),
(479, 'EA-003', 29, 85, 19, 61),
(480, 'EAS020', 29, 78, 19, 26),
(481, 'EAS020', 29, 79, 19, 75),
(482, 'EAS020', 29, 80, 19, 33),
(483, 'EAS020', 29, 84, 19, 35),
(484, 'EAS020', 29, 85, 19, 70),
(485, 'EAS021', 29, 78, 19, 48),
(486, 'EAS021', 29, 79, 19, 78),
(487, 'EAS021', 29, 80, 19, 45),
(488, 'EAS021', 29, 84, 19, 64),
(489, 'EAS021', 29, 85, 19, 80),
(490, 'EAS022', 29, 78, 19, 16),
(491, 'EAS022', 29, 79, 19, 63),
(492, 'EAS022', 29, 80, 19, 24),
(493, 'EAS022', 29, 84, 19, 35),
(494, 'EAS022', 29, 85, 19, 66),
(495, 'EAS023', 29, 78, 19, 57),
(496, 'EAS023', 29, 79, 19, 77),
(497, 'EAS023', 29, 80, 19, 50),
(498, 'EAS023', 29, 85, 19, 70),
(499, 'EAS023', 29, 84, 19, 62),
(500, 'EAS023', 29, 105, 19, 0);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_fee_adjustments`
--

CREATE TABLE `tbl_fee_adjustments` (
  `id` int(11) NOT NULL,
  `student_id` varchar(20) NOT NULL,
  `approved_by` int(11) NOT NULL,
  `adjustment_type` enum('discount','waiver','fine','correction') NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_fee_audit_logs`
--

CREATE TABLE `tbl_fee_audit_logs` (
  `id` int(11) NOT NULL,
  `actor_staff_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` int(11) NOT NULL,
  `old_value` text,
  `new_value` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_fee_invoices`
--

CREATE TABLE `tbl_fee_invoices` (
  `id` int(11) NOT NULL,
  `invoice_no` varchar(50) NOT NULL,
  `student_id` varchar(20) NOT NULL,
  `class_id` int(11) NOT NULL,
  `term_id` int(11) DEFAULT NULL,
  `academic_year` varchar(20) NOT NULL,
  `generated_by` int(11) NOT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date NOT NULL,
  `subtotal` decimal(12,2) NOT NULL,
  `discount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `penalty` decimal(12,2) NOT NULL DEFAULT '0.00',
  `grand_total` decimal(12,2) NOT NULL,
  `balance` decimal(12,2) NOT NULL,
  `status` enum('draft','sent','paid','partial','cancelled') NOT NULL DEFAULT 'draft',
  `notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_fee_invoice_lines`
--

CREATE TABLE `tbl_fee_invoice_lines` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `description` varchar(255) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `type` enum('installment','outstanding','sweater','transport','meal','other') NOT NULL DEFAULT 'other'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_fee_items`
--

CREATE TABLE `tbl_fee_items` (
  `id` int(11) NOT NULL,
  `fee_structure_id` int(11) NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `required` tinyint(1) NOT NULL DEFAULT '1',
  `notes` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_fee_payments`
--

CREATE TABLE `tbl_fee_payments` (
  `id` int(11) NOT NULL,
  `payment_ref` varchar(50) NOT NULL,
  `student_id` varchar(20) NOT NULL,
  `invoice_id` int(11) DEFAULT NULL,
  `paid_amount` decimal(12,2) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_method` enum('cash','bank','mobile_money','cheque','card','manual') NOT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `bank_reference` varchar(100) DEFAULT NULL,
  `slip_image` varchar(255) DEFAULT NULL,
  `received_by` int(11) NOT NULL,
  `verified_by` int(11) DEFAULT NULL,
  `verification_status` enum('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  `receipt_status` enum('not_issued','issued') NOT NULL DEFAULT 'not_issued',
  `notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_fee_receipts`
--

CREATE TABLE `tbl_fee_receipts` (
  `id` int(11) NOT NULL,
  `receipt_no` varchar(50) NOT NULL,
  `payment_id` int(11) NOT NULL,
  `printed_by` int(11) NOT NULL,
  `printed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `receipt_data` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_fee_structures`
--

CREATE TABLE `tbl_fee_structures` (
  `id` int(11) NOT NULL,
  `academic_year` varchar(20) NOT NULL,
  `class_id` int(11) NOT NULL,
  `tuition_fee` decimal(12,2) NOT NULL DEFAULT '0.00',
  `boarding_fee` decimal(12,2) NOT NULL DEFAULT '0.00',
  `development_fee` decimal(12,2) NOT NULL DEFAULT '0.00',
  `other_mandatory_fee` decimal(12,2) NOT NULL DEFAULT '0.00',
  `currency` varchar(3) NOT NULL DEFAULT 'TZS',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_grade_system`
--

CREATE TABLE `tbl_grade_system` (
  `id` int(11) NOT NULL,
  `name` varchar(20) NOT NULL,
  `min` double NOT NULL,
  `max` double NOT NULL,
  `remark` varchar(90) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `tbl_grade_system`
--

INSERT INTO `tbl_grade_system` (`id`, `name`, `min`, `max`, `remark`) VALUES
(1, 'A', 80, 100, 'Excellent'),
(2, 'B', 70, 79, 'Very Good'),
(3, 'C', 60, 69, 'Good'),
(4, 'D', 50, 59, 'Satisfactory'),
(5, 'E', 40, 49, 'PASS'),
(6, 'S', 35, 39, 'Subsidiary'),
(7, 'F', 0, 34, 'Fail');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_images`
--

CREATE TABLE `tbl_images` (
  `id` int(11) NOT NULL,
  `image_type` varchar(50) NOT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `image_data` longblob,
  `mime_type` varchar(50) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_login_sessions`
--

CREATE TABLE `tbl_login_sessions` (
  `session_key` varchar(90) NOT NULL,
  `staff` int(11) DEFAULT NULL,
  `student` varchar(20) DEFAULT NULL,
  `ip_address` varchar(90) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `tbl_login_sessions`
--

INSERT INTO `tbl_login_sessions` (`session_key`, `staff`, `student`, `ip_address`) VALUES
('0UI7KRUM7ODGC00T20AC', NULL, 'EAS017', '196.249.120.114'),
('2WKF59IS81FEA236W00W', NULL, 'E2101', '154.74.192.116'),
('68ZFBWLSJF6GJQLHHQKD', NULL, 'EAS003', '197.250.156.23'),
('7F75KWT7GR4H6G2F2S3J', NULL, 'EAS024', '102.202.75.188'),
('82DDXDEZFLRGXIE1TRSY', NULL, 'EAS010', '197.186.71.94'),
('ANY8LBXN3I8H8OEDYW1S', NULL, 'EAS002', '197.250.51.227'),
('E6ZCAKC69EMDF367HQN9', 43, NULL, '154.74.192.101'),
('EO6DOLW230L3DK373J42', NULL, 'EAS006', '197.250.157.212'),
('FEJZ4KHO0QQNNSWG63E8', NULL, 'EAS020', '154.74.190.104'),
('IG9OUS9XG3YTT2ACOVDX', NULL, 'E01109', '197.250.156.23'),
('KLDB62GDIAYLRT3GEV56', NULL, 'EA00023', '41.222.180.250'),
('LEU07T0J3W88LB4FU9PL', NULL, 'EAS001', '154.74.191.93'),
('N4WDSITZ9NF3FS85IASH', NULL, 'EA-004', '154.74.191.93'),
('QF9C0A6YBZI9TC71MNEL', 56, NULL, '154.74.190.6'),
('RCT2MFH5B9JC4RAKHPLP', NULL, 'EAS009', '197.186.74.234'),
('RNVT7CZML3YFXNOX6C4F', NULL, 'EA-005', '41.222.180.243'),
('RX7FRYEJMDE9O1E5HSTV', 1, NULL, '154.74.191.93'),
('S37O250JKC00PZ4D6F90', 42, NULL, '154.74.190.0'),
('S6L6E3XZ7HG3B65Z4USM', 57, NULL, '154.74.191.82'),
('TXM3W4OM7HYPFZCFRBYJ', NULL, 'EAS021', '41.75.212.70'),
('WBN031WTCZM09VZXSR0K', NULL, 'EAS011', '102.202.75.165'),
('XKGB4RRTSUPBH66NR9XL', 49, NULL, '154.74.192.116'),
('Y8CVBKLTCLRAKG4T9NI4', 55, NULL, '154.74.127.70');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_notification_history`
--

CREATE TABLE `tbl_notification_history` (
  `id` int(11) NOT NULL,
  `message` text NOT NULL,
  `sent_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sent_by` int(11) NOT NULL,
  `recipient_type` enum('parents','students','both') NOT NULL DEFAULT 'parents',
  `status` enum('pending','sent','failed') NOT NULL DEFAULT 'pending',
  `sent_count` int(11) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_password_resets`
--

CREATE TABLE `tbl_password_resets` (
  `id` int(11) NOT NULL,
  `user_id` varchar(20) NOT NULL,
  `user_type` enum('student','teacher','academic') NOT NULL,
  `new_password` varchar(255) NOT NULL,
  `reset_by` int(11) NOT NULL,
  `reset_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `tbl_password_resets`
--

INSERT INTO `tbl_password_resets` (`id`, `user_id`, `user_type`, `new_password`, `reset_by`, `reset_at`) VALUES
(1, '32', 'academic', 'Kimario@12345678', 1, '2026-02-28 12:16:45'),
(2, '32', 'academic', 'Qwertyuiop@1234567890', 1, '2026-02-28 12:19:39'),
(3, '41', 'teacher', 'Fabian@1234567890', 1, '2026-03-26 12:42:58'),
(4, '49', 'academic', 'Kimario@12345', 1, '2026-05-24 06:47:47'),
(5, 'EAS012', 'student', 'Edna@1234567890', 1, '2026-09-11 11:50:32'),
(6, 'EAS001', 'student', 'Jackline@1234567890', 1, '2026-09-15 16:00:29'),
(7, 'EAS007', 'student', 'Isaac@1234567890', 1, '2026-09-16 19:02:38'),
(8, 'EAS020', 'student', 'Ivan@1234567890', 1, '2026-10-06 18:37:01'),
(9, 'EAS001', 'student', 'Jackline@1234567890', 1, '2026-11-10 10:57:21');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_promotion_log`
--

CREATE TABLE `tbl_promotion_log` (
  `id` int(11) NOT NULL,
  `student_id` varchar(20) NOT NULL,
  `from_class` int(11) NOT NULL,
  `to_class` int(11) NOT NULL,
  `promoted_by` int(11) NOT NULL,
  `promotion_date` datetime NOT NULL,
  `academic_year` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_quizzes`
--

CREATE TABLE `tbl_quizzes` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text,
  `instructions` text,
  `class_id` int(11) NOT NULL,
  `subject_combination_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `term_id` int(11) NOT NULL,
  `total_points` int(11) DEFAULT '0',
  `time_limit_minutes` int(11) DEFAULT NULL,
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `is_published` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_quiz_answers`
--

CREATE TABLE `tbl_quiz_answers` (
  `id` int(11) NOT NULL,
  `submission_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `selected_option_id` int(11) DEFAULT NULL,
  `text_answer` text,
  `is_correct` tinyint(1) NOT NULL DEFAULT '0',
  `points_earned` decimal(5,2) NOT NULL DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_quiz_options`
--

CREATE TABLE `tbl_quiz_options` (
  `id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `option_text` varchar(500) NOT NULL,
  `is_correct` tinyint(1) NOT NULL DEFAULT '0',
  `order_index` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_quiz_questions`
--

CREATE TABLE `tbl_quiz_questions` (
  `id` int(11) NOT NULL,
  `quiz_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `question_type` enum('multiple_choice','true_false','short_answer') NOT NULL DEFAULT 'multiple_choice',
  `points` int(11) NOT NULL DEFAULT '1',
  `order_index` int(11) NOT NULL DEFAULT '0',
  `explanation` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_quiz_submissions`
--

CREATE TABLE `tbl_quiz_submissions` (
  `id` int(11) NOT NULL,
  `quiz_id` int(11) NOT NULL,
  `student_id` varchar(20) NOT NULL,
  `attempt_number` int(11) NOT NULL DEFAULT '1',
  `started_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `submitted_at` datetime DEFAULT NULL,
  `score` decimal(5,2) DEFAULT NULL,
  `percentage` decimal(5,2) DEFAULT NULL,
  `is_graded` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_result_change_requests`
--

CREATE TABLE `tbl_result_change_requests` (
  `id` int(11) NOT NULL,
  `student_id` varchar(20) NOT NULL,
  `class_id` int(11) NOT NULL,
  `term_id` int(11) NOT NULL,
  `subject_combination_id` int(11) NOT NULL,
  `current_score` float NOT NULL,
  `requested_score` float NOT NULL,
  `reason` text NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `status` enum('pending','approved','denied') NOT NULL DEFAULT 'pending',
  `requested_at` datetime NOT NULL,
  `processed_at` datetime DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `tbl_result_change_requests`
--

INSERT INTO `tbl_result_change_requests` (`id`, `student_id`, `class_id`, `term_id`, `subject_combination_id`, `current_score`, `requested_score`, `reason`, `teacher_id`, `status`, `requested_at`, `processed_at`, `processed_by`) VALUES
(1, 'E001-0001', 17, 11, 45, 60, 61, 'wrong counting', 43, 'denied', '2026-03-27 23:08:24', '2026-03-27 23:08:48', 49),
(2, 'E002-037', 19, 14, 43, 57, 78, 'Counting', 43, 'approved', '2026-04-15 09:31:02', '2026-04-15 09:31:26', 49);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_school`
--

CREATE TABLE `tbl_school` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `logo` varchar(50) NOT NULL,
  `result_system` int(11) NOT NULL COMMENT '0 = Average, 1 = Division',
  `allow_results` int(11) NOT NULL DEFAULT '1',
  `maintenance_mode` tinyint(4) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `tbl_school`
--

INSERT INTO `tbl_school` (`id`, `name`, `logo`, `result_system`, `allow_results`, `maintenance_mode`) VALUES
(1, 'EVERLASTING ACADEMY ADVANCE LEVEL 2026', 'school_logo_1773030209_1d7ad4cb.jpg', 0, 1, 0);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_school_averages`
--

CREATE TABLE `tbl_school_averages` (
  `id` int(11) NOT NULL,
  `term_id` int(11) NOT NULL,
  `total_results` int(11) NOT NULL,
  `average_score` decimal(5,2) NOT NULL,
  `pass_count` int(11) NOT NULL,
  `fail_count` int(11) NOT NULL,
  `pass_percentage` decimal(5,2) NOT NULL,
  `generated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_school_contacts`
--

CREATE TABLE `tbl_school_contacts` (
  `id` int(11) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `phone1` varchar(20) DEFAULT NULL,
  `phone2` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `tbl_school_contacts`
--

INSERT INTO `tbl_school_contacts` (`id`, `location`, `phone1`, `phone2`, `email`) VALUES
(1, 'P.O.BOX 90991 MISUGUSUGU KIBAHA', '+255 794457583', '+255 7923242', 'everlastingacademy24@gmail.com');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_smtp`
--

CREATE TABLE `tbl_smtp` (
  `id` int(11) NOT NULL,
  `server` varchar(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `port` varchar(255) NOT NULL,
  `encryption` varchar(255) NOT NULL,
  `status` int(11) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `tbl_smtp`
--

INSERT INTO `tbl_smtp` (`id`, `server`, `username`, `password`, `port`, `encryption`, `status`) VALUES
(1, 'smtp server here', 'smtp username here', 'smtp password here', '587', 'tls', 1);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_staff`
--

CREATE TABLE `tbl_staff` (
  `id` int(11) NOT NULL,
  `fname` varchar(20) NOT NULL,
  `lname` varchar(20) NOT NULL,
  `gender` varchar(6) NOT NULL,
  `email` varchar(90) NOT NULL,
  `password` varchar(90) NOT NULL,
  `level` int(11) NOT NULL COMMENT '0 = Admin, 1 = Academic, 2 = Teacher',
  `status` int(11) NOT NULL DEFAULT '1' COMMENT '0 = Blocked, 1 = Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `tbl_staff`
--

INSERT INTO `tbl_staff` (`id`, `fname`, `lname`, `gender`, `email`, `password`, `level`, `status`) VALUES
(1, 'William', 'Samson', 'Male', 'william@gmail.com', '$2y$10$S/yKJwxGoP12NHzY9.KmSe3Yyz4fgrozEOg8I9GcXjoiGmoDJFAYS', 0, 1),
(34, 'Rose', 'Arope', 'Female', 'rosearope@everlasting.academy', '$2y$10$Yg6B6ZoSH/TQ37ISvpVFUu4O8nSsu6/3zDSYHzofDEJMMs756pcdm', 2, 1),
(35, 'Paul', 'Peter', 'Male', 'paulpeter@everlasting.academy', '$2y$10$/pMpSUWloNLkhbB9xw.eg.QQeTj3QF2vcXI/eil4J0hiyQOv9.RyS', 2, 1),
(36, 'Colman', 'Njau', 'Male', 'colmannjau@everlasting.academy', '$2y$10$IRI7KgRqMvXqULPDh9NIx.U9NM6ZxI4hVQiFj0MKL2xnbqRCm0zba', 2, 1),
(37, 'Yusuph', 'Chikoko', 'Male', 'yusuphchikoko@everlasting.academy', '$2y$10$9X.yHlEqcPHUPgqEHuIZaOFRGwLKMK7FffviQkdBQuvvNp1gI5OlC', 2, 1),
(38, 'Hilary', 'Wilson', 'Male', 'hilaryWilson@everlasting.academy', '$2y$10$NRt.8Al5UDbTkttpXfbg3eUlPCwWXaxP4RxSNYzAhtAxFXtJ62WWa', 2, 1),
(39, 'Athumani', 'Mayawa', 'Male', 'athumanimayawa@everlasting.academy', '$2y$10$8Brw86iMjnbWyen6R.E9lu7CUFjDNkMeXnwxcwUWSIxNyDGXOQYIG', 2, 0),
(40, 'David', 'Simon', 'Male', 'davidsimon@everlasting.academy', '$2y$10$S8xnZvPBQK1dzz6.LKsD5uK/E.wjzH970I.mWQMn1il5dc2Dc9epq', 2, 1),
(41, 'Fabain ', 'Esau', 'Male', 'fabainesau@everlasting.academy', '$2y$10$l3TpBjUn0poEgOXd0VHKheZCHASi4n8pxpON9.HUar/uwsdvcOKOC', 2, 1),
(42, 'Salma', 'Kazilo', 'Female', 'salmakazilo@everlasting.academy', '$2y$10$uxGZd429PEk114Xw..OesOd7Nx7ay38Hx4LGzVo75FpsxcUx/I7ny', 2, 1),
(43, 'William', 'Sule', 'Male', 'williamsule@everlasting.academy', '$2y$10$xD81Ul5c3rCpRifDxyLBsOQUPCJ1Qi830txIawa0.d1yvayodqG5C', 2, 1),
(45, 'Rado', 'Ramadhani', 'Male', 'radoramadhani@everlasting.academy', '$2y$10$fCiCAHY5LZJ2FbaAnFoVPeJGvwqGKb2NeDT2P6K3QbfvW5LaMmCru', 2, 1),
(46, 'Kimario', 'Gasto', 'Male', 'kimariogasto@everlasting.academy', '$2y$10$/MJzde4qu2xXYQcBd1bq6e4LXTy.ZF2hXT3MsC8XhkbUMejEm.U7W', 2, 1),
(49, 'Kimario', 'Kimario', 'Male', 'kimario@everlasting.academy', '$2y$10$BfDCmNBf/YWr0iJ/D0q1Euj.xke2LiVHFrxm8I4e0vGlRC21cNCF.', 1, 1),
(50, 'Lucas', 'Nyabasamba', 'Male', 'lucas@everlasting.academy', '$2y$10$jtrh6Ytar2M8fWxLjWfaSeE1izx00PNc7wD4Z6F1DPqOBLEsTIxke', 2, 1),
(54, 'FRANCIS', 'LAURIAN', 'Male', 'francislaurian@everlasting.academy', '$2y$10$ku4ntigBm9JYNg2T4.qFPu/HkOgpTXbt/WE2GcyT93SCLyMsLatSW', 2, 1),
(55, 'Abubakar', 'Waziri', 'Male', 'abubakarwaziri@everlasting.academy', '$2y$10$Es1k/SGJAmO8lgccf.ZA4e2jPBKrQ.JRe1wLFHql.2WkCAey874W.', 2, 1),
(56, 'Sebastian', 'Melchiad', 'Male', 'sebastianmelchiad@everlasting.academy', '$2y$10$xBUrSQ6J2.jomKC0dhqjHeFTc1RIaGGny0K5wdvIdORS/C3TbYfz2', 2, 1),
(57, 'Paul', 'Peter', 'Male', 'paulpeter1@everlasting.academy', '$2y$10$FRsRPlQoAYKplvoLEgNZ/OS/QJeoWUdGuPnUcX6zVIFPqEIJTUp36', 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_students`
--

CREATE TABLE `tbl_students` (
  `id` varchar(20) NOT NULL,
  `fname` varchar(70) NOT NULL,
  `mname` varchar(70) NOT NULL,
  `lname` varchar(70) NOT NULL,
  `gender` varchar(7) NOT NULL,
  `email` varchar(90) NOT NULL,
  `class` int(11) NOT NULL,
  `password` varchar(90) NOT NULL,
  `level` int(11) NOT NULL DEFAULT '3' COMMENT '3 = student',
  `display_image` varchar(50) NOT NULL DEFAULT 'Blank',
  `status` int(11) NOT NULL DEFAULT '1' COMMENT '0 = Disabled, 1 = Enabled'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `tbl_students`
--

INSERT INTO `tbl_students` (`id`, `fname`, `mname`, `lname`, `gender`, `email`, `class`, `password`, `level`, `display_image`, `status`) VALUES
('E01109', 'AGNESS', 'DANIEL', 'NSANINGU', 'Female', 'agnessnsaningu@everlasting.academy', 31, '$2y$10$CfObxsP2ZFTGs4rODP56auEHD.dXyuGgJb2KbSrcXIpQeQNy3BXcG', 3, 'DEFAULT', 1),
('E2101', 'ANNA', 'DANIEL', 'NSANINGU', 'Female', 'annansaningu@everlasting.academy', 33, '$2y$10$JJ7O410H/7/XqWumZ3vRee6GULUKySQETUPec4k6uWfEFAYU7plt.', 3, 'DEFAULT', 1),
('EA-001', 'KELVIN', 'PATRICK', 'MWANAMKANA', 'Male', 'kelvinmwanamkana@everlasting.academy', 29, '$2y$10$uJABZ9N2wq9qZQ8OKwAsGO7FR8LIIulraJPP.8bsmBt7e8YSoz4BW', 3, 'DEFAULT', 1),
('EA-003', 'NOELALORYN', 'ALEX', 'MANDIA', 'Female', 'noelalorynmandia@everlasting.academy', 29, '$2y$10$9RX5MPjMDPVnUNaSru/yQOnqZmj8zXZWgnlbdQbqYpprudvdtlmHW', 3, 'DEFAULT', 1),
('EA-004', 'GRACE', 'RENATUS', 'KUNAMBI', 'Female', 'gracekunambi@everlasting.academy', 32, '$2y$10$vYufIEAUYNPMTvTQAASwluY9znVJFTqXPruL89VbeaaT1jZL4dJgm', 3, 'DEFAULT', 1),
('EA-005', 'FLORA', 'FRANK', 'LUHANGA', 'Female', 'floraluhanga@everlasting.academy', 30, '$2y$10$vt5jOHtS8NSR0yH1Rnj0sOlN28E2y4CXxcE4mldc2iaVIFE629fUS', 3, 'DEFAULT', 1),
('EA-006', 'MARIAM', 'ISSA', 'RAJAB', 'Female', 'mariamrajab@everlasting.academy', 31, '$2y$10$ZYP0lTUL6fw86cpGxupATeujhjP/5cNxEM110sTMNqs1Rqu/muh5S', 3, 'DEFAULT', 1),
('EA00023', 'BETHEL', 'ERNEST', 'MGAWANYI', 'Female', 'bethelmgawanyi@everlasting.academy', 32, '$2y$10$gb5vium4xX62gn6RiVSGkeaS6bl/4LyYthF60aMv3e57yjAiGMRae', 3, 'DEFAULT', 1),
('EAS001', 'JACKLINE', 'JOHN', 'KAGUMISA', 'Female', 'jacklinekagumisa@everlasting.academy', 31, '$2y$10$KQObYtXiaDvdSoCjaL1tLOIvXQrhNWaYsfwOaDvLN1kNtbx7TvvTa', 3, 'DEFAULT', 1),
('EAS002', 'SALLYROSE', 'L', 'LUGEMBE', 'Female', 'sallyroselugembe@everlasting.academy', 31, '$2y$10$f8caPdYsOmSQ79rwQ/J9GOVecfLH2EuvJbr.YKUeanIIUDi1isx3u', 3, 'DEFAULT', 1),
('EAS003', 'ZAINABU', 'O', 'MUSHI', 'Female', 'zainabumushi@everlasting.academy', 31, '$2y$10$nneM/ScO2.keASvEHth63OLg9PloXW7784TQP4eW1uUSdXLdSxgBa', 3, 'DEFAULT', 1),
('EAS004', 'ABDULRAHMAN', 'K ', 'SIAFU', 'Male', 'abdulrahmansiafu@everlasting.academy', 31, '$2y$10$ALlJDwsSxrVACRFwpXcPUO7UwJ4gar6yC9c0dz5uoDV/P2S4fXYHO', 3, 'DEFAULT', 1),
('EAS005', 'CHARITY', 'E', 'SIRIKWA', 'Female', 'charitysirikwa@everlasting.academy', 31, '$2y$10$FFsOenWazqBHoyLZt6QuN.OsoEK8NVoyRil01PN4qQ0lW9k/KL1c6', 3, 'DEFAULT', 1),
('EAS006', 'PRECIOUS', 'A ', 'NKWABA', 'Female', 'preciousnkwaba@avarlasting.acadamy', 31, '$2y$10$CATxl25CE.5kwMKm0PjTPuJQe5y5ufpN8NxX.e3JDG9JU3F6gYEnC', 3, 'DEFAULT', 1),
('EAS007', 'ISAAC', 'U', 'ADUBANGO', 'Male', 'isaacadubango@everlasting.academy', 31, '$2y$10$DnhxA1u9m2Yht7LlL8Rsq.Z1CIvqBcp1gii6pQ9Xc/FouPskhdDIG', 3, 'DEFAULT', 1),
('EAS008', 'AGNESS', 'F', 'MEDARD', 'Female', 'agnessmedard@fvfrlasting.acadfmy', 31, '$2y$10$iUBbm6W8oGWnTf/uYXlO6efODKgLQoLnouyEV5y6Wn/5VEBbceBtG', 3, 'DEFAULT', 1),
('EAS009', 'GHUDHAIPHA', 'J', 'KATIMA', 'Male', 'ghudhaiphakatima@everlasting.academy', 33, '$2y$10$5Y6lp2lKWd53QblE2b.oK.sHcpK7PxNbbXOF4KV5FLgNAdr4201gi', 3, 'DEFAULT', 1),
('EAS010', 'JACKSON', 'J', 'KAGUMISA', 'Male', 'jacksonkagumisa@everlasting.academy', 30, '$2y$10$cPeUi44AWy4ConU8UHNKROwmfwCIAS1v7S76/XJ.UMQKTk3iI7dEm', 3, 'DEFAULT', 1),
('EAS011', 'NASREEN ', 'R', 'KORONGO', 'Female', 'nasreenkorongo@everlasting.academy', 30, '$2y$10$Bj.NKbNwZrKMtUEoMNda.OIC6iRQPeQZ2n03P6Pzae.hkIwale.rm', 3, 'DEFAULT', 1),
('EAS012', 'EDNA ', 'E', 'MUJAMA', 'Female', 'ednamujama@everlasting.academy', 34, '$2y$10$m3R3bbnRFMDVU6ton8o1M.nb/OYwPBfMoxGTzHlL2fgpyPCN7BNR2', 3, 'DEFAULT', 1),
('EAS013', 'NOUREEN', 'C', 'NYAKIHA', 'Female', 'noureennyakiha@everlasting.academy', 34, '$2y$10$6eCXLRdCKlmAMSTCv.4s8uiDrDikl4Y3TLCkf.MQ8SxHXBpTzZG3S', 3, 'DEFAULT', 1),
('EAS014', 'CAREEN ', 'C', 'MSONGOLE', 'Female', 'careenmsongole@everlasting.academy', 36, '$2y$10$RerKpkmjU2TT5wfLec1dj.fo3RTsHvWZUuxrG1LD9gv1A5L2NM2AO', 3, 'DEFAULT', 1),
('EAS015', 'REGINARLD', 'R', 'MONKO', 'Male', 'reginarldmonko@everlasting.academy', 36, '$2y$10$F0RFJNnc3/O5J0dQetPGrOc8gdBmrhpAdjQ47NBVKJJWIUgc5wo1i', 3, 'DEFAULT', 1),
('EAS016', 'ETHAN', 'F', 'MAYERE', 'Male', 'ethanmayere@everlasting.academy', 36, '$2y$10$9ggy/OrbZblS84EJfVekvuH1GcbYx5ktp6p6unnR/AJp5aJg1VVDa', 3, 'DEFAULT', 1),
('EAS017', 'SHAMMAH', 'P', 'BANEBE', 'Female', 'shammahbanebe@everlasting.academy', 36, '$2y$10$BA3xh4qR2PdIou3g5pCyFujZ1J8K0IThjbSbFVh4UVQWFQ9rPHrHK', 3, 'DEFAULT', 1),
('EAS018', 'ESTHER', 'C', 'NYAKIHA', 'Female', 'esthernyakiha@everlasting.academy', 37, '$2y$10$y5u7cjjTFNV6d8HY9gC3xOGuTcpLpmATSIEtLtvPJo5kKY0sgGivG', 3, 'DEFAULT', 1),
('EAS019', 'SILATIAN', 'G', 'LUKUMAY', 'Female', 'silatianlukumay@everlasting.academy', 37, '$2y$10$9SnhyAGApSYAEAD9G2rXsOMiCpIIoisab2m9X5csUOWID/KcqSAce', 3, 'DEFAULT', 1),
('EAS020', 'IVAN', 'D', 'STEPHEN', 'Male', 'ivanstephen@everlasting.academy', 29, '$2y$10$xIr49uuNAOY7NGTjphtWk.KRxMMKb/AhjiLEAtWehPAchKvE2CJcq', 3, 'DEFAULT', 1),
('EAS021', 'EMMANUEL', 'J', 'KASIGWA', 'Male', 'emmanuelkasigwa@everlasting.academy', 29, '$2y$10$55xMq3Orh7AwvhabC2EmJeQ8lnt6VaUeYNsqorOzj6cgx1X//2ddy', 3, 'DEFAULT', 1),
('EAS022', 'ANATHA', 'K', 'KIENDENDE', 'Female', 'anathakiendende@everlasting.academy', 29, '$2y$10$8/IKNW2HtEL9god.oaHBSulaCOQ/M7ZL7JU7TSfFRr.RJKf1M/Ke6', 3, 'DEFAULT', 1),
('EAS023', 'CLINTON', 'A', 'MANDARI', 'Male', 'clintonmandari@everlasting.academy', 29, '$2y$10$Q3s9S4QTVLQSobTplHmrkeGoWxGsGLaRHMmbcdmVIaaIBye5jRhTS', 3, 'DEFAULT', 1),
('EAS024', 'LORRAINE', 'H', 'TEMBA', 'Female', 'lorrainetemba@everlasting.academy', 35, '$2y$10$SmAavd5UuW/yFSr98r6dJ.OH9T9MF7J5lUyPO/zhSyhGTbuhjpgD.', 3, 'DEFAULT', 1);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_student_fee_accounts`
--

CREATE TABLE `tbl_student_fee_accounts` (
  `id` int(11) NOT NULL,
  `student_id` varchar(20) NOT NULL,
  `class_id` int(11) NOT NULL,
  `academic_year` varchar(20) NOT NULL,
  `opening_balance` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_expected` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_paid` decimal(12,2) NOT NULL DEFAULT '0.00',
  `discount_total` decimal(12,2) NOT NULL DEFAULT '0.00',
  `waiver_total` decimal(12,2) NOT NULL DEFAULT '0.00',
  `balance_due` decimal(12,2) NOT NULL DEFAULT '0.00',
  `status` enum('pending','partial','cleared','overdue','exempted') NOT NULL DEFAULT 'pending',
  `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_subjects`
--

CREATE TABLE `tbl_subjects` (
  `id` int(11) NOT NULL,
  `name` varchar(90) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=Active, 0=Archived',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `tbl_subjects`
--

INSERT INTO `tbl_subjects` (`id`, `name`, `status`, `created_at`, `updated_at`) VALUES
(3, 'Mathematics', 1, '2026-03-22 16:25:04', '2026-03-22 16:25:04'),
(4, 'English', 1, '2026-03-22 16:25:04', '2026-03-22 16:25:04'),
(5, 'Kiswahili', 1, '2026-03-22 16:25:04', '2026-03-22 16:25:04'),
(6, 'Geography', 1, '2026-03-22 16:25:04', '2026-03-22 16:25:04'),
(7, 'History', 1, '2026-03-22 16:25:04', '2026-03-22 16:25:04'),
(10, 'Physics', 1, '2026-03-22 16:25:04', '2026-03-22 16:25:04'),
(15, 'Computer Studies', 1, '2026-03-22 16:25:04', '2026-03-22 16:25:04'),
(16, 'Literature Language', 1, '2026-03-22 16:25:04', '2026-03-22 16:25:04'),
(20, 'Chinese', 1, '2026-03-22 16:25:04', '2026-03-22 16:25:04'),
(21, 'Biology', 1, '2026-03-22 16:25:04', '2026-03-22 16:25:04'),
(22, 'Coding', 1, '2026-03-22 16:25:04', '2026-03-22 16:25:04'),
(23, 'Chemistry', 1, '2026-03-22 16:27:56', '2026-03-22 16:28:35'),
(25, 'Bussiness Studies', 1, '2026-03-23 07:02:53', '2026-03-23 07:02:53'),
(26, 'Book Keeping', 1, '2026-03-23 07:05:24', '2026-03-23 07:05:24'),
(27, 'Commerce', 1, '2026-03-23 07:05:32', '2026-03-23 07:05:32'),
(28, 'HTM', 1, '2026-03-23 07:50:49', '2026-03-23 07:50:49'),
(29, 'ICS', 1, '2026-03-25 11:10:20', '2026-03-25 11:10:20'),
(30, 'CIVICS', 1, '2026-03-25 11:16:39', '2026-03-25 11:16:39'),
(31, 'HISTORIA YA TANZANIA NA MAADILI', 1, '2026-05-24 07:13:10', '2026-05-24 07:13:10'),
(32, 'ACADEMIC COMMUNICATION', 1, '2026-05-24 07:13:19', '2026-05-24 07:13:19'),
(33, 'ACCOUNT', 1, '2026-09-09 11:20:32', '2026-09-09 11:20:32'),
(34, 'ECONOMICS', 1, '2026-09-09 11:20:42', '2026-09-09 11:20:42'),
(35, 'BUSSINESS', 1, '2026-09-09 11:20:52', '2026-09-09 11:20:52'),
(36, 'BAM', 1, '2026-09-09 11:26:11', '2026-09-09 11:26:11');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_subject_combinations`
--

CREATE TABLE `tbl_subject_combinations` (
  `id` int(11) NOT NULL,
  `class` text,
  `subject` int(11) NOT NULL,
  `teacher` int(11) NOT NULL,
  `reg_date` datetime NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `tbl_subject_combinations`
--

INSERT INTO `tbl_subject_combinations` (`id`, `class`, `subject`, `teacher`, `reg_date`, `status`) VALUES
(78, 'a:5:{i:0;s:2:\"28\";i:1;s:2:\"29\";i:2;s:2:\"32\";i:3;s:2:\"31\";i:4;s:2:\"30\";}', 32, 56, '2026-05-24 04:14:40', 1),
(79, 'a:3:{i:0;s:2:\"28\";i:1;s:2:\"29\";i:2;s:2:\"32\";}', 7, 37, '2026-05-24 04:15:05', 1),
(80, 'a:1:{i:0;s:2:\"29\";}', 16, 38, '2026-05-24 04:15:22', 1),
(81, 'a:2:{i:0;s:2:\"31\";i:1;s:2:\"30\";}', 10, 45, '2026-05-24 04:15:43', 1),
(82, 'a:1:{i:0;s:2:\"30\";}', 3, 50, '2026-05-24 04:16:07', 1),
(83, 'a:1:{i:0;s:2:\"30\";}', 15, 43, '2026-05-24 04:16:20', 1),
(84, 'a:1:{i:0;s:2:\"29\";}', 6, 35, '2026-06-13 05:41:15', 1),
(85, 'a:1:{i:0;s:2:\"29\";}', 28, 40, '2026-06-13 05:41:37', 1),
(86, 'a:1:{i:0;s:2:\"32\";}', 5, 37, '2026-06-13 05:44:48', 1),
(87, 'a:1:{i:0;s:2:\"32\";}', 28, 40, '2026-06-13 05:45:08', 1),
(88, 'a:1:{i:0;s:2:\"32\";}', 4, 38, '2026-06-13 05:46:43', 1),
(89, 'a:1:{i:0;s:2:\"30\";}', 28, 37, '2026-06-13 05:49:52', 1),
(90, 'a:1:{i:0;s:2:\"31\";}', 21, 46, '2026-06-13 05:51:35', 1),
(91, 'a:1:{i:0;s:2:\"31\";}', 23, 45, '2026-06-13 05:51:50', 1),
(92, 'a:1:{i:0;s:2:\"31\";}', 28, 37, '2026-06-13 05:52:04', 1),
(93, 'a:1:{i:0;s:2:\"33\";}', 23, 45, '2026-06-13 06:05:40', 1),
(94, 'a:1:{i:0;s:2:\"33\";}', 10, 45, '2026-06-13 06:06:01', 1),
(95, 'a:1:{i:0;s:2:\"33\";}', 28, 37, '2026-06-13 06:06:15', 1),
(96, 'a:1:{i:0;s:2:\"33\";}', 3, 50, '2026-06-13 06:06:38', 1),
(97, 'a:1:{i:0;s:2:\"33\";}', 32, 38, '2026-06-13 06:07:04', 1),
(98, 'a:4:{i:0;s:2:\"36\";i:1;s:2:\"35\";i:2;s:2:\"34\";i:3;s:2:\"37\";}', 32, 35, '2026-09-09 08:07:50', 1),
(99, 'a:1:{i:0;s:2:\"34\";}', 6, 34, '2026-09-09 08:08:20', 1),
(100, 'a:1:{i:0;s:2:\"35\";}', 15, 43, '2026-09-09 08:08:40', 1),
(101, 'a:1:{i:0;s:2:\"35\";}', 3, 45, '2026-09-09 08:09:05', 1),
(102, 'a:1:{i:0;s:2:\"36\";}', 33, 55, '2026-09-09 08:21:07', 1),
(103, 'a:1:{i:0;s:2:\"36\";}', 35, 36, '2026-09-09 08:21:30', 1),
(104, 'a:3:{i:0;s:2:\"36\";i:1;s:2:\"35\";i:2;s:2:\"34\";}', 34, 36, '2026-09-09 08:21:50', 1),
(105, 'a:6:{i:0;s:2:\"36\";i:1;s:2:\"37\";i:2;s:2:\"28\";i:3;s:2:\"29\";i:4;s:2:\"32\";i:5;s:2:\"31\";}', 36, 45, '2026-09-09 08:26:50', 1),
(106, 'a:1:{i:0;s:2:\"35\";}', 28, 37, '2026-09-09 08:47:58', 1),
(107, 'a:1:{i:0;s:2:\"34\";}', 28, 37, '2026-09-09 08:49:39', 1),
(108, 'a:1:{i:0;s:2:\"34\";}', 3, 45, '2026-09-09 08:50:39', 1),
(109, 'a:1:{i:0;s:2:\"36\";}', 28, 37, '2026-09-09 09:01:15', 1),
(110, 'a:1:{i:0;s:2:\"37\";}', 7, 35, '2026-09-09 09:07:04', 1),
(111, 'a:1:{i:0;s:2:\"37\";}', 34, 55, '2026-09-09 09:07:23', 1),
(112, 'a:1:{i:0;s:2:\"37\";}', 6, 34, '2026-09-09 09:07:49', 1),
(113, 'a:1:{i:0;s:2:\"37\";}', 28, 37, '2026-09-09 09:08:07', 1);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_system_settings`
--

CREATE TABLE `tbl_system_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `description` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `tbl_system_settings`
--

INSERT INTO `tbl_system_settings` (`id`, `setting_key`, `setting_value`, `description`) VALUES
(1, 'fee_module_enabled', '0', 'Show fee information to students (1=Yes, 0=No)');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_teaching_materials`
--

CREATE TABLE `tbl_teaching_materials` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL COMMENT 'Short title of the material',
  `instructions` text COMMENT 'Teacher instructions / description',
  `file_name` varchar(255) NOT NULL COMMENT 'Original file name',
  `file_path` varchar(500) NOT NULL COMMENT 'Server path to the file (not BLOB)',
  `file_size` int(11) DEFAULT '0' COMMENT 'File size in bytes',
  `mime_type` varchar(100) DEFAULT 'application/pdf',
  `class_id` int(11) NOT NULL COMMENT 'Which class this is for',
  `subject_combination_id` int(11) NOT NULL COMMENT 'Which subject (links to tbl_subject_combinations)',
  `teacher_id` int(11) NOT NULL COMMENT 'Who uploaded it (tbl_staff)',
  `term_id` int(11) NOT NULL COMMENT 'Academic term (tbl_terms)',
  `uploaded_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT '0 = hidden from students'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_terms`
--

CREATE TABLE `tbl_terms` (
  `id` int(11) NOT NULL,
  `name` varchar(90) NOT NULL,
  `status` int(11) NOT NULL DEFAULT '1' COMMENT '	0 = Disabled , 1 = Enabled',
  `results_editable` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `tbl_terms`
--

INSERT INTO `tbl_terms` (`id`, `name`, `status`, `results_editable`) VALUES
(15, 'FORM FIVE OPENING EXAM JANUARY', 1, 0),
(16, 'ANNUAL 2026', 0, 1),
(17, 'MIDTERM SEPTEMBER 2026', 1, 1),
(18, 'MONTHLY OCTOBER 2026', 1, 1),
(19, 'TERMINAL 2026', 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_video_materials`
--

CREATE TABLE `tbl_video_materials` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `instructions` text,
  `youtube_url` varchar(500) NOT NULL,
  `video_id` varchar(50) NOT NULL,
  `class_id` int(11) NOT NULL,
  `subject_combination_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `term_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_active` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `event_categories`
--
ALTER TABLE `event_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_announcements`
--
ALTER TABLE `tbl_announcements`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_bank_accounts`
--
ALTER TABLE `tbl_bank_accounts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_classes`
--
ALTER TABLE `tbl_classes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_division_system`
--
ALTER TABLE `tbl_division_system`
  ADD PRIMARY KEY (`division`);

--
-- Indexes for table `tbl_events`
--
ALTER TABLE `tbl_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `posted_by` (`posted_by`);

--
-- Indexes for table `tbl_exam_results`
--
ALTER TABLE `tbl_exam_results`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student` (`student`),
  ADD KEY `class` (`class`),
  ADD KEY `subject_combination` (`subject_combination`),
  ADD KEY `term` (`term`);

--
-- Indexes for table `tbl_fee_adjustments`
--
ALTER TABLE `tbl_fee_adjustments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `tbl_fee_audit_logs`
--
ALTER TABLE `tbl_fee_audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `actor_staff_id` (`actor_staff_id`);

--
-- Indexes for table `tbl_fee_invoices`
--
ALTER TABLE `tbl_fee_invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_no` (`invoice_no`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `term_id` (`term_id`),
  ADD KEY `generated_by` (`generated_by`);

--
-- Indexes for table `tbl_fee_invoice_lines`
--
ALTER TABLE `tbl_fee_invoice_lines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `invoice_id` (`invoice_id`);

--
-- Indexes for table `tbl_fee_items`
--
ALTER TABLE `tbl_fee_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fee_structure_id` (`fee_structure_id`);

--
-- Indexes for table `tbl_fee_payments`
--
ALTER TABLE `tbl_fee_payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `payment_ref` (`payment_ref`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `invoice_id` (`invoice_id`),
  ADD KEY `received_by` (`received_by`),
  ADD KEY `verified_by` (`verified_by`);

--
-- Indexes for table `tbl_fee_receipts`
--
ALTER TABLE `tbl_fee_receipts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `receipt_no` (`receipt_no`),
  ADD KEY `payment_id` (`payment_id`),
  ADD KEY `printed_by` (`printed_by`);

--
-- Indexes for table `tbl_fee_structures`
--
ALTER TABLE `tbl_fee_structures`
  ADD PRIMARY KEY (`id`),
  ADD KEY `class_id` (`class_id`);

--
-- Indexes for table `tbl_grade_system`
--
ALTER TABLE `tbl_grade_system`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_images`
--
ALTER TABLE `tbl_images`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `image_type` (`image_type`);

--
-- Indexes for table `tbl_login_sessions`
--
ALTER TABLE `tbl_login_sessions`
  ADD PRIMARY KEY (`session_key`),
  ADD KEY `staff` (`staff`),
  ADD KEY `student` (`student`);

--
-- Indexes for table `tbl_notification_history`
--
ALTER TABLE `tbl_notification_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sent_by` (`sent_by`);

--
-- Indexes for table `tbl_password_resets`
--
ALTER TABLE `tbl_password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `tbl_promotion_log`
--
ALTER TABLE `tbl_promotion_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `tbl_quizzes`
--
ALTER TABLE `tbl_quizzes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `subject_combination_id` (`subject_combination_id`),
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `term_id` (`term_id`);

--
-- Indexes for table `tbl_quiz_answers`
--
ALTER TABLE `tbl_quiz_answers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `submission_id` (`submission_id`),
  ADD KEY `question_id` (`question_id`),
  ADD KEY `selected_option_id` (`selected_option_id`);

--
-- Indexes for table `tbl_quiz_options`
--
ALTER TABLE `tbl_quiz_options`
  ADD PRIMARY KEY (`id`),
  ADD KEY `question_id` (`question_id`);

--
-- Indexes for table `tbl_quiz_questions`
--
ALTER TABLE `tbl_quiz_questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `quiz_id` (`quiz_id`);

--
-- Indexes for table `tbl_quiz_submissions`
--
ALTER TABLE `tbl_quiz_submissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `quiz_id` (`quiz_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `tbl_result_change_requests`
--
ALTER TABLE `tbl_result_change_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `term_id` (`term_id`),
  ADD KEY `subject_combination_id` (`subject_combination_id`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Indexes for table `tbl_school`
--
ALTER TABLE `tbl_school`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_school_averages`
--
ALTER TABLE `tbl_school_averages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `term_id` (`term_id`);

--
-- Indexes for table `tbl_school_contacts`
--
ALTER TABLE `tbl_school_contacts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_smtp`
--
ALTER TABLE `tbl_smtp`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_staff`
--
ALTER TABLE `tbl_staff`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_students`
--
ALTER TABLE `tbl_students`
  ADD PRIMARY KEY (`id`),
  ADD KEY `class` (`class`);

--
-- Indexes for table `tbl_student_fee_accounts`
--
ALTER TABLE `tbl_student_fee_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_year` (`student_id`,`academic_year`),
  ADD KEY `class_id` (`class_id`);

--
-- Indexes for table `tbl_subjects`
--
ALTER TABLE `tbl_subjects`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_subject_combinations`
--
ALTER TABLE `tbl_subject_combinations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `class` (`class`(768)),
  ADD KEY `teacher` (`teacher`),
  ADD KEY `subject` (`subject`);

--
-- Indexes for table `tbl_system_settings`
--
ALTER TABLE `tbl_system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `tbl_teaching_materials`
--
ALTER TABLE `tbl_teaching_materials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `subject_combination_id` (`subject_combination_id`),
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `term_id` (`term_id`);

--
-- Indexes for table `tbl_terms`
--
ALTER TABLE `tbl_terms`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_video_materials`
--
ALTER TABLE `tbl_video_materials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `subject_combination_id` (`subject_combination_id`),
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `term_id` (`term_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `event_categories`
--
ALTER TABLE `event_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `tbl_announcements`
--
ALTER TABLE `tbl_announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `tbl_bank_accounts`
--
ALTER TABLE `tbl_bank_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tbl_classes`
--
ALTER TABLE `tbl_classes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `tbl_events`
--
ALTER TABLE `tbl_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_exam_results`
--
ALTER TABLE `tbl_exam_results`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=501;

--
-- AUTO_INCREMENT for table `tbl_fee_adjustments`
--
ALTER TABLE `tbl_fee_adjustments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_fee_audit_logs`
--
ALTER TABLE `tbl_fee_audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_fee_invoices`
--
ALTER TABLE `tbl_fee_invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_fee_invoice_lines`
--
ALTER TABLE `tbl_fee_invoice_lines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_fee_items`
--
ALTER TABLE `tbl_fee_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_fee_payments`
--
ALTER TABLE `tbl_fee_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_fee_receipts`
--
ALTER TABLE `tbl_fee_receipts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_fee_structures`
--
ALTER TABLE `tbl_fee_structures`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_grade_system`
--
ALTER TABLE `tbl_grade_system`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `tbl_images`
--
ALTER TABLE `tbl_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_notification_history`
--
ALTER TABLE `tbl_notification_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_password_resets`
--
ALTER TABLE `tbl_password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `tbl_promotion_log`
--
ALTER TABLE `tbl_promotion_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_quizzes`
--
ALTER TABLE `tbl_quizzes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_quiz_answers`
--
ALTER TABLE `tbl_quiz_answers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_quiz_options`
--
ALTER TABLE `tbl_quiz_options`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_quiz_questions`
--
ALTER TABLE `tbl_quiz_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_quiz_submissions`
--
ALTER TABLE `tbl_quiz_submissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_result_change_requests`
--
ALTER TABLE `tbl_result_change_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tbl_school`
--
ALTER TABLE `tbl_school`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tbl_school_averages`
--
ALTER TABLE `tbl_school_averages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_school_contacts`
--
ALTER TABLE `tbl_school_contacts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tbl_smtp`
--
ALTER TABLE `tbl_smtp`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tbl_staff`
--
ALTER TABLE `tbl_staff`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

--
-- AUTO_INCREMENT for table `tbl_student_fee_accounts`
--
ALTER TABLE `tbl_student_fee_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_subjects`
--
ALTER TABLE `tbl_subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `tbl_subject_combinations`
--
ALTER TABLE `tbl_subject_combinations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=114;

--
-- AUTO_INCREMENT for table `tbl_system_settings`
--
ALTER TABLE `tbl_system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tbl_teaching_materials`
--
ALTER TABLE `tbl_teaching_materials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_terms`
--
ALTER TABLE `tbl_terms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `tbl_video_materials`
--
ALTER TABLE `tbl_video_materials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `tbl_exam_results`
--
ALTER TABLE `tbl_exam_results`
  ADD CONSTRAINT `tbl_exam_results_ibfk_1` FOREIGN KEY (`student`) REFERENCES `tbl_students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_exam_results_ibfk_2` FOREIGN KEY (`class`) REFERENCES `tbl_classes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_exam_results_ibfk_3` FOREIGN KEY (`subject_combination`) REFERENCES `tbl_subject_combinations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_exam_results_ibfk_4` FOREIGN KEY (`term`) REFERENCES `tbl_terms` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbl_fee_adjustments`
--
ALTER TABLE `tbl_fee_adjustments`
  ADD CONSTRAINT `tbl_fee_adjustments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `tbl_students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tbl_fee_adjustments_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `tbl_staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_fee_audit_logs`
--
ALTER TABLE `tbl_fee_audit_logs`
  ADD CONSTRAINT `tbl_fee_audit_logs_ibfk_1` FOREIGN KEY (`actor_staff_id`) REFERENCES `tbl_staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_fee_invoices`
--
ALTER TABLE `tbl_fee_invoices`
  ADD CONSTRAINT `tbl_fee_invoices_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `tbl_students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tbl_fee_invoices_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `tbl_classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tbl_fee_invoices_ibfk_3` FOREIGN KEY (`term_id`) REFERENCES `tbl_terms` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tbl_fee_invoices_ibfk_4` FOREIGN KEY (`generated_by`) REFERENCES `tbl_staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_fee_invoice_lines`
--
ALTER TABLE `tbl_fee_invoice_lines`
  ADD CONSTRAINT `tbl_fee_invoice_lines_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `tbl_fee_invoices` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_fee_items`
--
ALTER TABLE `tbl_fee_items`
  ADD CONSTRAINT `tbl_fee_items_ibfk_1` FOREIGN KEY (`fee_structure_id`) REFERENCES `tbl_fee_structures` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_fee_payments`
--
ALTER TABLE `tbl_fee_payments`
  ADD CONSTRAINT `tbl_fee_payments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `tbl_students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tbl_fee_payments_ibfk_2` FOREIGN KEY (`invoice_id`) REFERENCES `tbl_fee_invoices` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tbl_fee_payments_ibfk_3` FOREIGN KEY (`received_by`) REFERENCES `tbl_staff` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tbl_fee_payments_ibfk_4` FOREIGN KEY (`verified_by`) REFERENCES `tbl_staff` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `tbl_fee_receipts`
--
ALTER TABLE `tbl_fee_receipts`
  ADD CONSTRAINT `tbl_fee_receipts_ibfk_1` FOREIGN KEY (`payment_id`) REFERENCES `tbl_fee_payments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tbl_fee_receipts_ibfk_2` FOREIGN KEY (`printed_by`) REFERENCES `tbl_staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_fee_structures`
--
ALTER TABLE `tbl_fee_structures`
  ADD CONSTRAINT `tbl_fee_structures_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `tbl_classes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_login_sessions`
--
ALTER TABLE `tbl_login_sessions`
  ADD CONSTRAINT `tbl_login_sessions_ibfk_1` FOREIGN KEY (`staff`) REFERENCES `tbl_staff` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_login_sessions_ibfk_2` FOREIGN KEY (`student`) REFERENCES `tbl_students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbl_notification_history`
--
ALTER TABLE `tbl_notification_history`
  ADD CONSTRAINT `tbl_notification_history_ibfk_1` FOREIGN KEY (`sent_by`) REFERENCES `tbl_staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_quizzes`
--
ALTER TABLE `tbl_quizzes`
  ADD CONSTRAINT `fk_quizzes_class` FOREIGN KEY (`class_id`) REFERENCES `tbl_classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_quizzes_subject_comb` FOREIGN KEY (`subject_combination_id`) REFERENCES `tbl_subject_combinations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_quizzes_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `tbl_staff` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_quizzes_term` FOREIGN KEY (`term_id`) REFERENCES `tbl_terms` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_quiz_answers`
--
ALTER TABLE `tbl_quiz_answers`
  ADD CONSTRAINT `fk_answers_option` FOREIGN KEY (`selected_option_id`) REFERENCES `tbl_quiz_options` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_answers_question` FOREIGN KEY (`question_id`) REFERENCES `tbl_quiz_questions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_answers_submission` FOREIGN KEY (`submission_id`) REFERENCES `tbl_quiz_submissions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_quiz_options`
--
ALTER TABLE `tbl_quiz_options`
  ADD CONSTRAINT `fk_options_question` FOREIGN KEY (`question_id`) REFERENCES `tbl_quiz_questions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_quiz_questions`
--
ALTER TABLE `tbl_quiz_questions`
  ADD CONSTRAINT `fk_questions_quiz` FOREIGN KEY (`quiz_id`) REFERENCES `tbl_quizzes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_quiz_submissions`
--
ALTER TABLE `tbl_quiz_submissions`
  ADD CONSTRAINT `fk_submissions_quiz` FOREIGN KEY (`quiz_id`) REFERENCES `tbl_quizzes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_submissions_student` FOREIGN KEY (`student_id`) REFERENCES `tbl_students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_school_averages`
--
ALTER TABLE `tbl_school_averages`
  ADD CONSTRAINT `tbl_school_averages_ibfk_1` FOREIGN KEY (`term_id`) REFERENCES `tbl_terms` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbl_students`
--
ALTER TABLE `tbl_students`
  ADD CONSTRAINT `tbl_students_ibfk_1` FOREIGN KEY (`class`) REFERENCES `tbl_classes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbl_student_fee_accounts`
--
ALTER TABLE `tbl_student_fee_accounts`
  ADD CONSTRAINT `tbl_student_fee_accounts_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `tbl_students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tbl_student_fee_accounts_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `tbl_classes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_subject_combinations`
--
ALTER TABLE `tbl_subject_combinations`
  ADD CONSTRAINT `tbl_subject_combinations_ibfk_2` FOREIGN KEY (`subject`) REFERENCES `tbl_subjects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_subject_combinations_ibfk_3` FOREIGN KEY (`teacher`) REFERENCES `tbl_staff` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbl_teaching_materials`
--
ALTER TABLE `tbl_teaching_materials`
  ADD CONSTRAINT `fk_materials_class` FOREIGN KEY (`class_id`) REFERENCES `tbl_classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_materials_subject_comb` FOREIGN KEY (`subject_combination_id`) REFERENCES `tbl_subject_combinations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_materials_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `tbl_staff` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_materials_term` FOREIGN KEY (`term_id`) REFERENCES `tbl_terms` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_video_materials`
--
ALTER TABLE `tbl_video_materials`
  ADD CONSTRAINT `fk_videos_class` FOREIGN KEY (`class_id`) REFERENCES `tbl_classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_videos_subject_comb` FOREIGN KEY (`subject_combination_id`) REFERENCES `tbl_subject_combinations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_videos_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `tbl_staff` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_videos_term` FOREIGN KEY (`term_id`) REFERENCES `tbl_terms` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
