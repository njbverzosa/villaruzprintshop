-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Aug 24, 2026 at 12:35 AM
-- Server version: 11.8.8-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u408983097_Villaruz`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `registered_at` varchar(255) DEFAULT NULL,
  `acc_number` varchar(50) NOT NULL,
  `f_name` varchar(255) DEFAULT NULL,
  `user_name` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'Admin',
  `authorize_access` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 0,
  `profile` varchar(255) NOT NULL DEFAULT 'profile.jpg',
  `last_login_date` varchar(255) DEFAULT NULL,
  `text_pass` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `registered_at`, `acc_number`, `f_name`, `user_name`, `password`, `phone_number`, `email`, `role`, `authorize_access`, `status`, `profile`, `last_login_date`, `text_pass`) VALUES
(1, '09 June 2026', '4819', 'Norlie Jay Verzosa', 'System Admin', '$2y$10$RlEcBVB6XPQbSDo30D3gDeu5qG2dmYpAjotEr5QFR9hptCnwyCSL2', '09455374819', 'njbverzosa@gmail.com', 'Developer', 0, 1, 'profile.jpg', 'Mon, 24 Aug 2026 8:34 AM', 'Nj_Verzosa@24'),
(2, '09 June 2026', '3145', 'Joseph Migano Villaruz', 'Owner', '$2y$10$MLRQ6VYROOv/G6s7AlbP0OVf5XlM3K/9TzrinC86OBFiehU.liE9W', '09216583145', 'joseph101474@gmail.com', 'Ceo', 1, 1, 'profile.jpg', 'Mon, 24 Aug 2026 8:14 AM', 'Joseph0211@'),
(3, '10 June 2026', '8156', 'Corina Magno Verzosa', 'Admin', '$2y$10$.ZZ6S2IDfZwlnXpcaW/rS.tFuaD7g7EGtCYc7SV0W59cxLLunQWiK', '09943108156', 'corinamagno496@gmail.com', 'Admin', 2, 1, 'profile.jpg', 'Sat, 22 Aug 2026 3:50 PM', 'Magnocorina#23');

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `id` int(11) NOT NULL,
  `acc_number` varchar(50) NOT NULL,
  `order_number` varchar(50) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `unit` varchar(100) DEFAULT NULL,
  `selling_price` varchar(255) DEFAULT NULL,
  `pieces` int(11) NOT NULL,
  `date_time_add` varchar(50) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `cart`
--

INSERT INTO `cart` (`id`, `acc_number`, `order_number`, `product_name`, `unit`, `selling_price`, `pieces`, `date_time_add`, `total_amount`) VALUES
(187, '3173', '3173_00001_19 May 2026', 'Soft broom (walis tambo)', 'Pcs', '250', 1, '19 May 2026', 250.00),
(188, '3173', '3173_00001_19 May 2026', 'Dust pan', 'Pcs', '65', 1, '19 May 2026', 65.00),
(556, '5576', '5576_00001_22 Jul 2026', 'Epson Ink 003 (Set)', 'Set', '1150', 4, '22 Jul 2026', 4600.00),
(557, '5576', '5576_00001_22 Jul 2026', 'Epson Ink 003 Black', 'Bottles', ' 280', 4, '22 Jul 2026', 1120.00);

-- --------------------------------------------------------

--
-- Table structure for table `chat_account`
--

CREATE TABLE `chat_account` (
  `id` int(11) NOT NULL,
  `acc_number` varchar(50) NOT NULL,
  `chat_sent` datetime DEFAULT current_timestamp(),
  `status` tinyint(1) DEFAULT 1 COMMENT '0=blocked, 1=unblocked'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;

--
-- Dumping data for table `chat_account`
--

INSERT INTO `chat_account` (`id`, `acc_number`, `chat_sent`, `status`) VALUES
(1, '0466', '2026-08-23 14:54:00', 1);

-- --------------------------------------------------------

--
-- Table structure for table `chat_conversation`
--

CREATE TABLE `chat_conversation` (
  `id` int(11) NOT NULL,
  `acc_number` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `time` time DEFAULT curtime(),
  `date` date DEFAULT curdate(),
  `status` tinyint(1) DEFAULT 0 COMMENT '0=unread, 1=read',
  `sender_type` enum('customer','admin') NOT NULL DEFAULT 'customer',
  `receiver_acc` varchar(50) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `chat_conversation`
--

INSERT INTO `chat_conversation` (`id`, `acc_number`, `message`, `time`, `date`, `status`, `sender_type`, `receiver_acc`, `created_at`) VALUES
(1, '0466', 'Hello, good evening po', '14:53:44', '2026-08-23', 0, 'customer', '4819', '2026-08-23 14:53:44'),
(2, '0466', 'hello po', '14:54:00', '2026-08-23', 0, 'customer', '4819', '2026-08-23 14:54:00');

-- --------------------------------------------------------

--
-- Table structure for table `contracts`
--

CREATE TABLE `contracts` (
  `id` int(255) NOT NULL,
  `contractor` varchar(255) DEFAULT NULL,
  `contract_m_y` varchar(255) DEFAULT NULL,
  `contract_address` varchar(255) DEFAULT NULL,
  `contract_value` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;

--
-- Dumping data for table `contracts`
--

INSERT INTO `contracts` (`id`, `contractor`, `contract_m_y`, `contract_address`, `contract_value`) VALUES
(1, 'Lilymae Calixtro', 'May 2026', 'Osmeña Brgy. Council', '35000'),
(2, 'ENHS', 'May 2026', 'Eguia Dasol Pangasinan', '49100');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `registered_at` varchar(255) DEFAULT NULL,
  `acc_number` varchar(50) NOT NULL,
  `online_time` varchar(50) DEFAULT NULL,
  `f_name` varchar(255) DEFAULT 'Guest',
  `password` varchar(255) NOT NULL,
  `text_pass` text DEFAULT NULL,
  `phone_number` varchar(20) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'Customer',
  `status` tinyint(1) NOT NULL DEFAULT 0,
  `active_email` tinyint(1) NOT NULL DEFAULT 0,
  `otp_code` int(255) DEFAULT NULL,
  `profile` varchar(255) NOT NULL DEFAULT 'profile.jpg',
  `last_login_date` varchar(255) DEFAULT NULL,
  `street` varchar(255) DEFAULT NULL,
  `barangay` varchar(255) DEFAULT NULL,
  `land_mark` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `registered_at`, `acc_number`, `online_time`, `f_name`, `password`, `text_pass`, `phone_number`, `email`, `role`, `status`, `active_email`, `otp_code`, `profile`, `last_login_date`, `street`, `barangay`, `land_mark`) VALUES
(1, 'Tue, 14 Apr 2026 7:09 PM', '8156', NULL, 'Customer Assistant PO-1', '$2y$10$.iZ27o7rq8gnoFlEG7WP3.oUwWWrRf7skykGym2plUOYm83q6wn1G', 'Magnocorina#23', '09943108156', 'corinamagno496@gmail.com', 'Customer', 1, 1, NULL, 'profile.jpg', 'Sat, 15 Aug 2026 2:48 AM', NULL, '0', NULL),
(2, 'Wed, 15 Apr 2026 4:08 PM', '3874', NULL, 'Don Juan Bernal Sr ES', '$2y$10$LgfhrONHbwptwwqX2I1az.xdt79uxnlrGJZFfaP.osIqIGTSOlPMi', 'Dj@101445', '09274383874', 'lieziel.pobletin@deped.gov.ph', 'Customer', 0, 1, NULL, 'profile.jpg', 'Thu, 16 Apr 2026 5:29 AM', NULL, '0', NULL),
(3, 'Sat, 18 Apr 2026 5:39 PM', '4291', NULL, 'Cabinuangan ES', '$2y$10$MU5DTEFgwuipYmGDK9RdfeRitbT5w2oa.uc8vjYKu6DsPEA9Ek7EC', 'CabinuanganES@101517', '09209084291', 'Ryan.delarosa005@deped.gov.ph', 'Customer', 0, 1, 940090, 'profile.jpg', 'Sat, 18 Apr 2026 10:12 AM', NULL, '0', NULL),
(4, 'Sun, 19 Apr 2026 1:15 PM', '3412', NULL, 'Abner Abordo Jr', '$2y$10$Xcu0jABdz.O9NY1E4wmV1u/q78HcTByemJVQjc40lWXNAjcy.eAWO', 'iloveJesus1.', '09064683412', 'sirenping16@gmail.com', 'Customer', 0, 1, 254941, 'profile.jpg', NULL, NULL, '0', NULL),
(5, 'Tue, 21 Apr 2026 6:46 PM', '8382', NULL, 'Eksena Ocampo', '$2y$10$DB3CG29.WuSU1RTt3Aan7.NioFU4wyGecfqz2N95XR7eYQSbKifoO', 'Kh@ratz1017', '09989848382', 'emiliaocampo2@gmail.com', 'Customer', 0, 1, 238393, 'profile.jpg', 'Tue, 21 Apr 2026 11:18 AM', NULL, '0', NULL),
(6, 'Sat, 25 Apr 2026 8:51 AM', '4266', NULL, 'Princess Maria Juanita D Garcia', '$2y$10$ZyutCaWUCWfVx/kG.p3TtOu88NyQTx.4Kmf0S6GBwd2iCmtm3W7jS', 'Princess@21', '09129604266', 'garcia.princessmj@gmail.com', 'Customer', 0, 1, 441802, 'profile.jpg', 'Sun, 23 Aug 2026 11:38 PM', 'Barlo Millsite', 'Barlo', 'Surod ES'),
(7, 'Wed, 29 Apr 2026 10:15 PM', '4502', NULL, 'Ellen Magno Diaz', '$2y$10$47Ccll3XvFzVnUijANTsq.RK3f56Z12T2kbLWIaVunZFTwv5PCNqi', 'Tjellen2717!', '09307744502', 'graciamagno25@gmail.com', 'Customer', 0, 1, 939420, 'profile.jpg', 'Wed, 29 Apr 2026 2:16 PM', NULL, '0', NULL),
(8, 'Fri, 15 May 2026 7:24 AM', '2670', NULL, 'Jackielou Dacones', '$2y$10$l6e6usEUeezVDlS/jYiSM.phaLZSpKT0Q.eB5meUn56wppMBmKudu', 'June232018#', '09297982670', 'jackieloudacones25@gmail.com', 'Customer', 0, 1, 445418, 'profile.jpg', 'Tue, 21 Jul 2026 7:59 AM', NULL, '0', NULL),
(9, 'Mon, 18 May 2026 5:08 PM', '3173', NULL, 'Catherine Leonin', '$2y$10$yZU1jIDFJ30BgrmkdIcDLuz9byIT.09WD0VdlBK6BIKMMfcFsup1.', 'Catherine@23', '09483753173', 'catherine.leonin@deped.gov.ph', 'Customer', 0, 1, NULL, 'profile.jpg', 'Tue, 19 May 2026 12:34 PM', NULL, '0', NULL),
(10, 'Tue, 9 Jun 2026 11:22 AM', '5576', NULL, 'Clarisse Chan', '$2y$10$kgekrZeGukoKIfMAQoWGQ.aewJqUdG6pmUNLNIhtua2LMLFxT60f2', 'Cchan@14', '09685145576', 'miganoclarisse@gmail.com', 'Customer', 0, 1, NULL, 'profile.jpg', 'Tue, 11 Aug 2026 2:12 AM', NULL, '0', NULL),
(11, 'Wed, 15 Jul 2026 12:29 PM', '8344', NULL, 'Frances Mae Sabado', '$2y$10$20YsAiiyLsrHVmRIW/kb4u17uXn8dWuVLzMZtikfrEyTUPKkohSxS', 'Ces.101515', '09703788344', 'frances.sabado@gmail.com', 'Customer', 0, 1, 467947, 'profile.jpg', 'Thu, 16 Jul 2026 10:52 PM', NULL, '0', NULL),
(12, 'Mon, 3 Aug 2026 7:24 PM', '3145', NULL, 'Joseph Villaruz', '$2y$10$mBHGPRKtJUNZUEvNAwDT/uhtKOswoMIvauVOxic28biJwAxKPtQzO', 'Joseph0211@', '09216583145', 'joseph101474@gmail.com', 'Customer', 1, 1, 870187, 'profile.jpg', 'Tue, 18 Aug 2026 12:59 AM', NULL, '0', NULL),
(15, 'Fri, 21 Aug 2026 4:32 PM', '0466', '8:20 AM', 'Norlie Jay Verzosa', '$2y$10$Y2T5z9n7jv2VFVLIbDmefexqi87wQa8GMl0mF/78XDyQdP3SdvE2G', 'Admin_PO@24', '09956340466', 'njbverzosa@gmail.com', 'Customer', 1, 1, NULL, 'profile.jpg', 'Mon, 24 Aug 2026 8:20 AM', 'Purok 5 Cristoval Street', 'Poblacion', 'Near Nonat Store');

-- --------------------------------------------------------

--
-- Table structure for table `for_deliveries`
--

CREATE TABLE `for_deliveries` (
  `id` int(11) NOT NULL,
  `acc_number` varchar(50) NOT NULL,
  `ordered_by` varchar(255) NOT NULL,
  `delivery_number` varchar(50) NOT NULL,
  `delivery_address` varchar(255) DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `barangay` varchar(255) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `charge` varchar(255) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'PENDING',
  `date_time_sold` varchar(255) NOT NULL,
  `delivery_date` varchar(255) DEFAULT NULL,
  `delivery_m_y` varchar(255) DEFAULT NULL,
  `settled_at` varchar(50) DEFAULT NULL,
  `qr_code` text DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `for_deliveries`
--

INSERT INTO `for_deliveries` (`id`, `acc_number`, `ordered_by`, `delivery_number`, `delivery_address`, `city`, `barangay`, `total_amount`, `charge`, `status`, `date_time_sold`, `delivery_date`, `delivery_m_y`, `settled_at`, `qr_code`, `note`) VALUES
(1, '2368', 'San Vicente, Burgos, Pangasinan', 'DEL-20260415-0002', 'San Vicente, Burgos, Pangasinan', 'Burgos', 'San Vicente', 24990.00, '0', 'PAID', '17 Apr 2026', 'Mon, 20 April 2026', 'April 2026', NULL, 'http://localhost/delivery_receipt.php?delivery_number=DEL-20260415-0002', NULL),
(2, '2368', 'Barlo, Mabini, Pangasinan', 'DEL-20260416-0001', 'Barlo, Mabini, Pangasinan', 'Mabini', 'Barlo', 25600.00, '0', 'PAID', '17 Apr 2026', 'Mon, 20 April 2026', 'April 2026', NULL, 'http://localhost/delivery_receipt.php?delivery_number=DEL-20260416-0001', NULL),
(3, '4291', 'Cabinuangan Elementary School', 'DEL-20260418-0001', 'Purok 1, Cabinuangan, Mabini, Pangasinan', 'Mabini', 'Poblacion', 28900.00, '0', 'PAID', '18 Apr 2026', 'Mon, 20 April 2026', 'April 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=DEL-20260418-0001', NULL),
(4, '2368', 'Eguia National High School', 'DEL-20260421-0001', 'Eguia Dasol Pangasinan', 'Dasol', 'Eguia', 15167.00, '0', 'PAID', '22 Apr 2026', 'Thu, 23 April 2026', 'April 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=DEL-20260421-0001', NULL),
(5, '8156', 'Anapao Elementary School', 'DEL-20260422-0001', 'Anapao, Burgos Pangasinan', '', 'Anapo', 5300.00, '0', 'PAID', '22 Apr 2026', 'Thu, 23 April 2026', 'April 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=DEL-20260422-0001', NULL),
(21, '4266', 'Surod Elementary School', 'DEL-20260425-0001', 'Barlo Millsite', '', 'Barlo', 19900.00, '0', 'PAID', '25 Apr 2026', 'Mon, 27 April 2026', 'April 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=DEL-20260425-0001', NULL),
(22, '8156', 'Eguia National High School', 'DEL-20260504-0001', 'Eguia Dasol Pangasinan', '', 'Eguia', 11354.00, '0', 'PAID', '4 May 2026', 'Mon, 4 May 2026', 'May 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=DEL-20260504-0001', NULL),
(23, '2670', 'BARLO INTEGRATED SCHOOL', 'VPSGM000001', 'PUROK 1', 'MABINI PANGASINAN', 'BARLO', 19054.00, '0', 'PAID', '15 May 2026', 'Wed, 20 May 2026', 'May 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000001', NULL),
(24, '2670', 'BARLO INTEGRATED SCHOOL', 'VPSGM000002', 'PUROK 1', 'MABINI PANGASINAN', 'BARLO', 9602.00, '0', 'PAID', '15 May 2026', 'Wed, 20 May 2026', 'May 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000002', NULL),
(25, '2670', 'BARLO INTEGRATED SCHOOL', 'VPSGM000003', 'PUROK 1', 'MABINI PANGASINAN', 'BARLO', 17170.00, '0', 'PAID', '15 May 2026', 'Wed, 20 May 2026', 'May 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000003', NULL),
(30, '0022', 'Don Juan Bernal Sr. Elementary School', 'VPSGM000008', 'Don Juan Bernal Sr. Elementary School', 'Dasol', 'Hermosa', 30065.00, '0', 'PAID', '16 May 2026', 'Wed, 20 May 2026', 'May 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000008', NULL),
(31, '0022', 'Don Matias Elementary School', 'VPSGM000009', 'Don Matias Elementary School', 'Burgos', 'Don Matias', 7145.00, '0', 'PAID', '16 May 2026', 'Wed, 20 May 2026', 'May 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000009', NULL),
(32, '8156', 'Don Antonio Elementary School', 'VPSGM000010', 'Don Antonio Burgos Pangasinan', 'Burgos', 'Don Antonio', 12190.00, '0', 'PAID', '18 May 2026', 'Mon, 18 May 2026', 'May 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000010', NULL),
(33, '3173', 'Viga ES', 'VPSGM000011', 'Viga, Dasol,Pangasinan', 'Dasol', 'Viga', 24180.00, '0', 'PAID', '18 May 2026', 'Tue, 19 May 2026', 'May 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000011', NULL),
(34, '0022', 'EGUIA NATIONAL HIGH SCHOOL', 'VPSGM000012', 'EGUIA, DASOL PANGASINAN', 'DASOL', 'EGUIA', 30575.00, '0', 'PAID', '18 May 2026', 'Sun, 31 May 2026', 'May 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000012', NULL),
(35, '0022', 'SAPA PEQUEÑA ELEMENTARY SCHOOL', 'VPSGM000013', 'SAPA PEQUEÑA, BURGOS PANGASINAN', 'BURGOS', 'SAPA PEQUEÑA', 2925.00, '0', 'PAID', '1 June 2026', 'Mon, 1 June 2026', 'May 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000013', NULL),
(36, '0022', 'SAPA PEQUEÑA ELEMENTARY SCHOOL', 'VPSGM000014', 'SAPA PEQUEÑA, BURGOS PANGASINAN', 'BURGOS', 'SAPA PEQUEÑA', 24739.58, '0', 'PAID', '1 June 2026', 'Mon, 1 June 2026', 'May 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000014', NULL),
(37, '0022', 'Tambobong National High School', 'VPSGM000015', 'Tambobong National High School', 'Dasol', 'Tambobong', 27435.00, '0', 'PAID', '2 Jun 2026', 'Wed, 20 May 2026', 'May 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000015', NULL),
(38, '0022', 'Tambobong National High School', 'VPSGM000016', 'Tambobong National High School', 'Dasol', 'Tambobong', 27840.00, '0', 'PAID', '2 Jun 2026', 'Wed, 20 May 2026', 'May 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000016', NULL),
(39, '0022', 'Tambobong National High School', 'VPSGM000017', 'Tambobong National High School', 'Dasol', 'Tambobong', 30000.00, '0', 'PAID', '2 Jun 2026', 'Wed, 20 May 2026', 'May 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000017', NULL),
(40, '0022', 'EGUIA NATIONAL HIGH SCHOOL', 'VPSGM000018', 'EGUIA, DASOL PANGASINAN', NULL, NULL, 16550.00, '0', 'PAID', '5 Jun 2026', 'Thu, 4 June 2026', 'June 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000018', NULL),
(41, '0022', 'MAC ARTHUR SALANGA SR MEMORIAL E/S', 'VPSGM000019', 'OSMEÑA, DASOL PANGASINAN', NULL, NULL, 37109.38, '0', 'PAID', '5 Jun 2026', 'Mon, 8 June 2026', 'June 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000019', NULL),
(42, '0022', 'DON MATIAS ELEMENTARY SCHOOL', 'VPSGM000020', 'DON MATIAS, BURGOS PANGASINAN', NULL, NULL, 24739.58, '0', 'PAID', '5 Jun 2026', 'Wed, 10 June 2026', 'June 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000020', NULL),
(43, '0022', 'DON TIMOTEO NARABAL SR. E/S', 'VPSGM000021', 'ILIO-ILO, BURGOS, PANGASINAN', NULL, NULL, 28900.00, '0', 'PAID', '5 Jun 2026', 'Mon, 8 June 2026', 'June 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000021', NULL),
(44, '0022', 'ILIO-ILIO ELEMENTARY SCHOOL', 'VPSGM000022', 'ILIO-ILIO, BURGOS PANGASINAN', NULL, NULL, 4090.00, '0', 'PAID', '5 Jun 2026', 'Mon, 8 June 2026', 'June 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000022', NULL),
(45, '0022', 'TAMBOBONG NATIONAL HIGH SCHOOL', 'VPSGM000023', 'TAMBOBONG, DASOL, PANGASINAN', NULL, NULL, 9950.30, '0', 'PAID', '6 Jun 2026', 'Mon, 8 June 2026', 'June 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000023', NULL),
(46, '0022', 'TAMBOBONG NATIONAL HIGHB SCHOOL', 'VPSGM000024', 'TAMBOBONG, DASOL, PANGASINAN', NULL, NULL, 5509.00, '0', 'PAID', '6 Jun 2026', 'Mon, 8 June 2026', 'June 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000024', NULL),
(47, '0022', 'TAMBOBONG NATIONAL HIGH SCHOOL', 'VPSGM000025', 'TAMBOBONG, DASOL, PANGASINAN', NULL, NULL, 15180.00, '0', 'PAID', '6 Jun 2026', 'Mon, 8 June 2026', 'June 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000025', NULL),
(48, '0022', 'Tambobong National High School', 'VPSGM000026', 'Tambobong National High School, Dasol, Pangasinan', NULL, NULL, 15180.00, '0', 'PAID', '9 Jun 2026', 'Fri, 5 June 2026', 'June 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000026', NULL),
(49, '0022', 'Tambobong National High Shool', 'VPSGM000027', 'Tambobong National High Shool, Dasol, Pangasinan', NULL, NULL, 5509.00, '0', 'PAID', '9 Jun 2026', 'Tue, 9 June 2026', 'June 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000027', NULL),
(50, '0022', 'Tambobong National High School', 'VPSGM000028', 'Tambobong National High School, Dasol, Pangasinan', NULL, NULL, 11550.30, '0', 'PAID', '9 Jun 2026', 'Tue, 9 June 2026', 'June 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000028', NULL),
(51, '0022', 'Tambobong National High School', 'VPSGM000029', 'Tambobong National High School, Dasol, Pangasinan', NULL, NULL, 14100.00, '0', 'PAID', '9 Jun 2026', 'Tue, 9 June 2026', 'June 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000029', NULL),
(52, '0022', 'Tambac Barangay Council', 'VPSGM000030', 'Tambac, Dasol, Pangasinan', NULL, NULL, 29990.00, '0', 'PAID', '9 Jun 2026', 'Tue, 9 June 2026', 'June 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000030', NULL),
(54, '2670', 'BARLO INTEGRATED SCHOOL', 'VPSGM000032', 'BARLO, MABINI PANGASINAN', NULL, NULL, 18000.00, '0', 'PAID', '9 Jun 2026', 'Mon, 15 June 2026', 'June 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000032', NULL),
(56, '2670', 'BARLO INTEGRATED SCHOOL', 'VPSGM000033', 'BARLO MABINI PANGASINAN', NULL, NULL, 17900.00, '0', 'PAID', '9 Jun 2026', 'Mon, 15 June 2026', 'June 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000033', NULL),
(57, '2670', 'BARLO INTEGRATED SCHOOL', 'VPSGM000034', 'BARLO MABINI PANGASINAN', NULL, NULL, 6290.00, '0', 'PAID', '9 Jun 2026', 'Mon, 15 June 2026', 'June 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000034', NULL),
(58, '2670', 'barlo integrated school', 'VPSGM000035', 'barlo mabini pangasinan', NULL, NULL, 11563.00, '0', 'PAID', '10 Jun 2026', 'Mon, 15 June 2026', 'June 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000035', NULL),
(60, '0022', 'Petal ES', 'VPSGM000036', 'Petal, Dasol, Pangasinan', NULL, NULL, 19791.67, '0', 'PAID', '11 Jun 2026', 'Thu, 11 June 2026', 'June 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000036', NULL),
(61, '8156', 'VILLACORTA ELEMENTARY SCHOOL', 'VPSGM000037', 'VILLACORTA, MABINI, PANGASINAN', NULL, NULL, 5205.00, '0', 'PAID', '11 Jun 2026', 'Wed, 10 June 2026', 'June 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000037', NULL),
(62, '8156', 'VILLACORTA ELEMENTARY SCHOOL', 'VPSGM000038', 'VILLACORTA, MABINI, PANGASINAN', NULL, NULL, 2000.00, '0', 'PAID', '11 Jun 2026', 'Wed, 10 June 2026', 'June 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000038', NULL),
(63, 'Customer Assistant PO-1', 'EGUIA NATIONAL HIGH SCHOOL', 'VPSGM000039', 'EGUIA, DASOL, PANGASINAN', NULL, NULL, 30500.00, '0', 'PAID', '24 June 2026', '25 June 2026', 'June 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(64, 'Customer Assistant PO-1', 'EGUIA NATIONAL HIGH SCHOOL', 'VPSGM000040', 'EGUIA, DASOL, PANGASINAN', NULL, NULL, 25600.00, '0', 'PAID', '24 June 2026', '25 June 2026', 'June 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(65, 'Customer Assistant PO-1', 'EGUIA NATIONAL HIGH SCHOOL', 'VPSGM000041', 'EGUIA,DASOL,PANGASINAN', NULL, NULL, 22466.40, '0', 'PAID', '24 June 2026', '25 June 2026', 'June 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(66, 'Customer Assistant PO-1', 'EGUIA NATIONAL HIGH SCHOOL', 'VPSGM000042', 'EGUIA, DASOL, PANGASINAN', NULL, NULL, 15706.49, '0', 'PAID', '24 June 2026', '25 June 2026', 'June 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(67, 'Customer Assistant PO-1', 'Don Matias Elementary School', 'VPSGM000043', 'Don Matias, Burgos, Pangasinan', NULL, NULL, 1748.96, '0', 'PAID', '6 July 2026', '6 July 2026', 'July 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(68, '4266', 'SUROD ES', 'VPSGM000044', 'Barlo Millsite, Barlo, Mabini, Pangasinan', NULL, NULL, 9987.00, '0', 'PAID', '7 July 2026', '8 July 2026', 'July 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(69, 'Customer Assistant PO-1', 'CABANAS', 'VPSGM000045', 'TAMBOBONG, DASOL, PANGASINAN', NULL, NULL, 600.00, '0', 'PAID', '13 July 2026', '13 July 2026', 'July 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(70, 'Customer Assistant PO-2', 'Marissa B. Alferos', 'VPSGM000046', 'Don Antonio Bonilla ES, San Vicente, Burgos, Pangasinan', NULL, NULL, 6000.00, '0', 'PAID', '14 July 2026', 'Wed, 15 Jul 2026', 'July 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(71, 'Customer Assistant PO-1', 'TAMBOBONG NATIONAL HIGH SCHOOL', 'VPSGM000047', 'TAMBOBONG, DASOL, PANGASINAN', NULL, NULL, 12800.00, '0', 'PAID', '14 July 2026', '14 July 2026', 'July 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(72, 'Customer Assistant PO-1', 'TAMBOBONG NATIONAL HIGH SCHOOL', 'VPSGM000048', 'TAMBOBONG, DASOL, PANGASINAN', NULL, NULL, 5600.00, '0', 'PAID', '14 July 2026', '14 July 2026', 'July 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(74, 'Customer Assistant PO-1', 'CABIANGAN ELEMENTARY SCHOOL', 'VPSGM000049', 'CABIANGAN, MABINI, PANGASINAN', NULL, NULL, 33490.00, '0', 'PAID', '15 July 2026', '15 July 2026', 'July 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(75, 'Customer Assistant PO-1', 'EGUIA NATIONAL HIGH SCHOOL', 'VPSGM000050', 'EGUIA, DASOL, PANGASINAN', NULL, NULL, 1810.00, '0', 'PAID', '15 July 2026', '16 July 2026', 'July 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(76, 'Customer Assistant PO-1', 'TAMBOBONG NATIONAL HIGH SCHOOL', 'VPSGM000051', 'TAMBOBONG, DASOL, PANGASINAN', NULL, NULL, 40740.00, '0', 'PAID', '16 July 2026', '16 July 2026', 'July 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(77, '2670', 'barlo integrated school', 'VPSGM000052', 'barlo mabini pangasinan', NULL, NULL, 7120.00, '0', 'PAID', '16 July 2026', '21 July 2026', 'July 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(78, '2670', 'barlo integrated school-elem', 'VPSGM000053', 'barlo mabini pangasinan', NULL, NULL, 3200.00, '0', 'PAID', '16 July 2026', '21 July 2026', 'July 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(79, 'Customer Assistant PO-1', 'EGUIA BARANGAY COUNCIL', 'VPSGM000054', 'EGUIA, DASOL, PANGASINAN', NULL, NULL, 37000.00, '0', 'PAID', '18 July 2026', NULL, 'July 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(80, 'Customer Assistant PO-1', 'VIGA ELEMENTARY SCHOOL', 'VPSGM000055', 'VIGA, DASOL, PANGASINAN', NULL, NULL, 13459.40, '0', 'PAID', '19 July 2026', '20 July 2026', 'July 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(81, 'Customer Assistant PO-1', 'PAPALLASEN ELEMENTARY SCHOOL', 'VPSGM000056', 'PAPALLASEN, BURGOS, PANGASINAN', NULL, NULL, 9000.00, '0', 'PENDING', '20 July 2026', '20 July 2026', 'July 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(82, '3145', 'Eguia National High School', 'VPSGM000057', 'Eguia, Dasol, Pangasinan', NULL, NULL, 49990.00, '0', 'PAID', '3 August 2026', '5 August 2026', 'August 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(83, '3145', 'Eguia National High School', 'VPSGM000058', 'Eguia, Dasol, Pangasinan', NULL, NULL, 19195.00, '0', 'PAID', '3 August 2026', '5 August 2026', 'August 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(85, '3145', 'Don Juan Bernal Sr. Elementary School', 'VPSGM000060', 'Hermosa, Dasol, Pangasinan', NULL, NULL, 9700.00, '0', 'PENDING', '3 August 2026', '17 August 2026', 'August 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(86, '3145', 'Eguia National High Schiol', 'VPSGM000061', 'Eguia, Dasol, Panhasinan', NULL, NULL, 3820.00, '0', 'PENDING', '3 August 2026', 'Thu, 13 Aug 2026', 'August 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(87, '3145', 'Eguia National High School', 'VPSGM000062', 'Eguia, Dasol, Pangasinan', NULL, NULL, 20505.00, '0', 'PENDING', '13 August 2026', '14 August 2026', 'August 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000062', NULL),
(89, '8156', 'Don Juan Bernal Sr. Elementary School', 'VPSGM000063', 'Hermosa, Dasol, Pangasinan', NULL, NULL, 22680.00, '0', 'PENDING', '15 August 2026', '17 August 2026', 'August 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000063', NULL),
(92, '3145', 'Eguia Elementary School', 'VPSGM000064', 'Eguia Dasol Pangasinan', NULL, NULL, 28990.00, '0', 'PENDING', '18 August 2026', '17 August 2026', 'August 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000064', NULL),
(93, '4819', 'Barlo Integrated School', 'VPSGM000065', 'Barlo, Mabini, Pangasinan', NULL, NULL, 12000.00, '0', 'PENDING', '20 August 2026', '24 August 2026', 'August 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000065', NULL),
(94, '4819', 'Barlo Integrated School', 'VPSGM000066', 'Barlo, Mabini, Pangasinan', NULL, NULL, 6283.00, '0', 'PENDING', '20 August 2026', '24 August 2026', 'August 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000066', NULL),
(99, '4266', 'Princess Maria Juanita D Garcia', 'VPSGM000069', 'Barlo Millsite, Barlo, Surod ES', NULL, NULL, 5790.00, '0', 'PENDING', '23 August 2026', '24 August 2026', 'August 2026', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000069', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `location`
--

CREATE TABLE `location` (
  `id` int(11) NOT NULL,
  `from_market` varchar(255) DEFAULT NULL,
  `delivery_location` varchar(255) DEFAULT NULL,
  `charge` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `location`
--

INSERT INTO `location` (`id`, `from_market`, `delivery_location`, `charge`) VALUES
(1, 'Poblacion', 'Amalbalan', '70'),
(2, 'Poblacion', 'Petal', '70'),
(3, 'Poblacion', 'Eguia', '70'),
(4, 'Poblacion', 'Hermosa', '70'),
(5, 'Poblacion', 'Macalang', '70'),
(6, 'Poblacion', 'Magsaysay', '70'),
(7, 'Poblacion', 'Malacapas', '70'),
(8, 'Poblacion', 'Uli', '70'),
(9, 'Poblacion', 'Osmeña', '70'),
(10, 'Poblacion', 'Tambobong', '70'),
(11, 'Poblacion', 'Alilao', '70'),
(12, 'Poblacion', 'Malimpin', '70'),
(13, 'Poblacion', 'Tambac', '70'),
(14, 'Poblacion', 'Viga', '70'),
(15, 'Poblacion', 'Bobonot', '70'),
(16, 'Poblacion', 'Gais-Guipe', '70'),
(17, 'Poblacion', 'Poblacion', '70'),
(18, 'Poblacion', 'San Vicente', '70'),
(19, 'Poblacion', 'Poblacion', '150'),
(20, 'Poblacion', 'Bayambang', '150'),
(21, 'Poblacion', 'Cabacaraan', '150'),
(22, 'Poblacion', 'Dompoc', '150'),
(23, 'Poblacion', 'Don Matias', '150'),
(24, 'Poblacion', 'Ilio-ilio', '150'),
(25, 'Poblacion', 'Kita-kita', '150'),
(26, 'Poblacion', 'San Pascual', '150'),
(27, 'Poblacion', 'San Mateo', '150'),
(28, 'Poblacion', 'San Miguel', '150'),
(29, 'Poblacion', 'San Vicente', '150'),
(30, 'Poblacion', 'Sapa Grande', '150'),
(31, 'Poblacion', 'Sapa Pequeña', '150'),
(32, 'Poblacion', 'Poblacion', '200'),
(33, 'Poblacion', 'Barlo', '200'),
(34, 'Poblacion', 'Caabiangaan', '200'),
(35, 'Poblacion', 'Cabili', '200'),
(36, 'Poblacion', 'Caculangan', '200'),
(37, 'Poblacion', 'Calayucay', '200'),
(38, 'Poblacion', 'Capataan', '200'),
(39, 'Poblacion', 'Cawaynian', '200'),
(40, 'Poblacion', 'Gayagayaan', '200'),
(41, 'Poblacion', 'Lungal', '200'),
(42, 'Poblacion', 'Nalvo', '200'),
(43, 'Poblacion', 'Pangascasan', '200'),
(44, 'Poblacion', 'San Juan', '200'),
(45, 'Poblacion', 'Tagudin', '200'),
(46, 'Poblacion', 'Toritori', '200'),
(47, 'Poblacion', 'Poblacion', '200'),
(48, 'Poblacion', 'Bamban', '200'),
(49, 'Poblacion', 'Batangan', '200'),
(50, 'Poblacion', 'Cato', '200'),
(51, 'Poblacion', 'Doliman', '200'),
(52, 'Poblacion', 'Fatima', '200'),
(53, 'Poblacion', 'Maypasing', '200'),
(54, 'Poblacion', 'Nangalisan', '200'),
(55, 'Poblacion', 'Nayom', '200'),
(56, 'Poblacion', 'Pita', '200');

-- --------------------------------------------------------

--
-- Table structure for table `logs`
--

CREATE TABLE `logs` (
  `id` int(11) NOT NULL,
  `name` varchar(50) DEFAULT NULL,
  `action` varchar(255) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `created_at` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
--
-- Dumping data for table `logs`
--

INSERT INTO `logs` (`id`, `name`, `action`, `details`, `created_at`) VALUES
(1, 'Joseph', 'Added New Product', 'Added new product: Long folder (green) | Unit: Ream | Quantity: 2 | Price: ₱950 | Description: 50 pcs (ream)', '10 Jun 2026 4:37 PM'),
(2, 'Norlie', 'Sold Product', 'Sold product: A4 Coupon | Quantity: 1 Reams | Price: ₱230 each | Total: ₱230 | Purpose: Sell | Status: PENDING', '11 Jun 2026 8:18 AM'),
(3, 'Norlie', 'Updated Product', 'Updated product: A4 Coupon (ID: 97) | Changes: Price: ₱230 → ₱240', '11 Jun 2026 8:19 AM'),
(4, 'Norlie', 'Updated Product', 'Updated product: A4 Coupon (ID: 97) | Changes: Price: ₱240 → ₱230', '11 Jun 2026 10:44 AM'),
(5, 'Norlie', 'Updated Product', 'Updated product: A4 Coupon (ID: 97) | Changes: Price: ₱230 → ₱240', '11 June 2026 10:50 AM'),
(6, 'Norlie', 'Updated Product', 'Updated product: A4 Coupon (ID: 97) | Changes: Price: ₱240 → ₱230', '11 June 2026 10:50 AM'),
(7, 'Norlie', 'Updated Product', 'Updated product: Long Folder White (ID: 83) | Changes: Price: ₱800 → ₱850', '11 June 2026 10:51 AM'),
(8, 'Corina', 'Added New Product', 'Added new product: Stand fan | Unit: Pcs | Quantity: 2 | Price: ₱2800 | Description: N/A', '11 June 2026 10:56 AM'),
(9, 'Corina', 'Added New Product', 'Added new product: Wall fan | Unit: Pcs | Quantity: 2 | Price: ₱2600 | Description: N/A', '11 June 2026 10:56 AM'),
(10, 'Corina', 'Added New Product', 'Added new product: Desk fan | Unit: Pcs | Quantity: 2 | Price: ₱2200 | Description: N/A', '11 June 2026 10:57 AM'),
(11, 'Corina', 'Updated Product', 'Updated product: Bolo (ID: 252) | Changes: Price: ₱350 → ₱650', '11 June 2026 11:40 AM'),
(12, 'Norlie', 'Added New Product', 'Added new product: Digging Bar | Unit: Pcs | Quantity: 1 | Price: ₱650 | Description: N/A', '11 June 2026 11:42 AM'),
(13, 'Norlie', 'Added New Product', 'Added new product: Trash Bin (60L) | Unit: Pcs | Quantity: 3 | Price: ₱340 | Description: N/A', '11 June 2026 11:54 AM'),
(14, 'Norlie', 'Updated Product', 'Updated product: Plant trimmer (ID: 296) | Changes: Price: ₱750 → ₱751.67', '11 June 2026 11:55 AM'),
(15, 'Norlie', 'Updated Product', 'Updated product: Bolo (ID: 252) | Changes: Quantity: 2 → 3', '11 June 2026 2:51 PM'),
(16, 'Corina', 'Updated Product', 'Updated product: Toilet Tissue (ID: 224) | Changes: Quantity: 5 → 3', '11 June 2026 3:38 PM'),
(17, 'Corina', 'Updated Product', 'Updated product: Dust pan (ID: 142) | Changes: Quantity: 10 → 7', '11 June 2026 3:39 PM'),
(18, 'Corina', 'Updated Product', 'Updated product: Soft broom (walis tambo) (ID: 141) | Changes: Quantity: 10 → 6', '11 June 2026 3:39 PM'),
(19, 'Corina', 'Updated Product', 'Updated product: Trash Bin (60L) (ID: 302) | Changes: Quantity: 3 → 2', '11 June 2026 3:40 PM'),
(20, 'Norlie', 'Added New Product', 'Added new product: Plastic Mug | Unit: Pcs | Quantity: 100 | Price: ₱20 | Description: N/A', '11 June 2026 4:24 PM'),
(21, 'Norlie', 'Updated Product', 'Updated product: Check Soap (ID: 55) | Changes: Quantity: 3 → 7', '13 June 2026 8:29 AM'),
(22, 'Corina', 'Updated Product', 'Updated product: Shovel (ID: 205) | Changes: Quantity: 0 → 2', '13 June 2026 8:34 AM'),
(23, 'Corina', 'Updated Product', 'Updated product: Soft broom (walis tambo) (ID: 141) | Changes: Quantity: 0 → 10', '13 June 2026 8:34 AM'),
(24, 'Corina', 'Updated Product', 'Updated product: Dust pan (ID: 142) | Changes: Quantity: 0 → 10', '13 June 2026 8:35 AM'),
(25, 'Corina', 'Updated Product', 'Updated product: Tuff (ID: 294) | Changes: Quantity: 7 → 12', '13 June 2026 8:35 AM'),
(26, 'Corina', 'Updated Product', 'Updated product: Trash Bin (60L) (ID: 302) | Changes: Quantity: 0 → 3', '13 June 2026 8:36 AM'),
(27, 'Joseph', 'Added New Product', 'Added new product: Padlock | Unit: Pcs | Quantity: 5 | Price: ₱450 | Description: N/A', '13 June 2026 9:32 AM'),
(28, 'Joseph', 'Updated Product', 'Updated product: Smart TV, 45\" (ID: 222) | Changes: Name: \'Smart TV, 45\"\' → \'Smart TV, 43 inches\'', '14 June 2026 2:26 PM'),
(29, 'Norlie', 'Sold Product', 'Sold product: Canon Ink Black | Quantity: 1 Pcs | Price: ₱450 each | Total: ₱450 | Purpose: Sell | Status: PENDING', '16 June 2026 10:06 AM'),
(30, 'Norlie', 'Update Order Status', 'Order #426 status changed to PAID for product: Canon Ink Black', '16 June 2026 10:06 AM'),
(31, 'Norlie', 'Added New Product', 'Added new product: Kitchen Paper Towel twin Pack | Unit: Pack | Quantity: 1 | Price: ₱160 | Description: N/A', '16 June 2026 11:23 AM'),
(32, 'Norlie', 'Updated Product', 'Updated product: Jacket Folder A4 (ID: 79) | Changes: Quantity: 22 → 50', '17 June 2026 12:08 PM'),
(33, 'Norlie', 'Updated Product', 'Updated product: Jacket Folder A4 (ID: 79) | Changes: Price: ₱25 → ₱20', '17 June 2026 12:08 PM'),
(34, 'Norlie', 'Updated Product', 'Updated product: Jacket Folder long (ID: 78) | Changes: Name: \'Jacket Folder long\' → \'Jacket Folder Long\', Quantity: 4 → 50, Price: ₱45 → ₱25', '17 June 2026 12:09 PM'),
(35, 'Norlie', 'Updated Product', 'Updated product: Plastic Envelop (ID: 77) | Changes: Name: \'Plastic Envelop\' → \'Plastic Envelop Long\', Quantity: 32 → 50', '17 June 2026 12:10 PM'),
(36, 'Norlie', 'Updated Product', 'Updated product: Jacket Folder Long (ID: 78) | Changes: Quantity: 50 → 100', '17 June 2026 12:10 PM'),
(37, 'Norlie', 'Added New Product', 'Added new product: Flex Stick Green | Unit: Pack | Quantity: 3 | Price: ₱150 | Description: N/A', '17 June 2026 12:11 PM'),
(38, 'Norlie', 'Updated Product', 'Updated product: Long folder (green) (ID: 297) | Changes: Quantity: 2 → 100', '17 June 2026 12:13 PM'),
(39, 'Norlie', 'Updated Product', 'Updated product: Photo Paper 180 gsm (ID: 108) | Changes: Quantity: 6 → 20', '17 June 2026 12:13 PM'),
(40, 'Norlie', 'Added New Product', 'Added new product: Vellu Bond Long 180gsm (White) | Unit: Reams | Quantity: 100 | Price: ₱230 | Description: N/A', '17 June 2026 12:15 PM'),
(41, 'Norlie', 'Added New Product', 'Added new product: Packeging Tape Thick | Unit: Dozen | Quantity: 4 | Price: ₱1 | Description: N/A', '17 June 2026 12:17 PM'),
(42, 'Norlie', 'Added New Product', 'Added new product: Ordinary Jacket Folder (A4) | Unit: Pcs | Quantity: 50 | Price: ₱1 | Description: N/A', '17 June 2026 12:19 PM'),
(43, 'Norlie', 'Updated Product', 'Updated product: Plastic Envelop Long (ID: 77) | Changes: Quantity: 50 → 100', '17 June 2026 12:21 PM'),
(44, 'Norlie', 'Added New Product', 'Added new product: Press Broad Long | Unit: Pcs | Quantity: 30 | Price: ₱1 | Description: N/A', '17 June 2026 12:21 PM'),
(45, 'Norlie', 'Added New Product', 'Added new product: Alcohol Safe ISO | Unit: Gallons | Quantity: 1 | Price: ₱1 | Description: N/A', '17 June 2026 12:23 PM'),
(46, 'Norlie', 'Added New Product', 'Added new product: Pilot White Board Marker | Unit: Dozen | Quantity: 2 | Price: ₱1 | Description: N/A', '17 June 2026 12:31 PM'),
(47, 'Corina', 'Updated Product', 'Updated product: Pilot White Board Marker (ID: 312) | Changes: Unit: \'Dozen\' → \'Pcs\', Quantity: 2 → 21, Price: ₱1 → ₱49', '24 June 2026 8:50 AM'),
(48, 'Corina', 'Updated Product', 'Updated product: Press Broad Long (ID: 310) | Changes: Price: ₱1 → ₱25', '24 June 2026 8:55 AM'),
(49, 'Corina', 'Updated Product', 'Updated product: Ordinary Jacket Folder (A4) (ID: 309) | Changes: Price: ₱1 → ₱20', '24 June 2026 8:55 AM'),
(50, 'Corina', 'Updated Product', 'Updated product: Packeging Tape Thick (ID: 308) | Changes: Unit: \'Dozen\' → \'pcs\', Quantity: 4 → 18, Price: ₱1 → ₱65', '24 June 2026 8:56 AM'),
(51, 'Corina', 'Added New Product', 'Added new product: Flush door 210x150 | Unit: Pcs | Quantity: 5 | Price: ₱4500 | Description: N/A', '24 June 2026 9:00 AM'),
(52, 'Corina', 'Added New Product', 'Added new product: Door hinges | Unit: Pcs | Quantity: 15 | Price: ₱250 | Description: N/A', '24 June 2026 9:01 AM'),
(53, 'Corina', 'Added New Product', 'Added new product: Door knob | Unit: Pcs | Quantity: 5 | Price: ₱850 | Description: N/A', '24 June 2026 9:02 AM'),
(54, 'Corina', 'Added New Product', 'Added new product: Head frame | Unit: Pcs | Quantity: 2 | Price: ₱1300 | Description: N/A', '24 June 2026 9:02 AM'),
(55, 'Corina', 'Added New Product', 'Added new product: Still frame | Unit: Pcs | Quantity: 2 | Price: ₱1400 | Description: N/A', '24 June 2026 9:03 AM'),
(56, 'Corina', 'Added New Product', 'Added new product: Top bottom frame | Unit: Pcs | Quantity: 2 | Price: ₱1300 | Description: N/A', '24 June 2026 9:03 AM'),
(57, 'Corina', 'Added New Product', 'Added new product: Interlocker | Unit: Pcs | Quantity: 2 | Price: ₱1100 | Description: N/A', '24 June 2026 9:04 AM'),
(58, 'Corina', 'Added New Product', 'Added new product: Lockstie | Unit: Pcs | Quantity: 2 | Price: ₱1100 | Description: N/A', '24 June 2026 9:05 AM'),
(59, 'Corina', 'Added New Product', 'Added new product: Replective Glass | Unit: Pcs | Quantity: 6 | Price: ₱1800 | Description: N/A', '24 June 2026 9:06 AM'),
(60, 'Corina', 'Added New Product', 'Added new product: Silicone | Unit: Pcs | Quantity: 12 | Price: ₱200 | Description: N/A', '24 June 2026 9:07 AM'),
(61, 'Corina', 'Updated Product', 'Updated product: Door hinges (ID: 314) | Changes: Name: \'Door hinges\' → \'Door\'', '24 June 2026 9:09 AM'),
(62, 'Corina', 'Updated Product', 'Updated product: Packeging Tape Thick (ID: 308) | Changes: Name: \'Packeging Tape Thick\' → \'Packaging Tape Thick\'', '24 June 2026 9:10 AM'),
(63, 'Corina', 'Added New Product', 'Added new product: Swivel chair | Unit: Pcs | Quantity: 6 | Price: ₱3744.4 | Description: N/A', '24 June 2026 10:01 AM'),
(64, 'Corina', 'Updated Product', 'Updated product: Packaging Tape Thick (ID: 308) | Changes: Quantity: 18 → 24', '24 June 2026 12:10 PM'),
(65, 'Corina', 'Updated Product', 'Updated product: Scotch Tape 2 inch (ID: 49) | Changes: Quantity: 4 → 22', '24 June 2026 12:10 PM'),
(66, 'Corina', 'Updated Product', 'Updated product: Brother ink Yellow (ID: 99) | Changes: Quantity: 1 → 2', '24 June 2026 1:54 PM'),
(67, 'Corina', 'Added New Product', 'Added new product: Ecolum LED bulb | Unit: Pcs | Quantity: 5 | Price: ₱450 | Description: N/A', '24 June 2026 1:55 PM'),
(68, 'Corina', 'Added New Product', 'Added new product: Insect killer | Unit: Pcs | Quantity: 2 | Price: ₱375 | Description: N/A', '24 June 2026 1:56 PM'),
(69, 'Corina', 'Updated Product', 'Updated product: Padlock (ID: 304) | Changes: Quantity: 5 → 4', '24 June 2026 1:57 PM'),
(70, 'Corina', 'Added New Product', 'Added new product: Meter tape | Unit: Pcs | Quantity: 1 | Price: ₱275 | Description: N/A', '24 June 2026 1:57 PM'),
(71, 'Corina', 'Updated Product', 'Updated product: Battery Energizer AA (ID: 39) | Changes: Quantity: 10 → 22', '24 June 2026 1:58 PM'),
(72, 'Corina', 'Updated Product', 'Updated product: Alcohol (ID: 140) | Changes: Price: ₱600 → ₱631.49', '24 June 2026 1:59 PM'),
(73, 'Corina', 'Updated Product', 'Updated product: Kitchen Paper Towel twin Pack (ID: 305) | Changes: Quantity: 1 → 2', '24 June 2026 2:00 PM'),
(74, 'Norlie', 'Added New Product', 'Added new product: Scotch Tape 1 inch | Unit: Pcs | Quantity: 6 | Price: ₱35 | Description: N/A', '24 June 2026 2:02 PM'),
(75, 'Norlie', 'Updated Product', 'Updated product: Battery Energizer AA (ID: 39) | Changes: Price: ₱75 → ₱70', '24 June 2026 2:16 PM'),
(76, 'Norlie', 'Added New Product', 'Added new product: Hearbs & Beauti Lotion | Unit: Pcs | Quantity: 4 | Price: ₱1 | Description: Enchanted Spell', '25 June 2026 4:25 PM'),
(77, 'Norlie', 'Updated Product', 'Updated product: Hearbs & Beauti Lotion (ID: 328) | Changes: No changes', '25 June 2026 4:27 PM'),
(78, 'Norlie', 'Updated Product', 'Updated product: Hearbs & Beauti Lotion (ID: 328) | Changes: Name: \'Hearbs & Beauti Lotion\' → \'Hearbs & Beauty Lotion\'', '25 June 2026 4:27 PM'),
(79, 'Corina', 'Added New Product', 'Added new product: Epson ink 003 blk | Unit: Bottles | Quantity: 2 | Price: ₱274.48 | Description: N/A', '6 July 2026 9:36 AM'),
(80, 'Corina', 'Added New Product', 'Added new product: Coupon A4 | Unit: reams | Quantity: 5 | Price: ₱240 | Description: N/A', '6 July 2026 9:39 AM'),
(81, 'Joseph', 'Added New Product', 'Added new product: Green folder green | Unit: Pcs | Quantity: 100 | Price: ₱20 | Description: N/A', '7 July 2026 9:40 AM'),
(82, 'Joseph', 'Added New Product', 'Added new product: Folder long | Unit: Pcs | Quantity: 100 | Price: ₱10 | Description: N/A', '7 July 2026 9:41 AM'),
(83, 'Joseph', 'Added New Product', 'Added new product: A4 folder | Unit: Pcs | Quantity: 100 | Price: ₱9 | Description: N/A', '7 July 2026 9:41 AM'),
(84, 'Joseph', 'Added New Product', 'Added new product: Sliding folder long | Unit: Pcs | Quantity: 100 | Price: ₱15 | Description: N/A', '7 July 2026 9:42 AM'),
(85, 'Joseph', 'Updated Product', 'Updated product: Sliding folder long (ID: 334) | Changes: Price: ₱15 → ₱25', '7 July 2026 9:45 AM'),
(86, 'Joseph', 'Updated Product', 'Updated product: Junction Box 8cm (ID: 139) | Changes: Price: ₱12 → ₱85', '9 July 2026 7:01 PM'),
(87, 'Corina', 'Updated Product', 'Updated product: Junction Box 10cm (ID: 3) | Changes: Quantity: 0 → 4', '13 July 2026 9:24 AM'),
(89, 'Joseph', 'Added New Product', 'Added new product: HIK Vision CCTV Camera 2.0Mp | Unit: Pcs | Quantity: 10 | Price: ₱3000 | Description: N/A', '14 July 2026 10:31 AM'),
(90, 'Corina', 'Added New Product', 'Added new product: Stand fan, Camel | Unit: Pcs | Quantity: 4 | Price: ₱3200 | Description: N/A', '14 July 2026 2:02 PM'),
(91, 'Corina', 'Added New Product', 'Added new product: Stand fan, Eureka | Unit: Pcs | Quantity: 2 | Price: ₱2800 | Description: N/A', '14 July 2026 2:03 PM'),
(92, 'Corina', 'Updated Product', 'Updated product: Pilot Permanent Marker (ID: 143) | Changes: Quantity: 4 → 12', '14 July 2026 2:11 PM'),
(93, 'Corina', 'Updated Product', 'Updated product: Pilot White Board Marker (ID: 312) | Changes: Quantity: 21 → 20', '14 July 2026 2:22 PM'),
(94, 'Corina', 'Updated Product', 'Updated product: Paper Clip 50mm (ID: 26) | Changes: Quantity: 1 → 11', '14 July 2026 2:23 PM'),
(95, 'Corina', 'Updated Product', 'Updated product: Paper Clip 33mm (ID: 27) | Changes: Quantity: 2 → 10', '14 July 2026 2:23 PM'),
(96, 'Corina', 'Updated Product', 'Updated product: Binder Clips 1 1/4 Inc (ID: 21) | Changes: Quantity: 43 → 91', '14 July 2026 2:25 PM'),
(97, 'Corina', 'Added New Product', 'Added new product: Binder Clips 2 inch | Unit: Pcs | Quantity: 60 | Price: ₱15 | Description: N/A', '14 July 2026 2:26 PM'),
(98, 'Corina', 'Updated Product', 'Updated product: Double Sided Tape 1 inch (ID: 51) | Changes: Quantity: 4 → 16', '14 July 2026 2:27 PM'),
(99, 'Corina', 'Updated Product', 'Updated product: HBW Scissors (ID: 28) | Changes: Quantity: 5 → 10', '14 July 2026 2:27 PM'),
(100, 'Corina', 'Added New Product', 'Added new product: Worx Paper 200 gsm | Unit: Pcs | Quantity: 1 | Price: ₱380 | Description: N/A', '14 July 2026 2:30 PM'),
(101, 'Joseph', 'Added New Product', 'Added new product: TPlink Router / Wifi Extender | Unit: Pcs | Quantity: 2 | Price: ₱3500 | Description: N/A', '15 July 2026 7:34 AM'),
(102, 'Corina', 'Added New Product', 'Added new product: CCTV Package 3 | Unit: set | Quantity: 1 | Price: ₱23990 | Description: N/A', '15 July 2026 8:29 AM'),
(103, 'Corina', 'Added New Product', 'Added new product: Wifi repeater | Unit: Pcs | Quantity: 1 | Price: ₱3500 | Description: N/A', '15 July 2026 8:32 AM'),
(104, 'Norlie', 'Updated Product', 'Updated product: CCTV Package 3 (ID: 341) | Changes: No changes', '15 July 2026 9:02 AM'),
(105, 'Corina', 'Added New Product', 'Added new product: Circuit breaker | Unit: Pcs | Quantity: 1 | Price: ₱430 | Description: N/A', '16 July 2026 8:00 AM'),
(106, 'Corina', 'Added New Product', 'Added new product: Electrical tape | Unit: Pcs | Quantity: 1 | Price: ₱60 | Description: 20c yard', '16 July 2026 8:01 AM'),
(107, 'Corina', 'Added New Product', 'Added new product: Electric wire#6 | Unit: Pcs | Quantity: 1 | Price: ₱1140 | Description: 6 meters', '16 July 2026 8:03 AM'),
(108, 'Corina', 'Updated Product', 'Updated product: Circuit breaker (ID: 343) | Changes: Price: ₱430 → ₱473', '16 July 2026 8:20 AM'),
(109, 'Corina', 'Updated Product', 'Updated product: Electrical tape (ID: 344) | Changes: Price: ₱60 → ₱66', '16 July 2026 8:22 AM'),
(110, 'Corina', 'Updated Product', 'Updated product: Electric wire#6 (ID: 345) | Changes: Quantity: 1 → 6, Price: ₱1140 → ₱209', '16 July 2026 8:22 AM'),
(111, 'Corina', 'Added New Product', 'Added new product: Smart TV, 43 | Unit: Pcs | Quantity: 2 | Price: ₱19000 | Description: N/A', '16 July 2026 9:33 AM'),
(112, 'Corina', 'Added New Product', 'Added new product: Book Holder | Unit: Pcs | Quantity: 1 | Price: ₱480 | Description: N/A', '16 July 2026 9:38 AM'),
(113, 'Corina', 'Added New Product', 'Added new product: File organizer | Unit: Pcs | Quantity: 1 | Price: ₱460 | Description: N/A', '16 July 2026 9:39 AM'),
(114, 'Corina', 'Updated Product', 'Updated product: Smart TV, 43 (ID: 346) | Changes: Price: ₱19000 → ₱19900', '16 July 2026 9:44 AM'),
(115, 'Joseph', 'Added New Product', 'Added new product: Metal Detector (for gate security? | Unit: Pcs | Quantity: 2 | Price: ₱1820 | Description: N/A', '16 July 2026 2:36 PM'),
(116, 'Norlie', 'Updated Product', 'Updated product: Circuit breaker (ID: 343) | Changes: Name: \'Circuit breaker\' → \'Circuit Breaker BOLT ON 60AMP\', Price: ₱473 → ₱480', '16 July 2026 2:41 PM'),
(117, 'Norlie', 'Updated Product', 'Updated product: Electrical tape (ID: 344) | Changes: Name: \'Electrical tape\' → \'Electrical tape 20 yrd\', Price: ₱66 → ₱70', '16 July 2026 2:44 PM'),
(118, 'Norlie', 'Updated Product', 'Updated product: Electric wire#6 (ID: 345) | Changes: Price: ₱209 → ₱210', '16 July 2026 2:45 PM'),
(119, 'Joseph', 'Updated Product', 'Updated product: Stand fan (ID: 298) | Changes: Name: \'Stand fan\' → \'Stand fan (astron)\'', '16 July 2026 3:09 PM'),
(120, 'Joseph', 'Updated Product', 'Updated product: Stand fan, Eureka (ID: 337) | Changes: Price: ₱2800 → ₱2900', '16 July 2026 3:10 PM'),
(121, 'Corina', 'Added New Product', 'Added new product: Cork Bord | Unit: set | Quantity: 1 | Price: ₱3500 | Description: N/A', '18 July 2026 11:02 AM'),
(122, 'Corina', 'Added New Product', 'Added new product: Organizer Cabinet | Unit: unit | Quantity: 1 | Price: ₱2000 | Description: N/A', '18 July 2026 11:03 AM'),
(123, 'Corina', 'Added New Product', 'Added new product: Bulletin Board | Unit: unit | Quantity: 1 | Price: ₱3000 | Description: N/A', '18 July 2026 11:04 AM'),
(124, 'Corina', 'Added New Product', 'Added new product: Printer | Unit: set | Quantity: 1 | Price: ₱20000 | Description: N/A', '18 July 2026 11:05 AM'),
(125, 'Corina', 'Updated Product', 'Updated product: Printer (ID: 353) | Changes: Name: \'Printer\' → \'Printer 3 in 1\'', '18 July 2026 11:17 AM'),
(126, 'Corina', 'Added New Product', 'Added new product: Printer ink 003 | Unit: set | Quantity: 1 | Price: ₱2000 | Description: N/A', '18 July 2026 11:19 AM'),
(127, 'Corina', 'Added New Product', 'Added new product: Printer ink Blk 003 | Unit: Pcs | Quantity: 2 | Price: ₱500 | Description: N/A', '18 July 2026 11:19 AM'),
(128, 'Corina', 'Added New Product', 'Added new product: Long Coupon | Unit: Box | Quantity: 1 | Price: ₱1200 | Description: N/A', '19 July 2026 8:02 AM'),
(129, 'Corina', 'Updated Product', 'Updated product: File Folder Long (ID: 151) | Changes: Price: ₱800 → ₱850', '19 July 2026 8:02 AM'),
(130, 'Corina', 'Added New Product', 'Added new product: Stamp ink | Unit: Bottle | Quantity: 1 | Price: ₱65 | Description: N/A', '19 July 2026 8:04 AM'),
(131, 'Corina', 'Added New Product', 'Added new product: Epson ink 003 | Unit: Set | Quantity: 4 | Price: ₱1150 | Description: N/A', '19 July 2026 8:05 AM'),
(132, 'Corina', 'Updated Product', 'Updated product: A4 Coupon (ID: 97) | Changes: Unit: \'Reams\' → \'Box\', Quantity: 6 → 2, Price: ₱ 230 → ₱1150', '19 July 2026 8:08 AM'),
(133, 'Norlie', 'Sold Product', 'Sold product: Elmer\'s Glue 130g | Quantity: 1 Pcs | Price: ₱65 each | Total: ₱65 | Purpose: Sell | Status: PENDING', '31 July 2026 1:01 PM'),
(134, 'Norlie', 'Update Order Status', 'Order #515 status changed to PAID for product: Elmer\'s Glue 130g', '31 July 2026 1:01 PM'),
(135, 'Joseph', 'Added New Product', 'Added new product: Devant Smart TV 43 inches | Unit: Pcs | Quantity: 1 | Price: ₱19195 | Description: Smart TV', '3 August 2026 7:10 PM'),
(136, 'Joseph', 'Added New Product', 'Added new product: HIK Vision CCTV package (16-ch DVR/ 12 Camera with Sounds) | Unit: Pcs | Quantity: 1 | Price: ₱49990 | Description: HIK Vision CCTV Customized Package', '3 August 2026 7:19 PM'),
(137, 'Joseph', 'Added New Product', 'Added new product: 5 meter extention wire | Unit: Pcs | Quantity: 2 | Price: ₱550 | Description: N/A', '3 August 2026 7:59 PM'),
(138, 'Joseph', 'Added New Product', 'Added new product: Picture frame | Unit: Pcs | Quantity: 10 | Price: ₱250 | Description: 11 x 8.5 inches', '3 August 2026 8:00 PM'),
(139, 'Joseph', 'Added New Product', 'Added new product: Bleach 1L | Unit: Pcs | Quantity: 5 | Price: ₱35 | Description: N/A', '12 August 2026 6:51 PM'),
(140, 'Joseph', 'Added New Product', 'Added new product: Safeguard 55 grams | Unit: Pcs | Quantity: 5 | Price: ₱25 | Description: N/A', '12 August 2026 6:52 PM'),
(141, 'Joseph', 'Updated Product', 'Updated product: Coupon A4 (ID: 330) | Changes: Price: ₱240 → ₱230', '13 August 2026 5:59 AM'),
(142, 'Joseph', 'Added New Product', 'Added new product: Brown folder A4 | Unit: Ream | Quantity: 2 | Price: ₱900 | Description: N/A', '13 August 2026 6:37 AM'),
(143, 'Norlie', 'Added New Product', 'Added new product: Red Dragon Mouse | Unit: Pcs | Quantity: 4 | Price: ₱600 | Description: N/A', '13 August 2026 2:25 PM'),
(144, 'Norlie', 'Updated Product', 'Updated product: Red Dragon Mouse (ID: 366) | Changes: Name: \'Red Dragon Mouse\' → \'Red Dragon Mouse 2.4G Wireless Mouse\'', '13 August 2026 2:25 PM'),
(145, 'Joseph', 'Added New Product', 'Added new product: Volleyball original (Mikasa) | Unit: Pcs | Quantity: 2 | Price: ₱5990 | Description: N/A', '13 August 2026 4:07 PM'),
(146, 'Joseph', 'Added New Product', 'Added new product: Basketball GG7X (Molten) | Unit: Pcs | Quantity: 1 | Price: ₱3500 | Description: N/A', '13 August 2026 4:08 PM'),
(147, 'Joseph', 'Added New Product', 'Added new product: Shuttlecock (dunlop) | Unit: Tube(12pcs) | Quantity: 3 | Price: ₱1900 | Description: N/A', '13 August 2026 4:09 PM'),
(148, 'Joseph', 'Added New Product', 'Added new product: Arnis stick (rattan) pair | Unit: Pcs | Quantity: 6 | Price: ₱250 | Description: N/A', '13 August 2026 4:13 PM'),
(149, 'Joseph', 'Updated Product', 'Updated product: Cameras: 8 x 2.0MP Wide Angle (ID: 117) | Changes: Name: \'Cameras: 8 x 2.0MP Wide Angle\' → \'CCTV Package 2 Cameras: 8 x 2.0MP Wide Angle\', Unit: \'Set\' → \'Package\', Quantity: 0 → 1', '18 August 2026 7:46 AM'),
(150, 'Norlie', 'Added New Product', 'Added new product: Garden Hose 50 ft | Unit: Pcs | Quantity: 1 | Price: ₱2050 | Description: N/A', '20 August 2026 1:06 PM'),
(151, 'Norlie', 'Updated Product', 'Updated product: Garden Hose 50 ft (ID: 367) | Changes: Name: \'Garden Hose 50 ft\' → \'Garden Hose 100 ft\'', '20 August 2026 1:07 PM'),
(152, 'Norlie', 'Updated Product', 'Updated product: Garden Hose 100 ft (ID: 367) | Changes: Price: ₱2050 → ₱4000', '20 August 2026 1:08 PM'),
(153, 'Norlie', 'Added New Product', 'Added new product: Gloves | Unit: Pcs | Quantity: 100 | Price: ₱399 | Description: N/A', '20 August 2026 1:09 PM'),
(154, 'Norlie', 'Added New Product', 'Added new product: Boots 1 pair size 7 | Unit: Pcs | Quantity: 1 | Price: ₱600 | Description: N/A', '20 August 2026 1:10 PM'),
(155, 'Norlie', 'Added New Product', 'Added new product: Zonrox Sparkle 900ml | Unit: Pcs | Quantity: 1 | Price: ₱180 | Description: N/A', '20 August 2026 1:11 PM'),
(156, 'Norlie', 'Updated Product', 'Updated product: Zonrox Sparkle 900ml (ID: 370) | Changes: Unit: \'Pcs\' → \'Bottles\', Quantity: 1 → 2', '20 August 2026 1:12 PM'),
(157, 'Norlie', 'Updated Product', 'Updated product: Boots 1 pair size 7 (ID: 369) | Changes: Name: \'Boots 1 pair size 7\' → \'Boots (1 pair size 7)\'', '20 August 2026 1:12 PM'),
(158, 'Norlie', 'Updated Product', 'Updated product: Boots (1 pair size 7) (ID: 369) | Changes: Unit: \'Pcs\' → \'Pair\'', '20 August 2026 1:12 PM'),
(159, 'Norlie', 'Added New Product', 'Added new product: Smoke Detector | Unit: Pcs | Quantity: 5 | Price: ₱358 | Description: N/A', '20 August 2026 1:13 PM'),
(160, 'Norlie', 'Updated Product', 'Updated product: A4 Folder White (ID: 82) | Changes: Unit: \'Reams\' → \'Pcs\'', '21 August 2026 1:40 PM'),
(161, 'Joseph', 'Added New Product', 'Added new product: Computer Set with Printer (i3 268/8gb/Epson L3210) | Unit: Set | Quantity: 2 | Price: ₱49990 | Description: N/A', '22 August 2026 3:44 PM'),
(162, '', 'Updated Product', 'Updated product: Computer Set with Printer (i3 268/8gb/Epson L3210) (ID: 372) | Changes: Quantity: 2 → 1', '22 August 2026 3:47 PM'),
(163, '', 'Updated Product', 'Updated product: A4 Coupon (ID: 97) | Changes: Quantity: 0 → 10', '23 August 2026 12:47 AM'),
(164, 'Joseph', 'Updated Product', 'Updated product: Computer Set with Printer (i3 268/8gb/Epson L3210) (ID: 372) | Changes: Name: \'Computer Set with Printer (i3 268/8gb/Epson L3210)\' → \'Computer Set\'', '23 August 2026 12:47 AM'),
(165, 'Norlie', 'Updated Product', 'Updated product: A4 Coupon (ID: 97) | Changes: Price: ₱230 → ₱240', '23 August 2026 12:58 AM'),
(166, 'Norlie', 'Updated Product', 'Updated product: A4 Coupon (ID: 97) | Changes: Price: ₱240 → ₱230', '23 August 2026 12:58 AM'),
(167, 'Joseph', 'Added New Product', 'Added new product: Parchment paper | Unit: Pack | Quantity: 2 | Price: ₱45 | Description: N/A', '23 August 2026 4:37 PM'),
(168, 'Norlie', 'Added New Product', 'Added new product: Unisex Rubber Shoes (S32) | Unit: Pcs | Quantity: 1 | Price: ₱400 | Description: No box, still not used', '23 August 2026 11:28 PM'),
(169, '', 'Updated Product', 'Updated product: Computer Set (ID: 372) | Changes: No changes', '24 August 2026 8:16 AM');

-- --------------------------------------------------------

--
-- Table structure for table `merchandise_inventory`
--

CREATE TABLE `merchandise_inventory` (
  `id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `product_number` varchar(255) DEFAULT NULL,
  `unit` varchar(100) DEFAULT NULL,
  `qty_on_hand` int(11) NOT NULL DEFAULT 0,
  `selling_price` varchar(255) DEFAULT NULL,
  `last_restocked` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `product_image` varchar(255) NOT NULL DEFAULT 'no_image.png'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `merchandise_inventory`
