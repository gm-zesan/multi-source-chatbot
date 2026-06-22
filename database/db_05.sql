-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Jun 22, 2026 at 11:35 AM
-- Server version: 9.5.0
-- PHP Version: 8.2.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_05`
--

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` bigint NOT NULL,
  `company_name` varchar(100) DEFAULT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `company_name`, `contact_person`, `phone`, `city`) VALUES
(1, 'TechSource Ltd', 'Aminul Islam', '01777777777', 'Dhaka'),
(2, 'Global Supply', 'Mizanur Rahman', '01888888888', 'Chattogram'),
(3, 'Office World', 'Sharmin Akter', '01999999999', 'Sylhet'),
(4, 'Smart Traders', 'Jahid Hasan', '01666666666', 'Khulna'),
(5, 'BD Distributors', 'Nafisa Rahman', '01512345678', 'Dhaka');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
