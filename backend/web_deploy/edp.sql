-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 25, 2026 at 01:07 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `edp`
--

-- --------------------------------------------------------

--
-- Table structure for table `addresses`
--

CREATE TABLE `addresses` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `label` varchar(255) NOT NULL DEFAULT 'Home',
  `name` varchar(120) NOT NULL,
  `line1` varchar(255) NOT NULL,
  `line2` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(60) DEFAULT NULL,
  `postal_code` varchar(12) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `addresses`
--

INSERT INTO `addresses` (`id`, `user_id`, `label`, `name`, `line1`, `line2`, `city`, `state`, `postal_code`, `latitude`, `longitude`, `is_default`, `created_at`, `updated_at`) VALUES
(1, 17, 'Home', 'Testcaresort', '34 Jan Marg', NULL, 'Mohali', 'PB', '160061', 30.7149794, 76.7227993, 0, '2026-09-09 02:28:05', '2026-09-23 01:22:21'),
(2, 15, 'Home', 'Test User', '34 Jan Marg', NULL, 'Mohali', 'PB', '160061', 30.7149794, 76.7227993, 1, '2026-09-09 04:04:43', '2026-09-09 04:04:43'),
(3, 17, 'Home', 'Testcaresort', '34 Jan Marg', NULL, 'Mohali', 'PB', '160061', 30.7149794, 76.7227993, 0, '2026-09-10 05:14:36', '2026-09-23 01:22:21'),
(4, 17, 'Home', 'Testcaresort', '34 Jan Marg', NULL, 'Mohali', 'PB', '160061', 30.7149794, 76.7227993, 0, '2026-09-10 05:14:44', '2026-09-23 01:22:21'),
(5, 17, 'Home', 'Testcaresort', '34 Jan Marg', NULL, 'Mohali', 'PB', '160061', 30.7149794, 76.7227993, 0, '2026-09-10 07:25:56', '2026-09-23 01:22:21'),
(6, 15, 'Home', 'Testcaresort', '34 Jan Marg', NULL, 'Mohali', 'PB', '160061', 30.7149794, 76.7227993, 0, '2026-09-10 07:27:16', '2026-09-10 07:27:16'),
(7, 17, 'Home', 'Testcaresort', '34 Jan Marg', NULL, 'Mohali', 'PB', '160061', 30.7149794, 76.7227993, 0, '2026-09-10 23:19:31', '2026-09-23 01:22:21'),
(8, 17, 'Home', 'Testcaresort', '34 Jan Marg', NULL, 'Mohali', 'PB', '160061', 30.7149794, 76.7227993, 0, '2026-09-11 00:16:43', '2026-09-23 01:22:21'),
(9, 17, 'Home', 'Testcaresort', '34 Jan Marg', NULL, 'Mohali', 'PB', '160061', 30.7149794, 76.7227993, 0, '2026-09-11 02:15:09', '2026-09-23 01:22:21'),
(10, 17, 'Home', 'Testcaresort', '34 Jan Marg', NULL, 'Mohali', 'PB', '160061', 30.7149794, 76.7227993, 0, '2026-09-11 02:16:32', '2026-09-23 01:22:21'),
(11, 17, 'Home', 'Testcaresort', '34 Jan Marg', NULL, 'Mohali', 'PB', '160061', 30.7149794, 76.7227993, 0, '2026-09-11 02:26:55', '2026-09-23 01:22:21'),
(12, 17, 'Home', 'Testcaresort', '34 Jan Marg', NULL, 'Mohali', 'PB', '160061', 30.7149794, 76.7227993, 0, '2026-09-11 02:27:11', '2026-09-23 01:22:21'),
(13, 17, 'Home', 'Testcaresort', '34 Jan Marg', NULL, 'Mohali', 'PB', '160061', 30.7149794, 76.7227993, 0, '2026-09-11 02:51:54', '2026-09-23 01:22:21'),
(14, 15, 'Home', 'Test User', '34 Jan Marg', NULL, 'Mohali', 'PB', '160061', 30.7149794, 76.7227993, 0, '2026-09-11 04:19:57', '2026-09-11 04:19:57'),
(15, 17, 'Home', 'Testcaresort', '34 Jan Marg', NULL, 'Mohali', 'PB', '160061', 30.7149794, 76.7227993, 0, '2026-09-11 04:35:06', '2026-09-23 01:22:21'),
(16, 17, 'Home', 'Testcaresort', '34 Jan Marg', NULL, 'Mohali', 'PB', '160061', 30.7149794, 76.7227993, 0, '2026-09-11 07:31:04', '2026-09-23 01:22:21'),
(17, 17, 'Home', 'Testcaresort', '34 Jan Marg', NULL, 'Mohali', 'PB', '160061', 30.7149794, 76.7227993, 0, '2026-09-14 06:14:06', '2026-09-23 01:22:21'),
(18, 15, 'Home', 'Test User', 'SH12A', NULL, 'Mohali', 'Punjab', '160070', 30.6910873, 76.7137098, 0, '2026-09-18 05:03:22', '2026-09-18 05:03:22'),
(19, 17, 'Home', 'Testcaresort', 'edge 27', NULL, 'Mohali', 'Punjab', '160055', 30.7197622, 76.7056954, 0, '2026-09-23 01:18:18', '2026-09-23 01:22:21'),
(20, 17, 'Home', 'Testcaresort', 'edge 27', NULL, 'Mohali', 'Punjab', '160055', 30.7197622, 76.7056954, 0, '2026-09-23 01:18:55', '2026-09-23 01:22:21'),
(21, 17, 'Home', 'Testcaresort', 'edge 27', NULL, 'Mohali', 'Punjab', '160055', 30.7197622, 76.7056954, 0, '2026-09-23 01:22:07', '2026-09-23 01:22:21'),
(22, 17, 'Home', 'Testcaresort', 'edge 27', NULL, 'Mohali', 'Punjab', '160055', 30.7197622, 76.7056954, 1, '2026-09-23 01:22:21', '2026-09-23 01:22:21'),
(23, 17, 'Home', 'Testcaresort', 'edge 27', NULL, 'Mohali', 'Punjab', '160055', 30.7197622, 76.7056954, 0, '2026-09-23 01:23:55', '2026-09-23 01:23:55'),
(24, 41, 'Home', 'Testcaresort', 'edge 27', NULL, 'Mohali', 'Punjab', '160055', 30.7197622, 76.7056954, 1, '2026-09-23 01:29:41', '2026-09-23 01:29:41'),
(25, 41, 'Home', 'Testcaresort', 'edge 27', NULL, 'Mohali', 'Punjab', '160055', 30.7197622, 76.7056954, 0, '2026-09-23 01:33:52', '2026-09-23 01:33:52');

-- --------------------------------------------------------

--
-- Table structure for table `auth_otps`
--

CREATE TABLE `auth_otps` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `email` varchar(255) NOT NULL,
  `purpose` varchar(255) NOT NULL,
  `code_hash` varchar(255) NOT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `expires_at` timestamp NULL DEFAULT NULL,
  `last_sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `banners`
--

CREATE TABLE `banners` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `image_url` varchar(255) NOT NULL,
  `headline` varchar(255) DEFAULT NULL,
  `category_slug` varchar(255) DEFAULT NULL,
  `link_url` varchar(255) DEFAULT NULL,
  `placement` varchar(255) NOT NULL DEFAULT 'hero',
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `banners`
--

INSERT INTO `banners` (`id`, `image_url`, `headline`, `category_slug`, `link_url`, `placement`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, '/storage/products/k6QEhTv4qkr8iRWjC8pmtA6dscPBDgVE4F6KfUv2.png', NULL, 'mobiles-smartphones', NULL, 'hero', 1, 1, '2026-09-09 01:12:49', '2026-09-14 23:42:23'),
(2, '/storage/products/IT7BJ7r9I1BDJedkfQFO9oOpHFsz6dbUIjqc8v9p.png', NULL, 'personal-care-electronics', NULL, 'strip', 2, 1, '2026-09-09 01:12:49', '2026-09-14 23:23:56'),
(3, '/storage/products/SP6cbTpeV7pzv5gQhy82ASn7aLBAYcHUzKZWbBMt.png', NULL, 'office-electronics', NULL, 'strip', 3, 1, '2026-09-09 01:12:49', '2026-09-14 23:42:23'),
(4, '/storage/products/MNJkvZ4zGLm8fLGPhLWQDCnVkzq9aZZYoyKfwSS3.png', NULL, 'kids-and-baby-tech', NULL, 'strip', 4, 1, '2026-09-09 01:12:49', '2026-09-14 23:23:56');

-- --------------------------------------------------------

--
-- Table structure for table `cache`
--

CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cache`
--

INSERT INTO `cache` (`key`, `value`, `expiration`) VALUES
('nextech-cache-setting:active_countries', 'a:1:{s:1:\"v\";a:2:{i:0;s:2:\"IN\";i:1;s:2:\"US\";}}', 2105692944),
('nextech-cache-setting:branding', 'a:1:{s:1:\"v\";a:9:{s:10:\"store_name\";s:7:\"NexTech\";s:7:\"tagline\";s:36:\"Navigate to the Future of Technology\";s:8:\"logo_url\";N;s:11:\"favicon_url\";s:62:\"/storage/products/UwbDkyfQGw98w7hhx7hlPcFS1Cw9VZfnz06klnyk.jpg\";s:5:\"theme\";s:5:\"light\";s:12:\"layout_width\";s:4:\"full\";s:11:\"color_brand\";s:7:\"#2563EB\";s:12:\"color_accent\";s:7:\"#F97316\";s:13:\"color_heading\";s:7:\"#0F172A\";}}', 2105692946),
('nextech-cache-setting:checkout_fees', 'a:1:{s:1:\"v\";a:9:{s:13:\"delivery_mode\";s:5:\"fixed\";s:18:\"delivery_fee_cents\";i:299;s:23:\"delivery_near_fee_cents\";i:199;s:22:\"delivery_far_fee_cents\";i:599;s:29:\"free_delivery_threshold_cents\";i:3500;s:18:\"handling_fee_cents\";i:99;s:20:\"small_cart_fee_cents\";i:199;s:20:\"small_cart_min_cents\";i:1000;s:12:\"tax_rate_bps\";i:887;}}', 2105692946),
('nextech-cache-setting:checkout_fees_IN', 'a:1:{s:7:\"missing\";b:1;}', 2105692946),
('nextech-cache-setting:cod_enabled', 'a:1:{s:1:\"v\";b:0;}', 2105692946),
('nextech-cache-setting:commission_rate_bps', 'a:1:{s:7:\"missing\";b:1;}', 2105692946),
('nextech-cache-setting:decoration_min_products', 'a:1:{s:7:\"missing\";b:1;}', 2105693620),
('nextech-cache-setting:decoration_spot_check_rate', 'a:1:{s:7:\"missing\";b:1;}', 2105693620),
('nextech-cache-setting:footer', 'a:1:{s:1:\"v\";a:8:{s:9:\"copyright\";s:17:\"© {year} nextech\";s:4:\"note\";s:143:\"NexTech is a demo storefront. Prices, delivery estimates and content pages are illustrative and set by the store operator in the admin console.\";s:13:\"app_store_url\";s:39:\"https://apps.apple.com/app/nextech-demo\";s:14:\"play_store_url\";s:62:\"https://play.google.com/store/apps/details?id=com.nextech.demo\";s:7:\"socials\";a:5:{s:8:\"facebook\";s:28:\"https://facebook.com/nextech\";s:1:\"x\";s:21:\"https://x.com/nextech\";s:9:\"instagram\";s:29:\"https://instagram.com/nextech\";s:8:\"linkedin\";s:40:\"https://www.linkedin.com/company/nextech\";s:7:\"youtube\";s:32:\"https://www.youtube.com/@nextech\";}s:5:\"links\";a:0:{}s:8:\"bg_color\";s:7:\"#141414\";s:10:\"text_color\";s:7:\"#f5f5f5\";}}', 2105692946),
('nextech-cache-setting:grievance_officer', 'a:1:{s:7:\"missing\";b:1;}', 2105692946),
('nextech-cache-setting:home_market', 'a:1:{s:7:\"missing\";b:1;}', 2105692944),
('nextech-cache-setting:max_return_days', 'a:1:{s:7:\"missing\";b:1;}', 2105692946),
('nextech-cache-setting:nextech_label_mode', 'a:1:{s:1:\"v\";s:6:\"manual\";}', 2105693829),
('nextech-cache-setting:nextech_pickup', 'a:1:{s:1:\"v\";s:6:\"hidden\";}', 2105693829),
('nextech-cache-setting:payments', 'a:1:{s:7:\"missing\";b:1;}', 2105692834),
('nextech-cache-setting:payouts_IN', 'a:1:{s:7:\"missing\";b:1;}', 2105693074),
('nextech-cache-setting:return_window_days', 'a:1:{s:7:\"missing\";b:1;}', 2105692946);

-- --------------------------------------------------------

--
-- Table structure for table `cache_locks`
--

CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `carts`
--

CREATE TABLE `carts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `carts`
--

INSERT INTO `carts` (`id`, `user_id`, `created_at`, `updated_at`) VALUES
(1, 17, '2026-09-09 02:28:05', '2026-09-09 02:28:05'),
(2, 15, '2026-09-09 04:04:42', '2026-09-09 04:04:42'),
(6, 41, '2026-09-23 01:29:40', '2026-09-23 01:29:40');

-- --------------------------------------------------------

--
-- Table structure for table `cart_items`
--

CREATE TABLE `cart_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `cart_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `product_variant_id` bigint(20) UNSIGNED DEFAULT NULL,
  `quantity` int(10) UNSIGNED NOT NULL,
  `unit_price_cents` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `show_on_home` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `image_url`, `is_active`, `show_on_home`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'Mobiles & Smartphones', 'mobiles-smartphones', NULL, '/img/cat/mobiles-smartphones.webp', 1, 0, 1, '2026-09-09 01:12:49', '2026-09-23 23:55:45'),
(2, 'Laptops & Computers', 'laptops-computers', NULL, '/img/cat/laptops-computers.webp', 1, 1, 2, '2026-09-09 01:12:49', '2026-09-14 06:01:19'),
(3, 'Audio & Headphones', 'audio-headphones', NULL, '/img/cat/audio-headphones.jpg', 1, 1, 3, '2026-09-09 01:12:49', '2026-09-14 06:01:19'),
(4, 'Mobile Accessories', 'mobile-accessories', NULL, '/img/cat/mobile-accessories.webp', 1, 1, 4, '2026-09-09 01:12:49', '2026-09-14 06:01:19'),
(5, 'Smart Watches & Wearables', 'smart-watches-wearables', NULL, '/img/cat/smart-watches-wearables.jpg', 1, 1, 5, '2026-09-09 01:12:49', '2026-09-14 06:01:19'),
(6, 'Cameras & Photography', 'cameras-photography', NULL, '/img/cat/cameras-photography.jpg', 1, 1, 6, '2026-09-09 01:12:49', '2026-09-14 06:01:19'),
(7, 'Televisions', 'televisions', NULL, '/img/cat/televisions.jpg', 1, 1, 7, '2026-09-09 01:12:49', '2026-09-14 06:01:19'),
(8, 'Gaming Consoles & Accessories', 'gaming-consoles-accessories', NULL, '/img/cat/gaming-consoles-accessories.png', 1, 1, 8, '2026-09-09 01:12:49', '2026-09-14 06:01:19'),
(9, 'Home Appliances', 'home-appliances', NULL, '/img/cat/home-appliances.jpg', 1, 1, 9, '2026-09-09 01:12:49', '2026-09-14 06:01:19'),
(10, 'Computer Accessories', 'computer-accessories', NULL, '/img/cat/computer-accessories.jpg', 1, 1, 10, '2026-09-09 01:12:49', '2026-09-14 06:01:19'),
(11, 'Power Banks & Chargers', 'power-banks-chargers', NULL, '/img/cat/power-banks-chargers.jpg', 1, 1, 11, '2026-09-09 01:12:49', '2026-09-14 06:01:19'),
(12, 'Storage Devices', 'storage-devices', NULL, '/img/cat/storage-devices.jpg', 1, 1, 12, '2026-09-09 01:12:49', '2026-09-14 06:01:19'),
(13, 'Networking Devices', 'networking-devices', NULL, '/img/cat/networking-devices.jpg', 1, 1, 13, '2026-09-09 01:12:49', '2026-09-14 06:01:19'),
(14, 'Personal Care Electronics', 'personal-care-electronics', NULL, '/img/cat/personal-care-electronics.jpg', 1, 1, 14, '2026-09-09 01:12:49', '2026-09-14 06:01:19'),
(15, 'Kids & Baby Tech', 'kids-and-baby-tech', NULL, '/img/cat/kids-and-baby-tech.webp', 1, 1, 15, '2026-09-09 01:12:49', '2026-09-14 06:01:19'),
(16, 'Office Electronics', 'office-electronics', NULL, '/img/cat/office-electronics.jpg', 1, 1, 16, '2026-09-09 01:12:49', '2026-09-14 06:01:19'),
(17, 'Smart Home', 'smart-home', NULL, '/img/cat/smart-home.webp', 1, 1, 17, '2026-09-09 01:12:49', '2026-09-14 06:01:19'),
(18, 'Health & Fitness Tech', 'health-and-fitness-tech', NULL, '/img/cat/health-and-fitness-tech.jpg', 1, 1, 18, '2026-09-09 01:12:49', '2026-09-14 06:01:19'),
(19, 'Premium & Flagship', 'premium-and-flagship', NULL, '/img/cat/premium-and-flagship.webp', 1, 1, 19, '2026-09-09 01:12:49', '2026-09-14 06:01:19'),
(20, 'Car Electronics', 'car-electronics', NULL, '/img/cat/car-electronics.jpg', 1, 1, 20, '2026-09-09 01:12:49', '2026-09-14 06:01:19');

-- --------------------------------------------------------

--
-- Table structure for table `customer_emails`
--

CREATE TABLE `customer_emails` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `to_email` varchar(190) NOT NULL,
  `kind` varchar(30) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `body_html` longtext DEFAULT NULL,
  `order_id` bigint(20) UNSIGNED DEFAULT NULL,
  `campaign_id` bigint(20) UNSIGNED DEFAULT NULL,
  `status` varchar(12) NOT NULL DEFAULT 'sent',
  `error` varchar(500) DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_campaigns`
--

CREATE TABLE `email_campaigns` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `subject` varchar(200) NOT NULL,
  `body` text NOT NULL,
  `audience` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`audience`)),
  `promotional` tinyint(1) NOT NULL DEFAULT 1,
  `send_at` timestamp NULL DEFAULT NULL,
  `repeat` varchar(10) NOT NULL DEFAULT 'none',
  `status` varchar(12) NOT NULL DEFAULT 'draft',
  `last_run_at` timestamp NULL DEFAULT NULL,
  `sent_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(255) NOT NULL,
  `connection` varchar(255) NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `gift_cards`
--

CREATE TABLE `gift_cards` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `code` varchar(32) NOT NULL,
  `pin_hash` varchar(255) NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `issued_by` bigint(20) UNSIGNED DEFAULT NULL,
  `support_thread_id` bigint(20) UNSIGNED DEFAULT NULL,
  `order_id` bigint(20) UNSIGNED DEFAULT NULL,
  `initial_cents` int(10) UNSIGNED NOT NULL,
  `balance_cents` int(10) UNSIGNED NOT NULL,
  `reason` varchar(200) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `gift_cards`
--

INSERT INTO `gift_cards` (`id`, `code`, `pin_hash`, `user_id`, `issued_by`, `support_thread_id`, `order_id`, `initial_cents`, `balance_cents`, `reason`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'GC-CYJE-482G', '$2y$12$y1Sa7Th/HYauU6FhbHCQhu8adrdaC8ngvAs2kZIoYrupnCNrEyToe', 15, NULL, NULL, NULL, 350, 350, NULL, 1, '2026-09-10 07:56:07', '2026-09-10 07:56:07'),
(2, 'GC-5FSZ-EYUA', '$2y$12$1ntNpBpn/b8YlFxFtGfhsu/HifkYFvZobKfct52QdRKMi7qIcwcba', 17, 15, NULL, NULL, 2350, 0, 'Client not happy with order.', 0, '2026-09-11 00:14:43', '2026-09-11 02:16:33'),
(3, 'GC-HMAV-D8X5', '$2y$12$nmvFAoT7yed7ihnAmTjm5OF7seniTrLaB5cUAFQUnSnuQXU/VvAtG', 17, 15, NULL, NULL, 500, 500, 'Test issue without thread', 1, '2026-09-11 00:54:36', '2026-09-11 00:54:36'),
(4, 'GC-56UL-WBTS', '$2y$12$/hntxfZReWtfKUt98394qOmOsgauRfQg2o5IG9qOQkRVSlFIbxFBi', 17, 15, NULL, NULL, 649, 649, 'broken received.', 1, '2026-09-11 04:43:40', '2026-09-11 04:43:40'),
(5, 'GC-54RK-26XY', '$2y$12$tlJttSZB/koFGZ.TCqQYWuu0ZFKlzxK9tGofSHHbTYXsaMpnGzEYu', 17, 41, 14, 44, 50000, 50000, 'Missing: Laptop side screens', 1, '2026-09-24 01:21:04', '2026-09-24 01:21:04');

-- --------------------------------------------------------

--
-- Table structure for table `gift_card_redemptions`
--

CREATE TABLE `gift_card_redemptions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `gift_card_id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `amount_cents` int(10) UNSIGNED NOT NULL,
  `reversed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `home_tiles`
--

CREATE TABLE `home_tiles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `category_slug` varchar(255) DEFAULT NULL,
  `link_url` varchar(255) DEFAULT NULL,
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `home_tiles`
--

INSERT INTO `home_tiles` (`id`, `title`, `image_url`, `category_slug`, `link_url`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, NULL, NULL, 'mobiles-smartphones', NULL, 1, 1, '2026-09-09 01:12:49', '2026-09-14 23:42:24'),
(2, NULL, NULL, 'laptops-computers', NULL, 2, 1, '2026-09-09 01:12:49', '2026-09-14 23:42:24'),
(3, NULL, NULL, 'audio-headphones', NULL, 3, 1, '2026-09-09 01:12:49', '2026-09-14 23:42:25'),
(4, NULL, NULL, 'mobile-accessories', NULL, 4, 1, '2026-09-09 01:12:49', '2026-09-14 23:42:25'),
(5, NULL, NULL, 'smart-watches-wearables', NULL, 5, 1, '2026-09-09 01:12:49', '2026-09-14 23:42:25'),
(6, NULL, NULL, 'cameras-photography', NULL, 6, 1, '2026-09-09 01:12:49', '2026-09-14 23:42:26'),
(7, NULL, NULL, 'televisions', NULL, 7, 1, '2026-09-09 01:12:49', '2026-09-14 23:42:26'),
(8, NULL, NULL, 'gaming-consoles-accessories', NULL, 8, 1, '2026-09-09 01:12:49', '2026-09-14 23:42:26'),
(9, NULL, NULL, 'home-appliances', NULL, 9, 1, '2026-09-09 01:12:49', '2026-09-14 23:42:27'),
(10, NULL, NULL, 'computer-accessories', NULL, 10, 1, '2026-09-09 01:12:49', '2026-09-14 23:42:27'),
(11, NULL, NULL, 'power-banks-chargers', NULL, 11, 1, '2026-09-09 01:12:49', '2026-09-14 23:42:27'),
(12, NULL, NULL, 'storage-devices', NULL, 12, 1, '2026-09-09 01:12:49', '2026-09-14 23:42:28'),
(13, NULL, NULL, 'networking-devices', NULL, 13, 1, '2026-09-09 01:12:49', '2026-09-14 23:42:28'),
(14, NULL, NULL, 'personal-care-electronics', NULL, 14, 1, '2026-09-09 01:12:49', '2026-09-14 23:42:28'),
(15, NULL, NULL, 'kids-and-baby-tech', NULL, 15, 1, '2026-09-09 01:12:49', '2026-09-14 23:42:29'),
(16, NULL, NULL, 'office-electronics', NULL, 16, 1, '2026-09-09 01:12:49', '2026-09-14 23:42:29'),
(17, NULL, NULL, 'smart-home', NULL, 17, 1, '2026-09-09 01:12:49', '2026-09-14 23:42:29'),
(18, NULL, NULL, 'health-and-fitness-tech', NULL, 18, 1, '2026-09-09 01:12:49', '2026-09-14 23:42:30'),
(19, NULL, NULL, 'premium-and-flagship', NULL, 19, 1, '2026-09-09 01:12:49', '2026-09-14 23:42:30'),
(20, NULL, NULL, 'car-electronics', NULL, 20, 1, '2026-09-09 01:12:49', '2026-09-14 23:42:30');

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` smallint(5) UNSIGNED NOT NULL,
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
-- Table structure for table `label_requests`
--

CREATE TABLE `label_requests` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `shop_id` bigint(20) UNSIGNED NOT NULL,
  `ship_from_address_id` bigint(20) UNSIGNED DEFAULT NULL,
  `items` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`items`)),
  `status` varchar(12) NOT NULL DEFAULT 'requested',
  `note` varchar(500) DEFAULT NULL,
  `admin_note` varchar(500) DEFAULT NULL,
  `label_path` varchar(255) DEFAULT NULL,
  `label_template_id` bigint(20) UNSIGNED DEFAULT NULL,
  `order_package_id` bigint(20) UNSIGNED DEFAULT NULL,
  `handled_by` bigint(20) UNSIGNED DEFAULT NULL,
  `handled_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `label_templates`
--

CREATE TABLE `label_templates` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(80) NOT NULL,
  `size` varchar(8) NOT NULL DEFAULT '4x6',
  `header_text` varchar(80) DEFAULT NULL,
  `logo_url` varchar(500) DEFAULT NULL,
  `footer_note` varchar(300) DEFAULT NULL,
  `show_items` tinyint(1) NOT NULL DEFAULT 1,
  `show_phone` tinyint(1) NOT NULL DEFAULT 0,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `label_templates`
--

INSERT INTO `label_templates` (`id`, `name`, `size`, `header_text`, `logo_url`, `footer_note`, `show_items`, `show_phone`, `is_default`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Standard 4×6 (thermal printer)', '4x6', 'NexTech Shipping', NULL, 'Handle with care — electronics inside.', 1, 0, 1, 1, '2026-09-24 05:19:16', '2026-09-24 05:19:16'),
(2, 'A4 sheet (regular printer)', 'a4', 'NexTech Shipping', NULL, 'Cut along the border and tape it to the package.', 1, 0, 0, 1, '2026-09-24 05:19:16', '2026-09-24 05:19:16');

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
(4, '2026_09_04_054409_create_personal_access_tokens_table', 1),
(5, '2026_09_04_060000_create_categories_table', 1),
(6, '2026_09_04_060001_create_products_table', 1),
(7, '2026_09_04_070000_create_carts_table', 1),
(8, '2026_09_04_070001_create_cart_items_table', 1),
(9, '2026_09_04_080000_create_orders_table', 1),
(10, '2026_09_04_080001_create_order_items_table', 1),
(11, '2026_09_04_090000_create_addresses_table', 1),
(12, '2026_09_04_100000_add_stripe_payment_intent_to_orders_table', 1),
(13, '2026_09_04_110000_create_stripe_events_table', 1),
(14, '2026_09_04_120000_add_is_admin_to_users_table', 1),
(15, '2026_09_04_130000_add_courier_name_to_orders_table', 1),
(16, '2026_09_04_140000_create_auth_otps_table', 1),
(17, '2026_09_04_150000_create_stores_table', 1),
(18, '2026_09_04_150001_add_geo_to_addresses_table', 1),
(19, '2026_09_07_120000_create_settings_table', 1),
(20, '2026_09_07_120001_add_payment_method_to_orders_table', 1),
(21, '2026_09_07_140000_add_fee_breakdown_to_orders_table', 1),
(22, '2026_09_07_160000_create_product_variants_table', 1),
(23, '2026_09_07_160001_add_variant_to_cart_items_table', 1),
(24, '2026_09_07_160002_add_variant_to_order_items_table', 1),
(25, '2026_09_07_180000_relax_address_text_columns', 1),
(26, '2026_09_07_190000_add_delivery_instructions_to_orders_table', 1),
(27, '2026_09_07_200000_rename_preparing_status_to_packing', 1),
(28, '2026_09_07_210000_add_stripe_refund_id_to_orders_table', 1),
(29, '2026_09_07_220000_create_support_threads_table', 1),
(30, '2026_09_07_220001_create_support_messages_table', 1),
(31, '2026_09_07_220002_create_order_refunds_table', 1),
(32, '2026_09_07_230000_add_last_staff_message_at_to_support_threads', 1),
(33, '2026_09_07_240000_add_is_rider_to_users_table', 1),
(34, '2026_09_07_240001_add_delivery_partner_to_orders_table', 1),
(35, '2026_09_07_250000_add_phone_to_users_table', 1),
(36, '2026_09_07_260000_create_banners_table', 1),
(37, '2026_09_07_270000_create_home_tiles_table', 1),
(38, '2026_09_07_280000_create_pages_table', 1),
(39, '2026_09_08_090000_add_placement_to_banners_table', 1),
(40, '2026_09_08_100000_add_compare_at_price_to_products_and_variants', 1),
(41, '2026_09_08_110000_add_compare_at_price_to_order_items_table', 1),
(42, '2026_09_08_120000_add_sections_to_pages_table', 1),
(43, '2026_09_08_130000_add_banner_image_to_pages_table', 1),
(44, '2026_09_08_140000_add_stripe_customer_id_to_users_table', 1),
(45, '2026_09_09_120000_create_store_inventory_table', 2),
(46, '2026_09_09_130000_add_store_id_to_orders_table', 2),
(47, '2026_09_09_140000_add_rider_profile_and_stores', 2),
(48, '2026_09_09_150000_drop_legacy_product_store_scope', 2),
(49, '2026_09_09_160000_add_delivery_confirmation_to_orders', 3),
(50, '2026_09_09_170000_create_rider_reviews_table', 4),
(51, '2026_09_09_180000_add_chat_rating_to_support_threads', 5),
(52, '2026_09_10_000000_add_delivery_offer_to_orders_table', 6),
(53, '2026_09_10_000001_add_rider_offer_counters_to_users_table', 6),
(54, '2026_09_10_010000_create_rider_attendance', 7),
(55, '2026_09_10_020000_add_rider_offers_count_to_users_table', 8),
(56, '2026_09_10_030000_add_rider_daily_target_to_users_table', 9),
(57, '2026_09_10_040000_add_rider_since_to_users_table', 10),
(58, '2026_09_10_050000_add_receipt_emailed_at_to_orders_table', 11),
(59, '2026_09_10_060000_create_gift_cards', 12),
(60, '2026_09_11_000000_add_cash_settled_at_to_orders_table', 13),
(61, '2026_09_11_010000_add_cash_collected_at_to_orders_table', 14),
(62, '2026_09_11_020000_add_reversed_at_to_gift_card_redemptions_table', 15),
(63, '2026_09_11_030000_add_cancellation_reason_to_orders_table', 16),
(64, '2026_09_11_040000_add_items_returned_at_to_orders_table', 17),
(65, '2026_09_11_050000_add_internal_to_support_messages_table', 18),
(66, '2026_09_14_000000_create_product_reviews_table', 19),
(67, '2026_09_14_010000_add_rating_and_sold_to_products_table', 19),
(68, '2026_09_14_020000_add_sold_counted_at_to_orders_table', 19),
(69, '2026_09_16_120000_add_deal_flags_to_products_table', 20),
(70, '2026_09_17_112617_create_site_feedback_table', 20),
(71, '2026_09_17_120000_ensure_product_rating_columns', 21),
(72, '2026_09_18_090000_add_video_url_to_products_table', 22),
(73, '2026_09_15_180000_add_attachment_url_to_support_messages_table', 23),
(75, '2026_09_22_000000_add_country_to_stores_table', 24),
(76, '2026_09_22_000001_create_sellers_table', 24),
(77, '2026_09_22_000002_create_shops_table', 24),
(78, '2026_09_22_000003_add_shop_id_to_products_table', 24),
(79, '2026_09_22_000004_add_delivery_method_to_orders_table', 25),
(80, '2026_09_22_000005_create_shipments_table', 25),
(81, '2026_09_22_000006_add_shop_id_to_order_items_table', 26),
(82, '2026_09_22_000007_create_seller_ledger_entries_table', 26),
(83, '2026_09_22_000008_add_status_to_products_table', 27),
(84, '2026_09_22_000009_create_product_images_table', 28),
(85, '2026_09_22_000010_add_payout_fields_to_sellers_table', 29),
(86, '2026_09_23_000001_add_pickup_address_to_sellers_table', 30),
(88, '2026_09_23_000002_add_menu_placements_to_pages_table', 31),
(89, '2026_09_23_000003_add_sku_generation_columns', 32),
(90, '2026_09_24_000001_backfill_legacy_product_skus', 33),
(91, '2026_09_24_000002_backfill_product_main_image_from_gallery', 34),
(92, '2026_09_24_000003_create_payout_requests_table', 35),
(93, '2026_09_24_000004_create_rider_hiring_and_pay_tables', 36),
(94, '2026_09_24_000005_add_show_on_home_to_categories', 37),
(95, '2026_09_24_000006_add_seller_to_support_threads', 38),
(98, '2026_09_24_000007_add_prohibited_products_seller_page', 39),
(99, '2026_09_24_000008_add_anti_fraud_seller_page', 40),
(100, '2026_09_24_000009_add_fulfillment_and_epr_seller_pages', 41),
(101, '2026_09_24_000010_add_data_protection_seller_page', 42),
(102, '2026_09_24_000011_add_advertising_terms_seller_page', 43),
(103, '2026_09_24_000012_add_seller_cookies_policy_page', 44),
(104, '2026_09_24_000013_add_page_parents_and_seller_privacy', 45),
(105, '2026_09_24_000014_add_face_verification_seller_page', 46),
(106, '2026_09_24_000015_add_seller_services_agreement_page', 47),
(107, '2026_09_24_000016_retire_old_seller_terms_page', 48),
(108, '2026_09_24_000017_add_return_window_to_products', 49),
(109, '2026_09_24_000018_add_return_days_to_order_items', 50),
(110, '2026_09_24_000019_add_attachments_to_support_messages', 51),
(111, '2026_09_24_000020_add_hidden_from_seller_to_support_messages', 52),
(112, '2026_09_24_000021_convert_ended_chat_messages_to_system', 53),
(113, '2026_09_24_000022_create_seller_shipping_tables', 54),
(114, '2026_09_24_000023_add_markets', 55),
(115, '2026_09_24_000024_create_label_requests_table', 56),
(116, '2026_09_24_000025_add_label_path_to_label_requests', 57),
(117, '2026_09_24_000026_create_label_templates_table', 58),
(118, '2026_09_25_000027_create_crm_email_tables', 59),
(119, '2026_09_25_000028_add_seller_onboarding_tasks', 60),
(120, '2026_09_25_000029_add_address_types_to_shipping_template_groups', 61),
(121, '2026_09_25_000030_create_order_address_changes_table', 62),
(122, '2026_09_25_000031_add_temu_style_product_listing', 63),
(123, '2026_09_25_000032_make_product_category_nullable_for_drafts', 64),
(124, '2026_09_25_000033_create_store_decorations_table', 65),
(125, '2026_09_25_000034_create_product_reviews_table', 66);

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `market` varchar(2) NOT NULL DEFAULT 'US',
  `currency` varchar(3) NOT NULL DEFAULT 'usd',
  `store_id` bigint(20) UNSIGNED DEFAULT NULL,
  `delivery_method` varchar(255) NOT NULL DEFAULT 'own_rider',
  `status` varchar(255) NOT NULL DEFAULT 'pending_payment',
  `cancelled_by` varchar(20) DEFAULT NULL,
  `cancel_reason` varchar(300) DEFAULT NULL,
  `items_returned_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `cash_settled_at` timestamp NULL DEFAULT NULL,
  `cash_collected_at` timestamp NULL DEFAULT NULL,
  `receipt_emailed_at` timestamp NULL DEFAULT NULL,
  `sold_counted_at` timestamp NULL DEFAULT NULL,
  `delivery_verified` tinyint(1) DEFAULT NULL,
  `delivery_note` varchar(300) DEFAULT NULL,
  `delivery_code` varchar(8) DEFAULT NULL,
  `delivery_code_expires_at` timestamp NULL DEFAULT NULL,
  `courier_name` varchar(255) DEFAULT NULL,
  `delivery_partner_id` bigint(20) UNSIGNED DEFAULT NULL,
  `rider_offer_expires_at` timestamp NULL DEFAULT NULL,
  `rider_accepted_at` timestamp NULL DEFAULT NULL,
  `rider_offer_declined_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`rider_offer_declined_ids`)),
  `rider_offer_decline_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `payment_status` varchar(255) NOT NULL DEFAULT 'pending',
  `payment_method` varchar(255) NOT NULL DEFAULT 'card',
  `stripe_payment_intent_id` varchar(255) DEFAULT NULL,
  `stripe_refund_id` varchar(255) DEFAULT NULL,
  `refunded_amount_cents` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `subtotal_cents` int(10) UNSIGNED NOT NULL,
  `tax_cents` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `tax_included_cents` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `delivery_fee_cents` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `seller_shipping_cents` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `handling_fee_cents` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `small_cart_fee_cents` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `gift_card_discount_cents` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `total_cents` int(10) UNSIGNED NOT NULL,
  `delivery_address` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`delivery_address`)),
  `delivery_instructions` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `market`, `currency`, `store_id`, `delivery_method`, `status`, `cancelled_by`, `cancel_reason`, `items_returned_at`, `delivered_at`, `cash_settled_at`, `cash_collected_at`, `receipt_emailed_at`, `sold_counted_at`, `delivery_verified`, `delivery_note`, `delivery_code`, `delivery_code_expires_at`, `courier_name`, `delivery_partner_id`, `rider_offer_expires_at`, `rider_accepted_at`, `rider_offer_declined_ids`, `rider_offer_decline_count`, `payment_status`, `payment_method`, `stripe_payment_intent_id`, `stripe_refund_id`, `refunded_amount_cents`, `subtotal_cents`, `tax_cents`, `tax_included_cents`, `delivery_fee_cents`, `seller_shipping_cents`, `handling_fee_cents`, `small_cart_fee_cents`, `gift_card_discount_cents`, `total_cents`, `delivery_address`, `delivery_instructions`, `created_at`, `updated_at`) VALUES
(27, 17, 'US', 'usd', 1, 'own_rider', 'completed', NULL, NULL, NULL, '2026-09-14 06:27:52', '2026-09-23 02:35:38', '2026-09-14 06:27:39', '2026-09-14 06:27:53', NULL, 0, 'delivered.', NULL, NULL, 'Sam Rider', 16, NULL, NULL, NULL, 0, 'paid', 'cod', NULL, NULL, 0, 1499, 133, 0, 299, 0, 99, 0, 0, 2030, '{\"id\":17,\"user_id\":17,\"label\":\"Home\",\"name\":\"Testcaresort\",\"line1\":\"34 Jan Marg\",\"line2\":null,\"city\":\"Mohali\",\"state\":\"PB\",\"postal_code\":\"160061\",\"latitude\":30.7149794,\"longitude\":76.7227993,\"is_default\":false,\"created_at\":\"2026-09-14T11:44:06.000000Z\",\"updated_at\":\"2026-09-14T11:44:06.000000Z\",\"phone\":\"+15551234567\"}', NULL, '2026-09-14 06:14:06', '2026-09-23 02:35:38'),
(28, 15, 'US', 'usd', 1, 'online_courier', 'completed', NULL, NULL, NULL, '2026-09-23 04:38:24', NULL, '2026-09-23 00:16:30', '2026-09-23 04:38:30', NULL, 0, 'Delivered by online courier (MockCourier MOCK-VJTBW77GDR).', NULL, NULL, 'MockCourier', NULL, NULL, NULL, NULL, 0, 'paid', 'cod', NULL, NULL, 0, 8997, 798, 0, 0, 0, 99, 0, 0, 9894, '{\"id\":18,\"user_id\":15,\"label\":\"Home\",\"name\":\"Test User\",\"line1\":\"SH12A\",\"line2\":null,\"city\":\"Mohali\",\"state\":\"Punjab\",\"postal_code\":\"160070\",\"latitude\":30.6910873,\"longitude\":76.7137098,\"is_default\":false,\"created_at\":\"2026-09-18T10:33:22.000000Z\",\"updated_at\":\"2026-09-18T10:33:22.000000Z\",\"phone\":\"+15551234567\"}', NULL, '2026-09-18 05:03:23', '2026-09-23 04:38:30'),
(40, 17, 'US', 'usd', 1, 'own_rider', 'cancelled', 'admin', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'cancelled', 'card', NULL, NULL, 0, 50000, 4435, 0, 0, 0, 99, 0, 0, 54534, '{\"id\":19,\"user_id\":17,\"label\":\"Home\",\"name\":\"Testcaresort\",\"line1\":\"edge 27\",\"line2\":null,\"city\":\"Mohali\",\"state\":\"Punjab\",\"postal_code\":\"160055\",\"latitude\":30.7197622,\"longitude\":76.7056954,\"is_default\":false,\"created_at\":\"2026-09-23T06:48:18.000000Z\",\"updated_at\":\"2026-09-23T06:48:18.000000Z\",\"phone\":\"+15551234567\"}', NULL, '2026-09-23 01:18:19', '2026-09-23 01:28:18'),
(41, 17, 'US', 'usd', 1, 'own_rider', 'cancelled', 'admin', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'cancelled', 'card', NULL, NULL, 0, 9998, 887, 0, 0, 0, 99, 0, 0, 10984, '{\"id\":20,\"user_id\":17,\"label\":\"Home\",\"name\":\"Testcaresort\",\"line1\":\"edge 27\",\"line2\":null,\"city\":\"Mohali\",\"state\":\"Punjab\",\"postal_code\":\"160055\",\"latitude\":30.7197622,\"longitude\":76.7056954,\"is_default\":false,\"created_at\":\"2026-09-23T06:48:55.000000Z\",\"updated_at\":\"2026-09-23T06:48:55.000000Z\",\"phone\":\"+15551234567\"}', NULL, '2026-09-23 01:18:56', '2026-09-23 01:28:19'),
(42, 17, 'US', 'usd', 1, 'own_rider', 'cancelled', 'admin', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'cancelled', 'card', NULL, NULL, 0, 59998, 5322, 0, 0, 0, 99, 0, 0, 65419, '{\"id\":21,\"user_id\":17,\"label\":\"Home\",\"name\":\"Testcaresort\",\"line1\":\"edge 27\",\"line2\":null,\"city\":\"Mohali\",\"state\":\"Punjab\",\"postal_code\":\"160055\",\"latitude\":30.7197622,\"longitude\":76.7056954,\"is_default\":false,\"created_at\":\"2026-09-23T06:52:07.000000Z\",\"updated_at\":\"2026-09-23T06:52:07.000000Z\",\"phone\":\"+15551234567\"}', NULL, '2026-09-23 01:22:08', '2026-09-23 01:28:20'),
(43, 17, 'US', 'usd', 1, 'own_rider', 'cancelled', 'admin', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'cancelled', 'card', NULL, NULL, 0, 59998, 5322, 0, 0, 0, 99, 0, 0, 65419, '{\"id\":22,\"user_id\":17,\"label\":\"Home\",\"name\":\"Testcaresort\",\"line1\":\"edge 27\",\"line2\":null,\"city\":\"Mohali\",\"state\":\"Punjab\",\"postal_code\":\"160055\",\"latitude\":30.7197622,\"longitude\":76.7056954,\"is_default\":true,\"created_at\":\"2026-09-23T06:52:21.000000Z\",\"updated_at\":\"2026-09-23T06:52:21.000000Z\",\"phone\":\"+15551234567\"}', NULL, '2026-09-23 01:22:22', '2026-09-23 01:28:28'),
(44, 17, 'US', 'usd', 1, 'own_rider', 'completed', NULL, NULL, NULL, NULL, NULL, '2026-09-23 01:28:48', '2026-09-23 01:29:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'paid', 'cod', NULL, NULL, 0, 59998, 5322, 0, 0, 0, 99, 0, 0, 65419, '{\"id\":23,\"user_id\":17,\"label\":\"Home\",\"name\":\"Testcaresort\",\"line1\":\"edge 27\",\"line2\":null,\"city\":\"Mohali\",\"state\":\"Punjab\",\"postal_code\":\"160055\",\"latitude\":30.7197622,\"longitude\":76.7056954,\"is_default\":false,\"created_at\":\"2026-09-23T06:53:55.000000Z\",\"updated_at\":\"2026-09-23T06:53:55.000000Z\",\"phone\":\"+15551234567\"}', NULL, '2026-09-23 01:23:56', '2026-09-23 01:29:00'),
(45, 41, 'US', 'usd', 1, 'own_rider', 'completed', NULL, NULL, NULL, NULL, NULL, '2026-09-23 01:30:24', '2026-09-23 01:30:31', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'paid', 'cod', NULL, NULL, 0, 50000, 4435, 0, 0, 0, 99, 0, 0, 54534, '{\"id\":24,\"user_id\":41,\"label\":\"Home\",\"name\":\"Testcaresort\",\"line1\":\"edge 27\",\"line2\":null,\"city\":\"Mohali\",\"state\":\"Punjab\",\"postal_code\":\"160055\",\"latitude\":30.7197622,\"longitude\":76.7056954,\"is_default\":true,\"created_at\":\"2026-09-23T06:59:41.000000Z\",\"updated_at\":\"2026-09-23T06:59:41.000000Z\",\"phone\":\"+15551234567\"}', NULL, '2026-09-23 01:29:41', '2026-09-23 01:30:31'),
(46, 41, 'US', 'usd', 1, 'own_rider', 'completed', NULL, NULL, NULL, NULL, NULL, '2026-09-23 01:34:08', '2026-09-23 01:34:14', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'paid', 'cod', NULL, NULL, 0, 50000, 4435, 0, 0, 0, 99, 0, 0, 54534, '{\"id\":25,\"user_id\":41,\"label\":\"Home\",\"name\":\"Testcaresort\",\"line1\":\"edge 27\",\"line2\":null,\"city\":\"Mohali\",\"state\":\"Punjab\",\"postal_code\":\"160055\",\"latitude\":30.7197622,\"longitude\":76.7056954,\"is_default\":false,\"created_at\":\"2026-09-23T07:03:52.000000Z\",\"updated_at\":\"2026-09-23T07:03:52.000000Z\",\"phone\":\"+15551234567\"}', NULL, '2026-09-23 01:33:53', '2026-09-25 04:03:23');

-- --------------------------------------------------------

--
-- Table structure for table `order_address_changes`
--

CREATE TABLE `order_address_changes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `address` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`address`)),
  `status` varchar(12) NOT NULL DEFAULT 'pending',
  `note` varchar(500) DEFAULT NULL,
  `decided_by_shop_id` bigint(20) UNSIGNED DEFAULT NULL,
  `decided_by_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `decided_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `product_variant_id` bigint(20) UNSIGNED DEFAULT NULL,
  `shop_id` bigint(20) UNSIGNED DEFAULT NULL,
  `fulfilled_by` varchar(10) NOT NULL DEFAULT 'nextech',
  `product_name` varchar(255) NOT NULL,
  `sku` varchar(255) NOT NULL,
  `hsn_code` varchar(8) DEFAULT NULL,
  `gst_rate_bps` smallint(5) UNSIGNED DEFAULT NULL,
  `variant_label` varchar(255) DEFAULT NULL,
  `quantity` int(10) UNSIGNED NOT NULL,
  `unit_price_cents` int(10) UNSIGNED NOT NULL,
  `compare_at_price_cents` int(10) UNSIGNED DEFAULT NULL,
  `return_days` smallint(5) UNSIGNED DEFAULT NULL,
  `line_total_cents` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `product_variant_id`, `shop_id`, `fulfilled_by`, `product_name`, `sku`, `hsn_code`, `gst_rate_bps`, `variant_label`, `quantity`, `unit_price_cents`, `compare_at_price_cents`, `return_days`, `line_total_cents`, `created_at`, `updated_at`) VALUES
(30, 27, 17, NULL, NULL, 'nextech', 'Silicone Phone Case', 'GDP-PROD-017', NULL, NULL, NULL, 1, 1499, NULL, NULL, 1499, '2026-09-14 06:14:06', '2026-09-14 06:14:06'),
(31, 28, 46, NULL, NULL, 'nextech', 'Philips Hair Dryer', 'GDP-PROD-046', NULL, NULL, NULL, 3, 2999, NULL, NULL, 8997, '2026-09-18 05:03:23', '2026-09-18 05:03:23'),
(47, 40, 78, NULL, 3, 'nextech', 'Laptop side screens', 'SLR-PROD-048', NULL, NULL, NULL, 1, 50000, 70000, NULL, 50000, '2026-09-23 01:18:19', '2026-09-23 01:18:19'),
(48, 41, 37, NULL, NULL, 'nextech', 'Anker 20000mAh Power Bank', 'GDP-PROD-037', NULL, NULL, NULL, 2, 4999, NULL, NULL, 9998, '2026-09-23 01:18:56', '2026-09-23 01:18:56'),
(49, 42, 37, NULL, NULL, 'nextech', 'Anker 20000mAh Power Bank', 'GDP-PROD-037', NULL, NULL, NULL, 2, 4999, NULL, NULL, 9998, '2026-09-23 01:22:08', '2026-09-23 01:22:08'),
(50, 42, 78, NULL, 3, 'nextech', 'Laptop side screens', 'SLR-PROD-048', NULL, NULL, NULL, 1, 50000, 70000, NULL, 50000, '2026-09-23 01:22:08', '2026-09-23 01:22:08'),
(51, 43, 37, NULL, NULL, 'nextech', 'Anker 20000mAh Power Bank', 'GDP-PROD-037', NULL, NULL, NULL, 2, 4999, NULL, NULL, 9998, '2026-09-23 01:22:22', '2026-09-23 01:22:22'),
(52, 43, 78, NULL, 3, 'nextech', 'Laptop side screens', 'SLR-PROD-048', NULL, NULL, NULL, 1, 50000, 70000, NULL, 50000, '2026-09-23 01:22:22', '2026-09-23 01:22:22'),
(53, 44, 37, NULL, NULL, 'nextech', 'Anker 20000mAh Power Bank', 'GDP-PROD-037', NULL, NULL, NULL, 2, 4999, NULL, NULL, 9998, '2026-09-23 01:23:56', '2026-09-23 01:23:56'),
(54, 44, 78, NULL, 3, 'nextech', 'Laptop side screens', 'SLR-PROD-048', NULL, NULL, NULL, 1, 50000, 70000, NULL, 50000, '2026-09-23 01:23:56', '2026-09-23 01:23:56'),
(55, 45, 78, NULL, 3, 'nextech', 'Laptop side screens', 'SLR-PROD-048', NULL, NULL, NULL, 1, 50000, 70000, NULL, 50000, '2026-09-23 01:29:41', '2026-09-23 01:29:41'),
(56, 46, 78, NULL, 3, 'nextech', 'Laptop side screens', 'SLR-PROD-048', NULL, NULL, NULL, 1, 50000, 70000, NULL, 50000, '2026-09-23 01:33:53', '2026-09-23 01:33:53');

-- --------------------------------------------------------

--
-- Table structure for table `order_packages`
--

CREATE TABLE `order_packages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `shop_id` bigint(20) UNSIGNED NOT NULL,
  `ship_from_address_id` bigint(20) UNSIGNED DEFAULT NULL,
  `label_source` varchar(10) NOT NULL DEFAULT 'own',
  `carrier` varchar(40) NOT NULL,
  `tracking_number` varchar(60) NOT NULL,
  `label_url` varchar(500) DEFAULT NULL,
  `label_path` varchar(255) DEFAULT NULL,
  `label_cost_cents` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT 'shipped',
  `shipped_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `delivered_at` timestamp NULL DEFAULT NULL,
  `edit_count` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `last_edited_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_package_items`
--

CREATE TABLE `order_package_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_package_id` bigint(20) UNSIGNED NOT NULL,
  `order_item_id` bigint(20) UNSIGNED NOT NULL,
  `quantity` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_refunds`
--

CREATE TABLE `order_refunds` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `support_thread_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `amount_cents` int(10) UNSIGNED NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `stripe_refund_id` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_shop_shipping`
--

CREATE TABLE `order_shop_shipping` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `shop_id` bigint(20) UNSIGNED NOT NULL,
  `mode` varchar(12) NOT NULL,
  `fee_cents` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `free_shipping` tinyint(1) NOT NULL DEFAULT 0,
  `transit_min_days` tinyint(3) UNSIGNED NOT NULL,
  `transit_max_days` tinyint(3) UNSIGNED NOT NULL,
  `ship_by` date NOT NULL,
  `deliver_from` date NOT NULL,
  `deliver_by` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pages`
--

CREATE TABLE `pages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `slug` varchar(255) NOT NULL,
  `parent_slug` varchar(160) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `banner_image` varchar(255) DEFAULT NULL,
  `content` longtext DEFAULT NULL,
  `sections` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`sections`)),
  `is_published` tinyint(1) NOT NULL DEFAULT 1,
  `show_in_footer` tinyint(1) NOT NULL DEFAULT 1,
  `footer_group` varchar(255) NOT NULL DEFAULT 'useful_links',
  `menu_placements` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`menu_placements`)),
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pages`
--

INSERT INTO `pages` (`id`, `slug`, `parent_slug`, `title`, `banner_image`, `content`, `sections`, `is_published`, `show_in_footer`, `footer_group`, `menu_placements`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'about', NULL, 'About Us', NULL, 'NexTech delivers phones, laptops, audio gear and smart home tech to your door, fast.', '[{\"type\":\"hero\",\"image_url\":\"\\/img\\/pages\\/about-hero.jpg\",\"heading\":\"Electronics at your door, fast\",\"text\":\"NexTech is a demo storefront for fast electronics delivery \\u2014 phones, laptops, audio and smart home gear, picked and delivered from a store near you.\",\"button_label\":\"Start shopping\",\"button_url\":\"#\\/\"},{\"type\":\"stats\",\"heading\":\"NexTech by the numbers\",\"items\":[{\"title\":\"~30 min\",\"text\":\"Average delivery time\"},{\"title\":\"20+\",\"text\":\"Categories in stock\"},{\"title\":\"4.7 \\/ 5\",\"text\":\"Average order rating\"},{\"title\":\"Every day\",\"text\":\"New arrivals restocked\"}]},{\"type\":\"feature_grid\",\"heading\":\"Why shop with us\",\"items\":[{\"title\":\"Same-day delivery\",\"text\":\"Orders leave the nearest store within minutes of checkout.\"},{\"title\":\"Real prices\",\"text\":\"Everyday low prices with discounts shown clearly \\u2014 no surprises at checkout.\"},{\"title\":\"Genuine stock\",\"text\":\"Every listing is checked against live inventory before it ships.\"},{\"title\":\"Easy returns\",\"text\":\"Raise an issue from your order history and get a fast refund.\"}]},{\"type\":\"steps\",\"heading\":\"How it works\",\"items\":[{\"title\":\"Fill your cart\",\"text\":\"Browse the catalog and add what you need. Prices and offers are shown upfront.\"},{\"title\":\"Check out in a tap\",\"text\":\"Pay by card or cash on delivery \\u2014 the fee and ETA are confirmed before you pay.\"},{\"title\":\"We pick and pack\",\"text\":\"Your order is assembled and boxed at the nearest store within minutes.\"},{\"title\":\"Delivered to your door\",\"text\":\"Track it on the way; hand over cash on arrival if you chose that.\"}]},{\"type\":\"media_text\",\"image_url\":\"\\/img\\/pages\\/about-story.jpg\",\"image_side\":\"left\",\"heading\":\"Our story\",\"markdown\":\"NexTech started as a single neighbourhood electronics counter and now runs a small network of local hubs.\\n\\nThis whole site is a **demo build** \\u2014 every page here, including this one, is editable in **Admin -> Pages** using drag-and-drop sections.\"},{\"type\":\"media_text\",\"image_url\":\"\\/img\\/pages\\/about-hero.jpg\",\"image_side\":\"right\",\"heading\":\"From local stores, not a warehouse\",\"markdown\":\"We stock and dispatch from small hubs inside your neighbourhood, so gear travels metres, not miles.\\n\\nShorter journeys mean faster delivery, less packaging waste and a rider who can be at your door before your old charger even gives out.\"},{\"type\":\"quote\",\"text\":\"My charging cable died at 9pm and a new one was at my door before the shops closed. Genuinely faster than driving to the mall.\",\"author\":\"Priya M. \\u2014 early tester\"},{\"type\":\"feature_grid\",\"heading\":\"On the roadmap\",\"items\":[{\"title\":\"Scheduled delivery\",\"text\":\"Pick a future time slot, not just \\u201cas soon as possible\\u201d.\"},{\"title\":\"More neighbourhoods\",\"text\":\"New store hubs opening across the city through the year.\"},{\"title\":\"Loyalty perks\",\"text\":\"Rewards and member pricing for regulars, coming soon.\"}]},{\"type\":\"cta\",\"heading\":\"Need an upgrade already?\",\"text\":\"Browse thousands of items and check out in under a minute.\",\"button_label\":\"Shop now\",\"button_url\":\"#\\/\"}]', 1, 1, 'company', '[\"main_footer\"]', 1, '2026-09-09 01:12:49', '2026-09-18 04:29:51'),
(2, 'blog', NULL, 'Blog', NULL, 'Buying guides, deals and a look behind the delivery promise.', '[{\"type\":\"hero\",\"image_url\":\"\\/img\\/pages\\/blog-seasonal.jpg\",\"heading\":\"The NexTech Blog\",\"text\":\"Buying guides, tech tips and a look behind the fast-delivery promise.\"},{\"type\":\"feature_grid\",\"heading\":\"Latest posts\",\"items\":[{\"image_url\":\"\\/img\\/pages\\/blog-delivery.jpg\",\"title\":\"How we get your order shipped fast\",\"text\":\"A look under the hood of the delivery promise \\u2014 from stocked hubs to planned routes.\",\"link_url\":\"#\\/p\\/blog-fast-shipping\"},{\"image_url\":\"\\/img\\/pages\\/blog-dinner.jpg\",\"title\":\"5 easy home office upgrades under $50\",\"text\":\"Small, cheap changes that make working from home noticeably better.\",\"link_url\":\"#\\/p\\/blog-home-office-upgrades\"},{\"image_url\":\"\\/img\\/pages\\/blog-seasonal.jpg\",\"title\":\"The best time of year to buy tech\",\"text\":\"When prices actually drop on phones, laptops and audio \\u2014 and when to wait.\",\"link_url\":\"#\\/p\\/blog-seasonal-tech-deals\"},{\"image_url\":\"\\/img\\/pages\\/blog-waste.jpg\",\"title\":\"7 ways to cut down on e-waste\",\"text\":\"Small habits that keep old devices out of landfill \\u2014 and sometimes put cash back in your pocket.\",\"link_url\":\"#\\/p\\/blog-reduce-ewaste\"}]},{\"type\":\"stats\",\"heading\":\"The blog so far\",\"items\":[{\"title\":\"4\",\"text\":\"Posts published\"},{\"title\":\"~5 min\",\"text\":\"Average read\"},{\"title\":\"Weekly\",\"text\":\"New posts (soon)\"},{\"title\":\"0\",\"text\":\"Sponsored posts\"}]},{\"type\":\"feature_grid\",\"heading\":\"Browse by topic\",\"items\":[{\"title\":\"Buying guides\",\"text\":\"What to look for before you spend on phones, laptops and audio.\"},{\"title\":\"Deals\",\"text\":\"When prices actually drop, and how to tell a real deal from a markup.\"},{\"title\":\"Behind the scenes\",\"text\":\"How the store hubs, picking and routing actually work.\"},{\"title\":\"Sustainability\",\"text\":\"Less e-waste, less packaging, shorter journeys.\"}]},{\"type\":\"quote\",\"text\":\"Short, useful and no fluff \\u2014 I actually followed the home office guide the same evening I read it.\",\"author\":\"Alex R. \\u2014 newsletter subscriber\"},{\"type\":\"media_text\",\"image_url\":\"\\/img\\/pages\\/blog-dinner.jpg\",\"image_side\":\"right\",\"heading\":\"Write for us\",\"markdown\":\"Got a sharp buying guide, a setup tour or a strong opinion about USB-C? We publish guest posts.\\n\\nEmail **hello@nextech.example** with a two-line pitch. This is a demo build, so treat these as sample posts you can replace in **Admin -> Pages**.\"},{\"type\":\"rich_text\",\"markdown\":\"**Editorial note** \\u2014 nothing here is sponsored. Product mentions are picked by the writer, and prices and availability shown in posts can change.\"},{\"type\":\"cta\",\"heading\":\"Get new posts by email\",\"text\":\"A subscribe box is coming soon \\u2014 for now, check back weekly for the next one.\"}]', 1, 1, 'company', '[\"main_footer\"]', 2, '2026-09-09 01:12:49', '2026-09-18 04:31:02'),
(3, 'blog-fast-shipping', NULL, 'How we get your order shipped fast', NULL, 'A look under the hood of the NexTech shipping promise.', '[{\"type\":\"hero\",\"image_url\":\"\\/img\\/pages\\/blog-delivery.jpg\",\"heading\":\"How we get your order shipped fast\",\"text\":\"From stocked local hubs to routes built for your address \\u2014 a look under the hood.\"},{\"type\":\"stats\",\"heading\":\"The promise in numbers\",\"items\":[{\"title\":\"Same day\",\"text\":\"Dispatch in serviceable areas\"},{\"title\":\"1\\u20133 days\",\"text\":\"Typical delivery window\"},{\"title\":\"Every unit\",\"text\":\"Scanned before it ships\"},{\"title\":\"Live tracking\",\"text\":\"Shown after checkout\"}]},{\"type\":\"rich_text\",\"markdown\":\"### It starts at a local hub, not one giant warehouse\\nInstead of a single depot on the edge of town, we stock small hubs closer to our customers, so your order doesn\'t have to travel far to start moving.\\n\\n### Careful packing\\nElectronics get extra padding and a tamper-evident seal. Fragile items like TVs and monitors are boxed and checked twice before they leave.\\n\\n### Planned routes\\nCouriers leave with a route built for your address, so the last mile doesn\'t eat the time we saved upstream.\\n\\n### What can slow it down\\nStock availability, distance from the nearest hub, or an address we can\'t place on the map. You\'ll always see an estimated delivery window before you pay.\"},{\"type\":\"media_text\",\"image_url\":\"\\/img\\/pages\\/about-story.jpg\",\"image_side\":\"left\",\"heading\":\"Why a hub beats a warehouse\",\"markdown\":\"A single warehouse on the edge of the city is efficient for trucks, not for you.\\n\\nOur hubs carry the products people actually reorder, closer to where you live \\u2014 less range on the shelf, far less distance to your door.\"},{\"type\":\"feature_grid\",\"heading\":\"What we optimise for\",\"items\":[{\"title\":\"Distance\",\"text\":\"Kilometres from hub to door, not the country.\"},{\"title\":\"Careful handling\",\"text\":\"Fragile electronics get extra padding, always.\"},{\"title\":\"Real tracking\",\"text\":\"A status you can trust, updated as it moves.\"},{\"title\":\"Route quality\",\"text\":\"Delivery windows built around your address.\"}]},{\"type\":\"steps\",\"heading\":\"From order to doorstep\",\"items\":[{\"title\":\"Order placed\",\"text\":\"Your order is checked for stock at the nearest hub.\"},{\"title\":\"Packed and sealed\",\"text\":\"A second person verifies every item before boxing.\"},{\"title\":\"Courier dispatched\",\"text\":\"With a route built for your address.\"},{\"title\":\"At your door\",\"text\":\"Same day where available, within a few days otherwise.\"}]},{\"type\":\"quote\",\"text\":\"I ordered a monitor in the morning and it was on my desk by evening, still sealed in the manufacturer\'s box.\",\"author\":\"Dan K. \\u2014 verified buyer\"},{\"type\":\"rich_text\",\"markdown\":\"### A few things people ask\\n**Can I add to an order after checkout?** Not once packing starts \\u2014 place a second order and we\'ll try to align delivery.\\n\\n**What if I\'m not in?** The courier attempts contact, then holds the parcel at a nearby point or arranges a retry.\\n\\n**Do you ship everywhere?** Only inside a serviceable area for now. Enter your address at checkout to check.\"},{\"type\":\"cta\",\"heading\":\"See your delivery window\",\"text\":\"Enter your address and add items to your cart to see an estimate.\",\"button_label\":\"Start shopping\",\"button_url\":\"#\\/\"},{\"type\":\"feature_grid\",\"heading\":\"Keep reading\",\"items\":[{\"image_url\":\"\\/img\\/pages\\/blog-dinner.jpg\",\"title\":\"5 easy home office upgrades under $50\",\"text\":\"Small buys that make working from home better.\",\"link_url\":\"#\\/p\\/blog-home-office-upgrades\"},{\"image_url\":\"\\/img\\/pages\\/blog-seasonal.jpg\",\"title\":\"The best time of year to buy tech\",\"text\":\"When prices actually drop, by category.\",\"link_url\":\"#\\/p\\/blog-seasonal-tech-deals\"},{\"image_url\":\"\\/img\\/pages\\/blog-waste.jpg\",\"title\":\"7 ways to cut down on e-waste\",\"text\":\"Get more life out of what you already own.\",\"link_url\":\"#\\/p\\/blog-reduce-ewaste\"}]}]', 1, 0, 'blog', '[\"blog\"]', 1, '2026-09-09 01:12:49', '2026-09-14 06:33:33'),
(4, 'blog-home-office-upgrades', NULL, '5 easy home office upgrades under $50', NULL, 'Small, cheap upgrades that make working from home noticeably better.', '[{\"type\":\"hero\",\"image_url\":\"\\/img\\/pages\\/blog-dinner.jpg\",\"heading\":\"5 easy home office upgrades under $50\",\"text\":\"No big renovation needed \\u2014 five small buys that make a real difference.\"},{\"type\":\"rich_text\",\"markdown\":\"Keep a few basics on hand and any of these takes minutes to set up.\\n\\n1. **A laptop stand** \\u2014 raises your screen to eye level and frees up desk space underneath.\\n2. **A wireless mouse and keyboard** \\u2014 less cable clutter, more room to move.\\n3. **A USB-C hub** \\u2014 one cable in, everything else plugged in.\\n4. **A webcam with a physical shutter** \\u2014 sharper video calls, and privacy when you\'re not on one.\\n5. **A basic desk lamp** \\u2014 better lighting fixes video calls and eye strain in one move.\"},{\"type\":\"stats\",\"heading\":\"Why this is worth doing\",\"items\":[{\"title\":\"Under $50\",\"text\":\"Total for most of these\"},{\"title\":\"~10 min\",\"text\":\"Typical setup time\"},{\"title\":\"No tools\",\"text\":\"For most of them\"},{\"title\":\"0\",\"text\":\"Special skills needed\"}]},{\"type\":\"media_text\",\"image_url\":\"\\/img\\/pages\\/blog-seasonal.jpg\",\"image_side\":\"right\",\"heading\":\"Start with what slows you down most\",\"markdown\":\"Not sure where to begin? Fix the thing that annoys you daily \\u2014 a cramped mouse, a low webcam angle, or reaching for a cable every morning.\\n\\nOne good upgrade beats five mediocre ones.\"},{\"type\":\"feature_grid\",\"heading\":\"Keep these on your list\",\"items\":[{\"title\":\"A spare charging cable\",\"text\":\"One for the bag, one for the desk.\"},{\"title\":\"A power strip with USB ports\",\"text\":\"Charge three things without hunting for an outlet.\"},{\"title\":\"A monitor arm\",\"text\":\"Frees up desk space and fixes your posture.\"},{\"title\":\"A pair of good headphones\",\"text\":\"Fewer distractions, clearer calls.\"}]},{\"type\":\"steps\",\"heading\":\"Upgrade one thing a week\",\"items\":[{\"title\":\"Look\",\"text\":\"Notice what you reach for or complain about daily.\"},{\"title\":\"Compare\",\"text\":\"Check reviews and prices before you buy.\"},{\"title\":\"Install\",\"text\":\"Most of these take under 10 minutes.\"}]},{\"type\":\"rich_text\",\"markdown\":\"### Make it last\\nA laptop stand and a hub are useful even if you change desks or jobs \\u2014 they\'re not tied to one setup.\"},{\"type\":\"quote\",\"text\":\"I didn\'t realise how much a $20 laptop stand would help my neck until I used one for a week.\",\"author\":\"Meera S.\"},{\"type\":\"cta\",\"heading\":\"Shop desk essentials\",\"text\":\"Add a few small upgrades to your next order.\",\"button_label\":\"Shop accessories\",\"button_url\":\"#\\/\"},{\"type\":\"feature_grid\",\"heading\":\"Keep reading\",\"items\":[{\"image_url\":\"\\/img\\/pages\\/blog-delivery.jpg\",\"title\":\"How we get your order shipped fast\",\"text\":\"A look under the hood of our shipping promise.\",\"link_url\":\"#\\/p\\/blog-fast-shipping\"},{\"image_url\":\"\\/img\\/pages\\/blog-seasonal.jpg\",\"title\":\"The best time of year to buy tech\",\"text\":\"When prices actually drop, by category.\",\"link_url\":\"#\\/p\\/blog-seasonal-tech-deals\"},{\"image_url\":\"\\/img\\/pages\\/blog-waste.jpg\",\"title\":\"7 ways to cut down on e-waste\",\"text\":\"Get more life out of what you already own.\",\"link_url\":\"#\\/p\\/blog-reduce-ewaste\"}]}]', 1, 0, 'blog', '[\"blog\"]', 2, '2026-09-09 01:12:49', '2026-09-14 06:33:33'),
(5, 'blog-seasonal-tech-deals', NULL, 'The best time of year to buy tech', NULL, 'When prices actually drop, category by category.', '[{\"type\":\"hero\",\"image_url\":\"\\/img\\/pages\\/blog-seasonal.jpg\",\"heading\":\"The best time of year to buy tech\",\"text\":\"Buy at the right time and the same product costs noticeably less.\"},{\"type\":\"rich_text\",\"markdown\":\"Prices on electronics move with release cycles and shopping seasons, not the weather \\u2014 but the pattern is just as predictable.\\n\\n### What to watch for\\nNew phone and laptop models usually launch in a similar window each year, which is exactly when last year\'s model gets discounted.\"},{\"type\":\"stats\",\"heading\":\"Why timing matters\",\"items\":[{\"title\":\"Lower\",\"text\":\"Price when a new model just launched\"},{\"title\":\"Shorter\",\"text\":\"Wait for open-box and clearance deals\"},{\"title\":\"Better\",\"text\":\"Availability right after a launch window\"},{\"title\":\"Less\",\"text\":\"Rush, if you buy ahead of season\"}]},{\"type\":\"media_text\",\"image_url\":\"\\/img\\/pages\\/blog-dinner.jpg\",\"image_side\":\"left\",\"heading\":\"Buy the outgoing model\",\"markdown\":\"The newest model rarely offers the best value \\u2014 the one it replaces usually does.\\n\\nCheck the spec difference before paying extra for \'new\'.\"},{\"type\":\"feature_grid\",\"heading\":\"A rough calendar\",\"items\":[{\"title\":\"Early in the year\",\"text\":\"Last year\'s TVs and laptops get discounted.\"},{\"title\":\"Mid-year\",\"text\":\"Back-to-school deals on laptops and tablets.\"},{\"title\":\"Autumn\",\"text\":\"New phone launches \\u2014 older models drop in price.\"},{\"title\":\"Late in the year\",\"text\":\"The year\'s biggest sales across every category.\"}]},{\"type\":\"feature_grid\",\"heading\":\"Three ways to buy smart\",\"items\":[{\"title\":\"Compare the spec sheet\",\"text\":\"A cheaper older model may outperform a newer budget one.\"},{\"title\":\"Set a price alert\",\"text\":\"Buy the moment it hits your number.\"},{\"title\":\"Check the return window\",\"text\":\"Know your options before you commit.\"}]},{\"type\":\"rich_text\",\"markdown\":\"### What about open-box and refurbished\\nOpen-box electronics are often unused returns at a lower price, still covered by warranty. Worth checking before paying full price for new.\"},{\"type\":\"quote\",\"text\":\"Waited three weeks for a sale and saved enough to buy a case and screen protector too.\",\"author\":\"Tomasz W.\"},{\"type\":\"cta\",\"heading\":\"Browse today\'s prices\",\"text\":\"See what\'s in stock and shop the current lineup.\",\"button_label\":\"Browse electronics\",\"button_url\":\"#\\/\"},{\"type\":\"feature_grid\",\"heading\":\"Keep reading\",\"items\":[{\"image_url\":\"\\/img\\/pages\\/blog-delivery.jpg\",\"title\":\"How we get your order shipped fast\",\"text\":\"From stocked hubs to planned routes.\",\"link_url\":\"#\\/p\\/blog-fast-shipping\"},{\"image_url\":\"\\/img\\/pages\\/blog-dinner.jpg\",\"title\":\"5 easy home office upgrades under $50\",\"text\":\"Small buys, real difference.\",\"link_url\":\"#\\/p\\/blog-home-office-upgrades\"},{\"image_url\":\"\\/img\\/pages\\/blog-waste.jpg\",\"title\":\"7 ways to cut down on e-waste\",\"text\":\"Buy well, waste less.\",\"link_url\":\"#\\/p\\/blog-reduce-ewaste\"}]}]', 1, 0, 'blog', '[\"blog\"]', 3, '2026-09-09 01:12:49', '2026-09-14 06:33:33'),
(6, 'blog-reduce-ewaste', NULL, '7 ways to cut down on e-waste', NULL, 'Small habits that keep your gadgets running longer and out of landfill.', '[{\"type\":\"hero\",\"image_url\":\"\\/img\\/pages\\/blog-waste.jpg\",\"heading\":\"7 ways to cut down on e-waste\",\"text\":\"Small habits that keep your gadgets running longer and out of landfill.\"},{\"type\":\"rich_text\",\"markdown\":\"1. **Update before you replace.** A slow phone or laptop is often a software problem, not a hardware one.\\n2. **Replace the battery, not the device.** Many phones and laptops can have just the battery swapped.\\n3. **Use a proper case and screen protector.** The single biggest driver of early replacement is accidental damage.\\n4. **Recycle, don\'t bin.** Old electronics contain materials that shouldn\'t go to landfill \\u2014 most stores accept them for recycling.\\n5. **Sell or trade in what still works.** One person\'s outdated phone is still useful to someone else.\\n6. **Keep the original box and cables.** Makes resale or warranty claims far easier later.\\n7. **Buy for longevity.** A slightly more expensive product that lasts twice as long is the better deal.\"},{\"type\":\"media_text\",\"image_url\":\"\\/img\\/pages\\/blog-waste.jpg\",\"image_side\":\"right\",\"heading\":\"Recycle, don\'t bin it\",\"markdown\":\"Dead batteries and old devices contain materials that shouldn\'t go into general waste.\\n\\nMost electronics retailers, including local hubs, accept old devices for proper recycling \\u2014 check before you throw anything out.\"},{\"type\":\"steps\",\"heading\":\"Before you replace anything\",\"items\":[{\"title\":\"Diagnose\",\"text\":\"Check if it\'s a software issue or a cheap fix first.\"},{\"title\":\"Repair or upgrade\",\"text\":\"A new battery or extra storage can add years.\"},{\"title\":\"Recycle or trade in\",\"text\":\"If it\'s beyond saving, dispose of it properly.\"}]},{\"type\":\"stats\",\"heading\":\"What e-waste actually costs\",\"items\":[{\"title\":\"Fastest-growing\",\"text\":\"Waste stream in the world\"},{\"title\":\"Most valuable\",\"text\":\"Materials often go unrecovered\"},{\"title\":\"A checkup\",\"text\":\"How often a device tune-up helps\"},{\"title\":\"Recycling\",\"text\":\"The thing that actually fixes it\"}]},{\"type\":\"feature_grid\",\"heading\":\"Make it last longer\",\"items\":[{\"title\":\"Keep it charged sensibly\",\"text\":\"Avoid always running at 0% or 100%.\"},{\"title\":\"Don\'t let it overheat\",\"text\":\"Heat is the biggest driver of battery wear.\"},{\"title\":\"Update software\",\"text\":\"Security and performance fixes, for free.\"},{\"title\":\"Protect the screen\",\"text\":\"Cheaper than a full replacement.\"}]},{\"type\":\"rich_text\",\"markdown\":\"### Trade in what you\'re not using\\nA drawer of old phones and chargers isn\'t doing anyone any good. Trading in or recycling them is better than letting them sit unused.\"},{\"type\":\"quote\",\"text\":\"Traded in my old laptop instead of leaving it in a drawer \\u2014 paid for half of the new one.\",\"author\":\"Priya M.\"},{\"type\":\"cta\",\"heading\":\"Ready for an upgrade?\",\"text\":\"Browse the latest devices and trade in your old one.\",\"button_label\":\"Shop now\",\"button_url\":\"#\\/\"},{\"type\":\"feature_grid\",\"heading\":\"Keep reading\",\"items\":[{\"image_url\":\"\\/img\\/pages\\/blog-delivery.jpg\",\"title\":\"How we get your order shipped fast\",\"text\":\"Why fast, careful shipping matters.\",\"link_url\":\"#\\/p\\/blog-fast-shipping\"},{\"image_url\":\"\\/img\\/pages\\/blog-dinner.jpg\",\"title\":\"5 easy home office upgrades under $50\",\"text\":\"Use what you have, better.\",\"link_url\":\"#\\/p\\/blog-home-office-upgrades\"},{\"image_url\":\"\\/img\\/pages\\/blog-seasonal.jpg\",\"title\":\"The best time of year to buy tech\",\"text\":\"Buy well, waste less.\",\"link_url\":\"#\\/p\\/blog-seasonal-tech-deals\"}]}]', 1, 0, 'blog', '[\"blog\"]', 4, '2026-09-09 01:12:49', '2026-09-14 06:33:33'),
(7, 'contact', NULL, 'Contact us', '/img/pages/contact-banner.jpg', '**For any query about an order, your account or the service, use the addresses and contact details below. For the fastest help with a specific order, open it in your account and tap \"Get help\" so it reaches the team with the order already attached.**\n\n## Registered office\n\nNexTech Retail Private Limited\n\n4th Floor, Market House, 12 Commerce Road\n\nCityville, State 100001, India\n\n## Corporate office\n\nNexTech Retail Private Limited\n\nTower B, Riverside Business Park, 88 Harbour Avenue\n\nMetro City, State 400001, India\n\n## Contact details\n\n**Customer support:** support@nextech.example — replies within a few hours, every day 8am to 10pm.\n\n**Phone:** +91 00000 00000 — for urgent delivery issues only.\n\n**Press and partnerships:** hello@nextech.example\n\n## Grievance Officer\n\nIn line with the Consumer Protection (E-Commerce) Rules, 2020, complaints can be sent to our Grievance Officer.\n\n**Name:** Grievance Officer, NexTech Retail Private Limited\n\n**Email:** grievance@nextech.example\n\n**Address:** 4th Floor, Market House, 12 Commerce Road, Cityville, State 100001, India\n\nWe acknowledge every complaint within 48 hours and aim to resolve it within one month of receipt.\n\n## Company details\n\n**Legal entity:** NexTech Retail Private Limited\n\n**CIN:** U00000XX2020PTC000000\n\n**GSTIN:** 00AAAAA0000A0Z0\n\n**Registered address:** 4th Floor, Market House, 12 Commerce Road, Cityville, State 100001, India\n\n---\n\n*This is placeholder contact information for a demo store. Replace the entity name, addresses, identifiers and officer details in Admin -> Pages before going live.*', '[]', 1, 1, 'company', '[\"main_footer\"]', 3, '2026-09-09 01:12:49', '2026-09-14 06:26:31'),
(8, 'faqs', NULL, 'FAQs', NULL, 'Quick answers about delivery, payments, warranty and returns.', '[{\"type\":\"hero\",\"heading\":\"Frequently asked questions\",\"text\":\"Answers about delivery, payments, refunds and your account. Tap a question to see the full answer.\"},{\"type\":\"faq\",\"heading\":\"Orders & delivery\",\"items\":[{\"title\":\"How long does delivery take?\",\"text\":\"Most orders arrive within the time window shown at checkout. Traffic, a large order or building access can add a little time, and your live ETA updates if anything changes.\"},{\"title\":\"Do you deliver to my area?\",\"text\":\"Enter your address on the home page. If we deliver there you can start shopping straight away; if not, we\'ll say so and note your interest for when we expand.\"},{\"title\":\"Is there a minimum order?\",\"text\":\"There is no strict minimum, but a very small basket may carry a small-cart fee, which is always shown before you pay. Larger orders often qualify for free delivery.\"},{\"title\":\"Can I add items after placing an order?\",\"text\":\"Not once picking has started. You can place a second order, and if it is within a few minutes we will try to send both together.\"},{\"title\":\"What if I am not home when the rider arrives?\",\"text\":\"The rider calls and waits a couple of minutes. If delivery cannot be completed, the order returns to the store and we refund it or arrange a retry.\"}]},{\"type\":\"faq\",\"heading\":\"Payments\",\"items\":[{\"title\":\"How can I pay?\",\"text\":\"By card through our payment provider, or by cash on delivery where that option is shown at checkout.\"},{\"title\":\"Is it safe to save my card?\",\"text\":\"Card details are handled by our PCI-compliant payment provider and are never stored on NexTech servers. We keep only a reference and the payment status.\"},{\"title\":\"When am I charged?\",\"text\":\"For card orders, at checkout. For cash on delivery, you pay the rider the full amount on hand-over.\"},{\"title\":\"My payment failed but money was deducted \\u2014 what now?\",\"text\":\"A failed-payment hold is usually released by your bank within a few working days. If no order was created, no purchase was made. Contact support with the order time if it does not clear.\"}]},{\"type\":\"faq\",\"heading\":\"Warranty, refunds & returns\",\"items\":[{\"title\":\"Are products covered by warranty?\",\"text\":\"Yes \\u2014 every electronics item carries the manufacturer\'s standard warranty. The product page shows the coverage length; keep your order confirmation as proof of purchase.\"},{\"title\":\"How do I report a missing, wrong or faulty item?\",\"text\":\"Open the order in your account and tap **Get help**. Tell us which items were affected; we review and, where appropriate, refund, repair or replace them.\"},{\"title\":\"How long do refunds take?\",\"text\":\"Approved refunds go to your original payment method. Card refunds can take several working days to appear, depending on your bank. Cash-on-delivery refunds are made by a method we agree with you.\"},{\"title\":\"Can I return an item I\'ve changed my mind about?\",\"text\":\"Unopened items in original packaging can be returned within a reasonable window \\u2014 contact support to start a return. Opened consumables like cables or screen protectors generally cannot be returned once used.\"},{\"title\":\"Can I cancel an order?\",\"text\":\"Yes, until it leaves the store. After that, cancellation may not be possible \\u2014 contact support and we will help where we can.\"}]},{\"type\":\"faq\",\"heading\":\"Your account\",\"items\":[{\"title\":\"How do I sign in?\",\"text\":\"Use the email-code option, or set a password and sign in with your email and password. Staff accounts sign in on a separate admin page.\"},{\"title\":\"How do I change my address or phone number?\",\"text\":\"Edit them in your account. The details on an order that is already placed are frozen at the time you placed it.\"},{\"title\":\"How do I delete my account?\",\"text\":\"Contact support or email privacy@nextech.example. The Privacy Policy explains what happens to your data.\"},{\"title\":\"I am not getting order updates.\",\"text\":\"Check the email address on your account and your spam folder. You can always see live status on the order in your account.\"}]},{\"type\":\"cta\",\"heading\":\"Still need help?\",\"text\":\"Open the order in your account and tap \\u201cGet help\\u201d \\u2014 it reaches support with the order already attached.\"}]', 1, 1, 'help', '[\"main_menu\",\"main_footer\"]', 1, '2026-09-09 01:12:49', '2026-09-18 04:30:28'),
(9, 'privacy', NULL, 'Privacy Policy', NULL, 'NexTech Retail Private Limited (**\"NexTech\"**, **\"we\"**, **\"us\"** or **\"our\"**) is committed to protecting your privacy. This Privacy Policy explains what information we collect when you use the NexTech website and app (the **\"Platform\"**), how we use it, who we share it with, and the choices you have.\n\nBy using the Platform you agree to the practices described in this Policy. If you do not agree, please do not use the Platform.\n\n**Effective date:** this is a demo document — set a real date before going live.\n\n## 1. Information we collect\n\n### 1.1 Information you give us\n\n- **Account information** — your name, email address and phone number when you register or place an order.\n- **Delivery information** — the addresses you save, delivery instructions, and the contact number for a given order.\n- **Order information** — the items you buy, order value, and any issues or refunds you raise.\n- **Communications** — messages you send us through support chat or email.\n\n### 1.2 Information we collect automatically\n\n- **Device and usage data** — device type, browser, operating system, IP address, pages viewed and actions taken on the Platform.\n- **Approximate location** — derived from your address or, with your permission, your device, to check whether we deliver to you and to estimate delivery times.\n- **Cookies and similar technologies** — see Section 4.\n\n### 1.3 Information from third parties\n\n- **Payment status** from our payment processor. We never receive your full card number.\n- **Fraud and risk signals** from providers that help us keep accounts secure.\n\nWe do **not** knowingly collect sensitive personal data, and we ask that you do not send it to us.\n\n## 2. How we use your information\n\nWe use your information to:\n\n- create and manage your account;\n- process, pack and deliver your orders, and handle returns and refunds;\n- share the details a delivery rider needs — your name, address and phone — so your order can reach you;\n- provide customer support and respond to your queries;\n- detect, prevent and investigate fraud, abuse and security incidents;\n- improve the Platform, our range and our delivery operations;\n- send you service messages such as order updates and security notices; and\n- send you offers and updates **only if you have opted in**, which you can stop at any time.\n\n## 3. Payment information\n\nCard payments are processed by our third-party payment processor. Your card details are entered on their secure systems and are **not stored on NexTech servers**. We retain only a payment reference and the status of the transaction.\n\n## 4. Cookies and similar technologies\n\nWe use:\n\n- **Essential cookies and local storage** to keep you signed in and remember your cart and chosen location. The Platform does not work properly without these.\n- **Analytics** to understand which features are used so we can improve them.\n\nYou can clear or block cookies in your browser settings; some parts of the Platform may then stop working.\n\n## 5. How we share information\n\nWe share information only as described here:\n\n- **Delivery partners** — the name, address, phone number and order contents needed to deliver your order.\n- **Service providers** — payment processing, hosting, communications, mapping and analytics providers who process data on our instructions.\n- **Legal and safety** — where required by law, court order or a government request, or to protect the rights, property or safety of NexTech, our customers or the public.\n- **Business transfers** — if NexTech is involved in a merger, acquisition or sale of assets, your information may be transferred, subject to this Policy.\n\nWe do **not** sell your personal information.\n\n## 6. Data retention\n\nWe keep your information for as long as your account is active and for a reasonable period afterwards to meet legal, tax, accounting and dispute-resolution requirements. When it is no longer needed we delete or anonymise it.\n\n## 7. Your rights and choices\n\nDepending on where you live, you may have the right to:\n\n- **access** the personal information we hold about you;\n- **correct** information that is inaccurate — you can edit your profile and addresses in your account;\n- **delete** your account and associated personal information;\n- **object to or restrict** certain processing; and\n- **withdraw consent** for marketing at any time.\n\nTo exercise any of these, contact us using the details in Section 12. We may need to verify your identity before acting on a request.\n\n## 8. Security\n\nWe use technical and organisational measures to protect your information, including encryption in transit (HTTPS / TLS), access controls that limit staff and rider access to what they need, and revocable sign-in tokens. No method of transmission or storage is completely secure, so we cannot guarantee absolute security.\n\n## 9. Children\n\nThe Platform is not directed at children below the age required to form a binding contract where they live, and we do not knowingly collect their personal information. If you believe a child has provided us information, contact us and we will delete it.\n\n## 10. Third-party links\n\nThe Platform may link to third-party sites and services. We are not responsible for their privacy practices; please read their policies.\n\n## 11. International transfers\n\nYour information may be processed in countries other than the one you live in. Where we transfer information across borders, we use appropriate safeguards as required by applicable law.\n\n## 12. Grievance Officer and contact\n\nFor questions about this Policy or to exercise your rights, contact:\n\nGrievance Officer, NexTech Retail Private Limited\n\nEmail: privacy@nextech.example\n\nAddress: 4th Floor, Market House, 12 Commerce Road, Cityville, State 100001, India\n\nIn line with the Consumer Protection (E-Commerce) Rules, 2020, we acknowledge complaints within 48 hours and aim to resolve them within one month of receipt.\n\n## 13. Changes to this Policy\n\nWe may update this Policy from time to time. If we make material changes we will post the updated Policy on the Platform and, where appropriate, notify you. The **Effective date** above shows when it last changed.\n\n---\n\n*This is placeholder text for a demo store. Replace it with a privacy policy prepared and reviewed by your legal team, and set a real effective date, entity details and contact information in Admin -> Pages.*', '[]', 1, 1, 'legal', '[\"main_menu\",\"main_footer\"]', 1, '2026-09-09 01:12:49', '2026-09-14 06:21:56'),
(10, 'terms', NULL, 'Terms of Service', NULL, 'These Terms of Service (**\"Terms\"**) govern your use of the NexTech website and app (the **\"Platform\"**), operated by NexTech Retail Private Limited (**\"NexTech\"**, **\"we\"**, **\"us\"** or **\"our\"**). By creating an account, placing an order or otherwise using the Platform, you agree to these Terms and to our Privacy Policy. If you do not agree, do not use the Platform.\n\n**Effective date:** this is a demo document — set a real date before going live.\n\n## 1. Eligibility and your account\n\n- You must be old enough to form a legally binding contract where you live, and not barred from receiving our services under applicable law.\n- You must provide accurate, current and complete account and delivery information, and keep it up to date.\n- You are responsible for activity that happens under your account and for keeping your sign-in credentials secure. Tell us promptly if you suspect unauthorised use.\n- We may refuse, suspend or close an account for a breach of these Terms, suspected fraud or abuse, or where required by law.\n\n## 2. The service\n\nThe Platform lets you order groceries and household items from a nearby store for delivery. Product range, images, pricing and delivery areas vary by location and change over time. Nothing on the Platform is an offer; your order is an offer to buy, which we accept when we confirm it.\n\n## 3. Orders, pricing and availability\n\n- Prices, taxes, delivery fees and any other charges are shown before you confirm an order. Totals are calculated and confirmed by our servers at checkout.\n- Product weights and pack sizes are approximate. Substitutions are only made with your agreement.\n- If an item is unavailable, mispriced or ordered in quantities we consider abnormal, we may cancel all or part of the order and refund the affected amount.\n- Promotional prices and offers are subject to their own terms and may be withdrawn at any time.\n\n## 4. Payment\n\n- You can pay by card through our third-party payment processor, or by cash on delivery where that option is shown.\n- Card details are entered on the payment processor\'s systems and are **not stored on NexTech servers**.\n- For cash-on-delivery orders, the full amount is due to the delivery rider on hand-over.\n- If a payment fails or is reversed, we may cancel the order or suspend your account until it is resolved.\n\n## 5. Delivery\n\n- We deliver only to addresses within a serviceable area. Enter your address on the Platform to check.\n- Delivery time estimates are indicative and may be affected by weather, traffic, demand or access to your building.\n- Someone must be available to receive the order at the address. If delivery cannot be completed after reasonable attempts, the order may be returned and a cancellation fee or a partial refund may apply.\n- Risk in the goods passes to you on delivery.\n\n## 6. Cancellations and refunds\n\n- You may cancel an order until it leaves the store. After that, cancellation may not be possible.\n- Approved refunds are made to your original payment method. Card refunds may take several business days to appear, depending on your bank.\n- For missing, damaged or incorrect items, raise an issue from your order history within a reasonable time so we can review and, where appropriate, refund or replace.\n\n## 7. Acceptable use\n\nYou agree not to:\n\n- use the Platform for any unlawful, fraudulent or harmful purpose;\n- interfere with or disrupt the Platform, its servers or networks, or attempt to gain unauthorised access;\n- scrape, copy or harvest data from the Platform except as expressly permitted;\n- resell products bought through the Platform, or place orders you do not intend to pay for or receive;\n- abuse promotions, referral schemes or the refund process; or\n- upload or transmit anything unlawful, defamatory, infringing or malicious.\n\n## 8. Intellectual property\n\nThe Platform, including its content, design, logos and software, is owned by NexTech or its licensors and is protected by intellectual-property laws. We grant you a limited, non-exclusive, non-transferable, revocable licence to use the Platform for its intended purpose. All other rights are reserved.\n\n## 9. User content\n\nIf you submit content — such as support messages, feedback or ratings — you grant us a non-exclusive, worldwide, royalty-free licence to use it to operate and improve the service. You are responsible for the content you submit and confirm you have the right to submit it.\n\n## 10. Third-party services\n\nThe Platform relies on and may link to third-party services (for example payments, mapping and messaging). Their terms and policies apply to your use of those services, and we are not responsible for them.\n\n## 11. Disclaimers\n\nThe Platform and all products and services are provided on an **\"as is\"** and **\"as available\"** basis. To the fullest extent permitted by law, we disclaim all warranties, express or implied, including merchantability, fitness for a particular purpose and non-infringement. We do not warrant that the Platform will be uninterrupted, error-free or secure.\n\n## 12. Limitation of liability\n\nTo the fullest extent permitted by law, NexTech and its officers, employees and partners will not be liable for any indirect, incidental, special, consequential or punitive damages, or for loss of profits, data or goodwill, arising from your use of the Platform. Our total liability for any claim relating to an order will not exceed the amount you paid for that order.\n\n## 13. Indemnity\n\nYou agree to indemnify and hold NexTech harmless from claims, losses and expenses (including reasonable legal fees) arising from your breach of these Terms or your misuse of the Platform.\n\n## 14. Suspension and termination\n\nWe may suspend or terminate your access to the Platform at any time for a breach of these Terms, suspected fraud or abuse, or where required by law. You may stop using the Platform and close your account at any time. Sections that by their nature should survive termination will do so.\n\n## 15. Changes to these Terms\n\nWe may update these Terms from time to time. Material changes will be posted on the Platform and, where appropriate, notified to you. Continued use of the Platform after changes take effect means you accept the updated Terms.\n\n## 16. Governing law and disputes\n\nThese Terms are governed by the laws of India, without regard to conflict-of-law rules. Subject to any mandatory consumer-protection rights you have where you live, the courts at Metro City, India will have jurisdiction over disputes arising from these Terms.\n\n## 17. Grievance Officer and contact\n\nFor complaints or questions about these Terms, contact:\n\nGrievance Officer, NexTech Retail Private Limited\n\nEmail: grievance@nextech.example\n\nAddress: 4th Floor, Market House, 12 Commerce Road, Cityville, State 100001, India\n\nIn line with the Consumer Protection (E-Commerce) Rules, 2020, we acknowledge complaints within 48 hours and aim to resolve them within one month of receipt.\n\n## 18. General\n\n- **Entire agreement** — these Terms and the Privacy Policy are the entire agreement between you and NexTech regarding the Platform.\n- **Severability** — if any provision is held unenforceable, the rest remains in effect.\n- **No waiver** — our failure to enforce a provision is not a waiver of it.\n- **Assignment** — you may not assign these Terms; we may assign them in connection with a merger, acquisition or sale of assets.\n- **Force majeure** — we are not liable for delays or failures caused by events beyond our reasonable control.\n\n---\n\n*This is placeholder text for a demo store. Replace it with terms of service prepared and reviewed by your legal team, and set a real effective date, entity details, governing law and contact information in Admin -> Pages.*', '[]', 1, 1, 'legal', '[\"main_menu\",\"main_footer\"]', 2, '2026-09-09 01:12:49', '2026-09-14 23:43:53'),
(11, 'security', NULL, 'Security', '/img/pages/security-banner.jpg', 'NexTech Retail Private Limited (**\"NexTech\"**) takes the security of our customers and their data seriously. We value the work of security researchers and welcome reports of vulnerabilities in our website, app and infrastructure.\n\nThis page sets out how to report a security issue to us and what you can expect in return.\n\n**Effective date:** this is a demo document — set a real date before going live.\n\n## Our commitment\n\nIf you make a good-faith effort to comply with this policy during your research, we will:\n\n- work with you to understand and validate your report;\n- keep you informed of our progress towards a fix;\n- not pursue or support legal action against you for accidental, good-faith violations of this policy; and\n- credit you, with your permission, once the issue is resolved.\n\nActivities carried out in a manner consistent with this policy will be considered authorised conduct, and we will not treat them as a breach of our Terms of Service.\n\n## Guidelines\n\nPlease:\n\n- only test against accounts and data that you own or have explicit permission to use;\n- stop testing and report immediately if you encounter customer data, and do not access, modify, save, transfer or disclose it;\n- give us a reasonable time to investigate and fix an issue before disclosing it publicly, and coordinate any disclosure with us;\n- provide enough detail for us to reproduce the issue; and\n- make every effort to avoid privacy violations, data loss and service disruption.\n\nPlease do **not**:\n\n- run automated scanners against production, or any test that degrades or disrupts our services (including denial-of-service, brute force at volume, or spam);\n- use social engineering, phishing, or physical attempts against our staff, riders, offices or infrastructure;\n- attempt to access, download or exfiltrate data that is not yours;\n- publicly disclose a vulnerability before we have confirmed it is fixed; or\n- demand payment as a condition of disclosure.\n\n## In scope\n\n- Our customer website and web app\n- Our customer mobile apps\n- APIs that serve the above\n\n## Out of scope\n\nThe following generally do **not** qualify on their own, unless you can show a concrete, exploitable security impact:\n\n- Missing security headers, cookie flags, or best-practice hardening with no demonstrated exploit\n- Self-XSS, or issues requiring a fully compromised device or browser\n- Clickjacking on pages with no sensitive state-changing actions\n- Rate-limiting or brute-force concerns on non-authentication endpoints\n- Reports from automated tools without a working proof of concept\n- SPF / DKIM / DMARC configuration, or email spoofing of non-existent addresses\n- Outdated library versions with no proven vulnerability in our usage\n- Denial-of-service, resource-exhaustion, or volumetric findings\n- Social engineering, or physical security of our premises\n\n## How to report\n\nEmail **security@nextech.example** with:\n\n1. a clear description of the vulnerability and the affected URL, endpoint or app screen;\n2. step-by-step instructions to reproduce it;\n3. a proof of concept (script, request, screenshots or a short video); and\n4. your assessment of the impact and any suggested remediation.\n\nOne issue per report, please. If you need to share sensitive details, ask us for a secure channel.\n\n## What happens next\n\n- **Acknowledgement** — we aim to confirm receipt within 3 working days.\n- **Triage** — we validate the report and assign a severity, and will ask for more detail if needed.\n- **Fix** — remediation time depends on severity and complexity; we will keep you updated.\n- **Closure** — we let you know when the issue is resolved and confirm any credit.\n\n## Recognition\n\nWith your consent, we are happy to acknowledge researchers who report valid, previously unknown issues. NexTech does not currently run a paid bug-bounty programme; any reward is at our discretion.\n\n## Contact\n\nSecurity reports: **security@nextech.example**\n\nFor anything else, see the [Contact](/#/p/contact) page.\n\n---\n\n*This is placeholder text for a demo store. Replace it with a responsible-disclosure policy reviewed by your security and legal teams, and set real scope, contact details and an effective date in Admin -> Pages.*', '[]', 1, 1, 'legal', '[\"main_footer\"]', 3, '2026-09-09 01:12:49', '2026-09-14 06:18:24');
INSERT INTO `pages` (`id`, `slug`, `parent_slug`, `title`, `banner_image`, `content`, `sections`, `is_published`, `show_in_footer`, `footer_group`, `menu_placements`, `sort_order`, `created_at`, `updated_at`) VALUES
(12, 'blog-10-minute-delivery', NULL, 'How we get groceries to you in 10 minutes', NULL, 'A look under the hood of the NexTech delivery promise.', '[{\"type\":\"hero\",\"image_url\":\"\\/img\\/pages\\/blog-delivery.jpg\",\"heading\":\"How we get groceries to you in 10 minutes\",\"text\":\"From stocked neighbourhood hubs to routes built for your street \\u2014 a look under the hood.\"},{\"type\":\"stats\",\"heading\":\"The promise in numbers\",\"items\":[{\"title\":\"under 10 min\",\"text\":\"Typical door-to-door\"},{\"title\":\"3+\",\"text\":\"Pickers on one basket at peak\"},{\"title\":\"Every line\",\"text\":\"Scanned before it leaves\"},{\"title\":\"Live ETA\",\"text\":\"Shown before you pay\"}]},{\"type\":\"rich_text\",\"markdown\":\"### It starts with the store, not a warehouse\\nInstead of one big depot on the edge of town, we run small stocked hubs inside neighbourhoods. When your order lands, the picker is already a few metres from the shelf.\\n\\n### Picking in parallel\\nThe moment you check out, your list is split across the aisles so several people pack it at once. Chilled and frozen items are grabbed last so they stay cold.\\n\\n### Short, planned routes\\nRiders leave with a route that already accounts for one-way streets and building access, so the last hundred metres don\'t eat the time we just saved.\\n\\n### What can slow it down\\nHeavy weather, a very large basket, or an address we can\'t place on the map. You\'ll always see a live ETA before you pay, and it updates if something changes.\"},{\"type\":\"media_text\",\"image_url\":\"\\/img\\/pages\\/about-story.jpg\",\"image_side\":\"left\",\"heading\":\"Why a hub beats a warehouse\",\"markdown\":\"A warehouse on the ring road is efficient for lorries, not for you.\\n\\nOur hubs carry a tighter range \\u2014 the few thousand things people actually reorder \\u2014 a short walk from where you live. Less range on the shelf, far less distance to your door.\"},{\"type\":\"feature_grid\",\"heading\":\"What we optimise for\",\"items\":[{\"title\":\"Distance\",\"text\":\"Metres from shelf to door, not miles.\"},{\"title\":\"Parallel picking\",\"text\":\"Several people pack one order at once.\"},{\"title\":\"Cold chain\",\"text\":\"Chilled and frozen items are grabbed last.\"},{\"title\":\"Route quality\",\"text\":\"One-way streets and door access, solved before the rider leaves.\"}]},{\"type\":\"steps\",\"heading\":\"The ten minutes, step by step\",\"items\":[{\"title\":\"0:00 \\u2014 Order placed\",\"text\":\"Your list appears on the hub\'s screen and is split by aisle.\"},{\"title\":\"0:30 \\u2014 Picking starts\",\"text\":\"Several pickers work in parallel; chilled items come last.\"},{\"title\":\"3:00 \\u2014 Packed and checked\",\"text\":\"A second person scans every line against your order.\"},{\"title\":\"4:00 \\u2014 Rider dispatched\",\"text\":\"With a route built for your street, not just your postcode.\"},{\"title\":\"~10:00 \\u2014 At your door\",\"text\":\"Hand over cash now if you chose cash on delivery.\"}]},{\"type\":\"quote\",\"text\":\"The rider messaged when he was outside and waited while I found change. Felt like a neighbour dropping something round, not a courier.\",\"author\":\"Dan K. \\u2014 Camberwell\"},{\"type\":\"rich_text\",\"markdown\":\"### A few things people ask\\n**Can I add to an order after checkout?** Not once picking starts \\u2014 but you can place a second order, and if it\'s within a few minutes we try to send them out together.\\n\\n**What if I\'m not in?** The rider calls, then waits a couple of minutes. Undelivered orders come back to the hub and we refund or retry.\\n\\n**Do you deliver everywhere?** Only inside a hub\'s range for now. Enter your address on the home page to check.\"},{\"type\":\"cta\",\"heading\":\"See how fast it lands for you\",\"text\":\"Enter your address and add a few items to get a live ETA.\",\"button_label\":\"Start shopping\",\"button_url\":\"#\\/\"},{\"type\":\"feature_grid\",\"heading\":\"Keep reading\",\"items\":[{\"image_url\":\"\\/img\\/pages\\/blog-dinner.jpg\",\"title\":\"5 weeknight dinners in under 20 minutes\",\"text\":\"Five ingredients or fewer, on the table fast.\",\"link_url\":\"#\\/p\\/blog-weeknight-dinners\"},{\"image_url\":\"\\/img\\/pages\\/blog-seasonal.jpg\",\"title\":\"What\'s in season this month\",\"text\":\"Cheaper, fresher, and it tastes better.\",\"link_url\":\"#\\/p\\/blog-seasonal-produce\"},{\"image_url\":\"\\/img\\/pages\\/blog-waste.jpg\",\"title\":\"7 easy ways to waste less food\",\"text\":\"Cut your bill and your bin at once.\",\"link_url\":\"#\\/p\\/blog-less-food-waste\"}]}]', 0, 0, 'blog', '[\"blog\"]', 1, '2026-09-14 23:43:53', '2026-09-14 23:43:53'),
(13, 'blog-weeknight-dinners', NULL, '5 weeknight dinners in under 20 minutes', NULL, 'Five ingredients or fewer, on the table before the news finishes.', '[{\"type\":\"hero\",\"image_url\":\"\\/img\\/pages\\/blog-dinner.jpg\",\"heading\":\"5 weeknight dinners in under 20 minutes\",\"text\":\"Five ingredients or fewer, minimal washing up, on the table fast.\"},{\"type\":\"rich_text\",\"markdown\":\"Keep a few basics in and any of these comes together in the time it takes rice to cook.\\n\\n1. **Garlic butter pasta** \\u2014 pasta, butter, garlic, parmesan, black pepper. Reserve a little pasta water to bring it together.\\n2. **Chickpea & spinach curry** \\u2014 tinned chickpeas, curry paste, coconut milk, spinach. Simmer 10 minutes, serve with rice or bread.\\n3. **Egg fried rice** \\u2014 cold cooked rice, eggs, spring onion, soy, frozen peas. High heat, keep it moving.\\n4. **Halloumi & tomato traybake** \\u2014 halloumi, cherry tomatoes, olive oil, oregano. 15 minutes at 220\\u00b0C.\\n5. **Tuna & white bean salad** \\u2014 tinned tuna, cannellini beans, red onion, lemon, olive oil. No cooking at all.\"},{\"type\":\"stats\",\"heading\":\"Why this works on a weeknight\",\"items\":[{\"title\":\"5 or fewer\",\"text\":\"Ingredients per recipe\"},{\"title\":\"~15 min\",\"text\":\"Hands-on time\"},{\"title\":\"1 pan\",\"text\":\"For most of them\"},{\"title\":\"0\",\"text\":\"Special equipment\"}]},{\"type\":\"media_text\",\"image_url\":\"\\/img\\/pages\\/blog-seasonal.jpg\",\"image_side\":\"right\",\"heading\":\"Swap with the seasons\",\"markdown\":\"Every recipe above takes a swap. Spinach becomes chard or kale. Cherry tomatoes become any tomato, halved. Chickpeas become butter beans.\\n\\nCook whatever is cheap and good that week and the method still works.\"},{\"type\":\"feature_grid\",\"heading\":\"Keep these in the cupboard\",\"items\":[{\"title\":\"Dried pasta & rice\",\"text\":\"The base of three of the five above.\"},{\"title\":\"Tinned beans & tomatoes\",\"text\":\"Instant protein and a sauce in one tin.\"},{\"title\":\"Coconut milk & curry paste\",\"text\":\"A 10-minute curry any night.\"},{\"title\":\"Olive oil, garlic, lemon\",\"text\":\"Turns plain ingredients into a meal.\"}]},{\"type\":\"steps\",\"heading\":\"Get faster every week\",\"items\":[{\"title\":\"Prep in batches\",\"text\":\"Chop onion and garlic for two nights at a time.\"},{\"title\":\"Cook rice ahead\",\"text\":\"Cold rice is better for fried rice anyway.\"},{\"title\":\"Always double it\",\"text\":\"Tomorrow\'s lunch, sorted.\"}]},{\"type\":\"rich_text\",\"markdown\":\"### Make it a meal\\nRound any of these out with a bag of salad, some bread, or a piece of fruit. None of them need a starter.\"},{\"type\":\"quote\",\"text\":\"I stopped ordering takeaway on Tuesdays. The chickpea curry is genuinely faster than opening the app.\",\"author\":\"Meera S.\"},{\"type\":\"cta\",\"heading\":\"Stock the basics\",\"text\":\"Add the cupboard staples to your next order in a couple of taps.\",\"button_label\":\"Shop staples\",\"button_url\":\"#\\/\"},{\"type\":\"feature_grid\",\"heading\":\"Keep reading\",\"items\":[{\"image_url\":\"\\/img\\/pages\\/blog-delivery.jpg\",\"title\":\"How we get groceries to you in 10 minutes\",\"text\":\"A look under the hood of the delivery promise.\",\"link_url\":\"#\\/p\\/blog-10-minute-delivery\"},{\"image_url\":\"\\/img\\/pages\\/blog-seasonal.jpg\",\"title\":\"What\'s in season this month\",\"text\":\"The produce worth buying right now.\",\"link_url\":\"#\\/p\\/blog-seasonal-produce\"},{\"image_url\":\"\\/img\\/pages\\/blog-waste.jpg\",\"title\":\"7 easy ways to waste less food\",\"text\":\"Small habits, smaller bin.\",\"link_url\":\"#\\/p\\/blog-less-food-waste\"}]}]', 0, 0, 'blog', '[\"blog\"]', 2, '2026-09-14 23:43:53', '2026-09-14 23:43:53'),
(14, 'blog-seasonal-produce', NULL, 'What\'s in season this month', NULL, 'The produce that is cheapest, freshest and best right now.', '[{\"type\":\"hero\",\"image_url\":\"\\/img\\/pages\\/blog-seasonal.jpg\",\"heading\":\"What\'s in season this month\",\"text\":\"Buy with the seasons: cheaper, fresher, and it simply tastes better.\"},{\"type\":\"rich_text\",\"markdown\":\"Produce that\'s in season hasn\'t travelled far or sat in storage, so it costs less and tastes more like itself.\\n\\n### Vegetables to reach for\\nLeafy greens, carrots, beetroot, cabbage, leeks and squash are all at their best and their cheapest.\\n\\n### Fruit worth buying\\nApples, pears and citrus are crisp and well priced. Berries are better frozen this time of year.\"},{\"type\":\"stats\",\"heading\":\"Why buy in season\",\"items\":[{\"title\":\"Lower\",\"text\":\"Price when supply is high\"},{\"title\":\"Shorter\",\"text\":\"Time from field to shelf\"},{\"title\":\"Better\",\"text\":\"Flavour and texture\"},{\"title\":\"Less\",\"text\":\"Packaging and cold storage\"}]},{\"type\":\"media_text\",\"image_url\":\"\\/img\\/pages\\/blog-dinner.jpg\",\"image_side\":\"left\",\"heading\":\"Cook it simply\",\"markdown\":\"In-season produce doesn\'t need much done to it. Roast it, dress it with lemon and oil, or drop it in a soup.\\n\\nThe less you do, the more it tastes of itself.\"},{\"type\":\"feature_grid\",\"heading\":\"A rough month-by-month\",\"items\":[{\"title\":\"Late winter\",\"text\":\"Citrus, leeks, cabbage, stored apples.\"},{\"title\":\"Spring\",\"text\":\"Asparagus, spring greens, new potatoes, rhubarb.\"},{\"title\":\"Summer\",\"text\":\"Tomatoes, courgettes, berries, stone fruit.\"},{\"title\":\"Autumn\",\"text\":\"Squash, mushrooms, pears, root veg.\"}]},{\"type\":\"feature_grid\",\"heading\":\"Three ways to use a glut\",\"items\":[{\"title\":\"Roast a tray\",\"text\":\"Any root veg, olive oil, salt, 30 minutes. Eats hot or cold all week.\"},{\"title\":\"Make a soup base\",\"text\":\"Onion, carrot, celery, stock. Freezes in portions.\"},{\"title\":\"Quick pickle\",\"text\":\"Vinegar, sugar, salt over sliced veg. Ready by dinner.\"}]},{\"type\":\"rich_text\",\"markdown\":\"### What about frozen and tinned\\nFrozen peas, spinach, berries and sweetcorn are picked and frozen at their peak \\u2014 often better than \\\"fresh\\\" that has travelled a week. Tinned tomatoes and beans are pantry gold.\"},{\"type\":\"quote\",\"text\":\"Started shopping the \'in season\' shelf and my veg bill dropped without me trying.\",\"author\":\"Tomasz W.\"},{\"type\":\"cta\",\"heading\":\"Shop fresh produce\",\"text\":\"See what your nearest store has in today.\",\"button_label\":\"Browse produce\",\"button_url\":\"#\\/\"},{\"type\":\"feature_grid\",\"heading\":\"Keep reading\",\"items\":[{\"image_url\":\"\\/img\\/pages\\/blog-delivery.jpg\",\"title\":\"How we get groceries to you in 10 minutes\",\"text\":\"From stocked hubs to planned routes.\",\"link_url\":\"#\\/p\\/blog-10-minute-delivery\"},{\"image_url\":\"\\/img\\/pages\\/blog-dinner.jpg\",\"title\":\"5 weeknight dinners in under 20 minutes\",\"text\":\"Fast, cheap, five ingredients.\",\"link_url\":\"#\\/p\\/blog-weeknight-dinners\"},{\"image_url\":\"\\/img\\/pages\\/blog-waste.jpg\",\"title\":\"7 easy ways to waste less food\",\"text\":\"Buy less, bin less.\",\"link_url\":\"#\\/p\\/blog-less-food-waste\"}]}]', 0, 0, 'blog', '[\"blog\"]', 3, '2026-09-14 23:43:53', '2026-09-14 23:43:53'),
(15, 'blog-less-food-waste', NULL, '7 easy ways to waste less food', NULL, 'Small habits that cut your grocery bill and your bin at the same time.', '[{\"type\":\"hero\",\"image_url\":\"\\/img\\/pages\\/blog-waste.jpg\",\"heading\":\"7 easy ways to waste less food\",\"text\":\"Small habits that cut your grocery bill and your bin at the same time.\"},{\"type\":\"rich_text\",\"markdown\":\"1. **Shop your fridge first.** Plan two meals around what\'s already there before you order.\\n2. **Order little and often.** Fast delivery means you don\'t need to over-buy fresh food.\\n3. **Learn the labels.** \\\"Best before\\\" is about quality; \\\"use by\\\" is about safety.\\n4. **Store it right.** Herbs in water, potatoes in the dark, bread in the freezer.\\n5. **Cook once, eat twice.** Make a bit extra and label it for later.\\n6. **Keep a \\\"use me first\\\" shelf.** One spot in the fridge for things on the edge.\\n7. **Freeze the odds and ends.** Overripe fruit for smoothies, veg scraps for stock.\"},{\"type\":\"media_text\",\"image_url\":\"\\/img\\/pages\\/blog-waste.jpg\",\"image_side\":\"right\",\"heading\":\"The \'use me first\' shelf\",\"markdown\":\"Pick one shelf in the fridge \\u2014 eye level is best \\u2014 for anything close to the edge.\\n\\nEveryone in the house checks it before opening a new pack. It\'s the single habit that moves the needle most.\"},{\"type\":\"steps\",\"heading\":\"A two-minute weekly reset\",\"items\":[{\"title\":\"Look\",\"text\":\"Scan the fridge and note what needs using.\"},{\"title\":\"Plan\",\"text\":\"Pin two meals to those items.\"},{\"title\":\"Top up\",\"text\":\"Order only the gaps.\"}]},{\"type\":\"stats\",\"heading\":\"What waste actually costs\",\"items\":[{\"title\":\"~1 in 5\",\"text\":\"Bags of shopping binned, on average\"},{\"title\":\"Fresh food\",\"text\":\"The category wasted most\"},{\"title\":\"A month\",\"text\":\"How often a full reset helps\"},{\"title\":\"Planning\",\"text\":\"The thing that fixes it\"}]},{\"type\":\"feature_grid\",\"heading\":\"Store it so it lasts\",\"items\":[{\"title\":\"Herbs\",\"text\":\"Stems in a glass of water, a loose bag over the top.\"},{\"title\":\"Bread\",\"text\":\"Freeze half the loaf the day you get it.\"},{\"title\":\"Potatoes & onions\",\"text\":\"Cool, dark, and not right next to each other.\"},{\"title\":\"Leafy greens\",\"text\":\"Wrapped in a dry cloth, not left soaking.\"}]},{\"type\":\"rich_text\",\"markdown\":\"### Cook the scraps\\nVegetable ends and herb stalks go in a stock bag in the freezer. Overripe bananas get peeled and frozen for smoothies or bread. Stale bread becomes croutons or breadcrumbs.\"},{\"type\":\"quote\",\"text\":\"Ordering smaller amounts more often was the fix. I don\'t buy a week of salad and watch half of it wilt any more.\",\"author\":\"Priya M.\"},{\"type\":\"cta\",\"heading\":\"Plan this week\",\"text\":\"Build a short list around what you already have.\",\"button_label\":\"Start a list\",\"button_url\":\"#\\/\"},{\"type\":\"feature_grid\",\"heading\":\"Keep reading\",\"items\":[{\"image_url\":\"\\/img\\/pages\\/blog-delivery.jpg\",\"title\":\"How we get groceries to you in 10 minutes\",\"text\":\"Why fast delivery means buying less.\",\"link_url\":\"#\\/p\\/blog-10-minute-delivery\"},{\"image_url\":\"\\/img\\/pages\\/blog-dinner.jpg\",\"title\":\"5 weeknight dinners in under 20 minutes\",\"text\":\"Use what you have, fast.\",\"link_url\":\"#\\/p\\/blog-weeknight-dinners\"},{\"image_url\":\"\\/img\\/pages\\/blog-seasonal.jpg\",\"title\":\"What\'s in season this month\",\"text\":\"Buy well, waste less.\",\"link_url\":\"#\\/p\\/blog-seasonal-produce\"}]}]', 0, 0, 'blog', '[\"blog\"]', 4, '2026-09-14 23:43:53', '2026-09-14 23:43:53'),
(16, 'careers', NULL, 'Careers', NULL, '**Join NexTech**\n\nWe\'re a small team building a fast, no-nonsense electronics storefront. This is a demo build, so there are no live openings right now — but here\'s the kind of roles we\'d be hiring for as the team grows.\n\n### Open roles (illustrative)\n- Backend Engineer (Laravel / PHP)\n- React Native Mobile Engineer\n- Warehouse & Fulfilment Lead\n- Customer Support Specialist\n\nInterested in a real opportunity? This is placeholder content — edit it in **Admin -> Pages** to point at your own careers page or ATS.', NULL, 1, 1, 'company', '[\"main_footer\"]', 4, '2026-09-18 04:32:04', '2026-09-18 04:32:04'),
(17, 'press', NULL, 'Press', NULL, '**Press & media**\n\nFor interview requests, product images or company background, contact **press@nextech.example**.\n\n### Fact sheet\n- Founded: 2026\n- HQ: your city here\n- Categories: phones, laptops, audio, smart home and more\n\nThis is placeholder content for a demo storefront — replace it with real press materials in **Admin -> Pages**.', NULL, 1, 1, 'company', '[\"main_footer\"]', 5, '2026-09-18 04:32:04', '2026-09-18 04:32:04'),
(18, 'return-refund-policy', NULL, 'Return and refund policy', NULL, '**Returns**\n\nUnopened items in original packaging can be returned within a reasonable window of delivery — open the order in your account and tap **Get help** to start one. Opened consumables (cables, screen protectors, earbud tips) generally can\'t be returned once used.\n\n**Refunds**\n\nApproved refunds go back to your original payment method. Card refunds can take several working days to appear, depending on your bank; cash-on-delivery refunds are arranged directly with you.\n\n**Faulty or wrong items**\n\nReport it from your order history within a reasonable window and we\'ll repair, replace or refund it — whichever gets you sorted fastest.', NULL, 1, 1, 'legal', '[\"main_footer\"]', 4, '2026-09-18 04:32:04', '2026-09-18 04:32:04'),
(19, 'shipping-info', NULL, 'Shipping info', NULL, '**Delivery area**\n\nWe deliver from local store hubs, so coverage depends on your address — enter it on the home page to check.\n\n**Delivery time**\n\nMost orders arrive within the window shown at checkout. Traffic, a large order or building access can add a little time; your live ETA updates automatically.\n\n**Delivery fee**\n\nShown before you pay, based on distance and order size. Larger orders often qualify for free delivery.\n\n**Tracking**\n\nFollow your order in real time from **Account -> Orders** once it leaves the store.', NULL, 1, 1, 'legal', '[\"main_menu\",\"main_footer\"]', 5, '2026-09-18 04:32:04', '2026-09-18 04:32:04'),
(20, 'support-center', NULL, 'Support center & FAQ', NULL, 'Search common topics, or open a request from any order for the fastest help.', '[{\"type\":\"hero\",\"heading\":\"Hi, how can we help?\",\"text\":\"Search common topics below, or open a request from any order in your account for the fastest help.\"},{\"type\":\"feature_grid\",\"heading\":\"Popular topics\",\"items\":[{\"title\":\"Track my order\",\"text\":\"See live delivery status and estimated arrival from Account -> Orders.\",\"link_url\":\"#\\/\"},{\"title\":\"Returns & refunds\",\"text\":\"How to start a return and what happens to your money.\",\"link_url\":\"#\\/p\\/return-refund-policy\"},{\"title\":\"Payments\",\"text\":\"Accepted payment methods, failed payments and billing questions.\",\"link_url\":\"#\\/p\\/faqs\"},{\"title\":\"Account & login\",\"text\":\"Sign-in help, changing your details and deleting your account.\",\"link_url\":\"#\\/p\\/faqs\"},{\"title\":\"Shipping info\",\"text\":\"Delivery areas, timing and fees.\",\"link_url\":\"#\\/p\\/shipping-info\"},{\"title\":\"Report an issue\",\"text\":\"Missing, wrong or damaged items \\u2014 and how we make it right.\",\"link_url\":\"#\\/p\\/purchase-protection\"}]},{\"type\":\"rich_text\",\"markdown\":\"**Still stuck?** Open the order in your account and tap **Get help** \\u2014 it reaches our team with the order already attached, or use **Chat with us** from the Support menu for a general question.\"},{\"type\":\"cta\",\"heading\":\"Browse full FAQs\",\"text\":\"Detailed answers on orders, payments, warranty and your account.\",\"button_label\":\"View FAQs\",\"button_url\":\"#\\/p\\/faqs\"}]', 1, 1, 'help', '[\"main_menu\",\"main_footer\"]', 2, '2026-09-18 04:32:04', '2026-09-21 05:03:26'),
(21, 'purchase-protection', NULL, 'NexTech purchase protection', NULL, 'Every NexTech order is automatically covered \\u2014 no sign-up, no extra cost.', '[{\"type\":\"hero\",\"heading\":\"Every order is covered\",\"text\":\"NexTech purchase protection applies automatically \\u2014 there\'s nothing to sign up for and nothing extra to pay.\"},{\"type\":\"feature_grid\",\"heading\":\"What\'s covered\",\"items\":[{\"title\":\"Item not as described\",\"text\":\"If what arrives doesn\'t match the listing, we\'ll make it right.\"},{\"title\":\"Faulty or damaged on arrival\",\"text\":\"Report it from your order history and we\'ll repair, replace or refund it.\"},{\"title\":\"Item never arrived\",\"text\":\"If tracking shows delivered but you never received it, contact support \\u2014 we\'ll investigate and resolve it.\"},{\"title\":\"Secure payments\",\"text\":\"Card details are handled by our PCI-compliant payment provider and are never stored on NexTech servers.\"}]},{\"type\":\"steps\",\"heading\":\"How to make a claim\",\"items\":[{\"title\":\"Open the order\",\"text\":\"Go to Account -> Orders and find the order in question.\"},{\"title\":\"Tap Get help\",\"text\":\"Tell us what went wrong \\u2014 missing item, damage, or something else.\"},{\"title\":\"We review it\",\"text\":\"Most claims are resolved within a few days.\"},{\"title\":\"Refund or replacement\",\"text\":\"Approved claims are refunded to your original payment method, or replaced, whichever you prefer.\"}]},{\"type\":\"cta\",\"heading\":\"Have a question first?\",\"text\":\"Check the full return and refund policy for the specifics.\",\"button_label\":\"Return & refund policy\",\"button_url\":\"#\\/p\\/return-refund-policy\"}]', 1, 1, 'help', '[\"main_menu\",\"main_footer\"]', 3, '2026-09-18 04:32:04', '2026-09-21 05:03:26'),
(22, 'sitemap', NULL, 'Sitemap', NULL, '**Shop**\n- [Home](#/)\n- [Lightning Deals](#/deals/lightning)\n- [Unbeatable Deals](#/deals/unbeatable)\n\n**Company**\n- [About Us](#/p/about)\n- [Blog](#/p/blog)\n- [Careers](#/p/careers)\n- [Press](#/p/press)\n- [Contact us](#/p/contact)\n\n**Customer service**\n- [Return and refund policy](#/p/return-refund-policy)\n- [Shipping info](#/p/shipping-info)\n- [Privacy Policy](#/p/privacy)\n- [Terms of Service](#/p/terms)\n- [Security](#/p/security)\n\n**Help**\n- [Support center & FAQ](#/p/support-center)\n- [NexTech purchase protection](#/p/purchase-protection)\n- [FAQs](#/p/faqs)', NULL, 1, 1, 'help', '[\"main_footer\"]', 4, '2026-09-18 04:32:04', '2026-09-18 04:32:04'),
(23, 'accessibility', NULL, 'Accessibility', NULL, 'NexTech is committed to making this storefront usable for everyone. If you hit an accessibility barrier anywhere on the site, email **accessibility@nextech.example** and we\'ll look into it.', NULL, 1, 1, 'bottom', '[\"main_footer\"]', 1, '2026-09-18 04:32:04', '2026-09-18 04:32:04'),
(24, 'cookie-preferences', NULL, 'Cookie preferences', NULL, 'This demo storefront uses only the storage needed to keep you signed in and remember your cart — no third-party tracking or ad cookies. See the [Privacy Policy](#/p/privacy) for details.', NULL, 1, 1, 'bottom', '[\"main_footer\"]', 2, '2026-09-18 04:32:04', '2026-09-18 04:32:04'),
(25, 'affiliate-program', NULL, 'Affiliate & Influencer Program', NULL, '**Earn by sharing NexTech**\n\nJoin our affiliate program and earn a commission whenever someone buys through your link — no minimum audience size required.\n\n**How it works**\n1. Apply with your website, blog or social channel.\n2. Get a unique tracking link and creative assets.\n3. Earn a percentage of every qualifying order.\n\nThis is placeholder content for a demo storefront — replace it with a real application form in **Admin -> Pages**.', NULL, 1, 1, 'company', '[\"main_footer\"]', 6, '2026-09-21 02:43:42', '2026-09-21 02:43:42'),
(26, 'community-program', NULL, 'NexTech\'s Recycling Program', NULL, '**Trade in, don\'t throw out**\n\nBring an old phone, laptop or charger to any partner store and we\'ll recycle it responsibly — no purchase required.\n\nEligible trade-ins may also qualify for a store credit voucher, shown at checkout when available.\n\nThis is placeholder content for a demo storefront.', NULL, 1, 1, 'company', '[\"main_footer\"]', 7, '2026-09-21 02:43:42', '2026-09-21 02:43:42'),
(27, 'intellectual-property-policy', NULL, 'Intellectual property policy', NULL, 'NexTech respects the intellectual property rights of others and expects sellers and partners to do the same.\n\n**Reporting infringement**\n\nIf you believe a listing on NexTech infringes your copyright, trademark or other IP rights, email **ip@nextech.example** with:\n- A description of the right you hold\n- A link to the listing in question\n- Your contact details\n\nWe review reports and remove infringing listings where appropriate.', NULL, 1, 1, 'legal', '[\"main_footer\"]', 6, '2026-09-21 02:43:42', '2026-09-21 02:43:42'),
(28, 'product-safety-alerts', NULL, 'Your Recalls and Product Safety Alerts', NULL, 'If a product you\'ve bought from NexTech is ever subject to a manufacturer safety recall, we\'ll email the account it was ordered from with instructions.\n\nYou can also check your order history at any time in **Account -> Orders** and contact support if you\'re unsure whether an item is affected.\n\nThis is placeholder content for a demo storefront.', NULL, 1, 1, 'legal', '[\"main_footer\"]', 7, '2026-09-21 02:43:42', '2026-09-21 02:43:42'),
(29, 'report-suspicious-activity', NULL, 'Report suspicious activity', NULL, '**Think something\'s not right?**\n\nIf you\'ve received a suspicious email, text or call claiming to be from NexTech, or spotted a listing that looks like a scam, tell us.\n\nEmail **security@nextech.example** with as much detail as you can — screenshots, sender addresses and order numbers all help.\n\nNexTech will never ask for your password or full card number over email or phone.', NULL, 1, 1, 'legal', '[\"main_footer\"]', 8, '2026-09-21 02:43:42', '2026-09-21 02:43:42'),
(30, 'safety-center', NULL, 'Safety center', NULL, 'A few habits keep your NexTech account and payments safe.', '[{\"type\":\"hero\",\"heading\":\"Shopping safely on NexTech\",\"text\":\"A few habits keep your account and your money safe \\u2014 most scams rely on skipping one of these.\"},{\"type\":\"feature_grid\",\"heading\":\"Keep these in mind\",\"items\":[{\"title\":\"We never ask for your password\",\"text\":\"Not by email, not by phone, not by chat. Anyone asking is not really NexTech.\"},{\"title\":\"Pay only through checkout\",\"text\":\"Every legitimate NexTech purchase goes through our checkout. Never send money directly to a seller or courier.\"},{\"title\":\"Check the order before you pay cash\",\"text\":\"For cash on delivery, confirm the order number and items match what you ordered before handing over payment.\"},{\"title\":\"Verify delivery riders\",\"text\":\"Every rider on NexTech is verified before they can accept an order, and your delivery is tracked live in your account.\"}]},{\"type\":\"rich_text\",\"markdown\":\"**Spotted something suspicious?** Don\'t engage \\u2014 report it instead. See [Report suspicious activity](#\\/p\\/report-suspicious-activity) for how.\"},{\"type\":\"cta\",\"heading\":\"Need to reach us?\",\"text\":\"Support is available from the top menu any time.\",\"button_label\":\"Contact support\",\"button_url\":\"#\\/p\\/support-center\"}]', 1, 1, 'help', '[\"main_menu\",\"main_footer\"]', 5, '2026-09-21 02:43:42', '2026-09-21 05:03:26'),
(31, 'partner-with-nextech', NULL, 'Partner with NexTech', NULL, '**Sell on NexTech**\n\nRun an electronics store or brand? Apply to list your products on NexTech and reach customers through our delivery network.\n\nEmail **partners@nextech.example** with your business details to get started.\n\nThis is placeholder content for a demo storefront — replace it with a real seller-application flow in **Admin -> Pages**.', NULL, 1, 1, 'help', '[\"main_footer\"]', 6, '2026-09-21 02:43:42', '2026-09-21 02:43:42'),
(32, 'seller-terms', NULL, 'Seller Terms & Conditions', NULL, '## Seller Terms\n\nBy selling on NexTech you agree to our seller policies, commission structure, and content guidelines.\n\n- Products must be accurately described, with accurate images and pricing.\n- Counterfeit, replica, stolen, or otherwise fake products are strictly prohibited. Listing such items is a material breach of these terms.\n- A platform commission is deducted from each order sold through your shop, credited to your seller balance.\n- Violations of these terms may result in product removal or account suspension.\n\n### Payouts\n\n- Payouts are settled manually by NexTech (bank transfer or PayPal, your choice) outside the app.\n- Your balance must reach the platform\'s minimum payout threshold before a payout is issued — small amounts are batched into a single transfer rather than paid out after every order.\n- You are responsible for keeping your payout details accurate and up to date.\n\n### Dispute Resolution\n\n- All decisions made by NexTech regarding orders, listings, commissions, payouts, suspensions, or other platform matters are final and binding on the seller.\n- The seller agrees to resolve any dispute with NexTech through the platform\'s internal review process, and waives the right to pursue external legal action against NexTech arising from such decisions, to the maximum extent permitted by applicable law.\n- NexTech reserves the right to pursue legal action against a seller for breach of these terms, including but not limited to selling counterfeit, fake, or otherwise fraudulent products, or other unlawful conduct.', NULL, 0, 0, 'company', '[\"seller_footer\"]', 0, '2026-09-22 05:51:57', '2026-09-24 00:54:01'),
(36, 'prohibited-products', 'seller-services-agreement', 'Prohibited Products List', NULL, '_Release date: September 24, 2026_\n\nAs the seller of Your Products, you have the ultimate responsibility to ensure that Your Products comply with applicable laws, regulations, industry standards and the NexTech Seller Rules. We adopt this Prohibited Products List to give you guidance as to what products cannot be offered for sale on the NexTech Platform. This list is not legal advice, nor is it meant to be exhaustive. We reserve the right to interpret and define the scope of the categories on this list. You should carefully review the items on this list and ensure that Your Products do not fall into any of them. If you are not sure whether Your Products are covered by this list, we strongly encourage you to seek advice with your legal counsel or contact us for clarification.\n\nIf Your Products are in violation of applicable laws, regulations, industry standards or the NexTech Seller Rules, we will take corrective measures as we see fit, including but not limited to immediately removing the product listings, cancelling relevant orders and refunding to buyers, requiring you to conduct voluntary or mandatory recalls, suspending or terminating your access to some or all of the Services, temporarily or permanently withholding payments, and/or taking other actions available under the NexTech Seller Services Agreement.\n\n## 1. Firearms, ammunition, explosives, weapons and controlled devices\n\n1.1. Firearms and their replicas, parts and accessories (except toy guns that do not exactly resemble or resemble with near precision a firearm);\n\n1.2. Ammunition and their replicas and components;\n\n1.3. Explosives (e.g. fireworks, flares and grenades), explosive devices (flare guns, grenade launchers, projectile and concussive products), products that contain explosive materials (e.g. explosive fuses, blasting agents and detonators), and their respective replicas, parts and accessories (except plastic toy grenades);\n\n1.4. Certain knives and bladed products, including automatic knives, butterfly knives, gravity knives, switchblade knives, machetes, disguised knives, push daggers, belt buckle knives and any device having a length of less than 30 cm and resembling an innocuous object but designed to conceal a knife or blade, but excluding kitchen knives and toy swords;\n\n1.5. Controlled devices that can temporarily incapacitate or cause significant bodily harm to a person (e.g. kubotans, brass knuckles, nunchakus, throwing stars and stun guns);\n\n1.6. Items that contain information about how to make firearms, ammunition, explosives, weapons and controlled devices.\n\n## 2. Regulated substances and related devices\n\n2.1. Flammable or explosive chemicals (e.g. black powder, fireworks, flares, gasoline and regulated explosives precursors);\n\n2.2. Products containing radioactive substances;\n\n2.3. Products containing toxic or hazardous chemicals (e.g. mercury, hydrofluoric acid, nitric acid, sodium azide, cyanide, PFAS and carbon tetrachloride);\n\n2.4. Products containing ozone-depleting substances (e.g. CFCs, HCFCs, halons, methyl bromide and methyl chloroform);\n\n2.5. Radiation devices (e.g. X-ray machines, accelerators and neutron generators).\n\n## 3. Drugs, drug paraphernalia and dietary supplements\n\n3.1. Prescription drugs and OTC drugs for human or animal;\n\n3.2. Natural health products (e.g. dietary supplements, probiotics, herbal remedies) that do not comply with applicable laws and regulations;\n\n3.3. Products that make misleading or unauthorized health claims;\n\n3.4. Controlled substances, ingredients primarily used for producing controlled substances, products (e.g. dietary supplements) containing controlled substances;\n\n3.5. Narcotics and psychotropic drugs such as poppy, opium, coca leaves and their preparations and derivatives;\n\n3.6. Products containing marijuana;\n\n3.7. Products that claim to provide \"legal high\" or similar effects;\n\n3.8. Products that are primarily intended or designed for making, preparing, or using controlled substances (e.g. bongs, vaporizers, pill presses and capsule fillers).\n\n## 4. Medical devices and accessories\n\n4.1. Medical devices that do not meet applicable requirements on establishment registration, device approval or clearance, product listing, quality management, labelling, marketing and other regulatory requirements;\n\n4.2. High-risk (e.g. Class III) medical devices;\n\n4.3. Prescription medical devices that are not sold OTC to general consumers;\n\n4.4. Non-invasive devices (e.g. smartwatches and smart rings) claiming to measure blood glucose levels;\n\n4.5. Products with ultrasound technology marketed for wrinkle removal or weight loss.\n\n## 5. Cosmetic and personal care products\n\n5.1. Cosmetic and personal care products that do not meet applicable laws and regulatory requirements, including but not limited to product registration or notification, product safety, labelling, packaging, marketing and other related requirements;\n\n5.2. Cosmetic and personal care products with misleading health or therapeutic representations and indications (e.g., claims that may mislead consumers into thinking the product is a drug);\n\n5.3. Cosmetic and personal care products that contain prohibited or restricted ingredients or controlled substances;\n\n5.4. Cosmetic and personal care products that are intended for use by medical professionals or under medical supervision;\n\n5.5. Cosmetic and personal care products that are subject to recalls or safety alerts;\n\n5.6. Cosmetic and personal care products used to exfoliate or cleanse that contain plastic microbeads.\n\n## 6. Offensive or controversial products\n\n6.1. Products containing violent, terroristic, hateful, illegal, offensive or otherwise controversial material;\n\n6.2. Products that promote, incite or glorify violence, terrorism or hate;\n\n6.3. Products that promote, incite or glorify discrimination based on race, gender, religion, ethnicity, sexual orientation, or any other protected class;\n\n6.4. Products containing pornographic and obscene materials;\n\n6.5. Products that depict or suggest child abuse, child exploitation or children in a sexually suggestive manner;\n\n6.6. Used and unwashed underwear and similar products.\n\n## 7. Products for military, police and other government agencies\n\n7.1. Military, police and other law enforcement uniforms, gears, devices, supplies, accessories and badges;\n\n7.2. Products that misuse logos, names, images or marks representing military, police or other government agencies.\n\n## 8. Surveillance and hacking equipment\n\n8.1. Software and hardware used for intercepting public or private communication without consent (except answering machines, video cameras and baby monitors);\n\n8.2. Software, hardware and devices used for hacking, decrypting, decoding public or private communication without consent, including intercepting any function of a computer system;\n\n8.3. Devices used for recording private conduct without consent;\n\n8.4. Devices designed or used for blocking, jamming or interfering with law enforcement radar, laser signals, or traffic signals (e.g. jammers, laser or radar shifters).\n\n## 9. Gambling and lottery products\n\n9.1. Slot machines (except toy slot machines that are not operated with money);\n\n9.2. Lottery tickets;\n\n9.3. All gambling and lottery products prohibited from being sold under any applicable law or regulation.\n\n## 10. Tools and devices for illegal activities\n\n10.1. Tools and services used to harass others;\n\n10.2. Lock picking or locksmithing tools and devices, including tools or devices used for breaking into a place, motor vehicle, vault or safe;\n\n10.3. Tools and devices that facilitate shoplifting;\n\n10.4. Card skimming devices;\n\n10.5. Motor vehicle master keys;\n\n10.6. Unauthorized cable TV converter boxes;\n\n10.7. Tools and devices that are primarily designed or used to harass people or encourage or facilitate illegal activities;\n\n10.8. Products that display or disclose personal data (e.g. ID numbers or residential addresses).\n\n## 11. Government papers and documents\n\n11.1. Documents, certificates, tickets, papers, seals, badges, medals, identity cards and other identity documents issued by government agencies;\n\n11.2. Devices, materials and information used for making, forging or altering documents listed in 11.1.\n\n## 12. Cash, cash equivalents, gift cards and coupons\n\n12.1. Paper money, bank notes and coins, their imitations, replicas and counterfeits;\n\n12.2. Money orders, checks, traveler\'s checks or other cash equivalent instruments;\n\n12.3. Stock and securities;\n\n12.4. Gift cards, prepaid cards or other stored value products;\n\n12.5. Vouchers, coupons, food instruments;\n\n12.6. Virtual currencies;\n\n12.7. Gold, silver, and precious metal bullions that are non-compliant with applicable laws and regulations (e.g., lacking necessary markings as required by applicable laws and regulations);\n\n12.8. Devices and materials (including manuals or instructions) used to make, forge or alter the above.\n\n## 13. Animals and plants\n\n13.1. Live animals;\n\n13.2. Parts or products from animals of endangered or threatened species (e.g. fur and feathers);\n\n13.3. Parts or products from cats or dogs;\n\n13.4. Parts or products from animals that are prohibited by applicable laws, including but not limited to wildlife and conservation laws and the Convention on International Trade in Endangered Species of Wild Fauna and Flora (CITES);\n\n13.5. Hunting and trapping devices for animals of endangered or threatened species, or any other species captured in item 13.4;\n\n13.6. Products that encourage, promote or facilitate animal cruelty;\n\n13.7. Plants, seeds and their products that are dangerous or fatal when touched or consumed;\n\n13.8. Plants, seeds and their products that are designated as \"invasive\" or \"pests\", or similarly classified or prohibited, by federal, state or local government agencies;\n\n13.9. Plants, seeds and their products that are imported without the required permits or inspections;\n\n13.10. Plants, seeds and their products that do not comply with applicable laws (e.g., prohibited or requiring licenses/permits under applicable laws).\n\n## 14. Tobaccos and tobacco products\n\n14.1. Tobacco or products containing tobacco (e.g. cigarettes, cigars, nicogel and smokeless tobacco);\n\n14.2. Electronic cigarettes and related products;\n\n14.3. Nicotine replacement products (e.g. nicotine gum, lozenges, pouches and patches);\n\n14.4. Nicotine inhalers or nasal sprays;\n\n14.5. Products with brands or logos of tobacco or products containing tobacco.\n\n## 15. Alcohol\n\n15.1. Alcoholic beverages;\n\n15.2. Alcohol licenses.\n\n## 16. Human body parts, remains, and mortuary products\n\n16.1. Human body parts and remains, including genuine bones, skeletons, waste, organs, reproductive materials, but excluding wigs;\n\n16.2. Grave markers and tombstones;\n\n16.3. Burial items, including burial artifacts, and mortuary products.\n\n## 17. Products with potential safety issues\n\n17.1. Children\'s drawstring tops;\n\n17.2. Wired blinds and curtains;\n\n17.3. Padded crib bumpers, supported and unsupported vinyl cushion pad cover as well as vertical crib slat covers;\n\n17.4. Baby inclined sleepers;\n\n17.5. Drop side cribs;\n\n17.6. Magnets/magnet sets sold as entertainment toys (such as puzzles, sculpture making, mental stimulation or stress relief) or for children;\n\n17.7. Pictures of baby masks or babies wearing masks;\n\n17.8. Novelty lighters, such as lighters in the shapes of cartoon characters, toys, guns, watches, vehicles, etc.;\n\n17.9. Water-absorbing beads, jelly beads, water-absorbing balls, water balloons, polymer beads, gel beads and related products;\n\n17.10. Kites with metal parts or kites/kite strings used in kite battles;\n\n17.11. Sky lanterns and floating lanterns;\n\n17.12. Inflatable floats for children\'s necks;\n\n17.13. Water walking ball;\n\n17.14. Heated seat cushions for cars, and pure fabric car seats;\n\n17.15. Weighted infant sleep products;\n\n17.16. Infant sleep positioning products;\n\n17.17. Infant sleep products that do not comply with applicable safety standards (e.g., cribs, cradles, bassinets, mattress supports, mesh sides, and other components that are part of cribs, cradles, or bassinets, and play pen sleep accessories);\n\n17.18. Beaded and amber teething jewelry;\n\n17.19. Beaded clips and chains;\n\n17.20. Mushroom-shaped infant teether or pacifiers;\n\n17.21. Eclipse glasses and filters for solar viewing;\n\n17.22. Baby self-feeding products;\n\n17.23. Bicycle, ski, or snowboard helmets that do not comply with applicable regulations, standards, and requirements;\n\n17.24. Automotive products that are prohibited from being sold or not in compliance with any applicable law or regulation (including but not limited to seat belts, airbags, child restraint systems and tires).\n\n## 18. Recalled products\n\n18.1. Any products that do not comply with applicable consumer product safety laws and regulations (e.g. candles that spontaneously reignite, children\'s jewelry that contains more than the permitted levels of lead or cadmium, non-child resistant lighters, yo-yo toys stretching to 500 mm or more in length);\n\n18.2. Products subject to recalls (voluntary or mandatory), market withdrawals, and/or stop sales whether or not they are publicly announced.\n\n## 19. Others\n\n19.1. Other products, content, and/or related information prohibited from being published or sold under any applicable law or regulation.', NULL, 1, 0, 'legal', '[\"seller_footer\"]', 20, '2026-09-24 00:36:43', '2026-09-24 00:52:50'),
(37, 'seller-code-of-conduct', 'seller-services-agreement', 'Seller Code of Conduct', NULL, '_Release date: September 24, 2026_\n\nAs a seller on the NexTech Platform, you must follow this code of conduct when selling Your Products to buyers on the NexTech Platform. Violation of this code of conduct may result in enforcement actions against Your Account in accordance with the NexTech Seller Services Agreement, including but not limited to cancellation of product listings, withholding or forfeiture of payments, suspension or termination of your access to some or all of the Services. Unless otherwise specified, defined terms used in this code of conduct shall have the same meaning as in the NexTech Seller Services Agreement.\n\n## 1. Product Information\n\nYou must ensure that all information about Your Products is true, accurate and not misleading and is in accordance with applicable law. For example, this requires you to (i) only use true, accurate, complete and non-misleading texts and images to describe Your Products on the product listing pages, (ii) list Your Products only in the correct categories, (iii) provide all necessary labels, warnings, product manuals about Your Products on the product listing pages, product packages, product surfaces and other places as appropriate, (iv) provide all testing reports and certificates about Your Products as required by us, and (v) timely update your inventory information.\n\n## 2. Product Quality\n\nYou must ensure that Your Products are of good quality and comply with all quality standards required by applicable laws, regulations and industry standards.\n\n## 3. Unfair Practices\n\nYou may not engage in unfair practices to gain commercial advantages over buyers, other sellers, or us, such as:\n\n- Use deceptive and misleading text and images in your product listings;\n- Use fake orders or similar measures to manipulate sales rank or other metrics of Your Products or of your seller account;\n- Use bots, pay-for-clicks, pay-for-searches, keyword manipulation or similar measures to inflate search results and web traffic to your product listings;\n- Use artificially low prices to bait consumers and switch them to higher prices at or after the time of order;\n- Use prices that are unattainable due to the mandatory payment of additional non-governmental fees, or advertise a product price together with a manufacturer\'s suggested retail price or any other higher price at which products are not regularly sold;\n- Intentionally harm another seller or their product listings; or\n- Use fake orders or similar tactics during promotional events to fraudulently obtain or utilize coupons or other subsidies offered by us.\n\n## 4. Product Ratings and Reviews\n\nYou may ask buyers to rate or leave reviews about Your Products, but you may not provide or offer incentives for such feedback or reviews, you may not artificially inflate ratings, and you may not take any action to remove or ask us to improperly remove negative feedback or ratings regarding your products, including but not limited to:\n\n- Offering anything of value in exchange for providing a good rating or review or removing bad rating or review;\n- Specifically requesting buyers to give positive ratings or reviews;\n- Asking buyers to improperly change their ratings or reviews;\n- Only asking buyers who have previously provided positive feedback or ratings to give additional ratings or reviews; or\n- Impersonating buyers and attempting to provide feedback on your own sales or product listings.\n\n## 5. Marketing Messages\n\nYou should only communicate with buyers within the messaging system within the NexTech Platform. Contacting buyers or diverting communication or transactions off the NexTech Platform is strictly prohibited. Your communication with buyers should also be about responding to their inquiries, fulfilling orders, providing after-sale services or customer services. You may not send unsolicited marketing or promotional messages to users of the NexTech Platform.\n\n## 6. Counterfeits and Infringing Products\n\nSale of counterfeit products is strictly prohibited. You should respect the intellectual property rights of others and ensure that Your Products and product listings on the NexTech Platform do not infringe, misappropriate, or otherwise violate the intellectual property rights (including but not limited to copyrights, trademarks, patents, trade secrets, logos or similar business identifiers) or other proprietary rights of NexTech or any third party.\n\n## 7. Commercial Bribery\n\nYou must not bribe or attempt to bribe in any form (e.g. offer money, gifts, meals, entertainment, or other things of value) any of NexTech\'s employees, contractors, agents or representatives.\n\n## 8. Communication\n\nYou should always communicate with buyers and NexTech staff in a respectful manner, and should never use offensive or abusive language which may contain personal attacks or insults.', NULL, 1, 0, 'legal', '[\"seller_footer\"]', 21, '2026-09-24 00:36:43', '2026-09-24 00:52:50');
INSERT INTO `pages` (`id`, `slug`, `parent_slug`, `title`, `banner_image`, `content`, `sections`, `is_published`, `show_in_footer`, `footer_group`, `menu_placements`, `sort_order`, `created_at`, `updated_at`) VALUES
(38, 'anti-fraudulent-transactions-policy', 'seller-services-agreement', 'Anti-Fraudulent Transactions Policy', NULL, '_Release date: September 24, 2026_\n\nWe organize and sponsor a variety of promotional events to give users a fun and enjoyable shopping experience on the NexTech Platform and help you sell Your Products. Many of these promotional events use coupons or other forms of incentives to reward user participants. In order for real users to enjoy the benefits of these incentives, we adopt this Anti-Fraudulent Transactions Policy. You must carefully review the terms of this policy and strictly comply with them in your operations on the NexTech Platform. Capitalized terms used but not defined in this policy shall have the same meaning as those in the NexTech Seller Services Agreement.\n\n## 1. What is a Fraudulent Transaction?\n\nA Fraudulent Transaction refers to a transaction on the NexTech Platform between a NexTech Seller and a buyer who is related to or otherwise acts at the direction of or in concert with the NexTech Seller, primarily for the purpose of obtaining coupons, gifts, credits, rewards, givebacks or other things of value (collectively, the \"Benefits\") sponsored by us. Examples of buyers who are related to or otherwise act at the direction of or in concert with the NexTech Seller include but are not limited to:\n\n- (i) An administrator, operator, emergency contact person of the seller\'s NexTech account or Affiliated Accounts;\n- (ii) A director, officer, manager, employee, contractor, agent, representative of the seller; and\n- (iii) Persons who have familial or business relationships with the persons listed in (i) and (ii) above.\n\n## 2. How do we detect Fraudulent Transactions?\n\nWe monitor and analyze transaction activities, data and information to detect abnormalities leading to Fraudulent Transactions, including seller network, buyer network, purchase record, fulfillment record, etc. If we suspect that an abnormality suggests Fraudulent Transaction(s), we may make an information request to you, in which case you shall provide a reasonable explanation with supporting evidence within the time limit specified by us.\n\n## 3. How are Fraudulent Transactions processed?\n\nWithout prejudice and in addition to all rights and remedies available to us in the NexTech Seller Services Agreement, we are also entitled to assess a charge to recover damages and costs incurred that are caused by any Fraudulent Transaction in the amount of ten (10) times the value of the Benefits involved in the Fraudulent Transaction. For the avoidance of doubt, we may assess this charge even if you did not receive the Benefits involved in the Fraudulent Transaction.\n\nYou recognize that Fraudulent Transactions are extremely harmful to both the NexTech Platform and the real buyers. It is also increasingly challenging to detect Fraudulent Transactions because violators are becoming more and more sophisticated. As such, we will incur significant costs to put in place monitoring systems and resources to detect and prevent them. The amount of charge represents a genuine estimate of the damages suffered by us and the costs we have incurred or would incur in connection with monitoring, detection, investigation and prevention in connection with a Fraudulent Transaction.', NULL, 1, 0, 'legal', '[\"seller_footer\"]', 22, '2026-09-24 00:37:52', '2026-09-24 00:37:52'),
(39, 'seller-fulfillment-policy', 'seller-services-agreement', 'Seller Fulfillment Policy', NULL, '_Release date: September 24, 2026_\n\n## 1. General\n\n1.1. In order to provide high-quality and consistent user experience to customers on the NexTech Platform and transparency about how we evaluate and take enforcement actions about order fulfillment, we adopt this Seller Fulfillment Policy (this \"Policy\"). This Policy applies to all orders placed on the NexTech Platform and fulfilled within the United States, including both orders fulfilled by third-party logistics service providers from the list in the NexTech Seller Center with the NexTech backend system and orders fulfilled by third-party logistics service providers not on the list in the NexTech Seller Center. You must carefully review the terms of this Policy and strictly adhere to them in the fulfillment of orders of Your Products.\n\n1.2. Capitalized terms used but not defined in this Policy shall have the same meaning as those in the NexTech Seller Services Agreement.\n\n## 2. Time Limits\n\n2.1. When you add a new product in Your Account, you shall set for the product the \"Handling Time\", which is the time you need to prepare the product ready for shipping, and the \"Transit Time\", which is the time the logistics service provider needs to complete delivery of the product to buyers. When a buyer places an order for a product, we will provide the \"Ship Date\" (i.e. the date by which you shall ship the product to the buyer) and the \"Delivery Date\" (i.e. the date by which the product should be delivered to the buyer) for the product based on its Handling Time and Transit Time. You shall adhere to these time limits when fulfilling your orders. If you fail to set the Handling Time or the Transit Time for a product, we will assume the Handling Time to be one (1) operating day and the Transit Time to be two (2) operating days. You may make changes to the Handling Time and the Transit Time or select different third-party logistics service providers for Your Products at any time, which may affect the time limits for all orders created after the changes are made. But for all orders created before the changes, the original time limits shall still apply.\n\n2.2. When calculating the Handling Time and the Transit Time, we will only count business days unless you selected different operating days in the Shipping Settings section in Your Account, in which case we will count all operating days selected by you.\n\n2.3. You shall always ship your order on or before 23:59:59 of the Ship Date, which means uploading the order\'s carrier tracking number to Your Account. If you use a third-party logistics service provider on the list of the NexTech Seller Center, you can use NexTech\'s backend system to generate and upload the carrier tracking number. If you use a third-party logistics service provider not on the list of the NexTech Seller Center, you should obtain the carrier tracking number from the logistics service provider of your choice and manually upload it to Your Account. If you split an order into several packages, the order is shipped when you upload all carrier tracking numbers under the order to Your Account.\n\n2.4. Orders of Your Products shall always be delivered to buyers on or before 23:59:59 of their Delivery Dates, which can be evidenced by delivery scan records or other confirmation records accepted by us.\n\n2.5. All time limits are calculated based on the time zone in which the delivery address is located.\n\n2.6. If you become aware that an order can\'t be shipped or delivered within the time limits in Section 2.3, you should contact us immediately and provide us a reasonably detailed explanation for the delay.\n\n## 3. Delays\n\n3.1. If an order is not delivered on or before 23:59:59 of the Delivery Date, unless otherwise agreed with you, we may charge liquidated damages of US$5 per order and deduct the amount from Your Account. The liquidated damages amount is a reasonable and genuine pre-estimate of our losses which may include compensating the buyer and covering all costs incurred by us. If an order contains different products with different Delivery Dates, the latest Delivery Date shall apply to this order. For the avoidance of doubt, the charge of liquidated damages under this Section 3.1 does not release you from your obligation to fulfill the order.\n\n## 4. Out-of-Stocks\n\n4.1. If (i) you inform us or the buyer that one or more products in an order are out-of-stock, (ii) you inform us or the buyer that you can\'t fulfill an order for any reason or impose additional condition(s) on the fulfillment of the order (e.g. the buyer must pay additional fees, the buyer must pick up the order from designated location, the buyer must purchase more products first, etc.) (iii) you ask the buyer to cancel an order and apply for refund, (iv) you fail to ship an order by its Ship Date and do not respond to inquiries by us or the buyer, or (v) you fail to ship an order within seven (7) operating days, such order shall be deemed an out-of-stock order.\n\n4.2. Unless otherwise agreed with you, depending on the severity of the case, we may (i) cancel the order and refund the buyer, (ii) charge liquidated damages of US$5 per order and deduct the amount from Your Account, (iii) remove and ban from the NexTech Platform the listings of out-of-stock products, (iv) fulfill the order with the same products from other NexTech sellers at your expense, and/or (v) suspend processing disbursement of Your Account. The liquidated damages amount is a reasonable and genuine pre-estimate of our losses which may include compensating the buyer and covering all costs incurred by us.\n\n## 5. Abnormal and Fraudulent Fulfillment\n\n5.1. If (i) you upload a false carrier tracking number for an order; (ii) the order is not delivered to the buyer within a reasonable period after the corresponding carrier tracking number(s) are uploaded, or (iii) you use other abnormal methods to avoid fulfilling the order in accordance with the terms of the NexTech Seller Services Agreement and this Policy, you have committed abnormal fulfillment with respect to such order (the \"Abnormal Fulfillment\").\n\n5.2. For the purpose of Section 5.1(i), examples of false carrier tracking number include but not limited to:\n\n(1) the logistics information corresponding to the carrier tracking number is not available at the official website of the selected logistics service provider within 48 hours after you upload the carrier tracking number;\n\n(2) according to the official website of the selected logistics service provider, the package corresponding to the carrier tracking number has not been picked up within 48 hours after you upload the carrier tracking number;\n\n(3) the tracking information corresponding to the carrier tracking number does not match the actual delivery of the package(s) in the order (e.g. the carrier tracking number has been used for another order with different delivery information within 30 days; the pick-up information corresponding to the carrier tracking number does not match the shipping address under Your Account, the delivery information corresponding to the carrier tracking number does not match that of the order details; the tracking events corresponding to the carrier tracking number do not match the delivery track of the order or display an abnormal delivery track of the order).\n\n5.3. For the purpose of Section 5.1(ii), we may decide the reasonable period for an order taking into consideration the specific circumstances of the case.\n\n5.4. If you (i) deliver an empty package to the buyer, (ii) deliver a package of product(s) that are different from what the buyer ordered, (iii) only deliver some but not all of the products ordered by the buyer, or (iv) use other fraudulent methods that we consider to be egregious or particularly harmful to buyer\'s shopping experience, in each case to avoid fulfilling the order in accordance with the terms of the NexTech Seller Services Agreement and this Policy, you have committed aggravated fraudulent fulfillment with respect to such order (the \"Fraudulent Fulfillment\").\n\n5.5. If we determine that you have committed Fraudulent Fulfillment with respect to twenty (20) or more orders shipped in one day, accounting for 5% or more of the total number of shipped orders of a particular Standard Product Unit (\"SPU\") for the day, we may presume that you have committed Fraudulent Fulfillment with respect to all orders of the SPU shipped during that day.\n\n5.6. With respect to each Abnormal Fulfillment order under Section 5.1, we may charge liquidated damages of US$10 per order or 50% of the Disbursement Price of the order, whichever is higher, and deduct the amount from Your Account, provided, however, that the amount shall not exceed US$200 per order. The \"Disbursement Price,\" with respect to a product, means the aggregate Base Prices of Your Products in the order as shown in the transaction details page.\n\n5.7. With respect to each Fraudulent Fulfillment order under Section 5.4, regardless whether it is determined or presumed, we may charge liquidated damages of US$10 per order or 100% of the Disbursement Price of the order, whichever is higher, and deduct the amount from Your Account, provided, however, that the amount shall not exceed US$500 per order.\n\n5.8. The liquidated damages amounts in Sections 5.6 and 5.7 are reasonable and genuine pre-estimates of our losses and costs which include compensating buyers and covering all other expenses incurred by us.\n\n5.9. Without prejudice and in addition to our rights under Sections 5.6 to 5.8, with respect to each Abnormal Fulfillment and each Fraudulent Fulfillment order, regardless whether determined or presumed, unless otherwise agreed with you, we may, to the fullest extent permitted by Applicable Laws, (i) cancel the order and refund the buyer, (ii) remove and ban from the NexTech Platform the listings of products involved in Abnormal Fulfillment and Fraudulent Fulfillment, (iii) fulfill the order with the same products from other NexTech sellers at your expense, (iv) suspend processing disbursement of Your Account, (v) suspend, restrict or terminate your access to Your Account, (vi) require you to complete the fulfillment and delivery of the order, and/or (vii) suspend, restrict, limit or terminate some or all of the Services.\n\n5.10. We may use information from the official websites of all carriers used to deliver your orders to determine whether you have committed Abnormal Fulfillment or Fraudulent Fulfillment. If the carrier changes, we may use the new carrier tracking number and its corresponding logistics information to make those determinations.\n\n## 6. Free Shipping Undertakings\n\n6.1. You shall be liable to pay for all shipping expenses on all orders, including returns. Notwithstanding the above, a buyer may be required to pay shipping fees on (i) any order of less than US$35 at US$2.99 per order or such other amount as determined by us and (ii) any return (other than the first return or the first two returns for certain buyers selected by us) of an order at up to US$9 per return or such other amount as determined by us. To the extent any shipping fee is paid by a buyer or deducted from the refund to a buyer, such amount will be credited to you.\n\n6.2. You may not charge additional fulfillment or delivery fees to buyers or otherwise increase buyers\' fulfillment or delivery costs. Unless you can provide us with an explanation supported by evidence, which we, in our absolute and sole discretion, find reasonable and convincing, with respect to each violation of this Section 6.2, without prejudice and in addition to all remedies available to us under this Policy and the NexTech Seller Services Agreement, we may charge liquidated damages of either (i) US$150 per order or US$150 per package, whichever is higher, or (ii) ten (10) times the additional fees or increased costs borne by the buyer. The liquidated damages are a reasonable and genuine pre-estimate of our losses which may include compensating buyers and covering all costs incurred by us.\n\n## 7. Fraudulent Shipping Labels\n\n7.1. You may not generate shipping labels through counterfeiting, fraud, unauthorized acquisition or transaction (e.g., purchasing shipping labels through channels not approved by LSPs), or any other illegal or unauthorized means, to evade postage fees or commit fraud to logistics service providers (LSPs), buyers and/or us (the \"Fraudulent Shipping Label\").\n\n7.2. With respect to each of the violations in Section 7.1, without prejudice and in addition to all remedies available to us under this Policy and the NexTech Seller Services Agreement, we may charge liquidated damages of US$150 per order using Fraudulent Shipping Label.\n\n7.3. Without prejudice and in addition to our rights under Section 7.2, with respect to each order using Fraudulent Shipping Label, whether determined or presumed, unless otherwise agreed with you, we may, to the fullest extent permitted by Applicable Laws, (i) cancel the order and refund the buyer, (ii) remove and ban from the NexTech Platform the listings of products involved in orders using Fraudulent Shipping Labels, (iii) fulfill the order with the same products from other NexTech sellers at your expense, (iv) suspend processing disbursement of Your Account, (v) suspend, restrict or terminate your access to Your Account, (vi) require you to complete the fulfillment and delivery of the order, and/or (vii) suspend, restrict, limit or terminate some or all of the Services.\n\n7.4. For the avoidance of doubt, you hereby agree and acknowledge that the restrictive measures we shall be entitled to take under Section 7.2 shall not affect or limit the restrictive measures available to us under Sections 5.6 and 5.7. In addition, the liquidated damages caps provided under Section 5.6 and 5.7 shall only apply to the liquidated damages imposed for the violations described in Section 5.\n\n## 8. General\n\n8.1. If the fulfillment of an order is split into shipping and delivery of multiple packages, we may take enforcement action against the order if the shipping and delivery of any of the packages within the order violates this Policy.\n\n8.2. If you violate this Policy, we may take some or all enforcement actions against your violations as permitted by this Policy. The enforcement actions under this Policy are not exclusive but cumulative with all other enforcement actions available under this Policy, all rights, remedies and elections available to us under the NexTech Seller Services Agreement, and all other rights, remedies and elections available to us or the buyers under contract, at law or in equity.\n\n8.3. We may take some or all of the enforcement actions in connection with an order as permitted by this Policy. No waiver of some or all of the enforcement actions with respect to an order shall be deemed, or will constitute, a waiver of our rights with respect to other orders, whether or similar, nor will any waiver constitute a continuing waiver.\n\n8.4. If you violate this Policy, we may enforce against Your Account and your Affiliated Accounts.\n\n8.5. We understand that human errors may occur. Nevertheless, we reserve the right to decide whether your violation is willful or unintentional, which may affect whether we will take enforcement actions or what enforcement actions we may take. If you disagree with our decision on your violation of this Policy or the enforcement action(s) we take, you may file an appeal request from the NexTech Seller Center in accordance with the relevant NexTech Seller Rules.', NULL, 1, 0, 'legal', '[\"seller_footer\"]', 23, '2026-09-24 00:41:51', '2026-09-24 00:41:51'),
(40, 'epr-policy', 'seller-services-agreement', 'Extended Producer Responsibility (EPR) Policy', NULL, '_Release date: September 24, 2026_\n\n## 1. General\n\n1.1. This Extended Producer Responsibility Policy (\"EPR Policy\") constitutes an integral part of the NexTech Seller Services Agreement (hereinafter referred to as the \"Seller Services Agreement\") and is binding on sellers who are subject to extended producer responsibility obligations.\n\n1.2. Unless otherwise specifically stipulated in this EPR Policy, the meanings of the terms used herein shall be the same as those in the Seller Services Agreement.\n\n1.3. Extended Producer Responsibility (\"EPR\") refers to the principle that producers or stewards (including but not limited to sellers) must be responsible for the entire life cycle of the products they produce, import, sell, or otherwise place on the market, including the packaging of such products (hereinafter collectively referred to as \"goods\") in different jurisdictions. Producers can fulfill their extended producer responsibility obligations through the following methods: (1) becoming a member of a producer responsibility organization or stewardship program (hereinafter referred to as \"producer responsibility organization\") and paying fees to that organization or program to cover the costs of collecting and recycling products at their end of life (hereinafter referred to as \"environmental handling fees\"); or (2) implementing their own extended producer responsibility system independently.\n\n1.4. Extended Producer Responsibility Services refer to the related services we provide to sellers from time to time in situations where: (1) sellers elect to use our service or where they have not provided us and/or our designated parties with evidence proving that they and/or their goods have otherwise complied with extended producer responsibility requirements in the relevant jurisdiction; (2) the jurisdiction allows us to provide the extended producer responsibility service to sellers; and (3) we support the corresponding services for that jurisdiction and category scope. These services may include, but are not limited to, contracting with approved producer responsibility organizations, calculating and regularly reporting and paying the relevant environmental handling fees to the producer responsibility organizations based on the sales made by the relevant store, and/or arranging for the recycling of old goods (specifically as supported by us). Sellers may choose our services according to their needs and authorize us to assist them in fulfilling their EPR-related responsibilities.\n\n## 2. NexTech\'s Services and Support\n\n2.1. Under this EPR Policy, we provide sellers with specific extended producer responsibility-related services and technical services, including but not limited to calculate and report on quantities of products placed on the market in the relevant jurisdiction and pay and/or remit environmental handling fees to comply with the seller\'s own extended producer responsibility obligations.\n\n2.2. The seller may apply to use our extended producer responsibility-related services pursuant to this EPR Policy. However, the seller acknowledges and agrees that we retain the sole discretion to determine the inclusion of the seller within the scope of extended producer responsibility-related services.\n\n2.3. We reserve the right to independently determine the jurisdictions and categories for which extended producer responsibility-related services will be provided, based on the circumstances (for example, EPR-related services may not be offered in certain jurisdictions).\n\n2.4. We reserve the right to modify, restrict, or terminate, at any time, in whole or in part, the extended producer responsibility-related services (including but not limited to actions required to comply with applicable laws and regulations). Additionally, we reserve the right to impose service fees for any service or tool under the extended producer responsibility-related services, as detailed in these Terms of Service and/or displayed on the system page (if applicable).\n\n2.5. In the event that the seller does not use our extended producer responsibility-related services under these Terms of Service, or where the service is not available in respect of the jurisdiction or product category, the seller is required to provide valid documentation proving compliance with appropriate extended producer responsibility requirements for themselves and/or their products (including, but not limited to, EPR registration numbers), along with any other documentation requested by us. We retain the right to scrutinize the documentation provided and determine whether the seller has adequately discharged its obligations. Following review by us, the seller shall independently fulfill periodic reporting, payment of environmental handling fees, and/or other relevant obligations as mandated by the relevant producer responsibility organization. Regarding any environmental handling fees or similar expenses that have already been deducted by us in advance from the seller\'s account (if applicable), we reserve the discretion to decide whether to proceed with reporting and payment to the producer responsibility organization based on the circumstances, with the final determination subject to display on the system page.\n\n2.6. The seller acknowledges and understands that we may use system data and other information to estimate the sales of the seller\'s products in relevant markets (including quantities and weights) for the purpose of calculating applicable environmental handling fees. Furthermore, if certain products require the seller to provide product attribute information (such as weight), the seller must ensure timely and accurate submission of such information. Failure to provide timely or accurate product attribute information, or providing false or erroneous information, grants us the right to deduct corresponding environmental handling fees based on the maximum rate published by the producer responsibility organization for similar products, or to implement other measures as outlined in these Terms of Service. Any risks, liabilities, and consequences arising from these actions shall be borne solely by the seller.\n\n2.7. We reserve the right to provide the seller with sales information for their products for review and verification purposes. By accepting these terms, the seller agrees that our system records and displayed information shall constitute valid and reliable evidence for the calculation, declaration, and payment of environmental handling fees.\n\n## 3. Payment and Refund\n\n3.1. Issues relating to the costs incurred by NexTech in connection with the extended producer responsibility-related service are specified in this Article.\n\n3.2. Sellers understand and agree that we may calculate the environmental handling fees (including but not limited to the environmental handling fees that should be borne from the date of the store\'s registration to the date of store closure) payable by sellers based on sales of the seller\'s goods in the relevant market and that we may regularly report and pay the producer responsibility organization in accordance with the requirements of the relevant producer responsibility organization.\n\n3.3. Sellers understand and agree that environmental handling fees should be calculated based on the rates set by the producer responsibility organizations we have contracted with and paid to the producer responsibility organizations in the corresponding currency for those rates. Therefore, sellers agree that we have the right to convert the fees based on the prevailing exchange rate and deduct the corresponding environmental handling fees in the settlement currency of the seller\'s account.\n\n3.4. In the case of consumer returns after the reporting period, the corresponding environmental handling fees that have been deducted will not be refunded.\n\n3.5. If registration fees, management fees, and other miscellaneous fees are required to be paid in accordance with the requirements of the producer responsibility organization, these fees shall be borne by the seller, and the specific amounts will be displayed on the system page.\n\n3.6. If the producer responsibility organization is contracted with through an agency, the registration fees, agency fees, and/or other service fees (if any) charged by the agency shall be borne by the seller, and the specific amounts will be displayed on the system page.\n\n3.7. NexTech only provides extended producer responsibility reporting and environmental handling fee payment services in certain jurisdictions and categories where EPR laws permit NexTech to pay environmental handling fees in respect of the sales of sellers made on NexTech stores. Subsequently, these amounts will be recovered from their selling accounts. In such case, sellers understand and agree that we have the right to take fund restriction measures on the seller\'s account based on the real-time sales situation of the goods, either independently or by notifying our affiliates and relevant partners, and to deduct the corresponding environmental handling fees and/or other fees and amounts under this EPR Policy from the seller\'s account at fixed intervals (as specifically displayed on the system page).\n\n3.8. The seller acknowledges and agrees that in some cases, under applicable laws and regulations, NexTech itself is obligated to pay specific categories of environmental handling fees for goods sold by the seller (such as designated products in applicable jurisdictions) and in these cases the seller is responsible for these costs. We reserve the right, pursuant to the Seller Services Agreement, to deduct these fees directly from the seller\'s account or instruct our affiliated companies and partners to do so. In the event that the seller\'s account lacks sufficient funds, the seller agrees to promptly replenish the account as per our requirements.\n\n## 4. Taxes\n\n4.1. All amounts payable by the seller to us under this EPR Policy do not include any taxes or fees. \"Taxes and fees\" refer to any and all federal, state, regional, county, city, local, or foreign taxes of any nature levied, imposed, assessed, or collected by any tax authority (including but not limited to sales tax, use tax, license tax, excise tax, goods and services tax, value-added tax, stamp duty or transfer tax, levies, import taxes, assessments, duties, fees, charges, or withholding taxes), and all interest, penalties, fines, or other additional amounts imposed on such taxes. However, for further clarity, it does not include: (i) any of the aforementioned taxes based on gross income or net income, (ii) any of the aforementioned taxes that are franchise taxes, or (iii) any of the aforementioned taxes that are property taxes, movable property taxes, or rent taxes (collectively, \"Excluded Taxes\"). Each party shall bear any and all Excluded Taxes that it is responsible for under applicable law.\n\n4.2. Notwithstanding any other provisions in this EPR Policy, if the law requires any amount to be withheld, the seller shall notify us and pay any additional amount necessary to ensure that we receive a net amount (after any deductions or withholdings for taxes, levies, or any similar amounts) equal to the amount we would have received in the absence of any such deductions or withholdings. Additionally, the seller shall provide us with documentation showing that the withheld and deducted amounts have been paid to the relevant tax authority. We will provide the seller with reasonably requested tax return forms to reduce or avoid any deductions or withholdings of taxes, levies, or any similar amounts on payments under this EPR Policy. The seller agrees that if tax laws require the seller to register under applicable statutes, the seller shall promptly complete such registration and comply with such statutes and responsibilities. The seller agrees to promptly share the registration number or other unique identification number with us so that we can take relevant compliance measures. \"Tax authority\" refers to any governmental, national, city, or any local, state, or other fiscal, customs, excise, or tax authority, department, or official anywhere in the world responsible for and capable of imposing, collecting, auditing, assessing, managing, or levying any taxes or making any decision or judgment regarding any taxes.\n\n4.3. Any regulatory fees, fines, penalties, or charges assessed against us due to providing extended producer responsibility-related services to the seller under this EPR Policy shall be borne by the seller. We reserve the right to charge the seller for any related regulatory fees, fines, penalties, or charges. The seller shall indemnify us and hold us harmless against such regulatory fees, fines, penalties, or charges.\n\n## 5. Non-Compliance\n\n5.1. The seller understands and agrees that, if the seller fails to provide the required proof of compliance with EPR regulations on time and/or objects to NexTech providing the extended producer responsibility service where NexTech has the right or obligation to do so under law in the relevant jurisdiction, we have the right, on our own accord or by notifying our affiliates or relevant partners, to take one or more of the following measures:\n\n(1) Partial or complete removal, prohibition of sale, deletion, blocking, downgrade, removal from search results, prohibition from appearing in search results, or removal of advertisements for some or all products;\n\n(2) Prohibition from listing new products or placing products for sale in the store;\n\n(3) Closure or restriction of some or all functions and permissions of the seller\'s account (including withdrawal functions);\n\n(4) Restriction of withdrawal of some or all funds from the seller\'s account;\n\n(5) Deduction of some or all of the deposit/account reserve amount;\n\n(6) Termination of the agreement, cessation of cooperation, and removal of the seller from our platform;\n\n(7) Other measures as stipulated in the Seller Services Agreement and/or NexTech\'s Seller Rules.\n\n5.2. In the event that the seller\'s failure to fulfill their extended producer responsibility obligations and/or other related obligations results in any loss, damage, penalties, fines, etc., to us, our affiliates, and/or any third party, the seller shall fully compensate for such losses.', NULL, 1, 0, 'legal', '[\"seller_footer\"]', 24, '2026-09-24 00:41:51', '2026-09-24 00:41:51');
INSERT INTO `pages` (`id`, `slug`, `parent_slug`, `title`, `banner_image`, `content`, `sections`, `is_published`, `show_in_footer`, `footer_group`, `menu_placements`, `sort_order`, `created_at`, `updated_at`) VALUES
(41, 'global-data-protection-exhibit', 'seller-services-agreement', 'Global Data Protection Exhibit', NULL, '_Release date: September 24, 2026_\n\nThis Global Data Protection Exhibit (\"Data Protection Exhibit\") forms part of the NexTech Seller Services Agreement entered into between NexTech (\"NexTech\") and the Seller using the NexTech Platform (as defined in the Agreement) (\"Seller\") (each a \"Party\" and together the \"Parties\") (NexTech Seller Services Agreement together with the Data Protection Exhibit, collectively referred to as the \"Agreement\"), under which the Seller agrees to undertake various activities in connection with its sales and promotions on the NexTech Platform (the \"Services\").\n\nFor the purposes of this Data Protection Exhibit, and except where indicated otherwise, the term \"NexTech\" means NexTech and shall include its Affiliates, if and to the extent the other Party processes Personal Data in connection with this Agreement for which any such Affiliate qualifies as a data controller. All capitalised terms that are not expressly defined in this Data Protection Exhibit will have the meanings given to them in the Agreement.\n\nFor the purposes of performing the Agreement, Seller may have access to, or be provided with, Personal Data that is subject to Data Protection Laws and in relation to which either Party is subject to certain obligations. This Data Protection Exhibit assists the Parties in complying with their obligations when providing or allowing access to Personal Data.\n\nIn consideration of the mutual promises set out in this Data Protection Exhibit, the Parties agree as follows:\n\n## 1. Definitions\n\n1.1 For the purposes of this Data Protection Exhibit:\n\n- \"Affiliate\" means, in relation to an entity, another entity from time to time Controlling, Controlled by, or under common Control with that entity. For the purposes of this definition, \"Control\" means, with regard to an entity, the legal, beneficial or equitable ownership, directly or indirectly, of 50% or more of the capital stock (or other ownership interest, if not a corporation) of such entity ordinarily having voting rights, or the equivalent rights under contract, to control management decisions with regard to relevant subjects, and \"Controlled\" and \"Controlling\" will have corresponding meanings.\n- \"C-to-C Transfer Clauses\" means Sections I, II, III and IV (as applicable) in so far as they relate to Module One (Controller-to-Controller) within the Standard Contractual Clauses for the transfer of Personal Data to third countries pursuant to Regulation (EU) 2016/679 of the European Parliament and the Council approved by Commission Implementing Decision (EU) 2021/914 of 4 June 2021.\n- \"C-to-P Transfer Clauses\" means the Sections I, II, III and IV (as applicable) in so far as they relate to Module Two (Controller-to-Processor) within the Standard Contractual Clauses for the transfer of Personal Data to third countries pursuant to Regulation (EU) 2016/679 of the European Parliament and the Council approved by Commission Implementing Decision (EU) 2021/914 of 4 June 2021.\n- \"Data Protection Laws\" means all laws and regulations that apply to the processing of Personal Data under the Agreement as amended from time to time, including, but not limited to, the GDPR, the Data Protection Act 2018, any successor thereto, and any applicable laws and regulations of the United Kingdom (\"UK\"), United States and its states, Switzerland, Japan, Korea, European Union and its member states.\n- \"Data Subject Request\" means an actual or purported request, notice, or complaint from (or on behalf of) a data subject exercising his or her rights under Data Protection Laws.\n- \"Information Security Incident\" means the accidental or unlawful destruction, loss, alteration, unauthorized disclosure of, or access to Personal Data transmitted, stored or otherwise processed by Seller or its subprocessor. Information Security Incidents do not include unsuccessful attempts or activities that do not compromise the security of Personal Data, including unsuccessful log-in attempts, pings, port scans, denial of service attacks, or other network attacks on firewalls or networked systems.\n- \"Personal Data\" means any information relating to an identified or identifiable natural person or household. \"Personal Data\" shall include analogous terms under Data Protection Laws that is transferred from (or made available by) NexTech to Seller in connection with the Agreement where each Party acts as a data controller.\n- \"Regulator\" means any independent public authority, including any regulator or supervisory authority, established under the laws of any applicable jurisdiction responsible for the monitoring and application of Data Protection Laws.\n- \"Regulator Correspondence\" means any correspondence or communication received from a Regulator relating to Personal Data.\n- \"Third-Party Request\" means a written request from any third party for the disclosure of Personal Data, where compliance with such a request is required or purported to be required by applicable law or regulation.\n- \"special categories of data\", \"process/processing\", \"controller\", \"processor\", \"data subject\" and \"supervisory authority\" shall have the same meaning as in the GDPR, and shall include analogous terms under Data Protection Laws.\n- \"GDPR\" means Regulation (EU) 2016/679 of the European Parliament and the Council (General Data Protection Regulation) including as implemented or adopted under the laws of the United Kingdom.\n- \"subprocessor\" means any processor engaged by Seller or by any other subprocessor of the Seller, which receives Personal Data from the Seller in connection with the Agreement.\n\n1.2 In this Data Protection Exhibit:\n\n(a) a reference to a Clause, Schedule or Appendix is, unless stated otherwise, a reference to a Clause, Schedule or Appendix to this Data Protection Exhibit; and\n\n(b) unless the context otherwise requires, words in the singular shall include the plural and in the plural shall include the singular.\n\n## 2. Details of the processing activities & Processing Obligations\n\n2.1 NexTech may provide Personal Data to Seller for processing in connection with this Agreement. The subject matter of the data processing is to fulfil the Purposes and the processing will be carried out for the duration of the Agreement. Appendix 1 of the Data Protection Exhibit, as applicable, provides details of processing. Notwithstanding any contrary provision in this Data Protection Exhibit, NexTech shall be permitted to make amendments to the details of processing provided in Appendix 1 on written notice to Seller.\n\n2.2 In relation to the Personal Data, each Party acts as an independent controller of the Personal Data processed under this Agreement. The Parties agree that they do not act as joint controllers in this regard.\n\n2.3 Each Party shall comply with this Data Protection Exhibit and their respective obligations as independent controllers under Data Protection Laws when processing Personal Data in connection with the Agreement, and neither Party shall do or omit to do anything which places the other Party in breach of any Data Protection Laws. This Data Protection Exhibit is in addition to, and does not relieve, remove, or replace, a Party\'s obligations or rights under Data Protection Laws.\n\n2.4 Personal Data can only be processed by the Seller for the Purposes.\n\n2.5 Personal Data received pursuant to the Agreement shall be segregated from all other Personal Data processed by Seller.\n\n2.6 Seller shall not:\n\n(a) sell any Personal Data;\n\n(b) retain, use, share or disclose any Personal Data for any purpose other than for the Purposes;\n\n(c) use Personal Data for profiling, targeting, analytics, or data harvesting;\n\n(d) do anything to cause NexTech to be in breach of Data Protection Laws; or\n\n(e) combine Personal Data received pursuant to the Agreement with Personal Data (i) received from or on behalf of another person, or (ii) collected from Seller\'s own interaction with any data subject to whom such Personal Data pertains, except as and to the extent necessary as a part of Seller\'s performance of the Purposes.\n\n## 3. Transparency Obligations\n\nUnless agreed otherwise between NexTech and Seller, NexTech shall be responsible for:\n\n3.1 providing the relevant data subjects with any notice and information required by Data Protection Laws; and\n\n3.2 procuring all consents or rights from data subjects as are necessary in order for the Parties\' processing of Personal Data to comply with Data Protection Law.\n\n## 4. Data Security; Mutual Cooperation\n\n4.1 Seller shall implement and maintain reasonable technical, administrative, and physical safeguards to ensure a level of security appropriate to the risk associated with the processing activity as required by Data Protection Laws, including, at a minimum, the measures described in Appendix 2 (the \"Security Measures\"). Seller may update the Security Measures from time to time, so long as the updated measures do not decrease the overall protection of Personal Data.\n\n4.2 If Seller suffers or suspects an Information Security Incident in relation to Personal Data, Seller shall comply with its obligations under Data Protection Laws, including to report such an Information Security Incident to a Regulator or to data subjects. Seller shall notify NexTech promptly and without undue delay upon becoming aware of any Information Security Incident in relation to Personal Data.\n\n4.3 Each Party shall promptly (and without undue delay) notify the other Party in the event that it receives a Data Subject Request in relation to the processing of Personal Data under, or in connection with, the Agreement. The Party that receives such a Data Subject Request shall comply with its respective obligations under Data Protection Laws and shall be responsible for responding to such request, but each Party shall provide reasonable assistance to the other Party in complying with its respective obligations under Data Protection Laws.\n\n4.4 Each Party shall promptly (and without undue delay) notify the other relevant Party in the event that they receive any Regulator Correspondence or Third-Party Request in relation to the processing of Personal Data under, or in connection with, the Agreement. Unless otherwise agreed in writing by the Parties, the Party that receives any such Regulator Correspondence or Third-Party Request shall be responsible for responding to such request, but each Party shall provide reasonable assistance to the other Party in complying with their respective obligations under Data Protection Laws.\n\n## 5. Subprocessors\n\n5.1 Information about Seller\'s current subprocessors, including their functions and locations, is available in Appendix 4 to this Data Protection Exhibit.\n\n5.2 When Seller engages a subprocessor that it determines to be necessary for the processing of Personal Data for the Purposes, Seller shall ensure that it does so in a manner compliant with Data Protection Laws, including, in particular, GDPR Article 28 (where applicable).\n\n5.3 When Seller engages any new subprocessor, other than those listed at Appendix 4 to this Data Protection Exhibit, after the effective date of the Agreement, Seller will notify NexTech in writing of the proposed engagement (including the name and location of the relevant subprocessor and the activities it will perform). If NexTech objects to such engagement in a written notice to Seller within thirty (30) days after being informed of the engagement on reasonable grounds relating to the protection of Personal Data, NexTech and Seller will work together in good faith to find a mutually acceptable resolution to address such objection. If the Parties are unable to reach a mutually acceptable resolution within a reasonable timeframe, which shall not exceed thirty (30) days from the date on which NexTech raises its objection, NexTech may terminate this Agreement by providing written notice to Seller.\n\n5.4 Seller shall remain fully liable to NexTech for any subprocessors\' processing of Personal Data.\n\n## 6. Jurisdiction-Specific Provisions; Transfer Clauses\n\n6.1 The Parties will comply with the provisions of Appendix 3 to this Data Protection Exhibit, to the extent required by Data Protection Laws. In the event of any conflict between any applicable provisions of Appendix 3 and the Data Protection Exhibit, the applicable provisions in Appendix 3 will prevail. In the event that Data Protection Laws require additional or different terms to be executed between the Parties, NexTech may, by providing notice to Seller, amend Appendix 3 where such amendments are reasonably necessary to address the requirements of Data Protection Laws. Upon receipt of notice under this Section 6.1, Seller shall have thirty (30) days to submit to NexTech a written objection to the proposed amendment on reasonable grounds, otherwise the proposed amendment shall be deemed effective between the Parties.\n\n6.2 Subject to Section 6.1, in the event that the C-to-C Transfer Clauses in Appendix 3 are amended, replaced, or repealed by the European Commission, the United Kingdom, or under Data Protection Laws, the Parties shall work together in good faith to enter into an updated version of the C-to-C Transfer Clauses (to the extent required), or negotiate in good faith a solution to enable a transfer of Personal Data to be conducted in compliance with Data Protection Laws.\n\n6.3 The C-to-C Transfer Clauses will not apply to transfers of Personal Data where Seller has adopted an alternative recognized compliance mechanism for the lawful transfer of such Personal Data, such as the EU-U.S. Data Privacy Framework, the Swiss-U.S. Data Privacy Framework, or the UK Extension to the EU-U.S. Data Privacy Framework, as applicable and to the extent valid (\"Data Privacy Framework\"). Where Seller has a valid certification to the applicable Data Privacy Framework, the Parties agree that such transfer will be made in reliance on the Data Privacy Framework and that Seller will process Personal Data in compliance with the Data Privacy Framework principles.\n\n6.4 Seller warrants and undertakes that it shall not transfer, nor allow its subprocessors to transfer, Personal Data outside of the European Union, European Economic Area, the UK or Switzerland, unless it has specific authorization from NexTech to do so. For transfers of Personal Data under the Agreement by Seller or its subprocessors from the European Union, European Economic Area, the UK or Switzerland to countries that do not ensure an adequate level of data protection within the meaning of Data Protection Laws (which, for the avoidance of doubt, may include transfers from the European Economic Area to the UK), Seller acknowledges and agrees that Seller has implemented, and will implement, all transfer mechanisms required to comply with Data Protection Laws and shall ensure such compliance by its subprocessors, including entering into, or procuring that such subprocessors enter into, the C-to-P Transfer Clauses.\n\n6.5 Seller will provide NexTech reasonable support to enable NexTech\'s compliance with the requirements imposed on international transfers of Personal Data. Seller will, upon NexTech\'s request, provide information to NexTech that is reasonably necessary for NexTech to complete a transfer impact assessment (\"TIA\") to the extent required under Data Protection Laws.\n\n## 7. Allocation of costs\n\n7.1 Each Party shall perform its obligations under this Data Protection Exhibit at its own cost, unless otherwise specified.\n\n## 8. Governing Law\n\n8.1 The governing law of this Data Protection Exhibit shall be the law set forth in the Agreement, except that the governing law for the purposes of Clause 17 of the C-to-C Transfer Clauses shall be as set forth in Appendix 3.\n\n## 9. Termination\n\n9.1 NexTech is entitled to suspend and/or terminate the Agreement in so far as it relates to Personal Data by giving notice to the other Party if:\n\n(a) such other Party commits any material breach of this Agreement; and\n\n(b) NexTech gives notices to such other Party to remedy the breach (or to the extent that the breach is not capable of remedy, to give compensation for it) and the other Party fails to do so within twenty-eight days of the notice.\n\n## 10. Miscellaneous\n\n10.1 In the event of inconsistencies between the provisions of this Data Protection Exhibit and other agreements (including the Agreement) between the Parties, the provisions of this Data Protection Exhibit shall prevail with regard to the Parties\' obligations relating to Personal Data. In cases of doubt, this Data Protection Exhibit shall prevail, in particular, where it cannot be clearly established whether a clause relates to a Party\'s data protection obligations.\n\n10.2 The Parties acknowledge and agree that any NexTech Affiliate acting as a data controller may enforce any of NexTech\'s rights or the other Party\'s obligations under this Data Protection Exhibit to the extent such NexTech Affiliate reasonably deems necessary to comply with its obligations under Data Protection Laws.\n\n10.3 Should any provision or condition of this Data Protection Exhibit be held or declared invalid, unlawful or unenforceable by a competent authority or court, then the remainder of this Data Protection Exhibit shall remain valid. Such an invalidity, unlawfulness or unenforceability shall have no effect on the other provisions and conditions of this Data Protection Exhibit to the maximum extent permitted by law. The provision or condition affected shall be construed either:\n\n(a) to be amended in such a way that ensures its validity, lawfulness and enforceability while preserving the Parties\' intentions; or if that is not possible,\n\n(b) as if the invalid, unlawful or unenforceable part had never been contained in this Data Protection Exhibit.\n\n10.4 Except as stated in Section 6.1, any amendments to this Data Protection Exhibit shall be in writing duly signed by authorised representatives of the Parties hereto.\n\n10.5 Notwithstanding anything in the Agreement or any order form entered in connection therewith to the contrary, the Parties acknowledge and agree that Seller\'s access to Personal Data does not constitute part of the consideration exchanged by the Parties in respect of the Agreement.\n\n10.6 Notwithstanding anything to the contrary in the Agreement, any notices required or permitted to be given by Seller to NexTech under this Data Protection Exhibit may be given\n\n(a) in accordance with any notice clause of the Agreement;\n\n(b) to NexTech\'s primary points of contact with Seller; or\n\n(c) to any email provided by NexTech for the purpose of providing it with Agreement-related communications or alerts.\n\n10.7 In the event of changes to Data Protection Laws, Seller will take, and will ensure its subprocessors take, such measures as required under Data Protection Laws to continue facilitating the lawful processing of Personal Data for the Purposes pursuant to the Agreement, this Data Protection Exhibit, and Data Protection Laws.\n\n10.8 Notwithstanding anything to the contrary in the Agreement, Seller\'s liability arising from this Data Protection Exhibit shall not be subject to any exclusions or limitations on liability that may be provided for elsewhere in the Agreement.\n\n10.9 Seller will defend NexTech from and against any claims, demands, suits, causes of action, proceedings, investigations or inquiries (\"Claims\"), and indemnify and hold NexTech harmless from all losses, liabilities, damages, costs and expenses (including reasonable legal fees and fees related to any investigation or regulatory proceeding) (\"Losses\") to the extent that the Claims or Losses arise out of, are in connection with, or relate to: (i) any breach by Seller of this Data Protection Exhibit; and/or (ii) Seller\'s violation of any Data Protection Laws.\n\n## Appendix 1 — Details of Processing Activities\n\nThis Appendix 1 forms part of the Data Protection Exhibit and also serves as Annex I to the C-to-C Transfer Clauses, as applicable.\n\n**Categories of data subjects whose personal data is transferred:** NexTech customers\n\n**Categories of personal data transferred:** Name; Address; Contact details; Order information; Communication data; Product reviews; The return or refund reasons.\n\n**Sensitive data transferred (if applicable) and applied restrictions or safeguards** that fully take into consideration the nature of the data and the risks involved, such as for instance strict purpose limitation, access restrictions (including access only for staff having followed specialised training), keeping a record of access to the data, restrictions for onward transfers or additional security measures: N/A\n\n**The frequency of the transfer** (e.g. whether the data is transferred on a one-off or continuous basis): Continuous basis\n\n**Nature of the processing:** Receiving data, including accessing; Using data to perform the Services; Sharing data, including disclosure; Erasing data, including destruction and deletion.\n\n**Purpose(s) of the data transfer and further processing:** For the purpose of facilitating the delivery services, product customization services and online instant communication services undertaken by the Seller under the Agreement.\n\n**The duration of the processing and period for which the personal data will be retained**, or, if that is not possible, the criteria used to determine that period: For the duration of the Seller Agreement and operative time of this Data Protection Exhibit.\n\n**A. Competent supervisory authority (where required by Data Protection Laws):** The supervisory authority of the EU Member State where the data exporter is established or has appointed an EU representative. If there is no qualifying EU Member State, the Parties elect the supervisory authority of Ireland.\n\n## Appendix 2 — Technical and Organisational Measures Including Technical and Organisational Measures to Ensure the Security of the Data\n\nThis Appendix 2 forms part of the Data Protection Exhibit and also serves as Annex II to the C-to-C Transfer Clauses, to the extent applicable.\n\n1. Organisational management and dedicated staff responsible for the development, implementation and maintenance of Seller\'s information security program.\n2. Audit and risk assessment procedures for the purposes of periodic review and assessment of risks to Seller\'s organisation, monitoring and maintaining compliance with Seller\'s policies and procedures, and reporting the condition of its information security and compliance to internal senior management.\n3. Data security controls which include, at a minimum, logical segregation of data, restricted (e.g., role-based) access and monitoring, and utilisation of commercially available industry standard encryption technologies for Personal Data that is transmitted over public networks (i.e., the Internet) or when transmitted wirelessly or at rest or stored on portable or removable media (i.e., laptop computers, CD/DVD, USB drives, back-up tapes).\n4. Logical access controls designed to manage electronic access to data and system functionality based on authority levels and job functions, (e.g., granting access on a need-to-know and least privilege basis, use of unique IDs and passwords for all users, periodic review and revoking/changing access promptly when employment terminates or changes in job functions occur).\n5. Password controls designed to manage and control password strength, expiration and usage including prohibiting users from sharing passwords and requiring that Seller\'s passwords that are assigned to its employees: (i) be at least eight (8) characters in length, (ii) not be stored in readable format on Seller\'s computer systems; (iii) must have defined complexity; (iv) must have a history threshold to prevent reuse of recent passwords; and (v) newly issued passwords must be changed after first use.\n6. System audit or event logging and related monitoring procedures to proactively record user access and system activity.\n7. Physical and environmental security of data centers, server room facilities and other areas containing Personal Data designed to: (i) protect information assets from unauthorised physical access, (ii) manage, monitor and log movement of persons into and out of Seller\'s facilities, and (iii) guard against environmental hazards such as heat, fire and water damage.\n8. Operational procedures and controls to provide for configuration, monitoring and maintenance of technology and information systems, including secure disposal of systems and media to render all information or data contained therein as undecipherable or unrecoverable prior to final disposal or release from Seller\'s possession.\n9. Change management procedures and tracking mechanisms designed to test, approve and monitor all material changes to Seller\'s technology and information assets.\n10. Incident management procedures are designed to allow Seller to investigate, respond to, mitigate and notify of events related to the Seller\'s technology and information assets.\n11. Network security controls that provide for the use of enterprise firewalls and layered DMZ architectures, and intrusion detection systems and other traffic and event correlation procedures designed to protect systems from intrusion and limit the scope of any successful attack.\n12. Vulnerability assessment, patch management and threat protection technologies, and scheduled monitoring procedures designed to identify, assess, mitigate and protect against identified security threats, viruses and other malicious code.\n13. Business resiliency/continuity and disaster recovery procedures designed to maintain service and/or recovery from foreseeable emergencies or disasters.\n\nFor transfers to (sub-) processors, also describe the specific technical and organisational measures to be taken by the (sub-) processor to be able to provide assistance to the controller and, for transfers from a processor to a sub-processor, to the data exporter.\n\n## Appendix 3 — Jurisdiction-Specific Provisions\n\nThe terms below shall have the following meanings ascribed to them for the purposes of this Appendix 3:\n\n- \"Data Exporter\" means the Party transferring Personal Data outside of a country or, where there is no such transfer, the data controller; and\n- \"Data Importer\" means the Party receiving Personal Data subject to direct or onward transfer or, where there is no such transfer, the data processor.\n\n### I. European Economic Area\n\nA. The terms below shall have the following meanings ascribed to them for the purposes of this Section I:\n\n(a) \"Europe\" means the European Economic Area;\n\n(b) \"European Data Protection Laws\" means any applicable laws of Europe that relate to the processing of Personal Data under this Agreement.\n\n(c) \"GDPR\" means Regulation (EU) 2016/679 of the European Parliament and the Council of 27 April 2016.\n\nB. To the extent that any Data Exporter, acting as data controller, transfers Personal Data subject to European Data Protection Laws, either directly or via onward transfer, to a Data Importer, acting as a data controller, located in a country that does not ensure an adequate level of protection within the meaning of European Data Protection Laws, the Parties agree to comply with the terms of the C-to-C Transfer Clauses, which are hereby incorporated into this Data Protection Exhibit by reference.\n\nC. For the purposes of the C-to-C Transfer Clauses, the following additional provisions shall apply:\n\n(a) the names and addresses of those Data Exporter(s) and Data Importer(s) shall be considered to be incorporated into the C-to-C Transfer Clauses;\n\n(b) The Parties\' execution of this Data Protection Exhibit shall be considered as signature to the C-to-C Transfer Clauses.\n\n(c) Clause 7 (Docking Clause) shall apply.\n\n(d) The option under Clause 11 (Redress) shall not apply.\n\n(e) For the purposes of paragraph (a) of Clause 13 (Supervision), the Data Exporter shall be considered as established in an EU Member State.\n\n(f) The governing law for the purposes of Clause 17 (Governing law) shall be the law of Ireland.\n\n(g) The courts under Clause 18 (Choice of forum and jurisdiction) shall be the courts of Ireland.\n\n(h) The contents of Appendix 1 shall form Annex I to the C-to-C Transfer Clauses.\n\n(i) The Irish Data Protection Commission shall act as competent supervisory authority for the purposes of Annex I.C of the C-to-C Transfer Clauses (Competent Supervisory Authority).\n\n(j) The contents of Appendix 2 shall form Annex II of the C-to-C Transfer Clauses (Technical and organisational measures including technical and organisational measures to ensure the security of the data).\n\nD. The Parties shall each provide such information to data subjects as is required by GDPR Articles 13 and 14, as relevant, in respect of the Purposes.\n\nE. Seller shall ensure that it complies with GDPR Article 5(1)(e) and, in particular, shall keep Personal Data in a form that permits identification of data subjects for no longer than is necessary for the Purposes.\n\n### II. Japan\n\nA. The following provisions apply to all processing and transfers of Personal Data subject to Data Protection Laws of Japan.\n\nB. For the avoidance of doubt, \"Data Protection Laws\" includes the Act on the Protection of Personal Information (Act No. 57 of 2003, as amended) (\"APPI\").\n\nC. Data Importer shall not process Personal Data for purposes other than those specified in Appendix 1, or as otherwise agreed by the Data Exporter and Data Importer (for the purpose of this section, the \"Utilization Purposes\") without the prior written consent of the Data Exporter. Data Exporter represents that it has notified all applicable data subjects of the Utilization Purposes to the extent required by Data Protection Laws.\n\nD. Data Importer and Data Exporter agree to the collection of consents from data subjects required by Data Protection Laws as set forth in Section 3 of the Data Protection Exhibit, including without limitation for (1) the collection of any \"Special Care-Required Personal Information\" (as defined by Data Protection Laws) and (2) any disclosures of Personal Data made by Data Exporter to third parties, subject to Clause G below.\n\nE. Data Importer shall keep the Personal Data accurate and up-to-date within the scope necessary to achieve the Utilization Purposes, and shall delete any Personal Data that becomes unnecessary to achieve a Utilization Purpose or other legitimate business purpose. For the avoidance of doubt, it is not necessary to delete Personal Data where applicable laws require the Data Importer to retain it.\n\nF. Data Importer shall have in place appropriate technical and organizational measures to protect the Personal Data against accidental or unlawful destruction or accidental loss, leakage, alteration, and unauthorized disclosure or access, and which provide a level of security appropriate to the risk represented by the processing and the nature of the data to be protected.\n\nG. Data Importer shall exercise the necessary and appropriate control and supervision over its officers, employees, and subprocessors to securely manage the Personal Data received.\n\nH. Data Importer shall not disclose Personal Data to any third party except: (i) where such disclosure, transfer or access is mandated by applicable law; or (ii) where Data Exporter consents to the disclosure of Personal Data to the third party; or (iii) as permitted in Clause H, below. In the event that Data Importer discloses Personal Data to a third party, Data Importer shall impose contractual obligations upon the third party that are no less restrictive than the terms set forth in this Data Protection Exhibit.\n\nI. In the case where Data Importer entrusts the handling of the Personal Data to a third party pursuant to Clause H above, they shall exercise necessary and appropriate control and supervision over the entrustees to ensure the safety of such Personal Data, as stated in Clause G above, and they shall require the entrustees comply with obligations equivalent to the obligations of the Data Importers under this Data Protection Exhibit, including the obligations in this section. The Data Importers shall be responsible for any breach by the entrustees (and any subsequent entrustee) of the obligations above. For clarity, Clause H shall apply to all third-party entrustees and subsequent third-party entrustees.\n\nJ. To the extent required by the APPI, upon request of the data subject, each Data Importer shall correct, add, or delete certain Personal Data if the data subject can show the contents of the Personal Data are incorrect. Each Data Importer shall promptly inform the data subject if it has corrected, added, or deleted Personal Data, or if it has determined it does not have to do so.\n\nK. To the extent required by the APPI, upon request of the data subject, each Data Importer shall disclose the information on the Personal Data stipulated under the APPI, including (i) the contents of the retained Personal Data; (ii) the name of the Data Importer; (iii) the Utilization Purposes; (iv) the procedures for responding to a request for the Personal Data; and (v) the contact information data subjects should use to make claims regarding the handling of the Personal Data. Each Data Importer shall promptly inform the data subject if it has determined it does not have to provide requested information on the contents and/or the Utilization Purposes of the Personal Data.\n\nL. To the extent required by the APPI, each Data Importer shall delete or stop utilizing the Personal Data if the data subject can show that the Data Importer is using or has used such Personal Data outside of the designated Utilization Purposes or if was acquired by improper means; provided, however, that it is not required where it would be unreasonably expensive or unreasonably difficult to do so and where alternative action which would protect the data subject\'s interests can be taken. Each Data Importer shall promptly inform the data subject if it has deleted or stopped utilizing the Personal Data, or if it has determined it does not have to do so.\n\nM. To the extent required by the APPI, each Data Importer shall stop providing Personal Data to a third party, if the Data Importer has provided it to a third party in violation of the restrictions related to the provisions of the Personal Data to a third party under the APPI; provided, however, that it is not required where it would be unreasonably expensive or unreasonably difficult to do so and where alternative action which would protect the data subject\'s interests can be taken. Each Data Importer shall promptly inform the data subject if it has stopped providing the Personal Data, or if it has determined it does not have to do so.\n\nN. If a Data Importer knows or should know that any Personal Data has been or is likely to be leaked, disclosed, accessed, destroyed, altered, lost, used without authorization, or otherwise handled in any way not permitted under this Data Protection Exhibit, regardless of whether or not the Data Importer is liable for such incidents, the Data Importer shall immediately inform the Data Exporter of the same in writing, and shall take any appropriate measures to prevent such incident from occurring, expanding, and recurring.\n\n### III. Korea\n\nA. The following provisions apply to all processing and transfers of Personal Data subject to applicable laws in Korea. When processing Personal Data provided by or on behalf of Data Exporter:\n\n(a) The scope, classification, purposes and details of the processing of the Personal Data shall be as described in Appendix 1, or as otherwise agreed by the Data Exporter and Data Importer.\n\n(b) Data Importer shall limit access to Personal Data to those personnel who reasonably require such access for the purposes of the processing, and Data Importer shall establish and maintain safeguards as per Appendix 2 of the Data Protection Exhibit, including without limitation any safeguards necessary to comply with rules and regulations of Korean Data Protection Law from time to time (as applicable to an overseas transferee of Personal Data).\n\n(c) Notwithstanding anything in this Data Protection Exhibit to the contrary, Data Importer shall not disclose or transfer to any person or entity any Personal Data unless it obtains prior consent to transfer from relevant data subjects or otherwise does so in accordance with applicable provisions of Korean Data Protection Law.\n\n(d) Data Importer shall establish and implement appropriate procedures for (i) the handling of complaints regarding invasions of privacy and (ii) the resolution of any disputes with data subjects.\n\n(e) Data Importer shall be subject to (i) appropriate training and supervision with respect to its handling of Personal Data, and (ii) supervision and audit by relevant supervisory authorities.\n\n### IV. Switzerland\n\nA. For the purposes of this Section IV, the term \"Swiss Data Protection Laws\" means Switzerland\'s Federal Act on Data Protection of June 19, 1992, the Ordinance to the Federal Act on Data Protection, and the Ordinance on Data Protection Certification, and all Swiss laws relating to the processing, privacy, protection, or use of Personal Data.\n\nB. To the extent any Data Exporter transfers Personal Data subject to Swiss Data Protection Laws, either directly or via onward transfer, to a Data Importer located in a country that does not ensure an adequate level of protection within the meaning of Swiss Data Protection Laws, the Parties agree to the C-to-C Transfer Clauses in accordance with Section I of this Appendix 3 as supplemented by Clause C of this Section IV.\n\nC. The following additional provisions shall apply so that the C-to-C Transfer Clauses are suitable for providing an adequate level of protection for such transfer under Swiss Data Protection Laws:\n\n(a) \"FDPIC\" means the Swiss Federal Data Protection and Information Commissioner.\n\n(b) \"Revised FADP\" means the revised version of the Federal Act of Data Protection (\"FADP\") of 25 September 2020, which is scheduled to come into force on 1 January 2023.\n\n(c) The term \"EU Member State\" must not be interpreted in such a way as to exclude data subjects in Switzerland from the possibility for suing their rights in their place of habitual residence (Switzerland) in accordance with Clause 18(c).\n\n(d) The C-to-C Transfer Clauses also protect the data of legal entities until the entry into force of the Revised FADP.\n\n(e) The FDPIC shall act as the \"competent supervisory authority\" insofar as the relevant data transfer is governed by the FADP.\n\n### V. United Kingdom\n\nA. The terms below shall have the following meanings ascribed to them for the purposes of this Section:\n\n(a) \"UK\" means the United Kingdom.\n\n(b) \"UK Data Protection Laws\" means the UK GDPR, Data Protection Act of 2018, and all UK laws relating to the processing, privacy, protection, or use of Personal Data.\n\n(c) \"UK GDPR\" means the United Kingdom General Data Protection Regulation, as it forms part of the law of England and Wales, Scotland and Northern Ireland by virtue of section 3 of the European Union (Withdrawal) Act 2018.\n\nB. To the extent any Data Exporter transfers Personal Data subject to UK Data Protection Laws, either directly or via onward transfer, to a Data Importer located in a country that does not ensure an adequate level of protection within the meaning of UK Data Protection Laws, the Parties agree to the C-to-C Transfer Clauses in accordance with Section I of this Appendix 3 as supplemented by Clause C of this Section V.\n\nC. The following additional provisions shall apply so that the C-to-C Transfer Clauses are suitable for providing an adequate level of protection for such transfer under UK Data Protection Laws:\n\n(a) Part 2: Mandatory Clauses of the Approved Addendum, being the template Addendum B.1.0 issued by the ICO and laid before Parliament in accordance with s119A of the Data Protection Act 2018 on 28 January 2022, as it is revised under Section 18 of those Mandatory Clauses (\"Approved Addendum\") shall apply.\n\n(b) The information required by Part 1 of the Approved Addendum is set out in Appendix 1 of this Data Protection Exhibit.\n\n(c) With respect to Section 19 of the Approved Addendum, in the event the Approved Addendum changes, neither Party may end the Approved Addendum except as provided for in the Approved Addendum or the Agreement.\n\n### VI. United States\n\nA. The following provisions apply to the provision of Personal Data that is subject to Data Protection Laws of the United States (which includes the laws of any state of the United States) (\"US Personal Information\").\n\nB. To the extent NexTech discloses Deidentified data (as that term is defined under Data Protection Laws) originally derived from US Personal Information to Seller, or to the extent Recipient creates Deidentified data from US Personal Information received from or on behalf of NexTech, Seller shall:\n\n(a) adopt reasonable measures to prevent such Deidentified data from being used to infer information about, or otherwise being associated with, a particular natural person or, where required by Data Protection Laws, a household;\n\n(b) publicly commit to maintain and use such Deidentified data in a deidentified form and to not attempt to re-identify the Deidentified data, except that Seller may attempt to re-identify the data solely for the purpose of determining whether its deidentification processes satisfy the requirements of Data Protection Laws, as applicable; and\n\n(c) contractually obligate any recipients of the Deidentified data, including subprocessors, contractors, and other third parties, to comply with the provisions of this Section.\n\n## Appendix 4 — List of processors\n\nSellers hereby warrant that they comply with section 5 of this Data Protection Exhibit in respect of selecting and appointing processors. Sellers shall inform NexTech at least 30 days before they formally engage any processors. NexTech then shall have a 30-day right to object to any such engagement upon the receipt of such emails sent by sellers.', NULL, 1, 0, 'legal', '[\"seller_footer\"]', 25, '2026-09-24 00:43:32', '2026-09-24 00:43:32');
INSERT INTO `pages` (`id`, `slug`, `parent_slug`, `title`, `banner_image`, `content`, `sections`, `is_published`, `show_in_footer`, `footer_group`, `menu_placements`, `sort_order`, `created_at`, `updated_at`) VALUES
(42, 'seller-advertising-services-terms', 'seller-services-agreement', 'NexTech Seller Advertising Services Terms', NULL, '_Release date: September 24, 2026_\n\n## 1. General\n\n1.1 In order to provide high-quality advertising services to you, and facilitate you to promote products via NexTech Platform, we adopt this NexTech Seller Advertising Services Terms (these \"Terms\"). These Terms shall apply to all sellers using the online advertising services provided by NexTech via the NexTech Advertising Platform. You must carefully review the terms of these Terms and strictly adhere to them during the period of accepting the advertising services provided by NexTech via NexTech Advertising Platform.\n\n1.2 These Terms describe the terms pursuant to which NexTech offers you access to our advertising services, which help you promote your products on NexTech Platform (collectively, the \"Advertising Services\"). These Terms constitute part of our NexTech Seller Services Agreement. The Advertising Services are part of the \"Services\" as defined in the NexTech Seller Services Agreement. By registering for or using the Advertising Services, you (on behalf of yourself or the business you represent) agree to be bound by these Terms. In the event of conflict between the NexTech Seller Services Agreement and these Terms, these Terms will prevail.\n\n1.3 Capitalized terms used but not defined in these Terms shall have the same meaning as those in the NexTech Seller Services Agreement.\n\n## 2. Advertising Services\n\n2.1 The Advertising Services provided by NexTech pursuant to these Terms shall include the following: (i) preferential ranking and listing of products; (ii) a variety of online tools and services that would directly or indirectly benefit or promote your business, your products, your brand, your store, etc.; and (iii) any such other services as may be announced by NexTech from time to time. You hereby acknowledge and agree that NexTech shall be entitled to determine, at its reasonable discretion, whether the Advertising Services or any part thereof will be available to you, and NexTech shall be entitled to add or modify the content of the Advertising Services, and suspend the provision of the Advertising Services due to business development needs and at NexTech\'s reasonable discretion, and you will be notified of such corresponding changes or suspension.\n\n2.2 You hereby agree that to use the Advertising Services, you may be required to set certain targets or goals (the \"Advertising Target\", such as your target Return on Ad Spend, or ROAS) during a defined time period (the \"Advertising Period\"), so that we may make available certain features or services through which we may help you optimize your performance with respect to the Advertising Target. For the avoidance of doubt, regarding the Advertising Target and Advertising Period, you may check the specific rules in NexTech Advertising Platform, including setting daily, weekly, or monthly spending limits (please review current details related to these settings on the NexTech Advertising Platform). If you elect to use those features or services (by informing us in writing or as reflected in your settings via the Advertising Services), you understand that we may manage your campaigns and take such actions on your behalf without your prior consent, as may be further described by us in writing or in the Advertising Services, with the goal of achieving your indicated Advertising Target calculated as an average over the course of the Advertising Period.\n\n2.3 For the purpose of the Advertising Services, when you use the Advertising Services, you shall authorize NexTech to delegate some or all of the Advertising Services to its Affiliates and/or its business partners (if applicable) to perform. You shall remain responsible for the Advertising Service Fees (defined below) you incur for your own use of the Advertising Services, provided that such Advertising Service Fees will be limited by your Advertising Target.\n\n2.4 You hereby acknowledge and agree that, NexTech will provide Advertising Services based on the Advertising Target you set. However, notwithstanding the aforementioned, NexTech makes no representations regarding revenue; adjacency or competitive separation of your campaigns; the reach, frequency, cadence, or the performance of your campaigns; any anticipated benefits related to your use of the Advertising Services; that the Advertising Services are suitable for your intended purposes; or that all features or functionality will be available to all users of the Advertising Services at the same time; or fraudulent or invalid activity and any impact it may have on your campaigns.\n\n## 3. License\n\n3.1 For the purpose of the Advertising Services, you hereby agree and grant to NexTech, its Affiliates and business partners (if applicable) providing Advertising Services a worldwide, non-exclusive, royalty-free, fully-paid, and sublicensable right and license to publish, modify, distribute or otherwise use Your Materials for the Advertising Services; provided, however, as part of the Advertising Services, we will not alter any of Your Materials (except to re-size to the extent necessary for presentation, so long as the relative proportions of Your Materials remain the same). We will comply with your removal requests as to specific uses of Your Materials for the purpose of providing Advertising Services via the NexTech Advertising Platform to facilitate your offer and sale of products on the NexTech Platform. You hereby ensure that any of Your Materials you provide to us shall be complete, accurate and up-to-date, and will comply with the Applicable Laws and the applicable NexTech Seller Rules as published now or at any time in the future, as well as all applicable third-party rights. You shall be solely responsible for any of Your Materials provided by you or on your behalf to us. You agree that NexTech does not retain or exercise any editorial right over the contents of Your Materials, campaigns, or content generated as part of the Advertising Services.\n\n## 4. Advertising Reserve\n\n4.1 For the purpose of using the Advertising Services, you agree to maintain a certain balance as a deposit (the \"Advertising Reserve\") in Your Account (as defined in the NexTech Seller Services Agreement) to secure the performance of your obligations under these Terms, including but not limited to setting off the payable Advertising Service Fees, and to mitigate the risks of claims, disputes, violations of our policies, or other risks to us, buyers or third parties. We may adjust the amount of the Advertising Reserve based on your behavior and risks to us and/or third parties by providing you with no less than five (5) business days\' written notice; if you do not wish to comply with an increase in your Advertising Reserve, your sole option is to terminate your use of the Advertising Services. You shall not be entitled to receive interest or any other proceeds with respect to the Advertising Reserve. If the balance in Your Account is insufficient to cover the Advertising Reserve due to increased requirements or deductions, you shall pay or cause the payment of the difference into Your Account within the period specified by us, or we shall be entitled to suspend or terminate the Advertising Services.\n\n## 5. Advertising Service Fees\n\n5.1 You agree to pay the applicable fees for your use of the Advertising Services (the \"Advertising Service Fees\", as further described in Section 5.3 below). Advertising Services may contain certain default settings, pricing and targeting methodologies, and other advanced features, which may be updated from time to time. You agree to review that information and stay informed about the Advertising Services you use, including related product details also available via the Advertising Services, to ensure that your participation and settings remain consistent with your objectives. We may charge you for your use of any feature or tool of the Advertising Services at any time upon reasonable notice to you. We reserve the right to modify the methodologies and algorithms we use to calculate the Advertising Service Fees from time to time. You shall set an estimated Advertising Service Fees you would like to spend on the Advertising Services each day (the \"Daily Budget\"), and in the event that the actual Advertising Service Fees for that day is significantly higher than the Daily Budget (for the avoidance of doubt, the acceptable fluctuation range shall be displayed on the NexTech Advertising Platform), the Advertising Services for that day will be automatically suspended. Any disputes about the Advertising Service Fees (which we will evaluate reasonably) must be submitted to us in writing within sixty (60) days of the date you incurred such charge, otherwise you waive the right to contest the dispute and such charge will be final.\n\n5.2 Without any prejudice to our rights under Section 3 of the NexTech Seller Services Agreement, you hereby agree that regarding the Advertising Service Fees, we shall be entitled to deduct the corresponding amount from the balance of Your Account (including but not limited to the Advertising Reserve and the sales proceeds in Your Account), and you may check the details about the deduction on the NexTech Advertising Platform. If the balance in Your Account is insufficient to cover the Advertising Service Fees and other expenses or costs arising from the Advertising Services, we shall be entitled to (i) offset it against any payment we make to you or amount we may owe you; (ii) charge Your Card or any other payment instrument you provide to us; (iii) invoice you, in which case you will pay the invoiced amount upon receipt; (iv) collect it from you by any other lawful means; and/or (v) suspend or terminate the provision of the Advertising Services to you.\n\n5.3 You hereby acknowledge and agree that unless otherwise updated by NexTech, the Advertising Service Fees shall be calculated on applicable billing metrics (e.g., impressions). You hereby agree to pay us all applicable fees and charges we calculate for your use of the Advertising Services, the detail of which will be displayed on the NexTech Advertising Platform. You further agree that as the Advertising Services we provide might vary from time to time, we may propose a different calculation basis from time to time on the NexTech Advertising Platform.\n\n5.4 Advertising Service Fees are exclusive of applicable taxes for use of the Advertising Services, except as may be otherwise indicated via the Advertising Services. You shall be responsible for paying applicable taxes associated with using the Advertising Services, in accordance with Applicable Laws and as further described in the NexTech Seller Services Agreement. Collection of Advertising Service Fees and applicable taxes may be carried out via the means specified in the NexTech Seller Services Agreement, or as otherwise agreed in writing, including as set forth in your applicable payment agreement with us (as applicable). You will reimburse us for all reasonable expenses and attorneys\' fees incurred in connection with our collection of amounts payable and past due. NexTech reserves the right to offer credits and/or discounts.\n\n5.5 You expressly acknowledge and agree that during the period you use the Advertising Services, (i) we are authorized to charge you on a recurring basis for the Advertising Service Fees (in addition to any applicable taxes and other charges) and (ii) the Advertising Services will continue until you cancel or we suspend or stop providing access in accordance with these Terms. Unless the applicable Advertising Services otherwise provide, there are no refunds or credits for partially used Advertising Services periods.\n\n## 6. Withdrawal from Advertising Services\n\n6.1 You may voluntarily apply to withdraw from the Advertising Services at any time with prior written notice to us. For the details of how to withdraw, please refer to the instructions we display on the NexTech Advertising Platform, which may be updated from time to time in our sole discretion. You hereby acknowledge and agree that, after a withdrawal, suspension, or termination of the Advertising Services takes effect, all promotion of products through the NexTech Advertising Platform will cease. Notwithstanding a termination or suspension of Advertising Services by you, buyers may still place orders based on the Advertising Services already provided to you, and such orders will be considered successful and generated by the Advertising Services. You remain obligated to pay for any related Advertising Service Fees in accordance with Section 5 hereunder.\n\n## 7. Confidentiality\n\n7.1 Without any prejudice to our rights under Section 18 of the NexTech Seller Services Agreement, for the purpose of using the Advertising Services, you hereby agree to protect and keep Confidential Information obtained from us in connection with the Advertising Services that is identified as confidential or that, given the nature of such information or the manner of its disclosure, reasonably should be considered confidential (including non-public information about our technology, marketplace, inventory availability, targeting and pricing data). You will use such information only in connection with your participation in the Advertising Services.\n\n## 8. Representations and Warranties\n\n8.1 Without any prejudice to our rights under Section 14 of the NexTech Seller Services Agreement, for the purpose of using the Advertising Services, you represent and warrant to NexTech that any of Your Materials, and any goods and services you supply via NexTech Platform: (i) complies with all Applicable Laws, rules and regulations, industry codes and guidance; (ii) complies with all NexTech Seller Rules as published now or at any time in the future, including its Prohibited Products List and Seller Code of Conduct; (iii) does not and will not infringe the rights of any third party, including, but not limited to, any intellectual property rights, publicity rights or rights of privacy; (iv) is truthful, up-to-date and accurate; (v) will not be misleading, deceptive, involve any misrepresentation, or imply or represent that any party has approval or sponsorship of another party that it does not have; and (vi) will not contain any information or content that is illegal, contrary to any industry code, indecent, obscene, threatening, harassing, discriminatory, defamatory or in breach of confidentiality.\n\n8.2 You further represent and warrant to NexTech that: (i) you are fully authorized to publish and authorize us to use Your Materials for the Advertising Services; (ii) any offer promoted via the Advertising Services is valid and redeemable, and is not false or misleading; (iii) you have adequate inventory to support any offer promoted via the Advertising Services; (iv) you have obtained all necessary rights, consents, licenses or clearances in relation to the publication via the Advertising Services and have complied with all guidance of relevant regulatory bodies; (v) you have all required rights and licenses to grant us the license rights you are granting us under these Terms; (vi) you will not, nor will you permit or encourage any third party, to use any means to generate fraudulent or invalid clicks, impressions, queries or other interactions; (vii) you will not deliver malware to the Advertising Services or to consumers or devices through the Advertising Services; and (viii) you will not copy, modify, damage, reverse engineer, decompile, disassemble, reconstruct, create derivative works of, or interfere with the proper working of the Advertising Services.\n\n## 9. Disclaimer of Warranties\n\n9.1 To the fullest extent permitted by Applicable Laws, NexTech disclaims all guarantees regarding positioning, levels, quality or timing of: (i) sales of your products; (ii) click-through rates; (iii) availability, quantity or delivery of advertising impressions; (iv) any user actions related to your products promoted via the Advertising Services or listings; (v) conversion rates; (vi) accuracy or availability of data related to the Advertising Services; (vii) the targeting, reporting, adjacency, ranking or placement of ads; (viii) the duration of your campaigns or display of ads; and (ix) any recommendations or guidance we may make available in connection with the Advertising Services.\n\n9.2 To the extent permitted by Applicable Laws, we are not liable, and you agree not to hold NexTech responsible, for any damages or losses (including, but not limited to, loss of money, goodwill or reputation, profits, or other intangible losses or any special, indirect or consequential damages) resulting directly or indirectly from your use of the Advertising Services, including but not limited to: (i) the deletion or modification of the Advertising Services, (ii) the duration or manner in which your ads appear on the NexTech Platform, or (iii) NexTech\'s decision to end or remove your ads.\n\n## 10. Indemnity\n\n10.1 Without prejudice to our rights under Section 15 of the NexTech Seller Services Agreement, for the purpose of using the Advertising Services, you will indemnify and hold us (and our Affiliates, and our business partners providing Advertising Services, and our and their respective officers, directors, employees, agents, and/or assigns) harmless from any claim or demand, including reasonable legal fees, made by any third party arising out of or related to the provision of Advertising Services to you, your breach of these Terms, your use of the Advertising Services or your breach of any law or the rights of a third party.\n\n## 11. Miscellaneous\n\n11.1 These Terms, together with the NexTech Seller Services Agreement and other NexTech Seller Rules, shall constitute the sole and entire agreement between the Parties with respect to the Services (including but not limited to the Advertising Services hereunder) and related subject matters. These Terms shall be interpreted together with the NexTech Seller Services Agreement and other NexTech Seller Rules. The content not mentioned or provided under these Terms shall be subject to the NexTech Seller Services Agreement and other NexTech Seller Rules.', NULL, 1, 0, 'legal', '[\"seller_footer\"]', 26, '2026-09-24 00:44:46', '2026-09-24 00:44:46'),
(43, 'seller-cookies-policy', 'seller-privacy-policy', 'Seller Cookies and Similar Technologies Policy', NULL, '_Last Updated: September 24, 2026_\n\nThis Cookies and Similar Technologies Policy (the \"Cookies Policy\") supplements the NexTech Seller Privacy Policy (the \"Privacy Policy\") and describes how NexTech (\"we\", \"us\" or \"our\") uses cookies and similar technologies, and handles personal information associated with Sellers that we collect via cookies and similar technologies through our digital properties, including our Seller Center websites and related services (collectively, the \"Service\"), and other activities as described in this Cookies Policy. Capitalised terms used in this Cookies Policy but not defined shall have the meaning set forth in the Privacy Policy.\n\n## Introduction to Cookies and Similar Technologies\n\nOur Service uses cookies and other similar technologies and tools, such as local storage technologies, pixels, application programming interfaces (\"APIs\"), and software development kits (\"SDKs\") (collectively referred to as \"cookies\" in this Cookies Policy).\n\nCookies are usually small text files stored on your devices that can store information. Cookies serve a number of important functions, including to remember you and your previous interactions with our Service. Cookies used on our sites include \"session cookies\" that are deleted at the end of a session, \"persistent cookies\" that are retained for longer periods of time but usually automatically expire after a set time, \"first-party\" cookies that we set and use directly, and \"third-party\" cookies that are set by our service providers.\n\nLocal storage technologies are used to store information locally on your device. They are similar to cookies but can store more information and may be stored in different locations on your device. Local storage is typically used to enhance Service functionality and remember user preferences. For example, HTML5 local storage is an equivalent service to cookies but can store large amounts of data related to a particular application on devices outside of your browser.\n\nA pixel is a piece of software code that allows an object, usually a pixel-sized image, to be embedded on a website. Pixels are used to demonstrate that a webpage or email has been accessed or opened, or that certain content has been viewed or clicked.\n\nAn API is a set of protocols and tools that enable two or more software applications to communicate with each other. APIs are used to facilitate communication between us and our service providers, and between us and our advertising partners (in countries where we enable advertising functionality). APIs that can store or access information on your device are considered to be cookies.\n\nSDK commonly refers to one or more code libraries which can distribute services to enable key functionality in websites and apps. SDKs embedded in our Service that can collect data about your device, access or store data on your device are considered to be cookies.\n\nPlease see below for more information about the cookies and similar technologies that we use. The specific combination of technologies active at any time may vary based on your settings and how you use our Service.\n\n## Purposes of Using Cookies\n\n**Security and Authentication.** We use cookies to determine and implement appropriate risk control and security strategies.\n\n**Remembering Your Preferences.** To provide you with a better user experience, we need to remember the settings you choose on NexTech so that they work the way you want them to. This includes remembering your choices and preferences when browsing the website.\n\n**Service Functionalities and Performance Optimization.** We use cookies to support various functionalities, such as login processes, page performance, bank account verification, and basic business functions. We also use cookies to ensure that services relevant to your country/region and language are displayed and to enable us to understand where and in what language our Service is being used, so that we can effectively provide our Service.\n\n**Advertising.** In countries where we enable advertising functionality, we may share information about our Sellers via third-party cookies with third-party advertising partners. These third-party cookies allow us and third-party advertising partners to optimize the effectiveness of the advertisements to promote the Service shown on other platforms and websites.\n\n## Your Choices\n\n**Browser settings.** You can use your browser to enable, disable or delete cookies. To do this, follow the instructions in your browser settings (typically found under \"Help\", \"Tools\" or \"Edit\" settings). Please note that if you set your browser to disable cookies, you may not be able to access secure areas of our Service and some parts of our Service may not function properly. For more information about cookies, including how to see what cookies have been set on your browser and how to manage and delete them, visit [allaboutcookies.org](https://www.allaboutcookies.org). You can also configure your device to prevent images from loading to prevent pixels from functioning.\n\n**Opt out of using your personal information for advertising.** To opt-out of the use of your personal information for advertising, use the \"Cookie preferences\" link in the footer of the NexTech website in countries where we enable this functionality in accordance with applicable laws.\n\n**Do Not Track.** Some Internet browsers may be configured to send \"Do Not Track\" signals to the online services that you visit. We currently do not respond to \"Do Not Track\" or similar signals.\n\nFor any other choices or rights you may have, please refer to the Privacy Policy.\n\n## Changes to the Cookies Policy\n\nWe reserve the right to modify this Cookies Policy at any time. If we make material changes to this Cookies Policy, we will notify you by updating the date of this Cookies Policy, posting it on the Service, providing any notice or other appropriate means in accordance with applicable laws. Any modifications to this Cookies Policy will be effective upon our posting the modified version (or as otherwise indicated at the time of posting). We recommend that you review the Cookies Policy each time you visit our Service to stay informed of our privacy practices.\n\nIf you have any questions or comments about the Cookies Policy or the terms mentioned, please contact us as specified in the \"Contact Us\" section of the Privacy Policy.', NULL, 1, 0, 'legal', '[\"seller_footer\"]', 28, '2026-09-24 00:45:17', '2026-09-24 00:45:17');
INSERT INTO `pages` (`id`, `slug`, `parent_slug`, `title`, `banner_image`, `content`, `sections`, `is_published`, `show_in_footer`, `footer_group`, `menu_placements`, `sort_order`, `created_at`, `updated_at`) VALUES
(44, 'seller-privacy-policy', NULL, 'Seller Privacy Policy', NULL, '_Last Updated: September 24, 2026_\n\n**Index:** What Information We Collect · How and Why We Process Your Information · How and Why We Share Your Information · Your Rights and Choices · Children · Data Security and Retention · Additional Terms for Certain Jurisdictions · Changes to the Privacy Policy · Contact Us\n\nThis Seller Privacy Policy (\"Privacy Policy\") describes how NexTech (\"we\", \"us\" or \"our\") handles personal information that we collect from current and prospective sellers and their directors, officers, beneficial owners, employees, agents, trustees, stakeholders, and/or representatives, as applicable (hereinafter collectively referred to as \"Seller(s)\", or \"you\") through our digital properties that link to this Privacy Policy, including but not limited to our Seller Center websites and related services (collectively, the \"Service\"), and other activities as described in this Privacy Policy. If you interact with NexTech as a consumer, please refer to the [NexTech Privacy Policy](#/p/privacy) for details regarding the processing of your personal information, as this Privacy Policy only applies to personal information about you as a Seller.\n\nNexTech is committed to respecting and protecting your privacy. We strive to be transparent about our privacy practices, and this Privacy Policy describes, among other things, how we collect, use, share, and otherwise process the personal information of Sellers in connection with our Service, and your rights and choices with respect to your personal information. By continuing to use the Service, you acknowledge the practices described in this Privacy Policy.\n\nNexTech is responsible for the processing of your personal information. For additional terms for specific regions or countries, please see the \"Additional Terms for Certain Jurisdictions\" section.\n\n## What Information We Collect\n\nIn the course of providing and improving our Service, we collect your personal information for the purposes described in this Privacy Policy. The following are the types of personal information that we collect:\n\n### Information that you provide\n\nWhen you create an account, contact us directly, or otherwise use the Service, you may provide some or all of the following information:\n\n**Account and profile.** In order for you to create and manage a NexTech seller account, we collect your mobile phone number and/or email address as the login credentials for your account and assign a user identification number to your account. You may also be required to provide information, including your first and last name, email address, address (e.g., physical address), phone number, demographic information (e.g., your date of birth and nationality), corporate information, tax information and documents (e.g., VAT number, tax identification number), your government-issued identification number and documents (e.g., your passport, driver\'s license, or U.S. social security number), information contained on these documents (e.g., identification number and expiry date), your relationship with the Seller, your shop details and other information (e.g., proof of personal and/or business address). Where necessary for the purpose of verifying your relationship with the Seller, we may also ask you to provide a proof of relationship with the entity (e.g., a letter of authorization). We also collect other information associated with your account.\n\n**Identity verification information.** We may ask you to provide certain information (e.g., images and/or videos) for identity verification and fraud-prevention purposes. We may also ask you to attend video calls with us for identity verification and/or fraud-prevention purposes, and these video calls may be recorded and retained, together with any transcripts generated, for verification and quality assurance purposes, in accordance with applicable laws. During your use of the Service, we may take further steps to verify your identity and/or to prevent fraud by requiring you to provide additional information and documents, including proof of your business address (e.g., copy of warehouse lease contract/property certificate, copy of utility bills, a video capturing your general location, as applicable), proof of your financial institution account information and financial institution information, and/or a selfie video. Please do not capture images of other individuals in the videos you provide. We may also ask you to provide images from identity documents and imagery of your face. We partner with a third-party verification service that extracts measurements of the facial features contained in the images (\"Facial Information\"). Facial Information may be considered \"biometric data\" under the laws of some jurisdictions. For more information, please refer to [Our Face Verification Processing](#/p/face-verification-processing) notice.\n\n**Payment information.** In order for you to make/receive a payment when using the Service, and to comply with applicable laws, we collect data related to your payment card information (e.g., card number, billing address), financial institution account information (e.g., account holder\'s name, account number), financial institution information and other financial institution documents (e.g., bank statements).\n\n**Transactional information.** We collect logistics information, including your shipping information (e.g., name, address and phone number), and package and delivery information. We also collect order details and transaction history associated with your account, and information about refunds and complaints.\n\n**Support communications.** We collect communication history among you, our customers and us, and between you and us on the Service, which includes any text, images, video, audio, or supporting documents exchanged among or between the aforementioned parties through our customer/seller support functions on the Service, through social media or by any other means, to assist in providing support, and to facilitate and enhance support activity.\n\n**Your generated content.** We collect content that you generate, transmit, or otherwise make available on the Service (including in relation to appeals to our decisions), such as shop logos, photos, images, videos, audio, comments, questions, answers (including answers to questionnaires you participate in through the Service), messages, text, files, and other content or information, as well as associated metadata.\n\n**Other information not explicitly listed in \"information that you provide\".** We may collect other information that you provide for purposes as described in this Privacy Policy or for any other purpose disclosed to you prior to or at the time we collect your information in accordance with applicable laws. For example, we may also collect information related to manufacturers (and responsible persons as defined under applicable product safety laws) in connection with the products you make available via our Service, in order to comply with our legal and/or business obligations. This information may amount to personal information under the laws of some jurisdictions, and you are responsible for informing the manufacturer (and the responsible persons as defined under applicable product safety laws) about such processing activities, as set out in this Privacy Policy, and ensuring that all third-party personal information disclosed to us is in compliance with applicable laws.\n\n### Information from third-party sources\n\nTo the extent permitted by applicable laws, we may receive and collect your personal information from third-party sources, such as:\n\n**Data providers.** We receive and collect your personal information from data providers such as identity verification providers and data licensors that provide demographic and other information (e.g., Sellers\' names, corporate information, verification result), and bank account verification providers that provide bank account information (e.g., account holder\'s name, account number), which among other purposes help us verify identity, detect fraud, and provide our Service.\n\n**Marketing partners.** We receive and collect your personal information from our marketing partners, such as business partners with whom we collaborate on marketing events and promotion of the Service.\n\n**Other third-party services.** We collect your personal information from other third-party service providers for purposes as described in this Privacy Policy, such as:\n\n- **Logistics or warehousing service providers.** To effectively complete order fulfillment, we will obtain certain information from these providers, such as package information, delivery progress, proof of delivery and delivery address.\n- **Public authorities, public sources, and other parties.** We obtain your personal information from third-party sources, such as government agencies, public records, other publicly available sources, customers of NexTech or other third parties providing information about transactions or claims they may have related to you.\n\n### Information collected automatically\n\nTo enhance your experience with the Service and support the other purposes for which we collect personal information as outlined in this Privacy Policy, we may automatically collect information about you, your computer, or mobile device and your interactions with the Service and our communications over time, such as:\n\n**Device data.** We collect personal information about the device you use to access the Service, such as device model, operating system information, language settings, and unique device identifiers.\n\n**Service usage information.** We collect personal information about your interactions with the Service, including the source from which you arrived at our pages, the pages you viewed, the duration for which you visited a page, whether you opened our emails or clicked on the links within our emails, your preferences for receiving marketing communications from us, and other interactions with the Service (e.g., your browsing and searching history, your participation records in promotions). We also collect service-related, diagnostic, and performance information, including crash reports and performance logs.\n\n**General location data.** We collect your approximate location data based on your technical information (e.g., IP address).\n\n**Data collected via cookies and similar technologies.** We collect information via cookies and similar technologies to operate and provide the Service, including to enable your login to your NexTech seller account; to display the page you view; to measure and analyze how you use the Service, including your language setting, time zone; and to detect fraud and mitigate risks. Cookies and similar technologies are also used to enhance your experience with the Service and improve the Service. Pixels are very small images or pieces of data embedded in an image, also known as \"web beacons\" or \"clear GIFs\", that recognize cookies, the time and date the page or email was viewed or opened, a description of the page on which the pixel was placed, and similar information from your device. To learn more, including how to disable certain cookies, please read our [Seller Cookies and Similar Technologies Policy](#/p/seller-cookies-policy).\n\n### Declining to provide information\n\nWe need to collect certain personal information from you to provide the Service and certain related services. If you do not provide the information we require to provide the Service, we cannot provide the Service. However, we also collect some optional information from you; this information is optional, meaning we can still provide the Service, but without it, the quality of your experience of the Service may be affected.\n\n## How and Why We Process Your Information\n\nWe process your personal information that we collect for various purposes, including but not limited to, verifying your identity, to develop, improve, support, and provide the Service, allowing you to use its features and to fulfill and enforce our NexTech Seller Services Agreement. We may use your personal information for the following purposes:\n\n**Create, maintain, and manage your account.** We use your personal information to create and maintain your account on the Service, enable account security features (e.g., sending security codes via email or text messages), and facilitate your invitations to persons who you want to invite to assist you in managing your account on the Service.\n\n**Verify your identity and protect our business.** We use your personal information to verify your identity and prevent fraud on our platform in order to protect our customers, Sellers, and our business.\n\n**Orders, payments and delivery of services.** We use your personal information to process orders and payments, deliver services, process and communicate with you regarding orders, services, and promotional offers, and facilitate order disputes, refunds and/or returns of orders.\n\n**Improve and optimize services and troubleshooting.** We use your personal information to optimize features, analyze performance metrics, fix errors, and maintain and improve the Service and our business. As part of these activities, we may create aggregated or otherwise deidentified data based on the personal information we collect.\n\n**Deidentified information.** We deidentify your personal information such that it cannot reasonably be used to infer information about you or otherwise personally identify you, and we may use such deidentified information for any purpose, to the extent permitted by applicable laws. To the extent we possess or process any deidentified information, we will maintain and use such information in deidentified form and will not attempt to reidentify the information, except solely for the purpose of determining whether our deidentification process satisfies applicable legal requirements.\n\n**Communicate with you and provide support.** We use your personal information to communicate with you (e.g., announcements, notifications, updates, security alerts, calls, support, and administrative messages) and provide support for your requests, questions, and feedback.\n\n**Promotional activities.** We use your personal information such as your account information, transactional information and participation records to administer promotional activities.\n\n**Marketing.** We and our service providers collect and use your personal information for marketing purposes in accordance with applicable laws. Where permitted by applicable laws, we may send you direct marketing communications, such as emails, messages and/or calls. You may opt out of our marketing communications as described in the \"Your Rights and Choices\" section below.\n\n**Advertising.** We may collect and use your personal information for measuring the effectiveness of the advertisements shown to you to promote the Service on third-party platforms and websites, and we may share certain of your personal information with our third-party advertising partners to optimize the effectiveness of such advertisements on third-party platforms and websites in accordance with applicable laws. You can learn more about advertising and how to opt out of the use of your personal information for advertising in the [Seller Cookies and Similar Technologies Policy](#/p/seller-cookies-policy), in countries where we enable this functionality.\n\n**Fraud prevention and security.** We use your personal information to prevent, detect, investigate, and respond to fraud, unauthorized access to or use of the Service, violations of the NexTech Seller Services Agreement and/or other NexTech policies, or other misconduct.\n\n**Compliance, legal obligations and protection.** We may use your personal information for compliance purposes and to comply with applicable laws, including lawful requests, and legal processes (e.g., responding to subpoenas or other lawful requests from government or regulatory authorities); to protect our, your, and other Sellers\' and customers\' rights, privacy, safety, or property (e.g., the establishment, exercise or defence of legal claims); to audit internal processes to ensure compliance with legal and contractual requirements and our internal policies; to enforce the terms and conditions that govern the Service; to prevent, identify, investigate, and deter fraudulent, harmful, unauthorized, unethical, or illegal activities, including cyberattacks and identity theft.\n\n**Purposes for which we seek your consent.** In some cases, we may ask for your consent to collect, use, or share your personal information for a specific purpose that we communicate to you, in accordance with applicable laws.\n\n**Cookies and similar technologies for technical operations, performance enhancement, advertising, etc.** For more information about cookies and how we use them, please read our [Seller Cookies and Similar Technologies Policy](#/p/seller-cookies-policy).\n\n## How and Why We Share Your Information\n\nAt NexTech, we care deeply about privacy. We may share your personal information with the following parties for the purposes outlined below:\n\n**Affiliates.** Our Service is supported by entities within our corporate group. We share some of your personal information with NexTech subsidiaries and affiliates as necessary to provide organizational, technical, legal and compliance support for the Service, including for the purposes of providing and optimizing services, detecting irregular activities and safeguarding our Service and/or public safety. Such personal information includes name, address, corporate information, shop details and contact information. These subsidiaries and affiliates either follow the same practices described in this Privacy Policy or follow practices at least as protective as those described in this Privacy Policy.\n\n**Service providers.** We share your personal information with third parties who provide services on our behalf or help us operate the Service or our business (such as information technology, identity verification, bank account verification, email/text message delivery, fraud detection and prevention, security, compliance, and customer support). These third-party service providers only have access to personal information needed to perform their functions and services, and we require these service providers to use personal information only as necessary to perform their services or comply with applicable legal obligations.\n\n**Payment processors.** We share your personal information with our payment processors to assist you in the registration and certification of the payment service providers as described in our agreements, to make or receive a payment when you are using the Service, and/or to process disputes. Our payment processors may also process your personal information to comply with applicable laws and for compliance (e.g., anti-money laundering) and risk control purposes, in which case they may act as the controllers of such personal information.\n\n**Advertising partners.** We may share your personal information with third-party advertising partners to optimize the effectiveness of the advertisements to promote the Service presented on third-party platforms and websites in accordance with applicable laws. Details of the purposes are further described in our Seller Cookies and Similar Technologies Policy. For additional information and to learn about your right to opt out of such practices, see the \"Your Rights and Choices\" section, and the Seller Cookies and Similar Technologies Policy.\n\n**Third parties designated by you.** We may share your personal information with third parties where you have instructed us or provided your consent to do so. We may share the personal information required for the services you request with third parties designated by you. Please be aware that when you use third-party sites or services, their own terms and privacy policies will govern your use of those sites or services.\n\n**Business and marketing partners.** We may share your personal information with third parties with whom we collaborate in order to offer or promote the Service. For example, depending on your communication preferences, we may share your personal information with third-party service providers we have partnered with to send you marketing communications, for example via messages and/or emails.\n\n**Professional advisors, public authorities, institutions, regulators, and third parties with legal rights.** We may share your personal information with our professional advisors (e.g., lawyers, auditors, bankers and insurers), and public authorities, such as law enforcement authorities in response to legal processes (e.g., responding to subpoenas or other lawful requests from government or regulatory authorities); with third parties in accordance with legal requirements (e.g., name, business address and contact information to comply with legal requirements); and with other parties (including financial institutions) to enforce our agreements or policies, protect the rights, property and safety of NexTech, Sellers, customers, and others, and to detect, prevent and address actual or suspected fraud, violations of NexTech Seller Services Agreement, other illegal activities, security issues or when it is required by applicable laws.\n\n**Business transferees.** In the rare event of a business transaction such as a merger, acquisition, or reorganization, we may share some of your personal information with the relevant parties (e.g., a buyer or successor) to facilitate such a transaction. If we intend to transfer information about you, we will inform you either by email or by posting a notice on the Service.\n\n**Other users.** We share your personal information (e.g., contact information, business and shipping address) with customers for the receipt and/or delivery of products and services. Your shop details, product information, as well as certain account and profile information (e.g., name, address, contact information, VAT number, tax identification number, corporate information), may be made available to other users and the public in accordance with applicable laws.\n\n## Your Rights and Choices\n\nWe provide you with the ability to exercise certain rights and choices regarding our collection, use, sharing and processing of your personal information. Please see the \"Additional Terms for Certain Jurisdictions\" section for additional rights you may have and how to exercise such rights in certain jurisdictions. In accordance with applicable laws, your rights and choices may include the following:\n\n**Rights to access, delete and correct your personal information.** You may have the right to access, delete or correct your personal information, in addition to other rights under applicable privacy laws.\n\n**Withdrawal of consent.** Where we process your personal information based on consent (such as when conducting biometric identity verification before you start selling on our Service), you may withdraw your consent at any time using the details set out in the \"Contact Us\" section in this Privacy Policy. Your withdrawal of consent will not affect the lawfulness of processing based on consent before its withdrawal (please note, however, that we may still be entitled to process your personal information if we have another legal basis other than consent for doing so).\n\n**Opt-out from marketing communications.** To manage your preferences, opt out of or withdraw your consent to marketing communications made available to you, you can take any of the following actions. Rest assured that you can continue to use the Service even if you opt out of marketing communications.\n\n- **Email promotional offers:** When you provide us with your email address, we may send you certain marketing emails subject to the requirements of applicable laws. Standard data rates may apply. If you do not want to receive any marketing emails from us, you may follow the unsubscribe options at the bottom of each email to stop receiving such emails.\n- **Promotional offers via phone number:** When you provide us with your phone number, we may send you certain marketing text messages and/or make marketing calls to you, subject to the requirements of applicable laws. Standard data and messaging rates and/or call charges may apply. If you no longer wish to receive any marketing text messages from us, you can follow the instructions provided in these messages. If you no longer wish to receive any marketing calls from us, you can follow the instructions provided during the call or go to \"My Account\" to adjust your profile settings.\n- **WhatsApp promotional offers:** When you provide us with your mobile phone number, we may send you certain marketing messages and/or make marketing calls to you via the WhatsApp account associated with your mobile phone number, subject to the requirements of applicable laws. Standard data rates may apply. If you no longer wish to receive WhatsApp marketing messages from us, you may follow the instructions provided in the messages. If you no longer wish to receive any WhatsApp marketing calls from us, you can follow the instructions provided during the call.\n\n**Change settings for cookies and similar technologies.** Most browsers let you remove or reject cookies. To do this, follow the instructions in your browser settings. Many browsers accept cookies by default until you change your settings. Please note that if you set your browser to disable cookies, the Service may not work properly. For more information about cookies and similar technologies, including how to see what cookies and similar technologies have been set on your browser and how to manage and delete them, visit [allaboutcookies.org](https://www.allaboutcookies.org). You can also configure your device to prevent images from loading to prevent pixels from functioning.\n\n**Links to third-party platforms.** The Service may contain links to websites, mobile applications, and other online services operated by third parties. In addition, our content may be integrated into web pages or other online services that are not associated with us. However, please note that these links and integrations are not an endorsement of, or representation that we are affiliated with, any third party. Moreover, we do not control websites, mobile applications or online services operated by third parties, and we are not responsible for their actions. Therefore, we encourage you to read the policies and terms of use/service of the other websites, mobile applications and online services you use. If you revoke our ability to access information from a third-party platform, that choice will not apply to information that we have already received from that third party.\n\n**Do Not Track.** Some Internet browsers may be configured to send \"Do Not Track\" signals to the online services that you visit. We currently do not respond to \"Do Not Track\" or similar signals.\n\n**Other choices.** Please see the [Seller Cookies and Similar Technologies Policy](#/p/seller-cookies-policy) for additional rights and choices you may have and how to exercise such rights and choices.\n\n## Children\n\nTo register an account as a Seller on NexTech, you must be at least 18 years old or the age of majority as defined by applicable laws. Our Service is neither intended for, nor aimed at minors. We also do not knowingly collect or disclose the personal information of minors. If we become aware that we have unintentionally collected personal information from a minor through the Service, we will delete this information in compliance with applicable laws. If you believe that a minor may have provided us with personal information, contact us as specified in the \"Contact Us\" section of this Privacy Policy.\n\n## Data Security and Retention\n\nThe security of your personal information is important to us. We use appropriate technical and organizational measures to help protect your personal information from loss, theft, misuse, unauthorized access, disclosure, alteration, and/or destruction. We also follow the Payment Card Industry Data Security Standard (\"PCI-DSS\") in handling your credit card information. However, security risks are inherent in all internet and information technologies.\n\nWe generally retain personal information as long as necessary to fulfill the purposes for which we collected it or as disclosed to you at the point of collection, as well as for the purposes of satisfying any applicable legal, accounting, or reporting requirements, to establish, exercise or defend legal claims, or for fraud prevention purposes. To determine the appropriate retention period for personal information, we may consider factors such as the amount, nature, and sensitivity of the personal information, the potential risk of harm from unauthorized use or disclosure of your personal information, the purposes for which we process your personal information and whether we can achieve those purposes through other means, and the applicable legal requirements.\n\nData of Sellers will be stored on the infrastructure of cloud service providers. NexTech may need to engage and share your personal information with various parties, including our service providers, as described in the \"How and Why We Share Your Information\" section. These parties may be located in countries or jurisdictions that have data protection laws that are different in some aspects from the laws of the country or jurisdiction in which you reside. When we transfer your personal information outside the country or jurisdiction in which you reside, we implement appropriate measures to ensure that your personal information will remain protected in accordance with this Privacy Policy and applicable laws.\n\n## Additional Terms for Certain Jurisdictions\n\nWhere the laws of your state or country give you additional rights over your personal information, those rights apply in addition to this Privacy Policy. In the event of a conflict between the other terms of this Privacy Policy and any such additional terms, the additional terms shall prevail.\n\n## Changes to the Privacy Policy\n\nWe reserve the right to modify this Privacy Policy at any time. If we make material changes to this Privacy Policy, we will notify you by updating the date of this Privacy Policy, posting it on the Service, providing any notice or other appropriate means in accordance with applicable laws. Any modifications to this Privacy Policy will be effective from the time of posting the modified version (or as otherwise indicated at the time of posting). We recommend that you review the Privacy Policy each time you visit our Service to stay informed of our privacy practices.\n\nThe translated versions of this Privacy Policy are provided for your convenience. If there are any discrepancies between the English version and versions in other languages, to the extent permitted by applicable laws, the English version shall always prevail and govern your relationship with us.\n\n## Contact Us\n\nIf you have any questions or comments about our Privacy Policy or the terms mentioned, you may contact us at any time from **Seller Center → Messages**.', NULL, 1, 0, 'legal', '[\"seller_footer\"]', 27, '2026-09-24 00:47:14', '2026-09-24 00:48:53'),
(45, 'face-verification-processing', 'seller-privacy-policy', 'Our Face Verification Processing', NULL, '_Last Updated: September 24, 2026_\n\n**Index:** What Information Do We Collect · How Do We Use Facial Information · Data Security and Retention · Do We Share Facial Information · How to Withdraw Your Consent for Processing Facial Information · How to Access Your Facial Information · Seller Privacy Policy\n\nWhen you apply to register as a seller on NexTech, we take steps to facilitate your onboarding process utilizing facial recognition technology as outlined on this page. During your use of the Services, we may verify your identity by utilizing facial recognition technology as outlined on this page. We do this for purposes of verifying your identity and preventing fraudulent activities that may harm our customers, sellers, or our business. If you do not want to be verified by using the facial recognition technology, you may use the alternative manual process described on the pages collecting your information.\n\nPlease note that this Our Face Verification Processing notice (\"Notice\") supplements the Seller Privacy Policy, which can be found [here](#/p/seller-privacy-policy).\n\n## What Information Do We Collect\n\nFor the purposes described above, we will ask you to provide images of your face and of government-issued identity documents (e.g., your passport or driver\'s license) using your device\'s camera. We partner with a third-party verification service that extracts measurements of the facial features contained in the images (\"Facial Information\"). Facial Information may be considered \"biometric data\" under the laws of some jurisdictions. Your additional information (e.g., your name, date of birth, identity number, etc.) that is displayed on your government-issued identity documents will also be collected. Please refer to our [Seller Privacy Policy](#/p/seller-privacy-policy) for further details regarding the processing of this information.\n\n## How Do We Use Facial Information\n\nWith your consent, our third-party verification service provider processes the Facial Information only for the purposes of verifying your identity and preventing fraud as described above. We utilize facial recognition technology to check if you are a real person and if the photo on your government-issued documents matches your face. This Facial Information is solely used for the purposes outlined in this Notice and will not contribute to the improvement or development of our technologies, products, or services.\n\n## Data Security and Retention\n\nThe security of your personal information, including Facial Information, is important to us. We require our third-party verification service providers that process Facial Information to employ technical and/or organizational measures to help protect your Facial Information from loss, unauthorized access, unauthorized disclosure, alteration, and/or unlawful destruction. We entered into a robust data processing agreement with our third-party verification service providers to protect your personal information collected for identity verification.\n\nThe third-party verification service provider we partner with will retain Facial Information you provide for as long as necessary to carry out the purposes set forth in this Notice, but no longer than as required or permitted under applicable law. For Illinois residents, as applicable, we will permanently destroy your Facial Information when the first of the following occurs: (i) the initial purpose for collecting or obtaining such Facial Information has been satisfied; or (ii) within 3 years of your last interaction with us. We also contractually require a third-party verification provider to collect and retain the data for as long as necessary to carry out the purposes set forth in this Notice but no longer than as required or permitted under applicable law.\n\nWe retain only a record that your identity was verified.\n\n## Do We Share Facial Information\n\nAside from working with our third-party verification service provider to conduct identity verification, we do not share Facial Information unless required to do so under applicable laws, or if necessary to protect our legal interests, such as while exercising or defending legal claims.\n\n## How to Withdraw Your Consent for Processing Facial Information\n\nPursuant to applicable laws, you may withdraw your consent at any time by contacting us from **Seller Center → Messages**. If you do not wish to provide your facial images for verification purposes, you may use the alternative manual process described on the pages collecting your information.\n\n## How to Access Your Facial Information\n\nPursuant to applicable laws, you may submit a request to access your personal information by contacting us from **Seller Center → Messages**. Please refer to our [Seller Privacy Policy](#/p/seller-privacy-policy) for further information regarding your rights as a data subject.\n\n## Seller Privacy Policy\n\nNexTech is responsible for handling your personal information. For more details, please refer to our [Seller Privacy Policy](#/p/seller-privacy-policy).\n\nIf you have any queries or complaints about this Privacy Policy or our processing of personal information, please refer to our [Seller Privacy Policy](#/p/seller-privacy-policy).', NULL, 1, 0, 'legal', '[\"seller_footer\"]', 29, '2026-09-24 00:48:53', '2026-09-24 00:48:53');
INSERT INTO `pages` (`id`, `slug`, `parent_slug`, `title`, `banner_image`, `content`, `sections`, `is_published`, `show_in_footer`, `footer_group`, `menu_placements`, `sort_order`, `created_at`, `updated_at`) VALUES
(46, 'seller-services-agreement', NULL, 'NexTech Seller Services Agreement', NULL, '_Release date: September 24, 2026_\n\nThis NexTech Seller Services Agreement (this \"Agreement\") contains the terms and conditions that govern your access to and use of the services provided by us through the NexTech app and website (collectively, the \"NexTech Platform\") and is an agreement between you and us. As used in this Agreement, \"we,\" \"us,\" \"NexTech\" or similar terms shall mean NexTech, \"you\" shall mean the seller applicant, the \"Parties\" shall mean you and us, and a \"Party\" shall mean either you or us.\n\nIn addition to the terms and conditions contained in this Agreement, this Agreement also includes the following rules and content:\n\n- [Seller Code of Conduct](#/p/seller-code-of-conduct);\n- [Seller Fulfillment Policy](#/p/seller-fulfillment-policy);\n- [Anti-Fraudulent Transactions Policy](#/p/anti-fraudulent-transactions-policy);\n- [Prohibited Products List](#/p/prohibited-products);\n- [Extended Producer Responsibility (EPR) Policy](#/p/epr-policy);\n- [Global Data Protection Exhibit](#/p/global-data-protection-exhibit);\n- [NexTech Seller Advertising Services Terms](#/p/seller-advertising-services-terms) (if applicable);\n- other rules, specifications, and policies applicable to you that we publish from time to time (collectively referred to as the \"NexTech Seller Rules\").\n\nPlease review this Agreement, including the NexTech Seller Rules, carefully and fully understand its terms and conditions. If you register a NexTech seller account or use the services under this Agreement, it means that you have fully understood and agreed to be bound by the terms and conditions of this Agreement.\n\n## 1. Overview\n\n1.1. The NexTech Seller Rules are inseparable parts of this Agreement and have the same legal effect. If you use the Services (as defined below) on behalf of an entity, then \"you\" also refer to and include that entity, and you represent to us that you have all rights and authority necessary to bind that entity to this Agreement.\n\n1.2. We may amend (including revise, add, abolish, restate) this Agreement (including the NexTech Seller Rules) from time to time for legal, compliance, security, commercial or other reasons and publish the revised terms (\"Revised Term\") at the NexTech Seller Center, which is the online portal and tools provided to you to facilitate the operation and management of your NexTech seller account, including sub-accounts under it (\"Your Account\"). The Revised Terms will automatically take effect once published, unless otherwise provided therein. If you do not agree with the Revised Terms, please stop using the Services immediately. If you continue to use the Services after the Revised Terms are published, then you shall be deemed to have fully accepted and agreed to be bound by the Revised Terms. The Parties hereby confirm that unless otherwise agreed with regard to the effectiveness of the NexTech Seller Rules, this Agreement will replace all previous agreements between us and you regarding the use of Services as defined below.\n\n1.3. The Services under this Agreement may be provided by one or more of our Affiliates. Under any circumstances, this will not affect the validity of this Agreement and the rights that you enjoy and obligations and responsibilities that you undertake under this Agreement. For the avoidance of doubt, we shall be entitled to decide which Affiliate will actually provide the Services to you at our sole discretion. For purposes of this Agreement, \"Affiliate,\" with respect to an entity, means any other entity that controls, is controlled by, or is under common control with such entity. The term \"control,\" including controlling, controlled by and under common control with, means the possession, direct or indirect, of the power to direct or cause the direction of the management and policies of an entity, whether through the ownership of voting securities, by contract, or otherwise.\n\n## 2. Service Content\n\n2.1. The services provided by us and our Affiliates to you under this Agreement enable you to offer products and services directly on the NexTech Platform, which may include but are not limited to internet information technology services, software technology services, e-commerce transaction processing services and other related services (collectively referred to as the \"Services\").\n\n2.2. We may decide and change at any time and from time to time all aspects of the Services, including but not limited to their design, functionality, display, content, interface, availability and accessibility.\n\n## 3. Service Fees\n\n3.1. You will incur service fees for using the Services. Service fee details are set out in the applicable NexTech Seller Rules or announcements displayed in the NexTech Seller Center. The service fees shall be calculated based on the transaction data recorded in the system of the NexTech Platform. You authorize us to deduct service fees payable to us directly from Your Account or to take other measures in accordance with Section 3.3 or otherwise provided under this Agreement. If the balance of Your Account is insufficient, you shall promptly make up the difference.\n\n3.2. Service fees are charged as a commission on each sale of Your Products and are deducted from the sale proceeds credited to Your Account for that order. The applicable commission rate is displayed in the NexTech Seller Center.\n\n3.3. For any amount that we determine you owe us, including but not limited to the amount of any service fees and deductions to be applied to Your Account, we may: (i) offset it against any payment we make to you or amount we may owe you; (ii) charge any payment instrument you provide to us; (iii) invoice you, in which case you will pay the invoiced amount upon receipt; or (iv) collect it from you by any other lawful means. Except as provided otherwise, all amounts contemplated in this Agreement will be expressed and displayed in U.S. dollars, and all payments contemplated by this Agreement will be made in U.S. dollars.\n\n3.4. The balance of Your Account is paid out to the payout method (bank account or PayPal) you set in the NexTech Seller Center once it reaches the minimum payout amount shown there; a single payout may be capped at a maximum amount. When you first provide or update your payout details, we reserve the right to temporarily suspend payouts from Your Account until those details are successfully validated. In addition to the security measures under Section 6 of this Agreement, we may in our sole discretion require you to update your payout details subject to the terms of this Section 3.4.\n\n## 4. Account Registration and Store Opening\n\n4.1. In order for you to receive the Services, you must register a NexTech seller account by completing the registration process for one or more Services as instructed in the NexTech Seller Center. As part of the registration process, you shall provide us with your (or your business\'s) legal name, address, phone number and email address, as well as other information that we may request. Any personal data you provide to us will be processed in accordance with the [NexTech Seller Privacy Policy](#/p/seller-privacy-policy). You shall only be entitled to use the name that you are authorized to use in connection with the Services, and you shall update all information you provide to us in connection with the Services as necessary to ensure that it remains accurate, complete, and current at all times. You shall authorize us (and shall provide us with documentation evidencing your authorization upon our request) to verify your information (including any updated information).\n\n4.2. You shall ensure that any logo, mark, design, image or word used in connection with Your Account and online store on the NexTech Platform does not infringe on any other person\'s intellectual property rights or violate any Applicable Laws, defined under Section 5.2. NexTech reserves the right to take any necessary measures, including removal or temporary suspension, against any content, including but not limited to any logo, mark, design, image or word, which NexTech believes to be in violation of the terms of this Agreement.\n\n## 5. Use of Account\n\n5.1. You shall properly maintain and use Your Account, and you shall bear sole and full responsibility for any operation, action or commitment performed, taken or completed through Your Account, regardless of whether such operations, actions or commitments are undertaken by you or a third party (including your employees, contractors, or agents). You understand and agree that Your Account integrates large amounts of data, providing you with services including but not limited to business analytics, special tools, product listings display, market analyses, and other relevant services (subject to the information displayed in Your Account). You acknowledge and agree that such data have commercial value, remain NexTech\'s exclusive property and constitute Confidential Information under this Agreement. Your Account is for your own use only, as further described in Section 16 of this Agreement. You shall not share Your Account with or open sub-accounts for another person. You shall maintain the confidentiality and security of your user login name and password and shall be solely responsible for the use and loss of such information. We are not responsible for unauthorized use of Your Account. If you believe an unauthorized third party may be using Your Account or if the user login name and password to Your Account are leaked or stolen, you must change them and notify us immediately.\n\n5.2. When you use Your Account, you shall comply with all applicable laws, regulations, rules, industry standards, public policies, business ethics codes, internationally accepted labor and human rights standards, and codes of conduct of all applicable countries and regions (including but not limited to the countries and regions of your location, buyer\'s location, product origin, sales destination and export location) (\"Applicable Laws\"), in addition to the terms of this Agreement. You shall not use the NexTech Platform to engage in or facilitate any activity that is illegal, fraudulent, harmful to the reputation of the NexTech Platform, or detrimental to the interests of consumers.\n\n## 6. Deposit and Security Measures\n\n6.1. We may require that you maintain a certain balance as deposit (the \"Deposit\") in Your Account to secure the performance of your obligations under this Agreement or to mitigate the risks of returns, chargebacks, claims, disputes, violations of our policies, breach of this Agreement, or other risks to us, buyers or third parties. We may adjust the amount of the Deposit pursuant to Section 10 based on your behavior and risks to us and/or third parties. We shall not be obliged to pay interest or any other proceeds with respect to the Deposit. If the balance in Your Account is insufficient to cover the Deposit due to increased requirement or deduction, you shall pay or cause the payment of the difference into Your Account within the period specified by us.\n\n6.2. In the event of any returns, chargebacks, claims, disputes, violations of our policies or breaches of this Agreement, we may immediately deduct from the Deposit the amount owed by you and pay to the appropriate party. If the Deposit is insufficient to compensate for such amount, you shall promptly pay the difference.\n\n6.3. We may require that you pay other amounts to secure the performance of your obligations under this Agreement or to mitigate the risk of returns, chargebacks, claims, disputes, violations of our policies, breach of this Agreement, or other risks to us, buyers or third parties. These amounts may be refundable or nonrefundable in the manner we determine, and failure to comply with terms of this Agreement may result in their forfeiture.\n\n6.4. As a security measure, we may impose transaction limits on some or all customers and sellers relating to the value of any transaction or disbursement, the cumulative value of all transactions or disbursements during a period of time, or the number of transactions per day or other period of time. We will not be liable to you if we do not proceed with a transaction or disbursement that would exceed any limit established by us for security reasons.\n\n6.5. If we determine that your actions or performance may result in returns, chargebacks, claims, disputes, violations of our policies, breach of this Agreement, or other risks to us, buyers or third parties, we may in our sole discretion withhold any payments to you for as long as we reasonably determine such risk persists and take other remedial or preventive measures as we see reasonably necessary to mitigate such risk (such as remove product listings, restrict or limit your access to some or all of the Services, etc.).\n\n6.6. If we determine that Your Account, or any other account you have operated, has been used to engage in deceptive, fraudulent, or illegal activity, or your use of the Services has caused or is likely to cause serious harm to the legitimate interests of buyers, us, other sellers or third parties, we may in our sole discretion permanently withhold any payments to you.\n\n## 7. Rights and Licenses\n\n7.1. You grant us a royalty-free, non-exclusive, non-transferable (except as provided in Section 25.2), worldwide right and license, during the term of this Agreement and during any and all post-termination periods in which we are entitled to retain certain materials, information, and data, pursuant to Section 23.3, to use, distribute and otherwise exploit any and all Your Materials for the purpose of offering and maintaining the NexTech Platform, the Services, and other NexTech products or services, and to sublicense the foregoing rights to our Affiliates and operators of websites or mobile applications on which the NexTech Platform or its products or services are syndicated, offered, advertised or described; provided, however, that we will not change any of your trademarks (other than resizing them for presentation purposes). For purposes of this Agreement, \"Your Materials\" means the following items or information, whether registered or not, provided by you to us or our Affiliates in connection with your use of the Services: (a) ideas, data, research, procedures, processes, systems, methods of operation, concepts, principles, inventions, designs, and discoveries protected or protectable under the laws of any jurisdiction; (b) interfaces, protocols, glossaries, libraries, structured XML formats, specifications, grammars, data formats, or other similar materials; (c) software, hardware, code, technology, or other functional item; (d) copyrightable works under applicable law and content protected by database rights under applicable laws; and (e) trademarks (including service mark, trade dress, trade name or any other source or business identifier, protected or protectable under any laws).\n\n7.2. You grant us a worldwide, non-exclusive, perpetual, irrevocable, royalty-free, fully paid-up right and license to use, copy, modify, sell, publish, distribute, sublicense and create derivative works based on your suggestions, comments, or feedback regarding the NexTech Platform (collectively \"Feedback\") in any manner or for any purpose. We may, in our sole discretion, and without compensation to or attribution of you or any third party, use Feedback in any way, including in future modifications of the NexTech Platform.\n\n7.3. To the extent possible and applicable by law, you confirm that all moral rights, rights of authorship, or other similar rights, in the Feedback or Your Materials have been waived by the author of the material.\n\n## 8. Products\n\n8.1. You represent and warrant that: (i) you have all the necessary rights, permits, licenses and capacities to offer, sell and promote Your Products; (ii) Your Products comply with the Applicable Laws (including product liability, quality standards and consumer protection); and (iii) the offer and sale of Your Products comply with the Applicable Laws (including minimum age, marking and labeling requirements, product safety requirements, language requirements (where applicable), export and import administration and restrictions, sanctions, export controls, advertising and consumer protection).\n\n8.2. You shall ensure that all information regarding Your Products provided by you is true, accurate, current, complete and comply with the Applicable Laws (including the terms of this Agreement), and do not contain any sexually explicit, defamatory or obscene content.\n\n8.3. Any of Your Products that does not comply with the Applicable Laws or the terms of this Agreement or is returned by a buyer for any other reason will be regarded as a substandard product. For any substandard product, we shall be entitled to take disposal measures, including but not limited to notifying the buyer to destroy or return the product, canceling the order, applying a refund to the buyer, and/or destroying or disposing of the product. You will not take recourse against the buyer or claim any compensation or indemnity from us in connection with such disposal measures. At the same time, you shall actively cooperate with us in taking various disposal measures and bear all losses and expenses caused to consumers, us, our Affiliates or other third parties relating to substandard products.\n\n8.4. You shall cooperate with all regulatory authorities\' inquiries and investigations about Your Products in a timely manner as required. You shall be responsible for any public or private recall or safety warning relating to Your Products, including those due to any non-conformity or defect of Your Products. You shall notify us immediately upon becoming aware that any of Your Products is subject to a recall or a safety alert either publicly or privately. You understand and agree that if any of Your Products is subject to a recall or a safety alert, we shall be entitled to take disposal measures in connection with such product, including but not limited to actively cooperating with the regulatory authorities\' requirements, removing products from the NexTech Platform, issuing risk alerts to buyers, notifying buyers or other relevant parties to destroy/return products, cancelling orders, applying refunds to buyers and any other measures as requested or suggested by the regulatory authorities or as we see fit. You shall bear all costs and expenses incurred by us and our Affiliates, and shall not claim any compensation or indemnity from us, in connection with such disposal measures.\n\n## 9. Fulfillment and Delivery\n\n9.1. We will provide order information to you for each order of Your Products through Your Account. You are solely responsible and bear all risks for the source, offer, sale and fulfillment of Your Products in accordance with the applicable order information, the terms of this Agreement (including the NexTech Seller Fulfillment Policy), and all terms provided and displayed on the NexTech Platform at the time of the order.\n\n9.2. NexTech arranges collection of orders from the pickup address set in Your Account and delivery to buyers, using its own delivery riders or third-party logistics service providers (\"LSPs\") selected by NexTech. The use of an LSP is not a recommendation or endorsement by us of such LSP, nor do we guarantee the services provided by such LSP.\n\n9.3. You shall: (i) have each order ready for collection at your pickup address within the time limits shown in Your Account; (ii) package and label Your Products in a commercially reasonable manner and in compliance with all applicable packaging and labelling requirements, including any warnings or instructions necessary to safely use Your Products; (iii) strictly comply with the Seller Fulfillment Policy including all applicable fulfillment time limits; (iv) retrieve order information at least once each business day; (v) ensure that you are the seller of Your Products; (vi) include a packing list and any tax invoice required by law (if applicable) in each package of Your Products; and (vii) identify yourself as the seller of Your Products and the service provider for exchanges, returns, refunds and other related after-sales services.\n\n## 10. Payment\n\n10.1. Buyer payments for Your Products are collected and processed by third-party payment service providers used by NexTech (each a \"Third-Party PSP\"), such as Stripe, including collection, processing and refunds (the \"Payment Services\"). The sale proceeds of Your Products, less service fees and any other amounts due under this Agreement, are credited to Your Account and paid out to you as described in Section 3.4.\n\n10.2. While we do not provide Payment Services ourselves, we provide services to facilitate and enable Third-Party PSPs to provide Payment Services, including but not limited to: (i) providing Third-Party PSPs with information about you and underlying transactions as required; and (ii) providing you with an interface to view transactional and/or account data. You shall provide us with all necessary information and authorizations so that we can perform these services.\n\n## 11. Tax Matters\n\n11.1. You are responsible for collecting, reporting and paying any and all sales, goods and services, use, excise, premium, import, export, value added, consumption, and other taxes, regulatory fees, levies, or charges and duties assessed, incurred, or required to be collected or paid for any reason in connection with any offer or sale of Your Products through the NexTech Platform (collectively, \"Your Taxes\"), except that NexTech may calculate, collect and remit taxes on your behalf to the extent required by and in accordance with Applicable Laws. All fees and payments that you pay to us under this Agreement are exclusive of any applicable taxes, deductions or withholding.\n\n11.2. In addition to the service fees, you are solely responsible for paying any taxes, tariffs, government fees or financial charges arising from using the Services. If we are required by law or due to any business needs to withhold and pay any taxes, tariffs, government fees or financial charges on your behalf, you authorize us to deduct the withholding amount directly from Your Account. If the balance in Your Account is insufficient, you shall promptly make up the difference.\n\n11.3. To the extent we deem necessary and in our sole discretion, upon notice to you, you agree to enter into any applicable tax elections (\"Elections\") providing for the collection, reporting, and remittance of Your Taxes by us as provided for and in accordance with any Applicable Laws. You further agree to fully cooperate with us in respect of all steps required to effect such Elections in law, including but not limited to, upon request, the provision of all requested information related to charging, collecting, reporting and remittance of Your Taxes, and the execution, signing, and otherwise completion of any such form required to make such Elections within the time period as specified by us in the notice provided to you.\n\n## 12. After-Sales and Customer Services\n\n12.1. You are solely responsible to provide after-sales services and customer services to buyers in connection with Your Products in accordance with the terms of this Agreement. After-sales services include but not limited to order cancellations, returns, exchanges and refunds. Customer services include but not limited to processing customer inquiries, complaints and other communicative matters before, during and after the sales of Your Products. We do not assume any obligation with respect to after-sales services or customer services other than to pass any inquiries to your attention and to make available reasonable information in our possession regarding the fulfillment of Your Products.\n\n12.2. Notwithstanding Section 12.1, to ensure high-quality and consistent buyer experience on the NexTech Platform, you authorize us to facilitate the provision of after-sales services, including but not limited to buyers\' applications for order cancellations, returns, exchanges, refunds, and price match/adjustment. We will act in good faith to resolve the applications with information available to us at the time and in accordance with this Agreement. For the avoidance of doubt, if applicable, you will be solely responsible for any mandatory legal warranty or right to repair. You agree to accept and be bound by the resolution determined by us and not to take any further recourse against us or the buyer. If it is determined that a refund, price match/adjustment and/or other payment needs to be made to the buyer, you authorize us to deduct and pay the amount directly from Your Account to the buyer. If the balance of Your Account is insufficient, you shall promptly make up the difference. For the avoidance of doubt, we hereby acknowledge that to ensure the user experience, we will enable the buyers to claim for the price match/adjustment if they purchased the product at a price higher than its prevailing retail price.\n\n12.3. Notwithstanding Section 12.1, if we determine that the customer services provided by you with respect to a buyer do not comply with the applicable NexTech Seller Rules, we may facilitate in finding a resolution for you and the buyer. We will act in good faith with information available to us at the time and in accordance with this Agreement. You agree to accept and be bound by the resolution determined by us and not to take any further recourse against us or the buyer. If it is determined that a refund and/or other payment needs to be made to the buyer, you authorize us to deduct and pay the amount directly from Your Account to the buyer. If the balance of Your Account is insufficient, you shall promptly make up the difference.\n\n## 13. Transaction Disputes\n\n13.1. You authorize us to mediate and resolve disputes between you and buyers of Your Products. We will act based on the principle of fairness and objectivity. You shall promptly respond to our inquiries and deliver to us any information requested by us regarding the disputed transactions. You understand and agree that we may only review supporting documents or other evidentiary materials submitted by you, buyers or third parties on a general and non-professional level of knowledge. You further acknowledge and agree that for the purpose of such mediation and dispute resolution, we are not your agent or buyers\' agent. We cannot guarantee that the resolution meets your expectations, nor shall we assume any liability for the resolution processes or the resolution results. You hereby release us from all responsibility and liability associated with or arising from our mediation and resolution of disputes between you and buyers of Your Products. If you suffer losses as a result of inaccurate information provided by buyers or third parties, you shall independently seek damages from such buyers or third parties.\n\n13.2. Notwithstanding Section 13.1, we are not obliged to offer, and you are not obliged to accept our offer, to resolve the disputes between you and the buyers. You may choose to resolve the disputes directly with the buyers without our intervention subject to compliance with the NexTech Seller Rules. And you may choose not to accept the resolution proposed by us. At your request, we will provide a template settlement agreement for your reference and use upon your request. You hereby release us from all responsibility and liability associated with or arising from the use of the template settlement agreement.\n\n## 14. NexTech Sellers Representations and Undertakings\n\n14.1. You represent and warrant that: (i) if you are a business, you are duly organized, validly existing and in good standing under the laws of the country in which you are registered; (ii) you are fully competent and qualified to operate your business; (iii) you have all requisite power, authority, consents and capacity to enter into this Agreement, grant the rights, licenses, permits and authorizations in this Agreement, and perform your obligations under this Agreement; (iv) any information provided or made available by you is at all times true, accurate, complete and not misleading; (v) you are not subject to any sanctions or otherwise designated on any list of prohibited or restricted parties or affiliated with such a party including any anti-money laundering and terrorist financing laws; and (vi) your use of the Services and performance of your obligations under this Agreement do not, and will not, violate any Applicable Laws or conflict with any material contract, covenant or other obligation by which you are bound.\n\n14.2. We may conduct spot checks and other forms of inspections on your compliance of the terms and conditions of this Agreement, including the NexTech Seller Rules. You shall provide all reasonable access and cooperation to such spot checks and inspections. You shall ensure that all information submitted by you in connection with such spot checks and inspections is true, accurate, complete and not misleading.\n\n14.3. You undertake to act in good faith and not to engage in (i) any deceptive, malicious or anti-competitive act towards us, buyers or other sellers on the NexTech Platform, and (ii) any intentional or reckless conduct that is reasonably likely to disrupt the normal operation or business of the NexTech Platform, or facilitate the disruption of the normal operations or business of the NexTech Platform, including but are not limited to:\n\n(1) misuse, exploit or abuse the systems of the NexTech Platform; and\n\n(2) misuse, exploit or abuse the policies and rules of the NexTech Platform and its promotional events through fake reviews or comments, fraudulent transactions, related-party transactions or any other means.\n\n14.4. You acknowledge that we have incurred substantial costs to develop, maintain, operate and promote the NexTech Platform and to provide the Services to you. You undertake not to, directly or indirectly on your own or through third parties, engage in any conduct that is reasonably likely to cause harm to the goodwill and reputation of the NexTech Platform.\n\n14.5. You may have obligations to buyers or others in the event of claims for property damages or personal injuries in connection with Your Products. If you currently maintain commercial general, product, umbrella, and/or excess liability insurance to insure against such claims, each policy shall also include us and our Affiliates as additional insured. You may be required to obtain additional insurance. If notified of such requirement, you will have up to thirty (30) days to secure coverage. At our request, you will provide to us certificates of insurance, complete insurance policies, and any other related documents evidencing the required insurance coverage.\n\n14.6. If you violate any provision of this Agreement, including the NexTech Seller Rules, without prejudice to our rights under Section 6, we shall be entitled to take one or more of the following measures:\n\n(1) withhold part or all of the funds in Your Account and your Affiliated Accounts;\n\n(2) seek damages or compensation from you in accordance with this Agreement;\n\n(3) take any monetary corrective measures in accordance with this Agreement;\n\n(4) suspend, restrict, limit or terminate some or all of the Services;\n\n(5) issue alerts or warnings to buyers about you and/or Your Products;\n\n(6) conduct voluntary or mandatory recalls regarding Your Products;\n\n(7) cancel orders, apply refunds and take other remedial measures to compensate buyers of Your Products;\n\n(8) suspend, restrict, limit or terminate part or all of your access to the Services;\n\n(9) suspend or terminate this Agreement; and\n\n(10) take any other measure we deem appropriate to remedy the violation against Your Account and your Affiliated Accounts, including but not limited to taking monetary (e.g. deducting the corresponding amount as liquidated damages) or non-monetary corrective measures (e.g. suspending, restricting or terminating the function or access) against your Affiliated Accounts.\n\n## 15. Indemnification\n\n15.1. You shall defend, indemnify, and hold harmless us and our Affiliates, our and their respective administrators, officers, directors, managers, partners, legal representatives, shareholders, advisers, agents, employees (including temporary), staff, suppliers, and contractors (collectively, the \"Indemnified Party\") against any and all losses, damages, liabilities, deficiencies, claims, actions, judgments, settlements, interest, awards, penalties, fines, costs, or expenses of whatever kind, including reasonable attorneys\' fees, that are incurred by the Indemnified Party (collectively, \"Losses\"), arising out of or related to any third-party claim, proceeding, demand, investigation, or complaint alleging or arising from (i) breach or non-compliance of any provision of this Agreement by you or your agent; (ii) your non-compliance with the Applicable Laws; (iii) Your Products, including their offer, sale, fulfilment, refund, cancellation, return, adjustment and any personal injury or property damage related to them; (iv) Your Materials, including infringement of any Intellectual Property Rights by them; or (v) Your Taxes and duties or their registrations, filings, collections and payments.\n\n15.2. The Indemnified Party may select its own legal counsel to represent its interests, and you shall: (i) reimburse the Indemnified Party for its costs and attorneys\' fees immediately upon request as they are incurred; and (ii) remain liable to the Indemnified Party for any Losses indemnified under Section 15.1.\n\n15.3. You shall give prompt written notice to us of any proposed settlement of a claim that is indemnifiable under Section 15.1. Notwithstanding anything in this Section 15 to the contrary, you may not, without Indemnified Party\'s prior written consent, settle or compromise any claim or consent to the entry of any judgment regarding which indemnification is being sought hereunder.\n\n## 16. Affiliated Accounts\n\n16.1. Unless with our prior approval, you may only register one account for each region in which you sell. You may open sub-accounts under Your Account for operational convenience or other legitimate business reasons. For all purposes of this Agreement, all sub-accounts under Your Account are considered Your Account, and all accounts registered by you or your affiliates are considered your affiliated accounts (the \"Affiliated Account\").\n\n16.2. If one of your Affiliated Accounts violates the NexTech Seller Rules, we may suspend, restrict or terminate some or all of the functions of some or all of your Affiliated Accounts.\n\n## 17. Disclaimer and Limitation of Liability\n\n17.1. The NexTech Platform and the Services, including all information and content made available the Services are provided to you on an \"as-is\" and \"existing\" basis. We make no warranties and give no conditions of any kind, express or implied. We expressly disclaim any warranties and conditions of title, non-infringement, merchantability and fitness for a particular purpose, and any warranties and conditions implied by course of performance, course of dealing, or trade practice. We do not guarantee that: (i) the Service will be secure or available at any particular time or location; (ii) any defects or errors will be corrected; or (iii) the results of using the Services will meet your expectations. To the extent permitted by Applicable Law, your use of the Services, Your Account, the NexTech Seller Center and the NexTech Platform is at your own risk.\n\n17.2. We shall not, under any circumstances, assume any liability for inability to perform or delay in performing the obligations under this Agreement due to any event beyond our reasonable control (including but not limited to, internet connectivity failure, computer system failure, communications system failure, power failure, computer viruses, hacking, epidemics, strike, labor dispute, riot, uprising, disturbance, fire, flood, storm, explosion, war, government action, judgment or order from international or domestic court).\n\n17.3. Because we are not a party to the sale of Your Products between you and buyers, in the event of a dispute between you and buyers regarding a sale of Your Products, each party to the dispute releases NexTech and its agents, employees and representatives from claims, demands and liabilities of every kind and nature in connection with such dispute.\n\n17.4. TO THE EXTENT PERMITTED BY APPLICABLE LAW, IN NO EVENT SHALL OUR AGGREGATE LIABILITY IN CONNECTION WITH THIS AGREEMENT, WHETHER ARISING OUT OF OR RELATED TO BREACH OF CONTRACT, TORT (INCLUDING NEGLIGENCE) OR OTHERWISE, EXCEED THE TOTAL AMOUNT PAID BY YOU TO US DURING THE SIX-MONTH PERIOD PRECEDING THE EVENT GIVING RISE TO THE CLAIM. IN NO EVENT SHALL WE BE LIABLE FOR CONSEQUENTIAL, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, PUNITIVE OR ENHANCED DAMAGES, LOST PROFITS OR REVENUES, OR DIMINUTION IN VALUE IN CONNECTION WITH THIS AGREEMENT, REGARDLESS OF (A) WHETHER SUCH DAMAGES WERE FORESEEABLE, (B) WHETHER OR NOT YOU WERE ADVISED OF THE POSSIBILITY OF SUCH DAMAGES AND (C) THE LEGAL OR EQUITABLE THEORY (CONTRACT, TORT, OR OTHERWISE) UPON WHICH THE CLAIM IS BASED.\n\n## 18. Confidentiality, Privacy and Publicity\n\n18.1. You may receive information, documents, data, contents, materials relating to us, the Services or users of the NexTech Platform you obtained during the course of your use of the Services that is not known to the general public, including but not limited to: (i) information unique to specific users such as customer personal data; (ii) information about the Services such as business reports, trade insights, technical specifications, operational data, marketing events and terms; and (iii) information about us such as employee identity and position information (collectively, \"Confidential Information\").\n\n18.2. You acknowledge that all Confidential Information remains our exclusive property. You shall keep Confidential Information strictly confidential and use Confidential Information only to the extent necessary for your use of the Services. You shall be strictly prohibited from using Confidential Information for any other purpose. You shall not disclose, use, copy, transfer or permit a third party to use Confidential Information without our prior written consent. Notwithstanding the foregoing, you may share Confidential Information with government entities that have jurisdiction over you to the extent required by law, provided that you contact us before disclosure and limit the disclosure to the minimum extent necessary and state the confidential nature of the information shared clearly to the government entity.\n\n18.3. You shall take all reasonable steps to protect Confidential Information from unauthorized use or disclosure. At our request, you must immediately return or permanently destroy and delete Confidential Information.\n\n18.4. You may not use our name, trademarks, logo or other proprietary rights in any way without our prior written consent. You shall not issue or make any public statement about the Services without our prior written consent. You shall not misrepresent your relationship with us, including but not limited to claiming to have with us brand partnership, commercial alliance, licensing relationship, advertising endorsement, marketing sponsorship or similar arrangements.\n\n18.5. In connection with the Services, the Parties shall process and transfer Personal Data in compliance with the [Global Data Protection Exhibit](#/p/global-data-protection-exhibit). When and as required by the Parties from time to time, the Parties shall negotiate supplemental privacy and security terms in good faith as required for the lawful processing or transfer of Personal Data in accordance with the Data Protection Laws. The terms \"Personal Data\" and \"Data Protection Laws\" shall have the meaning set forth in the Global Data Protection Exhibit.\n\n18.6. Upon request from any competent government authority or to protect the integrity and operation of the NexTech Platform, we may access and disclose any information we consider necessary or appropriate, including but not limited to your contact details, business registration information, physical addresses, IP addresses and Your Materials.\n\n## 19. Force Majeure\n\n19.1. We will not be liable for any delay or failure to perform any of our obligations under this Agreement by reasons, events or other matters beyond our reasonable control.\n\n## 20. Relationship of Parties\n\n20.1. Nothing in this Agreement will create any partnership, joint venture, agency, mandate, franchise, sales representative, or employment relationship between you and us. Nothing in this Agreement shall be construed to give any third party beneficiary right to any person other than the Parties with respect to this Agreement. Except as otherwise expressly provided herein, neither Party shall have any express or implied right or authority to assume or create any obligations on behalf of or in the name of the other Party or to bind the other Party to any contract, agreement, or undertaking with any third party.\n\n## 21. Anti-Commercial Bribery and Conflicts of Interest\n\n21.1. You shall not, and shall ensure that your officers, directors, employees, agents and representatives shall not, directly or indirectly, make, offer or promise any illegal or improper bribe, kickback, payment, gift, paid travel or other forms of entertainment, or thing or service of value (\"Improper Benefit\") to any of our employees, consultants, agents, contractors or representatives.\n\n21.2. You shall not, and shall ensure that your officers, directors, employees, agents and representatives shall not, directly or indirectly, enter into any business partnership, collaboration, transaction with any of our employees, consultants, agents, contractors or representatives (\"Improper Business Relationship\").\n\n21.3. If you discover that any of our employees, consultants, agents, contractors or representatives solicits or accepts any Improper Benefit or Improper Business Relationship, you shall promptly notify us through Messages in the NexTech Seller Center and provide reasonable assistance to our investigation.\n\n## 22. Compliance with Applicable Laws\n\n22.1. You agree not to sell, import or export any products through NexTech Platform in violation of any Applicable Laws, including export and import regulations, international labour standards and applicable labor laws and regulations, including those targeting forced labour, prison labour and child labour. You hereby represent and warrant that your sale, transfer, export or import of the product does not violate any Applicable Laws and that you as a seller have taken all necessary steps to ensure you are in the position to legally import, export and sell the items. You are responsible for ensuring that your sale, importation and exportation of the products complies with all laws, regulations and requirements of the country from which it is being imported or exported and in which it is being sold. You hereby represent and warrant that products posted do not require an import license required by Applicable Laws.\n\n22.2. You agree to comply with all applicable import laws, sanctions laws, and export control laws, statutes, and regulations in your performance of this agreement, including but not limited to the requirements of the Export Administration Regulations, 15 C.F.R. 730-774, and the Office of Foreign Assets Control (\"OFAC\") regulations, Chapter V to 31 C.F.R., et seq.\n\nThis includes but is not limited to you refraining from sourcing any items from:\n\n- any origin subject to a comprehensive embargo by the U.S. Department of State or Treasury;\n- any person or entity located in, or entity owned by an entity located in, any destination subject to a comprehensive embargo;\n- any person or entity listed on the list of \"Specially Designated Nationals and Blocked Persons\" maintained by the U.S. Department of Treasury or any other applicable prohibited party list of the U.S. Government.\n\nThis clause will apply regardless of the legality of such a transaction under local law.\n\nYou represent and warrant that:\n\n- (i) you and your Affiliates are and always have been in compliance with all laws administered by OFAC or any other governmental entity imposing economic sanctions and trade embargoes (\"Economic Sanctions Laws\") against designated countries (\"Embargoed Countries\"), regimes, entities, and persons (collectively, \"Embargoed Targets\");\n- (ii) you and your Affiliates are not and have never been an Embargoed Target or otherwise subject to any Economic Sanctions Laws;\n- (iii) neither you nor any of your Affiliates is (a) directly or indirectly owned or controlled by any person currently included on the United Nations Security Council Consolidated List, the Specially Designated Nationals and Blocked Persons List or the Consolidated Sanctions List maintained by OFAC or any other similar list maintained by any governmental entity, or (b) directly or indirectly owned or controlled (\"Owned or Controlled\" or \"Owns or Controls\") by any person who is located, organized, or resident in a country or territory that is, or whose government is, the target of sanctions imposed by the U.S. Government, OFAC or any other governmental entity;\n- (iv) you shall promptly notify us if you or any of your Affiliates becomes directly or indirectly Owned or Controlled by any person described in subsection (iii) immediately above;\n- (v) neither you nor any of your Affiliates or any of your or their officers, directors, managers, agents, or employees is a person who (a) is currently the subject of any investigation by OFAC or any other governmental entity imposing economic sanctions or trade embargoes (\"Sanctions Investigation(s)\"), or (b) is directly or indirectly Owned or Controlled by any Person who is currently the subject of a Sanctions Investigation;\n- (vi) you shall promptly notify us if (a) you or any of your Affiliates, or any of your or their officers, directors, managers, agents, or employees becomes the subject of any Sanctions Investigation, or (b) any person who directly or indirectly Owns or Controls you or any of your Affiliates becomes the subject of any Sanctions Investigation.\n\n22.3. You shall also ensure that your direct suppliers, and use your best efforts to seek to ensure that your indirect suppliers, are in compliance with all Applicable Laws in relation to products sold through the NexTech Platform. Upon reasonable notice, we reserve the right to audit at any time (either directly, or through a third party who we may select at our absolute discretion) your compliance with Applicable Laws, and you agree to use your best efforts in encouraging your suppliers to permit us (or a third party at our discretion) to audit their compliance with Applicable Laws. We also expect you to have processes in place to ensure that all Applicable Laws are complied with, and to notify us as soon as possible if any known or suspected non-compliance with Applicable Laws arises.\n\n22.4. You agree to provide all necessary information that we reasonably request in order to establish whether any such non-compliance with Applicable Laws has occurred (whether in relation to you, or one of your suppliers), and to facilitate access to all relevant records and personnel for audit purposes. Should any non-compliance be identified, you shall promptly rectify or cause the rectification of the issue to our satisfaction.\n\n22.5. You hereby represent and warrant that all products offered for sale and sold through NexTech Platform are not listed on the Commerce Control List (CCL) of the Export Administration Regulations (EAR) (15 C.F.R. Parts 730-774) as administered by the United States Department of Commerce.\n\n## 23. Termination of Agreement\n\n23.1. You may terminate this Agreement at any time by providing an advance written notice to us in accordance with the NexTech Seller Rules. Unless otherwise indicated in your notice, the termination shall become effective immediately upon our receipt of the notice.\n\n23.2. We may terminate your access to the Services or this Agreement for convenience with 30 days\' advance notice. We may suspend or terminate your access to the Services or this Agreement immediately if we determine that:\n\n- you have materially breached this Agreement;\n- there have been three or more buyer complaints about Your Products;\n- Your Account may be used for deceptive, fraudulent or illegal activity;\n- you become bankrupt or insolvent or become non-operable for any reason; or\n- we are under legal obligation or requirement to do so.\n\n23.3. Upon termination of this Agreement: (i) all rights and obligations under this Agreement shall terminate immediately, except that you will remain responsible to perform all of your obligations in connection with transactions entered into before the termination and for any liabilities that accrued before or as a result of the termination; (ii) we may retain materials and information relating to you and Your Accounts as required by and in accordance with the Applicable Laws; (iii) we may retain your operational data for at least six (6) months after the termination or such longer time as we deem necessary to protect our interests and interests of third parties and customers of the NexTech Platform.\n\n23.4. Sections 3, 7, 8, 9, 11, 12, 13, 14, 15, 16, 17, 18, 23, 24 and 25 of this Agreement shall survive the termination until the obligations therein are fully performed.\n\n## 24. Governing Law and Dispute Resolution\n\n24.1. The laws of the State of Delaware and the federal laws of the United States, without reference to its conflict of law rules, shall govern this Agreement. The United Nations Convention on Contracts for the International Sale of Goods (CISG) shall not apply.\n\n24.2. The Parties agree that any dispute or claim arising out of or relating to this Agreement or your access to or use of the Services (each, a \"Dispute\") will be resolved by final and binding arbitration administered by the American Arbitration Association (\"AAA\") under its Commercial Arbitration Rules. There shall be one arbitrator agreed to by the Parties within twenty (20) days of receipt by respondent of the written demand for arbitration or in a default thereof appointed by the AAA in accordance with its Commercial Arbitration Rules. The place of the arbitration shall be Wilmington, Delaware. Arbitral awards shall be final and binding on the Parties and may be entered and enforced in any court having jurisdiction. Judgment on the award shall be final and non-appealable. Except as may be required by law, neither Party nor the arbitrator may disclose the existence, content or results of any arbitration arising out of or relating to this Agreement without the prior written consent of both Parties, unless to protect or pursue a legal right. The Parties shall bear their own attorneys\' fees and costs in arbitration unless the arbitrator finds that either the substance of the Dispute or the relief sought in the arbitration notice was frivolous or was brought for an improper purpose (as measured by the standards set forth under applicable arbitration legislation and rules of procedure). Before you may begin an arbitration proceeding, you must send a letter notifying us of your intent to pursue arbitration and describing your claim in reasonable detail.\n\n24.3. The Parties agree that any Dispute will be resolved only on an individual basis and not on a class, representative or collective basis. This subsection does not prevent either Party from participating in a class-wide settlement of claims.\n\n24.4. Notwithstanding the Parties\' agreement to resolve all Disputes through arbitration, you or we may assert claims in small claims court for disputes or claims within the scope of that court\'s jurisdiction, so long as the matter remains in such court and advances only on an individual (non-class, non-representative) basis.\n\n24.5. Subject to and without waiver of the agreement to arbitrate under Section 24.2, you and we each submit to the exclusive personal jurisdiction of and agree that the venue of any judicial proceedings will be brought in the state and federal courts located in Delaware. Each party waives any right to object to such forum and no party may allege the inconvenience of such forum, whether by motion or otherwise.\n\n24.6. Where permitted by Applicable Law, you agree that any claim against us must be brought within one year of the date on which you first become aware, or reasonably should have become aware, of facts giving rise to such claim. You agree that this one-year limitations period is reasonable and that if you fail to provide notice of intent to initiate an informal dispute resolution conference within such time, your claim will be forever barred and may not be pursued against us, either in arbitration or a court.\n\n## 25. Miscellaneous\n\n25.1. Notices. We will provide notices to you under this Agreement by sending system messages or in-platform messages to Your Account, posting announcements in the NexTech Seller Center, sending text messages to the contact number provided by you, or sending emails to the e-mail address provided by you. You must send all notices relating to the Services to our Seller Services Team via Messages in the NexTech Seller Center. Messages shall be deemed delivered upon being sent successfully.\n\n25.2. Assignment. You may not assign or transfer this Agreement or any right or obligation hereunder without our prior written consent. Any purported assignment or transfer in violation of this Section 25.2 shall be null and void. We may assign or transfer our rights and obligations under this Agreement in connection with (i) a merger, consolidation, acquisition or sale of all or substantially all of our assets or similar transaction or (ii) to an Affiliate as part of a corporate reorganization.\n\n25.3. Entire Agreement. This Agreement constitutes the sole and entire agreement between the Parties with respect to the Services and related subject matter, and supersedes all prior and contemporaneous understandings, agreements, representations, and warranties, both written and oral, with respect to such subject matter.\n\n25.4. No Waiver. No waiver by a Party of any of the provisions hereof shall be effective unless explicitly set forth in writing and signed by the Party so waiving. No waiver by a Party shall operate or be construed as a waiver in respect of any failure, breach, or default not expressly identified by such waiver, whether of a similar or different character, and whether occurring before or after that waiver. No failure to exercise, or delay in exercising, any right, remedy, power, or privilege arising from this Agreement shall operate or be construed as a waiver thereof; nor shall any single or partial exercise of any right, remedy, power, or privilege hereunder preclude any other or further exercise thereof or the exercise of any other right, remedy, power, or privilege.\n\n25.5. Cumulative Remedy. Except as otherwise expressly provided herein, the rights and remedies under this Agreement are cumulative and are in addition to and not in substitution for any other rights and remedies available at law or in equity or otherwise.\n\n25.6. Severability. If any provision of this Agreement is held to be illegal, unenforceable or invalid, the remaining portion such provision shall be severable and the remainder of this Agreement shall remain unaffected and continue to be in full force and effect.\n\n25.7. Headings. The headings of this Agreement are for convenience of reference only and shall not define, affect or limit the meaning, description and interpretation of the terms of this Agreement.\n\n25.8. Language. All documents, notices, waivers, consents or other communications under or in connection with this Agreement are to be prepared and executed in the English language only.', NULL, 1, 0, 'legal', '[\"seller_footer\"]', 19, '2026-09-24 00:52:50', '2026-09-24 00:52:50');

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
-- Table structure for table `payout_requests`
--

CREATE TABLE `payout_requests` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `shop_id` bigint(20) UNSIGNED NOT NULL,
  `amount_cents` bigint(20) UNSIGNED NOT NULL,
  `status` varchar(16) NOT NULL DEFAULT 'pending',
  `admin_note` varchar(500) DEFAULT NULL,
  `ledger_entry_id` bigint(20) UNSIGNED DEFAULT NULL,
  `processed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `personal_access_tokens`
--

CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) UNSIGNED NOT NULL,
  `name` text NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `personal_access_tokens`
--

INSERT INTO `personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES
(7, 'App\\Models\\User', 17, 'customer', '456796337ac1d836c1154146175b25528d58aa7869bfca580ba29b1ab373a31f', '[\"*\"]', '2026-09-09 02:28:52', NULL, '2026-09-09 02:25:33', '2026-09-09 02:28:52'),
(10, 'App\\Models\\User', 16, 'customer', '8fdc9fbafcf3cc942fa134edea164a87468fca875fef8adbfae8365b4d8b5978', '[\"*\"]', '2026-09-09 02:32:40', NULL, '2026-09-09 02:30:45', '2026-09-09 02:32:40'),
(13, 'App\\Models\\User', 16, 'customer', '523f28e36ce4c3be8307320ea60a0c0841c1fc7e81651b87ec1e01981c665d10', '[\"*\"]', '2026-09-09 02:39:14', NULL, '2026-09-09 02:34:07', '2026-09-09 02:39:14'),
(23, 'App\\Models\\User', 16, 'customer', '08d1476873ee76edd238f947b8a6bdbd95cb51de55064f6b7d3c3bc320b861f5', '[\"*\"]', '2026-09-09 04:49:30', NULL, '2026-09-09 04:49:14', '2026-09-09 04:49:30'),
(24, 'App\\Models\\User', 17, 'customer', '6e1418b75405dd71aa8656e2f5be0a721a725fe04e35b439acb002c868e89d16', '[\"*\"]', NULL, NULL, '2026-09-09 04:49:42', '2026-09-09 04:49:42'),
(26, 'App\\Models\\User', 16, 'customer', 'c3b22b30312f4efc2bb46da67896d60c0d98db74594c81755f414c6ad747ac4a', '[\"*\"]', '2026-09-09 04:50:17', NULL, '2026-09-09 04:50:11', '2026-09-09 04:50:17'),
(30, 'App\\Models\\User', 16, 'customer', 'eb7d0f829f0baef7af1b3bce1329714c8ee8a291d5505cd0beb45b5c0ffd7832', '[\"*\"]', '2026-09-09 05:00:53', NULL, '2026-09-09 05:00:19', '2026-09-09 05:00:53'),
(31, 'App\\Models\\User', 17, 'customer', '89f81e8cded777c546d7c26df2bc9dbfc01377b9cd6cf375ee8b5bae8706f96e', '[\"*\"]', NULL, NULL, '2026-09-09 05:01:05', '2026-09-09 05:01:05'),
(33, 'App\\Models\\User', 16, 'customer', 'b764b4d49406f5204ebe19b541f51eb693911e74fc35417979818a55fd025eb6', '[\"*\"]', '2026-09-09 05:02:15', NULL, '2026-09-09 05:01:27', '2026-09-09 05:02:15'),
(34, 'App\\Models\\User', 17, 'customer', 'a85cabe67ba928751616ade687760bd7ab810ded65534a0187eba48c74805962', '[\"*\"]', NULL, NULL, '2026-09-09 05:02:22', '2026-09-09 05:02:22'),
(43, 'App\\Models\\User', 16, 'customer', '7ad55327f05f20fe77067321d1627f177278d02d0976f8944b107220872de1d3', '[\"*\"]', '2026-09-09 05:36:35', NULL, '2026-09-09 05:36:30', '2026-09-09 05:36:35'),
(44, 'App\\Models\\User', 16, 'customer', '2ddb9ca2b6232a706c01fbd5889d70ba430949dbe50242e19a0707f8b6738849', '[\"*\"]', '2026-09-09 05:36:49', NULL, '2026-09-09 05:36:47', '2026-09-09 05:36:49'),
(48, 'App\\Models\\User', 16, 'customer', '3eeb808a865e69b6cb6606baa5c837fafee6026fdb3fd8d66936ec5f7394f76e', '[\"*\"]', '2026-09-10 01:32:05', NULL, '2026-09-09 23:53:46', '2026-09-10 01:32:05'),
(56, 'App\\Models\\User', 16, 'customer', '25c1e76dcd9646cb4ec576b2f05b236b123b29c2491883a2fc3687de12cd8221', '[\"*\"]', NULL, NULL, '2026-09-10 01:40:03', '2026-09-10 01:40:03'),
(57, 'App\\Models\\User', 16, 'customer', 'c024a66028f7068a0192df8846972f4d3e2b6bd6cf55c2044f696ec73b6bef82', '[\"*\"]', '2026-09-10 01:47:05', NULL, '2026-09-10 01:40:06', '2026-09-10 01:47:05'),
(61, 'App\\Models\\User', 16, 'customer', '913d24db963de2f035d6f88eaf4b32f5a783a70d1baf3079f8f69e384790e01e', '[\"*\"]', '2026-09-10 02:23:07', NULL, '2026-09-10 02:07:17', '2026-09-10 02:23:07'),
(63, 'App\\Models\\User', 28, 't', 'a0914c64b2715efee575f5f543d386913db4a51773e1fc2a0095741c874ebd8d', '[\"*\"]', '2026-09-11 00:00:49', NULL, '2026-09-10 02:14:06', '2026-09-11 00:00:49'),
(64, 'App\\Models\\User', 16, 'customer', '745b98f4daf38ac279fa60f0e4c8c97433ea6a114ea4d26becd73d86c00e0371', '[\"*\"]', '2026-09-10 03:06:03', NULL, '2026-09-10 02:50:37', '2026-09-10 03:06:03'),
(66, 'App\\Models\\User', 16, 'customer', '043eb6ea1f031d73e37ef0f345474d24dd07b9967c131228a207d2a7834dad83', '[\"*\"]', '2026-09-10 04:15:09', NULL, '2026-09-10 04:14:38', '2026-09-10 04:15:09'),
(69, 'App\\Models\\User', 16, 'customer', 'f9bf02190ff06ae73e68cd40c37b4b3511d3e05e52cebde48dce0a5fe1280c7d', '[\"*\"]', '2026-09-10 05:14:13', NULL, '2026-09-10 05:08:28', '2026-09-10 05:14:13'),
(70, 'App\\Models\\User', 17, 'customer', '6781b764e032a764801929595cda7fe6b1de45a6fdcfd773b7281db970318bdf', '[\"*\"]', '2026-09-10 05:32:21', NULL, '2026-09-10 05:14:33', '2026-09-10 05:32:21'),
(74, 'App\\Models\\User', 17, 'customer', '48ba6cefda5815400870c6b205e3c7ac07d4738b4300694a190e4a971ca22219', '[\"*\"]', NULL, NULL, '2026-09-10 06:13:59', '2026-09-10 06:13:59'),
(75, 'App\\Models\\User', 16, 'customer', 'e445f8bbbb737c10ab372c11b9ceea0b67f349c956b29925ae7a8b29449fad06', '[\"*\"]', '2026-09-10 07:15:25', NULL, '2026-09-10 06:14:05', '2026-09-10 07:15:25'),
(77, 'App\\Models\\User', 17, 'customer', '3b46ad6af609da2ae084e17ee249ecc7b34a998898e44c3e7eac5f9e5bd7a766', '[\"*\"]', '2026-09-10 07:26:23', NULL, '2026-09-10 07:25:36', '2026-09-10 07:26:23'),
(79, 'App\\Models\\User', 16, 'customer', '14bbf59e5a3bd59f7ffd4518e6bb28d5567757cef76b0f6da9cd72ef28e188d6', '[\"*\"]', '2026-09-10 07:53:04', NULL, '2026-09-10 07:29:09', '2026-09-10 07:53:04'),
(81, 'App\\Models\\User', 17, 'customer', '4ce885de3957ceefec8c484e45cffe46d17dc8923275c67436224c90894787f9', '[\"*\"]', '2026-09-10 07:40:28', NULL, '2026-09-10 07:39:58', '2026-09-10 07:40:28'),
(86, 'App\\Models\\User', 17, 'customer', '4ef3282f50af047c24856987211ec5b6563303c8a51df8d3f25a7059119683ee', '[\"*\"]', '2026-09-10 23:58:11', NULL, '2026-09-10 23:57:11', '2026-09-10 23:58:11'),
(89, 'App\\Models\\User', 17, 'customer', '70a866475fb4be8b8fece1cd52c7a6768ae0aa38eab4336f0b6a4d7f32047cb5', '[\"*\"]', '2026-09-11 00:01:09', NULL, '2026-09-11 00:00:50', '2026-09-11 00:01:09'),
(91, 'App\\Models\\User', 15, 'cdp-test', '9a6753fc632d81e03bbbe33c7f79ab8556101bd342652ea4454b8a052209ef8c', '[\"*\"]', '2026-09-11 01:03:24', NULL, '2026-09-11 00:01:28', '2026-09-11 01:03:24'),
(93, 'App\\Models\\User', 17, 'customer', '8c0723fd185e590338f35e2fb5fa9b515c6ed663c8ee8e634b2d796ed6561a04', '[\"*\"]', '2026-09-11 00:04:15', NULL, '2026-09-11 00:03:50', '2026-09-11 00:04:15'),
(95, 'App\\Models\\User', 16, 'customer', '31bd5b2dc28798a67ff91b05d67a988b76248597314026e893c02e77dc1741d3', '[\"*\"]', '2026-09-11 00:23:39', NULL, '2026-09-11 00:07:52', '2026-09-11 00:23:39'),
(99, 'App\\Models\\User', 17, 'customer', '74f468f217836aa5ae7cf4bf06aaade258fc5fe66f040308c03f9ca5bf7651c2', '[\"*\"]', '2026-09-11 00:19:10', NULL, '2026-09-11 00:15:16', '2026-09-11 00:19:10'),
(103, 'App\\Models\\User', 16, 'customer', 'f0f419a7af19499b254a6d064e96a2f769629def420cd7f67d76dec45867da34', '[\"*\"]', '2026-09-11 00:26:54', NULL, '2026-09-11 00:26:23', '2026-09-11 00:26:54'),
(107, 'App\\Models\\User', 17, 'customer', '553e227fbd8943e6266c23fb7d38f54c06c6072f9a5a479af80cd5ae7f47e38a', '[\"*\"]', '2026-09-11 02:18:07', NULL, '2026-09-11 01:47:55', '2026-09-11 02:18:07'),
(108, 'App\\Models\\User', 17, 'cdp-test', '4861f17f8d77f978099d49c169f4dbf730fb157c5f8009a9f231b794ba434ea0', '[\"*\"]', '2026-09-11 02:03:23', NULL, '2026-09-11 01:56:17', '2026-09-11 02:03:23'),
(110, 'App\\Models\\User', 16, 'customer', '28888caeb76891d5d7b53b16d8184395faeee8b54dc683c305be55af0f737b28', '[\"*\"]', '2026-09-11 02:23:04', NULL, '2026-09-11 02:18:41', '2026-09-11 02:23:04'),
(112, 'App\\Models\\User', 16, 'customer', '22f58c89c896972fe00965585c56655a2a67fde5403e6f4728f27940cff4e774', '[\"*\"]', '2026-09-11 02:26:07', NULL, '2026-09-11 02:23:13', '2026-09-11 02:26:07'),
(117, 'App\\Models\\User', 16, 'customer', '0c388c1f5cfd06360967c47b4e98997015dd1998a9e96ab768cc5c9d7ed81fb0', '[\"*\"]', '2026-09-11 02:44:19', NULL, '2026-09-11 02:43:17', '2026-09-11 02:44:19'),
(119, 'App\\Models\\User', 16, 'customer', '25520cd133c831d163f97602943d00b91c37e7cf0366287cf0e167ad45c0466b', '[\"*\"]', '2026-09-11 02:45:39', NULL, '2026-09-11 02:44:26', '2026-09-11 02:45:39'),
(121, 'App\\Models\\User', 17, 'customer', 'bb5939640d05c45ff07dc2ccf64e8a7a0972534aecc3581e5f1045cc9e750950', '[\"*\"]', '2026-09-11 02:52:15', NULL, '2026-09-11 02:51:45', '2026-09-11 02:52:15'),
(126, 'App\\Models\\User', 16, 'customer', '2c629f2bede71d18b92b734eb96ce35966194c4c741052b3ad08f5e0c4a0c018', '[\"*\"]', '2026-09-11 03:48:06', NULL, '2026-09-11 03:47:46', '2026-09-11 03:48:06'),
(131, 'App\\Models\\User', 17, 'customer', '974661dc0e69cfcf17ff88acdf997a58d244c9981971111e825c8f6c1ae7d628', '[\"*\"]', '2026-09-11 04:35:12', NULL, '2026-09-11 04:34:59', '2026-09-11 04:35:12'),
(133, 'App\\Models\\User', 16, 'customer', '118b44c45e7d6bda50cc01b4ca98b3328173cf96d34b10371f955f77d04d4a81', '[\"*\"]', '2026-09-11 05:19:32', NULL, '2026-09-11 04:41:30', '2026-09-11 05:19:32'),
(145, 'App\\Models\\User', 17, 'customer', '6a7627c7ab0b89a2ae1264fff32b48b28d2c3e25d7c4409ed3d654b75421e9f5', '[\"*\"]', '2026-09-11 07:36:03', NULL, '2026-09-11 07:30:49', '2026-09-11 07:36:03'),
(147, 'App\\Models\\User', 16, 'customer', '71aad9a79dabcb0d72947f46a1fbca9b098074322f975dc99fb2cd2ea81d6c8f', '[\"*\"]', '2026-09-11 07:37:03', NULL, '2026-09-11 07:36:42', '2026-09-11 07:37:03'),
(158, 'App\\Models\\User', 17, 'customer', 'f166a90c43a69c53ee8dbbadab9b2885f00e1de8af6a1a035f31ead7ee2d177d', '[\"*\"]', '2026-09-14 06:26:19', NULL, '2026-09-14 06:13:56', '2026-09-14 06:26:19'),
(160, 'App\\Models\\User', 16, 'customer', '53c2369b82e6e7e328fd7a7739e51d50d354de10c7b6de5f443bcc399c6a34c6', '[\"*\"]', '2026-09-14 06:27:01', NULL, '2026-09-14 06:26:46', '2026-09-14 06:27:01'),
(162, 'App\\Models\\User', 16, 'customer', 'a657005f08a34d1e4a72f95b06d8749344c16cb8d19b912eb8f3ec338d72a7a2', '[\"*\"]', '2026-09-14 06:28:00', NULL, '2026-09-14 06:27:35', '2026-09-14 06:28:00'),
(181, 'App\\Models\\User', 16, 'customer', 'd579f8f78aecef9abcc8b20f53535e144a14a5e776bc7b2176068dfa683a6625', '[\"*\"]', '2026-09-14 23:41:04', NULL, '2026-09-14 23:26:20', '2026-09-14 23:41:04'),
(191, 'App\\Models\\User', 17, 'customer', 'e754f590f0f65a4e1e932918afad3f2860734f4724d91fc27cc252546952c47b', '[\"*\"]', '2026-09-21 02:56:55', NULL, '2026-09-21 02:27:13', '2026-09-21 02:56:55'),
(193, 'App\\Models\\User', 17, 'customer', '75d9c3970c79ac1c9930fdf317686198e632d03ef9caf96327ff4ba12dea6401', '[\"*\"]', '2026-09-21 23:12:10', NULL, '2026-09-21 03:55:46', '2026-09-21 23:12:10'),
(195, 'App\\Models\\User', 31, 'customer', 'f60429f5cc0b96c3fbeaed32fe0ff5bbd71490261f3699e9107d4af1601b5240', '[\"*\"]', '2026-09-22 01:10:19', NULL, '2026-09-22 01:10:17', '2026-09-22 01:10:19'),
(197, 'App\\Models\\User', 32, 'customer', '9618f7f87924a6953cc5ab6833e7bb280734089ed6d2ea7651ac53a4fe53b6ff', '[\"*\"]', '2026-09-22 01:11:19', NULL, '2026-09-22 01:11:10', '2026-09-22 01:11:19'),
(201, 'App\\Models\\User', 33, 'customer', '2c38c1fa0f6668a02ef34ef5ba90b1f77bd7ef867d1e3b4f6990c975fa4c8f93', '[\"*\"]', '2026-09-22 01:14:52', NULL, '2026-09-22 01:14:50', '2026-09-22 01:14:52'),
(202, 'App\\Models\\User', 34, 'customer', 'aa4eb8cca0a46de56ebd1029bad0417f86f55de2707f046d2d5501aadb17e45b', '[\"*\"]', '2026-09-22 01:15:45', NULL, '2026-09-22 01:15:39', '2026-09-22 01:15:45'),
(204, 'App\\Models\\User', 34, 'customer', 'e63e5a09223afe2fb8bd75f3c908ee40dc4f35ea01f979751fbfc1107f1fa144', '[\"*\"]', '2026-09-22 01:16:02', NULL, '2026-09-22 01:16:00', '2026-09-22 01:16:02'),
(206, 'App\\Models\\User', 35, 't', 'ee88f548f78944cf055f83d9935917bfffd01787f0ae65d0806d1424b02f1712', '[\"*\"]', '2026-09-22 01:18:33', NULL, '2026-09-22 01:18:15', '2026-09-22 01:18:33'),
(208, 'App\\Models\\User', 15, 't', 'a9435433ab2374768fc1e4372fe94fd9cac6163bf26c1995643221b90cc2da41', '[\"*\"]', '2026-09-22 01:21:17', NULL, '2026-09-22 01:21:13', '2026-09-22 01:21:17'),
(239, 'App\\Models\\User', 15, 'customer', 'f846184b5de79a10accd930d9b9d25341abe756d5e2f74ad1e62db0558a1050a', '[\"*\"]', '2026-09-22 06:03:37', NULL, '2026-09-22 02:39:13', '2026-09-22 06:03:37'),
(240, 'App\\Models\\User', 15, 'customer', '68940709a4753fae91de4656a4fe4c8e3d1bd4b6d086aa3b344b7218be14a7d3', '[\"*\"]', '2026-09-22 04:38:14', NULL, '2026-09-22 04:38:11', '2026-09-22 04:38:14'),
(241, 'App\\Models\\User', 15, 'customer', 'd2f2986d0ccb009bade61b2f2ea86ed51227a0c48b7c843f75610ecaec449502', '[\"*\"]', '2026-09-22 04:38:34', NULL, '2026-09-22 04:38:31', '2026-09-22 04:38:34'),
(242, 'App\\Models\\User', 15, 'customer', 'c8e03d40c0e6e73c0cadd927d153f16ccdf5dfa4ec5ffd01659f1bc435fd0a36', '[\"*\"]', '2026-09-22 04:38:53', NULL, '2026-09-22 04:38:48', '2026-09-22 04:38:53'),
(243, 'App\\Models\\User', 15, 'customer', '011cb506a1339cc1529317b317f086ea818587f1af1149c0cf769bf0c32feed6', '[\"*\"]', '2026-09-22 04:39:40', NULL, '2026-09-22 04:39:35', '2026-09-22 04:39:40'),
(244, 'App\\Models\\User', 39, 'customer', '8788ef2a68dee73a13a7f4c1ac6398199062d164430ca2d4b45f80f96762ecbf', '[\"*\"]', NULL, NULL, '2026-09-22 04:42:02', '2026-09-22 04:42:02'),
(245, 'App\\Models\\User', 39, 'customer', 'ad67616d00c3ec9d21af6b5dd3e7c0c76789d217b56cd2b2fdeb01ae4f110b0d', '[\"*\"]', '2026-09-22 04:42:31', NULL, '2026-09-22 04:42:18', '2026-09-22 04:42:31'),
(246, 'App\\Models\\User', 39, 'customer', '1f1beb0b7045073d04f74ee9eb815a9efda0db9acb7f2deb9f19a30f27c8bc3e', '[\"*\"]', '2026-09-22 04:43:44', NULL, '2026-09-22 04:43:26', '2026-09-22 04:43:44'),
(247, 'App\\Models\\User', 15, 'customer', 'c9745089038fff8a9f54dd3fa64611da86b8c4ebe80326b0fbd22b7797af8cc3', '[\"*\"]', '2026-09-22 04:44:27', NULL, '2026-09-22 04:44:22', '2026-09-22 04:44:27'),
(248, 'App\\Models\\User', 40, 'customer', '0ac96143d3185482259b751c9cbfdd16ff6b7fabe0ab9b18223b2393d6609580', '[\"*\"]', '2026-09-22 04:44:56', NULL, '2026-09-22 04:44:40', '2026-09-22 04:44:56'),
(249, 'App\\Models\\User', 41, 'customer', 'd32a0a5a3e8b5d5698716436c4d216d452f57a2f2478ecd82b16431d3089bd3a', '[\"*\"]', NULL, NULL, '2026-09-22 04:57:08', '2026-09-22 04:57:08'),
(250, 'App\\Models\\User', 15, 'customer', 'c8043815400927ffcddea08a6ec70a405abe166daaad0a2122fa8dedbe1af683', '[\"*\"]', '2026-09-22 05:07:00', NULL, '2026-09-22 05:07:00', '2026-09-22 05:07:00'),
(251, 'App\\Models\\User', 43, 'customer', 'fa39f9d815f974353ab03fabb152a69decec4f2f69795c2e7c2cc308b383a882', '[\"*\"]', '2026-09-22 05:07:25', NULL, '2026-09-22 05:07:25', '2026-09-22 05:07:25'),
(252, 'App\\Models\\User', 43, 'customer', '2411096461043553d81812a23556f42ddc5000d79d4b0b167f0a495acd84a4a0', '[\"*\"]', '2026-09-22 05:07:45', NULL, '2026-09-22 05:07:45', '2026-09-22 05:07:45'),
(253, 'App\\Models\\User', 15, 'customer', 'c8f09e864ece2805d7e38402ff9013c92c98589ca55ed65657aa7119ecf5ac04', '[\"*\"]', '2026-09-22 05:32:20', NULL, '2026-09-22 05:32:19', '2026-09-22 05:32:20'),
(254, 'App\\Models\\User', 15, 'customer', '95a8b9cfdfaae35fdb8c8fa9b0c9807e173f6915b3bea5ba9d2d945c5d1ae2a4', '[\"*\"]', '2026-09-22 05:32:48', NULL, '2026-09-22 05:32:46', '2026-09-22 05:32:48'),
(255, 'App\\Models\\User', 15, 'customer', '61d483549774eea4dd9ef311aa7ac5b169b5e83b39c210c2f731f2766ca0dbeb', '[\"*\"]', '2026-09-22 05:33:05', NULL, '2026-09-22 05:33:05', '2026-09-22 05:33:05'),
(259, 'App\\Models\\User', 15, 'customer', 'fea61a5953d7e23460206379d245cbbd474a6a6ae7d389b5cbdcc8c7ce29c949', '[\"*\"]', '2026-09-22 05:53:08', NULL, '2026-09-22 05:53:04', '2026-09-22 05:53:08'),
(261, 'App\\Models\\User', 15, 'customer', '806341d865e5edf519402f88156890d34c399e1451dc3d483d21a82a43b9af5e', '[\"*\"]', '2026-09-22 05:53:37', NULL, '2026-09-22 05:53:31', '2026-09-22 05:53:37'),
(262, 'App\\Models\\User', 41, 'customer', '5b12b278ac75ba6622af6e361a2f815abf25d27bb650353c4d2e09f57f0e42f0', '[\"*\"]', '2026-09-22 05:55:04', NULL, '2026-09-22 05:54:02', '2026-09-22 05:55:04'),
(263, 'App\\Models\\User', 41, 'customer', '537cd622022122f87995e8d13dcf3539cf84e3805a2033b2ac737fad76fcfe04', '[\"*\"]', '2026-09-22 05:55:53', NULL, '2026-09-22 05:55:21', '2026-09-22 05:55:53'),
(265, 'App\\Models\\User', 41, 'customer', 'e6684474198dcee56ff98c6cad4460c6fe0272b7c82fd76041a2f2c8697d6a50', '[\"*\"]', '2026-09-22 05:56:47', NULL, '2026-09-22 05:56:35', '2026-09-22 05:56:47'),
(266, 'App\\Models\\User', 41, 'customer', 'af7a84c8c392826035bc2c0ec892a78f36f5ac02c077aa48f68b2ebb2de36c6b', '[\"*\"]', '2026-09-22 05:57:22', NULL, '2026-09-22 05:57:08', '2026-09-22 05:57:22'),
(267, 'App\\Models\\User', 41, 'customer', 'a1adf3c8106c852dd0436db5a9a35cf2f026af3fbf5db1009ea53d45c0499f3e', '[\"*\"]', '2026-09-22 05:58:15', NULL, '2026-09-22 05:58:04', '2026-09-22 05:58:15'),
(269, 'App\\Models\\User', 41, 'customer', '52eeb61b042fdc7da0249eef9b2239ca2eab95a47d1d54e455bf0519a36757c4', '[\"*\"]', '2026-09-22 06:03:47', NULL, '2026-09-22 06:03:46', '2026-09-22 06:03:47'),
(270, 'App\\Models\\User', 41, 'customer', 'eabf86da403d8a4426577fec14fe3bf1eb1b825cd2cb32629d29cfd85e084c2c', '[\"*\"]', '2026-09-22 06:04:07', NULL, '2026-09-22 06:04:07', '2026-09-22 06:04:07'),
(271, 'App\\Models\\User', 41, 'customer', 'e5230589c4e681fb1cc7947981622127d0567d4e02a77762dd8c18eaa0bcedbf', '[\"*\"]', '2026-09-22 06:04:18', NULL, '2026-09-22 06:04:18', '2026-09-22 06:04:18'),
(272, 'App\\Models\\User', 41, 'customer', '8481eec3635f3f840beea9d5df8410cc761cf44ed463af099e581e6fad5e5375', '[\"*\"]', NULL, NULL, '2026-09-22 06:04:33', '2026-09-22 06:04:33'),
(273, 'App\\Models\\User', 41, 'customer', '42af364518540b6fae5bb24e8d1f9be84c388567df8a6ad64fcb4c32bd136a60', '[\"*\"]', '2026-09-22 06:05:41', NULL, '2026-09-22 06:05:41', '2026-09-22 06:05:41'),
(274, 'App\\Models\\User', 41, 'customer', 'c2d4dc8f17d56f67726f3ce27ddbc63ce5a70b01e8decfb711407059ba2621fe', '[\"*\"]', '2026-09-22 06:06:09', NULL, '2026-09-22 06:06:09', '2026-09-22 06:06:09'),
(275, 'App\\Models\\User', 15, 'customer', '91e978657e6b553f593882482f6945a50e40a201552a214ba0c114ac41adc19d', '[\"*\"]', '2026-09-23 00:09:37', NULL, '2026-09-22 23:56:37', '2026-09-23 00:09:37'),
(277, 'App\\Models\\User', 15, 'customer', '99f83db171fa0a75a3aec8943b6cec5527265022e57f1d9a252f40ef7606f4f1', '[\"*\"]', '2026-09-23 00:18:10', NULL, '2026-09-23 00:17:19', '2026-09-23 00:18:10'),
(279, 'App\\Models\\User', 15, 'customer', 'f2a453ef20ffabd006ea1e58a8a179c2268012ec290b7b2edbc5a6823552fdca', '[\"*\"]', '2026-09-25 04:37:03', NULL, '2026-09-23 00:19:32', '2026-09-25 04:37:03'),
(280, 'App\\Models\\User', 41, 'customer', '45288de6c4a25a233e26aedc52c16ac055a8d160ce81eef4de3f79d3fd7b5291', '[\"*\"]', '2026-09-23 00:50:21', NULL, '2026-09-23 00:20:39', '2026-09-23 00:50:21'),
(281, 'App\\Models\\User', 15, 'customer', '5d7f44106c91e9fc6a42f9dc5dd5b0f16468adfda1dbcbf3254f997adb65724b', '[\"*\"]', '2026-09-23 00:50:27', NULL, '2026-09-23 00:25:29', '2026-09-23 00:50:27'),
(283, 'App\\Models\\User', 17, 'customer', '1b58d9e586b5d645e8c01b2ebbe4038311db7ef71989a46899fe526c6a9c4182', '[\"*\"]', '2026-09-23 01:28:02', NULL, '2026-09-23 01:17:54', '2026-09-23 01:28:02'),
(284, 'App\\Models\\User', 41, 'customer', '5b077fda93d8ff0149e0a8cf2414292efba0ca45b0364325327c57d83f82aa6b', '[\"*\"]', '2026-09-23 05:52:27', NULL, '2026-09-23 01:28:06', '2026-09-23 05:52:27'),
(285, 'App\\Models\\User', 41, 'customer', '541e7edfdd2658454ac04da2fa1ef5d77070656667fbcd91ece880beb8aeb804', '[\"*\"]', NULL, NULL, '2026-09-23 07:34:06', '2026-09-23 07:34:06'),
(286, 'App\\Models\\User', 41, 'customer', '788bf5d1d153a04cfc12c80aac9f9a06d082755d94e0227b152f901c7aa7acf9', '[\"*\"]', NULL, NULL, '2026-09-23 07:34:10', '2026-09-23 07:34:10'),
(287, 'App\\Models\\User', 41, 'customer', 'c175af260b5e521c8f8732cd925fad3feb12e3a4a908c2a229094e8cbf7b56ff', '[\"*\"]', NULL, NULL, '2026-09-23 07:34:16', '2026-09-23 07:34:16'),
(288, 'App\\Models\\User', 41, 'customer', '89b4d7018a1e1703592ca13ec6c3853eacd08675f8ce5b690b3651c68865d817', '[\"*\"]', '2026-09-23 07:34:23', NULL, '2026-09-23 07:34:23', '2026-09-23 07:34:23'),
(289, 'App\\Models\\User', 41, 'customer', '067cb689cefebd752818ac5757f9e7d48ed0f4881c9aed5451559cd84704d47f', '[\"*\"]', '2026-09-23 07:34:50', NULL, '2026-09-23 07:34:50', '2026-09-23 07:34:50'),
(290, 'App\\Models\\User', 15, 'customer', '9b8d9429135711cce9ed5c8fd05c46ccd98b8f6b6479717c651a2d492fba1189', '[\"*\"]', '2026-09-23 23:31:00', NULL, '2026-09-23 23:03:11', '2026-09-23 23:31:00'),
(291, 'App\\Models\\User', 41, 'customer', '56677215d241d0242a67c318edd9f5ba56e4741972192532a2b64bf5c568a31f', '[\"*\"]', '2026-09-23 23:50:14', NULL, '2026-09-23 23:06:59', '2026-09-23 23:50:14'),
(292, 'App\\Models\\User', 16, 'customer', '63a1c13bd2c63c8267cfa15698ae27e846dc0bce372b859eaeed2f26c66a5450', '[\"*\"]', '2026-09-23 23:51:07', NULL, '2026-09-23 23:50:19', '2026-09-23 23:51:07'),
(294, 'App\\Models\\User', 17, 'customer', '2f07ad50ba15b44c49f174678aab1d2c099acfa00a58b49ee3389e9ed2e67090', '[\"*\"]', '2026-09-24 01:03:33', NULL, '2026-09-24 00:57:54', '2026-09-24 01:03:33'),
(296, 'App\\Models\\User', 17, 'customer', '84fd6d3b24e33aef2e007d0f80c1368b247b01223768ba0c474408dfeefa2d32', '[\"*\"]', '2026-09-24 01:19:31', NULL, '2026-09-24 01:13:13', '2026-09-24 01:19:31'),
(298, 'App\\Models\\User', 17, 'customer', '1f01168d1136b8498ff70121d931b3207207526a54ec7f13fb724490ac98712a', '[\"*\"]', '2026-09-24 05:08:32', NULL, '2026-09-24 01:29:53', '2026-09-24 05:08:32'),
(299, 'App\\Models\\User', 41, 'customer', 'b5177d25ecb31650e802e4029179fb349279269b7130bb29aca4eceb37b24b1f', '[\"*\"]', '2026-09-25 00:04:02', NULL, '2026-09-24 05:08:37', '2026-09-25 00:04:02'),
(312, 'App\\Models\\User', 15, 'customer', '33e3532a37711b788462da10e89b3e4337c91ad3ce006178d6af6587e2d0901f', '[\"*\"]', '2026-09-25 05:37:25', NULL, '2026-09-25 04:51:26', '2026-09-25 05:37:25');

-- --------------------------------------------------------

--
-- Table structure for table `price_change_records`
--

CREATE TABLE `price_change_records` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `product_variant_id` bigint(20) UNSIGNED DEFAULT NULL,
  `old_price_cents` int(10) UNSIGNED NOT NULL,
  `new_price_cents` int(10) UNSIGNED NOT NULL,
  `source` varchar(20) NOT NULL DEFAULT 'sales_boost',
  `sales_boost_offer_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `category_id` bigint(20) UNSIGNED DEFAULT NULL,
  `shop_id` bigint(20) UNSIGNED DEFAULT NULL,
  `trademark_id` bigint(20) UNSIGNED DEFAULT NULL,
  `market` varchar(2) NOT NULL DEFAULT 'US',
  `status` varchar(20) NOT NULL DEFAULT 'approved',
  `rejection_reason` text DEFAULT NULL,
  `suggested_category_name` varchar(160) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `bullet_points` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`bullet_points`)),
  `detail_images` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`detail_images`)),
  `product_details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`product_details`)),
  `variation_theme` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`variation_theme`)),
  `size_chart` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`size_chart`)),
  `sku` varchar(255) NOT NULL,
  `seller_code` varchar(60) DEFAULT NULL,
  `hsn_code` varchar(8) DEFAULT NULL,
  `gst_rate_bps` smallint(5) UNSIGNED DEFAULT NULL,
  `country_of_origin` varchar(60) DEFAULT NULL,
  `manufacturer_info` varchar(500) DEFAULT NULL,
  `compliance` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`compliance`)),
  `next_variant_seq` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `price_cents` int(10) UNSIGNED NOT NULL,
  `compare_at_price_cents` int(10) UNSIGNED DEFAULT NULL,
  `price_references` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`price_references`)),
  `return_days` smallint(5) UNSIGNED DEFAULT NULL,
  `shipping_template_id` bigint(20) UNSIGNED DEFAULT NULL,
  `handling_days` tinyint(3) UNSIGNED DEFAULT NULL,
  `inventory_quantity` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `image_url` varchar(255) DEFAULT NULL,
  `video_url` varchar(500) DEFAULT NULL,
  `detail_video_url` varchar(500) DEFAULT NULL,
  `rating_avg` decimal(3,2) DEFAULT NULL,
  `rating_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `units_sold` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deal_type` varchar(255) DEFAULT NULL,
  `is_exclusive_offer` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `category_id`, `shop_id`, `trademark_id`, `market`, `status`, `rejection_reason`, `suggested_category_name`, `name`, `slug`, `description`, `bullet_points`, `detail_images`, `product_details`, `variation_theme`, `size_chart`, `sku`, `seller_code`, `hsn_code`, `gst_rate_bps`, `country_of_origin`, `manufacturer_info`, `compliance`, `next_variant_seq`, `price_cents`, `compare_at_price_cents`, `price_references`, `return_days`, `shipping_template_id`, `handling_days`, `inventory_quantity`, `image_url`, `video_url`, `detail_video_url`, `rating_avg`, `rating_count`, `units_sold`, `is_active`, `deal_type`, `is_exclusive_offer`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, NULL, 'US', 'approved', NULL, NULL, 'Apple iPhone 15 Pro', 'apple-iphone-15-pro', 'Apple\'s titanium-body flagship with the A17 Pro chip, a 48MP main camera, and USB-C — built for all-day performance in the pocket.', NULL, NULL, NULL, NULL, NULL, 'ADM000001', NULL, NULL, NULL, NULL, NULL, NULL, 3, 99900, NULL, NULL, NULL, NULL, NULL, 98, '/img/products/prod-apple-iphone-15-pro.jpg', NULL, NULL, 3.81, 239, 57, 1, 'lightning', 0, '2026-09-16 11:31:22', '2026-09-22 05:07:55'),
(2, 1, NULL, NULL, 'US', 'approved', NULL, NULL, 'Samsung Galaxy S24', 'samsung-galaxy-s24', 'A compact Android flagship with a bright Dynamic AMOLED display, Snapdragon power, and Galaxy AI features built in.', NULL, NULL, NULL, NULL, NULL, 'ADM000002', NULL, NULL, NULL, NULL, NULL, NULL, 2, 79900, NULL, NULL, NULL, NULL, NULL, 99, '/img/products/prod-samsung-galaxy-s24.jpg', NULL, NULL, 4.10, 635, 9572, 1, 'lightning', 0, '2025-11-22 05:17:22', '2026-09-21 06:30:22'),
(3, 1, NULL, NULL, 'US', 'approved', NULL, NULL, 'Google Pixel 8', 'google-pixel-8', 'Google\'s pure-Android phone with the Tensor G3 chip and a camera tuned for standout low-light and portrait shots.', NULL, NULL, NULL, NULL, NULL, 'ADM000003', NULL, NULL, NULL, NULL, NULL, NULL, 1, 69900, 74900, NULL, NULL, NULL, NULL, 100, '/img/products/prod-google-pixel-8.jpg', NULL, NULL, 3.66, 439, 1234, 1, 'lightning', 0, '2026-03-26 21:52:22', '2026-09-21 06:30:22'),
(4, 1, NULL, NULL, 'US', 'approved', NULL, NULL, 'OnePlus 12', 'oneplus-12', 'A fast, fluid flagship with Hasselblad-tuned cameras and 100W charging that tops up the battery in minutes.', NULL, NULL, NULL, NULL, NULL, 'ADM000004', NULL, NULL, NULL, NULL, NULL, NULL, 1, 73900, NULL, NULL, NULL, NULL, NULL, 100, '/img/products/prod-oneplus-12.jpg', '/videos/demo-smartphone-hands.mp4', NULL, 4.27, 420, 575, 1, 'unbeatable', 0, '2025-12-12 21:03:22', '2026-09-21 06:30:22'),
(5, 1, NULL, NULL, 'US', 'approved', NULL, NULL, 'Xiaomi 14', 'xiaomi-14', 'A pocketable flagship with Leica optics and flagship-tier Snapdragon performance at a sharp price.', NULL, NULL, NULL, NULL, NULL, 'ADM000005', NULL, NULL, NULL, NULL, NULL, NULL, 1, 64900, NULL, NULL, NULL, NULL, NULL, 98, '/img/products/prod-xiaomi-14.jpg', NULL, NULL, 3.88, 193, 1589, 1, NULL, 0, '2026-07-01 11:49:22', '2026-09-22 02:31:02'),
(6, 2, NULL, NULL, 'US', 'approved', NULL, NULL, 'Apple MacBook Air M3', 'apple-macbook-air-m3', 'Apple\'s fanless, all-day laptop — the M3 chip handles everyday work and creative apps without breaking a sweat.', NULL, NULL, NULL, NULL, NULL, 'ADM000006', NULL, NULL, NULL, NULL, NULL, NULL, 2, 109900, NULL, NULL, NULL, NULL, NULL, 80, '/img/products/prod-apple-macbook-air-m3.jpg', NULL, NULL, 4.55, 199, 4133, 1, 'unbeatable', 1, '2025-12-07 07:03:22', '2026-09-21 06:30:22'),
(7, 2, NULL, NULL, 'US', 'approved', NULL, NULL, 'Dell XPS 13', 'dell-xps-13', 'A compact ultrabook with an edge-to-edge InfinityEdge display, built for work on the go.', NULL, NULL, NULL, NULL, NULL, 'ADM000007', NULL, NULL, NULL, NULL, NULL, NULL, 4, 99900, NULL, NULL, NULL, NULL, NULL, 100, '/img/products/prod-dell-xps-13.png', NULL, NULL, 3.96, 517, 460, 1, 'unbeatable', 0, '2026-07-23 15:06:22', '2026-09-21 06:30:22'),
(8, 2, NULL, NULL, 'US', 'approved', NULL, NULL, 'HP Spectre x360', 'hp-spectre-x360', 'A convertible 2-in-1 with a gem-cut design that folds flat into tablet mode for sketching, notes, or streaming.', NULL, NULL, NULL, NULL, NULL, 'ADM000008', NULL, NULL, NULL, NULL, NULL, NULL, 2, 129900, NULL, NULL, NULL, NULL, NULL, 91, '/img/products/prod-hp-spectre-x360.png', NULL, NULL, 4.32, 114, 2624, 1, 'unbeatable', 0, '2026-06-25 20:18:22', '2026-09-21 06:30:22'),
(9, 2, NULL, NULL, 'US', 'approved', NULL, NULL, 'Lenovo ThinkPad X1 Carbon', 'lenovo-thinkpad-x1-carbon', 'The business standard: a carbon-fibre chassis, legendary keyboard, and MIL-SPEC durability.', NULL, NULL, NULL, NULL, NULL, 'ADM000009', NULL, NULL, NULL, NULL, NULL, NULL, 1, 159900, NULL, NULL, NULL, NULL, NULL, 89, '/img/products/prod-lenovo-thinkpad-x1-carbon.jpg', NULL, NULL, NULL, 0, 6998, 1, 'lightning', 0, '2026-02-17 08:20:22', '2026-09-21 06:30:22'),
(10, 2, NULL, NULL, 'US', 'approved', NULL, NULL, 'Asus ROG Zephyrus G14', 'asus-rog-zephyrus-g14', 'A compact gaming laptop that punches well above its size, with enough GPU power for the latest titles.', NULL, NULL, NULL, NULL, NULL, 'ADM000010', NULL, NULL, NULL, NULL, NULL, NULL, 1, 179900, NULL, NULL, NULL, NULL, NULL, 98, '/img/products/prod-asus-rog-zephyrus-g14.jpg', NULL, NULL, 4.42, 118, 581, 1, NULL, 0, '2026-08-04 07:58:22', '2026-09-21 06:30:22'),
(11, 3, NULL, NULL, 'US', 'approved', NULL, NULL, 'Sony WH-1000XM5', 'sony-wh-1000xm5', 'Industry-leading noise cancellation and all-day comfort, tuned for travel and focus.', NULL, NULL, NULL, NULL, NULL, 'ADM000011', NULL, NULL, NULL, NULL, NULL, NULL, 3, 34900, NULL, NULL, NULL, NULL, NULL, 99, '/img/products/prod-sony-wh-1000xm5.jpg', NULL, NULL, 3.86, 428, 4104, 1, 'lightning', 0, '2026-04-02 23:24:22', '2026-09-21 06:30:22'),
(12, 3, NULL, NULL, 'US', 'approved', NULL, NULL, 'Apple AirPods Pro 2', 'apple-airpods-pro-2', 'Adaptive noise cancellation, Transparency mode, and spatial audio in Apple\'s smallest true wireless earbuds.', NULL, NULL, NULL, NULL, NULL, 'ADM000012', NULL, NULL, NULL, NULL, NULL, NULL, 1, 24900, NULL, NULL, NULL, NULL, NULL, 99, '/img/products/prod-apple-airpods-pro-2.jpg', NULL, NULL, 4.74, 157, 11314, 1, 'lightning', 0, '2025-10-02 01:26:22', '2026-09-21 06:30:22'),
(13, 3, NULL, NULL, 'US', 'approved', NULL, NULL, 'Bose QuietComfort Ultra', 'bose-quietcomfort-ultra', 'Bose\'s quietest headphones yet, with immersive spatial audio and plush all-day comfort.', NULL, NULL, NULL, NULL, NULL, 'ADM000013', NULL, NULL, NULL, NULL, NULL, NULL, 1, 42900, NULL, NULL, NULL, NULL, NULL, 100, '/img/products/prod-bose-quietcomfort-ultra.jpg', NULL, NULL, 3.59, 576, 1693, 1, 'unbeatable', 0, '2026-04-02 09:44:22', '2026-09-21 06:30:22'),
(14, 3, NULL, NULL, 'US', 'approved', NULL, NULL, 'JBL Flip 6 Speaker', 'jbl-flip-6-speaker', 'A rugged, waterproof Bluetooth speaker with punchy JBL sound for the beach, the shower, or the backyard.', NULL, NULL, NULL, NULL, NULL, 'ADM000014', NULL, NULL, NULL, NULL, NULL, NULL, 1, 12900, NULL, NULL, NULL, NULL, NULL, 99, '/img/products/prod-jbl-flip-6-speaker.jpg', NULL, NULL, 3.66, 313, 2069, 1, NULL, 0, '2026-06-03 04:25:22', '2026-09-21 06:30:22'),
(15, 3, NULL, NULL, 'US', 'approved', NULL, NULL, 'Sennheiser Momentum 4', 'sennheiser-momentum-4', 'Audiophile-tuned sound with up to 60 hours of battery life on a single charge.', NULL, NULL, NULL, NULL, NULL, 'ADM000015', NULL, NULL, NULL, NULL, NULL, NULL, 1, 34900, NULL, NULL, NULL, NULL, NULL, 99, '/img/products/prod-sennheiser-momentum-4.jpg', NULL, NULL, NULL, 0, 9820, 1, NULL, 0, '2025-10-21 22:51:22', '2026-09-21 06:30:22'),
(16, 4, NULL, NULL, 'US', 'approved', NULL, NULL, 'Tempered Glass Screen Protector', 'tempered-glass-screen-protector', '9H hardness, an oleophobic coating, and edge-to-edge clarity that keeps your screen scratch-free.', NULL, NULL, NULL, NULL, NULL, 'ADM000016', NULL, NULL, NULL, NULL, NULL, NULL, 1, 999, NULL, NULL, NULL, NULL, NULL, 100, '/img/products/prod-tempered-glass-screen-protector.jpg', NULL, NULL, NULL, 0, 1734, 1, 'unbeatable', 0, '2026-04-24 01:25:22', '2026-09-21 06:30:22'),
(17, 4, NULL, NULL, 'US', 'approved', NULL, NULL, 'Silicone Phone Case', 'silicone-phone-case', 'A soft-touch silicone case with a microfibre lining that protects without adding bulk.', NULL, NULL, NULL, NULL, NULL, 'ADM000017', NULL, NULL, NULL, NULL, NULL, NULL, 3, 1499, NULL, NULL, NULL, NULL, NULL, 99, '/img/products/prod-silicone-phone-case.jpg', NULL, NULL, 4.65, 596, 590, 1, 'lightning', 1, '2026-04-21 11:43:22', '2026-09-21 06:30:22'),
(18, 4, NULL, NULL, 'US', 'approved', NULL, NULL, 'MagSafe Wireless Charger', 'magsafe-wireless-charger', 'Snap-on magnetic charging for a clean, cable-free charge every time you set your phone down.', NULL, NULL, NULL, NULL, NULL, 'ADM000018', NULL, NULL, NULL, NULL, NULL, NULL, 1, 3999, NULL, NULL, NULL, NULL, NULL, 100, '/img/products/prod-magsafe-wireless-charger.jpg', NULL, NULL, 4.28, 250, 6915, 1, 'unbeatable', 0, '2026-01-30 15:12:22', '2026-09-21 06:30:22'),
(19, 5, NULL, NULL, 'US', 'approved', NULL, NULL, 'Apple Watch Series 9', 'apple-watch-series-9', 'Apple\'s smartwatch with the new double-tap gesture, a brighter always-on display, and deep health tracking.', NULL, NULL, NULL, NULL, NULL, 'ADM000019', NULL, NULL, NULL, NULL, NULL, NULL, 2, 39900, NULL, NULL, NULL, NULL, NULL, 100, '/img/products/prod-apple-watch-series-9.jpg', NULL, NULL, 4.02, 757, 2096, 1, 'lightning', 0, '2025-10-27 23:02:22', '2026-09-21 06:30:22'),
(20, 5, NULL, NULL, 'US', 'approved', NULL, NULL, 'Samsung Galaxy Watch 6', 'samsung-galaxy-watch-6', 'A sleek Wear OS smartwatch with advanced sleep coaching and body composition tracking.', NULL, NULL, NULL, NULL, NULL, 'ADM000020', NULL, NULL, NULL, NULL, NULL, NULL, 1, 32900, NULL, NULL, NULL, NULL, NULL, 100, '/img/products/prod-samsung-galaxy-watch-6.jpg', NULL, NULL, 3.59, 268, 3710, 1, 'unbeatable', 0, '2026-04-22 13:19:22', '2026-09-21 06:30:22'),
(21, 5, NULL, NULL, 'US', 'approved', NULL, NULL, 'Fitbit Charge 6', 'fitbit-charge-6', 'A slim fitness tracker with built-in GPS, heart-rate tracking, and up to a week of battery life.', NULL, NULL, NULL, NULL, NULL, 'ADM000021', NULL, NULL, NULL, NULL, NULL, NULL, 2, 15900, NULL, NULL, NULL, NULL, NULL, 100, '/img/products/prod-fitbit-charge-6.jpg', NULL, NULL, 4.29, 404, 9396, 1, NULL, 0, '2025-11-08 03:33:22', '2026-09-21 06:30:22'),
(22, 6, NULL, NULL, 'US', 'approved', NULL, NULL, 'Canon EOS R50', 'canon-eos-r50', 'An entry-level mirrorless camera with fast autofocus, ideal for stepping up from a phone camera.', NULL, NULL, NULL, NULL, NULL, 'ADM000022', NULL, NULL, NULL, NULL, NULL, NULL, 1, 79900, NULL, NULL, NULL, NULL, NULL, 100, '/img/products/prod-canon-eos-r50.jpg', NULL, NULL, 4.99, 396, 9564, 1, 'lightning', 0, '2025-09-26 06:51:22', '2026-09-21 06:30:22'),
(23, 6, NULL, NULL, 'US', 'approved', NULL, NULL, 'Sony Alpha ZV-E10', 'sony-alpha-zv-e10', 'A vlogging-focused mirrorless camera with a fully articulating screen and background-defocus mode.', NULL, NULL, NULL, NULL, NULL, 'ADM000023', NULL, NULL, NULL, NULL, NULL, NULL, 1, 69900, NULL, NULL, NULL, NULL, NULL, 100, '/img/products/prod-sony-alpha-zv-e10.jpg', NULL, NULL, 4.60, 511, 2022, 1, 'unbeatable', 0, '2026-02-17 02:49:22', '2026-09-21 06:30:22'),
(24, 6, NULL, NULL, 'US', 'approved', NULL, NULL, 'GoPro Hero 12', 'gopro-hero-12', 'Rugged, waterproof, and stabilized — built to capture action from anywhere.', NULL, NULL, NULL, NULL, NULL, 'ADM000024', NULL, NULL, NULL, NULL, NULL, NULL, 1, 39900, NULL, NULL, NULL, NULL, NULL, 100, '/img/products/prod-gopro-hero-12.jpg', NULL, NULL, 4.64, 35, 2523, 1, NULL, 0, '2026-06-29 14:32:22', '2026-09-21 06:30:22'),
(25, 7, NULL, NULL, 'US', 'approved', NULL, NULL, 'Samsung 55\" QLED TV', 'samsung-55-qled-tv', 'Quantum Dot colour and a wide viewing angle bring movies and sport to life in a 55-inch frame.', NULL, NULL, NULL, NULL, NULL, 'ADM000025', NULL, NULL, NULL, NULL, NULL, NULL, 1, 89900, NULL, NULL, NULL, NULL, NULL, 100, '/img/products/prod-samsung-55-qled-tv.jpg', NULL, NULL, 4.13, 369, 12695, 1, 'lightning', 0, '2025-11-14 11:10:22', '2026-09-21 06:30:22'),
(26, 7, NULL, NULL, 'US', 'approved', NULL, NULL, 'LG 65\" OLED TV', 'lg-65-oled-tv', 'Self-lit OLED pixels deliver perfect blacks and infinite contrast on a 65-inch canvas.', NULL, NULL, NULL, NULL, NULL, 'ADM000026', NULL, NULL, NULL, NULL, NULL, NULL, 1, 179900, NULL, NULL, NULL, NULL, NULL, 100, '/img/products/prod-lg-65-oled-tv.jpg', NULL, NULL, 4.37, 309, 1554, 1, 'unbeatable', 0, '2026-08-16 11:00:22', '2026-09-21 06:30:22'),
(27, 7, NULL, NULL, 'US', 'approved', NULL, NULL, 'Sony 43\" Bravia TV', 'sony-43-bravia-tv', 'Sony\'s processing engine sharpens detail and motion for a crisp, cinematic picture.', NULL, NULL, NULL, NULL, NULL, 'ADM000027', NULL, NULL, NULL, NULL, NULL, NULL, 1, 54900, NULL, NULL, NULL, NULL, NULL, 100, '/img/products/prod-sony-43-bravia-tv.jpg', NULL, NULL, 3.93, 361, 329, 1, NULL, 0, '2026-06-18 11:46:22', '2026-09-21 06:30:22'),
(28, 8, NULL, NULL, 'US', 'approved', NULL, NULL, 'Sony PlayStation 5', 'sony-playstation-5', 'Lightning-fast SSD loading, stunning visuals, and the DualSense controller\'s haptic feedback.', NULL, NULL, NULL, NULL, NULL, 'ADM000028', NULL, NULL, NULL, NULL, NULL, NULL, 2, 49900, NULL, NULL, NULL, NULL, NULL, 100, '/img/products/prod-sony-playstation-5.jpg', NULL, NULL, 3.71, 633, 2512, 1, 'lightning', 0, '2026-05-01 15:13:22', '2026-09-21 06:30:22'),
(29, 8, NULL, NULL, 'US', 'approved', NULL, NULL, 'Microsoft Xbox Series X', 'microsoft-xbox-series-x', 'Microsoft\'s most powerful console, built for 4K gaming at up to 120fps.', NULL, NULL, NULL, NULL, NULL, 'ADM000029', NULL, NULL, NULL, NULL, NULL, NULL, 1, 49900, NULL, NULL, NULL, NULL, NULL, 100, '/img/products/prod-microsoft-xbox-series-x.jpg', NULL, NULL, 3.57, 178, 8273, 1, 'unbeatable', 0, '2025-12-13 20:19:22', '2026-09-21 06:30:22'),
(30, 8, NULL, NULL, 'US', 'approved', NULL, NULL, 'Nintendo Switch OLED', 'nintendo-switch-oled', 'A vivid 7-inch OLED screen makes handheld play pop, and it still docks to the TV in seconds.', NULL, NULL, NULL, NULL, NULL, 'ADM000030', NULL, NULL, NULL, NULL, NULL, NULL, 2, 34900, NULL, NULL, NULL, NULL, NULL, 100, '/img/products/prod-nintendo-switch-oled.jpg', NULL, NULL, 3.91, 260, 4298, 1, NULL, 0, '2025-11-08 09:04:22', '2026-09-21 06:30:22'),
(31, 9, NULL, NULL, 'US', 'approved', NULL, NULL, 'Dyson V15 Vacuum Cleaner', 'dyson-v15-vacuum-cleaner', 'A laser reveals hidden dust while a cordless motor delivers powerful, whole-home suction.', NULL, NULL, NULL, NULL, NULL, 'ADM000031', NULL, NULL, NULL, NULL, NULL, NULL, 1, 74900, NULL, NULL, NULL, NULL, NULL, 100, '/img/products/prod-dyson-v15-vacuum-cleaner.jpg', NULL, NULL, 4.61, 7, 230, 1, 'lightning', 0, '2026-08-05 22:14:22', '2026-09-21 06:30:22'),
(32, 9, NULL, NULL, 'US', 'approved', NULL, NULL, 'Philips Air Fryer XXL', 'philips-air-fryer-xxl', 'Rapid Air technology cooks crispy, low-oil favourites fast enough for a weeknight dinner.', NULL, NULL, NULL, NULL, NULL, 'ADM000032', NULL, NULL, NULL, NULL, NULL, NULL, 1, 19900, NULL, NULL, NULL, NULL, NULL, 100, '/img/products/prod-philips-air-fryer-xxl.jpg', NULL, NULL, 4.60, 252, 6028, 1, 'unbeatable', 0, '2026-03-07 08:28:22', '2026-09-21 06:30:22'),
(33, 9, NULL, NULL, 'US', 'approved', NULL, NULL, 'LG 8kg Front Load Washing Machine', 'lg-8kg-front-load-washing-machine', 'Steam-cleaning and a quiet direct-drive motor make laundry day easier.', NULL, NULL, NULL, NULL, NULL, 'ADM000033', NULL, NULL, NULL, NULL, NULL, NULL, 1, 54900, NULL, NULL, NULL, NULL, NULL, 100, '/img/products/prod-lg-8kg-front-load-washing-machine.jpg', NULL, NULL, 3.59, 25, 7606, 1, NULL, 0, '2025-12-12 23:38:22', '2026-09-21 06:30:22'),
(34, 10, NULL, NULL, 'US', 'approved', NULL, NULL, 'Logitech MX Master 3S Mouse', 'logitech-mx-master-3s-mouse', 'A precision mouse with silent clicks and an ultra-fast scroll wheel, built for all-day productivity.', NULL, NULL, NULL, NULL, NULL, 'ADM000034', NULL, NULL, NULL, NULL, NULL, NULL, 2, 9900, NULL, NULL, NULL, NULL, NULL, 100, '/img/products/prod-logitech-mx-master-3s-mouse.jpg', NULL, NULL, 4.52, 251, 2064, 1, 'lightning', 0, '2026-04-11 23:03:22', '2026-09-21 06:30:22'),
(35, 10, NULL, NULL, 'US', 'approved', NULL, NULL, 'Keychron K2 Mechanical Keyboard', 'keychron-k2-mechanical-keyboard', 'Hot-swappable mechanical switches and Bluetooth multi-device pairing in a compact 75% layout.', NULL, NULL, NULL, NULL, NULL, 'ADM000035', NULL, NULL, NULL, NULL, NULL, NULL, 1, 8900, NULL, NULL, NULL, NULL, NULL, 100, '/img/products/prod-keychron-k2-mechanical-keyboard.jpg', NULL, NULL, 3.76, 794, 2250, 1, 'unbeatable', 0, '2025-09-29 08:29:22', '2026-09-21 06:30:22'),
(36, 10, NULL, NULL, 'US', 'approved', NULL, NULL, 'Dell 27\" 4K Monitor', 'dell-27-4k-monitor', 'Sharp 4K clarity and accurate colour on a 27-inch panel built for work and creative editing.', NULL, NULL, NULL, NULL, NULL, 'ADM000036', NULL, NULL, NULL, NULL, NULL, NULL, 1, 39900, NULL, NULL, NULL, NULL, NULL, 100, '/img/products/prod-dell-27-4k-monitor.jpg', NULL, NULL, NULL, 0, 6203, 1, NULL, 0, '2025-12-04 09:55:22', '2026-09-21 06:30:22'),
(37, 11, NULL, NULL, 'US', 'approved', NULL, NULL, 'Anker 20000mAh Power Bank', 'anker-20000mah-power-bank', 'Enough capacity for multiple full phone charges, with fast pass-through charging.', NULL, NULL, NULL, NULL, NULL, 'ADM000037', NULL, NULL, NULL, NULL, NULL, NULL, 1, 4999, NULL, NULL, NULL, NULL, NULL, 92, '/img/products/prod-anker-20000mah-power-bank.jpg', NULL, NULL, 4.87, 452, 7800, 1, 'lightning', 1, '2026-01-13 19:45:22', '2026-09-23 01:23:56'),
(38, 11, NULL, NULL, 'US', 'approved', NULL, NULL, 'Apple 20W USB-C Fast Charger', 'apple-20w-usb-c-fast-charger', 'Apple\'s compact charger tops up an iPhone to 50% in about 30 minutes.', NULL, NULL, NULL, NULL, NULL, 'ADM000038', NULL, NULL, NULL, NULL, NULL, NULL, 1, 1999, NULL, NULL, NULL, NULL, NULL, 100, '/img/products/prod-apple-20w-usb-c-fast-charger.jpg', NULL, NULL, NULL, 0, 7797, 1, 'lightning', 1, '2025-09-27 09:22:22', '2026-09-21 06:30:22'),
(39, 11, NULL, NULL, 'US', 'approved', NULL, NULL, 'Belkin 3-in-1 Wireless Charging Stand', 'belkin-3-in-1-wireless-charging-stand', 'Charge your phone, watch, and earbuds together from a single stand.', NULL, NULL, NULL, NULL, NULL, 'ADM000039', NULL, NULL, NULL, NULL, NULL, NULL, 1, 9999, NULL, NULL, NULL, NULL, NULL, 100, '/img/products/prod-belkin-3-in-1-wireless-charging-stand.jpg', NULL, NULL, 4.19, 460, 3696, 1, 'unbeatable', 0, '2025-10-03 14:28:22', '2026-09-21 06:30:22'),
(40, 12, NULL, NULL, 'US', 'approved', NULL, NULL, 'SanDisk 1TB Portable SSD', 'sandisk-1tb-portable-ssd', 'Pocket-sized storage with fast transfer speeds, built to survive drops and bumps on the go.', NULL, NULL, NULL, NULL, NULL, 'ADM000040', NULL, NULL, NULL, NULL, NULL, NULL, 1, 8999, NULL, NULL, NULL, NULL, NULL, 100, '/img/products/prod-sandisk-1tb-portable-ssd.jpg', NULL, NULL, 3.58, 687, 597, 1, 'unbeatable', 0, '2026-04-19 02:45:22', '2026-09-21 06:30:22'),
(41, 12, NULL, NULL, 'US', 'approved', NULL, NULL, 'Samsung 256GB microSD Card', 'samsung-256gb-microsd-card', 'High-speed storage for phones, cameras, and handheld consoles.', NULL, NULL, NULL, NULL, NULL, 'ADM000041', NULL, NULL, NULL, NULL, NULL, NULL, 1, 2999, NULL, NULL, NULL, NULL, NULL, 100, '/img/products/prod-samsung-256gb-microsd-card.jpg', NULL, NULL, 4.26, 756, 5591, 1, 'lightning', 1, '2026-02-25 03:27:22', '2026-09-21 06:30:22'),
(42, 12, NULL, NULL, 'US', 'approved', NULL, NULL, 'WD 2TB External Hard Drive', 'wd-2tb-external-hard-drive', 'Reliable backup storage with plug-and-play simplicity for photos, videos, and files.', NULL, NULL, NULL, NULL, NULL, 'ADM000042', NULL, NULL, NULL, NULL, NULL, NULL, 1, 6999, NULL, NULL, NULL, NULL, NULL, 100, '/img/products/prod-wd-2tb-external-hard-drive.jpg', NULL, NULL, 4.49, 455, 6032, 1, 'lightning', 1, '2026-02-23 07:21:22', '2026-09-21 06:30:22'),
(43, 13, NULL, NULL, 'US', 'approved', NULL, NULL, 'TP-Link Archer WiFi 6 Router', 'tp-link-archer-wifi-6-router', 'Faster, more reliable Wi-Fi for a house full of devices with WiFi 6 speeds.', NULL, NULL, NULL, NULL, NULL, 'ADM000043', NULL, NULL, NULL, NULL, NULL, NULL, 1, 12900, NULL, NULL, NULL, NULL, NULL, 100, '/img/products/prod-tp-link-archer-wifi-6-router.jpg', NULL, NULL, 3.89, 444, 966, 1, 'unbeatable', 0, '2026-03-11 00:11:22', '2026-09-21 06:30:22'),
(44, 13, NULL, NULL, 'US', 'approved', NULL, NULL, 'Netgear Orbi Mesh WiFi System', 'netgear-orbi-mesh-wifi-system', 'Whole-home mesh coverage that eliminates dead zones without losing speed.', NULL, NULL, NULL, NULL, NULL, 'ADM000044', NULL, NULL, NULL, NULL, NULL, NULL, 1, 22900, NULL, NULL, NULL, NULL, NULL, 100, '/img/products/prod-netgear-orbi-mesh-wifi-system.jpg', NULL, NULL, 4.35, 390, 964, 1, NULL, 0, '2026-04-26 22:38:22', '2026-09-21 06:30:22'),
(45, 13, NULL, NULL, 'US', 'approved', NULL, NULL, 'TP-Link 8-Port Gigabit Switch', 'tp-link-8-port-gigabit-switch', 'Expand your wired network with eight reliable gigabit ports.', NULL, NULL, NULL, NULL, NULL, 'ADM000045', NULL, NULL, NULL, NULL, NULL, NULL, 1, 3999, NULL, NULL, NULL, NULL, NULL, 100, '/img/products/prod-tp-link-8-port-gigabit-switch.jpg', NULL, NULL, NULL, 0, 6470, 1, 'lightning', 1, '2025-10-22 23:52:22', '2026-09-21 06:30:22'),
(46, 14, NULL, NULL, 'US', 'approved', NULL, NULL, 'Philips Hair Dryer', 'philips-hair-dryer', 'Fast-drying airflow with a cooling shot to lock in your style.', NULL, NULL, NULL, NULL, NULL, 'ADM000046', NULL, NULL, NULL, NULL, NULL, NULL, 1, 2999, NULL, NULL, NULL, NULL, NULL, 100, '/img/products/prod-philips-hair-dryer.jpg', NULL, NULL, 4.56, 319, 3593, 1, 'lightning', 1, '2026-04-18 08:50:22', '2026-09-21 06:30:22'),
(47, 14, NULL, NULL, 'US', 'approved', NULL, NULL, 'Oral-B Electric Toothbrush', 'oral-b-electric-toothbrush', 'A pressure sensor and timer help you brush the dentist-recommended way, every time.', NULL, NULL, NULL, NULL, NULL, 'ADM000047', NULL, NULL, NULL, NULL, NULL, NULL, 1, 4999, NULL, NULL, NULL, NULL, NULL, 100, '/img/products/prod-oral-b-electric-toothbrush.jpg', NULL, NULL, 4.07, 782, 1436, 1, 'lightning', 1, '2025-12-24 12:20:22', '2026-09-21 06:30:22'),
(48, 14, NULL, NULL, 'US', 'approved', NULL, NULL, 'Panasonic Beard Trimmer', 'panasonic-beard-trimmer', 'Precision blades and multiple length settings for a clean, consistent trim.', NULL, NULL, NULL, NULL, NULL, 'ADM000048', NULL, NULL, NULL, NULL, NULL, NULL, 1, 3499, NULL, NULL, NULL, NULL, NULL, 100, '/img/products/prod-panasonic-beard-trimmer.jpg', NULL, NULL, 3.55, 28, 111, 1, 'unbeatable', 1, '2026-09-17 03:31:22', '2026-09-22 01:13:59'),
(49, 15, NULL, NULL, 'US', 'approved', NULL, NULL, 'Motorola Video Baby Monitor', 'motorola-video-baby-monitor', 'See and hear your baby clearly with night vision and two-way audio.', NULL, NULL, NULL, NULL, NULL, 'ADM000049', NULL, NULL, NULL, NULL, NULL, NULL, 1, 8999, NULL, NULL, NULL, NULL, NULL, 100, '/img/products/prod-motorola-video-baby-monitor.jpg', NULL, NULL, 3.89, 693, 4242, 1, 'unbeatable', 0, '2025-11-13 15:29:22', '2026-09-21 06:30:22'),
(50, 15, NULL, NULL, 'US', 'approved', NULL, NULL, 'Amazon Fire Kids Tablet', 'amazon-fire-kids-tablet', 'A durable, parent-controlled tablet built for young explorers, with a kid-proof case included.', NULL, NULL, NULL, NULL, NULL, 'ADM000050', NULL, NULL, NULL, NULL, NULL, NULL, 1, 9999, NULL, NULL, NULL, NULL, NULL, 100, '/img/products/prod-amazon-fire-kids-tablet.jpg', NULL, NULL, 3.89, 268, 1266, 1, NULL, 0, '2026-07-06 07:34:22', '2026-09-21 06:30:22'),
(51, 15, NULL, NULL, 'US', 'approved', NULL, NULL, 'LeapFrog Learning Tablet', 'leapfrog-learning-tablet', 'A screen-time companion designed to teach letters, numbers, and problem-solving through play.', NULL, NULL, NULL, NULL, NULL, 'ADM000051', NULL, NULL, NULL, NULL, NULL, NULL, 1, 5999, NULL, NULL, NULL, NULL, NULL, 100, '/img/products/prod-leapfrog-learning-tablet.jpg', NULL, NULL, 4.18, 481, 1563, 1, 'lightning', 1, '2026-03-02 04:32:22', '2026-09-21 06:30:22'),
(52, 16, NULL, NULL, 'US', 'approved', NULL, NULL, 'HP LaserJet Printer', 'hp-laserjet-printer', 'Crisp, fast black-and-white printing built for the home office.', NULL, NULL, NULL, NULL, NULL, 'ADM000052', NULL, NULL, NULL, NULL, NULL, NULL, 1, 17900, NULL, NULL, NULL, NULL, NULL, 100, '/img/products/prod-hp-laserjet-printer.jpg', NULL, NULL, 4.64, 726, 4705, 1, 'lightning', 0, '2026-04-05 05:46:22', '2026-09-21 06:30:22'),
(53, 16, NULL, NULL, 'US', 'approved', NULL, NULL, 'Epson Portable Projector', 'epson-portable-projector', 'A compact projector that turns any wall into a big screen for movies or presentations.', NULL, NULL, NULL, NULL, NULL, 'ADM000053', NULL, NULL, NULL, NULL, NULL, NULL, 1, 39900, NULL, NULL, NULL, NULL, NULL, 100, '/img/products/prod-epson-portable-projector.jpg', NULL, NULL, 4.27, 15, 11857, 1, 'unbeatable', 0, '2025-11-30 22:36:22', '2026-09-21 06:30:22'),
(54, 16, NULL, NULL, 'US', 'approved', NULL, NULL, 'Logitech Webcam C920', 'logitech-webcam-c920', 'Full HD 1080p video and clear audio, built for sharp video calls and streaming.', NULL, NULL, NULL, NULL, NULL, 'ADM000054', NULL, NULL, NULL, NULL, NULL, NULL, 1, 6999, 7999, NULL, NULL, NULL, NULL, 100, '/img/products/prod-logitech-webcam-c920.jpg', NULL, NULL, 3.84, 747, 2016, 1, NULL, 0, '2026-05-24 00:45:22', '2026-09-21 06:30:22'),
(55, 17, NULL, NULL, 'US', 'approved', NULL, NULL, 'Amazon Echo Dot (5th Gen)', 'amazon-echo-dot-5th-gen', 'A compact smart speaker with Alexa built in, for music, routines, and controlling the rest of your smart home.', NULL, NULL, NULL, NULL, NULL, 'ADM000055', NULL, NULL, NULL, NULL, NULL, NULL, 1, 4999, NULL, NULL, NULL, NULL, NULL, 100, '/img/products/cat-smart-home.jpg', NULL, NULL, 4.02, 422, 308, 1, NULL, 0, '2026-07-29 12:07:22', '2026-09-21 06:30:22'),
(56, 17, NULL, NULL, 'US', 'approved', NULL, NULL, 'Philips Hue Smart Bulb Starter Kit', 'philips-hue-smart-bulb-starter-kit', 'Millions of colours and app-controlled scenes, with a bridge included to get your smart lighting started.', NULL, NULL, NULL, NULL, NULL, 'ADM000056', NULL, NULL, NULL, NULL, NULL, NULL, 1, 6999, NULL, NULL, NULL, NULL, NULL, 100, '/img/products/cat-smart-home.jpg', NULL, NULL, 4.27, 17, 603, 1, NULL, 0, '2026-08-22 20:01:22', '2026-09-21 06:30:22'),
(57, 17, NULL, NULL, 'US', 'approved', NULL, NULL, 'TP-Link Kasa Smart Plug', 'tp-link-kasa-smart-plug', 'Turn any outlet smart — schedule, voice-control, or remotely switch appliances from your phone.', NULL, NULL, NULL, NULL, NULL, 'ADM000057', NULL, NULL, NULL, NULL, NULL, NULL, 1, 1999, NULL, NULL, NULL, NULL, NULL, 100, '/img/products/cat-smart-home.jpg', NULL, NULL, 3.76, 378, 14683, 1, NULL, 0, '2025-11-02 13:23:22', '2026-09-21 06:30:22'),
(58, 17, NULL, NULL, 'US', 'approved', NULL, NULL, 'Ring Video Doorbell', 'ring-video-doorbell', 'See, hear, and speak to visitors from anywhere, with motion alerts sent straight to your phone.', NULL, NULL, NULL, NULL, NULL, 'ADM000058', NULL, NULL, NULL, NULL, NULL, NULL, 1, 9999, NULL, NULL, NULL, NULL, NULL, 100, '/img/products/cat-smart-home.jpg', NULL, NULL, 4.86, 419, 6098, 1, NULL, 0, '2026-01-21 10:28:22', '2026-09-21 06:30:22'),
(59, 17, NULL, NULL, 'US', 'approved', NULL, NULL, 'Eufy RoboVac 11S Robot Vacuum', 'eufy-robovac-11s-robot-vacuum', 'A slim robot vacuum that slides under furniture and keeps floors clean on a schedule you set.', NULL, NULL, NULL, NULL, NULL, 'ADM000059', NULL, NULL, NULL, NULL, NULL, NULL, 1, 19900, NULL, NULL, NULL, NULL, NULL, 100, '/img/products/cat-smart-home.jpg', NULL, NULL, 3.82, 224, 1196, 1, NULL, 0, '2026-05-28 15:30:22', '2026-09-21 06:30:22'),
(60, 18, NULL, NULL, 'US', 'approved', NULL, NULL, 'Garmin Vivosmart 5 Fitness Band', 'garmin-vivosmart-5-fitness-band', 'A slim fitness band with heart-rate tracking, sleep scores, and up to seven days of battery life.', NULL, NULL, NULL, NULL, NULL, 'ADM000060', NULL, NULL, NULL, NULL, NULL, NULL, 1, 12900, NULL, NULL, NULL, NULL, NULL, 100, '/img/products/cat-health-and-fitness-tech.jpg', NULL, NULL, NULL, 0, 40, 1, NULL, 0, '2026-09-16 10:11:22', '2026-09-21 06:30:22'),
(61, 18, NULL, NULL, 'US', 'approved', NULL, NULL, 'Withings Body+ Smart Scale', 'withings-body-plus-smart-scale', 'Weight, body fat, and muscle mass synced automatically to your phone every time you step on.', NULL, NULL, NULL, NULL, NULL, 'ADM000061', NULL, NULL, NULL, NULL, NULL, NULL, 1, 9900, NULL, NULL, NULL, NULL, NULL, 100, '/img/products/cat-health-and-fitness-tech.jpg', NULL, NULL, 4.84, 256, 3796, 1, NULL, 0, '2026-04-16 07:13:22', '2026-09-21 06:30:22'),
(62, 18, NULL, NULL, 'US', 'approved', NULL, NULL, 'Omron Digital Blood Pressure Monitor', 'omron-digital-blood-pressure-monitor', 'Clinically validated, one-button readings you can track at home between doctor visits.', NULL, NULL, NULL, NULL, NULL, 'ADM000062', NULL, NULL, NULL, NULL, NULL, NULL, 1, 4999, NULL, NULL, NULL, NULL, NULL, 100, '/img/products/cat-health-and-fitness-tech.jpg', NULL, NULL, 4.43, 166, 1479, 1, NULL, 0, '2026-07-17 08:55:22', '2026-09-21 06:30:22'),
(63, 18, NULL, NULL, 'US', 'approved', NULL, NULL, 'Wellue Pulse Oximeter', 'wellue-pulse-oximeter', 'A fingertip sensor that reads blood oxygen and pulse rate in seconds, with an easy-read display.', NULL, NULL, NULL, NULL, NULL, 'ADM000063', NULL, NULL, NULL, NULL, NULL, NULL, 1, 2999, NULL, NULL, NULL, NULL, NULL, 100, '/img/products/cat-health-and-fitness-tech.jpg', NULL, NULL, 3.85, 10, 5400, 1, NULL, 0, '2025-11-24 22:05:22', '2026-09-21 06:30:22'),
(64, 18, NULL, NULL, 'US', 'approved', NULL, NULL, 'Xiaomi Smart Skipping Rope', 'xiaomi-smart-skipping-rope', 'Counts jumps, calories, and workout time automatically, and syncs your session to a fitness app.', NULL, NULL, NULL, NULL, NULL, 'ADM000064', NULL, NULL, NULL, NULL, NULL, NULL, 1, 1999, NULL, NULL, NULL, NULL, NULL, 100, '/img/products/cat-health-and-fitness-tech.jpg', NULL, NULL, 3.61, 297, 1800, 1, NULL, 0, '2026-06-12 06:04:22', '2026-09-21 06:30:22'),
(65, 19, NULL, NULL, 'US', 'approved', NULL, NULL, 'Apple iPhone 15 Pro Max', 'apple-iphone-15-pro-max', 'The largest, most capable iPhone — a titanium build, a 5x telephoto lens, and the A17 Pro chip.', NULL, NULL, NULL, NULL, NULL, 'ADM000065', NULL, NULL, NULL, NULL, NULL, NULL, 1, 119900, NULL, NULL, NULL, NULL, NULL, 100, '/img/products/cat-premium-and-flagship.jpg', NULL, NULL, 4.29, 104, 3159, 1, NULL, 0, '2026-04-11 02:02:22', '2026-09-21 06:30:22'),
(66, 19, NULL, NULL, 'US', 'approved', NULL, NULL, 'Samsung Galaxy Z Fold 6', 'samsung-galaxy-z-fold-6', 'A phone that unfolds into a tablet, with a smoother hinge and multitasking built for a bigger screen.', NULL, NULL, NULL, NULL, NULL, 'ADM000066', NULL, NULL, NULL, NULL, NULL, NULL, 1, 179900, NULL, NULL, NULL, NULL, NULL, 100, '/img/products/cat-premium-and-flagship.jpg', NULL, NULL, 4.09, 696, 1934, 1, NULL, 0, '2026-06-19 20:32:22', '2026-09-21 06:30:22'),
(67, 19, NULL, NULL, 'US', 'approved', NULL, NULL, 'Sony Xperia 1 VI', 'sony-xperia-1-vi', 'A creator-focused flagship with a versatile zoom lens system and pro-grade video controls.', NULL, NULL, NULL, NULL, NULL, 'ADM000067', NULL, NULL, NULL, NULL, NULL, NULL, 1, 139900, NULL, NULL, NULL, NULL, NULL, 100, '/img/products/cat-premium-and-flagship.jpg', NULL, NULL, NULL, 0, 6736, 1, NULL, 0, '2025-11-07 21:58:22', '2026-09-21 06:30:22'),
(68, 19, NULL, NULL, 'US', 'approved', NULL, NULL, 'Asus ROG Phone 8', 'asus-rog-phone-8', 'A gaming flagship with a 165Hz display, AirTrigger controls, and cooling built for long sessions.', NULL, NULL, NULL, NULL, NULL, 'ADM000068', NULL, NULL, NULL, NULL, NULL, NULL, 1, 109900, NULL, NULL, NULL, NULL, NULL, 100, '/img/products/cat-premium-and-flagship.jpg', NULL, NULL, NULL, 0, 2047, 1, NULL, 0, '2026-01-21 11:33:22', '2026-09-21 06:30:22'),
(69, 19, NULL, NULL, 'US', 'approved', NULL, NULL, 'Dell XPS 15 Plus', 'dell-xps-15-plus', 'A premium creator laptop with an edge-to-edge InfinityEdge display and serious rendering power.', NULL, NULL, NULL, NULL, NULL, 'ADM000069', NULL, NULL, NULL, NULL, NULL, NULL, 1, 189900, NULL, NULL, NULL, NULL, NULL, 100, '/img/products/cat-premium-and-flagship.jpg', NULL, NULL, 3.68, 13, 1887, 1, NULL, 0, '2026-04-26 11:43:22', '2026-09-21 06:30:22'),
(70, 20, NULL, NULL, 'US', 'approved', NULL, NULL, 'Garmin DriveSmart 55 GPS Navigator', 'garmin-drivesmart-55-gps-navigator', 'Voice-activated turn-by-turn navigation with live traffic, built for the dashboard.', NULL, NULL, NULL, NULL, NULL, 'ADM000070', NULL, NULL, NULL, NULL, NULL, NULL, 1, 19900, NULL, NULL, NULL, NULL, NULL, 100, '/img/products/cat-car-electronics.jpg', NULL, NULL, 4.44, 647, 10507, 1, NULL, 0, '2025-11-06 10:12:22', '2026-09-21 06:30:22'),
(71, 20, NULL, NULL, 'US', 'approved', NULL, NULL, 'Pioneer Bluetooth Car Stereo Receiver', 'pioneer-bluetooth-car-stereo-receiver', 'A touchscreen head unit upgrade with Bluetooth calling and streaming built in for any dashboard.', NULL, NULL, NULL, NULL, NULL, 'ADM000071', NULL, NULL, NULL, NULL, NULL, NULL, 1, 8999, NULL, NULL, NULL, NULL, NULL, 100, '/img/products/cat-car-electronics.jpg', NULL, NULL, 3.82, 665, 1374, 1, NULL, 0, '2026-08-17 06:10:22', '2026-09-21 06:30:22'),
(72, 20, NULL, NULL, 'US', 'approved', NULL, NULL, 'iOttie Car Dashboard Phone Mount', 'iottie-car-dashboard-phone-mount', 'A one-hand, one-touch mount that holds your phone steady on the dash or windshield for hands-free navigation.', NULL, NULL, NULL, NULL, NULL, 'ADM000072', NULL, NULL, NULL, NULL, NULL, NULL, 1, 2499, NULL, NULL, NULL, NULL, NULL, 100, '/img/products/cat-car-electronics.jpg', NULL, NULL, NULL, 0, 2506, 1, NULL, 0, '2026-05-25 06:16:22', '2026-09-21 06:30:22'),
(73, 20, NULL, NULL, 'US', 'approved', NULL, NULL, 'Car Vent Air Purifier & Freshener', 'car-vent-air-purifier-freshener', 'Clips onto any air vent to filter odours and keep the cabin smelling fresh on every drive.', NULL, NULL, NULL, NULL, NULL, 'ADM000073', NULL, NULL, NULL, NULL, NULL, NULL, 1, 1499, NULL, NULL, NULL, NULL, NULL, 97, '/img/products/cat-car-electronics.jpg', NULL, NULL, 4.18, 312, 755, 1, NULL, 0, '2026-02-15 14:38:22', '2026-09-22 01:44:43'),
(74, 20, NULL, NULL, 'US', 'approved', NULL, NULL, 'Pioneer Digital Car Clock Gauge', 'pioneer-digital-car-clock-gauge', 'A dash-mounted digital clock and gauge that drops into any spare vent or console slot.', NULL, NULL, NULL, NULL, NULL, 'ADM000074', NULL, NULL, NULL, NULL, NULL, NULL, 1, 2999, NULL, NULL, NULL, NULL, NULL, 100, '/img/products/cat-car-electronics.jpg', NULL, NULL, 4.79, 277, 3804, 1, NULL, 0, '2026-06-11 03:23:22', '2026-09-21 06:30:22'),
(78, 2, 3, NULL, 'IN', 'approved', NULL, NULL, 'Laptop side screens', 'laptop-side-screens', 'Do more work in one pc.', NULL, NULL, NULL, NULL, NULL, 'SLRCSXX0003', NULL, NULL, NULL, NULL, NULL, NULL, 3, 50000, 70000, NULL, NULL, NULL, NULL, 49, '/api/media/file/products/wqgk97y4RubOjrExw20BXlMvnsDo3hDNqTUQw1if.png', NULL, NULL, NULL, 0, 0, 1, NULL, 0, '2026-09-23 00:50:08', '2026-09-23 01:33:53');

-- --------------------------------------------------------

--
-- Table structure for table `product_images`
--

CREATE TABLE `product_images` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `url` varchar(500) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_images`
--

INSERT INTO `product_images` (`id`, `product_id`, `url`, `sort_order`, `created_at`, `updated_at`) VALUES
(5, 78, '/api/media/file/products/wqgk97y4RubOjrExw20BXlMvnsDo3hDNqTUQw1if.png', 0, '2026-09-23 00:50:08', '2026-09-23 00:50:08'),
(6, 78, '/api/media/file/products/eR58ryWuQ0ykcSqI3rYcxTjPRZvNjbs8U24sz4PJ.png', 1, '2026-09-23 00:50:08', '2026-09-23 00:50:08'),
(7, 78, '/api/media/file/products/DuW3DHGh72lbr1tIRErDzelrc8u82LoShkkV2oKI.png', 2, '2026-09-23 00:50:08', '2026-09-23 00:50:08');

-- --------------------------------------------------------

--
-- Table structure for table `product_reviews`
--

CREATE TABLE `product_reviews` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED DEFAULT NULL,
  `order_item_id` bigint(20) UNSIGNED DEFAULT NULL,
  `variant_label` varchar(120) DEFAULT NULL,
  `rating` tinyint(3) UNSIGNED NOT NULL,
  `body` text DEFAULT NULL,
  `fit` varchar(16) DEFAULT NULL,
  `images` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`images`)),
  `status` varchar(12) NOT NULL DEFAULT 'pending',
  `show_on_profile` tinyint(1) NOT NULL DEFAULT 1,
  `admin_note` varchar(500) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `helpful_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_upload_tasks`
--

CREATE TABLE `product_upload_tasks` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `shop_id` bigint(20) UNSIGNED NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `status` varchar(16) NOT NULL DEFAULT 'processing',
  `records` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `error_records` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `rows` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`rows`)),
  `results` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`results`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_variants`
--

CREATE TABLE `product_variants` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `label` varchar(255) NOT NULL,
  `options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`options`)),
  `sku` varchar(255) NOT NULL,
  `seller_code` varchar(60) DEFAULT NULL,
  `price_cents` int(10) UNSIGNED NOT NULL,
  `compare_at_price_cents` int(10) UNSIGNED DEFAULT NULL,
  `inventory_quantity` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `weight_grams` int(10) UNSIGNED DEFAULT NULL,
  `length_mm` int(10) UNSIGNED DEFAULT NULL,
  `width_mm` int(10) UNSIGNED DEFAULT NULL,
  `height_mm` int(10) UNSIGNED DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `sort_order` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_variants`
--

INSERT INTO `product_variants` (`id`, `product_id`, `label`, `options`, `sku`, `seller_code`, `price_cents`, `compare_at_price_cents`, `inventory_quantity`, `weight_grams`, `length_mm`, `width_mm`, `height_mm`, `image_url`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 7, '256GB SSD / 8GB RAM', NULL, 'ADM000007-V1', NULL, 99900, NULL, 80, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-09-09 01:12:49', '2026-09-14 04:15:22'),
(2, 7, '512GB SSD / 16GB RAM', NULL, 'ADM000007-V2', NULL, 119900, NULL, 58, NULL, NULL, NULL, NULL, NULL, 2, 1, '2026-09-09 01:12:49', '2026-09-14 04:15:22'),
(3, 7, '1TB SSD / 32GB RAM', NULL, 'ADM000007-V3', NULL, 149900, NULL, 22, NULL, NULL, NULL, NULL, NULL, 3, 1, '2026-09-09 01:12:49', '2026-09-14 04:15:22'),
(4, 11, 'Midnight Black', NULL, 'ADM000011-V1', NULL, 34900, NULL, 50, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-09-09 01:12:49', '2026-09-14 04:15:22'),
(5, 11, 'Platinum Silver', NULL, 'ADM000011-V2', NULL, 36900, NULL, 15, NULL, NULL, NULL, NULL, '/img/products/variant-sony-wh-1000xm5-platinum-silver.jpg', 2, 1, '2026-09-09 01:12:49', '2026-09-14 04:15:22'),
(6, 1, '256GB', NULL, 'ADM000001-V1', NULL, 109900, NULL, 40, NULL, NULL, NULL, NULL, NULL, 0, 1, '2026-09-14 04:55:09', '2026-09-14 07:04:36'),
(7, 1, '512GB', NULL, 'ADM000001-V2', NULL, 129900, NULL, 40, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-09-14 04:55:09', '2026-09-14 07:04:36'),
(8, 2, '256GB', NULL, 'ADM000002-V1', NULL, 89900, NULL, 40, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-09-14 04:55:09', '2026-09-14 04:55:09'),
(9, 6, '16GB / 512GB', NULL, 'ADM000006-V1', NULL, 139900, NULL, 40, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-09-14 04:55:09', '2026-09-14 04:55:09'),
(10, 8, '16GB / 1TB', NULL, 'ADM000008-V1', NULL, 159900, NULL, 40, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-09-14 04:55:09', '2026-09-14 04:55:09'),
(11, 17, 'Ocean Blue', NULL, 'ADM000017-V1', NULL, 1499, NULL, 40, NULL, NULL, NULL, NULL, '/img/products/variant-silicone-phone-case-ocean-blue.jpg', 1, 1, '2026-09-14 04:55:09', '2026-09-14 04:55:09'),
(12, 17, 'Blossom Pink', NULL, 'ADM000017-V2', NULL, 1499, NULL, 40, NULL, NULL, NULL, NULL, '/img/products/variant-silicone-phone-case-blossom-pink.jpg', 2, 1, '2026-09-14 04:55:09', '2026-09-14 04:55:09'),
(13, 19, '45mm', NULL, 'ADM000019-V1', NULL, 42900, NULL, 40, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-09-14 04:55:09', '2026-09-14 04:55:09'),
(14, 21, 'Coral', NULL, 'ADM000021-V1', NULL, 15900, NULL, 40, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-09-14 04:55:09', '2026-09-14 04:55:09'),
(15, 28, 'Digital Edition', NULL, 'ADM000028-V1', NULL, 44900, NULL, 40, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-09-14 04:55:09', '2026-09-14 06:01:19'),
(16, 30, 'Neon Red / Neon Blue', NULL, 'ADM000030-V1', NULL, 34900, NULL, 40, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-09-14 04:55:09', '2026-09-14 04:55:09'),
(17, 34, 'Pale Grey', NULL, 'ADM000034-V1', NULL, 9900, NULL, 40, NULL, NULL, NULL, NULL, '/api/media/file/products/8veyYFqXQP5ogGnWjqGBBMDAPzGfohMDY6vNxaXm.webp', 0, 1, '2026-09-14 04:55:09', '2026-09-21 03:00:35'),
(20, 78, 'Company2', NULL, 'SLRCSXX0003-V1', NULL, 5500, 6000, 55, NULL, NULL, NULL, NULL, '/api/media/file/products/0aP9RFYwOnZFFlkZCAwWCfQAvb0Yx0d5mdLU8O35.png', 0, 1, '2026-09-23 00:50:08', '2026-09-23 00:50:08'),
(21, 78, 'Company3', NULL, 'SLRCSXX0003-V2', NULL, 4500, 5500, 66, NULL, NULL, NULL, NULL, '/api/media/file/products/3sxrJqa4NtyAFEz30oOnbavnW1zjqKb86z7G2PgM.png', 1, 1, '2026-09-23 00:50:08', '2026-09-23 00:50:08');

-- --------------------------------------------------------

--
-- Table structure for table `review_helpful_votes`
--

CREATE TABLE `review_helpful_votes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_review_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rider_applications`
--

CREATE TABLE `rider_applications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `store_id` bigint(20) UNSIGNED DEFAULT NULL,
  `phone` varchar(40) NOT NULL,
  `home_address` varchar(255) NOT NULL,
  `home_lat` decimal(10,7) DEFAULT NULL,
  `home_lng` decimal(10,7) DEFAULT NULL,
  `vehicle_type` varchar(20) NOT NULL,
  `license_number` varchar(60) DEFAULT NULL,
  `license_document_path` varchar(255) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `rejection_reason` varchar(500) DEFAULT NULL,
  `reviewed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rider_ledger_entries`
--

CREATE TABLE `rider_ledger_entries` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED DEFAULT NULL,
  `type` varchar(20) NOT NULL,
  `amount_cents` bigint(20) NOT NULL,
  `distance_miles` decimal(8,2) DEFAULT NULL,
  `note` varchar(500) DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rider_payout_requests`
--

CREATE TABLE `rider_payout_requests` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `amount_cents` bigint(20) UNSIGNED NOT NULL,
  `status` varchar(16) NOT NULL DEFAULT 'pending',
  `admin_note` varchar(500) DEFAULT NULL,
  `ledger_entry_id` bigint(20) UNSIGNED DEFAULT NULL,
  `processed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rider_reviews`
--

CREATE TABLE `rider_reviews` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `rider_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `rating` tinyint(3) UNSIGNED NOT NULL,
  `comment` text DEFAULT NULL,
  `source` varchar(16) NOT NULL DEFAULT 'delivery',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rider_shifts`
--

CREATE TABLE `rider_shifts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `clock_in_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `clock_out_at` timestamp NULL DEFAULT NULL,
  `source` varchar(10) NOT NULL DEFAULT 'rider',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `rider_shifts`
--

INSERT INTO `rider_shifts` (`id`, `user_id`, `clock_in_at`, `clock_out_at`, `source`, `created_at`, `updated_at`) VALUES
(1, 16, '2026-09-10 10:38:47', '2026-09-10 05:08:47', 'rider', '2026-09-10 01:17:26', '2026-09-10 05:08:47'),
(10, 16, '2026-09-24 05:20:53', '2026-09-23 23:50:53', 'rider', '2026-09-10 05:08:49', '2026-09-23 23:50:53'),
(11, 16, '2026-09-23 23:50:54', NULL, 'rider', '2026-09-23 23:50:54', '2026-09-23 23:50:54');

-- --------------------------------------------------------

--
-- Table structure for table `rider_shift_breaks`
--

CREATE TABLE `rider_shift_breaks` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `rider_shift_id` bigint(20) UNSIGNED NOT NULL,
  `reason` varchar(80) DEFAULT NULL,
  `started_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ended_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `rider_shift_breaks`
--

INSERT INTO `rider_shift_breaks` (`id`, `rider_shift_id`, `reason`, `started_at`, `ended_at`, `created_at`, `updated_at`) VALUES
(1, 1, 'Lunch', '2026-09-10 06:47:38', '2026-09-10 01:17:38', '2026-09-10 01:17:30', '2026-09-10 01:17:38'),
(4, 1, 'Lunch', '2026-09-10 09:44:50', '2026-09-10 04:14:50', '2026-09-10 04:14:44', '2026-09-10 04:14:50'),
(5, 1, 'Lunch', '2026-09-10 10:38:42', '2026-09-10 05:08:42', '2026-09-10 05:08:34', '2026-09-10 05:08:42'),
(6, 10, 'Lunch', '2026-09-14 11:56:53', '2026-09-14 06:26:53', '2026-09-11 02:43:32', '2026-09-14 06:26:53');

-- --------------------------------------------------------

--
-- Table structure for table `rider_store`
--

CREATE TABLE `rider_store` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `store_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `rider_store`
--

INSERT INTO `rider_store` (`id`, `user_id`, `store_id`) VALUES
(3, 16, 1),
(4, 18, 2);

-- --------------------------------------------------------

--
-- Table structure for table `sales_boost_offers`
--

CREATE TABLE `sales_boost_offers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `product_variant_id` bigint(20) UNSIGNED DEFAULT NULL,
  `current_price_cents` int(10) UNSIGNED NOT NULL,
  `recommended_price_cents` int(10) UNSIGNED NOT NULL,
  `status` varchar(12) NOT NULL DEFAULT 'pending',
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `decided_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sellers`
--

CREATE TABLE `sellers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `country` varchar(2) NOT NULL,
  `business_type` varchar(32) NOT NULL,
  `company_name` varchar(160) NOT NULL,
  `tax_id` varchar(60) NOT NULL,
  `registered_line1` varchar(255) NOT NULL,
  `registered_line2` varchar(255) DEFAULT NULL,
  `registered_city` varchar(100) NOT NULL,
  `registered_state` varchar(60) NOT NULL,
  `registered_postal_code` varchar(12) NOT NULL,
  `registered_country` varchar(2) NOT NULL,
  `pickup_same_as_registered` tinyint(1) NOT NULL DEFAULT 1,
  `pickup_phone` varchar(32) DEFAULT NULL,
  `pickup_line1` varchar(255) DEFAULT NULL,
  `pickup_line2` varchar(255) DEFAULT NULL,
  `pickup_city` varchar(100) DEFAULT NULL,
  `pickup_state` varchar(60) DEFAULT NULL,
  `pickup_postal_code` varchar(12) DEFAULT NULL,
  `pickup_country` varchar(2) DEFAULT NULL,
  `contact_name` varchar(160) NOT NULL,
  `id_type` varchar(32) NOT NULL,
  `id_number` varchar(60) NOT NULL,
  `date_of_birth` date NOT NULL,
  `id_document_path` varchar(255) DEFAULT NULL,
  `business_document_path` varchar(255) DEFAULT NULL,
  `status` varchar(16) NOT NULL DEFAULT 'pending',
  `rejection_reason` varchar(500) DEFAULT NULL,
  `reviewed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `payout_method` varchar(16) DEFAULT NULL,
  `payout_details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payout_details`)),
  `tax_info` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tax_info`)),
  `tax_status` varchar(16) DEFAULT NULL,
  `tax_note` varchar(500) DEFAULT NULL,
  `tax_submitted_at` timestamp NULL DEFAULT NULL,
  `compliance` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`compliance`)),
  `compliance_status` varchar(16) DEFAULT NULL,
  `compliance_note` varchar(500) DEFAULT NULL,
  `compliance_submitted_at` timestamp NULL DEFAULT NULL,
  `bank_status` varchar(16) DEFAULT NULL,
  `bank_note` varchar(500) DEFAULT NULL,
  `bank_submitted_at` timestamp NULL DEFAULT NULL,
  `bank_verified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sellers`
--

INSERT INTO `sellers` (`id`, `user_id`, `country`, `business_type`, `company_name`, `tax_id`, `registered_line1`, `registered_line2`, `registered_city`, `registered_state`, `registered_postal_code`, `registered_country`, `pickup_same_as_registered`, `pickup_phone`, `pickup_line1`, `pickup_line2`, `pickup_city`, `pickup_state`, `pickup_postal_code`, `pickup_country`, `contact_name`, `id_type`, `id_number`, `date_of_birth`, `id_document_path`, `business_document_path`, `status`, `rejection_reason`, `reviewed_by`, `reviewed_at`, `submitted_at`, `payout_method`, `payout_details`, `tax_info`, `tax_status`, `tax_note`, `tax_submitted_at`, `compliance`, `compliance_status`, `compliance_note`, `compliance_submitted_at`, `bank_status`, `bank_note`, `bank_submitted_at`, `bank_verified_at`, `created_at`, `updated_at`) VALUES
(3, 15, 'IN', 'individual', 'testcaresort', '07DDIPA9391G1ZC', 'mohali', '5', 'India', 'Punjab', '160059', 'IN', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Web Dev', 'aadhaar', '1564111566645612', '2010-02-02', 'kyc/15/bmdY1Uq4tYolz36eKWd5vcERzoGEWll3XZRGr6Bw.jpg', 'kyc/15/l91i8Gl5faUyjHn61fx1PdAhjLQVaA5PQwfyO4ns.pdf', 'approved', NULL, 15, '2026-09-22 01:51:19', '2026-09-22 01:50:26', 'paypal', '{\"email\":\"seller-payout-verify@example.com\"}', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'linked', NULL, NULL, '2026-09-25 00:20:08', '2026-09-22 01:50:26', '2026-09-25 00:36:34'),
(7, 41, 'IN', 'individual', 'caresort', '07DEMOA9391G1ZC', '55', 'Pannu tower', 'Mohali', 'Punjab', '144444', 'IN', 1, '9888888888', '55', 'Pannu tower', 'Mohali', 'Punjab', '144444', 'IN', 'Web Dev', 'aadhaar', '222233334444', '2016-07-23', 'kyc/41/TeoCTiNHet9s3ctTUh87VQS39GRRIlC6Xtve7pxv.pdf', 'kyc/41/wCNAhMpKgAcMBSze3I3K7m1UGblHDp0LO96NkFY1.jpg', 'pending', NULL, NULL, NULL, '2026-09-24 06:18:30', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-22 23:36:13', '2026-09-24 06:18:30');

-- --------------------------------------------------------

--
-- Table structure for table `seller_ledger_entries`
--

CREATE TABLE `seller_ledger_entries` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `shop_id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED DEFAULT NULL,
  `type` varchar(24) NOT NULL,
  `amount_cents` int(11) NOT NULL,
  `commission_cents` int(11) DEFAULT NULL,
  `note` varchar(500) DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `seller_ledger_entries`
--

INSERT INTO `seller_ledger_entries` (`id`, `shop_id`, `order_id`, `type`, `amount_cents`, `commission_cents`, `note`, `created_by`, `created_at`, `updated_at`) VALUES
(4, 3, NULL, 'order_credit', 4500, 500, NULL, NULL, '2026-09-22 05:51:13', '2026-09-22 05:51:13'),
(5, 3, 44, 'order_credit', 45000, 5000, NULL, NULL, '2026-09-23 01:28:48', '2026-09-23 01:28:48'),
(6, 3, 45, 'order_credit', 45000, 5000, NULL, NULL, '2026-09-23 01:30:24', '2026-09-23 01:30:24'),
(7, 3, 46, 'order_credit', 45000, 5000, NULL, NULL, '2026-09-23 01:34:08', '2026-09-23 01:34:08');

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
('1adUTMnyviODTA5AJjYfVzigpM6dZXrQj8oDoghp', NULL, '127.0.0.1', 'curl/8.21.0', 'eyJfdG9rZW4iOiJOZFh5UDZxOWFacnBjUnhFSThTSW1HbjIwTjZCUFNFemI0c090Y2VKIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwXC9hcGlcL2FkbWluXC9wYWdlc1wvMzIiLCJyb3V0ZSI6bnVsbH0sIl9mbGFzaCI6eyJvbGQiOltdLCJuZXciOltdfX0=', 1790076873),
('1sWXlFBqkQagtTDuaH050K3PIKDVeUjSMr9YGvaX', NULL, '127.0.0.1', 'Symfony', 'eyJfdG9rZW4iOiJTT1BWRDQ4MTdvWnZKY1d5T0hCTWNmWjBYSWRoc2hEbHI3YkdkVGRjIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwXC91bnN1YnNjcmliZVwvMTc/c2lnbmF0dXJlPTEzMTIyZGJlZGFhZWUxMTA0NjQ2NzY4YjU0MDU1ZTA1YTliYmE2YmY2NWE4MmZlNjE1ZDQ4NDg1OWFiYjgwM2IiLCJyb3V0ZSI6InVuc3Vic2NyaWJlIn0sIl9mbGFzaCI6eyJvbGQiOltdLCJuZXciOltdfX0=', 1790313936),
('3oISb7Njdw4tKH5J5FntsNvMPeZvdPZt82SXyr4x', NULL, '127.0.0.1', 'curl/8.21.0', 'eyJfdG9rZW4iOiI5eHZJQzA1M1FNRWFFdDh0ZTM3S3lsMlFMY1ZJbklLdGVweGNnWjVEIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwXC9hcGlcL2Jhbm5lcnMiLCJyb3V0ZSI6bnVsbH0sIl9mbGFzaCI6eyJvbGQiOltdLCJuZXciOltdfX0=', 1789017213),
('5iKl36D45BSmZw2tSmv3YKKCEPl4XQlqZACdTMAo', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0', 'eyJfdG9rZW4iOiIyQXhSN0s5Mmh4NzNJbkRsSmxoTDVZTEJrWmJFUUVSRXZXRTJTaVI4IiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cL2xvY2FsaG9zdDo4MDAwIiwicm91dGUiOm51bGx9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX19', 1789367069),
('7bfRrrZFZMQOp1Bg6XxFzzALeccv503Yjg6woWe9', NULL, '127.0.0.1', 'Symfony', 'eyJfdG9rZW4iOiJkTVk0WFdWWHhPQlZtaExvRXhvbG5HbjAwTjlCdXBod2FSc2NXZE55IiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwXC91bnN1YnNjcmliZVwvMTc/c2lnbmF0dXJlPTEzMTIyZGJlZGFhZWUxMTA0NjQ2NzY4YjU0MDU1ZTA1YTliYmE2YmY2NWE4MmZlNjE1ZDQ4NDg1OWFiYjgwM2IiLCJyb3V0ZSI6InVuc3Vic2NyaWJlIn0sIl9mbGFzaCI6eyJvbGQiOltdLCJuZXciOltdfX0=', 1790313922),
('7x32XCjyl74wUgYrdWpUFtSpluem1SIRbbRXVtlN', NULL, '127.0.0.1', 'curl/8.21.0', 'eyJfdG9rZW4iOiJlUmlkM041QnQyenZOV3pack42c2dsUnJUMGdIZjVudlFndU1zbk5xIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwXC9hcGlcL2Zvb3RlciIsInJvdXRlIjpudWxsfSwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119fQ==', 1790168842),
('BU3pTwNXMnyuSrVlQiQZc2rx42bR04k24ro5Qr2x', NULL, '127.0.0.1', 'curl/8.21.0', 'eyJfdG9rZW4iOiIyQ29jd25NOW4ySTczS2VySVdIcXNzMVpBaDAzUHpIOHlZQmw5anBzIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwIiwicm91dGUiOm51bGx9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX19', 1790059846),
('E3uiXV8elXX319hZg99S3K9ivwaG5bNMZA1NgvB2', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0', 'eyJfdG9rZW4iOiJnWTFvWmlTVjVOcDZoWXRaYUJBOEJYcUtNdHhET2R3ME1WbVEwSlVoIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cL2xvY2FsaG9zdDo4MDAwIiwicm91dGUiOm51bGx9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX19', 1789044363),
('EcUaxHeMLWcyD1TPvFwuchfWw7Xg7nmdGwJgXbJ4', NULL, '127.0.0.1', 'curl/8.21.0', 'eyJfdG9rZW4iOiI2a05aWWFqaEY0UHlabVdRbTh1VXJTWlE3cG9MTFI4ZzhLRUdwSmN4IiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwXC9hcGlcL2hlYWx0aCIsInJvdXRlIjpudWxsfSwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119fQ==', 1789032140),
('efEA3p3gn1RfGDquS0gKMSgl4GgAhVDBCXrKSl0W', NULL, '127.0.0.1', 'curl/8.21.0', 'eyJfdG9rZW4iOiJiYmptVW4ydmZIUlJBQjAwUzJLTEc3SlVtMm9FWlJXOVNIalhFME5zIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwIiwicm91dGUiOm51bGx9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX19', 1790055796),
('gEoMm1BFGvbmLTr88RP3KcqIMmB0B8dtGsxBJnc9', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0', 'eyJfdG9rZW4iOiJoSFJISXMwVGRLU0JIVjlpYmFEVVMxRGp2cGNqWVI3YURWN1FJZDRHIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwIiwicm91dGUiOm51bGx9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX19', 1790320647),
('KDh8NR5yvlZygCHNdiWQkgeOXfc147vgjjuRM0Ko', NULL, '127.0.0.1', 'curl/8.21.0', 'eyJfdG9rZW4iOiI5TmhmVEh0anBudjgxZ1NkaWE3ZXhudmxaV05MRVlYc3QwN3JOa1ZlIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwXC9hcGlcL3NldHRpbmdzIiwicm91dGUiOm51bGx9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX19', 1790168842),
('LfYGkhTNNaonvFh2fAxz8ISJrItMdWw9bqKz8LRi', NULL, '127.0.0.1', 'curl/8.21.0', 'eyJfdG9rZW4iOiI5Sk9ONTNjUEczU2ViWDhZUGVFNlpiUXlvSjc2akROV3FuR21Vdm44IiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwXC9hcGlcL2F1dGhcL21lIiwicm91dGUiOm51bGx9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX19', 1789111585),
('MKCxGH4Kpc8Brjol0mxSnkw4fejEJPBIluXEzTA7', NULL, '127.0.0.1', 'curl/8.21.0', 'eyJfdG9rZW4iOiJuZkU4VFhUU3ZiaDB5aEJFdFBPNWxDREVNQXo1OU1CQnUxcE5meFN6IiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwXC9hcGlcL2hlYWx0aCIsInJvdXRlIjpudWxsfSwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119fQ==', 1790168637),
('sHowNqqq7GznUDx3FuAF0fz9iKFWqunvokcbEyqP', NULL, '127.0.0.1', 'curl/8.21.0', 'eyJfdG9rZW4iOiJjYVlDQWdnQ250Ymd0OTNsRkdZSU9pcTZ1UnBtMnFwTTc2YTI0VGpUIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwXC9hcGlcL3N0b3JlZnJvbnQiLCJyb3V0ZSI6bnVsbH0sIl9mbGFzaCI6eyJvbGQiOltdLCJuZXciOltdfX0=', 1789992442),
('TNsi2G5CBzP7yCvHjr0Lm9FESSmyJthVkIugGaWv', NULL, '127.0.0.1', 'curl/8.21.0', 'eyJfdG9rZW4iOiJ2YjVMaW5sYjdmQkgzc2drOWdNenBKaHVaNldRRVZwQ3V5dWJ0ZUoyIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwXC9hcGlcL3NldHRpbmdzIiwicm91dGUiOm51bGx9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX19', 1789992959),
('UTbg5UPwPiWkoC54ywTxGtgu03viEwtrup5vfuf8', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0', 'eyJfdG9rZW4iOiJjM0RBT05GUWNJb1FPTWl3WElPTTJsY0hCbTh0TUtHZUR0dU54WllzIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cL2xvY2FsaG9zdDo4MDAwIiwicm91dGUiOm51bGx9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX19', 1788953496),
('VbUeTGNJe0w12iJenDsJSM2ByZjaYiW3lC3tldpR', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0', 'eyJfdG9rZW4iOiJpZFl1WTh3Y09tUUQ4SlhJZVNYaGhHRURIamVodU1oSGFjTm1wY3h5IiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cL2xvY2FsaG9zdDo4MDAwIiwicm91dGUiOm51bGx9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX19', 1788940289),
('wBgDhJIiGenFef0bxqwhWccTcxoPh5NHt3TDSg4N', NULL, '127.0.0.1', 'curl/8.21.0', 'eyJfdG9rZW4iOiJrS3htcllrRW5aTzlUUU1aaGdadVhuUWpBSlNsc3NNUWdwbzNOWUk2IiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwIiwicm91dGUiOm51bGx9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX19', 1790058806),
('ZiwnkODKgtrcT3FKTmLZaSQhoOVtb0NLbJ689l6k', NULL, '127.0.0.1', 'curl/8.21.0', 'eyJfdG9rZW4iOiJvZjFhdjZjcXZCVHczN25PV1p0VWNKNkFVYVJHN2xrY2YxNE1mbG9BIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwXC9hcGlcL2hvbWUiLCJyb3V0ZSI6bnVsbH0sIl9mbGFzaCI6eyJvbGQiOltdLCJuZXciOltdfX0=', 1789447022);

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `key` varchar(255) NOT NULL,
  `value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`value`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`key`, `value`, `created_at`, `updated_at`) VALUES
('active_countries', '{\"v\":[\"IN\",\"US\"]}', '2026-09-22 01:11:02', '2026-09-22 01:11:02'),
('branding', '{\"v\":{\"store_name\":\"NexTech\",\"tagline\":\"Navigate to the Future of Technology\",\"logo_url\":null,\"favicon_url\":\"\\/storage\\/products\\/UwbDkyfQGw98w7hhx7hlPcFS1Cw9VZfnz06klnyk.jpg\",\"theme\":\"light\",\"layout_width\":\"full\",\"color_brand\":\"#2563EB\",\"color_accent\":\"#F97316\",\"color_heading\":\"#0F172A\"}}', '2026-09-09 01:31:48', '2026-09-14 07:06:38'),
('checkout_fees', '{\"v\":{\"delivery_mode\":\"fixed\",\"delivery_fee_cents\":299,\"delivery_near_fee_cents\":199,\"delivery_far_fee_cents\":599,\"free_delivery_threshold_cents\":3500,\"handling_fee_cents\":99,\"small_cart_fee_cents\":199,\"small_cart_min_cents\":1000,\"tax_rate_bps\":887}}', '2026-09-10 07:26:50', '2026-09-10 07:26:50'),
('cod_enabled', '{\"v\":false}', '2026-09-10 07:26:49', '2026-09-24 05:22:28'),
('color_brand', '{\"v\":\"#1f7a3d\"}', '2026-09-21 06:48:20', '2026-09-21 06:48:20'),
('courier', '{\"v\":{\"courier_provider\":\"mock\"}}', '2026-09-22 04:09:42', '2026-09-22 04:11:06'),
('footer', '{\"v\": {\"copyright\": \"\\u00a9 {year} nextech\", \"note\": \"NexTech is a demo storefront. Prices, delivery estimates and content pages are illustrative and set by the store operator in the admin console.\", \"app_store_url\": \"https://apps.apple.com/app/nextech-demo\", \"play_store_url\": \"https://play.google.com/store/apps/details?id=com.nextech.demo\", \"socials\": {\"facebook\": \"https://facebook.com/nextech\", \"x\": \"https://x.com/nextech\", \"instagram\": \"https://instagram.com/nextech\", \"linkedin\": \"https://www.linkedin.com/company/nextech\", \"youtube\": \"https://www.youtube.com/@nextech\"}, \"links\": [], \"bg_color\": \"#141414\", \"text_color\": \"#f5f5f5\"}}', '2026-09-09 01:12:49', '2026-09-14 05:52:41'),
('nextech_label_mode', '{\"v\":\"manual\"}', '2026-09-24 05:13:05', '2026-09-24 05:26:03'),
('nextech_pickup', '{\"v\":\"hidden\"}', '2026-09-24 05:16:22', '2026-09-25 04:58:38');

-- --------------------------------------------------------

--
-- Table structure for table `shipments`
--

CREATE TABLE `shipments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `provider` varchar(255) NOT NULL,
  `tracking_number` varchar(255) NOT NULL,
  `carrier` varchar(255) NOT NULL,
  `label_url` varchar(500) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'booked',
  `cost_cents` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `booked_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `shipments`
--

INSERT INTO `shipments` (`id`, `order_id`, `provider`, `tracking_number`, `carrier`, `label_url`, `status`, `cost_cents`, `booked_at`, `created_at`, `updated_at`) VALUES
(5, 28, 'mock', 'MOCK-VJTBW77GDR', 'MockCourier', NULL, 'delivered', 0, '2026-09-23 00:11:09', '2026-09-23 00:11:09', '2026-09-23 04:38:24');

-- --------------------------------------------------------

--
-- Table structure for table `shipping_templates`
--

CREATE TABLE `shipping_templates` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `shop_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `product_type` varchar(30) NOT NULL DEFAULT 'standard',
  `shop_address_id` bigint(20) UNSIGNED DEFAULT NULL,
  `handling_days` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `shipping_template_groups`
--

CREATE TABLE `shipping_template_groups` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `shipping_template_id` bigint(20) UNSIGNED NOT NULL,
  `regions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`regions`)),
  `address_types` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`address_types`)),
  `transit_min_days` tinyint(3) UNSIGNED NOT NULL,
  `transit_max_days` tinyint(3) UNSIGNED NOT NULL,
  `fee_cents` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `sort_order` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `shops`
--

CREATE TABLE `shops` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `seller_id` bigint(20) UNSIGNED NOT NULL,
  `market` varchar(2) NOT NULL DEFAULT 'US',
  `name` varchar(160) NOT NULL,
  `slug` varchar(180) NOT NULL,
  `shop_code` varchar(8) DEFAULT NULL,
  `next_product_seq` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `logo_url` varchar(500) DEFAULT NULL,
  `banner_url` varchar(500) DEFAULT NULL,
  `category_id` bigint(20) UNSIGNED DEFAULT NULL,
  `description` text DEFAULT NULL,
  `decoration_terms_accepted_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `fulfillment_mode` varchar(12) NOT NULL DEFAULT 'nextech',
  `ships_saturday` tinyint(1) NOT NULL DEFAULT 0,
  `ships_sunday` tinyint(1) NOT NULL DEFAULT 0,
  `working_holidays` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`working_holidays`)),
  `free_shipping_accepted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `label_template_id` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `shops`
--

INSERT INTO `shops` (`id`, `seller_id`, `market`, `name`, `slug`, `shop_code`, `next_product_seq`, `logo_url`, `banner_url`, `category_id`, `description`, `decoration_terms_accepted_at`, `is_active`, `fulfillment_mode`, `ships_saturday`, `ships_sunday`, `working_holidays`, `free_shipping_accepted_at`, `created_at`, `updated_at`, `label_template_id`) VALUES
(3, 3, 'IN', 'cs', 'cs', 'CSXX', 10, '/api/media/file/shops/JPWwmzWVj1Xg8QPlvFNz7V40jY3DfjllTsioSU9z.webp', NULL, 8, 'gaming desc.', '2026-09-25 05:26:28', 1, 'nextech', 0, 0, NULL, NULL, '2026-09-22 01:50:26', '2026-09-25 05:26:28', NULL),
(6, 7, 'IN', 'Caresort', 'caresort', 'CARE', 1, '/api/media/file/shops/D0hktULokZmSlDVR7zzcMHhLkFyMTUKkFdW6Srzv.jpg', NULL, 19, NULL, NULL, 0, 'nextech', 0, 0, NULL, NULL, '2026-09-22 23:36:13', '2026-09-22 23:36:13', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `shop_addresses`
--

CREATE TABLE `shop_addresses` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `shop_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `line1` varchar(255) NOT NULL,
  `line2` varchar(255) DEFAULT NULL,
  `city` varchar(100) NOT NULL,
  `state` varchar(2) NOT NULL,
  `postal_code` varchar(12) NOT NULL,
  `country` varchar(2) NOT NULL DEFAULT 'US',
  `phone` varchar(32) NOT NULL,
  `contact_name` varchar(120) NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `site_feedback`
--

CREATE TABLE `site_feedback` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `rating` tinyint(3) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stores`
--

CREATE TABLE `stores` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT 'Main Store',
  `line1` varchar(255) NOT NULL,
  `line2` varchar(255) DEFAULT NULL,
  `city` varchar(100) NOT NULL,
  `state` varchar(60) NOT NULL,
  `postal_code` varchar(12) NOT NULL,
  `country` varchar(2) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `delivery_radius_km` smallint(5) UNSIGNED NOT NULL DEFAULT 5,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `stores`
--

INSERT INTO `stores` (`id`, `name`, `line1`, `line2`, `city`, `state`, `postal_code`, `country`, `latitude`, `longitude`, `delivery_radius_km`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Caresort Solutions', 'C-86, Pannu Tower 4th Floor', 'Phase 7, Industrial Area', 'Sahibzada Ajit Singh Nagar', 'Punjab', '160055', 'US', 30.6908804, 76.7114879, 5, 1, '2026-09-09 01:33:16', '2026-09-09 01:33:16'),
(2, 'The Royal Majestic', 'chowk, 200 Feet Rd, near Phullanwal', 'Passi Nagar', 'Ludhiana', 'Punjab', '141013', 'US', 30.9090157, 75.8516010, 5, 1, '2026-09-09 01:36:49', '2026-09-09 01:36:49');

-- --------------------------------------------------------

--
-- Table structure for table `store_decorations`
--

CREATE TABLE `store_decorations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `shop_id` bigint(20) UNSIGNED NOT NULL,
  `platform` varchar(8) NOT NULL,
  `name` varchar(80) NOT NULL,
  `status` varchar(12) NOT NULL DEFAULT 'draft',
  `is_live` tinyint(1) NOT NULL DEFAULT 0,
  `page` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`page`)),
  `sections` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`sections`)),
  `review_note` varchar(500) DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `published_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `store_decorations`
--

INSERT INTO `store_decorations` (`id`, `shop_id`, `platform`, `name`, `status`, `is_live`, `page`, `sections`, `review_note`, `submitted_at`, `reviewed_at`, `published_at`, `created_at`, `updated_at`) VALUES
(3, 3, 'desktop', 'Desktop version 1', 'draft', 0, '{\"background_image_url\":\"\",\"background_color\":\"#f3f4f6\",\"accent_color\":\"#fb7701\"}', '[]', NULL, NULL, NULL, NULL, '2026-09-25 05:26:35', '2026-09-25 05:26:35');

-- --------------------------------------------------------

--
-- Table structure for table `store_inventory`
--

CREATE TABLE `store_inventory` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `store_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `product_variant_id` bigint(20) UNSIGNED DEFAULT NULL,
  `quantity` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `is_stocked` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `store_inventory`
--

INSERT INTO `store_inventory` (`id`, `store_id`, `product_id`, `product_variant_id`, `quantity`, `is_stocked`, `created_at`, `updated_at`) VALUES
(1, 1, 54, NULL, 50, 1, '2026-09-09 04:21:55', '2026-09-09 04:21:55');

-- --------------------------------------------------------

--
-- Table structure for table `stripe_events`
--

CREATE TABLE `stripe_events` (
  `id` varchar(255) NOT NULL,
  `type` varchar(255) NOT NULL,
  `processed_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `support_messages`
--

CREATE TABLE `support_messages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `support_thread_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `is_staff` tinyint(1) NOT NULL DEFAULT 0,
  `from_seller` tinyint(1) NOT NULL DEFAULT 0,
  `internal` tinyint(1) NOT NULL DEFAULT 0,
  `hidden_from_seller` tinyint(1) NOT NULL DEFAULT 0,
  `body` text NOT NULL,
  `attachments` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attachments`)),
  `attachment_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `support_messages`
--

INSERT INTO `support_messages` (`id`, `support_thread_id`, `user_id`, `is_staff`, `from_seller`, `internal`, `hidden_from_seller`, `body`, `attachments`, `attachment_url`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, 0, 0, 0, 0, 'Support request opened — item missing.', NULL, NULL, '2026-09-09 02:28:35', '2026-09-09 02:28:35'),
(2, 1, 17, 0, 0, 0, 0, 'Hi', NULL, NULL, '2026-09-09 02:28:35', '2026-09-09 02:28:35'),
(3, 1, 15, 1, 0, 0, 0, 'Hi', NULL, NULL, '2026-09-09 02:39:52', '2026-09-09 02:39:52'),
(4, 1, 15, 1, 0, 0, 0, 'We have noticed that your order is just packed and ready to be delivered. Please elaborate your issue.', NULL, NULL, '2026-09-09 02:40:31', '2026-09-09 02:40:31'),
(8, 1, 15, 1, 0, 0, 0, 'Delivered.', NULL, NULL, '2026-09-09 05:07:13', '2026-09-09 05:07:13'),
(36, 1, NULL, 1, 0, 0, 0, 'Customer ended the chat.', NULL, NULL, '2026-09-14 06:26:02', '2026-09-14 06:26:02'),
(46, 9, 41, 1, 0, 0, 0, 'change email', NULL, NULL, '2026-09-22 23:37:17', '2026-09-22 23:37:17'),
(47, 10, NULL, 0, 0, 0, 0, 'Support request opened — seller product issue.', NULL, NULL, '2026-09-23 00:17:58', '2026-09-23 00:17:58'),
(48, 10, 15, 0, 0, 0, 0, 'hi', NULL, NULL, '2026-09-23 00:17:58', '2026-09-23 00:17:58'),
(49, 10, 41, 1, 0, 0, 0, 'hi', NULL, NULL, '2026-09-23 00:18:34', '2026-09-23 00:18:34'),
(50, 10, 15, 0, 0, 0, 0, 'I am testing chat.', NULL, NULL, '2026-09-23 00:19:53', '2026-09-23 00:19:53'),
(51, 10, 41, 1, 0, 0, 0, 'Ok.', NULL, NULL, '2026-09-23 00:20:57', '2026-09-23 00:20:57'),
(52, 10, 15, 0, 0, 0, 0, 'Did you send a message?', NULL, NULL, '2026-09-23 00:22:09', '2026-09-23 00:22:09'),
(53, 10, 41, 1, 0, 0, 0, 'Yes.', NULL, NULL, '2026-09-23 00:23:02', '2026-09-23 00:23:02'),
(54, 10, 41, 1, 0, 0, 0, 'paid.', NULL, NULL, '2026-09-23 01:49:10', '2026-09-23 01:49:10'),
(55, 9, 41, 1, 0, 0, 0, 'change photo', NULL, NULL, '2026-09-23 04:19:32', '2026-09-23 04:19:32'),
(56, 10, NULL, 1, 0, 0, 0, 'Customer ended the chat.', NULL, NULL, '2026-09-24 00:04:27', '2026-09-24 00:04:27'),
(66, 14, NULL, 0, 0, 0, 0, 'Support request opened about order #44 — item damaged.', NULL, NULL, '2026-09-24 01:02:30', '2026-09-24 01:02:30'),
(67, 14, 17, 0, 0, 0, 0, 'hi, refund.', NULL, NULL, '2026-09-24 01:02:30', '2026-09-24 01:02:30'),
(68, 14, NULL, 0, 0, 0, 0, 'cs (the seller) has joined this chat to help with your order.', NULL, NULL, '2026-09-24 01:12:23', '2026-09-24 01:12:23'),
(69, 14, 15, 1, 1, 0, 0, 'show image.', NULL, NULL, '2026-09-24 01:12:50', '2026-09-24 01:12:50'),
(71, 14, 17, 0, 0, 0, 0, '', '[\"\\/api\\/media\\/file\\/support\\/PN4BpA8Ez1cDZieGbu4eDr6fa4C6RSbvbxEu9jfZ.png\",\"\\/api\\/media\\/file\\/support\\/2a30neBuVBS75Lh3hOwyZvjl4Q9nzY7zoQkMYAp9.png\"]', NULL, '2026-09-24 01:18:22', '2026-09-24 01:18:22'),
(72, 14, 15, 1, 1, 0, 0, 'Ok, refund granted.', NULL, NULL, '2026-09-24 01:18:59', '2026-09-24 01:18:59'),
(73, 14, 15, 1, 1, 0, 0, 'First return pickup', NULL, NULL, '2026-09-24 01:19:19', '2026-09-24 01:19:19'),
(74, 14, 41, 1, 0, 0, 0, 'pickup completed. refund will be granted as gift card, which can be used in next orders.', NULL, NULL, '2026-09-24 01:20:58', '2026-09-24 01:20:58'),
(75, 14, NULL, 1, 0, 0, 1, 'Store credit $500.00 issued for the missing item(s): Laptop side screens.\nGift card: GC-54RK-26XY\nPassword: bicqwbev\nEnter both at checkout on your next order to use the balance.', NULL, NULL, '2026-09-24 01:21:04', '2026-09-24 01:21:04'),
(76, 14, 41, 1, 0, 0, 0, 'Done.', NULL, NULL, '2026-09-24 01:22:50', '2026-09-24 01:22:50'),
(77, 14, NULL, 1, 0, 0, 0, 'Customer ended the chat.', NULL, NULL, '2026-09-24 01:30:11', '2026-09-24 01:30:11'),
(79, 10, NULL, 1, 0, 0, 0, 'Seller ended the chat.', NULL, NULL, '2026-09-24 01:36:21', '2026-09-24 01:36:21'),
(86, 9, 41, 0, 0, 0, 0, 'Ok.', NULL, NULL, '2026-09-24 06:16:36', '2026-09-24 06:16:36');

-- --------------------------------------------------------

--
-- Table structure for table `support_threads`
--

CREATE TABLE `support_threads` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED DEFAULT NULL,
  `seller_shop_id` bigint(20) UNSIGNED DEFAULT NULL,
  `seller_joined_at` timestamp NULL DEFAULT NULL,
  `issue_type` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'open',
  `last_message_at` timestamp NULL DEFAULT NULL,
  `last_staff_message_at` timestamp NULL DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `rating` tinyint(3) UNSIGNED DEFAULT NULL,
  `rating_comment` text DEFAULT NULL,
  `rated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `support_threads`
--

INSERT INTO `support_threads` (`id`, `user_id`, `order_id`, `seller_shop_id`, `seller_joined_at`, `issue_type`, `status`, `last_message_at`, `last_staff_message_at`, `resolved_at`, `rating`, `rating_comment`, `rated_at`, `created_at`, `updated_at`) VALUES
(1, 17, NULL, NULL, NULL, 'item_missing', 'resolved', '2026-09-14 06:26:02', '2026-09-14 06:26:02', '2026-09-14 06:26:02', 5, 'got order.', '2026-09-09 05:47:54', '2026-09-09 02:28:35', '2026-09-24 04:04:24'),
(9, 41, NULL, NULL, NULL, 'seller_product_issue', 'open', '2026-09-24 06:16:36', '2026-09-23 04:19:32', NULL, NULL, NULL, NULL, '2026-09-22 23:37:17', '2026-09-24 06:16:36'),
(10, 15, NULL, NULL, NULL, 'seller_product_issue', 'resolved', '2026-09-24 04:05:22', '2026-09-24 04:05:22', '2026-09-24 04:05:22', NULL, NULL, NULL, '2026-09-23 00:17:58', '2026-09-24 04:05:22'),
(14, 17, 44, 3, '2026-09-24 01:12:23', 'item_damaged', 'resolved', '2026-09-24 01:30:11', '2026-09-24 01:30:11', '2026-09-24 01:30:11', 5, NULL, '2026-09-24 01:30:08', '2026-09-24 01:02:30', '2026-09-24 04:04:24');

-- --------------------------------------------------------

--
-- Table structure for table `trademarks`
--

CREATE TABLE `trademarks` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `shop_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `registration_number` varchar(60) NOT NULL,
  `registration_country` varchar(2) NOT NULL,
  `logo_url` varchar(500) DEFAULT NULL,
  `certificate_path` varchar(255) DEFAULT NULL,
  `status` varchar(12) NOT NULL DEFAULT 'pending',
  `note` varchar(500) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `marketing_opt_out` tinyint(1) NOT NULL DEFAULT 0,
  `stripe_customer_id` varchar(255) DEFAULT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT 0,
  `is_rider` tinyint(1) NOT NULL DEFAULT 0,
  `rider_is_active` tinyint(1) NOT NULL DEFAULT 1,
  `rider_rating_avg` decimal(3,2) DEFAULT NULL,
  `rider_rating_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `rider_declined_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `rider_missed_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `rider_offers_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `rider_daily_target_minutes` smallint(5) UNSIGNED DEFAULT NULL,
  `rider_since` timestamp NULL DEFAULT NULL,
  `rider_available` tinyint(1) NOT NULL DEFAULT 0,
  `rider_unavailable_reason` varchar(200) DEFAULT NULL,
  `rider_last_seen_at` timestamp NULL DEFAULT NULL,
  `rider_base_address` varchar(255) DEFAULT NULL,
  `rider_base_lat` decimal(10,7) DEFAULT NULL,
  `rider_base_lng` decimal(10,7) DEFAULT NULL,
  `rider_last_lat` decimal(10,7) DEFAULT NULL,
  `rider_last_lng` decimal(10,7) DEFAULT NULL,
  `rider_last_located_at` timestamp NULL DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `rider_payout_method` varchar(20) DEFAULT NULL,
  `rider_payout_details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`rider_payout_details`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `phone`, `marketing_opt_out`, `stripe_customer_id`, `is_admin`, `is_rider`, `rider_is_active`, `rider_rating_avg`, `rider_rating_count`, `rider_declined_count`, `rider_missed_count`, `rider_offers_count`, `rider_daily_target_minutes`, `rider_since`, `rider_available`, `rider_unavailable_reason`, `rider_last_seen_at`, `rider_base_address`, `rider_base_lat`, `rider_base_lng`, `rider_last_lat`, `rider_last_lng`, `rider_last_located_at`, `email_verified_at`, `password`, `remember_token`, `created_at`, `updated_at`, `rider_payout_method`, `rider_payout_details`) VALUES
(15, 'Test User', 'seller@example.com', '+15551234567', 0, 'cus_VEAEdiTnuYPjHz', 0, 0, 1, NULL, 0, 0, 0, 0, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-14 23:45:34', '$2y$12$g5tHJ.DFCrrpKZljpURgPOLI45v01uAYIMzGZsLQ4PYlgosrxpM5.', NULL, '2026-09-09 01:12:48', '2026-09-22 05:01:19', NULL, NULL),
(16, 'Sam Rider', 'rider@example.com', NULL, 0, NULL, 0, 1, 1, NULL, 0, 0, 2, 9, NULL, '2026-09-10 01:17:26', 1, NULL, '2026-09-23 23:50:20', NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-14 23:45:34', '$2y$12$Kpz.EmIfJK7h7AZfl8iL2e01Sj7LyLQnSH0hWXa28jW9oyZp1Uu52', NULL, '2026-09-09 01:12:48', '2026-09-23 23:50:54', NULL, NULL),
(17, 'Testcaresort', 'testcaresort@outlook.com', '+15551234567', 1, 'cus_VE8eecdx05e2pN', 0, 0, 1, NULL, 0, 0, 0, 0, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '$2y$12$skX3B4/nh5s/002bOYkfZ.uO5eeXCokTCYPh.8Q.Z28wpSnfcUDvy', NULL, '2026-09-09 02:25:33', '2026-09-24 23:55:36', NULL, NULL),
(18, 'New Ride', 'new_ride@example.com', NULL, 0, NULL, 0, 1, 1, NULL, 0, 0, 0, 0, 480, '2026-09-09 05:14:36', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '$2y$12$kvtzqkILF65K1f7Zekch8ux7wHqhyNNSBFQG0zutKPpuhtETcpLZe', NULL, '2026-09-09 05:14:36', '2026-09-11 01:33:45', NULL, NULL),
(19, 'Ride Example', 'ride_example@gmail.com', NULL, 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, NULL, '2026-09-09 05:15:06', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '$2y$12$s8TjsvlZWoF3ROrlOuTM2ucqQDyws.3wK9rRNdYSEe4Y6x/JnNos2', NULL, '2026-09-09 05:15:06', '2026-09-11 01:34:32', NULL, NULL),
(28, 'UI Admin', 'uiadmin@ex.com', NULL, 0, NULL, 1, 0, 1, NULL, 0, 0, 0, 0, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '$2y$12$V6kPxpexIX6LF07gDN.9I.z9ssx/seSKPat5GgisFgIzNM9l0mhTW', NULL, '2026-09-10 02:14:06', '2026-09-10 02:14:06', NULL, NULL),
(41, 'Test User', 'test@example.com', '+15551234567', 0, NULL, 1, 0, 1, NULL, 0, 0, 0, 0, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '$2y$12$ZtKk3r06.tNkAJS54MLOWOj8dFxBxWM1z0iAoaJiLd73bngIwyxSi', NULL, '2026-09-22 04:57:03', '2026-09-22 04:57:03', NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `addresses`
--
ALTER TABLE `addresses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `addresses_user_id_is_default_index` (`user_id`,`is_default`);

--
-- Indexes for table `auth_otps`
--
ALTER TABLE `auth_otps`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `auth_otps_email_purpose_unique` (`email`,`purpose`);

--
-- Indexes for table `banners`
--
ALTER TABLE `banners`
  ADD PRIMARY KEY (`id`),
  ADD KEY `banners_is_active_sort_order_index` (`is_active`,`sort_order`);

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
-- Indexes for table `carts`
--
ALTER TABLE `carts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `carts_user_id_unique` (`user_id`);

--
-- Indexes for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cart_items_cart_id_product_id_product_variant_id_unique` (`cart_id`,`product_id`,`product_variant_id`),
  ADD KEY `cart_items_product_id_foreign` (`product_id`),
  ADD KEY `cart_items_product_variant_id_foreign` (`product_variant_id`),
  ADD KEY `cart_items_cart_id_index` (`cart_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `categories_slug_unique` (`slug`),
  ADD KEY `categories_is_active_index` (`is_active`);

--
-- Indexes for table `customer_emails`
--
ALTER TABLE `customer_emails`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_emails_order_id_foreign` (`order_id`),
  ADD KEY `customer_emails_campaign_id_foreign` (`campaign_id`),
  ADD KEY `customer_emails_user_id_created_at_index` (`user_id`,`created_at`);

--
-- Indexes for table `email_campaigns`
--
ALTER TABLE `email_campaigns`
  ADD PRIMARY KEY (`id`),
  ADD KEY `email_campaigns_created_by_foreign` (`created_by`),
  ADD KEY `email_campaigns_status_send_at_index` (`status`,`send_at`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
  ADD KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`);

--
-- Indexes for table `gift_cards`
--
ALTER TABLE `gift_cards`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `gift_cards_code_unique` (`code`),
  ADD KEY `gift_cards_issued_by_foreign` (`issued_by`),
  ADD KEY `gift_cards_support_thread_id_foreign` (`support_thread_id`),
  ADD KEY `gift_cards_order_id_foreign` (`order_id`),
  ADD KEY `gift_cards_user_id_is_active_index` (`user_id`,`is_active`);

--
-- Indexes for table `gift_card_redemptions`
--
ALTER TABLE `gift_card_redemptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `gift_card_redemptions_gift_card_id_foreign` (`gift_card_id`),
  ADD KEY `gift_card_redemptions_order_id_foreign` (`order_id`);

--
-- Indexes for table `home_tiles`
--
ALTER TABLE `home_tiles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `home_tiles_is_active_sort_order_index` (`is_active`,`sort_order`);

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
-- Indexes for table `label_requests`
--
ALTER TABLE `label_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `label_requests_order_id_foreign` (`order_id`),
  ADD KEY `label_requests_shop_id_foreign` (`shop_id`),
  ADD KEY `label_requests_ship_from_address_id_foreign` (`ship_from_address_id`),
  ADD KEY `label_requests_order_package_id_foreign` (`order_package_id`),
  ADD KEY `label_requests_handled_by_foreign` (`handled_by`),
  ADD KEY `label_requests_status_created_at_index` (`status`,`created_at`),
  ADD KEY `label_requests_label_template_id_foreign` (`label_template_id`);

--
-- Indexes for table `label_templates`
--
ALTER TABLE `label_templates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `orders_stripe_payment_intent_id_unique` (`stripe_payment_intent_id`),
  ADD KEY `orders_user_id_foreign` (`user_id`),
  ADD KEY `orders_status_index` (`status`),
  ADD KEY `orders_payment_status_index` (`payment_status`),
  ADD KEY `orders_payment_method_index` (`payment_method`),
  ADD KEY `orders_delivery_partner_id_foreign` (`delivery_partner_id`),
  ADD KEY `orders_store_id_foreign` (`store_id`),
  ADD KEY `orders_rider_offer_expires_at_index` (`rider_offer_expires_at`),
  ADD KEY `orders_market_index` (`market`);

--
-- Indexes for table `order_address_changes`
--
ALTER TABLE `order_address_changes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_address_changes_decided_by_shop_id_foreign` (`decided_by_shop_id`),
  ADD KEY `order_address_changes_decided_by_user_id_foreign` (`decided_by_user_id`),
  ADD KEY `order_address_changes_order_id_status_index` (`order_id`,`status`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_items_order_id_foreign` (`order_id`),
  ADD KEY `order_items_product_id_foreign` (`product_id`),
  ADD KEY `order_items_product_variant_id_foreign` (`product_variant_id`),
  ADD KEY `order_items_shop_id_foreign` (`shop_id`);

--
-- Indexes for table `order_packages`
--
ALTER TABLE `order_packages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_packages_order_id_foreign` (`order_id`),
  ADD KEY `order_packages_ship_from_address_id_foreign` (`ship_from_address_id`),
  ADD KEY `order_packages_shop_id_status_index` (`shop_id`,`status`);

--
-- Indexes for table `order_package_items`
--
ALTER TABLE `order_package_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_package_items_order_package_id_foreign` (`order_package_id`),
  ADD KEY `order_package_items_order_item_id_foreign` (`order_item_id`);

--
-- Indexes for table `order_refunds`
--
ALTER TABLE `order_refunds`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_refunds_order_id_foreign` (`order_id`),
  ADD KEY `order_refunds_support_thread_id_foreign` (`support_thread_id`),
  ADD KEY `order_refunds_created_by_foreign` (`created_by`);

--
-- Indexes for table `order_shop_shipping`
--
ALTER TABLE `order_shop_shipping`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_shop_shipping_order_id_shop_id_unique` (`order_id`,`shop_id`),
  ADD KEY `order_shop_shipping_shop_id_foreign` (`shop_id`);

--
-- Indexes for table `pages`
--
ALTER TABLE `pages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `pages_slug_unique` (`slug`),
  ADD KEY `pages_is_published_show_in_footer_sort_order_index` (`is_published`,`show_in_footer`,`sort_order`),
  ADD KEY `pages_parent_slug_index` (`parent_slug`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `payout_requests`
--
ALTER TABLE `payout_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `payout_requests_ledger_entry_id_foreign` (`ledger_entry_id`),
  ADD KEY `payout_requests_processed_by_foreign` (`processed_by`),
  ADD KEY `payout_requests_status_created_at_index` (`status`,`created_at`),
  ADD KEY `payout_requests_shop_id_status_index` (`shop_id`,`status`);

--
-- Indexes for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  ADD KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  ADD KEY `personal_access_tokens_expires_at_index` (`expires_at`);

--
-- Indexes for table `price_change_records`
--
ALTER TABLE `price_change_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `price_change_records_product_id_foreign` (`product_id`),
  ADD KEY `price_change_records_product_variant_id_foreign` (`product_variant_id`),
  ADD KEY `price_change_records_sales_boost_offer_id_foreign` (`sales_boost_offer_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `products_slug_unique` (`slug`),
  ADD UNIQUE KEY `products_sku_unique` (`sku`),
  ADD KEY `products_category_id_foreign` (`category_id`),
  ADD KEY `products_is_active_index` (`is_active`),
  ADD KEY `products_shop_id_foreign` (`shop_id`),
  ADD KEY `products_shipping_template_id_foreign` (`shipping_template_id`),
  ADD KEY `products_market_index` (`market`),
  ADD KEY `products_trademark_id_index` (`trademark_id`);

--
-- Indexes for table `product_images`
--
ALTER TABLE `product_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_images_product_id_sort_order_index` (`product_id`,`sort_order`);

--
-- Indexes for table `product_reviews`
--
ALTER TABLE `product_reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `product_reviews_order_item_id_unique` (`order_item_id`),
  ADD KEY `product_reviews_order_id_foreign` (`order_id`),
  ADD KEY `product_reviews_product_id_status_index` (`product_id`,`status`),
  ADD KEY `product_reviews_user_id_status_index` (`user_id`,`status`),
  ADD KEY `product_reviews_status_index` (`status`);

--
-- Indexes for table `product_upload_tasks`
--
ALTER TABLE `product_upload_tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_upload_tasks_shop_id_foreign` (`shop_id`);

--
-- Indexes for table `product_variants`
--
ALTER TABLE `product_variants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `product_variants_sku_unique` (`sku`),
  ADD KEY `product_variants_product_id_sort_order_index` (`product_id`,`sort_order`),
  ADD KEY `product_variants_is_active_index` (`is_active`);

--
-- Indexes for table `review_helpful_votes`
--
ALTER TABLE `review_helpful_votes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `review_helpful_votes_product_review_id_user_id_unique` (`product_review_id`,`user_id`),
  ADD KEY `review_helpful_votes_user_id_foreign` (`user_id`);

--
-- Indexes for table `rider_applications`
--
ALTER TABLE `rider_applications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `rider_applications_user_id_unique` (`user_id`),
  ADD KEY `rider_applications_store_id_foreign` (`store_id`),
  ADD KEY `rider_applications_reviewed_by_foreign` (`reviewed_by`),
  ADD KEY `rider_applications_status_created_at_index` (`status`,`created_at`);

--
-- Indexes for table `rider_ledger_entries`
--
ALTER TABLE `rider_ledger_entries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `rider_ledger_entries_created_by_foreign` (`created_by`),
  ADD KEY `rider_ledger_entries_user_id_created_at_index` (`user_id`,`created_at`),
  ADD KEY `rider_ledger_entries_order_id_type_index` (`order_id`,`type`);

--
-- Indexes for table `rider_payout_requests`
--
ALTER TABLE `rider_payout_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `rider_payout_requests_ledger_entry_id_foreign` (`ledger_entry_id`),
  ADD KEY `rider_payout_requests_processed_by_foreign` (`processed_by`),
  ADD KEY `rider_payout_requests_status_created_at_index` (`status`,`created_at`),
  ADD KEY `rider_payout_requests_user_id_status_index` (`user_id`,`status`);

--
-- Indexes for table `rider_reviews`
--
ALTER TABLE `rider_reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `rider_reviews_order_id_unique` (`order_id`),
  ADD KEY `rider_reviews_user_id_foreign` (`user_id`),
  ADD KEY `rider_reviews_rider_id_created_at_index` (`rider_id`,`created_at`);

--
-- Indexes for table `rider_shifts`
--
ALTER TABLE `rider_shifts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `rider_shifts_user_id_clock_in_at_index` (`user_id`,`clock_in_at`);

--
-- Indexes for table `rider_shift_breaks`
--
ALTER TABLE `rider_shift_breaks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `rider_shift_breaks_rider_shift_id_index` (`rider_shift_id`);

--
-- Indexes for table `rider_store`
--
ALTER TABLE `rider_store`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `rider_store_user_id_store_id_unique` (`user_id`,`store_id`),
  ADD KEY `rider_store_store_id_foreign` (`store_id`);

--
-- Indexes for table `sales_boost_offers`
--
ALTER TABLE `sales_boost_offers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sales_boost_offers_product_variant_id_foreign` (`product_variant_id`),
  ADD KEY `sales_boost_offers_created_by_foreign` (`created_by`),
  ADD KEY `sales_boost_offers_product_id_status_index` (`product_id`,`status`);

--
-- Indexes for table `sellers`
--
ALTER TABLE `sellers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sellers_user_id_unique` (`user_id`),
  ADD KEY `sellers_reviewed_by_foreign` (`reviewed_by`),
  ADD KEY `sellers_status_index` (`status`),
  ADD KEY `sellers_country_index` (`country`);

--
-- Indexes for table `seller_ledger_entries`
--
ALTER TABLE `seller_ledger_entries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `seller_ledger_entries_created_by_foreign` (`created_by`),
  ADD KEY `seller_ledger_entries_shop_id_created_at_index` (`shop_id`,`created_at`),
  ADD KEY `seller_ledger_entries_order_id_type_index` (`order_id`,`type`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`key`);

--
-- Indexes for table `shipments`
--
ALTER TABLE `shipments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `shipments_order_id_unique` (`order_id`);

--
-- Indexes for table `shipping_templates`
--
ALTER TABLE `shipping_templates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `shipping_templates_shop_id_foreign` (`shop_id`),
  ADD KEY `shipping_templates_shop_address_id_foreign` (`shop_address_id`);

--
-- Indexes for table `shipping_template_groups`
--
ALTER TABLE `shipping_template_groups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `shipping_template_groups_shipping_template_id_foreign` (`shipping_template_id`);

--
-- Indexes for table `shops`
--
ALTER TABLE `shops`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `shops_seller_id_unique` (`seller_id`),
  ADD UNIQUE KEY `shops_slug_unique` (`slug`),
  ADD UNIQUE KEY `shops_shop_code_unique` (`shop_code`),
  ADD KEY `shops_category_id_foreign` (`category_id`),
  ADD KEY `shops_market_index` (`market`),
  ADD KEY `shops_label_template_id_foreign` (`label_template_id`);

--
-- Indexes for table `shop_addresses`
--
ALTER TABLE `shop_addresses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `shop_addresses_shop_id_foreign` (`shop_id`);

--
-- Indexes for table `site_feedback`
--
ALTER TABLE `site_feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `site_feedback_user_id_foreign` (`user_id`);

--
-- Indexes for table `stores`
--
ALTER TABLE `stores`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `store_decorations`
--
ALTER TABLE `store_decorations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `store_decorations_shop_id_platform_is_live_index` (`shop_id`,`platform`,`is_live`),
  ADD KEY `store_decorations_status_index` (`status`);

--
-- Indexes for table `store_inventory`
--
ALTER TABLE `store_inventory`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `store_inventory_store_id_product_id_product_variant_id_unique` (`store_id`,`product_id`,`product_variant_id`),
  ADD KEY `store_inventory_product_variant_id_foreign` (`product_variant_id`),
  ADD KEY `store_inventory_product_id_store_id_index` (`product_id`,`store_id`);

--
-- Indexes for table `stripe_events`
--
ALTER TABLE `stripe_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `stripe_events_type_index` (`type`);

--
-- Indexes for table `support_messages`
--
ALTER TABLE `support_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `support_messages_user_id_foreign` (`user_id`),
  ADD KEY `support_messages_support_thread_id_id_index` (`support_thread_id`,`id`);

--
-- Indexes for table `support_threads`
--
ALTER TABLE `support_threads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `support_threads_user_id_foreign` (`user_id`),
  ADD KEY `support_threads_order_id_foreign` (`order_id`),
  ADD KEY `support_threads_status_index` (`status`),
  ADD KEY `support_threads_last_message_at_index` (`last_message_at`),
  ADD KEY `support_threads_seller_shop_id_foreign` (`seller_shop_id`);

--
-- Indexes for table `trademarks`
--
ALTER TABLE `trademarks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `trademarks_shop_id_status_index` (`shop_id`,`status`);

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
-- AUTO_INCREMENT for table `addresses`
--
ALTER TABLE `addresses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `auth_otps`
--
ALTER TABLE `auth_otps`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `banners`
--
ALTER TABLE `banners`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `carts`
--
ALTER TABLE `carts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `cart_items`
--
ALTER TABLE `cart_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `customer_emails`
--
ALTER TABLE `customer_emails`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `email_campaigns`
--
ALTER TABLE `email_campaigns`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `gift_cards`
--
ALTER TABLE `gift_cards`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `gift_card_redemptions`
--
ALTER TABLE `gift_card_redemptions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `home_tiles`
--
ALTER TABLE `home_tiles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `label_requests`
--
ALTER TABLE `label_requests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `label_templates`
--
ALTER TABLE `label_templates`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=126;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- AUTO_INCREMENT for table `order_address_changes`
--
ALTER TABLE `order_address_changes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=67;

--
-- AUTO_INCREMENT for table `order_packages`
--
ALTER TABLE `order_packages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `order_package_items`
--
ALTER TABLE `order_package_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `order_refunds`
--
ALTER TABLE `order_refunds`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `order_shop_shipping`
--
ALTER TABLE `order_shop_shipping`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `pages`
--
ALTER TABLE `pages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `payout_requests`
--
ALTER TABLE `payout_requests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=315;

--
-- AUTO_INCREMENT for table `price_change_records`
--
ALTER TABLE `price_change_records`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=98;

--
-- AUTO_INCREMENT for table `product_images`
--
ALTER TABLE `product_images`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `product_reviews`
--
ALTER TABLE `product_reviews`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_upload_tasks`
--
ALTER TABLE `product_upload_tasks`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `product_variants`
--
ALTER TABLE `product_variants`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `review_helpful_votes`
--
ALTER TABLE `review_helpful_votes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rider_applications`
--
ALTER TABLE `rider_applications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `rider_ledger_entries`
--
ALTER TABLE `rider_ledger_entries`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `rider_payout_requests`
--
ALTER TABLE `rider_payout_requests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `rider_reviews`
--
ALTER TABLE `rider_reviews`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `rider_shifts`
--
ALTER TABLE `rider_shifts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `rider_shift_breaks`
--
ALTER TABLE `rider_shift_breaks`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `rider_store`
--
ALTER TABLE `rider_store`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `sales_boost_offers`
--
ALTER TABLE `sales_boost_offers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `sellers`
--
ALTER TABLE `sellers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `seller_ledger_entries`
--
ALTER TABLE `seller_ledger_entries`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `shipments`
--
ALTER TABLE `shipments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `shipping_templates`
--
ALTER TABLE `shipping_templates`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `shipping_template_groups`
--
ALTER TABLE `shipping_template_groups`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `shops`
--
ALTER TABLE `shops`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `shop_addresses`
--
ALTER TABLE `shop_addresses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `site_feedback`
--
ALTER TABLE `site_feedback`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stores`
--
ALTER TABLE `stores`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `store_decorations`
--
ALTER TABLE `store_decorations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `store_inventory`
--
ALTER TABLE `store_inventory`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `support_messages`
--
ALTER TABLE `support_messages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=92;

--
-- AUTO_INCREMENT for table `support_threads`
--
ALTER TABLE `support_threads`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `trademarks`
--
ALTER TABLE `trademarks`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `addresses`
--
ALTER TABLE `addresses`
  ADD CONSTRAINT `addresses_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `carts`
--
ALTER TABLE `carts`
  ADD CONSTRAINT `carts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD CONSTRAINT `cart_items_cart_id_foreign` FOREIGN KEY (`cart_id`) REFERENCES `carts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `cart_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `cart_items_product_variant_id_foreign` FOREIGN KEY (`product_variant_id`) REFERENCES `product_variants` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `customer_emails`
--
ALTER TABLE `customer_emails`
  ADD CONSTRAINT `customer_emails_campaign_id_foreign` FOREIGN KEY (`campaign_id`) REFERENCES `email_campaigns` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `customer_emails_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `customer_emails_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `email_campaigns`
--
ALTER TABLE `email_campaigns`
  ADD CONSTRAINT `email_campaigns_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `gift_cards`
--
ALTER TABLE `gift_cards`
  ADD CONSTRAINT `gift_cards_issued_by_foreign` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `gift_cards_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `gift_cards_support_thread_id_foreign` FOREIGN KEY (`support_thread_id`) REFERENCES `support_threads` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `gift_cards_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `gift_card_redemptions`
--
ALTER TABLE `gift_card_redemptions`
  ADD CONSTRAINT `gift_card_redemptions_gift_card_id_foreign` FOREIGN KEY (`gift_card_id`) REFERENCES `gift_cards` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `gift_card_redemptions_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `label_requests`
--
ALTER TABLE `label_requests`
  ADD CONSTRAINT `label_requests_handled_by_foreign` FOREIGN KEY (`handled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `label_requests_label_template_id_foreign` FOREIGN KEY (`label_template_id`) REFERENCES `label_templates` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `label_requests_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `label_requests_order_package_id_foreign` FOREIGN KEY (`order_package_id`) REFERENCES `order_packages` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `label_requests_ship_from_address_id_foreign` FOREIGN KEY (`ship_from_address_id`) REFERENCES `shop_addresses` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `label_requests_shop_id_foreign` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_delivery_partner_id_foreign` FOREIGN KEY (`delivery_partner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `orders_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `orders_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `order_address_changes`
--
ALTER TABLE `order_address_changes`
  ADD CONSTRAINT `order_address_changes_decided_by_shop_id_foreign` FOREIGN KEY (`decided_by_shop_id`) REFERENCES `shops` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `order_address_changes_decided_by_user_id_foreign` FOREIGN KEY (`decided_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `order_address_changes_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `order_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `order_items_product_variant_id_foreign` FOREIGN KEY (`product_variant_id`) REFERENCES `product_variants` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `order_items_shop_id_foreign` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `order_packages`
--
ALTER TABLE `order_packages`
  ADD CONSTRAINT `order_packages_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_packages_ship_from_address_id_foreign` FOREIGN KEY (`ship_from_address_id`) REFERENCES `shop_addresses` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `order_packages_shop_id_foreign` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `order_package_items`
--
ALTER TABLE `order_package_items`
  ADD CONSTRAINT `order_package_items_order_item_id_foreign` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_package_items_order_package_id_foreign` FOREIGN KEY (`order_package_id`) REFERENCES `order_packages` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `order_refunds`
--
ALTER TABLE `order_refunds`
  ADD CONSTRAINT `order_refunds_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `order_refunds_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `order_refunds_support_thread_id_foreign` FOREIGN KEY (`support_thread_id`) REFERENCES `support_threads` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `order_shop_shipping`
--
ALTER TABLE `order_shop_shipping`
  ADD CONSTRAINT `order_shop_shipping_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_shop_shipping_shop_id_foreign` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payout_requests`
--
ALTER TABLE `payout_requests`
  ADD CONSTRAINT `payout_requests_ledger_entry_id_foreign` FOREIGN KEY (`ledger_entry_id`) REFERENCES `seller_ledger_entries` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `payout_requests_processed_by_foreign` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `payout_requests_shop_id_foreign` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `price_change_records`
--
ALTER TABLE `price_change_records`
  ADD CONSTRAINT `price_change_records_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `price_change_records_product_variant_id_foreign` FOREIGN KEY (`product_variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `price_change_records_sales_boost_offer_id_foreign` FOREIGN KEY (`sales_boost_offer_id`) REFERENCES `sales_boost_offers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `products_shipping_template_id_foreign` FOREIGN KEY (`shipping_template_id`) REFERENCES `shipping_templates` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `products_shop_id_foreign` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `product_images`
--
ALTER TABLE `product_images`
  ADD CONSTRAINT `product_images_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_reviews`
--
ALTER TABLE `product_reviews`
  ADD CONSTRAINT `product_reviews_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `product_reviews_order_item_id_foreign` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `product_reviews_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_reviews_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_upload_tasks`
--
ALTER TABLE `product_upload_tasks`
  ADD CONSTRAINT `product_upload_tasks_shop_id_foreign` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_variants`
--
ALTER TABLE `product_variants`
  ADD CONSTRAINT `product_variants_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `review_helpful_votes`
--
ALTER TABLE `review_helpful_votes`
  ADD CONSTRAINT `review_helpful_votes_product_review_id_foreign` FOREIGN KEY (`product_review_id`) REFERENCES `product_reviews` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `review_helpful_votes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `rider_applications`
--
ALTER TABLE `rider_applications`
  ADD CONSTRAINT `rider_applications_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `rider_applications_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `rider_applications_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `rider_ledger_entries`
--
ALTER TABLE `rider_ledger_entries`
  ADD CONSTRAINT `rider_ledger_entries_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `rider_ledger_entries_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `rider_ledger_entries_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `rider_payout_requests`
--
ALTER TABLE `rider_payout_requests`
  ADD CONSTRAINT `rider_payout_requests_ledger_entry_id_foreign` FOREIGN KEY (`ledger_entry_id`) REFERENCES `rider_ledger_entries` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `rider_payout_requests_processed_by_foreign` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `rider_payout_requests_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `rider_reviews`
--
ALTER TABLE `rider_reviews`
  ADD CONSTRAINT `rider_reviews_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `rider_reviews_rider_id_foreign` FOREIGN KEY (`rider_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `rider_reviews_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `rider_shifts`
--
ALTER TABLE `rider_shifts`
  ADD CONSTRAINT `rider_shifts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `rider_shift_breaks`
--
ALTER TABLE `rider_shift_breaks`
  ADD CONSTRAINT `rider_shift_breaks_rider_shift_id_foreign` FOREIGN KEY (`rider_shift_id`) REFERENCES `rider_shifts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `rider_store`
--
ALTER TABLE `rider_store`
  ADD CONSTRAINT `rider_store_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `rider_store_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `sales_boost_offers`
--
ALTER TABLE `sales_boost_offers`
  ADD CONSTRAINT `sales_boost_offers_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `sales_boost_offers_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sales_boost_offers_product_variant_id_foreign` FOREIGN KEY (`product_variant_id`) REFERENCES `product_variants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sellers`
--
ALTER TABLE `sellers`
  ADD CONSTRAINT `sellers_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `sellers_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `seller_ledger_entries`
--
ALTER TABLE `seller_ledger_entries`
  ADD CONSTRAINT `seller_ledger_entries_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `seller_ledger_entries_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `seller_ledger_entries_shop_id_foreign` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `shipments`
--
ALTER TABLE `shipments`
  ADD CONSTRAINT `shipments_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `shipping_templates`
--
ALTER TABLE `shipping_templates`
  ADD CONSTRAINT `shipping_templates_shop_address_id_foreign` FOREIGN KEY (`shop_address_id`) REFERENCES `shop_addresses` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `shipping_templates_shop_id_foreign` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `shipping_template_groups`
--
ALTER TABLE `shipping_template_groups`
  ADD CONSTRAINT `shipping_template_groups_shipping_template_id_foreign` FOREIGN KEY (`shipping_template_id`) REFERENCES `shipping_templates` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `shops`
--
ALTER TABLE `shops`
  ADD CONSTRAINT `shops_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `shops_label_template_id_foreign` FOREIGN KEY (`label_template_id`) REFERENCES `label_templates` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `shops_seller_id_foreign` FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `shop_addresses`
--
ALTER TABLE `shop_addresses`
  ADD CONSTRAINT `shop_addresses_shop_id_foreign` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `site_feedback`
--
ALTER TABLE `site_feedback`
  ADD CONSTRAINT `site_feedback_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `store_decorations`
--
ALTER TABLE `store_decorations`
  ADD CONSTRAINT `store_decorations_shop_id_foreign` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `store_inventory`
--
ALTER TABLE `store_inventory`
  ADD CONSTRAINT `store_inventory_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `store_inventory_product_variant_id_foreign` FOREIGN KEY (`product_variant_id`) REFERENCES `product_variants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `store_inventory_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `support_messages`
--
ALTER TABLE `support_messages`
  ADD CONSTRAINT `support_messages_support_thread_id_foreign` FOREIGN KEY (`support_thread_id`) REFERENCES `support_threads` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `support_messages_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `support_threads`
--
ALTER TABLE `support_threads`
  ADD CONSTRAINT `support_threads_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `support_threads_seller_shop_id_foreign` FOREIGN KEY (`seller_shop_id`) REFERENCES `shops` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `support_threads_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `trademarks`
--
ALTER TABLE `trademarks`
  ADD CONSTRAINT `trademarks_shop_id_foreign` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
