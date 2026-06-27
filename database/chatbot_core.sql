-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 27, 2026 at 09:51 AM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `chatbot_core`
--

-- --------------------------------------------------------

--
-- Table structure for table `cache`
--

CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cache_locks`
--

CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chat_logs`
--

CREATE TABLE `chat_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `query` text NOT NULL,
  `intent` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`intent`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `chat_logs`
--

INSERT INTO `chat_logs` (`id`, `query`, `intent`, `created_at`, `updated_at`) VALUES
(1, 'show customer', '{\"table\": \"customers\", \"action\": \"select\", \"source\": \"db_01\"}', '2026-06-21 22:54:40', '2026-06-21 22:54:40'),
(2, 'show customers', '{\"table\": \"customers\", \"action\": \"select\", \"source\": \"db_01\"}', '2026-06-21 22:55:49', '2026-06-21 22:55:49'),
(3, 'show customer', '{\"table\": \"customers\", \"action\": \"select\", \"source\": \"db_01\"}', '2026-06-21 22:55:51', '2026-06-21 22:55:51'),
(4, 'show client', '{\"table\": null, \"action\": \"select\", \"source\": \"db_01\"}', '2026-06-21 22:55:55', '2026-06-21 22:55:55'),
(5, 'show client', '{\"table\": \"customers\", \"action\": \"select\", \"source\": \"db_01\"}', '2026-06-21 23:05:27', '2026-06-21 23:05:27'),
(6, 'show clients', '{\"table\": \"customers\", \"action\": \"select\", \"source\": \"db_01\"}', '2026-06-21 23:05:30', '2026-06-21 23:05:30'),
(7, 'show top 10 customers', '{\"sort\": null, \"limit\": 10, \"table\": \"customers\", \"action\": \"select\", \"source\": \"db_01\", \"columns\": [\"*\"], \"filters\": []}', '2026-06-21 23:42:49', '2026-06-21 23:42:49'),
(8, 'show top 10 customers', '{\"sort\": null, \"limit\": 10, \"table\": \"customers\", \"action\": \"select\", \"source\": \"db_01\", \"columns\": [\"*\"], \"filters\": []}', '2026-06-21 23:42:56', '2026-06-21 23:42:56'),
(9, 'show top 10 customers', '{\"sort\": null, \"limit\": 10, \"table\": \"customers\", \"action\": \"select\", \"source\": \"db_01\", \"columns\": [\"*\"], \"filters\": []}', '2026-06-22 02:24:43', '2026-06-22 02:24:43'),
(10, 'show top 2 customers', '{\"sort\": null, \"limit\": 2, \"table\": \"customers\", \"action\": \"select\", \"source\": \"db_01\", \"columns\": [\"*\"], \"filters\": []}', '2026-06-22 02:24:51', '2026-06-22 02:24:51'),
(11, 'show limit 2 customers', '{\"sort\": null, \"limit\": 2, \"table\": \"customers\", \"action\": \"select\", \"source\": \"db_01\", \"columns\": [\"*\"], \"filters\": []}', '2026-06-22 02:25:02', '2026-06-22 02:25:02'),
(12, 'show email column', '{\"sort\": null, \"limit\": null, \"table\": null, \"action\": \"select\", \"source\": \"db_01\", \"columns\": [\"email\"], \"filters\": []}', '2026-06-22 02:25:15', '2026-06-22 02:25:15'),
(13, 'show email', '{\"sort\": null, \"limit\": null, \"table\": null, \"action\": \"select\", \"source\": \"db_01\", \"columns\": [\"email\"], \"filters\": []}', '2026-06-22 02:25:30', '2026-06-22 02:25:30'),
(14, 'show clients email', '{\"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": \"db_01\", \"columns\": [\"email\"], \"filters\": []}', '2026-06-22 02:25:39', '2026-06-22 02:25:39'),
(15, 'show clients email limit 2', '{\"sort\": null, \"limit\": 2, \"table\": \"customers\", \"action\": \"select\", \"source\": \"db_01\", \"columns\": [\"email\"], \"filters\": []}', '2026-06-22 02:26:03', '2026-06-22 02:26:03'),
(16, 'show top 10 customers', '{\"sort\": null, \"limit\": 10, \"table\": \"customers\", \"action\": \"select\", \"source\": \"db_01\", \"columns\": [\"*\"], \"filters\": []}', '2026-06-22 02:26:15', '2026-06-22 02:26:15'),
(17, 'show customer name,email', '{\"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": \"db_01\", \"columns\": [\"name\", \"email\"], \"filters\": []}', '2026-06-22 02:30:43', '2026-06-22 02:30:43'),
(18, 'show customer name and email', '{\"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": \"db_01\", \"columns\": [\"name\", \"email\"], \"filters\": []}', '2026-06-22 02:30:48', '2026-06-22 02:30:48'),
(19, 'show customer name email', '{\"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": \"db_01\", \"columns\": [\"name\", \"email\"], \"filters\": []}', '2026-06-22 02:30:51', '2026-06-22 02:30:51'),
(20, 'show customer name phone email', '{\"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": \"db_01\", \"columns\": [\"name\", \"email\", \"phone\"], \"filters\": []}', '2026-06-22 02:30:55', '2026-06-22 02:30:55'),
(21, 'show customers order by id desc', '{\"sort\": {\"column\": \"id\", \"direction\": \"desc\"}, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": \"db_01\", \"columns\": [\"id\"], \"filters\": []}', '2026-06-22 02:39:05', '2026-06-22 02:39:05'),
(22, 'show customers order by name desc', '{\"sort\": {\"column\": \"name\", \"direction\": \"desc\"}, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": \"db_01\", \"columns\": [\"name\"], \"filters\": []}', '2026-06-22 02:39:09', '2026-06-22 02:39:09'),
(23, 'show customers order by name asc', '{\"sort\": {\"column\": \"name\", \"direction\": \"asc\"}, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": \"db_01\", \"columns\": [\"name\"], \"filters\": []}', '2026-06-22 02:39:20', '2026-06-22 02:39:20'),
(24, 'show clients', '{\"sort\": null, \"limit\": null, \"table\": null, \"action\": \"select\", \"source\": null, \"columns\": [\"*\"], \"filters\": []}', '2026-06-22 03:34:48', '2026-06-22 03:34:48'),
(25, 'show client', '{\"sort\": null, \"limit\": null, \"table\": null, \"action\": \"select\", \"source\": null, \"columns\": [\"*\"], \"filters\": []}', '2026-06-22 03:34:53', '2026-06-22 03:34:53'),
(26, 'show customer', '{\"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": null, \"columns\": [\"*\"], \"filters\": []}', '2026-06-22 03:34:57', '2026-06-22 03:34:57'),
(27, 'show customers', '{\"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": null, \"columns\": [\"*\"], \"filters\": []}', '2026-06-22 03:34:59', '2026-06-22 03:34:59'),
(28, 'show customers phone', '{\"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": null, \"columns\": [\"*\"], \"filters\": []}', '2026-06-22 03:45:09', '2026-06-22 03:45:09'),
(29, 'show customers email', '{\"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": null, \"columns\": [\"*\"], \"filters\": []}', '2026-06-22 03:45:15', '2026-06-22 03:45:15'),
(30, 'show customers email', '{\"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": null, \"columns\": [\"*\"], \"filters\": []}', '2026-06-22 03:45:33', '2026-06-22 03:45:33'),
(31, 'show customers email', '{\"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": null, \"columns\": [\"*\"], \"filters\": []}', '2026-06-22 03:48:32', '2026-06-22 03:48:32'),
(32, 'show customer', '{\"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": null, \"columns\": [\"*\"], \"filters\": []}', '2026-06-22 03:48:38', '2026-06-22 03:48:38'),
(33, 'show client', '{\"sort\": null, \"limit\": null, \"table\": null, \"action\": \"select\", \"source\": null, \"columns\": [\"*\"], \"filters\": []}', '2026-06-22 03:48:42', '2026-06-22 03:48:42'),
(34, 'show client clients', '{\"sort\": null, \"limit\": null, \"table\": null, \"action\": \"select\", \"source\": null, \"columns\": [\"*\"], \"filters\": []}', '2026-06-22 03:48:49', '2026-06-22 03:48:49'),
(35, 'show top 2 customers', '{\"sort\": null, \"limit\": 2, \"table\": \"customers\", \"action\": \"select\", \"source\": null, \"columns\": [\"*\"], \"filters\": []}', '2026-06-22 03:49:55', '2026-06-22 03:49:55'),
(36, 'show top 2 customer', '{\"sort\": null, \"limit\": 2, \"table\": \"customers\", \"action\": \"select\", \"source\": null, \"columns\": [\"*\"], \"filters\": []}', '2026-06-22 03:49:57', '2026-06-22 03:49:57'),
(37, 'show top 1 customer', '{\"sort\": null, \"limit\": 1, \"table\": \"customers\", \"action\": \"select\", \"source\": null, \"columns\": [\"*\"], \"filters\": []}', '2026-06-22 03:50:01', '2026-06-22 03:50:01'),
(38, 'show top 5 customer', '{\"sort\": null, \"limit\": 5, \"table\": \"customers\", \"action\": \"select\", \"source\": null, \"columns\": [\"*\"], \"filters\": []}', '2026-06-22 03:50:05', '2026-06-22 03:50:05'),
(39, 'show top 7 customer', '{\"sort\": null, \"limit\": 7, \"table\": \"customers\", \"action\": \"select\", \"source\": null, \"columns\": [\"*\"], \"filters\": []}', '2026-06-22 03:50:07', '2026-06-22 03:50:07'),
(40, 'show limit 3 customer', '{\"sort\": null, \"limit\": 3, \"table\": \"customers\", \"action\": \"select\", \"source\": null, \"columns\": [\"*\"], \"filters\": []}', '2026-06-22 03:50:51', '2026-06-22 03:50:51'),
(41, 'show customer where id 1', '{\"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": null, \"columns\": [\"*\"], \"filters\": []}', '2026-06-22 03:51:03', '2026-06-22 03:51:03'),
(42, 'show customer where name mim akter', '{\"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": null, \"columns\": [\"*\"], \"filters\": []}', '2026-06-22 03:51:13', '2026-06-22 03:51:13'),
(43, 'show customer where name \"mim akter\"', '{\"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": null, \"columns\": [\"*\"], \"filters\": []}', '2026-06-22 03:51:18', '2026-06-22 03:51:18'),
(44, 'show customer name', '{\"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": null, \"columns\": [\"name\"], \"filters\": []}', '2026-06-22 03:55:32', '2026-06-22 03:55:32'),
(45, 'show customer email', '{\"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": null, \"columns\": [\"email\"], \"filters\": []}', '2026-06-22 03:55:36', '2026-06-22 03:55:36'),
(46, 'show customer email name', '{\"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": null, \"columns\": [\"email\", \"name\"], \"filters\": []}', '2026-06-22 03:55:38', '2026-06-22 03:55:38'),
(47, 'show customer email name phone', '{\"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": null, \"columns\": [\"email\", \"name\", \"phone\"], \"filters\": []}', '2026-06-22 03:55:45', '2026-06-22 03:55:45'),
(48, 'show customer city', '{\"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": null, \"columns\": [\"city\"], \"filters\": []}', '2026-06-22 03:55:49', '2026-06-22 03:55:49'),
(49, 'show customer city limit 2', '{\"sort\": null, \"limit\": 2, \"table\": \"customers\", \"action\": \"select\", \"source\": null, \"columns\": [\"city\"], \"filters\": []}', '2026-06-22 03:55:53', '2026-06-22 03:55:53'),
(50, 'show customer mobile', '{\"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": null, \"columns\": [\"mobile\"], \"filters\": []}', '2026-06-22 03:56:25', '2026-06-22 03:56:25'),
(51, 'show clients', '{\"sort\": null, \"limit\": null, \"table\": null, \"action\": \"select\", \"source\": null, \"columns\": [\"clients\"], \"filters\": []}', '2026-06-22 03:56:33', '2026-06-22 03:56:33'),
(52, 'show clients', '{\"raw\": \"show clients\", \"sort\": null, \"limit\": null, \"table\": null, \"action\": \"select\", \"source\": null, \"tokens\": [\"show\", \"clients\"], \"columns\": [\"clients\"], \"filters\": []}', '2026-06-22 03:58:54', '2026-06-22 03:58:54'),
(53, 'show clients', '{\"raw\": \"show clients\", \"sort\": null, \"limit\": null, \"table\": null, \"action\": \"select\", \"source\": null, \"tokens\": [\"show\", \"clients\"], \"columns\": [\"clients\"], \"filters\": []}', '2026-06-22 03:59:01', '2026-06-22 03:59:01'),
(54, 'show client', '{\"raw\": \"show client\", \"sort\": null, \"limit\": null, \"table\": null, \"action\": \"select\", \"source\": null, \"tokens\": [\"show\", \"client\"], \"columns\": [\"client\"], \"filters\": []}', '2026-06-22 03:59:03', '2026-06-22 03:59:03'),
(55, 'show customer where name \"mim akter\"', '{\"raw\": \"show customer where name \\\"mim akter\\\"\", \"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": null, \"tokens\": [\"show\", \"customer\", \"where\", \"name\", \"mim\", \"akter\"], \"columns\": [\"name\", \"mim\", \"akter\"], \"filters\": []}', '2026-06-22 03:59:23', '2026-06-22 03:59:23'),
(56, 'show customer where id order by desc', '{\"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": null, \"columns\": [\"id\"], \"filters\": []}', '2026-06-22 04:00:15', '2026-06-22 04:00:15'),
(57, 'show customer where id order by asc', '{\"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": null, \"columns\": [\"id\"], \"filters\": []}', '2026-06-22 04:00:18', '2026-06-22 04:00:18'),
(58, 'show customer where id order by dsc', '{\"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": null, \"columns\": [\"id\", \"dsc\"], \"filters\": []}', '2026-06-22 04:00:22', '2026-06-22 04:00:22'),
(59, 'show customer where id order by des', '{\"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": null, \"columns\": [\"id\", \"des\"], \"filters\": [{\"value\": \"order\", \"column\": \"id\", \"operator\": \"=\"}]}', '2026-06-22 04:00:25', '2026-06-22 04:00:25'),
(60, 'show customer where id order by desc', '{\"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": null, \"columns\": [\"id\"], \"filters\": [{\"value\": \"order\", \"column\": \"id\", \"operator\": \"=\"}]}', '2026-06-22 04:00:27', '2026-06-22 04:00:27'),
(61, 'show customer where id = 1', '{\"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": null, \"columns\": [\"id\"], \"filters\": [{\"value\": \"\", \"column\": \"id\", \"operator\": \"=\"}]}', '2026-06-22 04:01:38', '2026-06-22 04:01:38'),
(62, 'show customer where name mim', '{\"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": null, \"columns\": [\"name\", \"mim\"], \"filters\": [{\"value\": \"mim\", \"column\": \"name\", \"operator\": \"=\"}]}', '2026-06-22 04:01:49', '2026-06-22 04:01:49'),
(63, 'show customer where name \"mim akter\"', '{\"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": null, \"columns\": [\"name\", \"mim\", \"akter\"], \"filters\": [{\"value\": \"mim akter\", \"column\": \"name\", \"operator\": \"=\"}]}', '2026-06-22 04:01:56', '2026-06-22 04:01:56'),
(64, 'show customer where id = 1', '{\"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": null, \"columns\": [\"id\"], \"filters\": [{\"value\": \"1\", \"column\": \"id\", \"operator\": \"=\"}]}', '2026-06-22 04:05:16', '2026-06-22 04:05:16'),
(65, 'show customer where name mim akter', '{\"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": null, \"columns\": [\"name\", \"mim\", \"akter\"], \"filters\": []}', '2026-06-22 04:05:23', '2026-06-22 04:05:23'),
(66, 'show customer where name=mim akter', '{\"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": null, \"columns\": [\"name\", \"mim\", \"akter\"], \"filters\": [{\"value\": \"mim\", \"column\": \"name\", \"operator\": \"=\"}]}', '2026-06-22 04:05:28', '2026-06-22 04:05:28'),
(67, 'show customer where name = mim akter', '{\"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": null, \"columns\": [\"name\", \"mim\", \"akter\"], \"filters\": [{\"value\": \"mim\", \"column\": \"name\", \"operator\": \"=\"}]}', '2026-06-22 04:05:31', '2026-06-22 04:05:31'),
(68, 'show customer where name = \"mim akter\"', '{\"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": null, \"columns\": [\"name\", \"mim\", \"akter\"], \"filters\": []}', '2026-06-22 04:05:36', '2026-06-22 04:05:36'),
(69, 'show customer where name = \"Mim Akter\"', '{\"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": null, \"columns\": [\"name\", \"mim\", \"akter\"], \"filters\": []}', '2026-06-22 04:06:18', '2026-06-22 04:06:18'),
(70, 'show customer where name = \"Mim Akter\"', '{\"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": null, \"columns\": [\"name\", \"mim\", \"akter\"], \"filters\": [{\"value\": \"mim akter\", \"column\": \"name\", \"operator\": \"=\"}, {\"value\": \"=\", \"column\": \"name\", \"operator\": \"=\"}]}', '2026-06-22 04:08:42', '2026-06-22 04:08:42'),
(71, 'show customer where name = \"Mim Akter\"', '{\"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": null, \"columns\": [\"name\", \"mim\", \"akter\"], \"filters\": [{\"value\": \"mim akter\", \"column\": \"name\", \"operator\": \"=\"}, {\"value\": \"=\", \"column\": \"name\", \"operator\": \"=\"}]}', '2026-06-22 04:08:44', '2026-06-22 04:08:44'),
(72, 'show customer where name = \"Mim Akter\"', '{\"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": null, \"columns\": [\"name\", \"mim\", \"akter\"], \"filters\": [{\"value\": \"mim akter\", \"column\": \"name\", \"operator\": \"=\"}, {\"value\": \"=\", \"column\": \"name\", \"operator\": \"=\"}]}', '2026-06-22 04:09:00', '2026-06-22 04:09:00'),
(73, 'show customer name', '{\"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": null, \"columns\": [\"name\"], \"filters\": []}', '2026-06-22 04:09:25', '2026-06-22 04:09:25'),
(74, 'show customer name', '{\"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": null, \"columns\": [\"name\"], \"filters\": []}', '2026-06-22 04:11:02', '2026-06-22 04:11:02'),
(75, 'show client', '{\"sort\": null, \"limit\": null, \"table\": null, \"action\": \"select\", \"source\": null, \"columns\": [\"client\"], \"filters\": []}', '2026-06-22 04:11:16', '2026-06-22 04:11:16'),
(76, 'show client', '{\"sort\": null, \"limit\": null, \"table\": null, \"action\": \"select\", \"source\": null, \"columns\": [\"client\"], \"filters\": []}', '2026-06-22 04:13:08', '2026-06-22 04:13:08'),
(77, 'show client', '{\"sort\": null, \"limit\": null, \"table\": null, \"action\": \"select\", \"source\": null, \"columns\": [\"client\"], \"filters\": []}', '2026-06-22 04:15:30', '2026-06-22 04:15:30'),
(78, 'show client', '{\"sort\": null, \"limit\": null, \"table\": null, \"action\": \"select\", \"source\": null, \"columns\": [\"client\"], \"filters\": []}', '2026-06-22 04:15:30', '2026-06-22 04:15:30'),
(79, 'show client', '{\"sort\": null, \"limit\": null, \"table\": null, \"action\": \"select\", \"source\": null, \"columns\": [\"client\"], \"filters\": []}', '2026-06-22 04:15:30', '2026-06-22 04:15:30'),
(80, 'show client', '{\"sort\": null, \"limit\": null, \"table\": null, \"action\": \"select\", \"source\": null, \"columns\": [\"client\"], \"filters\": []}', '2026-06-22 04:15:30', '2026-06-22 04:15:30'),
(81, 'show client', '{\"sort\": null, \"limit\": null, \"table\": null, \"action\": \"select\", \"source\": null, \"columns\": [\"client\"], \"filters\": []}', '2026-06-22 04:15:30', '2026-06-22 04:15:30'),
(82, 'show client', '{\"sort\": null, \"limit\": null, \"table\": null, \"action\": \"select\", \"source\": null, \"columns\": [\"client\"], \"filters\": []}', '2026-06-22 04:15:31', '2026-06-22 04:15:31'),
(83, 'show client', '{\"sort\": null, \"limit\": null, \"table\": null, \"action\": \"select\", \"source\": null, \"columns\": [\"client\"], \"filters\": []}', '2026-06-22 04:15:31', '2026-06-22 04:15:31'),
(84, 'show customer', '{\"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": null, \"columns\": [\"*\"], \"filters\": []}', '2026-06-22 04:51:40', '2026-06-22 04:51:40'),
(85, 'show customer name', '{\"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": null, \"columns\": [\"name\"], \"filters\": []}', '2026-06-22 04:51:42', '2026-06-22 04:51:42'),
(86, 'show client', '{\"sort\": null, \"limit\": null, \"table\": null, \"action\": \"select\", \"source\": null, \"columns\": [\"*\"], \"filters\": []}', '2026-06-22 04:51:50', '2026-06-22 04:51:50'),
(87, 'show client', '{\"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": \"db_01\", \"columns\": [\"*\"], \"filters\": []}', '2026-06-22 04:53:25', '2026-06-22 04:53:25'),
(88, 'show clients', '{\"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": \"db_01\", \"columns\": [\"*\"], \"filters\": []}', '2026-06-22 04:53:28', '2026-06-22 04:53:28'),
(89, 'show clients', '{\"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": \"db_01\", \"columns\": [\"*\"], \"filters\": []}', '2026-06-22 04:55:20', '2026-06-22 04:55:20'),
(90, 'show clients', '{\"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": \"db_01\", \"columns\": [\"*\"], \"filters\": []}', '2026-06-22 04:55:21', '2026-06-22 04:55:21'),
(91, 'show clients', '{\"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": \"db_01\", \"columns\": [\"*\"], \"filters\": []}', '2026-06-22 04:55:21', '2026-06-22 04:55:21'),
(92, 'show clients', '{\"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": \"db_01\", \"columns\": [\"*\"], \"filters\": []}', '2026-06-22 04:55:21', '2026-06-22 04:55:21'),
(93, 'show clients', '{\"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": \"db_01\", \"columns\": [\"*\"], \"filters\": []}', '2026-06-22 04:55:21', '2026-06-22 04:55:21'),
(94, 'show client where name = \"mim akter\"', '{\"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": \"db_01\", \"columns\": [\"name\"], \"filters\": []}', '2026-06-22 04:55:38', '2026-06-22 04:55:38'),
(95, 'show client where id =1', '{\"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": \"db_01\", \"columns\": [\"id\"], \"filters\": [{\"value\": \"1\", \"column\": \"id\", \"operator\": \"=\"}]}', '2026-06-22 04:55:45', '2026-06-22 04:55:45'),
(96, 'show client', '{\"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": \"db_01\", \"columns\": [\"*\"], \"filters\": []}', '2026-06-22 04:55:50', '2026-06-22 04:55:50'),
(97, 'show client where city = \"dhaka\"', '{\"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": \"db_01\", \"columns\": [\"city\"], \"filters\": []}', '2026-06-22 04:56:04', '2026-06-22 04:56:04'),
(98, 'show client where city = dhaka', '{\"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": \"db_01\", \"columns\": [\"city\"], \"filters\": [{\"value\": \"dhaka\", \"column\": \"city\", \"operator\": \"=\"}]}', '2026-06-22 04:56:54', '2026-06-22 04:56:54'),
(99, 'show client where name = mim akter', '{\"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": \"db_01\", \"columns\": [\"name\"], \"filters\": [{\"value\": \"mim\", \"column\": \"name\", \"operator\": \"=\"}]}', '2026-06-22 04:57:04', '2026-06-22 04:57:04'),
(100, 'show client where name = mimakter', '{\"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": \"db_01\", \"columns\": [\"name\"], \"filters\": [{\"value\": \"mimakter\", \"column\": \"name\", \"operator\": \"=\"}]}', '2026-06-22 04:57:09', '2026-06-22 04:57:09'),
(101, 'show client where name = mim', '{\"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": \"db_01\", \"columns\": [\"name\"], \"filters\": [{\"value\": \"mim\", \"column\": \"name\", \"operator\": \"=\"}]}', '2026-06-22 04:57:57', '2026-06-22 04:57:57'),
(102, 'show client where name = mim akter', '{\"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": \"db_01\", \"columns\": [\"name\"], \"filters\": [{\"value\": \"mim akter\", \"column\": \"name\", \"operator\": \"=\"}]}', '2026-06-22 04:59:39', '2026-06-22 04:59:39'),
(103, 'show client', '{\"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": \"db_01\", \"columns\": [\"*\"], \"filters\": []}', '2026-06-22 04:59:54', '2026-06-22 04:59:54'),
(104, 'show customer', '{\"action\":\"select\",\"table\":\"customers\",\"source\":\"db_01\",\"limit\":null,\"filters\":[],\"columns\":[\"*\"],\"sort\":null}', '2026-06-22 10:53:55', '2026-06-22 10:53:55'),
(105, 'show customer', '{\"action\":\"select\",\"table\":\"customers\",\"source\":\"db_01\",\"limit\":null,\"filters\":[],\"columns\":[\"*\"],\"sort\":null}', '2026-06-22 10:56:10', '2026-06-22 10:56:10'),
(106, 'show customer', '{\"action\":\"select\",\"table\":\"customers\",\"source\":\"db_01\",\"limit\":null,\"filters\":[],\"columns\":[\"*\"],\"sort\":null}', '2026-06-22 10:56:11', '2026-06-22 10:56:11'),
(107, 'show customers', '{\"action\":\"select\",\"table\":\"customers\",\"source\":\"db_01\",\"limit\":null,\"filters\":[],\"columns\":[\"*\"],\"sort\":null}', '2026-06-22 10:56:14', '2026-06-22 10:56:14'),
(108, 'show customers', '{\"action\":\"select\",\"table\":\"customers\",\"source\":\"db_01\",\"limit\":null,\"filters\":[],\"columns\":[\"*\"],\"sort\":null}', '2026-06-22 10:56:52', '2026-06-22 10:56:52'),
(109, 'show clients', '{\"action\":\"select\",\"table\":\"customers\",\"source\":\"db_01\",\"limit\":null,\"filters\":[],\"columns\":[\"*\"],\"sort\":null}', '2026-06-22 10:57:14', '2026-06-22 10:57:14'),
(110, 'show clients', '{\"action\":\"select\",\"table\":\"customers\",\"source\":\"db_01\",\"limit\":null,\"filters\":[],\"columns\":[\"*\"],\"sort\":null}', '2026-06-22 10:58:39', '2026-06-22 10:58:39'),
(111, 'show clients', '{\"action\":\"select\",\"table\":\"customers\",\"source\":\"db_01\",\"limit\":null,\"filters\":[],\"columns\":[\"*\"],\"sort\":null}', '2026-06-22 10:58:43', '2026-06-22 10:58:43'),
(112, 'show clients', '{\"action\":\"select\",\"table\":\"customers\",\"source\":\"db_01\",\"limit\":null,\"filters\":[],\"columns\":[\"*\"],\"sort\":null}', '2026-06-22 10:59:09', '2026-06-22 10:59:09'),
(113, 'show customer', '{\"action\":\"select\",\"table\":\"customers\",\"source\":\"db_01\",\"limit\":null,\"filters\":[],\"columns\":[\"*\"],\"sort\":null}', '2026-06-22 11:00:24', '2026-06-22 11:00:24'),
(114, 'show customers', '{\"action\":\"select\",\"table\":\"customers\",\"source\":\"db_01\",\"limit\":null,\"filters\":[],\"columns\":[\"*\"],\"sort\":null}', '2026-06-22 11:00:26', '2026-06-22 11:00:26'),
(115, 'show customers', '{\"action\":\"select\",\"table\":\"customers\",\"source\":\"db_01\",\"limit\":null,\"filters\":[],\"columns\":[\"*\"],\"sort\":null}', '2026-06-22 11:03:01', '2026-06-22 11:03:01'),
(116, 'show client', '{\"action\":\"select\",\"table\":\"customers\",\"source\":\"db_01\",\"limit\":null,\"filters\":[],\"columns\":[\"*\"],\"sort\":null}', '2026-06-22 11:04:27', '2026-06-22 11:04:27'),
(117, 'show all customer', '{\"action\":\"select\",\"table\":\"customers\",\"source\":\"db_01\",\"limit\":null,\"filters\":[],\"columns\":[\"*\"],\"sort\":null}', '2026-06-24 12:08:03', '2026-06-24 12:08:03'),
(118, 'show all client', '{\"action\":\"select\",\"table\":\"customers\",\"source\":\"db_01\",\"limit\":null,\"filters\":[],\"columns\":[\"*\"],\"sort\":null}', '2026-06-24 12:08:10', '2026-06-24 12:08:10'),
(119, 'show customer name,email', '{\"action\":\"select\",\"table\":\"customers\",\"source\":\"db_01\",\"limit\":null,\"filters\":[],\"columns\":[\"name\",\"email\"],\"sort\":null}', '2026-06-24 12:10:39', '2026-06-24 12:10:39'),
(120, 'show customer where id = 5', '{\"action\":\"select\",\"table\":\"customers\",\"source\":\"db_01\",\"limit\":null,\"filters\":[{\"column\":\"id\",\"operator\":\"=\",\"value\":\"5\"}],\"columns\":[\"id\"],\"sort\":null}', '2026-06-24 12:13:05', '2026-06-24 12:13:05'),
(121, 'show top 20 customer email order by id desc', '{\"action\":\"select\",\"table\":\"customers\",\"source\":\"db_01\",\"limit\":20,\"filters\":[],\"columns\":[\"id\",\"email\"],\"sort\":{\"column\":\"id\",\"direction\":\"desc\"}}', '2026-06-24 12:13:16', '2026-06-24 12:13:16'),
(122, 'show customers', '{\"action\":\"select\",\"table\":\"customers\",\"limit\":null,\"filters\":[],\"sort\":null}', '2026-06-24 12:47:10', '2026-06-24 12:47:10'),
(123, 'show sales', '{\"action\":\"select\",\"table\":null,\"limit\":null,\"filters\":[],\"sort\":null}', '2026-06-24 12:47:14', '2026-06-24 12:47:14'),
(124, 'show product', '{\"action\":\"select\",\"table\":null,\"limit\":null,\"filters\":[],\"sort\":null}', '2026-06-24 12:47:36', '2026-06-24 12:47:36'),
(125, 'show products', '{\"action\":\"select\",\"table\":null,\"limit\":null,\"filters\":[],\"sort\":null}', '2026-06-24 12:47:39', '2026-06-24 12:47:39'),
(126, 'show sales', '{\"action\":\"select\",\"table\":null,\"limit\":null,\"filters\":[],\"sort\":null}', '2026-06-24 12:51:46', '2026-06-24 12:51:46'),
(127, 'show product', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[\"*\"],\"limit\":null,\"filters\":[],\"sort\":null}', '2026-06-24 13:07:29', '2026-06-24 13:07:29'),
(128, 'show product', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[\"*\"],\"limit\":null,\"filters\":[],\"sort\":null}', '2026-06-24 13:08:43', '2026-06-24 13:08:43'),
(129, 'show products', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[\"*\"],\"limit\":null,\"filters\":[],\"sort\":null}', '2026-06-24 13:09:18', '2026-06-24 13:09:18'),
(130, 'show products', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[\"*\"],\"limit\":null,\"filters\":[],\"sort\":null}', '2026-06-24 13:11:05', '2026-06-24 13:11:05'),
(131, 'show product', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[\"*\"],\"limit\":null,\"filters\":[],\"sort\":null}', '2026-06-24 13:11:10', '2026-06-24 13:11:10'),
(132, 'show sale', '{\"action\":\"select\",\"table\":\"sales\",\"columns\":[\"*\"],\"limit\":null,\"filters\":[],\"sort\":null}', '2026-06-24 13:11:49', '2026-06-24 13:11:49'),
(133, 'show product where id = 5', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[\"id\"],\"limit\":null,\"filters\":[{\"column\":\"id\",\"operator\":\"=\",\"value\":\"5\"}],\"sort\":null}', '2026-06-24 13:12:14', '2026-06-24 13:12:14'),
(134, 'show product name', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[\"product_name\"],\"limit\":null,\"filters\":[],\"sort\":null}', '2026-06-24 13:15:43', '2026-06-24 13:15:43'),
(135, 'show product where id=5', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[\"*\"],\"limit\":null,\"filters\":[{\"column\":\"id\",\"operator\":\"=\",\"value\":\"5\"}],\"sort\":null}', '2026-06-24 13:17:18', '2026-06-24 13:17:18'),
(136, 'show product where name=Office Chair', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[\"name\"],\"limit\":null,\"filters\":[{\"column\":\"name\",\"operator\":\"=\",\"value\":\"office chair\"}],\"sort\":null}', '2026-06-24 13:17:38', '2026-06-24 13:17:38'),
(137, 'show product where name = Office Chair', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[\"name\"],\"limit\":null,\"filters\":[{\"column\":\"name\",\"operator\":\"=\",\"value\":\"office chair\"}],\"sort\":null}', '2026-06-24 13:17:44', '2026-06-24 13:17:44'),
(138, 'show customer where name = mim akter', '{\"action\":\"select\",\"table\":\"customers\",\"columns\":[\"name\"],\"limit\":null,\"filters\":[{\"column\":\"name\",\"operator\":\"=\",\"value\":\"mim akter\"}],\"sort\":null}', '2026-06-24 13:18:09', '2026-06-24 13:18:09'),
(139, 'show product where product name = Office Chair', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[\"product_name\"],\"limit\":null,\"filters\":[],\"sort\":null}', '2026-06-24 13:18:42', '2026-06-24 13:18:42'),
(140, 'show product where product_name = Office Chair', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[\"name\"],\"limit\":null,\"filters\":[{\"column\":\"product_name\",\"operator\":\"=\",\"value\":\"office chair\"}],\"sort\":null}', '2026-06-24 13:18:50', '2026-06-24 13:18:50'),
(141, 'show product where product_name = Office Chair', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[\"name\"],\"limit\":null,\"filters\":[{\"column\":\"product_name\",\"operator\":\"=\",\"value\":\"office chair\"}],\"sort\":null}', '2026-06-24 13:19:11', '2026-06-24 13:19:11'),
(142, 'show product where product_name = \"Office Chair\"', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[\"name\"],\"limit\":null,\"filters\":[{\"column\":\"product_name\",\"operator\":\"=\",\"value\":\"office chair\"}],\"sort\":null}', '2026-06-24 13:19:17', '2026-06-24 13:19:17'),
(143, 'show product where product_name = Office Chair', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[\"name\"],\"limit\":null,\"filters\":[{\"column\":\"product_name\",\"operator\":\"=\",\"value\":\"office chair\"}],\"sort\":null}', '2026-06-24 13:19:26', '2026-06-24 13:19:26'),
(144, 'show product where id = 2', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[\"*\"],\"limit\":null,\"filters\":[{\"column\":\"id\",\"operator\":\"=\",\"value\":\"2\"}],\"sort\":null}', '2026-06-24 13:19:35', '2026-06-24 13:19:35'),
(145, 'show product where product_name = Mouse', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[\"name\"],\"limit\":null,\"filters\":[{\"column\":\"product_name\",\"operator\":\"=\",\"value\":\"mouse\"}],\"sort\":null}', '2026-06-24 13:19:46', '2026-06-24 13:19:46'),
(146, 'show product where product_name = Mouse', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[\"*\"],\"limit\":null,\"filters\":[{\"column\":\"product_name\",\"operator\":\"=\",\"value\":\"mouse\"}],\"sort\":null}', '2026-06-24 13:29:12', '2026-06-24 13:29:12'),
(147, 'show product where product name = Mouse', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[\"*\"],\"limit\":null,\"filters\":[],\"sort\":null}', '2026-06-24 13:29:25', '2026-06-24 13:29:25'),
(148, 'show product where product_name = Mouse', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[\"*\"],\"limit\":null,\"filters\":[{\"column\":\"product_name\",\"operator\":\"=\",\"value\":\"mouse\"}],\"sort\":null}', '2026-06-24 13:29:42', '2026-06-24 13:29:42'),
(149, 'show product where category = Furniture', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[\"*\"],\"limit\":null,\"filters\":[{\"column\":\"category\",\"operator\":\"=\",\"value\":\"furniture\"}],\"sort\":null}', '2026-06-24 13:34:35', '2026-06-24 13:34:35'),
(150, 'show product where category = Furniture', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[\"category\"],\"limit\":null,\"filters\":[{\"column\":\"category\",\"operator\":\"=\",\"value\":\"furniture\"}],\"sort\":null}', '2026-06-24 13:34:53', '2026-06-24 13:34:53'),
(151, 'show product where category = Furniture', '{\"action\":\"select\",\"table\":null,\"columns\":[\"*\"],\"limit\":null,\"filters\":[{\"column\":\"category\",\"operator\":\"=\",\"value\":\"furniture\"}],\"sort\":null}', '2026-06-24 13:58:15', '2026-06-24 13:58:15'),
(152, 'show product where category = Furniture', '{\"action\":\"select\",\"table\":null,\"columns\":[\"*\"],\"limit\":null,\"filters\":[{\"column\":\"category\",\"operator\":\"=\",\"value\":\"furniture\"}],\"sort\":null}', '2026-06-24 13:58:16', '2026-06-24 13:58:16'),
(153, 'show product where category = Furniture', '{\"action\":\"select\",\"table\":null,\"columns\":[\"*\"],\"limit\":null,\"filters\":[{\"column\":\"category\",\"operator\":\"=\",\"value\":\"furniture\"}],\"sort\":null}', '2026-06-24 13:58:41', '2026-06-24 13:58:41'),
(154, 'show product where category = Furniture', '{\"action\":\"select\",\"table\":null,\"columns\":[\"*\"],\"limit\":null,\"filters\":[{\"column\":\"category\",\"operator\":\"=\",\"value\":\"furniture\"}],\"sort\":null}', '2026-06-24 13:58:42', '2026-06-24 13:58:42'),
(155, 'show product where category = Furniture', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[\"*\"],\"limit\":null,\"filters\":[{\"column\":\"category\",\"operator\":\"=\",\"value\":\"furniture\"}],\"sort\":null}', '2026-06-24 13:58:58', '2026-06-24 13:58:58'),
(156, 'show customer email and phone where city = dhaka and id > 5 order by id desc limit 10', '{\"action\":\"select\",\"table\":\"customers\",\"columns\":[\"id\",\"email\",\"phone\",\"city\"],\"limit\":10,\"filters\":[{\"column\":\"city\",\"operator\":\"=\",\"value\":\"dhaka\"},{\"column\":\"id\",\"operator\":\">\",\"value\":\"5\"}],\"sort\":{\"column\":\"id\",\"direction\":\"desc\"},\"confidence\":50}', '2026-06-24 14:07:28', '2026-06-24 14:07:28'),
(157, 'show customer email and phone where city = dhaka and id > 5 order by id desc limit 10', '{\"action\":\"select\",\"table\":\"customers\",\"columns\":[\"id\",\"email\",\"phone\",\"city\"],\"limit\":10,\"filters\":[{\"column\":\"city\",\"operator\":\"=\",\"value\":\"dhaka\"},{\"column\":\"id\",\"operator\":\">\",\"value\":\"5\"}],\"sort\":{\"column\":\"id\",\"direction\":\"desc\"},\"confidence\":70,\"aggregate\":null}', '2026-06-24 14:13:43', '2026-06-24 14:13:43'),
(158, 'show customer email and phone', '{\"action\":\"select\",\"table\":\"customers\",\"columns\":[\"email\",\"phone\"],\"limit\":null,\"filters\":[],\"sort\":null,\"confidence\":40,\"aggregate\":null}', '2026-06-24 14:13:53', '2026-06-24 14:13:53'),
(159, 'show customer email = rahim@test.com', '{\"action\":\"select\",\"table\":\"customers\",\"columns\":[\"email\"],\"limit\":null,\"filters\":[],\"sort\":null,\"confidence\":35,\"aggregate\":null}', '2026-06-24 14:14:07', '2026-06-24 14:14:07'),
(160, 'show customer where email = rahim@test.com', '{\"action\":\"select\",\"table\":\"customers\",\"columns\":[\"email\"],\"limit\":null,\"filters\":[{\"column\":\"email\",\"operator\":\"=\",\"value\":\"rahim@test.com\"}],\"sort\":null,\"confidence\":40,\"aggregate\":null}', '2026-06-24 14:14:12', '2026-06-24 14:14:12'),
(161, 'show customer where email = rahim@test.com and where phone = 01555555555', '{\"action\":\"select\",\"table\":\"customers\",\"columns\":[\"email\",\"phone\"],\"limit\":null,\"filters\":[{\"column\":\"email\",\"operator\":\"=\",\"value\":\"rahim@test.com\"},{\"column\":\"phone\",\"operator\":\"=\",\"value\":\"01555555555\"}],\"sort\":null,\"confidence\":50,\"aggregate\":null}', '2026-06-24 14:14:48', '2026-06-24 14:14:48'),
(162, 'show customer where email = rahim@test.com or where phone = 01555555555', '{\"action\":\"select\",\"table\":\"customers\",\"columns\":[\"email\",\"phone\"],\"limit\":null,\"filters\":[{\"column\":\"email\",\"operator\":\"=\",\"value\":\"rahim@test.com\"},{\"column\":\"phone\",\"operator\":\"=\",\"value\":\"01555555555\"}],\"sort\":null,\"confidence\":50,\"aggregate\":null}', '2026-06-24 14:14:53', '2026-06-24 14:14:53'),
(163, 'show customer where phone = 01555555555', '{\"action\":\"select\",\"table\":\"customers\",\"columns\":[\"phone\"],\"limit\":null,\"filters\":[{\"column\":\"phone\",\"operator\":\"=\",\"value\":\"01555555555\"}],\"sort\":null,\"confidence\":40,\"aggregate\":null}', '2026-06-24 14:15:10', '2026-06-24 14:15:10'),
(164, 'show product', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[\"*\"],\"limit\":null,\"filters\":[],\"sort\":null,\"confidence\":30,\"aggregate\":null}', '2026-06-24 14:18:06', '2026-06-24 14:18:06'),
(165, 'show customer name and phone', '{\"action\":\"select\",\"table\":\"customers\",\"columns\":[\"name\",\"phone\"],\"limit\":null,\"filters\":[],\"sort\":null,\"confidence\":40,\"aggregate\":null}', '2026-06-24 14:18:30', '2026-06-24 14:18:30'),
(166, 'show customer name and phone', '{\"action\":\"select\",\"table\":\"customers\",\"columns\":[\"name\",\"phone\"],\"limit\":null,\"filters\":[],\"sort\":null,\"confidence\":40,\"aggregate\":null}', '2026-06-24 14:35:01', '2026-06-24 14:35:01'),
(167, 'show customer where name = Rahim Uddin', '{\"action\":\"select\",\"table\":\"customers\",\"columns\":[\"*\"],\"limit\":null,\"filters\":[{\"column\":\"name\",\"operator\":\"=\",\"value\":\"rahim uddin\"}],\"sort\":null,\"confidence\":35,\"aggregate\":null}', '2026-06-24 14:35:15', '2026-06-24 14:35:15'),
(168, 'show products where name = Rahim Uddin', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[\"*\"],\"limit\":null,\"filters\":[{\"column\":\"name\",\"operator\":\"=\",\"value\":\"rahim uddin\"}],\"sort\":null,\"confidence\":35,\"aggregate\":null}', '2026-06-24 14:35:37', '2026-06-24 14:35:37'),
(169, 'show products', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[\"*\"],\"limit\":null,\"filters\":[],\"sort\":null,\"confidence\":30,\"aggregate\":null}', '2026-06-24 14:35:45', '2026-06-24 14:35:45'),
(170, 'show products where name = Rahim Uddin', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[\"*\"],\"limit\":null,\"filters\":[{\"column\":\"name\",\"operator\":\"=\",\"value\":\"rahim uddin\"}],\"sort\":null,\"confidence\":35,\"aggregate\":null}', '2026-06-24 14:35:58', '2026-06-24 14:35:58'),
(171, 'show products where name = Desk Lamp', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[\"*\"],\"limit\":null,\"filters\":[{\"column\":\"name\",\"operator\":\"=\",\"value\":\"desk lamp\"}],\"sort\":null,\"confidence\":35,\"aggregate\":null}', '2026-06-24 14:36:02', '2026-06-24 14:36:02'),
(172, 'show products where products_name = Desk Lamp', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[\"*\"],\"limit\":null,\"filters\":[{\"column\":\"products_name\",\"operator\":\"=\",\"value\":\"desk lamp\"}],\"sort\":null,\"confidence\":35,\"aggregate\":null}', '2026-06-24 14:36:09', '2026-06-24 14:36:09'),
(173, 'show products where product_name = Desk Lamp', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[\"*\"],\"limit\":null,\"filters\":[{\"column\":\"product_name\",\"operator\":\"=\",\"value\":\"desk lamp\"}],\"sort\":null,\"confidence\":35,\"aggregate\":null}', '2026-06-24 14:36:16', '2026-06-24 14:36:16'),
(174, 'show customers', '{\"action\":\"select\",\"table\":\"customers\",\"columns\":[\"*\"],\"limit\":null,\"filters\":[],\"sort\":null,\"confidence\":30,\"aggregate\":null}', '2026-06-24 14:39:52', '2026-06-24 14:39:52'),
(175, 'show customer name', '{\"action\":\"select\",\"table\":\"customers\",\"columns\":[\"name\"],\"limit\":null,\"filters\":[],\"sort\":null,\"confidence\":35,\"aggregate\":null}', '2026-06-24 14:39:59', '2026-06-24 14:39:59'),
(176, 'show customer name and phone', '{\"action\":\"select\",\"table\":\"customers\",\"columns\":[\"name\",\"phone\"],\"limit\":null,\"filters\":[],\"sort\":null,\"confidence\":40,\"aggregate\":null}', '2026-06-24 14:40:09', '2026-06-24 14:40:09'),
(177, 'show customer where phone = 01555555555', '{\"action\":\"select\",\"table\":\"customers\",\"columns\":[\"*\"],\"limit\":null,\"filters\":[{\"column\":\"phone\",\"operator\":\"=\",\"value\":\"01555555555\"}],\"sort\":null,\"confidence\":35,\"aggregate\":null}', '2026-06-24 14:40:22', '2026-06-24 14:40:22'),
(178, 'show customer where city = dhaka', '{\"action\":\"select\",\"table\":\"customers\",\"columns\":[\"*\"],\"limit\":null,\"filters\":[{\"column\":\"city\",\"operator\":\"=\",\"value\":\"dhaka\"}],\"sort\":null,\"confidence\":35,\"aggregate\":null}', '2026-06-24 14:40:33', '2026-06-24 14:40:33'),
(179, 'show product product_name and price', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[\"product_name\",\"name\",\"price\"],\"limit\":null,\"filters\":[],\"sort\":null,\"confidence\":45,\"aggregate\":null}', '2026-06-24 14:40:52', '2026-06-24 14:40:52'),
(180, 'show product', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[\"*\"],\"limit\":null,\"filters\":[],\"sort\":null,\"confidence\":30,\"aggregate\":null}', '2026-06-24 14:41:04', '2026-06-24 14:41:04'),
(181, 'show product price', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[\"price\"],\"limit\":null,\"filters\":[],\"sort\":null,\"confidence\":35,\"aggregate\":null}', '2026-06-24 14:41:09', '2026-06-24 14:41:09'),
(182, 'show product price and stock', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[\"price\",\"stock\"],\"limit\":null,\"filters\":[],\"sort\":null,\"confidence\":40,\"aggregate\":null}', '2026-06-24 14:41:16', '2026-06-24 14:41:16'),
(183, 'show product product_name price and stock', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[\"product_name\",\"name\",\"price\",\"stock\"],\"limit\":null,\"filters\":[],\"sort\":null,\"confidence\":50,\"aggregate\":null}', '2026-06-24 14:41:24', '2026-06-24 14:41:24'),
(184, 'show product product_name,  price and stock', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[\"product_name\",\"name\",\"price\",\"stock\"],\"limit\":null,\"filters\":[],\"sort\":null,\"confidence\":50,\"aggregate\":null}', '2026-06-24 14:41:27', '2026-06-24 14:41:27'),
(185, 'show product product_name', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[\"product_name\",\"name\"],\"limit\":null,\"filters\":[],\"sort\":null,\"confidence\":40,\"aggregate\":null}', '2026-06-24 14:41:36', '2026-06-24 14:41:36'),
(186, 'show product name', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[\"name\"],\"limit\":null,\"filters\":[],\"sort\":null,\"confidence\":35,\"aggregate\":null}', '2026-06-24 14:41:42', '2026-06-24 14:41:42'),
(187, 'show product product_name', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[\"product_name\",\"name\"],\"limit\":null,\"filters\":[],\"sort\":null,\"confidence\":40,\"aggregate\":null}', '2026-06-24 14:41:51', '2026-06-24 14:41:51'),
(188, 'show product category and stock', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[\"category\",\"stock\"],\"limit\":null,\"filters\":[],\"sort\":null,\"confidence\":40,\"aggregate\":null}', '2026-06-24 14:42:07', '2026-06-24 14:42:07'),
(189, 'show product where price > 1000', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[\"*\"],\"limit\":null,\"filters\":[{\"column\":\"price\",\"operator\":\">\",\"value\":\"1000\"}],\"sort\":null,\"confidence\":35,\"aggregate\":null}', '2026-06-24 14:42:18', '2026-06-24 14:42:18'),
(190, 'show product where price > 100', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[\"*\"],\"limit\":null,\"filters\":[{\"column\":\"price\",\"operator\":\">\",\"value\":\"100\"}],\"sort\":null,\"confidence\":35,\"aggregate\":null}', '2026-06-24 14:42:24', '2026-06-24 14:42:24'),
(191, 'show product where price = 150', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[\"*\"],\"limit\":null,\"filters\":[{\"column\":\"price\",\"operator\":\"=\",\"value\":\"150\"}],\"sort\":null,\"confidence\":35,\"aggregate\":null}', '2026-06-24 14:42:37', '2026-06-24 14:42:37'),
(192, 'show product where price < 100', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[\"*\"],\"limit\":null,\"filters\":[{\"column\":\"price\",\"operator\":\"<\",\"value\":\"100\"}],\"sort\":null,\"confidence\":35,\"aggregate\":null}', '2026-06-24 14:42:46', '2026-06-24 14:42:46'),
(193, 'show product where price > 100', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[\"*\"],\"limit\":null,\"filters\":[{\"column\":\"price\",\"operator\":\">\",\"value\":\"100\"}],\"sort\":null,\"confidence\":35,\"aggregate\":null}', '2026-06-24 14:42:50', '2026-06-24 14:42:50'),
(194, 'show sales total_amount', '{\"action\":\"select\",\"table\":\"sales\",\"columns\":[\"total_amount\"],\"limit\":null,\"filters\":[],\"sort\":null,\"confidence\":45,\"aggregate\":\"SUM\"}', '2026-06-24 14:43:20', '2026-06-24 14:43:20'),
(195, 'show sales customer_id and total_amount', '{\"action\":\"select\",\"table\":\"customers\",\"columns\":[\"id\"],\"limit\":null,\"filters\":[],\"sort\":null,\"confidence\":45,\"aggregate\":\"SUM\"}', '2026-06-24 14:43:32', '2026-06-24 14:43:32'),
(196, 'show sales customer_id and total_amount', '{\"action\":\"select\",\"table\":\"customers\",\"columns\":[\"id\"],\"limit\":null,\"filters\":[],\"sort\":null,\"confidence\":45,\"aggregate\":\"SUM\"}', '2026-06-24 14:43:41', '2026-06-24 14:43:41'),
(197, 'show sales customer_id total_amount', '{\"action\":\"select\",\"table\":\"customers\",\"columns\":[\"id\"],\"limit\":null,\"filters\":[],\"sort\":null,\"confidence\":45,\"aggregate\":\"SUM\"}', '2026-06-24 14:43:47', '2026-06-24 14:43:47'),
(198, 'show sales customer_id and total_amount', '{\"action\":\"select\",\"table\":\"customers\",\"columns\":[\"id\"],\"limit\":null,\"filters\":[],\"sort\":null,\"confidence\":45,\"aggregate\":\"SUM\"}', '2026-06-24 14:43:50', '2026-06-24 14:43:50'),
(199, 'show sales total_amount', '{\"action\":\"select\",\"table\":\"sales\",\"columns\":[\"total_amount\"],\"limit\":null,\"filters\":[],\"sort\":null,\"confidence\":45,\"aggregate\":\"SUM\"}', '2026-06-24 14:44:01', '2026-06-24 14:44:01'),
(200, 'show sales total_amount and customer_id', '{\"action\":\"select\",\"table\":\"customers\",\"columns\":[\"id\"],\"limit\":null,\"filters\":[],\"sort\":null,\"confidence\":45,\"aggregate\":\"SUM\"}', '2026-06-24 14:44:08', '2026-06-24 14:44:08'),
(201, 'show sales total_amount where customer_id = 1', '{\"action\":\"select\",\"table\":\"customers\",\"columns\":[],\"limit\":null,\"filters\":[{\"column\":\"customer_id\",\"operator\":\"=\",\"value\":\"1\"}],\"sort\":null,\"confidence\":45,\"aggregate\":\"SUM\"}', '2026-06-24 14:44:20', '2026-06-24 14:44:20'),
(202, 'show sales where customer_id = 1', '{\"action\":\"select\",\"table\":\"customers\",\"columns\":[\"*\"],\"limit\":null,\"filters\":[{\"column\":\"customer_id\",\"operator\":\"=\",\"value\":\"1\"}],\"sort\":null,\"confidence\":35,\"aggregate\":null}', '2026-06-24 14:44:25', '2026-06-24 14:44:25');
INSERT INTO `chat_logs` (`id`, `query`, `intent`, `created_at`, `updated_at`) VALUES
(203, 'show customer where city = dhaka and id > 1', '{\"action\":\"select\",\"table\":\"customers\",\"columns\":[\"*\"],\"limit\":null,\"filters\":[{\"column\":\"city\",\"operator\":\"=\",\"value\":\"dhaka\"},{\"column\":\"id\",\"operator\":\">\",\"value\":\"1\"}],\"sort\":null,\"confidence\":40,\"aggregate\":null}', '2026-06-24 14:44:40', '2026-06-24 14:44:40'),
(204, 'show product where price > 1000 and stock > 50', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[\"*\"],\"limit\":null,\"filters\":[{\"column\":\"price\",\"operator\":\">\",\"value\":\"1000\"},{\"column\":\"stock\",\"operator\":\">\",\"value\":\"50\"}],\"sort\":null,\"confidence\":40,\"aggregate\":null}', '2026-06-24 14:44:59', '2026-06-24 14:44:59'),
(205, 'show product where price > 1000', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[\"*\"],\"limit\":null,\"filters\":[{\"column\":\"price\",\"operator\":\">\",\"value\":\"1000\"}],\"sort\":null,\"confidence\":35,\"aggregate\":null}', '2026-06-24 14:45:14', '2026-06-24 14:45:14'),
(206, 'show product where price = 1500', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[\"*\"],\"limit\":null,\"filters\":[{\"column\":\"price\",\"operator\":\"=\",\"value\":\"1500\"}],\"sort\":null,\"confidence\":35,\"aggregate\":null}', '2026-06-24 14:45:24', '2026-06-24 14:45:24'),
(207, 'show product where price > 1000 and stock > 50', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[\"*\"],\"limit\":null,\"filters\":[{\"column\":\"price\",\"operator\":\">\",\"value\":\"1000\"},{\"column\":\"stock\",\"operator\":\">\",\"value\":\"50\"}],\"sort\":null,\"confidence\":40,\"aggregate\":null}', '2026-06-24 14:45:28', '2026-06-24 14:45:28'),
(208, 'show sales where customer_id = 1 and total_amount > 10000', '{\"action\":\"select\",\"table\":\"customers\",\"columns\":[\"*\"],\"limit\":null,\"filters\":[{\"column\":\"customer_id\",\"operator\":\"=\",\"value\":\"1\"},{\"column\":\"total_amount\",\"operator\":\">\",\"value\":\"10000\"}],\"sort\":null,\"confidence\":40,\"aggregate\":null}', '2026-06-24 14:45:48', '2026-06-24 14:45:48'),
(209, 'show customer order by id desc', '{\"action\":\"select\",\"table\":\"customers\",\"columns\":[\"*\"],\"limit\":null,\"filters\":[],\"sort\":{\"column\":\"id\",\"direction\":\"desc\"},\"confidence\":35,\"aggregate\":null}', '2026-06-24 14:46:05', '2026-06-24 14:46:05'),
(210, 'show sales order by total_amount desc limit 3', '{\"action\":\"select\",\"table\":\"sales\",\"columns\":[\"*\"],\"limit\":3,\"filters\":[],\"sort\":{\"column\":\"total_amount\",\"direction\":\"desc\"},\"confidence\":40,\"aggregate\":null}', '2026-06-24 14:46:21', '2026-06-24 14:46:21'),
(211, 'count customers', '{\"action\":null,\"table\":\"customers\",\"columns\":[],\"limit\":null,\"filters\":[],\"sort\":null,\"confidence\":30,\"aggregate\":\"COUNT\"}', '2026-06-24 14:46:34', '2026-06-24 14:46:34'),
(212, 'count sales', '{\"action\":null,\"table\":\"sales\",\"columns\":[],\"limit\":null,\"filters\":[],\"sort\":null,\"confidence\":30,\"aggregate\":\"COUNT\"}', '2026-06-24 14:46:42', '2026-06-24 14:46:42'),
(213, 'sum sales total_amount', '{\"action\":null,\"table\":\"sales\",\"columns\":[\"total_amount\"],\"limit\":null,\"filters\":[],\"sort\":null,\"confidence\":35,\"aggregate\":\"SUM\"}', '2026-06-24 14:46:50', '2026-06-24 14:46:50'),
(214, 'average product price', '{\"action\":null,\"table\":\"products\",\"columns\":[\"price\"],\"limit\":null,\"filters\":[],\"sort\":null,\"confidence\":35,\"aggregate\":\"AVG\"}', '2026-06-24 14:47:00', '2026-06-24 14:47:00'),
(215, 'min product price', '{\"action\":null,\"table\":\"products\",\"columns\":[\"price\"],\"limit\":null,\"filters\":[],\"sort\":null,\"confidence\":35,\"aggregate\":\"MIN\"}', '2026-06-24 14:47:12', '2026-06-24 14:47:12'),
(216, 'show customer email where city = dhaka order by id desc limit 2', '{\"action\":\"select\",\"table\":\"customers\",\"columns\":[\"email\"],\"limit\":2,\"filters\":[{\"column\":\"city\",\"operator\":\"=\",\"value\":\"dhaka\"}],\"sort\":{\"column\":\"id\",\"direction\":\"desc\"},\"confidence\":50,\"aggregate\":null}', '2026-06-24 14:47:26', '2026-06-24 14:47:26'),
(217, 'show product product_name and price where stock > 50 order by price desc', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[\"product_name\",\"name\",\"price\"],\"limit\":null,\"filters\":[{\"column\":\"stock\",\"operator\":\">\",\"value\":\"50\"}],\"sort\":{\"column\":\"price\",\"direction\":\"desc\"},\"confidence\":55,\"aggregate\":null}', '2026-06-24 14:47:40', '2026-06-24 14:47:40'),
(218, 'show product product_name', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[\"product_name\",\"name\"],\"limit\":null,\"filters\":[],\"sort\":null,\"confidence\":40,\"aggregate\":null}', '2026-06-24 14:47:59', '2026-06-24 14:47:59'),
(219, 'show sales where customer_id = 1', '{\"action\":\"select\",\"table\":\"customers\",\"columns\":[\"*\"],\"limit\":null,\"filters\":[{\"column\":\"customer_id\",\"operator\":\"=\",\"value\":\"1\"}],\"sort\":null,\"confidence\":35,\"aggregate\":null}', '2026-06-24 14:48:44', '2026-06-24 14:48:44'),
(220, 'show sales where customer_id = 1', '{\"action\":\"select\",\"table\":\"customers\",\"columns\":[\"*\"],\"limit\":null,\"filters\":[{\"column\":\"customer_id\",\"operator\":\"=\",\"value\":\"1\"}],\"sort\":null,\"confidence\":35,\"aggregate\":null}', '2026-06-24 14:48:45', '2026-06-24 14:48:45'),
(221, 'show sales where customer_id = 1', '{\"action\":\"select\",\"table\":\"customers\",\"columns\":[\"*\"],\"limit\":null,\"filters\":[{\"column\":\"customer_id\",\"operator\":\"=\",\"value\":\"1\"}],\"sort\":null,\"confidence\":35,\"aggregate\":null}', '2026-06-24 14:48:45', '2026-06-24 14:48:45'),
(222, 'show sales where customer_id = 1', '{\"action\":\"select\",\"table\":\"customers\",\"columns\":[\"*\"],\"limit\":null,\"filters\":[{\"column\":\"customer_id\",\"operator\":\"=\",\"value\":\"1\"}],\"sort\":null,\"confidence\":35,\"aggregate\":null}', '2026-06-24 14:48:46', '2026-06-24 14:48:46'),
(223, 'show sales where customer_id = 1', '{\"action\":\"select\",\"table\":\"customers\",\"columns\":[\"*\"],\"limit\":null,\"filters\":[{\"column\":\"customer_id\",\"operator\":\"=\",\"value\":\"1\"}],\"sort\":null,\"confidence\":35,\"aggregate\":null}', '2026-06-24 14:48:46', '2026-06-24 14:48:46'),
(224, 'show sales where customer_id = 1', '{\"action\":\"select\",\"table\":\"customers\",\"columns\":[\"*\"],\"limit\":null,\"filters\":[{\"column\":\"customer_id\",\"operator\":\"=\",\"value\":\"1\"}],\"sort\":null,\"confidence\":35,\"aggregate\":null}', '2026-06-24 14:48:46', '2026-06-24 14:48:46'),
(225, 'show sales where customer_id = 1', '{\"action\":\"select\",\"table\":\"customers\",\"columns\":[\"*\"],\"limit\":null,\"filters\":[{\"column\":\"customer_id\",\"operator\":\"=\",\"value\":\"1\"}],\"sort\":null,\"confidence\":35,\"aggregate\":null}', '2026-06-24 14:48:46', '2026-06-24 14:48:46'),
(226, 'show sales where customer_id = 1', '{\"action\":\"select\",\"table\":\"customers\",\"columns\":[\"*\"],\"limit\":null,\"filters\":[{\"column\":\"customer_id\",\"operator\":\"=\",\"value\":\"1\"}],\"sort\":null,\"confidence\":35,\"aggregate\":null}', '2026-06-24 14:48:46', '2026-06-24 14:48:46'),
(227, 'show sales where customer_id = 1', '{\"action\":\"select\",\"table\":\"customers\",\"columns\":[\"*\"],\"limit\":null,\"filters\":[{\"column\":\"customer_id\",\"operator\":\"=\",\"value\":\"1\"}],\"sort\":null,\"confidence\":35,\"aggregate\":null}', '2026-06-24 14:48:47', '2026-06-24 14:48:47'),
(228, 'show sales customer_id and total_amount', '{\"action\":\"select\",\"table\":\"customers\",\"columns\":[\"id\"],\"limit\":null,\"filters\":[],\"sort\":null,\"confidence\":45,\"aggregate\":\"SUM\"}', '2026-06-24 14:48:54', '2026-06-24 14:48:54'),
(229, 'count sales', '{\"action\":\"select\",\"table\":\"sales\",\"columns\":[],\"limit\":null,\"filters\":[],\"sort\":null,\"confidence\":30,\"aggregate\":\"COUNT\"}', '2026-06-24 14:55:52', '2026-06-24 14:55:52'),
(230, 'min product price', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[\"price\"],\"limit\":null,\"filters\":[],\"sort\":null,\"confidence\":35,\"aggregate\":\"MIN\"}', '2026-06-24 14:56:39', '2026-06-24 14:56:39'),
(231, 'show sales where customer_id = 1', '{\"action\":\"select\",\"table\":\"sales\",\"columns\":[\"*\"],\"limit\":null,\"filters\":[{\"column\":\"customer_id\",\"operator\":\"=\",\"value\":\"1\"}],\"sort\":null,\"confidence\":35,\"aggregate\":null,\"aggregate_column\":null}', '2026-06-24 15:07:27', '2026-06-24 15:07:27'),
(232, 'min product price', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[\"price\"],\"limit\":null,\"filters\":[],\"sort\":null,\"confidence\":35,\"aggregate\":\"MIN\",\"aggregate_column\":\"price\"}', '2026-06-24 15:07:53', '2026-06-24 15:07:53'),
(233, 'min product price', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[\"price\"],\"limit\":null,\"filters\":[],\"sort\":null,\"confidence\":35,\"aggregate\":\"MIN\",\"aggregate_column\":\"price\"}', '2026-06-24 15:08:01', '2026-06-24 15:08:01'),
(234, 'min product price', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[\"price\"],\"limit\":null,\"filters\":[],\"sort\":null,\"confidence\":35,\"aggregate\":\"MIN\",\"aggregate_column\":\"price\"}', '2026-06-24 15:15:57', '2026-06-24 15:15:57'),
(235, 'show products', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[\"*\"],\"limit\":null,\"filters\":[],\"sort\":null,\"confidence\":30,\"aggregate\":null,\"aggregate_column\":null}', '2026-06-24 15:16:10', '2026-06-24 15:16:10'),
(236, 'max product price', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[\"price\"],\"limit\":null,\"filters\":[],\"sort\":null,\"confidence\":35,\"aggregate\":\"MAX\",\"aggregate_column\":\"price\"}', '2026-06-24 15:16:20', '2026-06-24 15:16:20'),
(237, 'count product', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[],\"limit\":null,\"filters\":[],\"sort\":null,\"confidence\":30,\"aggregate\":\"COUNT\",\"aggregate_column\":\"*\"}', '2026-06-24 15:16:26', '2026-06-24 15:16:26'),
(238, 'product count', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[],\"limit\":null,\"filters\":[],\"sort\":null,\"confidence\":30,\"aggregate\":\"COUNT\",\"aggregate_column\":\"*\"}', '2026-06-24 15:16:32', '2026-06-24 15:16:32'),
(239, 'show customer', '{\"action\":\"select\",\"table\":\"customers\",\"columns\":[\"*\"],\"limit\":null,\"filters\":[],\"sort\":null,\"confidence\":30,\"aggregate\":null,\"aggregate_column\":null}', '2026-06-26 10:06:39', '2026-06-26 10:06:39'),
(240, 'show customer where email = rahim@test.com', '{\"action\":\"select\",\"table\":\"customers\",\"columns\":[\"*\"],\"limit\":null,\"filters\":[{\"column\":\"email\",\"operator\":\"=\",\"value\":\"rahim@test.com\"}],\"sort\":null,\"confidence\":35,\"aggregate\":null,\"aggregate_column\":null}', '2026-06-26 10:06:55', '2026-06-26 10:06:55'),
(241, 'show customer name', '{\"action\":\"select\",\"table\":\"customers\",\"columns\":[\"name\"],\"limit\":null,\"filters\":[],\"sort\":null,\"confidence\":35,\"aggregate\":null,\"aggregate_column\":null}', '2026-06-26 10:07:02', '2026-06-26 10:07:02'),
(242, 'show product', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[\"*\"],\"limit\":null,\"filters\":[],\"sort\":null,\"confidence\":30,\"aggregate\":null,\"aggregate_column\":null}', '2026-06-26 10:07:10', '2026-06-26 10:07:10'),
(243, 'show product quantity of laptop', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[\"*\"],\"limit\":null,\"filters\":[],\"sort\":null,\"confidence\":30,\"aggregate\":null,\"aggregate_column\":null}', '2026-06-26 10:07:37', '2026-06-26 10:07:37'),
(244, 'show product count\\', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[],\"limit\":null,\"filters\":[],\"sort\":null,\"confidence\":40,\"aggregate\":\"COUNT\",\"aggregate_column\":\"*\"}', '2026-06-26 10:07:44', '2026-06-26 10:07:44'),
(245, 'show product count', '{\"action\":\"select\",\"table\":\"products\",\"columns\":[],\"limit\":null,\"filters\":[],\"sort\":null,\"confidence\":40,\"aggregate\":\"COUNT\",\"aggregate_column\":\"*\"}', '2026-06-26 10:07:49', '2026-06-26 10:07:49'),
(246, 'show prodt count', '{\"success\":false,\"message\":\"No table detected\",\"intent\":{\"action\":\"select\",\"table\":null,\"columns\":[],\"limit\":null,\"filters\":[],\"sort\":null,\"confidence\":20,\"aggregate\":\"COUNT\",\"aggregate_column\":\"*\"}}', '2026-06-26 10:15:05', '2026-06-26 10:15:05'),
(247, 'show prod count', '{\"success\":false,\"message\":\"No table detected\",\"intent\":{\"action\":\"select\",\"table\":null,\"columns\":[],\"limit\":null,\"filters\":[],\"sort\":null,\"confidence\":20,\"aggregate\":\"COUNT\",\"aggregate_column\":\"*\"}}', '2026-06-26 10:15:10', '2026-06-26 10:15:10'),
(248, 'context routing', '{\"success\":false,\"message\":\"No table detected\",\"intent\":{\"action\":null,\"table\":null,\"columns\":[\"*\"],\"limit\":null,\"filters\":[],\"sort\":null,\"confidence\":0,\"aggregate\":null,\"aggregate_column\":null}}', '2026-06-26 10:16:15', '2026-06-26 10:16:15');

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL,
  `reserved_at` int(10) UNSIGNED DEFAULT NULL,
  `available_at` int(10) UNSIGNED NOT NULL,
  `created_at` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_batches`