--

INSERT INTO `merchandise_inventory` (`id`, `product_name`, `product_number`, `unit`, `qty_on_hand`, `selling_price`, `last_restocked`, `description`, `product_image`) VALUES
(1, 'Video Connector', 'PRD000001', 'Pcs', 50, '25', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(2, 'Dahua IP Camera 2.0mp Wide Angle', 'PRD000002', 'Pcs', 8, '2500', '21 August 2026 2:13 PM', NULL, 'Dahua IP Camera 2.0mp Wide Angle.png'),
(3, 'Junction Box 10cm', 'PRD000003', 'Pcs', 0, '150', '07 April 2026 8:00 AM', '', 'no_image.png'),
(4, 'HIK Vision DVR 8-Ch', 'PRD000004', 'Pcs', 2, '4200', '07 April 2026 8:00 AM', 'With Hard Drive', 'no_image.png'),
(5, 'HIK Vision Power Supply', 'PRD000005', 'Pcs', 0, '1590', '07 April 2026 8:00 AM', NULL, 'HIK Vision Power Supply.png'),
(6, 'Epson Ink 003 Black', 'PRD000006', 'Bottles', 53, ' 280', '19 Aug 2026', NULL, 'no_image.png'),
(7, 'Epson Ink 003 Cyan', 'PRD000007', 'Bottles', 44, ' 290', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(8, 'Epson Ink 003 Magenta', 'PRD000008', 'Bottles', 54, ' 290', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(9, 'Epson Ink 003 Yellow', 'PRD000009', 'Bottles', 42, ' 290', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(10, 'Epson Ink 664 Black', 'PRD000010', 'Bottles', 22, ' 280', '23 Aug 2026', NULL, 'no_image.png'),
(11, 'Epson Ink 664 Cyan', 'PRD000011', 'Bottles', 23, '290', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(12, 'Epson Ink 664 Magenta', 'PRD000012', 'Bottles', 23, '290', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(13, 'Epson Ink 664 Yellow', 'PRD000013', 'Bottles', 18, '290', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(14, 'Canon Ink Cyan', 'PRD000014', 'Pcs', 3, '650', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(15, 'Canon Ink Yellow', 'PRD000015', 'Pcs', 3, '350', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(16, 'Canon Ink Magenta', 'PRD000016', 'Pcs', 3, '350', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(17, 'Canon Ink Black', 'PRD000017', 'Pcs', 1, '450', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(18, 'Flex Stick', 'PRD000018', 'Pcs', 100, '15', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(19, 'Staples Wire #35', 'PRD000019', 'Box', 16, '75', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(20, 'HBW Stapler', 'PRD000020', 'Box', 3, ' 185', '23 Aug 2026', NULL, 'no_image.png'),
(21, 'Binder Clips 1 1/4 Inc', 'PRD000021', 'Pcs', 91, '6', '07 April 2026 8:00 AM', '', 'no_image.png'),
(22, 'Mongol 2 Pencil', 'PRD000022', 'Pcs', 12, '12', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(23, 'Correction Tape', 'PRD000023', 'Pcs', 2, '65', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(24, 'Mouse Logitech', 'PRD000024', 'Pcs', 2, '450', '07 April 2026 8:00 AM', NULL, 'Mouse Logitech.png'),
(25, 'Gold Sticker', 'PRD000025', 'Box', 40, '80', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(26, 'Paper Clip 50mm', 'PRD000026', 'Packs', 11, '65', '07 April 2026 8:00 AM', '', 'no_image.png'),
(27, 'Paper Clip 33mm', 'PRD000027', 'Packs', 10, '45', '07 April 2026 8:00 AM', '', 'no_image.png'),
(28, 'HBW Scissors', 'PRD000028', 'Pcs', 10, '180', '07 April 2026 8:00 AM', '', 'no_image.png'),
(29, 'Elmer\'s Glue 130g', 'PRD000029', 'Pcs', 5, '65', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(30, 'Elmer\'s Glue 240g', 'PRD000030', 'Pcs', 3, '130', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(31, 'Paper Fastener', 'PRD000031', 'Packs', 11, ' 100', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(32, 'Excel highligher', 'PRD000032', 'Pcs', 11, '65', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(33, 'Heavy Duty Staples', 'PRD000033', 'Box', 2, '65', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(34, 'Matrix Ballpen Black', 'PRD000034', 'Pcs', 15, '10', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(35, 'Thumb Tax', 'PRD000035', 'Packs', 2, '10', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(36, 'Push Pin', 'PRD000036', 'Packs', 7, '50', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(37, 'Siamese Cable', 'PRD000037', 'Three Hundred Meters', 1, '4200', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(38, 'Matrix Ballpen Blue', 'PRD000038', 'Pcs', 46, '10', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(39, 'Battery Energizer AA', 'PRD000039', 'Pcs', 22, '70', '07 April 2026 8:00 AM', '', 'no_image.png'),
(40, 'Ruler', 'PRD000040', 'Pcs', 11, '10', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(41, 'Excel White Board', 'PRD000041', 'Pcs', 1, '49', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(42, 'Rubber Band', 'PRD000042', 'Box', 1, '180', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(43, 'Battery Energizer AAA', 'PRD000043', 'Pcs', 6, '75', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(44, 'ID Card Holder', 'PRD000044', 'Pcs', 12, '10', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(45, 'Panda Sign Pen', 'PRD000045', 'Pcs', 12, '75', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(46, 'Panda Gel Tech Pen', 'PRD000046', 'Pcs', 12, '45', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(47, 'Rambo G-Tech Pen', 'PRD000047', 'Pcs', 12, '55', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(48, 'Scotch Tape 24mm', 'PRD000048', 'Rolls', 10, '45', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(49, 'Scotch Tape 2 inch', 'PRD000049', 'Rolls', 22, '65', '07 April 2026 8:00 AM', '', 'no_image.png'),
(50, 'Packaging Tape', 'PRD000050', 'Rolls', 1, '65', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(51, 'Double Sided Tape 1 inch', 'PRD000051', 'Rolls', 16, '45', '07 April 2026 8:00 AM', '', 'no_image.png'),
(52, 'Ink Toner Cartridge', 'PRD000052', 'Boxes', 2, '280', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(53, 'HBW Heavy Duty Puncher', 'PRD000053', 'Boxes', 1, ' 250', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(54, 'Stick Glue Big', 'PRD000054', 'Pcs', 1, '10', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(55, 'Check Soap', 'PRD000055', 'Pcs', 0, '65', '07 April 2026 8:00 AM', '', 'no_image.png'),
(56, 'HBW Tape Dispenser', 'PRD000056', 'Boxes', 2, '250', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(57, 'Brown Envelop Short', 'PRD000057', 'Pcs', 42, '10', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(58, 'Brown Envelop A4', 'PRD000058', 'Pcs', 69, '10', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(59, 'Brown Envelop Long', 'PRD000059', 'Pcs', 68, '10', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(60, 'Expanded Folder Long Yellow', 'PRD000060', 'Pcs', 3, ' 25', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(61, 'Expanded Folder Long Green', 'PRD000061', 'Pcs', 20, ' 25', '23 Aug 2026', NULL, 'no_image.png'),
(62, 'Expanded Folder Long Orange', 'PRD000062', 'Pcs', 2, ' 25', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(63, 'Expanded Folder Long Pink', 'PRD000063', 'Pcs', 2, '25', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(64, 'Rambo A4 Cetificate Holder Blue', 'PRD000064', 'Pcs', 20, '60', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(65, 'Rambo A4 Cetificate Holder Green', 'PRD000065', 'Pcs', 8, '60', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(66, 'Rambo A4 Cetificate Holder Black', 'PRD000066', 'Pcs', 7, '60', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(67, 'Joy Certificate Holder A4 Red', 'PRD000067', 'Pcs', 23, '60', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(68, 'Plastic Certificate', 'PRD000068', 'Pcs', 1, '160', '07 April 2026 8:00 AM', 'With handle Violet', 'no_image.png'),
(69, 'Plastic Certificate', 'PRD000069', 'Pcs', 1, '160', '07 April 2026 8:00 AM', ' With handle Orange', 'no_image.png'),
(70, 'Clear Book Pink', 'PRD000070', 'Pcs', 1, '65', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(71, 'Clear Book Orange', 'PRD000071', 'Pcs', 1, '65', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(72, 'Clear Book Yellow', 'PRD000072', 'Pcs', 1, '65', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(73, 'Clear Book Blue', 'PRD000073', 'Pcs', 1, '65', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(74, 'Clear Book Green', 'PRD000074', 'Pcs', 1, '65', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(75, 'Clear Book Red', 'PRD000075', 'Pcs', 1, '65', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(76, 'Mouse Pad', 'PRD000076', 'Pcs', 2, '50', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(77, 'Plastic Envelop Long', 'PRD000077', 'Pcs', 100, '15', '07 April 2026 8:00 AM', '', 'no_image.png'),
(78, 'Jacket Folder Long', 'PRD000078', 'Pcs', 100, '25', '07 April 2026 8:00 AM', '', 'no_image.png'),
(79, 'Jacket Folder A4', 'PRD000079', 'Pcs', 50, '20', '07 April 2026 8:00 AM', '', 'no_image.png'),
(80, 'Gun Powerful', 'PRD000080', 'Pcs', 2, '250', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(81, 'Joy Certificate Holder two sided', 'PRD000081', 'Pcs', 9, '95', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(82, 'A4 Folder White', 'PRD000082', 'Reams', 4, ' 8.50', '19 Aug 2026', NULL, 'no_image.png'),
(83, 'Long Folder White', 'PRD000083', 'Ream', 4, '850', '07 April 2026 8:00 AM', '', 'no_image.png'),
(84, 'File Folder long brown', 'PRD000084', 'Pcs', 83, '10', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(85, 'File Folder A4 brown', 'PRD000085', 'Pcs', 30, '10', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(86, 'File Folder long white', 'PRD000086', 'Pcs', 14, '10', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(87, 'File Folder A4 white', 'PRD000087', 'Pcs', 0, '10', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(88, 'Tissue', 'PRD000088', 'Packs', 6, '240', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(89, 'Business Envelope', 'PRD000089', 'Pcs', 26, '10', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(90, 'Expandable Envelope', 'PRD000090', 'Pcs', 30, '25', '07 April 2026 8:00 AM', 'with String Long', 'no_image.png'),
(91, 'Medal Bronze 6m', 'PRD000091', 'Pcs', 42, '65', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(92, 'Worx Paper long 90gsm', 'PRD000092', 'Reams', 4, '280', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(93, 'Vellum A4 180 gms', 'PRD000093', 'Reams', 4, '370', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(94, 'Sticker Paper', 'PRD000094', 'Packs', 21, '100', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(95, 'Yellow Pad', 'PRD000095', 'Pad', 9, '65', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(96, 'Legal Coupon', 'PRD000096', 'Reams', 80, ' 240', '23 Aug 2026', NULL, 'no_image.png'),
(97, 'A4 Coupon', 'PRD000097', 'Box', 10, '230', '23 August 2026 12:58 AM', '', 'no_image.png'),
(98, 'Coupon short', 'PRD000098', 'Reams', 3, '200', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(99, 'Brother ink Yellow', 'PRD000099', 'Pcs', 2, '390', '07 April 2026 8:00 AM', '', 'no_image.png'),
(100, 'Brother ink Cyan', 'PRD000100', 'Pcs', 2, '390', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(101, 'Brother Ink Magenta', 'PRD000101', 'Pcs', 2, '390', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(102, 'Brother Ink Blk', 'PRD000102', 'Pcs', 5, '440', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(103, '4 layers File Desk Organizer', 'PRD000103', 'Pcs', 0, '404', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(104, 'HIK Vision Analog ColorVu', 'PRD000104', 'Box', 1, '4000', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(105, 'Dahua POE 2.0 4-Port 10/100Mbps', 'PRD000105', 'Pc', 1, '4000', '07 April 2026 8:00 AM', '+2-Port Gigabit', 'no_image.png'),
(106, 'HD Camera Full Color 360°', 'PRD000106', 'Pcs', 1, '6000', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(107, 'Alert Toothpaste Advanced Care', 'PRD000107', 'Pcs', 4, '150', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(108, 'Photo Paper 180 gsm', 'PRD000108', 'Packs', 20, '100', '07 April 2026 8:00 AM', '', 'no_image.png'),
(109, '60-watts Bosca Flood Light with Panel', 'PRD000109', 'set', 1, '3200', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(110, 'Tuff Liquid Detergent 1000 ml', 'PRD000110', 'Pcs', 13, '380', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(111, 'Tuff Toilet Bowl 1000 ml', 'PRD000111', 'Pc', 0, '360', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(112, 'Chiq 1.0 HP Inverter Split Type Aircon', 'PRD000112', 'Set', 1, '25900', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(113, 'Chiq 1.5 HP Inverter Split Type Aircon', 'PRD000113', 'Set', 1, '29900', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(114, 'Chiq 2.0HP Inverter  Split Type Aircon', 'PRD000114', 'Set', 1, '34900', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(115, 'Chiq 2.5HP Inverter Split Type Aircon', 'PRD000115', 'Set', 1, '43900', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(116, 'Cameras: 4 x 2.0MP Wide Angle', 'PRD000116', 'Set', 0, '19900', '07 April 2026 8:00 AM', 'Package - DVR: HIK Vision 4-Channel DVR - Installation: Free - Warranty: 1 Year', 'no_image.png'),
(117, 'CCTV Package 2 Cameras: 8 x 2.0MP Wide Angle', 'PRD000117', 'Package', 1, '28990', '18 August 2026 7:46 AM', 'Package 2 - DVR: HIK Vision 8-Channel DVR - Installation: Free - Warranty: 1 Year', 'no_image.png'),
(118, 'Cameras: 4 x 2.0MP Wide Angle', 'PRD000118', 'Set', 0, '23900', '07 April 2026 8:00 AM', 'Package 3:  (Upgrade Ready) DVR: HIK Vision 8-Channel DVR (future expansion capability) - Installation: Free - Warranty: 1 Year', 'no_image.png'),
(119, 'Cameras: 6 x 2.0MP Wide Angle', 'PRD000119', 'Set', 1, '24900', '07 April 2026 8:00 AM', 'Package 4: (Upgrade Ready)- DVR: HIK Vision 8-Channel DVR (future expansion capability) - Installation: Free - Warranty: 1 Year', 'no_image.png'),
(120, 'Long folder brown', 'PRD000120', 'Reams', 1, '900', '07 April 2026 8:00 AM', NULL, 'no_image.png'),
(121, 'CCTV Premium package', 'PRD000121', 'Set', 1, '100000', '07 April 2026 8:00 AM', 'HIK DVR with AoC support (8-ch) HIK 2.0mp x 8 camera Color Vu (24-hour full colour)  Free Installation 1 year warranty', 'no_image.png'),
(122, 'Power Connector', 'PRD000122', 'Pcs', 38, '15', '07 April 2026 8:00 AM', '', 'no_image.png'),
(123, 'Male Power Connector', 'PRD000123', 'Pcs', 50, '20', '07 April 2026 8:00 AM', 'Male Power Connector', 'no_image.png'),
(124, 'HIK Vision DVR  With Hard Drive', 'PRD000124', 'Pcs', 3, '4200', '07 April 2026 8:00 AM', '8 channel Analog', 'no_image.png'),
(125, 'Bosca Solar Flood Light with Panel', 'PRD000125', 'Pcs', 0, '5300', '07 April 2026 8:00 AM', '100 watts', 'no_image.png'),
(126, 'Kraft Folder Legal', 'PRD000126', 'Packs', 0, '850', '07 April 2026 8:00 AM', '', 'no_image.png'),
(127, 'Epson printer', 'PRD000127', 'Pcs', 1, '12000', '07 April 2026 8:00 AM', 'L3210', 'no_image.png'),
(128, 'HIK Vision DVR 8-Ch With Hard Drive', 'PRD000128', 'Pcs', 0, '4200', '07 April 2026 8:00 AM', '', 'no_image.png'),
(129, 'Detergent Powder/kilo', 'PRD000129', 'Pcs', 10, ' 180', '07 April 2026 8:00 AM', '', 'no_image.png'),
(130, 'Muriatic Acid 500ml', 'PRD000130', 'Pcs', 0, '169', '07 April 2026 8:00 AM', '', 'no_image.png'),
(131, 'Gallon Dishwashing liquid', 'PRD000131', 'Gallon', 1, '250', '07 April 2026 8:00 AM', '', 'no_image.png'),
(132, 'Gallon Alcohol', 'PRD000132', 'Pcs', 2, '600', '07 April 2026 8:00 AM', '', 'no_image.png'),
(133, 'Walis tingting', 'PRD000133', 'Pcs', 15, ' 50', '07 April 2026 8:00 AM', '', 'no_image.png'),
(134, 'Rake/kalykay', 'PRD000134', 'Pcs', 0, '650', '07 April 2026 8:00 AM', '', 'no_image.png'),
(135, 'Junction Box 8cm', 'PRD000135', 'Pcs', 10, '85', '07 April 2026 8:00 AM', '', 'no_image.png'),
(136, 'Alcohol', 'PRD000136', 'Gallon', 1, '631.49', '07 April 2026 8:00 AM', '', 'no_image.png'),
(137, 'Soft broom (walis tambo)', 'PRD000137', 'Pcs', 0, '250', '07 April 2026 8:00 AM', '', 'no_image.png'),
(138, 'Dust pan', 'PRD000138', 'Pcs', 0, '95', '07 April 2026 8:00 AM', '', 'no_image.png'),
(139, 'Pilot Permanent Marker', 'PRD000139', 'Pcs', 12, '49', '07 April 2026 8:00 AM', '', 'no_image.png'),
(140, 'White board marker', 'PRD000140', 'Pcs', 24, '49', '07 April 2026 8:00 AM', '', 'no_image.png'),
(141, 'FlexStick ballpen', 'PRD000141', 'Pack', 11, ' 150', '07 April 2026 8:00 AM', '', 'no_image.png'),
(142, 'Trash Bag', 'PRD000142', 'Pcs', 5, '120', '07 April 2026 8:00 AM', 'Size: M', 'no_image.png'),
(143, 'Toilet Powder Cleaner', 'PRD000143', 'Pack', 20, '185', '07 April 2026 8:00 AM', '', 'no_image.png'),
(144, 'Toilet Bleach', 'PRD000144', 'Bottle', 20, '360', '07 April 2026 8:00 AM', '', 'no_image.png'),
(145, 'Walis Tambo', 'PRD000145', 'Pcs', 10, '250', '07 April 2026 8:00 AM', '', 'no_image.png'),
(146, 'Ballpen Green', 'PRD000146', 'Pack', 0, ' 150', '07 April 2026 8:00 AM', '', 'no_image.png'),
(147, 'File Folder Long', 'PRD000147', 'Ream', 0, ' 850', '07 April 2026 8:00 AM', '', 'no_image.png'),
(148, 'File Folder A4', 'PRD000148', 'Ream', 2, '800', '07 April 2026 8:00 AM', '', 'no_image.png'),
(149, 'Latex Gloss Paint 16 Liters', 'PRD000149', 'Gallons', 0, '3700', '07 April 2026 8:00 AM', '16 Liters', 'no_image.png'),
(150, 'Enamel Gloss Paint 16 Liters', 'PRD000150', 'Gallons', 1, '3700', '07 April 2026 8:00 AM', '', 'no_image.png'),
(151, 'Raw Sienna 1 Liters (tenting color)', 'PRD000151', 'Gallons', 4, '280', '07 April 2026 8:00 AM', '', 'no_image.png'),
(152, 'Yellow 1 Liters (tenting color)', 'PRD000152', 'Gallons', 4, '280', '07 April 2026 8:00 AM', '', 'no_image.png'),
(153, 'Paint Brush', 'PRD000153', 'Pcs', 0, '95', '07 April 2026 8:00 AM', '', 'no_image.png'),
(154, 'Roller Brush', 'PRD000154', 'Pcs', 0, '95', '07 April 2026 8:00 AM', '', 'no_image.png'),
(155, 'Thinner', 'PRD000155', 'Bottle', 0, '150', '07 April 2026 8:00 AM', '', 'no_image.png'),
(156, 'Retractable Ballpen Black', 'PRD000156', 'Pcs', 0, '35', '07 April 2026 8:00 AM', '', 'no_image.png'),
(157, 'Retractable Ballpen Red', 'PRD000157', 'Pcs', 0, '35', '07 April 2026 8:00 AM', '', 'no_image.png'),
(158, 'Expanded Folder Blue', 'PRD000158', 'Pcs', 0, '28', '07 April 2026 8:00 AM', '', 'no_image.png'),
(159, 'Tuff TBC 1 Liters', 'PRD000159', 'Bottle', 2, ' 315', '07 April 2026 8:00 AM', '', 'no_image.png'),
(160, 'Bottled Hand Soap', 'PRD000160', 'Bottle', 10, ' 180', '07 April 2026 8:00 AM', '', 'no_image.png'),
(161, 'Joy Diswashing Liquid', 'PRD000161', 'Pcs', 2, '94.79', '07 April 2026 8:00 AM', '', 'no_image.png'),
(162, 'Thalo green paint', 'PRD000162', 'Gallon', 0, '1350', '07 April 2026 8:00 AM', '', 'no_image.png'),
(163, 'Lemon yellow paint', 'PRD000163', 'Gallon', 0, '1350', '07 April 2026 8:00 AM', '', 'no_image.png'),
(164, 'White paint', 'PRD000164', 'Gallon', 0, '1350', '07 April 2026 8:00 AM', '', 'no_image.png'),
(165, 'Raw Sienna', 'PRD000165', 'Liter', 0, '390', '07 April 2026 8:00 AM', '', 'no_image.png'),
(166, 'Brown paint', 'PRD000166', 'Gallon', 0, '1350', '07 April 2026 8:00 AM', '', 'no_image.png'),
(167, 'Epson Ink 003 (Set)', 'PRD000167', 'Set', 0, '1150', '14 Aug 2026', '', 'no_image.png'),
(168, 'Epson Ink 664 set', 'PRD000168', 'Set', 10, ' 1150', '23 Aug 2026', '', 'no_image.png'),
(169, 'Stappler # 26/6', 'PRD000169', 'Pcs', 0, '65', '07 April 2026 8:00 AM', '', 'no_image.png'),
(170, 'Ballpen Black', 'PRD000170', 'TUBB', 0, '150', '07 April 2026 8:00 AM', '', 'no_image.png'),
(171, 'Epson Ink 004', 'PRD000171', 'Set', 97, '1150', '07 April 2026 8:00 AM', '', 'no_image.png'),
(172, 'Pentel Pen Black', 'PRD000172', 'Pcs', 0, '49', '07 April 2026 8:00 AM', '', 'no_image.png'),
(173, 'USB 8GB', 'PRD000173', 'Pcs', 2, '450', '07 April 2026 8:00 AM', '', 'no_image.png'),
(174, 'USB 64GB', 'PRD000174', 'Pcs', 3, '650', '07 April 2026 8:00 AM', '', 'no_image.png'),
(175, 'PVC Wall Panel', 'PRD000175', 'Pcs', 0, '1200', '07 April 2026 8:00 AM', '', 'no_image.png'),
(176, 'UV Ceramic Panel', 'PRD000176', 'Pcs', 0, '3500', '07 April 2026 8:00 AM', '', 'no_image.png'),
(177, 'LED Panel Light 12+4 watts', 'PRD000177', 'Pcs', 0, '1130', '07 April 2026 8:00 AM', '', 'no_image.png'),
(178, 'LED Panel Light 6+3 watts', 'PRD000178', 'Pcs', 0, '720', '07 April 2026 8:00 AM', '', 'no_image.png'),
(179, 'LED Panel Light 3+3 watts', 'PRD000179', 'Pcs', 0, '650', '07 April 2026 8:00 AM', '', 'no_image.png'),
(180, 'Boysen Paint', 'PRD000180', 'Pcs', 0, '1600', '07 April 2026 8:00 AM', '', 'no_image.png'),
(181, 'Pallets', 'PRD000181', 'Pcs', 0, '95', '07 April 2026 8:00 AM', '', 'no_image.png'),
(182, 'Roller Paint Brush', 'PRD000182', 'Pcs', 0, '165', '07 April 2026 8:00 AM', '', 'no_image.png'),
(183, 'Screws', 'PRD000183', 'Pcs', 0, '460', '07 April 2026 8:00 AM', '', 'no_image.png'),
(184, 'Tox screw', 'PRD000184', 'Pcs', 0, '460', '07 April 2026 8:00 AM', '', 'no_image.png'),
(185, 'Fine sandpaper', 'PRD000185', 'Pcs', 0, '250', '07 April 2026 8:00 AM', '', 'no_image.png'),
(186, 'Construction Adhesive', 'PRD000186', 'Pcs', 0, '390', '07 April 2026 8:00 AM', '', 'no_image.png'),
(187, 'Skilled Worker', 'PRD000187', 'Pcs', 2, '800', '07 April 2026 8:00 AM', 'Labor (2 days)', 'no_image.png'),
(188, 'Helper', 'PRD000188', 'Pcs', 2, '600', '07 April 2026 8:00 AM', 'Labor (2 days)', 'no_image.png'),
(189, 'Wall Putty', 'PRD000189', 'Pcs', 0, '950', '07 April 2026 8:00 AM', '', 'no_image.png'),
(190, 'Plate with partition', 'PRD000190', 'Pcs', 0, '73', '07 April 2026 8:00 AM', '', 'no_image.png'),
(191, 'Bowl', 'PRD000191', 'Pcs', 0, '50', '07 April 2026 8:00 AM', '', 'no_image.png'),
(192, 'Platito', 'PRD000192', 'Pcs', 38, '48', '07 April 2026 8:00 AM', '', 'no_image.png'),
(193, 'Spoon', 'PRD000193', 'Pcs', 0, '121.25', '07 April 2026 8:00 AM', 'dozen', 'no_image.png'),
(194, 'Fork', 'PRD000194', 'Pcs', 0, '121.25', '07 April 2026 8:00 AM', 'Dozen', 'no_image.png'),
(195, 'Mop with spinner', 'PRD000195', 'Pcs', 1, ' 450', '14 Aug 2026', '', 'no_image.png'),
(196, 'Trash bin (240L)', 'PRD000196', 'Pcs', 1, '3000', '07 April 2026 8:00 AM', '', 'no_image.png'),
(197, 'Rechargeable Sprayer', 'PRD000197', 'Pcs', 0, ' 2419.58', '07 April 2026 8:00 AM', '', 'no_image.png'),
(198, 'Rake', 'PRD000198', 'Pcs', 0, '650', '07 April 2026 8:00 AM', '', 'no_image.png'),
(199, 'Bareta (digging)', 'PRD000199', 'Pcs', 1, '650', '07 April 2026 8:00 AM', '', 'no_image.png'),
(200, 'Spade Pork', 'PRD000200', 'Pcs', 0, ' 350', '07 April 2026 8:00 AM', '', 'no_image.png'),
(201, 'Shovel', 'PRD000201', 'Pcs', 0, '350', '07 April 2026 8:00 AM', '', 'no_image.png'),
(202, 'Fabric softener', 'PRD000202', 'Pcs', 0, ' 380', '07 April 2026 8:00 AM', '', 'no_image.png'),
(203, 'Tuff TBC', 'PRD000203', 'Pcs', 0, ' 380', '07 April 2026 8:00 AM', '', 'no_image.png'),
(204, 'Hand saw', 'PRD000204', 'Pcs', 0, ' 350', '07 April 2026 8:00 AM', '', 'no_image.png'),
(205, 'Latex White Paint', 'PRD000205', 'Pail', 7, '3700', '07 April 2026 8:00 AM', '', 'no_image.png'),
(206, 'Raw Sienna Paint', 'PRD000206', 'Liter', 6, '250', '07 April 2026 8:00 AM', '', 'no_image.png'),
(207, 'Baguio Green Paint', 'PRD000207', 'Gal', 4, '1350', '07 April 2026 8:00 AM', '', 'no_image.png'),
(208, 'Paint Roller', 'PRD000208', 'Pcs', 6, '150', '07 April 2026 8:00 AM', '', 'no_image.png'),
(209, 'Paint Tray', 'PRD000209', 'Pcs', 8, '140', '07 April 2026 8:00 AM', '', 'no_image.png'),
(210, 'Paint Brush 1', 'PRD000210', 'Pcs', 6, '95', '07 April 2026 8:00 AM', '', 'no_image.png'),
(211, 'Paint Brush 3', 'PRD000211', 'Pcs', 8, '130', '07 April 2026 8:00 AM', '', 'no_image.png'),
(212, 'Paint Brush 5', 'PRD000212', 'Pcs', 6, '180', '07 April 2026 8:00 AM', '', 'no_image.png'),
(213, 'Disinfectant Spray', 'PRD000213', 'Pcs', 35, '480', '07 April 2026 8:00 AM', '', 'no_image.png'),
(214, 'Tuff toilet cleaner', 'PRD000214', 'Bottles', 5, '380', '07 April 2026 8:00 AM', '', 'no_image.png'),
(215, 'Mriatic Acid', 'PRD000215', 'Bottles', 10, '180', '07 April 2026 8:00 AM', '', 'no_image.png'),
(216, 'Bluwave, detergent poweder', 'PRD000216', 'Packs', 16, '180', '07 April 2026 8:00 AM', '', 'no_image.png'),
(217, 'Varnish', 'PRD000217', 'Bottles', 0, '135', '07 April 2026 8:00 AM', '', 'no_image.png'),
(218, 'Smart TV', 'PRD000218', 'Unit', 9, ' 18000', '07 April 2026 8:00 AM', '', 'no_image.png'),
(219, 'Epson L3210 Printer', 'PRD000219', 'Unit', 9, '12000', '07 April 2026 8:00 AM', '', 'no_image.png'),
(220, 'Toilet Tissue', 'PRD000220', 'Packs', 3, '240', '07 April 2026 8:00 AM', '', 'no_image.png'),
(221, 'Muriatic Acid', 'PRD000221', 'Bottles', 5, '180', '07 April 2026 8:00 AM', '', 'no_image.png'),
(222, 'Board with Frame (4x8)', 'PRD000222', 'Pcs', 0, '6500', '07 April 2026 8:00 AM', '', 'no_image.png'),
(223, 'Tubular (1x2)', 'PRD000223', 'Pcs', 0, '650', '07 April 2026 8:00 AM', '', 'no_image.png'),
(224, 'Welding Rod', 'PRD000224', 'Pcs', 0, '25', '07 April 2026 8:00 AM', '', 'no_image.png'),
(225, 'Sliding Rollers', 'PRD000225', 'Pcs', 0, '350', '07 April 2026 8:00 AM', '', 'no_image.png'),
(226, 'Hand Drill with Sander', 'PRD000226', 'Set', 0, '2400.38', '07 April 2026 8:00 AM', '', 'no_image.png'),
(227, 'Tools', 'PRD000227', 'Set', 0, '350', '07 April 2026 8:00 AM', '', 'no_image.png'),
(228, 'Grass cutter Rechargeable', 'PRD000228', 'Pcs', 0, '3618', '07 April 2026 8:00 AM', '', 'no_image.png'),
(229, 'Wheel Barrow', 'PRD000229', 'Pcs', 0, '4500', '07 April 2026 8:00 AM', '', 'no_image.png'),
(230, 'Grass cutter Scissor', 'PRD000230', 'Pcs', 0, '590', '07 April 2026 8:00 AM', '', 'no_image.png'),
(231, 'Bareta/Digging 3ft', 'PRD000231', 'Pcs', 0, '650', '07 April 2026 8:00 AM', '', 'no_image.png'),
(232, 'Bareta/Digging 4ft', 'PRD000232', 'Pcs', 0, '750', '07 April 2026 8:00 AM', '', 'no_image.png'),
(233, 'Crowbar 45 cm', 'PRD000233', 'Pcs', 0, '400', '07 April 2026 8:00 AM', '', 'no_image.png'),
(234, 'CrowbAR 70 CM', 'PRD000234', 'Pcs', 0, '500', '07 April 2026 8:00 AM', '', 'no_image.png'),
(235, 'Itak straight', 'PRD000235', 'Pcs', 0, '660', '07 April 2026 8:00 AM', '', 'no_image.png'),
(236, 'Itak kawit', 'PRD000236', 'Pcs', 0, '660', '07 April 2026 8:00 AM', '', 'no_image.png'),
(237, 'Double Sided step Ladder- 8 steps', 'PRD000237', 'Pcs', 0, '3200', '07 April 2026 8:00 AM', '', 'no_image.png'),
(238, 'Handsaw', 'PRD000238', 'Pcs', 0, '350', '07 April 2026 8:00 AM', '', 'no_image.png'),
(239, 'Maso with Handle', 'PRD000239', 'Pcs', 0, '300', '07 April 2026 8:00 AM', '', 'no_image.png'),
(240, 'Hammer', 'PRD000240', 'Pcs', 0, '300', '07 April 2026 8:00 AM', '', 'no_image.png'),
(241, 'Yellow Nylon Rope 14mm', 'PRD000241', 'ROLL', 0, '800', '07 April 2026 8:00 AM', '', 'no_image.png'),
(242, 'Nylon String', 'PRD000242', 'kg', 0, '650', '07 April 2026 8:00 AM', '', 'no_image.png'),
(243, 'Walis Pang-agiw', 'PRD000243', 'Pcs', 0, ' 180', '07 April 2026 8:00 AM', '', 'no_image.png'),
(244, 'Hook Screw 1\"', 'PRD000244', 'Packs', 0, '1.6', '07 April 2026 8:00 AM', '', 'no_image.png'),
(245, 'Rust Remover', 'PRD000245', 'Bottles', 0, '280.5', '07 April 2026 8:00 AM', '', 'no_image.png'),
(246, 'Toilet bowl and urinal cleaner (Tuff)', 'PRD000246', 'Bottle', 0, '380', '07 April 2026 8:00 AM', '', 'no_image.png'),
(247, 'Trolley trash bin', 'PRD000247', 'Pcs', 5, '1500', '07 April 2026 8:00 AM', '', 'no_image.png'),
(248, 'Bolo', 'PRD000248', 'Pcs', 1, '650', '07 April 2026 8:00 AM', '', 'no_image.png'),
(249, 'Pail orocan assorted color', 'PRD000249', 'Pcs', 4, ' 280', '14 Aug 2026', '', 'no_image.png'),
(250, 'Trolley cart', 'PRD000250', 'Pc', 1, '1800', '07 April 2026 8:00 AM', '', 'no_image.png'),
(251, 'SIAMESE CABLE 100 METERS', 'PRD000251', 'ROLL', 0, '2500', '07 April 2026 8:00 AM', '', 'no_image.png'),
(252, 'Outlet 3 Gang', 'PRD000252', 'set', 0, '247', '07 April 2026 8:00 AM', '', 'no_image.png'),
(253, 'Outlet 2 Gang', 'PRD000253', 'Pcs', 0, '192.4', '07 April 2026 8:00 AM', '', 'no_image.png'),
(254, 'Switch 2 Gang', 'PRD000254', 'set', 0, '211.9', '07 April 2026 8:00 AM', '', 'no_image.png'),
(255, 'Switch 1 Gang', 'PRD000255', 'sets', 0, '169', '07 April 2026 8:00 AM', '', 'no_image.png'),
(256, 'Switch 3 way 1 Gang', 'PRD000256', 'sets', 0, '275', '07 April 2026 8:00 AM', '', 'no_image.png'),
(257, '2 Gang Switch 3 way', 'PRD000257', 'pc', 0, '350', '07 April 2026 8:00 AM', '', 'no_image.png'),
(258, 'Secondary Rock 2 Poles (ARC)', 'PRD000258', 'SETS', 0, '590', '07 April 2026 8:00 AM', '', 'no_image.png'),
(259, 'Screw Porcelain Insulator Big', 'PRD000259', 'Pcs', 0, '52', '07 April 2026 8:00 AM', '', 'no_image.png'),
(260, 'Entrance Cap #1 1/4', 'PRD000260', 'Pcs', 0, '104', '07 April 2026 8:00 AM', '', 'no_image.png'),
(261, 'PVC Pipe #1 1/4', 'PRD000261', 'Pcs', 0, '195', '07 April 2026 8:00 AM', '', 'no_image.png'),
(262, 'Receptacle', 'PRD000262', 'Pcs', 0, '65', '07 April 2026 8:00 AM', '', 'no_image.png'),
(263, 'LED Bulb', 'PRD000263', 'Pcs', 0, '450', '07 April 2026 8:00 AM', '', 'no_image.png'),
(264, 'Faucets', 'PRD000264', 'Pcs', 0, '95', '07 April 2026 8:00 AM', '', 'no_image.png'),
(265, 'Tee', 'PRD000265', 'Pcs', 0, '45', '07 April 2026 8:00 AM', '', 'no_image.png'),
(266, 'Elbow', 'PRD000266', 'Pcs', 0, '45', '07 April 2026 8:00 AM', '', 'no_image.png'),
(267, 'Coupling', 'PRD000267', 'Pcs', 0, '65', '07 April 2026 8:00 AM', '', 'no_image.png'),
(268, 'Female Adapter', 'PRD000268', 'Pcs', 0, '65', '07 April 2026 8:00 AM', '', 'no_image.png'),
(269, 'Neltex', 'PRD000269', 'Pcs', 0, '220', '07 April 2026 8:00 AM', '', 'no_image.png'),
(270, 'Tiles, 40x40', 'PRD000270', 'Pcs', 0, '78', '07 April 2026 8:00 AM', '', 'no_image.png'),
(271, 'Cement', 'PRD000271', 'bag', 0, '280', '07 April 2026 8:00 AM', '', 'no_image.png'),
(272, 'Green paint for blackboard', 'PRD000272', 'Pcs', 0, '1350', '07 April 2026 8:00 AM', '', 'no_image.png'),
(273, 'Mahogany', 'PRD000273', 'Pcs', 0, '1350', '07 April 2026 8:00 AM', '', 'no_image.png'),
(274, 'Paint Brush #2', 'PRD000274', 'Pcs', 0, '95', '07 April 2026 8:00 AM', '', 'no_image.png'),
(275, 'Vulca seal', 'PRD000275', 'Pcs', 0, '850', '07 April 2026 8:00 AM', '', 'no_image.png'),
(276, 'Paint brush #4', 'PRD000276', 'Pcs', 0, '125', '07 April 2026 8:00 AM', '', 'no_image.png'),
(277, 'Green Paint', 'PRD000277', 'Gal', 3, '1350', '07 April 2026 8:00 AM', '', 'no_image.png'),
(278, 'Mahogany Paint', 'PRD000278', 'Gal', 2, '1350', '07 April 2026 8:00 AM', '', 'no_image.png'),
(279, 'Vulcaseal', 'PRD000279', 'Litters', 3, '850', '07 April 2026 8:00 AM', '', 'no_image.png'),
(280, 'Epson Printer L5290 (wifi)', 'PRD000280', 'Unit', 2, '17900', '07 April 2026 8:00 AM', 'Maximum warranty', 'no_image.png'),
(281, 'Energel .5 black', 'PRD000281', 'Pcs', 12, ' 98', '07 April 2026 8:00 AM', '', 'no_image.png'),
(282, 'G-tech .5 black', 'PRD000282', 'Pcs', 12, ' 95', '07 April 2026 8:00 AM', '', 'no_image.png'),
(283, 'G-tech .5 blue', 'PRD000283', 'Pcs', 12, '95', '07 April 2026 8:00 AM', '', 'no_image.png'),
(284, 'Energel .5 blue', 'PRD000284', 'Pcs', 12, ' 98', '07 April 2026 8:00 AM', '', 'no_image.png'),
(285, 'YHO #150(connector)', 'PRD000285', 'Pcs', 8, '200', '07 April 2026 8:00 AM', '', 'no_image.png'),
(286, 'Acer Aspire 5 Laptop', 'PRD000286', 'Unit', 0, '29990', '07 April 2026 8:00 AM', 'A514-54-344A\r\nSakura Pink\r\nWNindows 1 Home Single Languager Processor Intel@ CoreT i3.1115G4 LCD 14\" HD Acer ComfWiewM LCD . Graphics Intel6 UHD Graphics Merory 8CB DDR4 Memory Storage 512GB PCIe NVMe SSD', 'no_image.png'),
(287, 'Baby Wipes (100 sheets)', 'PRD000287', 'Pack', 10, ' 100', '07 April 2026 8:00 AM', '', 'no_image.png'),
(288, 'Water Container (blue)', 'PRD000288', 'Pcs', 10, '180', '07 April 2026 8:00 AM', '', 'no_image.png'),
(289, 'Water Drum 80 Liters', 'PRD000289', 'Pcs', 1, ' 450', '07 April 2026 8:00 AM', '', 'no_image.png'),
(290, 'Tuff', 'PRD000290', 'bottles', 0, '285', '07 April 2026 8:00 AM', '', 'Tuff.png'),
(291, 'Powder detergent', 'PRD000291', 'Packs', 0, '85', '07 April 2026 8:00 AM', '', 'no_image.png'),
(292, 'Plant trimmer', 'PRD000292', 'Pcs', 0, '751.67', '07 April 2026 8:00 AM', '', 'no_image.png'),
(293, 'Long folder (green)', 'PRD000293', 'Ream', 100, '950', '07 April 2026 8:00 AM', '50 pcs (ream)', 'no_image.png'),
(294, 'Stand fan (astron)', 'PRD000294', 'Pcs', 2, '2800', '07 April 2026 8:00 AM', '', 'no_image.png'),
(295, 'Wall fan', 'PRD000295', 'Pcs', 2, '2600', '07 April 2026 8:00 AM', '', 'no_image.png'),
(296, 'Desk fan', 'PRD000296', 'Pcs', 2, '2200', '07 April 2026 8:00 AM', '', 'no_image.png'),
(297, 'Digging Bar', 'PRD000297', 'Pcs', 0, '650', '07 April 2026 8:00 AM', '', 'no_image.png'),
(298, 'Trash Bin (60L)', 'PRD000298', 'Pcs', 0, '340', '07 April 2026 8:00 AM', '', 'no_image.png'),
(299, 'Plastic Mug', 'PRD000299', 'Pcs', 0, '20', '07 April 2026 8:00 AM', '', 'no_image.png'),
(300, 'Padlock', 'PRD000300', 'Pcs', 4, '450', '07 April 2026 8:00 AM', '', 'no_image.png'),
(301, 'Kitchen Paper Towel twin Pack', 'PRD000301', 'Pack', 2, '160', '07 April 2026 8:00 AM', '', 'no_image.png'),
(302, 'Flex Stick Green', 'PRD000302', 'Pack', 3, '150', '07 April 2026 8:00 AM', '', 'no_image.png'),
(303, 'Vellu Bond Long 180gsm (White)', 'PRD000303', 'Reams', 100, '230', '07 April 2026 8:00 AM', '', 'no_image.png'),
(304, 'Packaging Tape Thick', 'PRD000304', 'pcs', 24, '65', '07 April 2026 8:00 AM', '', 'no_image.png'),
(305, 'Ordinary Jacket Folder (A4)', 'PRD000305', 'Pcs', 50, '20', '07 April 2026 8:00 AM', '', 'no_image.png'),
(306, 'Press Broad Long', 'PRD000306', 'Pcs', 30, '25', '07 April 2026 8:00 AM', '', 'no_image.png'),
(307, 'Alcohol Safe ISO', 'PRD000307', 'Gallons', 1, '1', '07 April 2026 8:00 AM', '', 'no_image.png'),
(308, 'Pilot White Board Marker', 'PRD000308', 'Pcs', 20, '49', '07 April 2026 8:00 AM', '', 'no_image.png'),
(309, 'Flush door 210x150', 'PRD000309', 'Pcs', 5, '4500', '07 April 2026 8:00 AM', '', 'no_image.png'),
(310, 'Door', 'PRD000310', 'Pcs', 15, '250', '07 April 2026 8:00 AM', '', 'no_image.png'),
(311, 'Door knob', 'PRD000311', 'Pcs', 5, '850', '07 April 2026 8:00 AM', '', 'no_image.png'),
(312, 'Head frame', 'PRD000312', 'Pcs', 2, '1300', '07 April 2026 8:00 AM', '', 'no_image.png'),
(313, 'Still frame', 'PRD000313', 'Pcs', 2, '1400', '07 April 2026 8:00 AM', '', 'no_image.png'),
(314, 'Top bottom frame', 'PRD000314', 'Pcs', 2, '1300', '07 April 2026 8:00 AM', '', 'no_image.png'),
(315, 'Interlocker', 'PRD000315', 'Pcs', 2, '1100', '07 April 2026 8:00 AM', '', 'no_image.png'),
(316, 'Lockstie', 'PRD000316', 'Pcs', 2, '1100', '07 April 2026 8:00 AM', '', 'no_image.png'),
(317, 'Replective Glass', 'PRD000317', 'Pcs', 6, '1800', '07 April 2026 8:00 AM', '', 'no_image.png'),
(318, 'Silicone', 'PRD000318', 'Pcs', 12, '200', '07 April 2026 8:00 AM', '', 'no_image.png'),
(319, 'Swivel chair', 'PRD000319', 'Pcs', 5, ' 3744.4', '07 April 2026 8:00 AM', '', 'no_image.png'),
(320, 'Ecolum LED bulb', 'PRD000320', 'Pcs', 5, '450', '07 April 2026 8:00 AM', '', 'no_image.png'),
(321, 'Insect killer', 'PRD000321', 'Pcs', 2, '375', '07 April 2026 8:00 AM', '', 'no_image.png'),
(322, 'Meter tape', 'PRD000322', 'Pcs', 1, '275', '07 April 2026 8:00 AM', '', 'no_image.png'),
(323, 'Scotch Tape 1 inch', 'PRD000323', 'Pcs', 6, '35', '07 April 2026 8:00 AM', '', 'no_image.png'),
(324, 'Hearbs & Beauty Lotion', 'PRD000324', 'Pcs', 4, '1', '07 April 2026 8:00 AM', 'Enchanted Spell (2pcs), Joyful Bliss(1pcs), Passion Bloom(1pcs)', 'no_image.png'),
(325, 'Epson ink 003 blk', 'PRD000325', 'Bottles', 0, '274.48', '07 April 2026 8:00 AM', '', 'no_image.png'),
(326, 'Coupon A4', 'PRD000326', 'reams', 0, ' 230', '23 Aug 2026', '', 'no_image.png'),
(327, 'Green folder green', 'PRD000327', 'Pcs', 100, ' 20', '23 Aug 2026', '', 'no_image.png'),
(328, 'Folder long', 'PRD000328', 'Pcs', 100, '10', '07 April 2026 8:00 AM', '', 'no_image.png'),
(329, 'A4 folder', 'PRD000329', 'Pcs', 100, '9', '07 April 2026 8:00 AM', '', 'no_image.png'),
(330, 'Sliding folder long', 'PRD000330', 'Pcs', 100, '25', '07 April 2026 8:00 AM', '', 'no_image.png'),
(331, 'HIK Vision CCTV Camera 2.0Mp', 'PRD000331', 'Pcs', 8, '3000', '07 April 2026 8:00 AM', '', 'HIK Vision CCTV Camera 2.0Mp.png'),
(332, 'Stand fan, Camel', 'PRD000332', 'Pcs', 0, '3200', '14 Aug 2026', '', 'Stand fan, Camel.png'),
(333, 'Stand fan, Eureka', 'PRD000333', 'Pcs', 0, '2900', '07 April 2026 8:00 AM', '', 'Stand fan, Eureka.png'),
(334, 'Binder Clips 2 inch', 'PRD000334', 'Pcs', 60, '15', '07 April 2026 8:00 AM', '', 'no_image.png'),
(335, 'Worx Paper 200 gsm', 'PRD000335', 'Pcs', 1, '380', '07 April 2026 8:00 AM', '', 'no_image.png'),
(336, 'TPlink Router / Wifi Extender', 'PRD000336', 'Pcs', 2, '3500', '07 April 2026 8:00 AM', '', 'no_image.png'),
(337, 'CCTV Package 3', 'PRD000337', 'set', 0, '23990', '07 April 2026 8:00 AM', '1 HIKVISION, 4 Cameras, 1 Power Supply, 1 changer, 1 mouse', 'no_image.png'),
(338, 'Wifi repeater', 'PRD000338', 'Pcs', 1, '3500', '07 April 2026 8:00 AM', '', 'no_image.png'),
(339, 'Circuit Breaker BOLT ON 60AMP', 'PRD000339', 'Pcs', 0, ' 480', '07 April 2026 8:00 AM', '', 'no_image.png'),
(340, 'Electrical tape 20 yrd', 'PRD000340', 'Pcs', 0, ' 70', '07 April 2026 8:00 AM', '', 'no_image.png'),
(341, 'Electric wire#6', 'PRD000341', 'Pcs', 0, ' 210', '07 April 2026 8:00 AM', '', 'no_image.png'),
(342, 'Smart TV, 43', 'PRD000342', 'Pcs', 0, '19900', '07 April 2026 8:00 AM', '', 'Smart TV, 43inch.png'),
(343, 'Book Holder', 'PRD000343', 'Pcs', 0, '480', '07 April 2026 8:00 AM', '', 'no_image.png'),
(344, 'File organizer', 'PRD000344', 'Pcs', 0, '460', '07 April 2026 8:00 AM', '', 'no_image.png'),
(345, 'Metal Detector (for gate security?', 'PRD000345', 'Pcs', 1, '1820', '14 Aug 2026', '', 'Metal Detector.png'),
(346, 'Cork Bord', 'PRD000346', 'set', 0, '3500', '14 Aug 2026', '', 'no_image.png'),
(347, 'Organizer Cabinet', 'PRD000347', 'unit', 0, '2000', '14 Aug 2026', '', 'no_image.png'),
(348, 'Bulletin Board', 'PRD000348', 'unit', 0, '3000', '14 Aug 2026', '', 'no_image.png'),
(349, 'Printer 3 in 1', 'PRD000349', 'set', 0, '20000', '14 Aug 2026', '', 'no_image.png'),
(350, 'Printer ink 003', 'PRD000350', 'set', 0, '2000', '14 Aug 2026', '', 'Printer ink 003.png'),
(351, 'Printer ink Blk 003', 'PRD000351', 'Pcs', 0, '500', '14 Aug 2026', '', 'no_image.png'),
(352, 'Long Coupon', 'PRD000352', 'Box', 0, ' 1200', '07 April 2026 8:00 AM', '', 'no_image.png'),
(353, 'Stamp ink', 'PRD000353', 'Bottle', 0, ' 65', '07 April 2026 8:00 AM', '', 'no_image.png'),
(354, 'Epson ink 003', 'PRD000354', 'Set', 0, ' 1150', '07 April 2026 8:00 AM', '', 'no_image.png'),
(355, 'Devant Smart TV 43 inches', 'PRD000355', 'Pcs', 0, '19195', '14 Aug 2026', 'Smart TV', 'Devant Smart TV 43 inches.png'),
(356, 'HIK Vision CCTV package (16-ch DVR/ 12 Camera with Sounds)', 'PRD000356', 'Pcs', 1, '49990', '07 April 2026 8:00 AM', 'HIK Vision CCTV Customized Package', 'no_image.png'),
(357, '5 meter extention wire', 'PRD000357', 'Pcs', 2, ' 550', '19 Aug 2026', '', 'no_image.png'),
(358, 'Picture frame', 'PRD000358', 'Pcs', 10, '250', '07 April 2026 8:00 AM', '11 x 8.5 inches', 'no_image.png'),
(359, 'Bleach 1L', 'PRD000359', 'Pcs', 5, '35', '12 August 2026 6:51 PM', '', 'no_image.png'),
(360, 'Safeguard 55 grams', 'PRD000360', 'Pcs', 5, '25', '12 August 2026 6:52 PM', '', 'Safeguard 55 grams.png'),
(361, 'Brown folder A4', 'PRD000361', 'Ream', 2, '900', '13 August 2026 6:37 AM', '', 'no_image.png'),
(362, 'Red Dragon Mouse 2.4G Wireless Mouse', 'PRD000362', 'Pcs', 4, '600', '13 August 2026 2:25 PM', '', 'Red Dragon Mouse 2.4G Wireless Mouse.png'),
(363, 'Volleyball original (Mikasa)', 'PRD000363', 'Pcs', 2, '5990', '13 August 2026 4:07 PM', '', 'Volleyball original (Mikasa).png'),
(364, 'Basketball GG7X (Molten)', 'PRD000364', 'Pcs', 1, '3500', '13 August 2026 4:08 PM', '', 'Basketball GG7X (Molten).png'),
(365, 'Shuttlecock (dunlop)', 'PRD000365', 'Tube(12pcs)', 3, '1900', '13 August 2026 4:09 PM', '', 'Shuttlecock (dunlop).png'),
(366, 'Arnis stick (rattan) pair', 'PRD000366', 'Pcs', 6, '250', '13 August 2026 4:13 PM', '', 'no_image.png'),
(367, 'Garden Hose 100 ft', NULL, 'Pcs', 1, ' 3850', '20 Aug 2026', '', 'no_image.png'),
(368, 'Gloves', NULL, 'Pcs', 100, ' 399', '20 Aug 2026', '', 'no_image.png'),
(369, 'Boots (1 pair size 7)', NULL, 'Pair', 1, ' 600', '20 Aug 2026', '', 'no_image.png'),
(370, 'Zonrox Sparkle 900ml', NULL, 'Bottles', 2, ' 180', '20 Aug 2026', '', 'no_image.png'),
(371, 'Smoke Detector', NULL, 'Pcs', 5, ' 358', '20 Aug 2026', '', 'Smoke Detector.png'),
(372, 'Computer Set', NULL, 'Set', 1, '49990', '24 August 2026 8:16 AM', 'With Printer (i3/Epson L3210)', 'no_image.png'),
(373, 'Parchment paper', NULL, 'Pack', 2, ' 45', '23 Aug 2026', '', 'no_image.png'),
(374, 'Unisex Rubber Shoes (S32)', NULL, 'Pcs', 1, '400', '23 August 2026 11:28 PM', 'No box, still not used', 'no_image.png');

-- --------------------------------------------------------

--
-- Table structure for table `order_status_history`
--

CREATE TABLE `order_status_history` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `acc_number` varchar(50) NOT NULL,
  `delivery_address` varchar(255) DEFAULT NULL,
  `delivery_number` varchar(255) DEFAULT NULL,
  `product_name` varchar(255) NOT NULL,
  `selling_price` varchar(255) DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'PENDING',
  `pieces` int(11) NOT NULL,
  `unit` varchar(50) NOT NULL,
  `total_amount` decimal(10,2) DEFAULT 0.00,
  `date_time_sold` varchar(50) NOT NULL,
  `delivery_date` varchar(255) DEFAULT NULL,
  `qr_code` text DEFAULT NULL,
  `note` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
--
-- Dumping data for table `order_status_history`
--

INSERT INTO `order_status_history` (`id`, `order_id`, `acc_number`, `delivery_address`, `delivery_number`, `product_name`, `selling_price`, `status`, `pieces`, `unit`, `total_amount`, `date_time_sold`, `delivery_date`, `qr_code`, `note`) VALUES
(1, 97, '', NULL, NULL, 'Legal Coupon', '250', 'PAID', 1, 'Reams', 250.00, 'Tue, 14 Apr 2026 9:31 AM', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/sales_invoice.php?order_id=3', 'In Use'),
(2, 59, '', NULL, NULL, 'Brown Envelop A4', '10', 'PAID', 1, 'Pcs', 10.00, 'Tue, 14 Apr 2026 10:17 AM', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/sales_invoice.php?order_id=5', 'Sell'),
(3, 5, '2368', 'San Vicente, Burgos, Pangasinan', 'DEL-20260415-0002', 'HIK Vision DVR 8-Ch With Hard Drive', '4200', 'PAID', 1, 'Pcs', 4200.00, 'Wed, 15 Apr 2026 11:12 PM', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=DEL-20260415-0002', NULL),
(4, 5, '2368', 'San Vicente, Burgos, Pangasinan', 'DEL-20260415-0002', 'HIK Vision Power Supply', '1590', 'PAID', 1, 'Pcs', 1590.00, 'Wed, 15 Apr 2026 11:12 PM', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=DEL-20260415-0002', NULL),
(5, 5, '2368', 'San Vicente, Burgos, Pangasinan', 'DEL-20260415-0002', 'Dahua IP Camera 2.0mp Wide Angle', '2500', 'PAID', 6, 'Pcs', 15000.00, 'Wed, 15 Apr 2026 11:12 PM', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=DEL-20260415-0002', NULL),
(6, 5, '2368', 'San Vicente, Burgos, Pangasinan', 'DEL-20260415-0002', 'Siamese', '4200', 'PAID', 1, 'Box', 4200.00, 'Wed, 15 Apr 2026 11:12 PM', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=DEL-20260415-0002', NULL),
(7, 6, '2368', 'Barlo, Mabini, Pangasinan', 'DEL-20260416-0001', '60-watts Bosca Flood Light with Panel', '3200', 'PAID', 8, 'set', 25600.00, 'Thu, 16 Apr 2026 10:51 AM', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=DEL-20260416-0001', NULL),
(8, 18, '4291', 'Purok 1, Cabinuangan, Mabini, Pangasinan', 'DEL-20260418-0001', 'Cameras: 8 x 2.0MP Wide Angle', '28900', 'PAID', 1, 'Set', 28900.00, 'Sat, 18 Apr 2026 6:10 PM', 'Mon, 20 April 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=DEL-20260418-0001', NULL),
(9, 21, '', NULL, NULL, 'HBW Stapler', '185', 'PAID', 1, 'Box', 185.00, 'Mon, 20 Apr 2026 11:16 AM', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/sales_invoice.php?order_id=40', 'Sell'),
(10, 19, '2368', 'Eguia Dasol Pangasinan', 'DEL-20260421-0001', 'Battery Energizer AA', '75', 'PAID', 16, 'Pcs', 1200.00, 'Tue, 21 Apr 2026 1:47 PM', 'Thu, 23 April 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=DEL-20260421-0001', NULL),
(11, 19, '2368', 'Eguia Dasol Pangasinan', 'DEL-20260421-0001', 'Tissue', '240', 'PAID', 4, 'Packs', 960.00, 'Tue, 21 Apr 2026 1:47 PM', 'Thu, 23 April 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=DEL-20260421-0001', NULL),
(12, 19, '2368', 'Eguia Dasol Pangasinan', 'DEL-20260421-0001', 'Epson Ink 003 Yellow', '290', 'PAID', 1, 'Bottles', 290.00, 'Tue, 21 Apr 2026 1:47 PM', 'Thu, 23 April 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=DEL-20260421-0001', NULL),
(13, 19, '2368', 'Eguia Dasol Pangasinan', 'DEL-20260421-0001', 'Epson Ink 003 Black', '280', 'PAID', 3, 'Bottles', 840.00, 'Tue, 21 Apr 2026 1:47 PM', 'Thu, 23 April 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=DEL-20260421-0001', NULL),
(14, 19, '2368', 'Eguia Dasol Pangasinan', 'DEL-20260421-0001', 'Brother Ink Blk', '440', 'PAID', 2, 'Pcs', 880.00, 'Tue, 21 Apr 2026 1:47 PM', 'Thu, 23 April 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=DEL-20260421-0001', NULL),
(15, 19, '2368', 'Eguia Dasol Pangasinan', 'DEL-20260421-0001', 'Brother ink Cyan', '390', 'PAID', 1, 'Pcs', 390.00, 'Tue, 21 Apr 2026 1:47 PM', 'Thu, 23 April 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=DEL-20260421-0001', NULL),
(16, 19, '2368', 'Eguia Dasol Pangasinan', 'DEL-20260421-0001', 'Brother Ink Magenta', '390', 'PAID', 1, 'Pcs', 390.00, 'Tue, 21 Apr 2026 1:47 PM', 'Thu, 23 April 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=DEL-20260421-0001', NULL),
(17, 19, '2368', 'Eguia Dasol Pangasinan', 'DEL-20260421-0001', 'Brother ink Yellow', '390', 'PAID', 2, 'Pcs', 780.00, 'Tue, 21 Apr 2026 1:47 PM', 'Thu, 23 April 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=DEL-20260421-0001', NULL),
(18, 19, '2368', 'Eguia Dasol Pangasinan', 'DEL-20260421-0001', '4 layers File Desk Organizer', '404', 'PAID', 3, 'Pcs', 1212.00, 'Tue, 21 Apr 2026 1:47 PM', 'Thu, 23 April 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=DEL-20260421-0001', NULL),
(19, 19, '2368', 'Eguia Dasol Pangasinan', 'DEL-20260421-0001', 'Kraft Folder Legal', '850', 'PAID', 1, 'Packs', 850.00, 'Tue, 21 Apr 2026 1:47 PM', 'Thu, 23 April 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=DEL-20260421-0001', NULL),
(20, 19, '2368', 'Eguia Dasol Pangasinan', 'DEL-20260421-0001', 'A4 Coupon', '230', 'PAID', 15, 'Reams', 3450.00, 'Tue, 21 Apr 2026 1:47 PM', 'Thu, 23 April 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=DEL-20260421-0001', NULL),
(21, 19, '2368', 'Eguia Dasol Pangasinan', 'DEL-20260421-0001', 'Legal Coupon', '240', 'PAID', 15, 'Reams', 3600.00, 'Tue, 21 Apr 2026 1:47 PM', 'Thu, 23 April 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=DEL-20260421-0001', NULL),
(22, 19, '2368', 'Eguia Dasol Pangasinan', 'DEL-20260421-0001', 'Correction Tape', '65', 'PAID', 5, 'Pcs', 325.00, 'Tue, 21 Apr 2026 1:47 PM', 'Thu, 23 April 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=DEL-20260421-0001', NULL),
(23, 20, '8156', 'Anapao, Burgos Pangasinan', 'DEL-20260422-0001', 'Bosca Solar Flood Light with Panel', '5300', 'PAID', 1, 'Pcs', 5300.00, 'Wed, 22 Apr 2026 12:19 PM', 'Thu, 23 April 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=DEL-20260422-0001', NULL),
(59, 21, '4266', 'Barlo Millsite', 'DEL-20260425-0001', 'Cameras: 4 x 2.0MP Wide Angle', '19900', 'PAID', 1, 'Set', 19900.00, 'Sat, 25 Apr 2026 9:07 AM', 'Mon, 27 April 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=DEL-20260425-0001', NULL),
(60, 96, '', NULL, NULL, 'Legal Coupon', '240', 'PAID', 1, 'Reams', 240.00, 'Mon, 4 May 2026 8:22 AM', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/sales_invoice.php?order_id=60', 'Use'),
(61, 22, '8156', 'Eguia Dasol Pangasinan', 'DEL-20260504-0001', 'Expanded Folder Long Yellow', '25', 'PAID', 25, 'Pcs', 625.00, 'Mon, 4 May 2026 4:50 PM', 'Mon, 4 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=DEL-20260504-0001', NULL),
(62, 22, '8156', 'Eguia Dasol Pangasinan', 'DEL-20260504-0001', 'A4 Coupon', '230', 'PAID', 15, 'Reams', 3450.00, 'Mon, 4 May 2026 4:50 PM', 'Mon, 4 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=DEL-20260504-0001', NULL),
(63, 22, '8156', 'Eguia Dasol Pangasinan', 'DEL-20260504-0001', 'Legal Coupon', '240', 'PAID', 15, 'Reams', 3600.00, 'Mon, 4 May 2026 4:50 PM', 'Mon, 4 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=DEL-20260504-0001', NULL),
(64, 22, '8156', 'Eguia Dasol Pangasinan', 'DEL-20260504-0001', 'Kraft Folder Legal', '850', 'PAID', 1, 'Packs', 850.00, 'Mon, 4 May 2026 4:50 PM', 'Mon, 4 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=DEL-20260504-0001', NULL),
(65, 22, '8156', 'Eguia Dasol Pangasinan', 'DEL-20260504-0001', 'A4 Folder White', '800', 'PAID', 1, 'Reams', 800.00, 'Mon, 4 May 2026 4:50 PM', 'Mon, 4 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=DEL-20260504-0001', NULL),
(66, 22, '8156', 'Eguia Dasol Pangasinan', 'DEL-20260504-0001', 'Detergent Powder/kilo', '185', 'PAID', 1, 'Pcs', 185.00, 'Mon, 4 May 2026 4:50 PM', 'Mon, 4 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=DEL-20260504-0001', NULL),
(67, 22, '8156', 'Eguia Dasol Pangasinan', 'DEL-20260504-0001', 'Muriatic Acid 500ml', '169', 'PAID', 1, 'Pcs', 169.00, 'Mon, 4 May 2026 4:50 PM', 'Mon, 4 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=DEL-20260504-0001', NULL),
(68, 22, '8156', 'Eguia Dasol Pangasinan', 'DEL-20260504-0001', 'Gallon Dishwashing liquid', '250', 'PAID', 1, 'Pcs', 250.00, 'Mon, 4 May 2026 4:50 PM', 'Mon, 4 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=DEL-20260504-0001', NULL),
(69, 22, '8156', 'Eguia Dasol Pangasinan', 'DEL-20260504-0001', 'Gallon Alcohol', '600', 'PAID', 1, 'Pcs', 600.00, 'Mon, 4 May 2026 4:50 PM', 'Mon, 4 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=DEL-20260504-0001', NULL),
(70, 22, '8156', 'Eguia Dasol Pangasinan', 'DEL-20260504-0001', 'Broom tinging', '35', 'PAID', 5, 'Pcs', 175.00, 'Mon, 4 May 2026 4:50 PM', 'Mon, 4 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=DEL-20260504-0001', NULL),
(71, 22, '8156', 'Eguia Dasol Pangasinan', 'DEL-20260504-0001', 'Rake/kalykay', '650', 'PAID', 1, 'Pcs', 650.00, 'Mon, 4 May 2026 4:50 PM', 'Mon, 4 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=DEL-20260504-0001', NULL),
(72, 77, '', NULL, NULL, 'Plastic Envelop', '15', 'PAID', 2, 'Pcs', 30.00, 'Thu, 7 May 2026 8:33 AM', NULL, NULL, 'Sell'),
(73, 59, '', NULL, NULL, 'Brown Envelop Long', '10', 'PAID', 3, 'Pcs', 30.00, 'Thu, 7 May 2026 8:33 AM', NULL, NULL, 'Sell'),
(74, 48, '', NULL, NULL, 'Scotch Tape 24mm', '45', 'PAID', 1, 'Rolls', 45.00, 'Thu, 7 May 2026 8:34 AM', NULL, NULL, 'Sell'),
(75, 97, '', NULL, NULL, 'A4 Coupon', '230', 'PAID', 1, 'Reams', 230.00, 'Thu, 7 May 2026 8:35 AM', NULL, NULL, 'Use'),
(76, 34, '', NULL, NULL, 'Matrix Ballpen Black', '10', 'PAID', 1, 'Pcs', 10.00, 'Thu, 7 May 2026 8:40 AM', NULL, NULL, 'Sell'),
(77, 108, '', NULL, NULL, 'Photo Paper 180 gsm', '100', 'PAID', 1, 'Packs', 100.00, 'Thu, 7 May 2026 8:46 AM', NULL, NULL, 'Use'),
(80, 76, '', NULL, NULL, 'Mouse Pad', '50', 'PAID', 1, 'Pcs', 50.00, 'Thu, 7 May 2026 4:14 PM', NULL, NULL, 'Use'),
(81, 3, '', NULL, NULL, 'Junction Box 10cm', '150', 'PAID', 7, 'Pcs', 1050.00, 'Thu, 7 May 2026 5:08 PM', NULL, NULL, 'Sell'),
(82, 10, '', NULL, NULL, 'Epson Ink 664 Black', '280', 'PAID', 1, 'Bottles', 280.00, 'Fri, 8 May 2026 3:54 PM', NULL, NULL, 'Sell'),
(83, 94, '', NULL, NULL, 'Sticker Paper', '100', 'PAID', 1, 'Packs', 100.00, 'Mon, 11 May 2026 8:44 AM', NULL, NULL, 'Sell'),
(84, 77, '', NULL, NULL, 'Plastic Envelop', '15', 'PAID', 1, 'Pcs', 15.00, 'Mon, 11 May 2026 9:44 AM', NULL, NULL, 'Sell'),
(85, 94, '', NULL, NULL, 'Sticker Paper', '100', 'PAID', 1, 'Packs', 100.00, 'Tue, 12 May 2026 9:09 AM', NULL, NULL, 'Use'),
(86, 108, '', NULL, NULL, 'Photo Paper 180 gsm', '100', 'PAID', 1, 'Packs', 100.00, 'Tue, 12 May 2026 10:13 AM', NULL, NULL, 'Use'),
(88, 34, '', NULL, NULL, 'Matrix Ballpen Black', '10', 'PAID', 1, 'Pcs', 10.00, '14 May 2026 9:21 AM', NULL, NULL, 'Sell'),
(89, 97, '', NULL, NULL, 'A4 Coupon', '230', 'PAID', 1, 'Reams', 230.00, '14 May 2026 11:13 AM', NULL, NULL, 'Use'),
(90, 23, '2670', 'PUROK 1', 'VPSGM000001', 'A4 Coupon', '230', 'PAID', 28, 'PCS', 6440.00, '15 May 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000001', NULL),
(91, 23, '2670', 'PUROK 1', 'VPSGM000001', 'Long Folder White', '800', 'PAID', 3, 'PCS', 2400.00, '15 May 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000001', NULL),
(92, 23, '2670', 'PUROK 1', 'VPSGM000001', 'Ballpen Black', '150', 'PAID', 4, 'BOX', 600.00, '15 May 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000001', NULL),
(93, 23, '2670', 'PUROK 1', 'VPSGM000001', 'Ballpen Green', '49', 'PAID', 4, 'BOX', 196.00, '15 May 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000001', NULL),
(94, 23, '2670', 'PUROK 1', 'VPSGM000001', 'Elmer\'s Glue 130g', '65', 'PAID', 12, 'PCS', 780.00, '15 May 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000001', NULL),
(103, 24, '2670', 'PUROK 1', 'VPSGM000002', 'A4 Coupon', '230', 'PAID', 18, 'PCS', 4140.00, '15 May 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000002', NULL),
(104, 24, '2670', 'PUROK 1', 'VPSGM000002', 'Epson Ink 003 Black', '280', 'PAID', 3, 'PCS', 840.00, '15 May 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000002', NULL),
(105, 24, '2670', 'PUROK 1', 'VPSGM000002', 'Epson Ink 003 Cyan', '290', 'PAID', 3, 'PCS', 870.00, '15 May 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000002', NULL),
(106, 24, '2670', 'PUROK 1', 'VPSGM000002', 'Epson Ink 003 Magenta', '290', 'PAID', 3, 'PCS', 870.00, '15 May 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000002', NULL),
(107, 24, '2670', 'PUROK 1', 'VPSGM000002', 'Epson Ink 003 Yellow', '290', 'PAID', 3, 'PCS', 870.00, '15 May 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000002', NULL),
(108, 24, '2670', 'PUROK 1', 'VPSGM000002', 'FlexStick ballpen', '150', 'PAID', 2, 'PCS', 300.00, '15 May 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000002', NULL),
(109, 24, '2670', 'PUROK 1', 'VPSGM000002', 'Elmer\'s Glue 130g', '65', 'PAID', 8, 'PCS', 520.00, '15 May 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000002', NULL),
(110, 24, '2670', 'PUROK 1', 'VPSGM000002', 'Pentel Pen Black', '49', 'PAID', 8, 'PCS', 392.00, '15 May 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000002', NULL),
(111, 24, '2670', 'PUROK 1', 'VPSGM000002', 'Long Folder White', '800', 'PAID', 1, 'PCS', 800.00, '15 May 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000002', NULL),
(112, 25, '2670', 'PUROK 1', 'VPSGM000003', 'Detergent Powder/kilo', '185', 'PAID', 20, 'Pcs', 3700.00, '15 May 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000003', NULL),
(113, 25, '2670', 'PUROK 1', 'VPSGM000003', 'Tuff Toilet Bowl 1000 ml', '360', 'PAID', 20, 'Pc', 7200.00, '15 May 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000003', NULL),
(114, 25, '2670', 'PUROK 1', 'VPSGM000003', 'Soft broom (walis tambo)', '250', 'PAID', 18, 'Pcs', 4500.00, '15 May 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000003', NULL),
(115, 25, '2670', 'PUROK 1', 'VPSGM000003', 'Dust pan', '65', 'PAID', 18, 'Pcs', 1170.00, '15 May 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000003', NULL),
(116, 25, '2670', 'PUROK 1', 'VPSGM000003', 'Trash Bag', '120', 'PAID', 5, 'Pcs', 600.00, '15 May 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000003', NULL),
(153, 30, '0022', 'Don Juan Bernal Sr. Elementary School', 'VPSGM000008', 'A4 Coupon', '230', 'PAID', 40, 'PCS', 9200.00, '16 May 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000008', NULL),
(154, 30, '0022', 'Don Juan Bernal Sr. Elementary School', 'VPSGM000008', 'File Folder A4 white', '10', 'PAID', 100, 'PCS', 1000.00, '16 May 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000008', NULL),
(155, 30, '0022', 'Don Juan Bernal Sr. Elementary School', 'VPSGM000008', 'Legal Coupon', '240', 'PAID', 3, 'PCS', 720.00, '16 May 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000008', NULL),
(156, 30, '0022', 'Don Juan Bernal Sr. Elementary School', 'VPSGM000008', 'File Folder long white', '10', 'PAID', 70, 'PCS', 700.00, '16 May 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000008', NULL),
(157, 30, '0022', 'Don Juan Bernal Sr. Elementary School', 'VPSGM000008', 'Retractable Ballpen Black', '35', 'PAID', 15, 'PCS', 525.00, '16 May 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000008', NULL),
(158, 30, '0022', 'Don Juan Bernal Sr. Elementary School', 'VPSGM000008', 'Retractable Ballpen Red', '35', 'PAID', 15, 'PCS', 525.00, '16 May 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000008', NULL),
(159, 30, '0022', 'Don Juan Bernal Sr. Elementary School', 'VPSGM000008', 'Gallon Alcohol', '600', 'PAID', 3, 'PCS', 1800.00, '16 May 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000008', NULL),
(160, 30, '0022', 'Don Juan Bernal Sr. Elementary School', 'VPSGM000008', 'Walis Tambo', '250', 'PAID', 13, 'PCS', 3250.00, '16 May 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000008', NULL),
(161, 30, '0022', 'Don Juan Bernal Sr. Elementary School', 'VPSGM000008', 'Correction Tape', '65', 'PAID', 13, 'PCS', 845.00, '16 May 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000008', NULL),
(162, 30, '0022', 'Don Juan Bernal Sr. Elementary School', 'VPSGM000008', 'Epson Ink 003 Black', '280', 'PAID', 10, 'PCS', 2800.00, '16 May 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000008', NULL),
(163, 30, '0022', 'Don Juan Bernal Sr. Elementary School', 'VPSGM000008', 'Epson Ink 003 Cyan', '290', 'PAID', 10, 'PCS', 2900.00, '16 May 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000008', NULL),
(164, 30, '0022', 'Don Juan Bernal Sr. Elementary School', 'VPSGM000008', 'Epson Ink 003 Magenta', '290', 'PAID', 10, 'PCS', 2900.00, '16 May 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000008', NULL),
(165, 30, '0022', 'Don Juan Bernal Sr. Elementary School', 'VPSGM000008', 'Epson Ink 003 Yellow', '290', 'PAID', 10, 'PCS', 2900.00, '16 May 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000008', NULL),
(166, 31, '0022', 'Don Matias Elementary School', 'VPSGM000009', 'Expanded Folder Blue', '28', 'PAID', 40, 'PCS', 1120.00, '16 May 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000009', NULL),
(167, 31, '0022', 'Don Matias Elementary School', 'VPSGM000009', 'A4 Coupon', '230', 'PAID', 10, 'PCS', 2300.00, '16 May 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000009', NULL),
(168, 31, '0022', 'Don Matias Elementary School', 'VPSGM000009', 'Epson Ink 003 Black', '280', 'PAID', 5, 'PCS', 1400.00, '16 May 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000009', NULL),
(169, 31, '0022', 'Don Matias Elementary School', 'VPSGM000009', 'Detergent Powder/kilo', '185', 'PAID', 5, 'PCS', 925.00, '16 May 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000009', NULL),
(170, 31, '0022', 'Don Matias Elementary School', 'VPSGM000009', 'Joy Diswashing Liquid', '95', 'PAID', 1, 'PCS', 95.00, '16 May 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000009', NULL),
(171, 31, '0022', 'Don Matias Elementary School', 'VPSGM000009', 'Bottled Hand Soap', '180', 'PAID', 2, 'PCS', 360.00, '16 May 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000009', NULL),
(172, 31, '0022', 'Don Matias Elementary School', 'VPSGM000009', 'Tuff TBC 1 Liters', '315', 'PAID', 3, 'PCS', 945.00, '16 May 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000009', NULL),
(173, 32, '8156', 'Don Antonio Burgos Pangasinan', 'VPSGM000010', 'Latex Gloss Paint 16 Liters', '3700', 'PAID', 1, 'Gallons', 3700.00, '18 May 2026', 'Mon, 18 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000010', NULL),
(174, 32, '8156', 'Don Antonio Burgos Pangasinan', 'VPSGM000010', 'Thalo green paint', '1350', 'PAID', 1, 'Gallon', 1350.00, '18 May 2026', 'Mon, 18 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000010', NULL),
(175, 32, '8156', 'Don Antonio Burgos Pangasinan', 'VPSGM000010', 'Lemon yellow paint', '1350', 'PAID', 2, 'Gallon', 2700.00, '18 May 2026', 'Mon, 18 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000010', NULL),
(176, 32, '8156', 'Don Antonio Burgos Pangasinan', 'VPSGM000010', 'White paint', '1350', 'PAID', 1, 'Gallon', 1350.00, '18 May 2026', 'Mon, 18 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000010', NULL),
(177, 32, '8156', 'Don Antonio Burgos Pangasinan', 'VPSGM000010', 'Raw Sienna', '390', 'PAID', 1, 'Liter', 390.00, '18 May 2026', 'Mon, 18 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000010', NULL),
(178, 32, '8156', 'Don Antonio Burgos Pangasinan', 'VPSGM000010', 'Paint Brush', '105', 'PAID', 3, 'Pcs', 315.00, '18 May 2026', 'Mon, 18 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000010', NULL),
(179, 32, '8156', 'Don Antonio Burgos Pangasinan', 'VPSGM000010', 'Roller Brush', '95', 'PAID', 3, 'Pcs', 285.00, '18 May 2026', 'Mon, 18 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000010', NULL),
(180, 32, '8156', 'Don Antonio Burgos Pangasinan', 'VPSGM000010', 'Thinner', '150', 'PAID', 5, 'Bottle', 750.00, '18 May 2026', 'Mon, 18 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000010', NULL),
(181, 32, '8156', 'Don Antonio Burgos Pangasinan', 'VPSGM000010', 'Brown paint', '1350', 'PAID', 1, 'Gallon', 1350.00, '18 May 2026', 'Mon, 18 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000010', NULL),
(185, 33, '3173', 'Viga, Dasol,Pangasinan', 'VPSGM000011', 'Paper Clip 50mm', '65', 'PAID', 2, 'PCS', 130.00, '18 May 2026', 'Tue, 19 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000011', NULL),
(186, 33, '3173', 'Viga, Dasol,Pangasinan', 'VPSGM000011', 'A4 Folder White', '800', 'PAID', 1, 'PCS', 800.00, '18 May 2026', 'Tue, 19 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000011', NULL),
(189, 33, '3173', 'Viga, Dasol,Pangasinan', 'VPSGM000011', 'HBW Stapler', '185', 'PAID', 1, 'PCS', 185.00, '18 May 2026', 'Tue, 19 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000011', NULL),
(190, 86, '', NULL, NULL, 'File Folder long white', '10', 'PAID', 2, 'Pcs', 20.00, '19 May 2026 8:15 AM', NULL, NULL, 'Sell'),
(191, 23, '4819', 'PUROK 1', 'VPSGM000001', 'Pilot Permanent Marker', '49', 'PAID', 12, 'PCS', 588.00, 'Tue, 19 May 2026 5:08 AM', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000001', NULL),
(192, 23, '4819', 'PUROK 1', 'VPSGM000001', 'Epson Ink 004', '1150', 'PAID', 3, 'PCS', 3450.00, 'Tue, 19 May 2026 5:22 AM', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000001', NULL),
(193, 23, '4819', 'PUROK 1', 'VPSGM000001', 'Epson Ink 003 (Set)', '1150', 'PAID', 4, 'PCS', 4600.00, 'Tue, 19 May 2026 5:22 AM', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000001', NULL),
(194, 33, '4819', 'Viga, Dasol,Pangasinan', 'VPSGM000011', 'Epson Ink 003 set', '1150', 'PAID', 10, 'PCS', 11500.00, 'Wed, 20 May 2026 6:04 AM', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000011', NULL),
(195, 33, '4819', 'Viga, Dasol,Pangasinan', 'VPSGM000011', 'A4 Coupon', '230', 'PAID', 50, 'PCS', 11500.00, 'Wed, 20 May 2026 6:06 AM', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000011', NULL),
(196, 33, '4819', 'Viga, Dasol,Pangasinan', 'VPSGM000011', 'Stappler # 26/6', '65', 'PAID', 1, 'PCS', 65.00, 'Wed, 20 May 2026 6:08 AM', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000011', NULL),
(197, 34, '0022', 'EGUIA, DASOL PANGASINAN', 'VPSGM000012', 'PVC Wall Panel', '1200', 'PAID', 11, 'Pcs', 13200.00, '29 May 2026', 'Sun, 31 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000012', NULL),
(198, 34, '0022', 'EGUIA, DASOL PANGASINAN', 'VPSGM000012', 'UV Ceramic Panel', '3500', 'PAID', 1, 'Pcs', 3500.00, '29 May 2026', 'Sun, 31 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000012', NULL),
(199, 34, '0022', 'EGUIA, DASOL PANGASINAN', 'VPSGM000012', 'LED Panel Light 12+4 watts', '1130', 'PAID', 1, 'Pcs', 1130.00, '29 May 2026', 'Sun, 31 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000012', NULL),
(200, 34, '0022', 'EGUIA, DASOL PANGASINAN', 'VPSGM000012', 'LED Panel Light 6+3 watts', '720', 'PAID', 4, 'Pcs', 2880.00, '29 May 2026', 'Sun, 31 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000012', NULL),
(201, 34, '0022', 'EGUIA, DASOL PANGASINAN', 'VPSGM000012', 'LED Panel Light 3+3 watts', '650', 'PAID', 4, 'Pcs', 2600.00, '29 May 2026', 'Sun, 31 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000012', NULL),
(202, 34, '0022', 'EGUIA, DASOL PANGASINAN', 'VPSGM000012', 'Boysen Paint', '1600', 'PAID', 1, 'Pcs', 1600.00, '29 May 2026', 'Sun, 31 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000012', NULL),
(203, 34, '0022', 'EGUIA, DASOL PANGASINAN', 'VPSGM000012', 'Wall Putty', '950', 'PAID', 1, 'Pcs', 950.00, '29 May 2026', 'Sun, 31 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000012', NULL),
(204, 34, '0022', 'EGUIA, DASOL PANGASINAN', 'VPSGM000012', 'Pallets', '95', 'PAID', 1, 'Pcs', 95.00, '29 May 2026', 'Sun, 31 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000012', NULL),
(205, 34, '0022', 'EGUIA, DASOL PANGASINAN', 'VPSGM000012', 'Paint Brush', '95', 'PAID', 1, 'Pcs', 95.00, '29 May 2026', 'Sun, 31 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000012', NULL),
(206, 34, '0022', 'EGUIA, DASOL PANGASINAN', 'VPSGM000012', 'Roller Paint Brush', '165', 'PAID', 1, 'Pcs', 165.00, '29 May 2026', 'Sun, 31 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000012', NULL),
(207, 34, '0022', 'EGUIA, DASOL PANGASINAN', 'VPSGM000012', 'Screws', '460', 'PAID', 1, 'Pcs', 460.00, '29 May 2026', 'Sun, 31 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000012', NULL),
(208, 34, '0022', 'EGUIA, DASOL PANGASINAN', 'VPSGM000012', 'Tox screw', '460', 'PAID', 1, 'Pcs', 460.00, '29 May 2026', 'Sun, 31 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000012', NULL),
(209, 34, '0022', 'EGUIA, DASOL PANGASINAN', 'VPSGM000012', 'Fine sandpaper', '250', 'PAID', 1, 'Pcs', 250.00, '29 May 2026', 'Sun, 31 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000012', NULL),
(210, 34, '0022', 'EGUIA, DASOL PANGASINAN', 'VPSGM000012', 'Construction Adhesive', '390', 'PAID', 1, 'Pcs', 390.00, '29 May 2026', 'Sun, 31 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000012', NULL),
(213, 35, '0022', 'SAPA PEQUEÑA, BURGOS PANGASINAN', 'VPSGM000013', 'Plate with partition', '73', 'PAID', 20, 'Pcs', 1460.00, '30 May 2026', 'Mon, 1 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000013', NULL),
(214, 35, '0022', 'SAPA PEQUEÑA, BURGOS PANGASINAN', 'VPSGM000013', 'Bowl', '50', 'PAID', 10, 'Pcs', 500.00, '30 May 2026', 'Mon, 1 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000013', NULL),
(215, 35, '0022', 'SAPA PEQUEÑA, BURGOS PANGASINAN', 'VPSGM000013', 'Platito', '48', 'PAID', 10, 'Pcs', 480.00, '30 May 2026', 'Mon, 1 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000013', NULL),
(216, 35, '0022', 'SAPA PEQUEÑA, BURGOS PANGASINAN', 'VPSGM000013', 'Spoon', '121.25', 'PAID', 2, 'Pcs', 242.50, '30 May 2026', 'Mon, 1 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000013', NULL),
(217, 35, '0022', 'SAPA PEQUEÑA, BURGOS PANGASINAN', 'VPSGM000013', 'Fork', '121.25', 'PAID', 2, 'Pcs', 242.50, '30 May 2026', 'Mon, 1 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000013', NULL),
(218, 36, '0022', 'SAPA PEQUEÑA, BURGOS PANGASINAN', 'VPSGM000014', 'Detergent Powder/kilo', ' 180', 'PAID', 6, 'PCS', 1080.00, '30 May 2026', 'Mon, 1 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000014', NULL),
(219, 36, '0022', 'SAPA PEQUEÑA, BURGOS PANGASINAN', 'VPSGM000014', 'Floor mop with spinner', ' 450', 'PAID', 8, 'PCS', 3600.00, '30 May 2026', 'Mon, 1 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000014', NULL),
(220, 36, '0022', 'SAPA PEQUEÑA, BURGOS PANGASINAN', 'VPSGM000014', 'Trash bin (240L)', ' 3000', 'PAID', 2, 'PCS', 6000.00, '30 May 2026', 'Mon, 1 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000014', NULL),
(221, 36, '0022', 'SAPA PEQUEÑA, BURGOS PANGASINAN', 'VPSGM000014', 'Rechargeable Sprayer', ' 2419.58', 'PAID', 1, 'PCS', 2419.58, '30 May 2026', 'Mon, 1 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000014', NULL),
(222, 36, '0022', 'SAPA PEQUEÑA, BURGOS PANGASINAN', 'VPSGM000014', 'Rake', ' 750', 'PAID', 2, 'PCS', 1500.00, '30 May 2026', 'Mon, 1 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000014', NULL),
(223, 36, '0022', 'SAPA PEQUEÑA, BURGOS PANGASINAN', 'VPSGM000014', 'Bareta (digging)', ' 650', 'PAID', 2, 'PCS', 1300.00, '30 May 2026', 'Mon, 1 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000014', NULL),
(224, 36, '0022', 'SAPA PEQUEÑA, BURGOS PANGASINAN', 'VPSGM000014', 'Spade Pork', ' 350', 'PAID', 2, 'PCS', 700.00, '30 May 2026', 'Mon, 1 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000014', NULL),
(225, 36, '0022', 'SAPA PEQUEÑA, BURGOS PANGASINAN', 'VPSGM000014', 'Shovel', ' 350', 'PAID', 2, 'PCS', 700.00, '30 May 2026', 'Mon, 1 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000014', NULL),
(226, 36, '0022', 'SAPA PEQUEÑA, BURGOS PANGASINAN', 'VPSGM000014', 'Fabric softener', ' 380', 'PAID', 5, 'PCS', 1900.00, '30 May 2026', 'Mon, 1 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000014', NULL),
(227, 36, '0022', 'SAPA PEQUEÑA, BURGOS PANGASINAN', 'VPSGM000014', 'Tuff TBC', ' 380', 'PAID', 10, 'PCS', 3800.00, '30 May 2026', 'Mon, 1 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000014', NULL),
(228, 36, '0022', 'SAPA PEQUEÑA, BURGOS PANGASINAN', 'VPSGM000014', 'Hand saw', ' 350', 'PAID', 2, 'PCS', 700.00, '30 May 2026', 'Mon, 1 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000014', NULL),
(229, 36, '0022', 'SAPA PEQUEÑA, BURGOS PANGASINAN', 'VPSGM000014', 'Check Soap', ' 65', 'PAID', 16, 'PCS', 1040.00, '30 May 2026', 'Mon, 1 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000014', NULL),
(230, 97, '', NULL, NULL, 'A4 Coupon', '230', 'PAID', 1, 'Reams', 230.00, '1 Jun 2026 9:12 AM', NULL, NULL, 'Use'),
(231, 97, '', NULL, NULL, 'A4 Coupon', '230', 'PAID', 5, 'Reams', 1150.00, '1 Jun 2026 3:44 PM', NULL, NULL, 'Sell'),
(232, 97, '', NULL, NULL, 'A4 Coupon', '230', 'PAID', 6, 'Reams', 1380.00, '2 Jun 2026 8:17 AM', NULL, NULL, 'Sell'),
(233, 37, '0022', 'Tambobong National High School', 'VPSGM000015', 'Walis Tambo', '250', 'PAID', 20, 'Pcs', 5000.00, '2 Jun 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000015', NULL),
(234, 37, '0022', 'Tambobong National High School', 'VPSGM000015', 'Dust pan', '65', 'PAID', 20, 'Pcs', 1300.00, '2 Jun 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000015', NULL),
(235, 37, '0022', 'Tambobong National High School', 'VPSGM000015', 'Disinfectant Spray', '480', 'PAID', 5, 'Pcs', 2400.00, '2 Jun 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000015', NULL),
(236, 37, '0022', 'Tambobong National High School', 'VPSGM000015', 'Alcohol', '600', 'PAID', 5, 'Gallon', 3000.00, '2 Jun 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000015', NULL),
(237, 37, '0022', 'Tambobong National High School', 'VPSGM000015', 'Toilet Tissue', '240', 'PAID', 5, 'Packs', 1200.00, '2 Jun 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000015', NULL),
(238, 37, '0022', 'Tambobong National High School', 'VPSGM000015', 'Tuff, toilet bowl cleaner', '360', 'PAID', 24, 'Pcs', 8640.00, '2 Jun 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000015', NULL),
(239, 37, '0022', 'Tambobong National High School', 'VPSGM000015', 'Muriatic Acid', '180', 'PAID', 5, 'Bottles', 900.00, '2 Jun 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000015', NULL),
(240, 37, '0022', 'Tambobong National High School', 'VPSGM000015', 'Bluwave, detergent poweder', '180', 'PAID', 24, 'Packs', 4320.00, '2 Jun 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000015', NULL),
(241, 37, '0022', 'Tambobong National High School', 'VPSGM000015', 'Varnish', '135', 'PAID', 5, 'Bottles', 675.00, '2 Jun 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000015', NULL),
(242, 38, '0022', 'Tambobong National High School', 'VPSGM000016', 'Latex White Paint', '3700', 'PAID', 3, 'Pail', 11100.00, '2 Jun 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000016', NULL),
(243, 38, '0022', 'Tambobong National High School', 'VPSGM000016', 'Raw Sienna Paint', '250', 'PAID', 4, 'Liter', 1000.00, '2 Jun 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000016', NULL),
(244, 38, '0022', 'Tambobong National High School', 'VPSGM000016', 'Baguio Green Paint', '1350', 'PAID', 6, 'Gal', 8100.00, '2 Jun 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000016', NULL),
(245, 38, '0022', 'Tambobong National High School', 'VPSGM000016', 'Thalo green paint', '1350', 'PAID', 1, 'Gallon', 1350.00, '2 Jun 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000016', NULL),
(246, 38, '0022', 'Tambobong National High School', 'VPSGM000016', 'Lemon yellow paint', '1350', 'PAID', 2, 'Gallon', 2700.00, '2 Jun 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000016', NULL),
(247, 38, '0022', 'Tambobong National High School', 'VPSGM000016', 'White paint', '1350', 'PAID', 1, 'Gallon', 1350.00, '2 Jun 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000016', NULL),
(248, 38, '0022', 'Tambobong National High School', 'VPSGM000016', 'Paint Roller', '150', 'PAID', 4, 'Pcs', 600.00, '2 Jun 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000016', NULL),
(249, 38, '0022', 'Tambobong National High School', 'VPSGM000016', 'Paint Tray', '140', 'PAID', 2, 'Pcs', 280.00, '2 Jun 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000016', NULL),
(250, 38, '0022', 'Tambobong National High School', 'VPSGM000016', 'Paint Brush 3', '130', 'PAID', 2, 'Pcs', 260.00, '2 Jun 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000016', NULL),
(251, 38, '0022', 'Tambobong National High School', 'VPSGM000016', 'Paint Brush 1', '95', 'PAID', 4, 'Pcs', 380.00, '2 Jun 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000016', NULL),
(252, 38, '0022', 'Tambobong National High School', 'VPSGM000016', 'Paint Brush 5', '180', 'PAID', 4, 'Pcs', 720.00, '2 Jun 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000016', NULL),
(253, 39, '0022', 'Tambobong National High School', 'VPSGM000017', 'Smart TV, 45\"', '18000', 'PAID', 1, 'Unit', 18000.00, '2 Jun 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000017', NULL),
(254, 39, '0022', 'Tambobong National High School', 'VPSGM000017', 'Epson L3210 Printer', '12000', 'PAID', 1, 'Unit', 12000.00, '2 Jun 2026', 'Wed, 20 May 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000017', NULL),
(255, 40, '0022', 'EGUIA, DASOL PANGASINAN', 'VPSGM000018', 'Board with Frame (4x8)', '6500', 'PAID', 2, 'Pcs', 13000.00, '5 Jun 2026', 'Thu, 4 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000018', NULL),
(256, 40, '0022', 'EGUIA, DASOL PANGASINAN', 'VPSGM000018', 'Tubular (1x2)', '650', 'PAID', 4, 'Pcs', 2600.00, '5 Jun 2026', 'Thu, 4 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000018', NULL),
(257, 40, '0022', 'EGUIA, DASOL PANGASINAN', 'VPSGM000018', 'Welding Rod', '25', 'PAID', 10, 'Pcs', 250.00, '5 Jun 2026', 'Thu, 4 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000018', NULL),
(258, 40, '0022', 'EGUIA, DASOL PANGASINAN', 'VPSGM000018', 'Sliding Rollers', '350', 'PAID', 2, 'Pcs', 700.00, '5 Jun 2026', 'Thu, 4 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000018', NULL),
(259, 41, '0022', 'OSMEÑA, DASOL PANGASINAN', 'VPSGM000019', 'Hand Drill with Sander', '2400.38', 'PAID', 1, 'Set', 2400.38, '5 Jun 2026', 'Mon, 8 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000019', NULL),
(260, 41, '0022', 'OSMEÑA, DASOL PANGASINAN', 'VPSGM000019', 'Tools', '350', 'PAID', 1, 'Set', 350.00, '5 Jun 2026', 'Mon, 8 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000019', NULL),
(261, 41, '0022', 'OSMEÑA, DASOL PANGASINAN', 'VPSGM000019', 'Grass cutter Rechargeable', '3618', 'PAID', 1, 'Pcs', 3618.00, '5 Jun 2026', 'Mon, 8 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000019', NULL),
(262, 41, '0022', 'OSMEÑA, DASOL PANGASINAN', 'VPSGM000019', 'Wheel Barrow', '4500', 'PAID', 1, 'Pcs', 4500.00, '5 Jun 2026', 'Mon, 8 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000019', NULL),
(263, 41, '0022', 'OSMEÑA, DASOL PANGASINAN', 'VPSGM000019', 'Shovel', '350', 'PAID', 2, 'Pcs', 700.00, '5 Jun 2026', 'Mon, 8 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000019', NULL),
(264, 41, '0022', 'OSMEÑA, DASOL PANGASINAN', 'VPSGM000019', 'Grass cutter Scissor', '590', 'PAID', 2, 'Pcs', 1180.00, '5 Jun 2026', 'Mon, 8 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000019', NULL),
(265, 41, '0022', 'OSMEÑA, DASOL PANGASINAN', 'VPSGM000019', 'Bareta/Digging 3ft', '650', 'PAID', 1, 'Pcs', 650.00, '5 Jun 2026', 'Mon, 8 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000019', NULL),
(266, 41, '0022', 'OSMEÑA, DASOL PANGASINAN', 'VPSGM000019', 'Bareta/Digging 4ft', '750', 'PAID', 1, 'Pcs', 750.00, '5 Jun 2026', 'Mon, 8 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000019', NULL),
(267, 41, '0022', 'OSMEÑA, DASOL PANGASINAN', 'VPSGM000019', 'Crowbar 45 cm', '400', 'PAID', 1, 'Pcs', 400.00, '5 Jun 2026', 'Mon, 8 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000019', NULL),
(268, 41, '0022', 'OSMEÑA, DASOL PANGASINAN', 'VPSGM000019', 'CrowbAR 70 CM', '500', 'PAID', 1, 'Pcs', 500.00, '5 Jun 2026', 'Mon, 8 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000019', NULL),
(269, 41, '0022', 'OSMEÑA, DASOL PANGASINAN', 'VPSGM000019', 'Itak straight', '660', 'PAID', 3, 'Pcs', 1980.00, '5 Jun 2026', 'Mon, 8 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000019', NULL),
(270, 41, '0022', 'OSMEÑA, DASOL PANGASINAN', 'VPSGM000019', 'Itak kawit', '660', 'PAID', 2, 'Pcs', 1320.00, '5 Jun 2026', 'Mon, 8 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000019', NULL),
(271, 41, '0022', 'OSMEÑA, DASOL PANGASINAN', 'VPSGM000019', 'Trash bin (240L)', '3000', 'PAID', 3, 'Pcs', 9000.00, '5 Jun 2026', 'Mon, 8 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000019', NULL),
(272, 41, '0022', 'OSMEÑA, DASOL PANGASINAN', 'VPSGM000019', 'Double Sided step Ladder- 8 steps', '3200', 'PAID', 1, 'Pcs', 3200.00, '5 Jun 2026', 'Mon, 8 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000019', NULL),
(273, 41, '0022', 'OSMEÑA, DASOL PANGASINAN', 'VPSGM000019', 'Handsaw', '350', 'PAID', 1, 'Pcs', 350.00, '5 Jun 2026', 'Mon, 8 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000019', NULL),
(274, 41, '0022', 'OSMEÑA, DASOL PANGASINAN', 'VPSGM000019', 'Maso with Handle', '300', 'PAID', 1, 'Pcs', 300.00, '5 Jun 2026', 'Mon, 8 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000019', NULL),
(275, 41, '0022', 'OSMEÑA, DASOL PANGASINAN', 'VPSGM000019', 'Hammer', '300', 'PAID', 2, 'Pcs', 600.00, '5 Jun 2026', 'Mon, 8 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000019', NULL),
(276, 41, '0022', 'OSMEÑA, DASOL PANGASINAN', 'VPSGM000019', 'Yellow Nylon Rope 14mm', '800', 'PAID', 1, 'ROLL', 800.00, '5 Jun 2026', 'Mon, 8 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000019', NULL),
(277, 41, '0022', 'OSMEÑA, DASOL PANGASINAN', 'VPSGM000019', 'Nylon String', '650', 'PAID', 1, 'kg', 650.00, '5 Jun 2026', 'Mon, 8 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000019', NULL),
(278, 41, '0022', 'OSMEÑA, DASOL PANGASINAN', 'VPSGM000019', 'Rake', '750', 'PAID', 2, 'Pcs', 1500.00, '5 Jun 2026', 'Mon, 8 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000019', NULL),
(279, 41, '0022', 'OSMEÑA, DASOL PANGASINAN', 'VPSGM000019', 'Walis tingting', '45', 'PAID', 20, 'Pcs', 900.00, '5 Jun 2026', 'Mon, 8 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000019', NULL),
(280, 41, '0022', 'OSMEÑA, DASOL PANGASINAN', 'VPSGM000019', 'Walis Pang-agiw', '180', 'PAID', 2, 'Pcs', 360.00, '5 Jun 2026', 'Mon, 8 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000019', NULL),
(281, 41, '0022', 'OSMEÑA, DASOL PANGASINAN', 'VPSGM000019', 'Hook Screw 1\"', '1.6', 'PAID', 100, 'Packs', 160.00, '5 Jun 2026', 'Mon, 8 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000019', NULL),
(282, 41, '0022', 'OSMEÑA, DASOL PANGASINAN', 'VPSGM000019', 'Rust Remover', '280.5', 'PAID', 2, 'Bottles', 561.00, '5 Jun 2026', 'Mon, 8 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000019', NULL),
(283, 41, '0022', 'OSMEÑA, DASOL PANGASINAN', 'VPSGM000019', 'Toilet bowl and urinal cleaner (Tuff)', '380', 'PAID', 1, 'Bottle', 380.00, '5 Jun 2026', 'Mon, 8 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000019', NULL),
(284, 42, '0022', 'DON MATIAS, BURGOS PANGASINAN', 'VPSGM000020', 'Walis tingting', '50', 'PENDING', 35, 'Pcs', 1750.00, '5 Jun 2026', 'Wed, 10 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000020', NULL),
(285, 42, '0022', 'DON MATIAS, BURGOS PANGASINAN', 'VPSGM000020', 'Rake', '650', 'PENDING', 2, 'Pcs', 1300.00, '5 Jun 2026', 'Wed, 10 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000020', NULL);
INSERT INTO `order_status_history` (`id`, `order_id`, `acc_number`, `delivery_address`, `delivery_number`, `product_name`, `selling_price`, `status`, `pieces`, `unit`, `total_amount`, `date_time_sold`, `delivery_date`, `qr_code`, `note`) VALUES
(286, 42, '0022', 'DON MATIAS, BURGOS PANGASINAN', 'VPSGM000020', 'Walis Tambo', '250', 'PENDING', 10, 'Pcs', 2500.00, '5 Jun 2026', 'Wed, 10 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000020', NULL),
(287, 42, '0022', 'DON MATIAS, BURGOS PANGASINAN', 'VPSGM000020', 'Dust pan', '95', 'PENDING', 10, 'Pcs', 950.00, '5 Jun 2026', 'Wed, 10 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000020', NULL),
(288, 42, '0022', 'DON MATIAS, BURGOS PANGASINAN', 'VPSGM000020', 'Tuff toilet cleaner', '380', 'PENDING', 5, 'Bottles', 1900.00, '5 Jun 2026', 'Wed, 10 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000020', NULL),
(289, 42, '0022', 'DON MATIAS, BURGOS PANGASINAN', 'VPSGM000020', 'Detergent Powder/kilo', '180', 'PENDING', 10, 'Pcs', 1800.00, '5 Jun 2026', 'Wed, 10 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000020', NULL),
(290, 42, '0022', 'DON MATIAS, BURGOS PANGASINAN', 'VPSGM000020', 'Trolley trash bin', '1500', 'PENDING', 5, 'Pcs', 7500.00, '5 Jun 2026', 'Wed, 10 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000020', NULL),
(291, 42, '0022', 'DON MATIAS, BURGOS PANGASINAN', 'VPSGM000020', 'Bolo', '350', 'PENDING', 2, 'Pcs', 700.00, '5 Jun 2026', 'Wed, 10 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000020', NULL),
(292, 42, '0022', 'DON MATIAS, BURGOS PANGASINAN', 'VPSGM000020', 'Pail orocan assorted color', '280', 'PENDING', 5, 'Pcs', 1400.00, '5 Jun 2026', 'Wed, 10 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000020', NULL),
(293, 42, '0022', 'DON MATIAS, BURGOS PANGASINAN', 'VPSGM000020', 'Mop with spinner', '450', 'PENDING', 2, 'Pcs', 900.00, '5 Jun 2026', 'Wed, 10 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000020', NULL),
(294, 42, '0022', 'DON MATIAS, BURGOS PANGASINAN', 'VPSGM000020', 'Gallon Dishwashing liquid', '250', 'PENDING', 1, 'Gallon', 250.00, '5 Jun 2026', 'Wed, 10 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000020', NULL),
(295, 42, '0022', 'DON MATIAS, BURGOS PANGASINAN', 'VPSGM000020', 'Joy Diswashing Liquid', '94.79', 'PENDING', 2, 'Pcs', 189.58, '5 Jun 2026', 'Wed, 10 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000020', NULL),
(296, 42, '0022', 'DON MATIAS, BURGOS PANGASINAN', 'VPSGM000020', 'Bottled Hand Soap', '180', 'PENDING', 10, 'Bottle', 1800.00, '5 Jun 2026', 'Wed, 10 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000020', NULL),
(297, 42, '0022', 'DON MATIAS, BURGOS PANGASINAN', 'VPSGM000020', 'Trolley cart', '1800', 'PENDING', 1, 'Pc', 1800.00, '5 Jun 2026', 'Wed, 10 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000020', NULL),
(298, 43, '0022', 'ILIO-IILO, BURGOS, PANGASINAN', 'VPSGM000021', 'Cameras: 8 x 2.0MP Wide Angle', '28900', 'PAID', 1, 'Set', 28900.00, '5 Jun 2026', 'Mon, 8 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000021', NULL),
(299, 44, '0022', 'ILIO-ILIO, BURGOS PANGASINAN', 'VPSGM000022', 'SIAMESE CABLE 100 METERS', '2500', 'PAID', 1, 'ROLL', 2500.00, '5 Jun 2026', 'Mon, 8 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000022', NULL),
(300, 44, '0022', 'ILIO-ILIO, BURGOS PANGASINAN', 'VPSGM000022', 'HIK Vision Power Supply', '1590', 'PAID', 1, 'Pcs', 1590.00, '5 Jun 2026', 'Mon, 8 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000022', NULL),
(301, 45, '0022', 'TAMBOBONG, DASOL, PANGASINAN', 'VPSGM000023', 'Outlet 3 Gang', '247', 'PAID', 1, 'set', 247.00, '6 Jun 2026', 'Mon, 8 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000023', NULL),
(302, 45, '0022', 'TAMBOBONG, DASOL, PANGASINAN', 'VPSGM000023', 'Outlet 2 Gang', '192.4', 'PAID', 1, 'Pcs', 192.40, '6 Jun 2026', 'Mon, 8 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000023', NULL),
(303, 45, '0022', 'TAMBOBONG, DASOL, PANGASINAN', 'VPSGM000023', 'Switch 2 Gang', '211.9', 'PAID', 1, 'set', 211.90, '6 Jun 2026', 'Mon, 8 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000023', NULL),
(304, 45, '0022', 'TAMBOBONG, DASOL, PANGASINAN', 'VPSGM000023', 'Switch 1 Gang', '169', 'PAID', 4, 'sets', 676.00, '6 Jun 2026', 'Mon, 8 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000023', NULL),
(305, 45, '0022', 'TAMBOBONG, DASOL, PANGASINAN', 'VPSGM000023', 'Switch 3 way 1 Gang', '275', 'PAID', 9, 'sets', 2475.00, '6 Jun 2026', 'Mon, 8 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000023', NULL),
(306, 45, '0022', 'TAMBOBONG, DASOL, PANGASINAN', 'VPSGM000023', '2 Gang Switch 3 way', '350', 'PAID', 1, 'pc', 350.00, '6 Jun 2026', 'Mon, 8 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000023', NULL),
(307, 45, '0022', 'TAMBOBONG, DASOL, PANGASINAN', 'VPSGM000023', 'Secondary Rock 2 Poles (ARC)', '590', 'PAID', 4, 'SETS', 2360.00, '6 Jun 2026', 'Mon, 8 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000023', NULL),
(308, 45, '0022', 'TAMBOBONG, DASOL, PANGASINAN', 'VPSGM000023', 'Screw Porcelain Insulator Big', '52', 'PAID', 15, 'Pcs', 780.00, '6 Jun 2026', 'Mon, 8 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000023', NULL),
(309, 45, '0022', 'TAMBOBONG, DASOL, PANGASINAN', 'VPSGM000023', 'Entrance Cap #1 1/4', '104', 'PAID', 2, 'Pcs', 208.00, '6 Jun 2026', 'Mon, 8 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000023', NULL),
(310, 45, '0022', 'TAMBOBONG, DASOL, PANGASINAN', 'VPSGM000023', 'PVC Pipe #1 1/4', '195', 'PAID', 2, 'Pcs', 390.00, '6 Jun 2026', 'Mon, 8 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000023', NULL),
(311, 45, '0022', 'TAMBOBONG, DASOL, PANGASINAN', 'VPSGM000023', 'Receptacle', '65', 'PAID', 4, 'Pcs', 260.00, '6 Jun 2026', 'Mon, 8 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000023', NULL),
(312, 45, '0022', 'TAMBOBONG, DASOL, PANGASINAN', 'VPSGM000023', 'LED Bulb', '450', 'PAID', 4, 'Pcs', 1800.00, '6 Jun 2026', 'Mon, 8 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000023', NULL),
(313, 46, '0022', 'TAMBOBONG, DASOL, PANGASINAN', 'VPSGM000024', 'Faucets', '95', 'PAID', 11, 'Pcs', 1045.00, '6 Jun 2026', 'Mon, 8 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000024', NULL),
(314, 46, '0022', 'TAMBOBONG, DASOL, PANGASINAN', 'VPSGM000024', 'Tee', '45', 'PAID', 6, 'Pcs', 270.00, '6 Jun 2026', 'Mon, 8 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000024', NULL),
(315, 46, '0022', 'TAMBOBONG, DASOL, PANGASINAN', 'VPSGM000024', 'Elbow', '45', 'PAID', 7, 'Pcs', 315.00, '6 Jun 2026', 'Mon, 8 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000024', NULL),
(316, 46, '0022', 'TAMBOBONG, DASOL, PANGASINAN', 'VPSGM000024', 'Coupling', '65', 'PAID', 5, 'Pcs', 325.00, '6 Jun 2026', 'Mon, 8 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000024', NULL),
(317, 46, '0022', 'TAMBOBONG, DASOL, PANGASINAN', 'VPSGM000024', 'Female Adapter', '65', 'PAID', 10, 'Pcs', 650.00, '6 Jun 2026', 'Mon, 8 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000024', NULL),
(318, 46, '0022', 'TAMBOBONG, DASOL, PANGASINAN', 'VPSGM000024', 'Neltex', '220', 'PAID', 2, 'Pcs', 440.00, '6 Jun 2026', 'Mon, 8 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000024', NULL),
(319, 46, '0022', 'TAMBOBONG, DASOL, PANGASINAN', 'VPSGM000024', 'Tiles, 40x40', '78', 'PAID', 28, 'Pcs', 2184.00, '6 Jun 2026', 'Mon, 8 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000024', NULL),
(320, 46, '0022', 'TAMBOBONG, DASOL, PANGASINAN', 'VPSGM000024', 'Cement', '280', 'PAID', 1, 'bag', 280.00, '6 Jun 2026', 'Mon, 8 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000024', NULL),
(321, 47, '0022', 'TAMBOBONG, DASOL, PANGASINAN', 'VPSGM000025', 'Green paint for blackboard', '1350', 'PAID', 3, 'Pcs', 4050.00, '6 Jun 2026', 'Mon, 8 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000025', NULL),
(322, 47, '0022', 'TAMBOBONG, DASOL, PANGASINAN', 'VPSGM000025', 'Mahogany', '1350', 'PAID', 2, 'Pcs', 2700.00, '6 Jun 2026', 'Mon, 8 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000025', NULL),
(323, 47, '0022', 'TAMBOBONG, DASOL, PANGASINAN', 'VPSGM000025', 'Varnish', '135', 'PAID', 10, 'Bottles', 1350.00, '6 Jun 2026', 'Mon, 8 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000025', NULL),
(324, 47, '0022', 'TAMBOBONG, DASOL, PANGASINAN', 'VPSGM000025', 'Paint Brush #2', '95', 'PAID', 14, 'Pcs', 1330.00, '6 Jun 2026', 'Mon, 8 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000025', NULL),
(325, 47, '0022', 'TAMBOBONG, DASOL, PANGASINAN', 'VPSGM000025', 'Vulca seal', '850', 'PAID', 3, 'Pcs', 2550.00, '6 Jun 2026', 'Mon, 8 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000025', NULL),
(326, 47, '0022', 'TAMBOBONG, DASOL, PANGASINAN', 'VPSGM000025', 'Paint brush #4', '125', 'PAID', 4, 'Pcs', 500.00, '6 Jun 2026', 'Mon, 8 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000025', NULL),
(327, 47, '0022', 'TAMBOBONG, DASOL, PANGASINAN', 'VPSGM000025', 'Thalo green paint', '1350', 'PAID', 1, 'Gallon', 1350.00, '6 Jun 2026', 'Mon, 8 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000025', NULL),
(328, 47, '0022', 'TAMBOBONG, DASOL, PANGASINAN', 'VPSGM000025', 'White paint', '1350', 'PAID', 1, 'Gallon', 1350.00, '6 Jun 2026', 'Mon, 8 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000025', NULL),
(329, 48, '0022', 'Tambobong National High School, Dasol, Pangasinan', 'VPSGM000026', 'Green Paint', '1350', 'PAID', 3, 'Gal', 4050.00, '9 Jun 2026', 'Fri, 5 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000026', NULL),
(330, 48, '0022', 'Tambobong National High School, Dasol, Pangasinan', 'VPSGM000026', 'Mahogany Paint', '1350', 'PAID', 2, 'Gal', 2700.00, '9 Jun 2026', 'Fri, 5 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000026', NULL),
(331, 48, '0022', 'Tambobong National High School, Dasol, Pangasinan', 'VPSGM000026', 'Thalo green paint', '1350', 'PENDING', 1, 'Gallon', 1350.00, '9 Jun 2026', 'Fri, 5 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000026', NULL),
(332, 48, '0022', 'Tambobong National High School, Dasol, Pangasinan', 'VPSGM000026', 'White paint', '1350', 'PAID', 1, 'Gallon', 1350.00, '9 Jun 2026', 'Fri, 5 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000026', NULL),
(333, 48, '0022', 'Tambobong National High School, Dasol, Pangasinan', 'VPSGM000026', 'Paint Brush #2', '95', 'PAID', 14, 'Pcs', 1330.00, '9 Jun 2026', 'Fri, 5 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000026', NULL),
(334, 48, '0022', 'Tambobong National High School, Dasol, Pangasinan', 'VPSGM000026', 'Paint brush #4', '125', 'PAID', 4, 'Pcs', 500.00, '9 Jun 2026', 'Fri, 5 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000026', NULL),
(335, 48, '0022', 'Tambobong National High School, Dasol, Pangasinan', 'VPSGM000026', 'Varnish', '135', 'PAID', 10, 'Bottles', 1350.00, '9 Jun 2026', 'Fri, 5 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000026', NULL),
(336, 48, '0022', 'Tambobong National High School, Dasol, Pangasinan', 'VPSGM000026', 'Vulcaseal', '850', 'PAID', 3, 'Litters', 2550.00, '9 Jun 2026', 'Fri, 5 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000026', NULL),
(337, 49, '0022', 'Tambobong National High Shool, Dasol, Pangasinan', 'VPSGM000027', 'Faucets', '95', 'PAID', 11, 'Pcs', 1045.00, '9 Jun 2026', 'Tue, 9 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000027', NULL),
(338, 49, '0022', 'Tambobong National High Shool, Dasol, Pangasinan', 'VPSGM000027', 'Tee', '45', 'PAID', 6, 'Pcs', 270.00, '9 Jun 2026', 'Tue, 9 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000027', NULL),
(339, 49, '0022', 'Tambobong National High Shool, Dasol, Pangasinan', 'VPSGM000027', 'Elbow', '45', 'PAID', 7, 'Pcs', 315.00, '9 Jun 2026', 'Tue, 9 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000027', NULL),
(340, 49, '0022', 'Tambobong National High Shool, Dasol, Pangasinan', 'VPSGM000027', 'Coupling', '65', 'PAID', 5, 'Pcs', 325.00, '9 Jun 2026', 'Tue, 9 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000027', NULL),
(341, 49, '0022', 'Tambobong National High Shool, Dasol, Pangasinan', 'VPSGM000027', 'Female Adapter', '65', 'PAID', 10, 'Pcs', 650.00, '9 Jun 2026', 'Tue, 9 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000027', NULL),
(342, 49, '0022', 'Tambobong National High Shool, Dasol, Pangasinan', 'VPSGM000027', 'Neltex', '220', 'PAID', 2, 'Pcs', 440.00, '9 Jun 2026', 'Tue, 9 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000027', NULL),
(343, 49, '0022', 'Tambobong National High Shool, Dasol, Pangasinan', 'VPSGM000027', 'Cement', '280', 'PAID', 1, 'bag', 280.00, '9 Jun 2026', 'Tue, 9 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000027', NULL),
(344, 49, '0022', 'Tambobong National High Shool, Dasol, Pangasinan', 'VPSGM000027', 'Tiles, 40x40', '78', 'PAID', 28, 'Pcs', 2184.00, '9 Jun 2026', 'Tue, 9 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000027', NULL),
(345, 50, '0022', 'Tambobong National High School, Dasol, Pangasinan', 'VPSGM000028', 'Outlet 3 Gang', '247', 'PAID', 1, 'set', 247.00, '9 Jun 2026', 'Tue, 9 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000028', NULL),
(346, 50, '0022', 'Tambobong National High School, Dasol, Pangasinan', 'VPSGM000028', 'Outlet 2 Gang', '192.4', 'PAID', 1, 'Pcs', 192.40, '9 Jun 2026', 'Tue, 9 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000028', NULL),
(347, 50, '0022', 'Tambobong National High School, Dasol, Pangasinan', 'VPSGM000028', 'Switch 2 Gang', '211.9', 'PAID', 1, 'set', 211.90, '9 Jun 2026', 'Tue, 9 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000028', NULL),
(348, 50, '0022', 'Tambobong National High School, Dasol, Pangasinan', 'VPSGM000028', 'Switch 1 Gang', '169', 'PAID', 4, 'sets', 676.00, '9 Jun 2026', 'Tue, 9 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000028', NULL),
(349, 50, '0022', 'Tambobong National High School, Dasol, Pangasinan', 'VPSGM000028', 'Switch 3 way 1 Gang', '275', 'PAID', 9, 'sets', 2475.00, '9 Jun 2026', 'Tue, 9 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000028', NULL),
(350, 50, '0022', 'Tambobong National High School, Dasol, Pangasinan', 'VPSGM000028', 'YHO #150(connector)', '200', 'PAID', 8, 'Pcs', 1600.00, '9 Jun 2026', 'Tue, 9 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000028', NULL),
(351, 50, '0022', 'Tambobong National High School, Dasol, Pangasinan', 'VPSGM000028', '2 Gang Switch 3 way', '350', 'PAID', 1, 'pc', 350.00, '9 Jun 2026', 'Tue, 9 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000028', NULL),
(352, 50, '0022', 'Tambobong National High School, Dasol, Pangasinan', 'VPSGM000028', 'Secondary Rock 2 Poles (ARC)', '590', 'PAID', 4, 'SETS', 2360.00, '9 Jun 2026', 'Tue, 9 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000028', NULL),
(353, 50, '0022', 'Tambobong National High School, Dasol, Pangasinan', 'VPSGM000028', 'Screw Porcelain Insulator Big', '52', 'PAID', 15, 'Pcs', 780.00, '9 Jun 2026', 'Tue, 9 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000028', NULL),
(354, 50, '0022', 'Tambobong National High School, Dasol, Pangasinan', 'VPSGM000028', 'Entrance Cap #1 1/4', '104', 'PAID', 2, 'Pcs', 208.00, '9 Jun 2026', 'Tue, 9 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000028', NULL),
(355, 50, '0022', 'Tambobong National High School, Dasol, Pangasinan', 'VPSGM000028', 'PVC Pipe #1 1/4', '195', 'PAID', 2, 'Pcs', 390.00, '9 Jun 2026', 'Tue, 9 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000028', NULL),
(356, 50, '0022', 'Tambobong National High School, Dasol, Pangasinan', 'VPSGM000028', 'Receptacle', '65', 'PAID', 4, 'Pcs', 260.00, '9 Jun 2026', 'Tue, 9 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000028', NULL),
(357, 50, '0022', 'Tambobong National High School, Dasol, Pangasinan', 'VPSGM000028', 'LED Bulb', '450', 'PAID', 4, 'Pcs', 1800.00, '9 Jun 2026', 'Tue, 9 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000028', NULL),
(358, 51, '0022', 'Tambobong National High School, Dasol, Pangasinan', 'VPSGM000029', 'Legal Coupon', '240', 'PAID', 30, 'Reams', 7200.00, '9 Jun 2026', 'Tue, 9 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000029', NULL),
(359, 51, '0022', 'Tambobong National High School, Dasol, Pangasinan', 'VPSGM000029', 'A4 Coupon', '230', 'PAID', 30, 'Reams', 6900.00, '9 Jun 2026', 'Tue, 9 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000029', NULL),
(360, 52, '0022', 'Tambac, Dasol, Pangasinan', 'VPSGM000030', 'Acer Aspire 5 Laptop', '29990', 'PAID', 1, 'Unit', 29990.00, '9 Jun 2026', 'Tue, 9 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000030', NULL),
(362, 54, '2670', 'BARLO, MABINI PANGASINAN', 'VPSGM000032', 'Smart TV', ' 18000', 'CANCELLED', 1, 'PCS', 18000.00, '9 Jun 2026', 'Tue, 23 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000032', NULL),
(373, 56, '2670', 'BARLO MABINI PANGASINAN', 'VPSGM000033', 'Epson Printer L5290 (wifi)', '17900', 'PENDING', 1, 'Unit', 17900.00, '9 Jun 2026', 'Tue, 16 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000033', NULL),
(374, 57, '2670', 'BARLO MABINI PANGASINAN', 'VPSGM000034', 'Baby Wipes (100 sheets)', ' 100', 'PENDING', 10, 'PCS', 1000.00, '9 Jun 2026', 'Tue, 16 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000034', NULL),
(375, 57, '2670', 'BARLO MABINI PANGASINAN', 'VPSGM000034', 'Toilet Tissue', ' 240', 'PENDING', 5, 'PCS', 1200.00, '9 Jun 2026', 'Tue, 16 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000034', NULL),
(376, 57, '2670', 'BARLO MABINI PANGASINAN', 'VPSGM000034', 'Mop with spinner', ' 450', 'PENDING', 2, 'PCS', 900.00, '9 Jun 2026', 'Tue, 16 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000034', NULL),
(377, 57, '2670', 'BARLO MABINI PANGASINAN', 'VPSGM000034', 'Walis Pang-agiw', ' 180', 'PENDING', 1, 'PCS', 180.00, '9 Jun 2026', 'Tue, 16 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000034', NULL),
(378, 57, '2670', 'BARLO MABINI PANGASINAN', 'VPSGM000034', 'Walis tingting', ' 50', 'PENDING', 4, 'PCS', 200.00, '9 Jun 2026', 'Tue, 16 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000034', NULL),
(379, 57, '2670', 'BARLO MABINI PANGASINAN', 'VPSGM000034', 'Detergent Powder/kilo', ' 180', 'PENDING', 2, 'PCS', 360.00, '9 Jun 2026', 'Tue, 16 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000034', NULL),
(380, 57, '2670', 'BARLO MABINI PANGASINAN', 'VPSGM000034', 'Tuff TBC 1 Liter', ' 315', 'PENDING', 2, 'PCS', 630.00, '9 Jun 2026', 'Tue, 16 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000034', NULL),
(381, 57, '2670', 'BARLO MABINI PANGASINAN', 'VPSGM000034', 'Bottled Hand Soap', ' 180', 'PENDING', 2, 'PCS', 360.00, '9 Jun 2026', 'Tue, 16 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000034', NULL),
(382, 57, '2670', 'BARLO MABINI PANGASINAN', 'VPSGM000034', 'Pail orocan assorted color', ' 280', 'PENDING', 2, 'PCS', 560.00, '9 Jun 2026', 'Tue, 16 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000034', NULL),
(383, 57, '2670', 'BARLO MABINI PANGASINAN', 'VPSGM000034', 'Water Drum 80 Liters', ' 450', 'PENDING', 2, 'PCS', 900.00, '9 Jun 2026', 'Tue, 16 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000034', NULL),
(384, 98, '', NULL, NULL, 'Coupon short', '200', 'PAID', 1, 'Reams', 200.00, '10 Jun 2026 1:42 PM', NULL, NULL, 'Sell'),
(385, 58, '2670', 'barlo mabini pangasinan', 'VPSGM000035', 'A4 Coupon', ' 230', 'PENDING', 20, 'PCS', 4600.00, '10 Jun 2026', 'Tue, 16 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000035', NULL),
(386, 58, '2670', 'barlo mabini pangasinan', 'VPSGM000035', 'Legal Coupon', ' 240', 'PENDING', 5, 'PCS', 1200.00, '10 Jun 2026', 'Tue, 16 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000035', NULL),
(387, 58, '2670', 'barlo mabini pangasinan', 'VPSGM000035', 'Long folder (green)', ' 950', 'PENDING', 1, 'PCS', 950.00, '10 Jun 2026', 'Tue, 16 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000035', NULL),
(388, 58, '2670', 'barlo mabini pangasinan', 'VPSGM000035', 'Ballpen Green', ' 150', 'PENDING', 1, 'PCS', 150.00, '10 Jun 2026', 'Tue, 16 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000035', NULL),
(389, 58, '2670', 'barlo mabini pangasinan', 'VPSGM000035', 'FlexStick ballpen', ' 150', 'PENDING', 1, 'PCS', 150.00, '10 Jun 2026', 'Tue, 16 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000035', NULL),
(390, 58, '2670', 'barlo mabini pangasinan', 'VPSGM000035', 'Energel .5 blue', ' 98', 'PENDING', 3, 'PCS', 294.00, '10 Jun 2026', 'Tue, 16 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000035', NULL),
(391, 58, '2670', 'barlo mabini pangasinan', 'VPSGM000035', 'Energel .5 black', ' 98', 'PENDING', 3, 'PCS', 294.00, '10 Jun 2026', 'Tue, 16 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000035', NULL),
(392, 58, '2670', 'barlo mabini pangasinan', 'VPSGM000035', 'G-tech .5 black', ' 95', 'PENDING', 4, 'PCS', 380.00, '10 Jun 2026', 'Tue, 16 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000035', NULL),
(393, 58, '2670', 'barlo mabini pangasinan', 'VPSGM000035', 'Epson Ink 003 Black', ' 280', 'PENDING', 2, 'PCS', 560.00, '10 Jun 2026', 'Tue, 16 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000035', NULL),
(394, 58, '2670', 'barlo mabini pangasinan', 'VPSGM000035', 'Epson Ink 003 Cyan', ' 290', 'PENDING', 2, 'PCS', 580.00, '10 Jun 2026', 'Tue, 16 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000035', NULL),
(395, 58, '2670', 'barlo mabini pangasinan', 'VPSGM000035', 'Epson Ink 003 Magenta', ' 290', 'PENDING', 2, 'PCS', 580.00, '10 Jun 2026', 'Tue, 16 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000035', NULL),
(396, 58, '2670', 'barlo mabini pangasinan', 'VPSGM000035', 'Epson Ink 003 Yellow', ' 290', 'PENDING', 2, 'PCS', 580.00, '10 Jun 2026', 'Tue, 16 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000035', NULL),
(397, 58, '2670', 'barlo mabini pangasinan', 'VPSGM000035', 'Expanded Folder Long Orange', ' 25', 'PENDING', 20, 'PCS', 500.00, '10 Jun 2026', 'Tue, 16 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000035', NULL),
(398, 58, '2670', 'barlo mabini pangasinan', 'VPSGM000035', 'Expanded Folder Long Yellow', ' 25', 'PENDING', 20, 'PCS', 500.00, '10 Jun 2026', 'Tue, 16 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000035', NULL),
(399, 58, '2670', 'barlo mabini pangasinan', 'VPSGM000035', 'Pilot Permanent Marker', ' 49', 'PENDING', 5, 'PCS', 245.00, '10 Jun 2026', 'Tue, 16 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000035', NULL),
(400, 97, '', NULL, NULL, 'A4 Coupon', '230', 'PAID', 1, 'Reams', 230.00, '11 Jun 2026 8:18 AM', NULL, NULL, 'Sell'),
(410, 60, '0022', 'Petal, Dasol, Pangasinan', 'VPSGM000036', 'Bolo', '650', 'PAID', 2, 'Pcs', 1300.00, '11 Jun 2026', 'Thu, 11 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000036', NULL),
(411, 60, '0022', 'Petal, Dasol, Pangasinan', 'VPSGM000036', 'Shovel', '350', 'PAID', 2, 'Pcs', 700.00, '11 Jun 2026', 'Thu, 11 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000036', NULL),
(412, 60, '0022', 'Petal, Dasol, Pangasinan', 'VPSGM000036', 'Digging Bar', '650', 'PAID', 1, 'Pcs', 650.00, '11 Jun 2026', 'Thu, 11 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000036', NULL),
(413, 60, '0022', 'Petal, Dasol, Pangasinan', 'VPSGM000036', 'Soft broom (walis tambo)', '250', 'PAID', 10, 'Pcs', 2500.00, '11 Jun 2026', 'Thu, 11 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000036', NULL),
(414, 60, '0022', 'Petal, Dasol, Pangasinan', 'VPSGM000036', 'Dust pan', '95', 'PAID', 10, 'Pcs', 950.00, '11 Jun 2026', 'Thu, 11 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000036', NULL),
(415, 60, '0022', 'Petal, Dasol, Pangasinan', 'VPSGM000036', 'Tuff', '285', 'PAID', 12, 'bottles', 3420.00, '11 Jun 2026', 'Thu, 11 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000036', NULL),
(416, 60, '0022', 'Petal, Dasol, Pangasinan', 'VPSGM000036', 'Powder detergent', '85', 'PAID', 100, 'Packs', 8500.00, '11 Jun 2026', 'Thu, 11 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000036', NULL),
(417, 60, '0022', 'Petal, Dasol, Pangasinan', 'VPSGM000036', 'Plant trimmer', '751.67', 'PAID', 1, 'Pcs', 751.67, '11 Jun 2026', 'Thu, 11 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000036', NULL),
(418, 60, '0022', 'Petal, Dasol, Pangasinan', 'VPSGM000036', 'Trash Bin (60L)', '340', 'PAID', 3, 'Pcs', 1020.00, '11 Jun 2026', 'Thu, 11 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000036', NULL),
(419, 61, '8156', 'VILLACORTA, MABINI, PANGASINAN', 'VPSGM000037', 'Tuff', '285', 'PAID', 5, 'bottles', 1425.00, '11 Jun 2026', 'Wed, 10 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000037', NULL),
(420, 61, '8156', 'VILLACORTA, MABINI, PANGASINAN', 'VPSGM000037', 'Check Soap', ' 65', 'PAID', 7, 'Pcs', 455.00, '11 Jun 2026', 'Wed, 10 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000037', NULL),
(421, 61, '8156', 'VILLACORTA, MABINI, PANGASINAN', 'VPSGM000037', 'Tissue', '240', 'PAID', 2, 'Packs', 480.00, '11 Jun 2026', 'Wed, 10 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000037', NULL),
(422, 61, '8156', 'VILLACORTA, MABINI, PANGASINAN', 'VPSGM000037', 'Dust pan', '95', 'PAID', 7, 'Pcs', 665.00, '11 Jun 2026', 'Wed, 10 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000037', NULL),
(423, 61, '8156', 'VILLACORTA, MABINI, PANGASINAN', 'VPSGM000037', 'Soft broom (walis tambo)', '250', 'PAID', 6, 'Pcs', 1500.00, '11 Jun 2026', 'Wed, 10 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000037', NULL),
(424, 61, '8156', 'VILLACORTA, MABINI, PANGASINAN', 'VPSGM000037', 'Trash Bin (60L)', '340', 'PAID', 2, 'Pcs', 680.00, '11 Jun 2026', 'Wed, 10 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000037', NULL),
(425, 62, '8156', 'VILLACORTA, MABINI, PANGASINAN', 'VPSGM000038', 'Plastic Mug', '20', 'PAID', 100, 'Pcs', 2000.00, '11 Jun 2026', 'Wed, 10 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000038', NULL),
(426, 0, '', NULL, NULL, 'Canon Ink Black', '450', 'PAID', 1, 'Pcs', 450.00, '16 June 2026 10:06 AM', NULL, NULL, 'Sell'),
(427, 63, 'Customer Assistant PO-1', 'EGUIA, DASOL, PANGASINAN', 'VPSGM000039', 'Flush door 210x150', '4500', 'PAID', 5, 'Pcs', 22500.00, '24 June 2026', '25 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(429, 63, 'Customer Assistant PO-1', 'EGUIA, DASOL, PANGASINAN', 'VPSGM000039', 'Door knob', '850', 'PAID', 5, 'Pcs', 4250.00, '24 June 2026', '25 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(430, 64, 'Customer Assistant PO-1', 'EGUIA, DASOL, PANGASINAN', 'VPSGM000040', 'Head frame', '1300', 'PAID', 2, 'Pcs', 2600.00, '24 June 2026', '25 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(431, 64, 'Customer Assistant PO-1', 'EGUIA, DASOL, PANGASINAN', 'VPSGM000040', 'Still frame', '1400', 'PAID', 2, 'Pcs', 2800.00, '24 June 2026', '25 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(432, 64, 'Customer Assistant PO-1', 'EGUIA, DASOL, PANGASINAN', 'VPSGM000040', 'Top bottom frame', '1300', 'PAID', 2, 'Pcs', 2600.00, '24 June 2026', '25 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(433, 64, 'Customer Assistant PO-1', 'EGUIA, DASOL, PANGASINAN', 'VPSGM000040', 'Interlocker', '1100', 'PAID', 2, 'Pcs', 2200.00, '24 June 2026', '25 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(434, 64, 'Customer Assistant PO-1', 'EGUIA, DASOL, PANGASINAN', 'VPSGM000040', 'Lockstie', '1100', 'PAID', 2, 'Pcs', 2200.00, '24 June 2026', '25 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(435, 64, 'Customer Assistant PO-1', 'EGUIA, DASOL, PANGASINAN', 'VPSGM000040', 'Replective Glass', '1800', 'PAID', 6, 'Pcs', 10800.00, '24 June 2026', '25 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(436, 64, 'Customer Assistant PO-1', 'EGUIA, DASOL, PANGASINAN', 'VPSGM000040', 'Silicone', '200', 'PAID', 12, 'Pcs', 2400.00, '24 June 2026', '25 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(437, 65, 'Customer Assistant PO-1', 'EGUIA,DASOL,PANGASINAN', 'VPSGM000041', 'Swivel chair', '3744.4', 'PAID', 6, 'Pcs', 22466.40, '24 June 2026', '25 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(438, 66, 'Customer Assistant PO-1', 'EGUIA, DASOL, PANGASINAN', 'VPSGM000042', 'Brother ink Yellow', '390', 'PAID', 2, 'Pcs', 780.00, '24 June 2026', '25 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(439, 66, 'Customer Assistant PO-1', 'EGUIA, DASOL, PANGASINAN', 'VPSGM000042', 'Ecolum LED bulb', '450', 'PAID', 5, 'Pcs', 2250.00, '24 June 2026', '25 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(440, 66, 'Customer Assistant PO-1', 'EGUIA, DASOL, PANGASINAN', 'VPSGM000042', 'Scotch Tape 2 inch', '65', 'PAID', 4, 'Rolls', 260.00, '24 June 2026', '25 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(441, 66, 'Customer Assistant PO-1', 'EGUIA, DASOL, PANGASINAN', 'VPSGM000042', 'Scotch Tape 1 inch', '35', 'PAID', 6, 'Pcs', 210.00, '24 June 2026', '25 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(442, 66, 'Customer Assistant PO-1', 'EGUIA, DASOL, PANGASINAN', 'VPSGM000042', 'Photo Paper 180 gsm', '100', 'PAID', 2, 'Packs', 200.00, '24 June 2026', '25 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(443, 66, 'Customer Assistant PO-1', 'EGUIA, DASOL, PANGASINAN', 'VPSGM000042', 'A4 Coupon', ' 230', 'PAID', 15, 'Reams', 3450.00, '24 June 2026', '25 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(444, 66, 'Customer Assistant PO-1', 'EGUIA, DASOL, PANGASINAN', 'VPSGM000042', 'Legal Coupon', ' 240', 'PAID', 15, 'Reams', 3600.00, '24 June 2026', '25 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(445, 66, 'Customer Assistant PO-1', 'EGUIA, DASOL, PANGASINAN', 'VPSGM000042', 'Insect killer', '375', 'PAID', 2, 'Pcs', 750.00, '24 June 2026', '25 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(446, 66, 'Customer Assistant PO-1', 'EGUIA, DASOL, PANGASINAN', 'VPSGM000042', 'Padlock', '450', 'PAID', 4, 'Pcs', 1800.00, '24 June 2026', '25 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(447, 66, 'Customer Assistant PO-1', 'EGUIA, DASOL, PANGASINAN', 'VPSGM000042', 'Meter tape', '275', 'PAID', 1, 'Pcs', 275.00, '24 June 2026', '25 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(448, 66, 'Customer Assistant PO-1', 'EGUIA, DASOL, PANGASINAN', 'VPSGM000042', 'Battery Energizer AA', '70', 'PAID', 10, 'Pcs', 700.00, '24 June 2026', '25 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(449, 66, 'Customer Assistant PO-1', 'EGUIA, DASOL, PANGASINAN', 'VPSGM000042', 'Alcohol', '631.49', 'PAID', 1, 'Gallon', 631.49, '24 June 2026', '25 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(450, 66, 'Customer Assistant PO-1', 'EGUIA, DASOL, PANGASINAN', 'VPSGM000042', 'Tissue', '240', 'PAID', 2, 'Packs', 480.00, '24 June 2026', '25 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(451, 66, 'Customer Assistant PO-1', 'EGUIA, DASOL, PANGASINAN', 'VPSGM000042', 'Kitchen Paper Towel twin Pack', '160', 'PAID', 2, 'Pack', 320.00, '24 June 2026', '25 June 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(452, 67, 'Customer Assistant PO-1', 'Don Matias, Burgos, Pangasinan', 'VPSGM000043', 'Coupon A4', '240', 'PAID', 5, 'reams', 1200.00, '6 July 2026', '6 July 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(453, 67, 'Customer Assistant PO-1', 'Don Matias, Burgos, Pangasinan', 'VPSGM000043', 'Epson ink 003 blk', '274.48', 'PAID', 2, 'Bottles', 548.96, '6 July 2026', '6 July 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(454, 68, '4266', 'Barlo Millsite, Barlo, Mabini, Pangasinan', 'VPSGM000044', 'File Folder A4', '800', 'PAID', 1, 'Ream', 800.00, '7 July 2026', '8 July 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(456, 68, '4266', 'Barlo Millsite, Barlo, Mabini, Pangasinan', 'VPSGM000044', 'Epson Ink 003 Black', ' 280', 'PAID', 3, 'Bottles', 840.00, '7 July 2026', '8 July 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(457, 68, '4266', 'Barlo Millsite, Barlo, Mabini, Pangasinan', 'VPSGM000044', 'Epson Ink 003 Yellow', ' 290', 'PAID', 2, 'Bottles', 580.00, '7 July 2026', '8 July 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(458, 68, '4266', 'Barlo Millsite, Barlo, Mabini, Pangasinan', 'VPSGM000044', 'Epson Ink 664 Black', '280', 'PAID', 2, 'Bottles', 560.00, '7 July 2026', '8 July 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(459, 68, '4266', 'Barlo Millsite, Barlo, Mabini, Pangasinan', 'VPSGM000044', 'Epson Ink 664 Magenta', '290', 'PAID', 2, 'Bottles', 580.00, '7 July 2026', '8 July 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(460, 68, '4266', 'Barlo Millsite, Barlo, Mabini, Pangasinan', 'VPSGM000044', 'Coupon A4', '240', 'PAID', 13, 'reams', 3120.00, '7 July 2026', '8 July 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(461, 68, '4266', 'Barlo Millsite, Barlo, Mabini, Pangasinan', 'VPSGM000044', 'Scotch Tape 1 inch', '35', 'PAID', 3, 'Pcs', 105.00, '7 July 2026', '8 July 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(462, 68, '4266', 'Barlo Millsite, Barlo, Mabini, Pangasinan', 'VPSGM000044', 'Scotch Tape 2 inch', '65', 'PAID', 2, 'Rolls', 130.00, '7 July 2026', '8 July 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(463, 68, '4266', 'Barlo Millsite, Barlo, Mabini, Pangasinan', 'VPSGM000044', 'Legal Coupon', ' 240', 'PAID', 1, 'Reams', 240.00, '7 July 2026', '8 July 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(464, 68, '4266', 'Barlo Millsite, Barlo, Mabini, Pangasinan', 'VPSGM000044', 'Toilet Tissue', '240', 'PAID', 1, 'Packs', 240.00, '7 July 2026', '8 July 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(465, 68, '4266', 'Barlo Millsite, Barlo, Mabini, Pangasinan', 'VPSGM000044', 'Staples Wire #35', '75', 'PAID', 2, 'Box', 150.00, '7 July 2026', '8 July 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(466, 68, '4266', 'Barlo Millsite, Barlo, Mabini, Pangasinan', 'VPSGM000044', 'HBW Stapler', '185', 'PAID', 2, 'Box', 370.00, '7 July 2026', '8 July 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(467, 68, '4266', 'Barlo Millsite, Barlo, Mabini, Pangasinan', 'VPSGM000044', 'Double Sided Tape 1 inch', '45', 'PAID', 1, 'Rolls', 45.00, '7 July 2026', '8 July 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(468, 68, '4266', 'Barlo Millsite, Barlo, Mabini, Pangasinan', 'VPSGM000044', 'Push Pin', '50', 'PAID', 3, 'Packs', 150.00, '7 July 2026', '8 July 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(469, 68, '4266', 'Barlo Millsite, Barlo, Mabini, Pangasinan', 'VPSGM000044', 'HBW Scissors', '180', 'PAID', 6, 'Pcs', 1080.00, '7 July 2026', '8 July 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(470, 68, '4266', 'Barlo Millsite, Barlo, Mabini, Pangasinan', 'VPSGM000044', 'Elmer\'s Glue 130g', '65', 'PAID', 1, 'Pcs', 65.00, '7 July 2026', '8 July 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(471, 68, '4266', 'Barlo Millsite, Barlo, Mabini, Pangasinan', 'VPSGM000044', 'Paper Clip 33mm', '45', 'PAID', 1, 'Packs', 45.00, '7 July 2026', '8 July 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(472, 68, '4266', 'Barlo Millsite, Barlo, Mabini, Pangasinan', 'VPSGM000044', 'Paper Fastener', '100', 'PAID', 1, 'Packs', 100.00, '7 July 2026', '8 July 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(473, 68, '4266', 'Barlo Millsite, Barlo, Mabini, Pangasinan', 'VPSGM000044', 'Panda Sign Pen', '75', 'PAID', 1, 'Pcs', 75.00, '7 July 2026', '8 July 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(474, 68, '4266', 'Barlo Millsite, Barlo, Mabini, Pangasinan', 'VPSGM000044', 'FlexStick ballpen', ' 150', 'PAID', 1, 'Pack', 150.00, '7 July 2026', '8 July 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(475, 68, '4266', 'Barlo Millsite, Barlo, Mabini, Pangasinan', 'VPSGM000044', 'Correction Tape', '65', 'PAID', 1, 'Pcs', 65.00, '7 July 2026', '8 July 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(476, 68, '4266', 'Barlo Millsite, Barlo, Mabini, Pangasinan', 'VPSGM000044', 'Folder long', '10', 'PAID', 10, 'Pcs', 100.00, '7 July 2026', '8 July 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(478, 69, 'Customer Assistant PO-1', 'TAMBOBONG, DASOL, PANGASINAN', 'VPSGM000045', 'Junction Box 10cm', '150', 'PAID', 4, 'Pcs', 600.00, '13 July 2026', '13 July 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(479, 70, 'Customer Assistant PO-2', 'Don Antonio Bonilla ES, San Vicente, Burgos, Pangasinan', 'VPSGM000046', 'HIK Vision CCTV Camera 2.0Mp', '3000', 'PAID', 2, 'Pcs', 6000.00, '14 July 2026', '14 July 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(480, 71, 'Customer Assistant PO-1', 'TAMBOBONG, DASOL, PANGASINAN', 'VPSGM000047', 'Stand fan, Camel', '3200', 'PAID', 4, 'Pcs', 12800.00, '14 July 2026', '14 July 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(481, 72, 'Customer Assistant PO-1', 'TAMBOBONG, DASOL, PANGASINAN', 'VPSGM000048', 'Stand fan, Eureka', '2800', 'PAID', 2, 'Pcs', 5600.00, '14 July 2026', '14 July 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(486, 74, 'Customer Assistant PO-1', 'CABIANGAN, MABINI, PANGASINAN', 'VPSGM000049', 'CCTV Package 3', '23990', 'PAID', 1, 'set', 23990.00, '15 July 2026', '15 July 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(487, 75, 'Customer Assistant PO-1', 'EGUIA, DASOL, PANGASINAN', 'VPSGM000050', 'Circuit breaker BOLT ON 60AMP', ' 480', 'PAID', 1, 'PCS', 480.00, '16 July 2026', '17 July 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(488, 75, 'Customer Assistant PO-1', 'EGUIA, DASOL, PANGASINAN', 'VPSGM000050', 'Electrical tape 20 yrd', ' 70', 'PAID', 1, 'PCS', 70.00, '16 July 2026', '17 July 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(489, 75, 'Customer Assistant PO-1', 'EGUIA, DASOL, PANGASINAN', 'VPSGM000050', 'Electric wire#6', ' 210', 'PAID', 6, 'PCS', 1260.00, '16 July 2026', '17 July 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(490, 76, 'Customer Assistant PO-1', 'TAMBOBONG, DASOL, PANGASINAN', 'VPSGM000051', 'Smart TV, 43', '19900', 'PAID', 2, 'Pcs', 39800.00, '16 July 2026', '16 July 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(491, 76, 'Customer Assistant PO-1', 'TAMBOBONG, DASOL, PANGASINAN', 'VPSGM000051', 'Book Holder', '480', 'PAID', 1, 'Pcs', 480.00, '16 July 2026', '16 July 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(492, 76, 'Customer Assistant PO-1', 'TAMBOBONG, DASOL, PANGASINAN', 'VPSGM000051', 'File organizer', '460', 'PAID', 1, 'Pcs', 460.00, '16 July 2026', '16 July 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(493, 77, '2670', 'barlo mabini pangasinan', 'VPSGM000052', 'Metal Detector (for gate security?', '1820', 'PAID', 1, 'Pcs', 1820.00, '16 July 2026', '21 July 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(494, 77, '2670', 'barlo mabini pangasinan', 'VPSGM000052', 'A4 Coupon', ' 230', 'PAID', 10, 'Reams', 2300.00, '16 July 2026', '21 July 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(495, 77, '2670', 'barlo mabini pangasinan', 'VPSGM000052', 'Epson Ink 003 Black', ' 280', 'PAID', 4, 'Bottles', 1120.00, '16 July 2026', '21 July 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(496, 77, '2670', 'barlo mabini pangasinan', 'VPSGM000052', 'Epson Ink 003 (Set)', '1150', 'PAID', 1, 'Set', 1150.00, '16 July 2026', '21 July 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(497, 77, '2670', 'barlo mabini pangasinan', 'VPSGM000052', 'Mop with spinner', ' 450', 'PAID', 1, 'Pcs', 450.00, '16 July 2026', '21 July 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL);
INSERT INTO `order_status_history` (`id`, `order_id`, `acc_number`, `delivery_address`, `delivery_number`, `product_name`, `selling_price`, `status`, `pieces`, `unit`, `total_amount`, `date_time_sold`, `delivery_date`, `qr_code`, `note`) VALUES
(498, 77, '2670', 'barlo mabini pangasinan', 'VPSGM000052', 'Pail orocan assorted color', ' 280', 'PAID', 1, 'Pcs', 280.00, '16 July 2026', '21 July 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(499, 78, '2670', 'barlo mabini pangasinan', 'VPSGM000053', 'Stand fan, Camel', '3200', 'PAID', 1, 'Pcs', 3200.00, '16 July 2026', '21 July 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(500, 79, 'Customer Assistant PO-1', 'EGUIA, DASOL, PANGASINAN', 'VPSGM000054', 'Cork Bord', '3500', 'PAID', 2, 'set', 7000.00, '18 July 2026', '22 July 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(501, 79, 'Customer Assistant PO-1', 'EGUIA, DASOL, PANGASINAN', 'VPSGM000054', 'Organizer Cabinet', '2000', 'PAID', 1, 'unit', 2000.00, '18 July 2026', '22 July 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(502, 79, 'Customer Assistant PO-1', 'EGUIA, DASOL, PANGASINAN', 'VPSGM000054', 'Bulletin Board', '3000', 'PAID', 1, 'unit', 3000.00, '18 July 2026', '22 July 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(503, 79, 'Customer Assistant PO-1', 'EGUIA, DASOL, PANGASINAN', 'VPSGM000054', 'Printer 3 in 1', '20000', 'PAID', 1, 'set', 20000.00, '18 July 2026', '22 July 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(504, 79, 'Customer Assistant PO-1', 'EGUIA, DASOL, PANGASINAN', 'VPSGM000054', 'Printer ink 003', '2000', 'PAID', 2, 'set', 4000.00, '18 July 2026', '22 July 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(505, 79, 'Customer Assistant PO-1', 'EGUIA, DASOL, PANGASINAN', 'VPSGM000054', 'Printer ink Blk 003', '500', 'PAID', 2, 'Pcs', 1000.00, '18 July 2026', '22 July 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(506, 80, 'Customer Assistant PO-1', 'VIGA, DASOL, PANGASINAN', 'VPSGM000055', 'Long Coupon', ' 1200', 'PAID', 1, 'PCS', 1200.00, '19 July 2026', '20 July 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(507, 80, 'Customer Assistant PO-1', 'VIGA, DASOL, PANGASINAN', 'VPSGM000055', 'File Folder Long', ' 850', 'PAID', 1, 'PCS', 850.00, '19 July 2026', '20 July 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(508, 80, 'Customer Assistant PO-1', 'VIGA, DASOL, PANGASINAN', 'VPSGM000055', 'Paper Fastener', ' 100', 'PAID', 2, 'PCS', 200.00, '19 July 2026', '20 July 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(509, 80, 'Customer Assistant PO-1', 'VIGA, DASOL, PANGASINAN', 'VPSGM000055', 'HBW Heavy Duty Puncher', ' 250', 'PAID', 2, 'PCS', 500.00, '19 July 2026', '20 July 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(510, 80, 'Customer Assistant PO-1', 'VIGA, DASOL, PANGASINAN', 'VPSGM000055', 'Stamp ink', ' 65', 'PAID', 1, 'PCS', 65.00, '19 July 2026', '20 July 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(511, 80, 'Customer Assistant PO-1', 'VIGA, DASOL, PANGASINAN', 'VPSGM000055', 'Epson ink 003', ' 1150', 'PAID', 4, 'PCS', 4600.00, '19 July 2026', '20 July 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(512, 80, 'Customer Assistant PO-1', 'VIGA, DASOL, PANGASINAN', 'VPSGM000055', 'A4 Coupon', ' 1150', 'PAID', 2, 'PCS', 2300.00, '19 July 2026', '20 July 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(513, 80, '8156', 'VIGA, DASOL, PANGASINAN', 'VPSGM000055', 'Swivel chair', ' 3744.4', 'PAID', 1, 'PCS', 3744.40, 'Sun, 19 Jul 2026 12:35 AM', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000055', NULL),
(514, 81, 'Customer Assistant PO-1', 'PAPALLASEN, BURGOS, PANGASINAN', 'VPSGM000056', 'HIK Vision CCTV Camera 2.0Mp', '3000', 'PENDING', 3, 'Pcs', 9000.00, '20 July 2026', '20 July 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(515, 0, '', NULL, NULL, 'Elmer\'s Glue 130g', '65', 'PAID', 1, 'Pcs', 65.00, '31 July 2026 1:01 PM', NULL, NULL, 'Sell'),
(516, 82, '3145', 'Eguia, Dasol, Pangasinan', 'VPSGM000057', 'HIK Vision CCTV package (16-ch DVR/ 12 Camera with', '49990', 'PAID', 1, 'Pcs', 49990.00, '3 August 2026', '5 August 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(517, 83, '3145', 'Eguia, Dasol, Pangasinan', 'VPSGM000058', 'Devant Smart TV 43 inches', '19195', 'PAID', 1, 'Pcs', 19195.00, '3 August 2026', '5 August 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(521, 85, '3145', 'Hermosa, Dasol, Pangasinan', 'VPSGM000060', '5 meter extention wire', ' 550', 'CREDIT', 1, 'PCS', 550.00, '3 August 2026', '3 August 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(522, 85, '3145', 'Hermosa, Dasol, Pangasinan', 'VPSGM000060', 'Epson Ink 003 Black', ' 280', 'CREDIT', 5, 'PCS', 1400.00, '3 August 2026', '3 August 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(523, 85, '3145', 'Hermosa, Dasol, Pangasinan', 'VPSGM000060', 'A4 Coupon', ' 230', 'CREDIT', 30, 'REAMS', 6900.00, '3 August 2026', '3 August 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(524, 86, '3145', 'Eguia, Dasol, Panhasinan', 'VPSGM000061', 'Picture frame', '250', 'PENDING', 7, 'Pcs', 1750.00, '3 August 2026', '5 August 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(525, 86, '3145', 'Eguia, Dasol, Panhasinan', 'VPSGM000061', 'Metal Detector (for gate security?', '1820', 'PENDING', 1, 'Pcs', 1820.00, '3 August 2026', '5 August 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=', NULL),
(526, 87, '3145', 'Eguia, Dasol, Pangasinan', 'VPSGM000062', 'Brown folder A4', '900', 'PENDING', 1, 'Ream', 900.00, '13 August 2026', '14 August 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000062', NULL),
(527, 87, '3145', 'Eguia, Dasol, Pangasinan', 'VPSGM000062', 'Staples Wire #35', '75', 'PENDING', 3, 'Box', 225.00, '13 August 2026', '14 August 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000062', NULL),
(528, 87, '3145', 'Eguia, Dasol, Pangasinan', 'VPSGM000062', 'Bleach 1L', '35', 'PENDING', 3, 'Pcs', 105.00, '13 August 2026', '14 August 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000062', NULL),
(529, 87, '3145', 'Eguia, Dasol, Pangasinan', 'VPSGM000062', 'Safeguard 55 grams', '25', 'PENDING', 5, 'Pcs', 125.00, '13 August 2026', '14 August 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000062', NULL),
(530, 87, '3145', 'Eguia, Dasol, Pangasinan', 'VPSGM000062', 'Battery Energizer AA', '70', 'PENDING', 20, 'Pcs', 1400.00, '13 August 2026', '14 August 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000062', NULL),
(531, 87, '3145', 'Eguia, Dasol, Pangasinan', 'VPSGM000062', 'Brother ink Yellow', '390', 'PENDING', 3, 'Pcs', 1170.00, '13 August 2026', '14 August 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000062', NULL),
(532, 87, '3145', 'Eguia, Dasol, Pangasinan', 'VPSGM000062', 'Brother ink Cyan', '390', 'PENDING', 3, 'Pcs', 1170.00, '13 August 2026', '14 August 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000062', NULL),
(533, 87, '3145', 'Eguia, Dasol, Pangasinan', 'VPSGM000062', 'Brother Ink Magenta', '390', 'PENDING', 3, 'Pcs', 1170.00, '13 August 2026', '14 August 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000062', NULL),
(534, 87, '3145', 'Eguia, Dasol, Pangasinan', 'VPSGM000062', 'Brother Ink Blk', '440', 'PENDING', 3, 'Pcs', 1320.00, '13 August 2026', '14 August 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000062', NULL),
(535, 87, '3145', 'Eguia, Dasol, Pangasinan', 'VPSGM000062', 'Epson Ink 003 Black', ' 280', 'PENDING', 2, 'Bottles', 560.00, '13 August 2026', '14 August 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000062', NULL),
(536, 87, '3145', 'Eguia, Dasol, Pangasinan', 'VPSGM000062', 'Epson Ink 003 Cyan', ' 290', 'PENDING', 2, 'Bottles', 580.00, '13 August 2026', '14 August 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000062', NULL),
(537, 87, '3145', 'Eguia, Dasol, Pangasinan', 'VPSGM000062', 'Epson Ink 003 Magenta', ' 290', 'PENDING', 2, 'Bottles', 580.00, '13 August 2026', '14 August 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000062', NULL),
(538, 87, '3145', 'Eguia, Dasol, Pangasinan', 'VPSGM000062', 'Epson Ink 003 Yellow', ' 290', 'PENDING', 2, 'Bottles', 580.00, '13 August 2026', '14 August 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000062', NULL),
(539, 87, '3145', 'Eguia, Dasol, Pangasinan', 'VPSGM000062', 'Picture frame', '250', 'PENDING', 7, 'Pcs', 1750.00, '13 August 2026', '14 August 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000062', NULL),
(540, 87, '3145', 'Eguia, Dasol, Pangasinan', 'VPSGM000062', 'Metal Detector (for gate security?', '1820', 'PENDING', 1, 'Pcs', 1820.00, '13 August 2026', '14 August 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000062', NULL),
(541, 87, '3145', 'Eguia, Dasol, Pangasinan', 'VPSGM000062', 'Legal Coupon', ' 240', 'PENDING', 15, 'Reams', 3600.00, '13 August 2026', '14 August 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000062', NULL),
(542, 87, '3145', 'Eguia, Dasol, Pangasinan', 'VPSGM000062', 'Coupon A4', '230', 'PENDING', 15, 'reams', 3450.00, '13 August 2026', '14 August 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000062', NULL),
(547, 89, '8156', 'DON JUAN BERNAL SR. E.S', 'VPSGM000063', 'Volleyball original (Mikasa)', '5990', 'PENDING', 2, 'Pcs', 11980.00, '15 August 2026', '17 August 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000063', NULL),
(548, 89, '8156', 'DON JUAN BERNAL SR. E.S', 'VPSGM000063', 'Basketball GG7X (Molten)', '3500', 'PENDING', 1, 'Pcs', 3500.00, '15 August 2026', '17 August 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000063', NULL),
(549, 89, '8156', 'DON JUAN BERNAL SR. E.S', 'VPSGM000063', 'Shuttlecock (dunlop)', '1900', 'PENDING', 3, 'Tube(12pcs)', 5700.00, '15 August 2026', '17 August 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000063', NULL),
(550, 89, '8156', 'DON JUAN BERNAL SR. E.S', 'VPSGM000063', 'Arnis stick (rattan) pair', '250', 'PENDING', 6, 'Pcs', 1500.00, '15 August 2026', '17 August 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000063', NULL),
(553, 92, '3145', 'Eguia, Elementary School', 'VPSGM000064', 'CCTV Package 2 Cameras: 8 x 2.0MP Wide Angle', '28990', 'PENDING', 1, 'Package', 28990.00, '18 August 2026', '19 August 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000064', NULL),
(554, 85, '8156', 'Hermosa, Dasol, Pangasinan', 'VPSGM000060', 'A4 Folder White', ' 8.50', 'PENDING', 100, 'PCS', 850.00, 'Tue, 18 Aug 2026 1:53 AM', NULL, 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000060', NULL),
(555, 93, '4819', 'Barlo, Mabini, Pangasinan', 'VPSGM000065', 'Epson L3210 Printer', '12000', 'PENDING', 1, 'Unit', 12000.00, '20 August 2026', '24 August 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000065', NULL),
(556, 94, '4819', 'Barlo, Mabini, Pangasinan', 'VPSGM000066', 'Smoke Detector', ' 358', 'PENDING', 3, 'PCS', 1074.00, '20 August 2026', '24 August 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000066', NULL),
(557, 94, '4819', 'Barlo, Mabini, Pangasinan', 'VPSGM000066', 'Garden Hose 100 ft', ' 3850', 'PENDING', 1, 'PCS', 3850.00, '20 August 2026', '24 August 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000066', NULL),
(558, 94, '4819', 'Barlo, Mabini, Pangasinan', 'VPSGM000066', 'Gloves', ' 399', 'PENDING', 1, 'PCS', 399.00, '20 August 2026', '24 August 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000066', NULL),
(559, 94, '4819', 'Barlo, Mabini, Pangasinan', 'VPSGM000066', 'Zonrox Sparkle 900ml', ' 180', 'PENDING', 2, 'PCS', 360.00, '20 August 2026', '24 August 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000066', NULL),
(560, 94, '4819', 'Barlo, Mabini, Pangasinan', 'VPSGM000066', 'Boots (1 pair size 7)', ' 600', 'PENDING', 1, 'PCS', 600.00, '20 August 2026', '24 August 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000066', NULL),
(563, 97, '0466', 'Purok 5 Cristoval Street, Poblacion, Near Nonar Store', 'VPSGM000067', 'Dahua IP Camera 2.0mp Wide Angle', '2500', 'PENDING', 1, 'Pcs', 2500.00, '22 August 2026', '25 August 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000067', NULL),
(564, 98, '0466', 'Purok 5 Cristoval Street, Poblacion, Near Nonar Store', 'VPSGM000068', 'Gloves', '399', 'PENDING', 1, 'Pcs', 399.00, '22 August 2026', '25 August 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000068', NULL),
(565, 99, '4266', 'Barlo Millsite, Barlo, Surod ES', 'VPSGM000069', 'Coupon A4', ' 230', 'PENDING', 13, 'PCS', 2990.00, '23 August 2026', '24 August 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000069', NULL),
(566, 99, '4266', 'Barlo Millsite, Barlo, Surod ES', 'VPSGM000069', 'Legal Coupon', ' 240', 'PENDING', 4, 'PCS', 960.00, '23 August 2026', '24 August 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000069', NULL),
(567, 99, '4266', 'Barlo Millsite, Barlo, Surod ES', 'VPSGM000069', 'Epson Ink 664 set', ' 1150', 'PENDING', 1, 'PCS', 1150.00, '23 August 2026', '24 August 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000069', NULL),
(568, 99, '4266', 'Barlo Millsite, Barlo, Surod ES', 'VPSGM000069', 'Epson Ink 664 Black', ' 280', 'PENDING', 1, 'PCS', 280.00, '23 August 2026', '24 August 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000069', NULL),
(569, 99, '4266', 'Barlo Millsite, Barlo, Surod ES', 'VPSGM000069', 'Expanded Folder Long Green', ' 25', 'PENDING', 4, 'PCS', 100.00, '23 August 2026', '24 August 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000069', NULL),
(570, 99, '4266', 'Barlo Millsite, Barlo, Surod ES', 'VPSGM000069', 'Green folder green', ' 20', 'PENDING', 4, 'PCS', 80.00, '23 August 2026', '24 August 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000069', NULL),
(571, 99, '4266', 'Barlo Millsite, Barlo, Surod ES', 'VPSGM000069', 'HBW Stapler', ' 185', 'PENDING', 1, 'PCS', 185.00, '23 August 2026', '24 August 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000069', NULL),
(572, 99, '4266', 'Barlo Millsite, Barlo, Surod ES', 'VPSGM000069', 'Parchment paper', ' 45', 'PENDING', 1, 'PCS', 45.00, '23 August 2026', '24 August 2026', 'https://villaruz-print-shop-and-general-merchandise.shop/delivery_receipt.php?delivery_number=VPSGM000069', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `warehouse`
--

CREATE TABLE `warehouse` (
  `id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `unit` varchar(100) DEFAULT NULL,
  `qty_on_hand` int(11) NOT NULL DEFAULT 0,
  `selling_price` int(11) NOT NULL,
  `last_restocked` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `chat_account`
--
ALTER TABLE `chat_account`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `acc_number` (`acc_number`),
  ADD KEY `idx_acc_number` (`acc_number`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `chat_conversation`
--
ALTER TABLE `chat_conversation`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_acc_number` (`acc_number`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_date` (`date`),
  ADD KEY `idx_sender` (`sender_type`);

--
-- Indexes for table `contracts`
--
ALTER TABLE `contracts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `for_deliveries`
--
ALTER TABLE `for_deliveries`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `location`
--
ALTER TABLE `location`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `logs`
--
ALTER TABLE `logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `merchandise_inventory`
--
ALTER TABLE `merchandise_inventory`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `order_status_history`
--
ALTER TABLE `order_status_history`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `warehouse`
--
ALTER TABLE `warehouse`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=642;

--
-- AUTO_INCREMENT for table `chat_account`
--
ALTER TABLE `chat_account`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `chat_conversation`
--
ALTER TABLE `chat_conversation`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `contracts`
--
ALTER TABLE `contracts`
  MODIFY `id` int(255) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `for_deliveries`
--
ALTER TABLE `for_deliveries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=100;

--
-- AUTO_INCREMENT for table `location`
--
ALTER TABLE `location`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT for table `logs`
--
ALTER TABLE `logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=170;

--
-- AUTO_INCREMENT for table `merchandise_inventory`
--
ALTER TABLE `merchandise_inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=375;

--
-- AUTO_INCREMENT for table `order_status_history`
--
ALTER TABLE `order_status_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=573;

--
-- AUTO_INCREMENT for table `warehouse`
--
ALTER TABLE `warehouse`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=140;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
