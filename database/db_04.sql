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
-- Database: `db_04`
--

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` bigint NOT NULL,
  `employee_name` varchar(100) DEFAULT NULL,
  `department` varchar(50) DEFAULT NULL,
  `salary` decimal(10,2) DEFAULT NULL,
  `joining_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `employee_name`, `department`, `salary`, `joining_date`) VALUES
(1, 'Hasib Khan', 'IT', 50000.00, '2024-01-15'),
(2, 'Tania Akter', 'HR', 42000.00, '2023-11-10'),
(3, 'Rakib Hossain', 'Accounts', 45000.00, '2022-07-01'),
(4, 'Shila Rahman', 'Marketing', 47000.00, '2024-03-20'),
(5, 'Nayeem Islam', 'IT', 55000.00, '2025-01-05');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
