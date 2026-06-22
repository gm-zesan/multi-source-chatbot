-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Jun 22, 2026 at 11:29 AM
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
-- Database: `chatbot_core`
--

-- --------------------------------------------------------

--
-- Table structure for table `cache`
--

CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cache_locks`
--

CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chat_logs`
--

CREATE TABLE `chat_logs` (
  `id` bigint UNSIGNED NOT NULL,
  `query` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `intent` json NOT NULL,
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
(103, 'show client', '{\"sort\": null, \"limit\": null, \"table\": \"customers\", \"action\": \"select\", \"source\": \"db_01\", \"columns\": [\"*\"], \"filters\": []}', '2026-06-22 04:59:54', '2026-06-22 04:59:54');

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint UNSIGNED NOT NULL,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint UNSIGNED NOT NULL,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint UNSIGNED NOT NULL,
  `reserved_at` int UNSIGNED DEFAULT NULL,
  `available_at` int UNSIGNED NOT NULL,
  `created_at` int UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_batches`
--

CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int UNSIGNED NOT NULL,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL
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
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
('lxOvvT4AVNkYuJF6mriYSAyLhGwnEpNIanwDAvwj', NULL, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiUHhTZEZPU0lLc3dxV2xZN0F4Tkx4bXI4NllMQ2ZHT1pFNkt1WWoxYiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjY6Imh0dHA6Ly9sb2NhbGhvc3Q6ODAwMC9jaGF0IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1782125994);

-- --------------------------------------------------------

--
-- Table structure for table `source_tables`
--

CREATE TABLE `source_tables` (
  `id` bigint UNSIGNED NOT NULL,
  `source_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `table_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `alias` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
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
(4, 'db_01', 'customers', 'clients', '2026-06-22 05:05:03', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `source_table_columns`
--

CREATE TABLE `source_table_columns` (
  `id` bigint UNSIGNED NOT NULL,
  `table_name` varchar(255) NOT NULL,
  `column_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `source_table_columns`
--

INSERT INTO `source_table_columns` (`id`, `table_name`, `column_name`, `created_at`, `updated_at`) VALUES
(1, 'customers', 'id', '2026-06-22 08:36:19', NULL),
(2, 'customers', 'name', '2026-06-22 08:36:24', NULL),
(3, 'customers', 'email', '2026-06-22 08:36:30', NULL),
(4, 'customers', 'phone', '2026-06-22 08:36:35', NULL),
(5, 'customers', 'city', '2026-06-22 09:48:26', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=104;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `source_tables`
--
ALTER TABLE `source_tables`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `source_table_columns`
--
ALTER TABLE `source_table_columns`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