--

CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000000_create_users_table', 1),
(2, '0001_01_01_000001_create_cache_table', 1),
(3, '0001_01_01_000002_create_jobs_table', 1),
(4, '2026_06_22_044139_create_source_tables_table', 2),
(5, '2026_06_22_044919_create_chat_logs_table', 2);

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
('e1YJQBT2EFgMVxTQ5m73S3PZif5RIG4qXblfA4pd', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiV010RnNTT25ndXlEQzUyU21BVVhSb09tRWFQZ2lOM1piMzd4ekY1WCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjY6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9jaGF0IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1782490575),
('pHAcANMRFNCfbHWT6NEQkwJOSKa8c6BJG7qzcz16', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoidElvNkNmMjNQazViSUh5MXQ3ZFBEa000bzJDVFppMzlwdnhZRm9USiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjY6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9jaGF0IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1782335797);

-- --------------------------------------------------------

--
-- Table structure for table `source_tables`
--

CREATE TABLE `source_tables` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `source_id` varchar(255) NOT NULL,
  `table_name` varchar(255) NOT NULL,
  `alias` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `source_tables`
--

INSERT INTO `source_tables` (`id`, `source_id`, `table_name`, `alias`, `created_at`, `updated_at`) VALUES
(1, 'db_01', 'customers', 'customer', '2026-06-22 05:04:24', NULL),
(2, 'db_01', 'customers', 'customers', '2026-06-22 05:04:54', NULL),
(3, 'db_01', 'customers', 'client', '2026-06-22 05:04:59', NULL),
(4, 'db_01', 'customers', 'clients', '2026-06-22 05:05:03', NULL),
(5, 'db_02', 'products', 'product', '2026-06-24 18:17:46', NULL),
(6, 'db_02', 'products', 'products', '2026-06-24 18:17:50', NULL),
(7, 'db_02', 'products', 'item', '2026-06-24 18:17:53', NULL),
(8, 'db_02', 'products', 'items', '2026-06-24 18:17:57', NULL),
(9, 'db_03', 'sales', 'sale', '2026-06-24 18:19:28', NULL),
(10, 'db_03', 'sales', 'sales', '2026-06-24 18:19:35', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `source_table_columns`
--

CREATE TABLE `source_table_columns` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `table_name` varchar(255) NOT NULL,
  `column_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `source_table_columns`
--

INSERT INTO `source_table_columns` (`id`, `table_name`, `column_name`, `created_at`, `updated_at`) VALUES
(1, 'customers', 'id', '2026-06-22 08:36:19', NULL),
(2, 'customers', 'name', '2026-06-22 08:36:24', NULL),
(3, 'customers', 'email', '2026-06-22 08:36:30', NULL),
(4, 'customers', 'phone', '2026-06-22 08:36:35', NULL),
(5, 'customers', 'city', '2026-06-22 09:48:26', NULL),
(6, 'products', 'id', '2026-06-24 18:32:26', NULL),
(7, 'products', 'product_name', '2026-06-24 18:32:31', NULL),
(8, 'products', 'name', '2026-06-24 18:32:34', NULL),
(9, 'products', 'category', '2026-06-24 18:32:39', NULL),
(10, 'products', 'price', '2026-06-24 18:32:42', NULL),
(11, 'products', 'stock', '2026-06-24 18:32:47', NULL),
(12, 'sales', 'id', '2026-06-24 18:32:51', NULL),
(13, 'sales', 'customer_id', '2026-06-24 18:32:54', NULL),
(14, 'sales', 'total_amount', '2026-06-24 18:32:58', NULL),
(15, 'sales', 'order_date', '2026-06-24 18:33:02', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cache`
--
ALTER TABLE `cache`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_expiration_index` (`expiration`);

--
-- Indexes for table `cache_locks`
--
ALTER TABLE `cache_locks`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_locks_expiration_index` (`expiration`);

--
-- Indexes for table `chat_logs`
--
ALTER TABLE `chat_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobs_queue_index` (`queue`);

--
-- Indexes for table `job_batches`
--
ALTER TABLE `job_batches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

--
-- Indexes for table `source_tables`
--
ALTER TABLE `source_tables`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `source_table_columns`
--
ALTER TABLE `source_table_columns`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `chat_logs`
--
ALTER TABLE `chat_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=249;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `source_tables`
--
ALTER TABLE `source_tables`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `source_table_columns`
--
ALTER TABLE `source_table_columns`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
