-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:3306
-- Tiempo de generación: 04-08-2026 a las 10:02:58
-- Versión del servidor: 10.6.27-MariaDB-cll-lve
-- Versión de PHP: 8.4.23

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `portally_operaciones`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `avances_nave`
--

CREATE TABLE `avances_nave` (
  `id` int(10) UNSIGNED NOT NULL,
  `nave_id` int(10) UNSIGNED NOT NULL,
  `fecha_turno` date DEFAULT NULL,
  `turno` enum('Mañana','Noche') DEFAULT NULL,
  `directa_tm` decimal(12,2) DEFAULT NULL,
  `indirecta_tm` decimal(12,2) DEFAULT NULL,
  `despacho_tm` decimal(12,2) DEFAULT NULL,
  `bodegas` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`bodegas`)),
  `descripcion_avance` text DEFAULT NULL,
  `registrado_por` varchar(100) NOT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `campos_tipo_nave`
--

CREATE TABLE `campos_tipo_nave` (
  `id` int(10) UNSIGNED NOT NULL,
  `tipo_nave_id` int(10) UNSIGNED NOT NULL,
  `clave` varchar(50) NOT NULL,
  `etiqueta` varchar(100) NOT NULL,
  `tipo_dato` enum('texto','numero','fecha','booleano','seleccion') NOT NULL DEFAULT 'texto',
  `requerido` tinyint(1) NOT NULL DEFAULT 0,
  `opciones` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`opciones`)),
  `orden` int(11) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `naves`
--

CREATE TABLE `naves` (
  `id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `muelle` varchar(120) DEFAULT NULL,
  `tipo_nave_id` int(10) UNSIGNED NOT NULL,
  `actividad_id` int(10) UNSIGNED DEFAULT NULL,
  `eta` datetime DEFAULT NULL,
  `etb` datetime DEFAULT NULL,
  `etd` datetime DEFAULT NULL,
  `estado` enum('Programada','En Puerto','En Operaciones','Finalizada') NOT NULL DEFAULT 'Programada',
  `datos_adicionales` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`datos_adicionales`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `naves`
--

INSERT INTO `naves` (`id`, `nombre`, `muelle`, `tipo_nave_id`, `actividad_id`, `eta`, `etb`, `etd`, `estado`, `datos_adicionales`, `created_at`, `updated_at`) VALUES
(12, 'ML MAGPIE', 'Berth 02', 5, 6, '2026-06-08 13:00:00', NULL, '2026-06-16 14:50:00', 'Finalizada', '{\"cantidad_total\":30250,\"tipo_carga\":\"Big Bags\",\"bodegas_uniforme\":true}', '2026-06-08 02:46:42', '2026-06-19 19:50:21'),
(13, 'DRACO FAITH V2808', 'Berth 03', 6, 6, '2026-06-11 12:00:00', '2026-06-12 15:31:00', '2026-06-13 15:32:00', 'Finalizada', '{\"cantidad_total\":3040,\"tipo_carga\":\"Nitrato\",\"bodegas_uniforme\":true}', '2026-06-08 02:48:56', '2026-06-17 13:09:54'),
(14, 'LAKONIA', 'Berth 03', 1, 1, '2026-06-09 21:49:00', NULL, '2026-06-10 17:00:00', 'Finalizada', '{\"teus\":600,\"tipo_carga\":\"Contenedores General\"}', '2026-06-08 02:50:08', '2026-06-17 13:09:54'),
(15, 'TIAN XIANG HE', 'Berth 3.5', 1, 1, '2026-06-10 13:00:00', '2026-06-10 16:20:00', '2026-06-11 16:21:00', 'Finalizada', '{\"teus\":1222,\"tipo_carga\":\"Contenedores General\",\"cantidad_total\":1222}', '2026-06-08 02:52:12', '2026-06-17 13:09:54'),
(17, 'PING AN CHENG MFT 2026-169', 'Berth 02', 6, 7, '2026-06-13 10:00:00', NULL, '2026-06-19 14:06:00', 'Finalizada', '{\"cantidad_total\":2315,\"tipo_carga\":\"Bultos\",\"bodegas_uniforme\":true}', '2026-06-08 02:56:25', '2026-06-19 19:07:07'),
(18, 'ECO MARIN', 'Berth 04', 1, 1, '2026-06-13 23:00:00', '2026-06-13 16:35:00', '2026-06-13 16:36:00', 'Finalizada', '{\"teus\":100,\"tipo_carga\":\"Contenedores IMOS\",\"cantidad_total\":180}', '2026-06-08 02:57:51', '2026-06-17 13:09:54'),
(20, 'CMA CGM HELIUM', 'Berth 04', 1, 1, '2026-06-14 07:00:00', NULL, '2026-06-14 16:52:00', 'Finalizada', '{\"teus\":250,\"tipo_carga\":\"Contenedores General\",\"tipo_operacion\":\"Mixto\"}', '2026-06-08 03:00:52', '2026-06-17 13:09:54'),
(21, 'YANTIAN 132W', 'Berth 04', 1, 1, '2026-06-09 15:49:00', NULL, '2026-06-10 16:00:00', 'Finalizada', '{\"teus\":2200,\"tipo_carga\":\"Contenedores General\"}', '2026-06-10 00:28:04', '2026-06-17 13:09:54'),
(22, 'GUANG WU KOU V6', 'Berth 03', 4, 8, '2026-06-16 22:00:00', '2026-06-16 11:35:00', '2026-06-17 11:41:00', 'Finalizada', '{\"vehiculos\":1882,\"tipo_carga\":\"Carga Rodante\",\"cantidad_total\":1882}', '2026-06-10 14:13:10', '2026-06-19 16:41:55'),
(23, 'LAKONIA', 'Berth 3.5', 1, 1, '2026-06-14 18:00:00', '2026-06-15 16:06:53', '2026-06-10 16:09:00', 'Finalizada', '{\"teus\":900,\"tipo_carga\":\"Contenedores General\",\"cantidad_total\":900}', '2026-06-10 14:15:52', '2026-06-17 13:09:54'),
(26, 'LAKONIA', 'Berth 04', 1, 1, '2026-06-14 18:36:00', '2026-06-14 16:56:00', '2026-06-15 17:05:00', 'Finalizada', '{\"teus\":900,\"tipo_carga\":\"Contenedores General\",\"cantidad_total\":900}', '2026-06-15 12:36:55', '2026-06-17 13:09:54'),
(27, 'COSCO MALAYSIA', 'Berth 04', 1, 1, '2026-06-15 07:00:00', NULL, '2026-06-15 07:44:00', 'Finalizada', '{\"teus\":2000,\"tipo_carga\":\"Contenedores General\"}', '2026-06-15 21:59:09', '2026-06-17 13:09:54'),
(30, 'EA GATUN', 'Berth 04', 1, NULL, NULL, '2026-06-16 07:57:00', '2026-06-17 19:51:00', 'Finalizada', '{\"cantidad_total\":442}', '2026-06-17 12:59:31', '2026-06-18 00:51:07'),
(37, 'NORVIC HOUSTON V262602', 'Berth 01', 2, NULL, NULL, '2026-06-19 14:11:00', '2026-06-20 08:02:00', 'Finalizada', '{\"cantidad_total\":37400}', '2026-06-19 17:45:04', '2026-06-21 13:11:47'),
(38, 'BBC RAINBOW/1275054', 'Berth 03', 6, NULL, NULL, '2026-06-18 13:45:00', '2026-06-19 19:45:00', 'Finalizada', '{\"cantidad_total\":111}', '2026-06-19 18:48:09', '2026-06-20 00:46:11'),
(40, 'GEORGIA ATH V002-003', 'Berth 02', 2, 2, '2026-06-19 14:37:00', '2026-06-19 19:33:00', '2026-06-26 19:41:00', 'Finalizada', '{\"cantidad_total\":35000,\"tipo_carga\":\"Ma\\u00edz \\/ Soya\",\"bodegas_uniforme\":false,\"bodegas\":[{\"bodega\":1,\"tipo_carga\":\"Ma\\u00edz\"},{\"bodega\":2,\"tipo_carga\":\"Ma\\u00edz\"},{\"bodega\":3,\"tipo_carga\":\"Soya\"},{\"bodega\":4,\"tipo_carga\":\"Ma\\u00edz\"},{\"bodega\":5,\"tipo_carga\":\"Ma\\u00edz\"}]}', '2026-06-19 19:39:27', '2026-06-27 00:42:31'),
(41, 'BALTIC SUMMER', 'Berth 03', 6, 6, '2026-06-19 15:00:00', NULL, '2026-06-22 07:40:00', 'Finalizada', '{\"cantidad_total\":6618,\"tipo_carga\":\"Big Bags\",\"bodegas_uniforme\":true}', '2026-06-19 19:41:05', '2026-07-01 15:12:38'),
(42, 'XIN DA LIAN', 'Berth 04', 1, 1, '2026-06-19 17:41:00', '2026-06-19 21:02:00', '2026-06-19 07:57:00', 'Finalizada', '{\"teus\":2000,\"tipo_carga\":\"Contenedores General\",\"cantidad_total\":2000}', '2026-06-19 19:42:15', '2026-06-20 12:58:05'),
(43, 'ECO SIROCCO', 'Berth 04', 1, 1, '2026-06-21 01:00:00', NULL, '2026-06-20 23:23:00', 'Finalizada', '{\"teus\":180,\"tipo_carga\":\"Contenedores General\"}', '2026-06-19 19:43:44', '2026-06-21 13:24:04'),
(44, 'MYD SHENZHEN 26025S', 'Berth 04', 1, 1, '2026-06-21 12:00:00', '2026-06-21 19:04:00', '2026-06-21 07:26:00', 'Finalizada', '{\"teus\":900,\"tipo_carga\":\"Contenedores General\",\"cantidad_total\":900}', '2026-06-19 19:45:06', '2026-06-22 12:27:20'),
(45, 'CSCL SATURN 088W', 'Berth 04', 1, NULL, NULL, '2026-06-22 07:10:00', NULL, 'En Operaciones', '{\"cantidad_total\":3139}', '2026-06-23 04:01:45', '2026-06-23 12:10:40'),
(46, 'CENA FAITH V2607', 'Berth 03', 1, NULL, NULL, '2026-06-22 23:01:00', NULL, 'En Operaciones', '{\"cantidad_total\":22}', '2026-06-23 04:08:15', '2026-06-23 04:08:15'),
(48, 'CHENG KANG KOU', 'Berth 02', 4, NULL, NULL, '2026-06-28 19:49:00', '2026-06-28 06:33:00', 'Finalizada', '{\"cantidad_total\":1487}', '2026-06-28 12:41:25', '2026-06-29 11:34:04'),
(51, 'COSCO SHIPPING THAMES', 'Berth 04', 1, NULL, NULL, NULL, '2026-06-29 08:15:00', 'Finalizada', '{\"cantidad_total\":2012}', '2026-06-29 01:12:08', '2026-06-30 13:15:47'),
(52, 'LAKONIA', 'Berth 04', 1, NULL, NULL, NULL, '2026-06-29 08:15:00', 'Finalizada', '{\"cantidad_total\":830}', '2026-06-29 01:15:12', '2026-06-30 13:15:53'),
(58, 'CARIBBEAN TRADER', 'Berth 03', 1, 1, '2026-06-29 18:58:00', NULL, '2026-06-30 11:25:00', 'Finalizada', '{\"teus\":432,\"tipo_carga\":\"Contenedores General\"}', '2026-07-01 00:06:53', '2026-07-01 16:27:07'),
(63, 'EA GATUN', 'Berth 03', 1, 1, '2026-07-03 19:41:04', '2026-07-03 19:41:04', '2026-07-03 19:41:04', 'Finalizada', '{\"teus\":311}', '2026-07-04 00:41:04', '2026-07-04 00:41:04'),
(64, 'SHARP ISLAND', 'Berth 01', 2, 2, '2026-07-04 19:19:39', '2026-07-04 19:19:39', NULL, 'En Operaciones', '{\"cantidad_total\":25779.52999999999883584678173065185546875}', '2026-07-05 00:19:41', '2026-07-05 00:19:41'),
(65, 'ECO SIROCCO', 'Berth 03', 1, 1, '2026-07-05 06:37:27', '2026-07-05 06:37:27', '2026-07-05 06:37:27', 'Finalizada', '{\"teus\":178}', '2026-07-05 11:37:27', '2026-07-05 11:37:27'),
(66, 'CMA CGM OSMIUM', 'Berth 04', 1, 1, '2026-07-05 06:40:53', '2026-07-05 06:40:53', '2026-07-05 06:40:53', 'Finalizada', '{\"teus\":476}', '2026-07-05 11:40:53', '2026-07-05 11:40:54'),
(67, 'ECO SIROCCO', 'Berth 03', 1, 1, '2026-07-04 07:20:18', '2026-07-04 07:20:18', '2026-07-04 07:20:18', 'Finalizada', '{\"teus\":178}', '2026-07-05 12:20:18', '2026-07-05 12:20:18'),
(68, 'CMA CGM OSMIUM', 'Berth 04', 1, 1, '2026-07-04 07:21:14', '2026-07-04 07:21:14', '2026-07-04 07:21:14', 'Finalizada', '{\"teus\":476}', '2026-07-05 12:21:14', '2026-07-05 12:21:14'),
(69, 'MYD SHENZHEN', 'Berth 03', 1, 1, '2026-07-05 07:00:14', '2026-07-05 07:00:14', '2026-07-05 07:02:37', 'Finalizada', '{\"teus\":658}', '2026-07-06 12:00:15', '2026-07-06 12:02:37'),
(70, 'XIN OU ZHOU', 'Berth 04', 1, 1, '2026-07-06 19:10:16', '2026-07-06 19:10:16', '2026-07-06 07:23:58', 'Finalizada', '{\"teus\":2227}', '2026-07-06 23:49:04', '2026-07-07 12:23:58'),
(71, 'OOCL CHENNAI 011W', 'Berth 04', 1, 1, '2026-07-07 19:08:08', '2026-07-07 19:08:08', '2026-07-07 06:27:54', 'Finalizada', '{\"teus\":1048}', '2026-07-08 00:08:08', '2026-07-08 11:27:54'),
(72, 'MEDKON ZOE 200N', 'Berth 03', 1, 1, '2026-07-07 19:17:45', '2026-07-07 19:17:45', '2026-07-08 14:38:33', 'Finalizada', '{\"teus\":405}', '2026-07-08 00:08:57', '2026-07-08 19:38:33'),
(73, 'FM PROSTERY', 'Berth 03', 6, 28, '2026-07-08 19:26:34', '2026-07-08 19:26:34', NULL, 'En Operaciones', '{\"cantidad_total\":1923}', '2026-07-08 19:41:04', '2026-07-09 00:26:33'),
(74, 'BUNUN NOBLE', 'Berth 02', 6, 7, '2026-07-09 19:12:15', '2026-07-09 19:12:15', '2026-07-12 07:11:00', 'Finalizada', '{\"cantidad_total\":15000}', '2026-07-10 00:12:16', '2026-07-13 12:11:00'),
(75, 'BBC RAINBOW 12750', 'Berth 03', 6, 28, '2026-07-10 06:57:26', '2026-07-10 06:57:26', '2026-07-10 06:57:26', 'Finalizada', '{\"cantidad_total\":1200}', '2026-07-11 11:57:27', '2026-07-11 11:57:27'),
(76, 'CHARM C 024S', 'Berth 04', 1, 1, '2026-07-10 07:08:41', '2026-07-10 07:08:41', '2026-07-11 07:49:07', 'Finalizada', '{\"teus\":433}', '2026-07-11 12:08:42', '2026-07-11 12:49:07'),
(77, 'CMA CGM MERKURI', 'Berth 04', 1, 1, '2026-07-12 18:59:41', '2026-07-12 18:59:41', '2026-07-12 18:59:41', 'Finalizada', '{\"teus\":226}', '2026-07-12 23:59:40', '2026-07-12 23:59:40'),
(78, 'LAKONIA 338S', 'Berth 04', 1, 1, '2026-07-12 19:04:29', '2026-07-12 19:04:29', '2026-07-12 05:39:27', 'Finalizada', '{\"teus\":635}', '2026-07-13 00:01:06', '2026-07-13 10:39:27'),
(79, 'BODRUM -M', 'Berth 02', 6, 7, '2026-07-14 17:27:56', '2026-07-14 17:27:56', '2026-07-14 17:23:00', 'En Operaciones', '{\"cantidad_total\":4550,\"tipo_carga\":\"Urea\",\"bodegas_uniforme\":false,\"bodegas\":[{\"bodega\":2,\"tipo_carga\":\"Urea\"},{\"bodega\":3,\"tipo_carga\":\"Otros\"},{\"bodega\":4,\"tipo_carga\":\"Urea\"}]}', '2026-07-13 23:34:53', '2026-07-14 22:27:57'),
(80, 'ECO MARIN', 'Berth 04', 1, 1, '2026-07-13 18:36:24', '2026-07-13 18:36:24', '2026-07-13 18:36:24', 'Finalizada', '{\"teus\":155}', '2026-07-13 23:36:25', '2026-07-13 23:36:25'),
(81, 'XIN HONG KONG', 'Berth 04', 1, 1, '2026-07-13 18:56:00', NULL, '2026-07-14 17:24:00', 'En Operaciones', '{\"teus\":1886,\"tipo_carga\":\"Contenedores General\"}', '2026-07-13 23:37:55', '2026-07-14 22:24:53'),
(82, 'CARIBBEAN TRADER 201N', 'Berth 03', 1, 1, '2026-07-14 19:10:58', '2026-07-14 19:10:58', '2026-07-14 05:27:03', 'Finalizada', '{\"teus\":560}', '2026-07-14 22:18:38', '2026-07-15 10:27:02'),
(83, 'EA GATUN', 'Berth 04', 1, 1, '2026-07-14 07:08:43', '2026-07-14 07:08:43', NULL, 'En Operaciones', '{\"teus\":435}', '2026-07-15 10:03:30', '2026-07-15 12:08:44'),
(84, 'GLOBAL CORAL V1', 'Berth 01', 2, 2, '2026-07-15 19:12:36', '2026-07-15 19:12:36', NULL, 'En Operaciones', '{\"cantidad_total\":36866.830000000001746229827404022216796875}', '2026-07-16 00:12:37', '2026-07-16 00:12:37'),
(85, 'CSCL WINTER 061W', 'Berth 01', 1, 1, '2026-07-18 17:43:09', '2026-07-18 17:43:09', '2026-07-18 18:38:05', 'Finalizada', '{\"teus\":3614}', '2026-07-18 00:34:21', '2026-07-18 23:38:05'),
(86, 'ECO SIROCCO 2609S', 'Berth 03', 1, 1, '2026-07-19 07:10:04', '2026-07-19 07:10:04', '2026-07-19 07:10:04', 'Finalizada', '{\"teus\":183}', '2026-07-19 12:10:04', '2026-07-19 12:10:04'),
(87, 'CMA CGM CAPE', 'Berth 04', 1, 1, '2026-07-19 07:49:54', '2026-07-19 07:49:54', '2026-07-19 15:43:16', 'Finalizada', '{\"teus\":312}', '2026-07-19 12:49:55', '2026-07-19 20:43:16'),
(88, 'MYD SHENZHEN', 'Berth 03', 1, 1, '2026-07-19 19:04:19', '2026-07-19 19:04:19', '2026-07-19 06:51:24', 'Finalizada', '{\"teus\":546}', '2026-07-19 20:46:08', '2026-07-20 11:51:25'),
(89, 'BEIJING 115W', 'Berth 3.5', 1, 1, '2026-07-20 18:58:37', '2026-07-20 18:58:37', '2026-07-20 07:35:09', 'Finalizada', '{\"teus\":1809}', '2026-07-20 23:37:24', '2026-07-21 12:35:09'),
(90, 'MEDKON ZOE 201N', 'Berth 03', 1, 1, '2026-07-21 19:22:56', '2026-07-21 19:22:56', '2026-07-21 05:01:23', 'Finalizada', '{\"teus\":505}', '2026-07-22 00:22:57', '2026-07-22 10:01:23'),
(91, 'MOLLY SCHULTE 142S', 'Berth 04', 1, 1, '2026-07-21 19:31:41', '2026-07-21 19:31:41', NULL, 'En Operaciones', '{\"teus\":784}', '2026-07-22 00:31:41', '2026-07-22 00:31:41'),
(92, 'MN UNITED HARMONY', 'Berth 01', 2, 2, '2026-07-22 07:20:14', '2026-07-22 07:20:14', '2026-07-24 19:14:37', 'Finalizada', '{\"cantidad_total\":34089.9899999999979627318680286407470703125}', '2026-07-23 00:33:47', '2026-07-25 00:14:38'),
(93, 'CHANG AN CHENG', 'Berth 03', 6, 30, '2026-07-22 07:17:01', '2026-07-22 07:17:01', '2026-07-23 07:26:05', 'Finalizada', '{\"cantidad_total\":1827}', '2026-07-23 00:35:55', '2026-07-24 12:26:06'),
(94, 'CHARM C', 'Berth 04', 1, 1, '2026-07-23 07:35:38', '2026-07-23 07:35:38', NULL, 'En Operaciones', '{\"teus\":1375}', '2026-07-24 01:17:42', '2026-07-24 12:35:38'),
(95, 'MN UNITED HARMONY', 'Berth 01', 2, 2, '2026-07-25 19:35:31', '2026-07-25 19:35:31', NULL, 'En Operaciones', '{\"cantidad_total\":34089.9899999999979627318680286407470703125}', '2026-07-25 12:11:38', '2026-07-26 00:35:31'),
(96, 'CL SPRUCE', 'Berth 02', 6, 7, '2026-08-02 19:15:20', '2026-08-02 19:15:20', NULL, 'En Operaciones', '{\"cantidad_total\":30250}', '2026-07-26 00:32:16', '2026-08-03 00:13:36'),
(97, 'CMA CGM BUZIOS', 'Berth 04', 1, 1, '2026-07-26 16:40:51', '2026-07-26 16:40:51', '2026-07-26 16:40:51', 'Finalizada', '{\"teus\":152}', '2026-07-26 21:40:52', '2026-07-26 21:40:52'),
(98, 'LAKONIA', 'Berth 03', 1, 1, '2026-07-26 20:12:16', '2026-07-26 20:12:16', '2026-07-26 07:17:17', 'Finalizada', '{\"teus\":829}', '2026-07-27 01:12:16', '2026-07-27 12:17:18'),
(99, 'ECO MARIN', 'Berth 03', 1, 1, '2026-07-27 16:45:18', '2026-07-27 16:45:18', '2026-07-27 16:45:18', 'Finalizada', '{\"teus\":247}', '2026-07-27 21:45:18', '2026-07-27 21:45:19'),
(100, 'CSCL ASIA V.171W', 'Berth 04', 1, 1, '2026-07-27 07:08:28', '2026-07-27 07:08:28', NULL, 'En Operaciones', '{\"teus\":1698}', '2026-07-28 03:41:01', '2026-07-28 12:06:47'),
(101, 'CSCL ASIA', 'Berth 04', 1, 1, '2026-07-28 19:22:11', '2026-07-28 19:22:11', '2026-07-28 04:51:07', 'Finalizada', '{\"teus\":1693}', '2026-07-28 20:37:14', '2026-07-29 09:51:08'),
(102, 'CARIBBEAN TRADER', 'Berth 03', 6, 28, '2026-07-28 19:23:44', '2026-07-28 19:23:44', NULL, 'En Operaciones', '{\"cantidad_total\":553}', '2026-07-28 20:37:51', '2026-07-29 00:23:44'),
(103, 'CARIBBEAN TRADER', 'Berth 03', 1, 1, '2026-07-28 02:18:12', '2026-07-28 02:18:12', '2026-07-28 05:31:19', 'Finalizada', '{\"teus\":553}', '2026-07-29 07:18:13', '2026-07-29 10:31:19'),
(104, 'OOCL HO CHU MIN', 'Berth 04', 1, 1, '2026-07-29 19:30:48', '2026-07-29 19:30:48', '2026-07-29 04:41:47', 'Finalizada', '{\"teus\":1806}', '2026-07-30 00:30:48', '2026-07-30 09:41:47'),
(105, 'WEN JING KOU', 'Berth 03', 4, 8, '2026-07-29 19:32:05', '2026-07-29 19:32:05', '2026-07-30 07:36:55', 'Finalizada', '{\"vehiculos\":2741}', '2026-07-30 00:32:05', '2026-07-31 12:36:55'),
(106, 'EA GATUN 26031S', 'Berth 02', 1, 1, '2026-07-31 02:49:45', '2026-07-31 02:49:45', '2026-07-31 06:25:17', 'Finalizada', '{\"teus\":376}', '2026-08-01 00:49:45', '2026-08-01 11:25:17'),
(107, 'EA GATUN 26031S', 'Berth 03', 1, 1, '2026-07-31 02:58:12', '2026-07-31 02:58:12', NULL, 'En Operaciones', '{\"teus\":376}', '2026-08-01 00:57:27', '2026-08-01 00:58:12'),
(108, 'ECO SIROCCO 26031S', 'Berth 03', 1, 1, '2026-08-02 07:39:05', '2026-08-02 07:39:05', '2026-08-02 19:00:57', 'Finalizada', '{\"teus\":187}', '2026-08-02 12:39:05', '2026-08-02 23:59:14'),
(109, 'BBC ELISABETH', 'Berth 02', 6, 7, '2026-08-02 19:00:14', '2026-08-02 19:00:14', NULL, 'En Operaciones', '{\"cantidad_total\":111}', '2026-08-02 23:58:30', '2026-08-02 23:58:30'),
(110, 'COSCO PACIFIC 099W', 'Berth 04', 1, 1, '2026-08-02 14:05:47', '2026-08-02 14:05:47', NULL, 'En Operaciones', '{\"teus\":1321}', '2026-08-03 07:30:45', '2026-08-03 12:05:46'),
(111, 'MV STAR MAINE', 'Berth 02', 6, 7, '2026-08-03 19:16:54', '2026-08-03 19:16:54', '2026-08-03 07:28:44', 'Finalizada', '{\"cantidad_total\":5500}', '2026-08-03 23:38:54', '2026-08-04 12:28:45'),
(112, 'SHENZHEN 26031S', 'Berth 04', 1, 1, '2026-08-03 07:26:41', '2026-08-03 07:26:41', NULL, 'En Operaciones', '{\"teus\":484}', '2026-08-04 12:26:42', '2026-08-04 12:26:42');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tallyman_actividades`
--

CREATE TABLE `tallyman_actividades` (
  `id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `orden` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `tallyman_actividades`
--

INSERT INTO `tallyman_actividades` (`id`, `nombre`, `activo`, `orden`) VALUES
(1, 'Containers Loading/Discharge (Carga/Descarga de Contenedores)', 1, 1),
(2, 'Corn Loading/Discharge (Carga/Descarga de Maíz)', 1, 2),
(3, 'Salt Loading/Discharge (Carga/Descarga de Sal)', 1, 3),
(4, 'Soybean Unloading/Loading (Carga/Descarga de Soya)', 1, 4),
(5, 'Bulk Carrier Loading/Discharge (Carga/Descarga de Granel)', 1, 5),
(6, 'Cement Big Bags Loading/Discharge (Carga/Descarga de Cemento en Big Bags)', 1, 6),
(7, 'General Cargo Loading/Discharge (Carga/Descarga de Carga General)', 1, 7),
(8, 'Car Loading/Discharge (Carga/Descarga de Vehículos)', 1, 8),
(9, 'Minerals (Minerales)', 1, 9),
(10, 'Fishmeals (Harina de Pescado)', 1, 10),
(11, 'Container deconsolidation (Desconsolidación de Contenedores)', 1, 11),
(12, 'Car deconsolidation (Desconsolidación de Vehículos)', 1, 12),
(13, 'Containers Dispatch (Despacho de Contenedores)', 1, 13),
(14, 'Corn Dispatch (Despacho de Maíz)', 1, 14),
(15, 'Salt Dispatch (Despacho de Sal)', 1, 15),
(16, 'Soybean Dispatch (Despacho de Soya)', 1, 16),
(17, 'Bulk Carrier Dispatch (Despacho de Granel)', 1, 17),
(18, 'Big Bags Dispatch (Despacho de Big Bags)', 1, 18),
(19, 'General Cargo Dispatch (Despacho de Carga General)', 1, 19),
(20, 'Car Dispatch (Despacho de Vehículos)', 1, 20),
(21, 'Reception of Salt (Recepción de Sal)', 1, 21),
(22, 'Dispatch of Lump', 1, 22),
(23, 'Dispatch of Structure Metallic', 1, 23),
(28, 'Nitrate Big Bags Loading/Discharge (Carga/Descarga de Nitrato en Big Bags)', 1, 6),
(29, 'Urea Big Bags Loading/Discharge (Carga/Descarga de Urea en Big Bags)', 1, 6),
(30, 'Steel Balls Big Bags Loading/Discharge (Carga/Descarga de Bolas de Acero en Big Bags)', 1, 6),
(31, 'Cement Big Bags Dispatch (Despacho de Cemento en Big Bags)', 1, 18);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tallyman_incidencias`
--

CREATE TABLE `tallyman_incidencias` (
  `id` int(10) UNSIGNED NOT NULL,
  `fecha_turno` date NOT NULL,
  `turno` varchar(20) NOT NULL,
  `hubo` tinyint(1) NOT NULL DEFAULT 0,
  `detalle` text DEFAULT NULL,
  `registrado_por` varchar(120) NOT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tallyman_registros`
--

CREATE TABLE `tallyman_registros` (
  `id` int(10) UNSIGNED NOT NULL,
  `fecha_turno` date NOT NULL,
  `turno` varchar(20) NOT NULL,
  `ubicacion_tipo` enum('BERTH','YARD') NOT NULL,
  `ubicacion` varchar(40) NOT NULL,
  `nave_id` int(10) UNSIGNED DEFAULT NULL,
  `nave_patio` varchar(100) DEFAULT NULL,
  `actividad_id` int(10) UNSIGNED NOT NULL,
  `estado_pos` enum('ACTIVE','INACTIVE','FINISH') NOT NULL DEFAULT 'ACTIVE',
  `status_act` enum('Inicio','En Proceso','Culminado') NOT NULL DEFAULT 'Inicio',
  `planned` decimal(14,2) DEFAULT NULL,
  `executed` decimal(14,2) NOT NULL DEFAULT 0.00,
  `directa` decimal(14,2) DEFAULT NULL,
  `interna` decimal(14,2) DEFAULT NULL,
  `productivity` decimal(12,2) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `cargo_type` varchar(40) DEFAULT NULL,
  `bl` varchar(120) DEFAULT NULL,
  `producto` varchar(120) DEFAULT NULL,
  `unidades` decimal(14,2) DEFAULT NULL,
  `tons` decimal(14,2) DEFAULT NULL,
  `ubic_codigo` varchar(40) DEFAULT NULL,
  `coord_entrante` varchar(120) DEFAULT NULL,
  `coord_saliente` varchar(120) DEFAULT NULL,
  `registrado_por` varchar(120) NOT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `tallyman_registros`
--

INSERT INTO `tallyman_registros` (`id`, `fecha_turno`, `turno`, `ubicacion_tipo`, `ubicacion`, `nave_id`, `nave_patio`, `actividad_id`, `estado_pos`, `status_act`, `planned`, `executed`, `directa`, `interna`, `productivity`, `details`, `cargo_type`, `bl`, `producto`, `unidades`, `tons`, `ubic_codigo`, `coord_entrante`, `coord_saliente`, `registrado_por`, `fecha_registro`) VALUES
(22, '2026-06-08', 'D', 'YARD', 'Yard', NULL, 'ISE HARMONY', 14, 'ACTIVE', 'Inicio', 4930.00, 814.18, NULL, NULL, NULL, 'B/L: 01\nALMACEN: GWA 814.18', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Sistemas', '2026-06-08 20:18:02'),
(24, '2026-06-08', 'D', 'YARD', 'Yard', NULL, 'CONDOR VALPARAISO', 23, 'ACTIVE', 'Culminado', 2.00, 2.00, NULL, NULL, NULL, 'M2A1\nPLANCHAS METALICAS', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Sistemas', '2026-06-08 20:21:43'),
(25, '2026-06-08', 'D', 'YARD', 'Yard', NULL, 'SEDNA DESGAGNES / 1180043', 18, 'ACTIVE', 'Inicio', 35.00, 0.00, NULL, NULL, NULL, 'UBICATION: BR4A', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Sistemas', '2026-06-08 20:22:29'),
(27, '2026-06-08', 'D', 'YARD', 'Yard', NULL, 'YANTIAN', 20, 'ACTIVE', 'Inicio', 93.00, 40.00, NULL, NULL, NULL, 'BL: 6450237330\nUBICACION: E30103', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Sistemas', '2026-06-08 20:24:10'),
(28, '2026-06-08', 'D', 'YARD', 'Yard', NULL, 'MINERALES', 9, 'ACTIVE', 'Inicio', 12.00, 3.00, NULL, NULL, NULL, 'MINERALES', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Sistemas', '2026-06-08 20:31:41'),
(30, '2026-06-08', 'D', 'YARD', 'Yard', NULL, 'HAI HE KOU', 20, 'ACTIVE', 'Inicio', 1.00, 0.00, NULL, NULL, NULL, 'ZONE: AR1A', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Sistemas', '2026-06-09 03:47:28'),
(31, '2026-06-08', 'N', 'YARD', 'Yard', NULL, 'ISE HARMONY', 14, 'ACTIVE', 'En Proceso', 4930.00, 429.16, NULL, NULL, NULL, 'BL01\nALMACEN GWA \nSTATUS IN YARD: 3685.38', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'FANNY YESENIA ASTO', '2026-06-09 11:17:43'),
(32, '2026-06-08', 'N', 'YARD', 'Yard', NULL, 'HAI HE KOU', 20, 'ACTIVE', 'En Proceso', 1.00, 0.00, NULL, NULL, NULL, 'AR1A', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'FANNY YESENIA ASTO', '2026-06-09 11:21:31'),
(46, '2026-06-09', 'D', 'BERTH', 'Berth 04', 21, NULL, 1, 'ACTIVE', 'Inicio', 2200.00, 398.00, 398.00, NULL, NULL, 'DISCHARGE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Sistemas CSP', '2026-06-10 14:38:53'),
(47, '2026-06-09', 'D', 'YARD', 'Yard', NULL, 'YANTIAN', 20, 'ACTIVE', 'En Proceso', 93.00, 35.00, NULL, NULL, NULL, 'EA', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Sistemas CSP', '2026-06-10 14:39:31'),
(48, '2026-06-09', 'D', 'YARD', 'Yard', NULL, 'ISE HARMONY', 14, 'ACTIVE', 'En Proceso', 4930.00, 713.48, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Sistemas CSP', '2026-06-10 14:53:55'),
(51, '2026-06-09', 'N', 'BERTH', 'Berth 04', 21, NULL, 1, 'ACTIVE', 'En Proceso', 2200.00, 1471.00, 1471.00, NULL, NULL, 'DISCHARGE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Sistemas CSP', '2026-06-10 15:00:08'),
(53, '2026-06-09', 'N', 'YARD', 'Yard', NULL, 'ISE HARMONY', 14, 'ACTIVE', 'En Proceso', 4930.00, 707.24, NULL, NULL, NULL, 'GWA/GWB', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Sistemas CSP', '2026-06-10 15:16:08'),
(54, '2026-06-09', 'N', 'YARD', 'Yard', NULL, 'YANTIAN', 20, 'ACTIVE', 'En Proceso', 93.00, 28.00, NULL, NULL, NULL, 'CAR DISPATCH', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Sistemas CSP', '2026-06-10 15:16:33'),
(55, '2026-06-10', 'D', 'BERTH', 'Berth 02', 12, NULL, 6, 'ACTIVE', 'Inicio', 30250.00, 1414.00, NULL, 1414.00, 6.00, 'Todo en modalidad DESCARGA INDIRECTA', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-06-11 00:01:34'),
(56, '2026-06-10', 'D', 'BERTH', 'Berth 3.5', 23, NULL, 1, 'ACTIVE', 'Inicio', 900.00, 256.00, 256.00, NULL, 5.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-06-11 00:22:17'),
(57, '2026-06-10', 'D', 'YARD', 'Yard', NULL, 'YANTIAN', 20, 'ACTIVE', 'Culminado', 93.00, 41.00, NULL, NULL, NULL, 'FINALIZO EL DESPACHO DE VEHICULOS DESCONSOLIDADOS (cigüeñas)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-06-11 00:26:48'),
(59, '2026-06-10', 'D', 'YARD', 'Yard', NULL, 'ISE HARMONY', 14, 'ACTIVE', 'En Proceso', 4930.00, 1593.16, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Sistemas CSP', '2026-06-11 01:23:58'),
(63, '2026-06-10', 'N', 'BERTH', 'Berth 3.5', 15, NULL, 1, 'ACTIVE', 'Inicio', 1222.00, 966.00, 966.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'TERRY PEREYRA EDGARD DAVID', '2026-06-11 12:33:32'),
(64, '2026-06-10', 'N', 'BERTH', 'Berth 02', 12, NULL, 6, 'ACTIVE', 'En Proceso', 30250.00, 2496.00, NULL, 2496.00, 7.00, 'HOLD #1: 744 BB\nHOLD #2: 144 BB\nHOLD #3: 874 BB\nHOLD #4: 132 BB\nHOLD #5: 602 BB\nTOTAL:  2496 BB', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'TERRY PEREYRA EDGARD DAVID', '2026-06-11 12:33:59'),
(65, '2026-06-10', 'N', 'YARD', 'Yard', NULL, 'ISE HARMONY', 14, 'ACTIVE', 'Culminado', 4930.00, 672.78, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'TERRY PEREYRA EDGARD DAVID', '2026-06-11 12:36:54'),
(70, '2026-06-11', 'D', 'BERTH', 'Berth 02', 12, NULL, 6, 'ACTIVE', 'En Proceso', 30250.00, 3288.00, NULL, 3288.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Sistemas CSP', '2026-06-12 12:45:33'),
(71, '2026-06-11', 'D', 'YARD', 'Yard', NULL, 'YANTIAN', 20, 'ACTIVE', 'Culminado', 93.00, 93.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Sistemas CSP', '2026-06-12 12:45:55'),
(72, '2026-06-11', 'N', 'BERTH', 'Berth 02', 12, NULL, 6, 'ACTIVE', 'En Proceso', 30250.00, 3432.00, 2286.00, 3432.00, NULL, '5 bodegas trabajando', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Sistemas CSP', '2026-06-12 12:46:21'),
(73, '2026-06-11', 'N', 'YARD', 'Yard', 12, NULL, 18, 'ACTIVE', 'Inicio', 30250.00, 2286.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Sistemas CSP', '2026-06-12 12:47:31'),
(74, '2026-06-10', 'D', 'BERTH', 'Berth 04', 21, NULL, 1, 'ACTIVE', 'Culminado', 2200.00, 331.00, 331.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Sistemas CSP', '2026-06-12 22:10:02'),
(75, '2026-06-12', 'D', 'BERTH', 'Berth 02', 12, NULL, 6, 'ACTIVE', 'En Proceso', 30250.00, 2131.00, 3622.00, 2131.00, 9.00, 'H01:859 BAGS\nH02: 425 BAGS\nH03: 980 BAGS\nH04: 540 BAGS\nH05: 781 BAGS', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'FANNY YESENIA ASTO', '2026-06-13 01:15:52'),
(76, '2026-06-12', 'D', 'YARD', 'Yard', NULL, 'MINERALES', 9, 'ACTIVE', 'Culminado', 12.00, 27.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'FANNY YESENIA ASTO', '2026-06-13 01:17:03'),
(77, '2026-06-12', 'D', 'YARD', 'Yard', NULL, 'TIAN XIANG HE', 12, 'ACTIVE', 'Culminado', 29.00, 29.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'FANNY YESENIA ASTO', '2026-06-13 01:18:39'),
(78, '2026-06-12', 'N', 'BERTH', 'Berth 02', 12, NULL, 6, 'ACTIVE', 'En Proceso', 30250.00, 2018.00, NULL, 2018.00, 8.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'URBINA MEDINA EVELYN LOLY', '2026-06-13 12:31:34'),
(79, '2026-06-12', 'N', 'BERTH', 'Berth 03', 13, NULL, 6, 'ACTIVE', 'Inicio', 3040.00, 1060.00, 1060.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'URBINA MEDINA EVELYN LOLY', '2026-06-13 12:34:34'),
(80, '2026-06-13', 'D', 'BERTH', 'Berth 02', 12, NULL, 6, 'ACTIVE', 'En Proceso', 30250.00, 0.00, 2792.00, NULL, 6.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-06-13 23:45:23'),
(81, '2026-06-13', 'D', 'BERTH', 'Berth 03', 13, NULL, 6, 'ACTIVE', 'En Proceso', 3040.00, 1380.00, 1380.00, NULL, 2.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-06-13 23:56:18'),
(83, '2026-06-13', 'D', 'BERTH', 'Berth 02', 17, NULL, 7, 'ACTIVE', 'Inicio', 2315.00, 27.00, 27.00, NULL, 3.00, '*HOLD #03: 12\n*HOLD #04: 15\nTOTAL: 27 UNIDADES', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-06-14 00:36:50'),
(84, '2026-06-13', 'D', 'YARD', 'Yard', NULL, 'COSU6448590251', 12, 'ACTIVE', 'Culminado', 116.00, 116.00, NULL, NULL, NULL, '116 pedeteados\nBL: COSU6448590251 - 52 pedeteados.\n- Todo pedeteado tarjado y precintado \nBL: COSU6448590250 - 64 pedeteado.\n- Todo pedeteado, faltó tarjar 14 (las dos últimas filas), precintar faltó 31.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-06-14 00:50:13'),
(85, '2026-06-13', 'N', 'BERTH', 'Berth 02', 12, NULL, 6, 'ACTIVE', 'En Proceso', 30250.00, 5129.00, 1490.00, 5129.00, 6.00, 'BODEGA 2: 579 BAGS\nBODEGA 4: 911 BAGS', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'FANNY YESENIA ASTO', '2026-06-14 12:32:19'),
(86, '2026-06-13', 'N', 'BERTH', 'Berth 03', 13, NULL, 6, 'ACTIVE', 'Culminado', 3040.00, 620.00, 620.00, NULL, 2.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'FANNY YESENIA ASTO', '2026-06-14 12:33:02'),
(88, '2026-06-13', 'N', 'BERTH', 'Berth 04', 18, NULL, 1, 'ACTIVE', 'Culminado', 180.00, 180.00, 180.00, NULL, NULL, 'QC: 103\nQC: 104', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'FANNY YESENIA ASTO', '2026-06-14 12:38:39'),
(89, '2026-06-14', 'D', 'BERTH', 'Berth 02', 12, NULL, 6, 'ACTIVE', 'En Proceso', 30250.00, 0.00, 2646.00, NULL, 6.00, 'TRABAJANDO BODEGAS 1, 2 Y4', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-06-15 00:42:53'),
(90, '2026-06-14', 'D', 'BERTH', 'Berth 02', 17, NULL, 7, 'ACTIVE', 'En Proceso', 2315.00, 24.00, 24.00, NULL, 3.00, 'HOLD 5 = 4\nHOLD 4 = 1\nHOLD 2 = 1\nHOLD 1 = 10', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-06-15 00:45:00'),
(91, '2026-06-14', 'D', 'BERTH', 'Berth 04', 20, NULL, 1, 'ACTIVE', 'Culminado', 250.00, 250.00, 250.00, NULL, 4.00, 'Nave terminó operaciones en el turno', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-06-15 00:54:36'),
(92, '2026-06-14', 'N', 'BERTH', 'Berth 02', 12, NULL, 6, 'ACTIVE', 'En Proceso', 30250.00, 4747.00, 2430.00, 4747.00, 5.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'FANNY YESENIA ASTO', '2026-06-15 12:33:14'),
(94, '2026-06-14', 'N', 'BERTH', 'Berth 04', 26, NULL, 1, 'ACTIVE', 'Inicio', 900.00, 461.00, 461.00, NULL, 3.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'FANNY YESENIA ASTO', '2026-06-15 12:36:55'),
(95, '2026-06-10', 'N', 'BERTH', 'Berth 3.5', 23, NULL, 1, 'ACTIVE', 'Culminado', 900.00, 644.00, 644.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Sistemas CSP', '2026-06-15 21:09:27'),
(96, '2026-06-11', 'D', 'BERTH', 'Berth 3.5', 15, NULL, 1, 'ACTIVE', 'Culminado', 1222.00, 256.00, 256.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Sistemas CSP', '2026-06-15 21:19:22'),
(98, '2026-06-13', 'N', 'BERTH', 'Berth 02', 17, NULL, 7, 'ACTIVE', 'En Proceso', 2315.00, 55.00, 55.00, NULL, NULL, 'HOLD 4 = 22\nHOLD 3 = 24\nHOLD 2 = 9', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Sistemas CSP', '2026-06-15 21:35:24'),
(99, '2026-06-14', 'N', 'BERTH', 'Berth 02', 17, NULL, 7, 'ACTIVE', 'En Proceso', 2315.00, 382.00, 382.00, NULL, NULL, 'HOLD 5 = 9\nHOLD 4 = 6\nHOLD 2 = 1\nHOLD 1 = 366', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Sistemas CSP', '2026-06-15 21:48:49'),
(100, '2026-06-15', 'D', 'BERTH', 'Berth 02', 17, NULL, 7, 'ACTIVE', 'En Proceso', 2315.00, 514.00, 514.00, NULL, NULL, 'HOLD 4 = 13\nHOLD 1 = 501', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Sistemas CSP', '2026-06-15 22:07:18'),
(101, '2026-06-15', 'D', 'BERTH', 'Berth 02', 12, NULL, 6, 'ACTIVE', 'En Proceso', 30250.00, 0.00, 2471.00, NULL, 5.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Sistemas CSP', '2026-06-15 22:11:28'),
(102, '2026-06-15', 'D', 'YARD', 'Yard', NULL, 'TIAN XIANG', 20, 'ACTIVE', 'Inicio', 160.00, 160.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'URBINA MEDINA EVELYN LOLY', '2026-06-16 01:30:33'),
(103, '2026-06-15', 'N', 'BERTH', 'Berth 04', 27, NULL, 1, 'ACTIVE', 'Culminado', 2000.00, 676.00, 676.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RISCO SEMINARIO GRECIA DEL MAR', '2026-06-16 12:36:27'),
(104, '2026-06-15', 'N', 'BERTH', 'Berth 02', 12, NULL, 6, 'ACTIVE', 'En Proceso', 30250.00, 0.00, 1451.00, NULL, NULL, 'bodegas culminadas: 1,3,4,5\nproxima a terminar: hold 2', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RISCO SEMINARIO GRECIA DEL MAR', '2026-06-16 12:37:57'),
(105, '2026-06-15', 'N', 'BERTH', 'Berth 02', 17, NULL, 7, 'ACTIVE', 'En Proceso', 2315.00, 522.00, 522.00, NULL, 5.00, 'REMANENTE: 89 Bolas de acero + 689 barras de acero + 8 bultos\nHOLD 4 = 14\nHOLD 3 = 4\nHOLD 1 = 504', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RISCO SEMINARIO GRECIA DEL MAR', '2026-06-16 12:38:57'),
(106, '2026-06-15', 'N', 'YARD', 'Yard', NULL, 'TIAN XIANG', 20, 'ACTIVE', 'En Proceso', 160.00, 56.00, NULL, NULL, NULL, '56 chasis/8 cigueñas', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RISCO SEMINARIO GRECIA DEL MAR', '2026-06-16 12:39:45'),
(107, '2026-06-15', 'D', 'BERTH', 'Berth 04', 27, NULL, 1, 'ACTIVE', 'Inicio', 2000.00, 1324.00, 1324.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Sistemas CSP', '2026-06-16 13:53:15'),
(108, '2026-06-16', 'D', 'BERTH', 'Berth 02', 12, NULL, 6, 'ACTIVE', 'Culminado', 30250.00, 5595.00, 3200.00, 5595.00, 4.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-06-16 19:17:30'),
(109, '2026-06-16', 'D', 'BERTH', 'Berth 02', 17, NULL, 7, 'ACTIVE', 'En Proceso', 2315.00, 104.00, 102.00, 2.00, 4.00, 'HOLD 4 = 9\nHOLD 1 = 93', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-06-17 00:03:16'),
(110, '2026-06-16', 'D', 'YARD', 'Yard', NULL, 'YANTIAN', 20, 'ACTIVE', 'Culminado', 93.00, 93.00, NULL, NULL, NULL, '14 cigueñas - DESCONSOLIDADO', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-06-17 00:17:25'),
(113, '2026-06-16', 'N', 'BERTH', 'Berth 02', 17, NULL, 7, 'ACTIVE', 'En Proceso', 2315.00, 232.00, 55.00, 177.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RISCO SEMINARIO GRECIA DEL MAR', '2026-06-17 12:56:07'),
(114, '2026-06-16', 'N', 'BERTH', 'Berth 03', 22, NULL, 8, 'ACTIVE', 'Inicio', 1882.00, 319.00, NULL, 319.00, 13.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RISCO SEMINARIO GRECIA DEL MAR', '2026-06-17 12:57:25'),
(115, '2026-06-16', 'N', 'BERTH', 'Berth 04', 30, NULL, 1, 'ACTIVE', 'Inicio', 442.00, 216.00, 216.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RISCO SEMINARIO GRECIA DEL MAR', '2026-06-17 12:59:32'),
(117, '2026-06-12', 'D', 'YARD', 'Yard', 12, NULL, 18, 'ACTIVE', 'En Proceso', 30250.00, 3622.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Sistemas CSP', '2026-06-17 16:40:52'),
(118, '2026-06-13', 'D', 'YARD', 'Yard', 12, NULL, 18, 'ACTIVE', 'En Proceso', 30250.00, 2792.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Sistemas CSP', '2026-06-17 16:43:41'),
(119, '2026-06-13', 'N', 'YARD', 'Yard', 12, NULL, 18, 'ACTIVE', 'En Proceso', 30250.00, 1490.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Sistemas CSP', '2026-06-17 16:45:16'),
(120, '2026-06-14', 'D', 'YARD', 'Yard', 12, NULL, 18, 'ACTIVE', 'En Proceso', 30250.00, 2646.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Sistemas CSP', '2026-06-17 16:46:03'),
(121, '2026-06-14', 'N', 'YARD', 'Yard', 12, NULL, 18, 'ACTIVE', 'En Proceso', 30250.00, 2430.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Sistemas CSP', '2026-06-17 16:46:33'),
(122, '2026-06-15', 'D', 'YARD', 'Yard', 12, NULL, 18, 'ACTIVE', 'En Proceso', 30250.00, 2471.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Sistemas CSP', '2026-06-17 16:47:14'),
(123, '2026-06-15', 'N', 'YARD', 'Yard', 12, NULL, 18, 'ACTIVE', 'En Proceso', 30250.00, 1451.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Sistemas CSP', '2026-06-17 16:48:23'),
(124, '2026-06-16', 'D', 'YARD', 'Yard', 12, NULL, 18, 'ACTIVE', 'En Proceso', 30250.00, 3200.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Sistemas CSP', '2026-06-17 16:49:58'),
(125, '2026-06-16', 'N', 'YARD', 'Yard', 12, NULL, 18, 'ACTIVE', 'En Proceso', 30250.00, 2120.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Sistemas CSP', '2026-06-17 16:51:42'),
(126, '2026-06-12', 'N', 'YARD', 'Yard', 12, NULL, 18, 'ACTIVE', 'En Proceso', 30250.00, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Sistemas CSP', '2026-06-17 16:59:43'),
(128, '2026-06-17', 'D', 'BERTH', 'Berth 02', 17, NULL, 7, 'ACTIVE', 'En Proceso', 2315.00, 164.00, 164.00, NULL, 2.00, 'Descarga de barras de acero\nDespacho de cemento', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-06-17 21:57:41'),
(129, '2026-06-17', 'D', 'BERTH', 'Berth 03', 22, NULL, 8, 'ACTIVE', 'En Proceso', 1882.00, 1286.00, NULL, 1286.00, 6.00, 'Trabajando Deck 12, figuran pendiente de descargar 2 GWM, no se encuentran en piso tampoco pedeteado\n.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-06-17 21:58:18'),
(130, '2026-06-17', 'D', 'BERTH', 'Berth 04', 30, NULL, 1, 'ACTIVE', 'Culminado', 442.00, 226.00, 226.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-06-17 21:59:29'),
(132, '2026-06-17', 'D', 'YARD', 'Yard', 12, NULL, 18, 'ACTIVE', 'En Proceso', 30250.00, 870.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-06-18 00:53:05'),
(133, '2026-06-17', 'N', 'BERTH', 'Berth 01', 37, NULL, 2, 'ACTIVE', 'En Proceso', 37400.00, 8783.40, NULL, 8783.40, NULL, 'GWC', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'URBINA MEDINA EVELYN LOLY', '2026-06-18 13:36:24'),
(134, '2026-06-17', 'N', 'BERTH', 'Berth 02', 17, NULL, 7, 'ACTIVE', 'En Proceso', 2315.00, 34.00, NULL, 34.00, NULL, 'Solo H4', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'URBINA MEDINA EVELYN LOLY', '2026-06-18 13:39:08'),
(135, '2026-06-17', 'N', 'BERTH', 'Berth 03', 22, NULL, 8, 'ACTIVE', 'Culminado', 1882.00, 277.00, NULL, 277.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'URBINA MEDINA EVELYN LOLY', '2026-06-18 13:40:33'),
(136, '2026-06-17', 'N', 'YARD', 'Yard', 17, NULL, 19, 'ACTIVE', 'En Proceso', 468.00, 3.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'URBINA MEDINA EVELYN LOLY', '2026-06-18 13:41:47'),
(137, '2026-06-16', 'N', 'YARD', 'Yard', NULL, 'SEDNA DESGAGNES / 1180043', 18, 'ACTIVE', 'Culminado', 35.00, 35.00, NULL, NULL, NULL, 'BR4A', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Sistemas CSP', '2026-06-18 13:42:54'),
(138, '2026-06-16', 'N', 'YARD', 'Yard', NULL, 'HAI HE KOU', 20, 'ACTIVE', 'Culminado', 1.00, 1.00, NULL, NULL, NULL, 'AR1A', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Sistemas CSP', '2026-06-18 13:43:18'),
(139, '2026-06-16', 'N', 'YARD', 'Yard', 17, NULL, 19, 'ACTIVE', 'En Proceso', 468.00, 177.00, NULL, NULL, NULL, 'AR1A', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Sistemas CSP', '2026-06-18 13:44:02'),
(141, '2026-06-16', 'D', 'YARD', 'Yard', NULL, 'MINERALES', 9, 'ACTIVE', 'Culminado', 12.00, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Sistemas CSP', '2026-06-18 13:51:10'),
(142, '2026-06-18', 'N', 'BERTH', 'Berth 01', 37, NULL, 2, 'ACTIVE', 'En Proceso', 37400.00, 6486.16, 4087.76, 2398.40, NULL, 'H2 2583.16 TN\nH3 2296.56 TN\nH4 2168.88 TN', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'URBINA MEDINA EVELYN LOLY', '2026-06-19 12:58:45'),
(144, '2026-06-18', 'N', 'BERTH', 'Berth 03', 38, NULL, 7, 'ACTIVE', 'En Proceso', 111.00, 37.00, NULL, 37.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'URBINA MEDINA EVELYN LOLY', '2026-06-19 13:02:48'),
(145, '2026-06-18', 'N', 'YARD', 'Yard', 22, NULL, 20, 'ACTIVE', 'En Proceso', 1882.00, 148.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'URBINA MEDINA EVELYN LOLY', '2026-06-19 13:08:06'),
(160, '2026-06-18', 'N', 'BERTH', 'Berth 02', 17, NULL, 7, 'ACTIVE', 'En Proceso', 2315.00, 255.00, NULL, 255.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Sistemas CSP', '2026-06-19 16:26:22'),
(161, '2026-06-18', 'N', 'YARD', 'Yard', 17, NULL, 19, 'ACTIVE', 'En Proceso', 468.00, 8.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Sistemas CSP', '2026-06-19 16:26:36'),
(165, '2026-06-17', 'N', 'YARD', 'Yard', 22, NULL, 20, 'ACTIVE', 'Inicio', 1882.00, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'PAUL MONTERO REZABAL', '2026-06-19 17:32:20'),
(166, '2026-06-17', 'D', 'BERTH', 'Berth 01', 37, NULL, 2, 'ACTIVE', 'Inicio', 37400.00, 660.00, NULL, 660.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'PAUL MONTERO REZABAL', '2026-06-19 17:45:04'),
(167, '2026-06-17', 'D', 'YARD', 'Yard', 37, NULL, 14, 'ACTIVE', 'Inicio', 16202.46, 0.00, NULL, NULL, NULL, 'Generado automáticamente desde muelle (descarga interna).', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'PAUL MONTERO REZABAL', '2026-06-19 17:45:04'),
(168, '2026-06-17', 'N', 'YARD', 'Yard', 37, NULL, 14, 'ACTIVE', 'En Proceso', 16202.46, 0.00, NULL, NULL, NULL, 'Generado automáticamente desde muelle (descarga interna).', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'PAUL MONTERO REZABAL', '2026-06-19 17:45:35'),
(169, '2026-06-18', 'D', 'BERTH', 'Berth 01', 37, NULL, 2, 'ACTIVE', 'En Proceso', 37400.00, 7051.82, 2840.82, 4211.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'PAUL MONTERO REZABAL', '2026-06-19 18:36:45'),
(170, '2026-06-18', 'D', 'YARD', 'Yard', 37, NULL, 14, 'ACTIVE', 'En Proceso', 16202.46, 0.00, NULL, NULL, NULL, 'Generado automáticamente desde muelle (descarga interna).', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'PAUL MONTERO REZABAL', '2026-06-19 18:36:45'),
(171, '2026-06-18', 'N', 'YARD', 'Yard', 37, NULL, 14, 'ACTIVE', 'En Proceso', 16202.46, 0.00, NULL, NULL, NULL, 'Generado automáticamente desde muelle (descarga interna).', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'PAUL MONTERO REZABAL', '2026-06-19 18:38:30'),
(172, '2026-06-18', 'D', 'BERTH', 'Berth 03', 38, NULL, 7, 'ACTIVE', 'Inicio', 111.00, 69.00, NULL, 69.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'PAUL MONTERO REZABAL', '2026-06-19 18:48:10'),
(173, '2026-06-18', 'D', 'YARD', 'Yard', 38, NULL, 19, 'ACTIVE', 'Inicio', 106.00, 0.00, NULL, NULL, NULL, 'Generado automáticamente desde muelle (descarga interna).', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'PAUL MONTERO REZABAL', '2026-06-19 18:48:10'),
(174, '2026-06-18', 'N', 'YARD', 'Yard', 38, NULL, 19, 'ACTIVE', 'En Proceso', 106.00, 0.00, NULL, NULL, NULL, 'Generado automáticamente desde muelle (descarga interna).', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'PAUL MONTERO REZABAL', '2026-06-19 18:49:57'),
(184, '2026-06-19', 'D', 'BERTH', 'Berth 01', 37, NULL, 2, 'ACTIVE', 'En Proceso', 37400.00, 3331.14, 3331.14, NULL, 4.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-06-19 23:36:10'),
(185, '2026-06-19', 'D', 'BERTH', 'Berth 02', 40, NULL, 2, 'ACTIVE', 'Inicio', 35000.00, 500.00, 500.00, NULL, 2.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-06-19 23:37:48'),
(187, '2026-06-19', 'D', 'BERTH', 'Berth 04', 42, NULL, 1, 'ACTIVE', 'Inicio', 2000.00, 360.00, 360.00, NULL, 4.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-06-20 00:37:31'),
(188, '2026-06-19', 'D', 'YARD', 'Yard', 17, NULL, 19, 'ACTIVE', 'En Proceso', 468.00, 126.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-06-20 00:45:15'),
(189, '2026-06-19', 'D', 'BERTH', 'Berth 03', 38, NULL, 7, 'ACTIVE', 'Culminado', 111.00, 5.00, 5.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-06-20 00:46:11'),
(190, '2026-06-19', 'D', 'YARD', 'Yard', 22, NULL, 20, 'ACTIVE', 'En Proceso', 1882.00, 281.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-06-20 00:48:01'),
(191, '2026-06-19', 'D', 'YARD', 'Yard', NULL, NULL, 9, 'ACTIVE', 'Culminado', 13.00, 13.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-06-20 00:50:48'),
(193, '2026-06-19', 'D', 'YARD', 'Yard', 38, NULL, 19, 'ACTIVE', 'En Proceso', 106.00, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Sistemas CSP', '2026-06-20 03:03:30'),
(194, '2026-06-19', 'N', 'BERTH', 'Berth 01', 37, NULL, 2, 'ACTIVE', 'En Proceso', 37400.00, 2608.94, 2608.94, 0.00, 3.00, 'H1 10 tolvas / 103.02\nH2 5 tolvas / 874.96 TN\nH4  42 tolvas / 1556.74 TN\nH5 2 tolvas / 74.20 TN\n\nSe culminó bodega 2', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-06-20 12:30:18'),
(195, '2026-06-19', 'N', 'BERTH', 'Berth 02', 40, NULL, 2, 'ACTIVE', 'En Proceso', 34820.96, 7091.06, 4589.32, 2501.74, 4.00, 'H2 47 tolvas / 1694.28 TN\nH3 82 tolvas / 2895.04 TN\nH5 85 tolvas / 2501.74 TN\n\nGWB:  2501.74 TN', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-06-20 12:42:37'),
(196, '2026-06-19', 'N', 'YARD', 'Yard', 40, NULL, 14, 'ACTIVE', 'Inicio', 9145.26, 0.00, NULL, NULL, NULL, 'Generado automáticamente desde muelle (descarga interna).', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-06-20 12:42:37'),
(197, '2026-06-19', 'N', 'BERTH', 'Berth 03', 41, NULL, 6, 'ACTIVE', 'Inicio', 6618.00, 980.00, 980.00, NULL, 2.00, 'Descarga directa \nDescarga de 22 contenedores para desconsolidado en zona IMO', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-06-20 12:44:56'),
(198, '2026-06-19', 'N', 'BERTH', 'Berth 04', 42, NULL, 1, 'ACTIVE', 'Culminado', 1101.00, 738.00, 738.00, NULL, 3.00, 'No tuvimos CDR', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-06-20 12:46:08'),
(199, '2026-06-19', 'N', 'YARD', 'Yard', 17, NULL, 19, 'ACTIVE', 'En Proceso', 468.00, 144.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-06-20 12:51:05'),
(200, '2026-06-19', 'N', 'YARD', 'Yard', 22, NULL, 20, 'ACTIVE', 'En Proceso', 1882.00, 130.00, NULL, NULL, NULL, 'Despacho de cigüeñas, trucks, bulto y propios medios', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-06-20 12:56:51'),
(201, '2026-06-20', 'D', 'BERTH', 'Berth 01', 37, NULL, 2, 'ACTIVE', 'En Proceso', 37400.00, 5248.60, 5248.60, NULL, 3.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RISCO SEMINARIO GRECIA DEL MAR', '2026-06-21 00:30:30'),
(202, '2026-06-20', 'D', 'BERTH', 'Berth 02', 40, NULL, 2, 'ACTIVE', 'En Proceso', 35000.00, 7085.18, 7085.18, NULL, 2.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RISCO SEMINARIO GRECIA DEL MAR', '2026-06-21 00:31:15'),
(203, '2026-06-20', 'D', 'BERTH', 'Berth 03', 41, NULL, 6, 'ACTIVE', 'En Proceso', 6618.00, 1900.00, 1900.00, NULL, 2.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RISCO SEMINARIO GRECIA DEL MAR', '2026-06-21 00:33:22'),
(204, '2026-06-20', 'D', 'YARD', 'Yard', NULL, NULL, 9, 'ACTIVE', 'Inicio', 10.00, 10.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RISCO SEMINARIO GRECIA DEL MAR', '2026-06-21 00:39:02'),
(205, '2026-06-20', 'D', 'YARD', 'Yard', 22, NULL, 20, 'ACTIVE', 'En Proceso', 1882.00, 315.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RISCO SEMINARIO GRECIA DEL MAR', '2026-06-21 00:40:23'),
(206, '2026-06-20', 'D', 'YARD', 'Yard', 17, NULL, 19, 'ACTIVE', 'En Proceso', 468.00, 35.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RISCO SEMINARIO GRECIA DEL MAR', '2026-06-21 00:40:32'),
(207, '2026-06-20', 'D', 'YARD', 'Yard', 38, NULL, 19, 'ACTIVE', 'En Proceso', 106.00, 37.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RISCO SEMINARIO GRECIA DEL MAR', '2026-06-21 00:40:54'),
(208, '2026-06-20', 'D', 'YARD', 'Yard', NULL, 'COSCO MALAYSIA', 1, 'ACTIVE', 'En Proceso', NULL, 3.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RISCO SEMINARIO GRECIA DEL MAR', '2026-06-21 00:42:25'),
(209, '2026-06-20', 'N', 'BERTH', 'Berth 01', 37, NULL, 2, 'ACTIVE', 'Culminado', 37399.20, 334.10, 184.44, 149.66, 2.00, 'Z1J1: 5 tolvas / 149.66 TN', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-06-21 13:11:47'),
(210, '2026-06-20', 'N', 'YARD', 'Yard', 37, NULL, 14, 'ACTIVE', 'En Proceso', 16202.46, 0.00, NULL, NULL, NULL, 'Generado automáticamente desde muelle (descarga interna).', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-06-21 13:11:47'),
(211, '2026-06-20', 'N', 'BERTH', 'Berth 02', 40, NULL, 2, 'ACTIVE', 'En Proceso', 34820.96, 5475.60, 3469.92, 2005.68, 3.00, 'Trabajando BL2 y 4\nH2 y 4', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-06-21 13:21:47'),
(212, '2026-06-20', 'N', 'YARD', 'Yard', 40, NULL, 14, 'ACTIVE', 'En Proceso', 9145.26, 0.00, NULL, NULL, NULL, 'Generado automáticamente desde muelle (descarga interna).', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-06-21 13:21:47'),
(213, '2026-06-20', 'N', 'BERTH', 'Berth 03', 41, NULL, 6, 'ACTIVE', 'En Proceso', 6618.00, 1260.00, 1260.00, NULL, 2.00, 'H1:  420 bbgs (+15 Sanos, 1 trasegados)\nH2:  240 bbgs (+8 sanos, 4 trade)\nH3:  400 bbgs (+16 sanos: 12 poroso, 4 tecnico)\nH4:  200 bbgs (+8 sanos)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-06-21 13:22:54'),
(214, '2026-06-20', 'N', 'BERTH', 'Berth 04', 43, NULL, 1, 'ACTIVE', 'Culminado', 157.00, 157.00, 157.00, NULL, 2.00, 'Tuvimos 3 CDR', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-06-21 13:24:04'),
(215, '2026-06-20', 'N', 'YARD', 'Yard', 22, NULL, 20, 'ACTIVE', 'En Proceso', 1882.00, 216.00, NULL, NULL, NULL, 'Despacho de cigüeñas, camabajas, por sus propios medios', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-06-21 13:25:37'),
(216, '2026-06-21', 'D', 'BERTH', 'Berth 04', 44, NULL, 1, 'ACTIVE', 'Inicio', 900.00, 346.00, 346.00, NULL, 3.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-06-21 19:10:32'),
(217, '2026-06-21', 'D', 'BERTH', 'Berth 02', 40, NULL, 2, 'ACTIVE', 'En Proceso', 35000.00, 6267.50, 6267.50, NULL, 3.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-06-21 19:11:24'),
(218, '2026-06-21', 'D', 'BERTH', 'Berth 03', 41, NULL, 6, 'ACTIVE', 'En Proceso', 6618.00, 1389.00, 1389.00, NULL, 3.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-06-21 19:12:16'),
(219, '2026-06-21', 'D', 'YARD', 'Yard', 38, NULL, 19, 'ACTIVE', 'En Proceso', 106.00, 3.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-06-21 19:14:42'),
(220, '2026-06-21', 'D', 'YARD', 'Yard', 22, NULL, 20, 'ACTIVE', 'En Proceso', 1882.00, 19.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-06-21 19:15:00'),
(221, '2026-06-21', 'N', 'BERTH', 'Berth 02', 40, NULL, 2, 'ACTIVE', 'En Proceso', 35000.00, 3287.54, 2428.54, 859.00, 3.00, 'H2: 29 tolvas (14 internas, 15 externas)\nH3: 2 tolvas (1 interna, 1 externa)\nH4: 59 tolvas (9 internas, 50 externas)\nGWA:  24 tolvas/ 859 TN', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-06-22 12:21:27'),
(222, '2026-06-21', 'N', 'YARD', 'Yard', 40, NULL, 14, 'ACTIVE', 'En Proceso', 9145.26, 0.00, NULL, NULL, NULL, 'Generado automáticamente desde muelle (descarga interna).', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-06-22 12:21:27'),
(223, '2026-06-21', 'N', 'BERTH', 'Berth 03', 41, NULL, 6, 'ACTIVE', 'En Proceso', 6618.00, 718.00, 718.00, NULL, 2.00, 'H1: 20 bbgs\nH2: 378 bbgs \nH4: 320 bbgs\nBbgs vacíos: 58 grandes y 1 pequeños', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-06-22 12:24:43'),
(224, '2026-06-21', 'N', 'BERTH', 'Berth 04', 44, NULL, 1, 'ACTIVE', 'Culminado', 768.00, 422.00, 422.00, NULL, 3.00, '3 CDR en turno', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-06-22 12:27:20'),
(225, '2026-06-22', 'D', 'BERTH', 'Berth 02', 40, NULL, 2, 'ACTIVE', 'En Proceso', 35000.00, 3750.00, NULL, 3750.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'URBINA MEDINA EVELYN LOLY', '2026-06-23 00:52:25'),
(227, '2026-06-22', 'D', 'BERTH', 'Berth 03', 41, NULL, 6, 'ACTIVE', 'En Proceso', 6618.00, 400.00, 400.00, NULL, NULL, 'Fin de operaciones y embarque de 22 contenedores', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'URBINA MEDINA EVELYN LOLY', '2026-06-23 00:54:16'),
(228, '2026-06-22', 'D', 'YARD', 'Yard', 22, NULL, 20, 'ACTIVE', 'En Proceso', 1882.00, 189.00, NULL, NULL, NULL, '19 maquinarias y resto vehículos por sus propios medios', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'URBINA MEDINA EVELYN LOLY', '2026-06-23 00:55:09'),
(229, '2026-06-22', 'D', 'YARD', 'Yard', 37, NULL, 14, 'ACTIVE', 'En Proceso', 16202.46, 3371.42, NULL, NULL, NULL, '95 tolvas', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'URBINA MEDINA EVELYN LOLY', '2026-06-23 00:56:11'),
(230, '2026-06-22', 'D', 'YARD', 'Yard', 17, NULL, 19, 'ACTIVE', 'En Proceso', 468.00, 18.00, NULL, NULL, NULL, 'Despachos de trucks', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'URBINA MEDINA EVELYN LOLY', '2026-06-23 00:57:39'),
(231, '2026-06-22', 'N', 'YARD', 'Yard', 22, NULL, 20, 'ACTIVE', 'En Proceso', 1882.00, 104.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-06-23 03:56:26'),
(232, '2026-06-22', 'N', 'YARD', 'Yard', 17, NULL, 19, 'ACTIVE', 'En Proceso', 468.00, 10.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-06-23 03:57:00'),
(233, '2026-06-22', 'N', 'BERTH', 'Berth 04', 45, NULL, 1, 'ACTIVE', 'Inicio', 3139.00, 791.00, 791.00, NULL, 6.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-06-23 04:01:46'),
(234, '2026-06-22', 'N', 'BERTH', 'Berth 03', 46, NULL, 1, 'ACTIVE', 'Inicio', 22.00, 22.00, 22.00, NULL, 1.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-06-23 04:08:15'),
(235, '2026-06-22', 'N', 'BERTH', 'Berth 02', 40, NULL, 2, 'ACTIVE', 'En Proceso', 35000.00, 36.60, 36.60, NULL, 1.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-06-23 08:25:00'),
(236, '2026-06-23', 'D', 'BERTH', 'Berth 04', 45, NULL, 1, 'ACTIVE', 'En Proceso', 3139.00, 1202.00, 1202.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'URBINA MEDINA EVELYN LOLY', '2026-06-24 01:54:15'),
(237, '2026-06-23', 'D', 'YARD', 'Yard', 40, NULL, 14, 'ACTIVE', 'En Proceso', 9145.26, 7200.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'URBINA MEDINA EVELYN LOLY', '2026-06-24 02:01:03'),
(238, '2026-06-23', 'D', 'YARD', 'Yard', 37, NULL, 14, 'ACTIVE', 'En Proceso', 16202.46, 2735.60, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'URBINA MEDINA EVELYN LOLY', '2026-06-24 02:04:01'),
(239, '2026-06-23', 'D', 'YARD', 'Yard', 22, NULL, 20, 'ACTIVE', 'En Proceso', 1882.00, 162.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'URBINA MEDINA EVELYN LOLY', '2026-06-24 02:05:18'),
(240, '2026-06-23', 'N', 'BERTH', 'Berth 04', 45, NULL, 1, 'ACTIVE', 'En Proceso', 3139.00, 1157.00, 1157.00, NULL, 5.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-06-24 03:45:00'),
(241, '2026-06-23', 'N', 'YARD', 'Yard', 22, NULL, 20, 'ACTIVE', 'En Proceso', 1882.00, 42.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-06-24 03:45:25'),
(242, '2026-06-23', 'N', 'YARD', 'Yard', 40, NULL, 16, 'ACTIVE', 'Inicio', 9145.26, 2262.28, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-06-24 03:46:13'),
(243, '2026-06-23', 'N', 'YARD', 'Yard', 37, NULL, 14, 'ACTIVE', 'En Proceso', 16202.46, 1918.84, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-06-24 03:46:55'),
(244, '2026-06-24', 'D', 'BERTH', 'Berth 03', NULL, NULL, 1, 'ACTIVE', 'Inicio', 1496.00, 512.00, 512.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'URBINA MEDINA EVELYN LOLY', '2026-06-25 00:06:29'),
(245, '2026-06-24', 'D', 'YARD', 'Yard', 40, NULL, 16, 'ACTIVE', 'En Proceso', 9145.26, 1313.84, NULL, NULL, NULL, '38 tolvas atendidas', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'URBINA MEDINA EVELYN LOLY', '2026-06-25 00:07:55'),
(246, '2026-06-24', 'D', 'YARD', 'Yard', 37, NULL, 14, 'ACTIVE', 'En Proceso', 16202.46, 1385.80, NULL, NULL, NULL, '39 tolvas atendidas', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'URBINA MEDINA EVELYN LOLY', '2026-06-25 00:11:11'),
(247, '2026-06-24', 'D', 'YARD', 'Yard', 22, NULL, 20, 'ACTIVE', 'En Proceso', 1882.00, 90.00, NULL, NULL, NULL, 'Despachos de vehículos en cigüeñas', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'URBINA MEDINA EVELYN LOLY', '2026-06-25 00:11:56'),
(248, '2026-06-24', 'N', 'BERTH', 'Berth 03', NULL, NULL, 1, 'ACTIVE', 'En Proceso', 1496.00, 732.00, 732.00, NULL, 4.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-06-25 10:01:45'),
(249, '2026-06-24', 'N', 'YARD', 'Yard', 22, NULL, 20, 'ACTIVE', 'En Proceso', 1882.00, 31.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-06-25 10:04:29'),
(250, '2026-06-24', 'N', 'YARD', 'Yard', 40, NULL, 14, 'ACTIVE', 'En Proceso', 9145.26, 1543.14, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-06-25 10:06:03'),
(251, '2026-06-24', 'N', 'YARD', 'Yard', 37, NULL, 14, 'ACTIVE', 'En Proceso', 16202.46, 2015.92, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-06-25 10:06:31'),
(252, '2026-06-25', 'D', 'BERTH', 'Berth 02', 40, NULL, 2, 'ACTIVE', 'En Proceso', 35000.00, 88.64, 88.64, NULL, 2.00, 'H1: soya BL4 (modalidad mixta)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-06-26 00:24:51'),
(253, '2026-06-25', 'D', 'YARD', 'Yard', NULL, 'COSCO MALAYSIA', 20, 'ACTIVE', 'En Proceso', 60.00, 178.00, NULL, NULL, NULL, 'Despacho de cigüeñas: 30 cigüeñas/  178 autos', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-06-26 00:26:06'),
(254, '2026-06-25', 'D', 'YARD', 'Yard', 40, NULL, 14, 'ACTIVE', 'En Proceso', 9145.26, 446.50, NULL, NULL, NULL, 'Georgia: \nGWA -  13 tolvas / 446.5 TN maíz', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-06-26 00:32:27'),
(255, '2026-06-25', 'D', 'YARD', 'Yard', 37, NULL, 14, 'ACTIVE', 'En Proceso', 16202.46, 1154.60, NULL, NULL, NULL, 'Norvic: GWC -  31 tolvas / 1154.60 TN', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-06-26 00:33:46'),
(256, '2026-06-25', 'D', 'YARD', 'Yard', 40, NULL, 16, 'ACTIVE', 'En Proceso', 9145.26, 30.62, NULL, NULL, NULL, 'GWB - 1 tolva / 30.62 TN soya', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-06-26 00:36:22'),
(258, '2026-06-25', 'D', 'YARD', 'Yard', NULL, NULL, 12, 'ACTIVE', 'Culminado', 60.00, 60.00, NULL, NULL, NULL, '20 contenedores / 60 autos', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-06-26 00:51:20'),
(259, '2026-06-25', 'D', 'YARD', 'Yard', NULL, NULL, 9, 'ACTIVE', 'Inicio', 36.00, 36.00, NULL, NULL, NULL, '36 Plataformas', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-06-26 00:51:58'),
(260, '2026-06-25', 'N', 'BERTH', 'Berth 02', 40, NULL, 2, 'ACTIVE', 'En Proceso', 35000.00, 1573.43, 1573.43, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'URBINA MEDINA EVELYN LOLY', '2026-06-26 12:14:42'),
(261, '2026-06-25', 'N', 'YARD', 'Yard', 40, NULL, 16, 'ACTIVE', 'En Proceso', 9145.26, 163.26, NULL, NULL, NULL, '05 tolvas atendidas', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'URBINA MEDINA EVELYN LOLY', '2026-06-26 12:15:19'),
(262, '2026-06-25', 'N', 'YARD', 'Yard', 37, NULL, 14, 'ACTIVE', 'En Proceso', 16202.46, 545.30, NULL, NULL, NULL, '15 tolvas atendidas', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'URBINA MEDINA EVELYN LOLY', '2026-06-26 12:16:18'),
(263, '2026-06-26', 'D', 'BERTH', 'Berth 02', 40, NULL, 2, 'ACTIVE', 'Culminado', 35000.00, 28.84, NULL, 28.84, 2.00, 'H1:  2 tolvas (internas) /  28.84TN', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-06-27 00:42:31'),
(264, '2026-06-26', 'D', 'YARD', 'Yard', 40, NULL, 14, 'ACTIVE', 'En Proceso', 9145.26, 253.10, NULL, NULL, NULL, 'GWA - 1 tolva /  5.54 TN maiz\nGWB - 8 tolvas /  247.56 TN Soya', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-06-27 00:42:31'),
(265, '2026-06-26', 'D', 'YARD', 'Yard', 37, NULL, 14, 'ACTIVE', 'En Proceso', 16202.46, 869.34, NULL, NULL, NULL, 'GWC - 24  tolvas /  869.34 TN', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-06-27 00:43:56'),
(266, '2026-06-26', 'D', 'YARD', 'Yard', NULL, NULL, 9, 'ACTIVE', 'Culminado', 60.00, 60.00, NULL, NULL, NULL, 'Se relevó a tallys de turno día para cierre', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-06-27 00:45:56'),
(268, '2026-06-26', 'D', 'YARD', 'Yard', NULL, 'CSCL SATURN', 19, 'ACTIVE', 'Culminado', 30.00, 26.00, NULL, NULL, NULL, 'Despacho de bultos: 3 plataforma/   26 Bultos', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-06-27 00:58:24'),
(269, '2026-06-26', 'D', 'YARD', 'Yard', 38, NULL, 19, 'ACTIVE', 'En Proceso', 106.00, 14.00, NULL, NULL, NULL, 'Despacho de bultos: 4 plataforma/   14 Bultos', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-06-27 01:00:32'),
(270, '2026-06-26', 'N', 'YARD', 'Yard', 37, NULL, 14, 'ACTIVE', 'En Proceso', 16202.46, 480.42, NULL, NULL, NULL, '14 tolvas atendidas', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'URBINA MEDINA EVELYN LOLY', '2026-06-27 11:44:54'),
(271, '2026-06-26', 'N', 'YARD', 'Yard', 40, NULL, 14, 'ACTIVE', 'En Proceso', 9145.26, 9.78, NULL, NULL, NULL, 'Fin de operaciones', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'URBINA MEDINA EVELYN LOLY', '2026-06-27 11:47:27'),
(272, '2026-06-27', 'D', 'YARD', 'Yard', NULL, NULL, 9, 'ACTIVE', 'Culminado', 60.00, 60.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-06-28 00:20:19'),
(273, '2026-06-27', 'D', 'YARD', 'Yard', 17, NULL, 19, 'ACTIVE', 'En Proceso', 468.00, 4.00, NULL, NULL, NULL, 'Despacho de bultos: 2 plataforma/ 4 Bultos', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-06-28 00:20:59'),
(274, '2026-06-27', 'D', 'YARD', 'Yard', 38, NULL, 19, 'ACTIVE', 'En Proceso', 106.00, 7.00, NULL, NULL, NULL, 'Despacho de bultos:  1 plataforma/ 7 Bultos', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-06-28 00:23:53'),
(275, '2026-06-27', 'D', 'YARD', 'Yard', 37, NULL, 14, 'ACTIVE', 'En Proceso', 16202.46, 218.04, NULL, NULL, NULL, 'Norvic: GWC -   6 tolvas /   218.04 TN', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-06-28 00:25:21'),
(276, '2026-06-27', 'D', 'YARD', 'Yard', NULL, 'SATURN', 20, 'ACTIVE', 'Inicio', 32.00, 32.00, NULL, NULL, NULL, 'Despacho de cigüeñas: 4 plataformas/32 autos', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-06-28 00:27:49'),
(277, '2026-06-27', 'N', 'BERTH', 'Berth 02', 48, NULL, 8, 'ACTIVE', 'Inicio', 1487.00, 59.00, 59.00, NULL, 12.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RISCO SEMINARIO GRECIA DEL MAR', '2026-06-28 12:41:25'),
(278, '2026-06-27', 'N', 'BERTH', 'Berth 03', NULL, NULL, 1, 'ACTIVE', 'Inicio', 148.00, 67.00, 67.00, NULL, 2.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RISCO SEMINARIO GRECIA DEL MAR', '2026-06-28 12:44:49'),
(279, '2026-06-27', 'N', 'YARD', 'Yard', NULL, 'SATURN', 20, 'ACTIVE', 'Culminado', 32.00, 28.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RISCO SEMINARIO GRECIA DEL MAR', '2026-06-28 12:45:15'),
(280, '2026-06-27', 'N', 'YARD', 'Yard', 37, NULL, 14, 'ACTIVE', 'En Proceso', 16202.46, 257.26, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RISCO SEMINARIO GRECIA DEL MAR', '2026-06-28 12:49:55'),
(281, '2026-06-28', 'N', 'BERTH', 'Berth 02', 48, NULL, 8, 'ACTIVE', 'Culminado', 1487.00, 599.00, 599.00, NULL, 12.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-06-29 00:50:57'),
(283, '2026-06-28', 'N', 'BERTH', 'Berth 04', 51, NULL, 1, 'ACTIVE', 'En Proceso', 2012.00, 1246.00, 1246.00, NULL, 4.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-06-29 01:12:08'),
(284, '2026-06-28', 'N', 'BERTH', 'Berth 04', 52, NULL, 1, 'ACTIVE', 'En Proceso', 830.00, 249.00, 249.00, NULL, 3.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-06-29 01:15:13'),
(286, '2026-06-29', 'D', 'BERTH', 'Berth 04', 52, NULL, 1, 'ACTIVE', 'Culminado', 830.00, 581.00, 581.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'URBINA MEDINA EVELYN LOLY', '2026-06-29 23:52:22'),
(287, '2026-06-29', 'D', 'BERTH', 'Berth 04', 51, NULL, 1, 'ACTIVE', 'Culminado', 2012.00, 766.00, 766.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'URBINA MEDINA EVELYN LOLY', '2026-06-29 23:53:39'),
(288, '2026-06-29', 'D', 'YARD', 'Yard', NULL, 'CHENG KANG KOU', 20, 'ACTIVE', 'Inicio', 1487.00, 86.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'URBINA MEDINA EVELYN LOLY', '2026-06-29 23:58:50'),
(289, '2026-06-29', 'D', 'YARD', 'Yard', 38, NULL, 19, 'ACTIVE', 'En Proceso', 106.00, 1.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'URBINA MEDINA EVELYN LOLY', '2026-06-29 23:59:59'),
(290, '2026-06-29', 'N', 'YARD', 'Yard', NULL, 'CHENG KANG KOU', 20, 'ACTIVE', 'En Proceso', 1487.00, 22.00, NULL, NULL, NULL, 'cigueñas llegan a partir de las 1200hrs \ndespacho por sus propios medios llegaran a partir de las 0800hrs', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RISCO SEMINARIO GRECIA DEL MAR', '2026-06-30 10:11:20'),
(299, '2026-06-30', 'D', 'YARD', 'Yard', NULL, 'CHENG KANG KOU', 20, 'ACTIVE', 'En Proceso', 1487.00, 160.00, NULL, NULL, NULL, '17 cigüeñas y demás despachos por sus propios medios', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'URBINA MEDINA EVELYN LOLY', '2026-07-01 00:07:36'),
(300, '2026-06-30', 'D', 'YARD', 'Yard', 38, NULL, 19, 'ACTIVE', 'En Proceso', 106.00, 6.00, NULL, NULL, NULL, '1 carreta', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'URBINA MEDINA EVELYN LOLY', '2026-07-01 00:13:33'),
(302, '2026-06-30', 'N', 'YARD', 'Yard', NULL, 'CHENG KANG KOU', 20, 'ACTIVE', 'En Proceso', 1487.00, 90.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-07-01 07:34:22'),
(309, '2026-06-30', 'N', 'BERTH', 'Berth 03', 58, NULL, 1, 'ACTIVE', 'Culminado', 432.00, 200.00, 200.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Sistemas CSP', '2026-07-01 15:58:28'),
(315, '2026-06-30', 'D', 'BERTH', 'Berth 03', 58, NULL, 1, 'ACTIVE', 'Culminado', 432.00, 232.00, 232.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Sistemas CSP', '2026-07-01 16:25:08'),
(316, '2026-07-01', 'D', 'YARD', 'Yard', 38, NULL, 19, 'ACTIVE', 'En Proceso', 106.00, 10.00, NULL, NULL, NULL, '8 carretas', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'URBINA MEDINA EVELYN LOLY', '2026-07-01 23:33:06'),
(317, '2026-07-01', 'D', 'YARD', 'Yard', NULL, 'CHENG KANG KOU', 20, 'ACTIVE', 'En Proceso', 1487.00, 233.00, NULL, NULL, NULL, '-Cigüeñas : 28 / 173 vehículos\n-Propios medios: 60 vehículos', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'URBINA MEDINA EVELYN LOLY', '2026-07-01 23:40:09'),
(318, '2026-07-01', 'N', 'YARD', 'Yard', NULL, 'CHENG KANG KOU', 20, 'ACTIVE', 'En Proceso', 1487.00, 88.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-07-02 07:56:36'),
(319, '2026-07-02', 'D', 'YARD', 'Yard', 38, NULL, 19, 'ACTIVE', 'En Proceso', 106.00, 5.00, NULL, NULL, NULL, 'Despacho de bultos:  4 plataforma/  5 Bultos', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-07-03 01:37:32'),
(320, '2026-07-02', 'D', 'YARD', 'Yard', NULL, 'Cosco pacific', 1, 'ACTIVE', 'Culminado', 7.00, 7.00, NULL, NULL, NULL, 'Despacho de cigüeñas:  1 plataformas /  7 autos', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-07-03 01:38:28'),
(321, '2026-07-02', 'D', 'YARD', 'Yard', NULL, 'COSCO SHIPPING THAMES', 1, 'ACTIVE', 'Culminado', 51.00, 51.00, NULL, NULL, NULL, 'Despacho de cigüeñas:  9 plataformas /  51 autos', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-07-03 01:39:59');
INSERT INTO `tallyman_registros` (`id`, `fecha_turno`, `turno`, `ubicacion_tipo`, `ubicacion`, `nave_id`, `nave_patio`, `actividad_id`, `estado_pos`, `status_act`, `planned`, `executed`, `directa`, `interna`, `productivity`, `details`, `cargo_type`, `bl`, `producto`, `unidades`, `tons`, `ubic_codigo`, `coord_entrante`, `coord_saliente`, `registrado_por`, `fecha_registro`) VALUES
(322, '2026-07-02', 'D', 'YARD', 'Yard', NULL, 'CHENG KANG KOU', 20, 'ACTIVE', 'En Proceso', 1487.00, 262.00, NULL, NULL, NULL, 'Despacho de cigüeñas:  23 plataformas /  150 autos\n32 Maquinarias\n80 Propios medios', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-07-03 01:41:13'),
(323, '2026-07-02', 'D', 'YARD', 'Yard', NULL, NULL, 9, 'ACTIVE', 'Culminado', 12.00, 12.00, NULL, NULL, NULL, '12 plataformas', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-07-03 01:41:45'),
(324, '2026-07-02', 'D', 'YARD', 'Yard', NULL, NULL, 12, 'ACTIVE', 'Inicio', 52.00, 52.00, NULL, NULL, NULL, '52 contenedores / pendiente tarjar 104 chasis', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-07-03 01:42:35'),
(325, '2026-07-03', 'D', 'BERTH', 'Berth 03', 63, NULL, 1, 'ACTIVE', 'Culminado', 311.00, 311.00, 311.00, NULL, NULL, '6 USR', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-07-04 00:41:04'),
(326, '2026-07-03', 'D', 'YARD', 'Yard', 22, NULL, 20, 'ACTIVE', 'En Proceso', 1882.00, 74.00, NULL, NULL, NULL, '12 maq\n1 plataforma / 2 bultos\n4 buses por sus propios medios\n7 cigüeñas / 56 chasis', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-07-04 00:44:06'),
(327, '2026-07-03', 'D', 'YARD', 'Yard', NULL, NULL, 12, 'ACTIVE', 'Culminado', 55.00, 55.00, NULL, NULL, NULL, '55 contenedores / 136 chasis. pendiente tarjar 38 chasis', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-07-04 00:45:22'),
(328, '2026-07-03', 'D', 'YARD', 'Yard', NULL, NULL, 20, 'ACTIVE', 'Culminado', 121.00, 121.00, NULL, NULL, NULL, 'Cigüeñas 17/ 121 chasis', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-07-04 00:48:40'),
(329, '2026-07-03', 'N', 'YARD', 'Yard', NULL, 'CHENG KANG KOU', 20, 'ACTIVE', 'En Proceso', 1487.00, 16.00, NULL, NULL, NULL, '2 cigueñas en el turno', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'URBINA MEDINA EVELYN LOLY', '2026-07-04 12:00:04'),
(330, '2026-07-03', 'N', 'YARD', 'Yard', NULL, 'COSCO THAMES', 20, 'ACTIVE', 'Inicio', 392.00, 30.00, NULL, NULL, NULL, 'Despacho en cigueñas', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'URBINA MEDINA EVELYN LOLY', '2026-07-04 12:02:49'),
(331, '2026-07-04', 'D', 'YARD', 'Yard', 22, NULL, 20, 'ACTIVE', 'En Proceso', 1882.00, 3.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RISCO SEMINARIO GRECIA DEL MAR', '2026-07-04 23:59:09'),
(332, '2026-07-04', 'D', 'YARD', 'Yard', 38, NULL, 19, 'ACTIVE', 'En Proceso', 106.00, 20.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RISCO SEMINARIO GRECIA DEL MAR', '2026-07-05 00:01:25'),
(333, '2026-07-04', 'D', 'YARD', 'Yard', NULL, 'CHENG KANG KOU', 20, 'ACTIVE', 'En Proceso', 1487.00, 72.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RISCO SEMINARIO GRECIA DEL MAR', '2026-07-05 00:03:15'),
(334, '2026-07-04', 'D', 'BERTH', 'Berth 01', 64, NULL, 2, 'ACTIVE', 'Inicio', 25779.53, 1684.66, 1684.66, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RISCO SEMINARIO GRECIA DEL MAR', '2026-07-05 00:19:41'),
(335, '2026-07-04', 'D', 'YARD', 'Yard', NULL, NULL, 12, 'ACTIVE', 'Culminado', 29.00, 29.00, NULL, NULL, NULL, '29 contenedores/ 87 chasis', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RISCO SEMINARIO GRECIA DEL MAR', '2026-07-05 00:35:29'),
(342, '2026-07-04', 'N', 'BERTH', 'Berth 01', 64, NULL, 2, 'ACTIVE', 'En Proceso', 25779.53, 9544.46, 4361.34, 5183.12, NULL, 'GWA: 4043.52 TN\nGWD: 1139.60 TN', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-07-05 12:17:04'),
(344, '2026-07-04', 'N', 'BERTH', 'Berth 03', 67, NULL, 1, 'ACTIVE', 'Culminado', 178.00, 178.00, 178.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-07-05 12:20:18'),
(345, '2026-07-04', 'N', 'BERTH', 'Berth 04', 68, NULL, 1, 'ACTIVE', 'Culminado', 476.00, 476.00, 476.00, NULL, NULL, '5 CDR', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-07-05 12:21:14'),
(346, '2026-07-04', 'N', 'YARD', 'Yard', NULL, 'CHENG KANG KOU', 20, 'ACTIVE', 'En Proceso', 1487.00, 40.00, NULL, NULL, NULL, '5 cigüeñas /  40 chasis', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-07-05 12:21:55'),
(347, '2026-07-04', 'N', 'YARD', 'Yard', NULL, 'COSCO THAMES', 20, 'ACTIVE', 'En Proceso', 392.00, 43.00, NULL, NULL, NULL, '6 cigüeñas /   43 chasis', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-07-05 12:22:24'),
(348, '2026-07-05', 'D', 'YARD', 'Yard', NULL, 'CHENG KANG KOU', 20, 'ACTIVE', 'En Proceso', 1487.00, 258.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RISCO SEMINARIO GRECIA DEL MAR', '2026-07-05 23:51:42'),
(349, '2026-07-05', 'D', 'YARD', 'Yard', NULL, 'COSCO THAMES', 20, 'ACTIVE', 'En Proceso', 392.00, 58.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RISCO SEMINARIO GRECIA DEL MAR', '2026-07-05 23:52:03'),
(350, '2026-07-05', 'D', 'BERTH', 'Berth 01', 64, NULL, 2, 'ACTIVE', 'En Proceso', 25779.53, 10415.82, 0.00, 10415.82, NULL, 'Soya : 1831.42\nMaíz : 4166.54', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RISCO SEMINARIO GRECIA DEL MAR', '2026-07-06 00:01:30'),
(351, '2026-07-05', 'D', 'YARD', 'Yard', 64, NULL, 14, 'ACTIVE', 'Inicio', 17748.32, 0.00, NULL, NULL, NULL, 'Generado automáticamente desde muelle (descarga interna).', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RISCO SEMINARIO GRECIA DEL MAR', '2026-07-06 00:14:12'),
(352, '2026-07-05', 'N', 'BERTH', 'Berth 03', 69, NULL, 1, 'ACTIVE', 'Culminado', 658.00, 658.00, 658.00, NULL, NULL, '19 CDR', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-07-06 12:00:15'),
(353, '2026-07-05', 'N', 'BERTH', 'Berth 01', 64, NULL, 2, 'ACTIVE', 'En Proceso', 25779.53, 3919.96, 1770.58, 2149.38, NULL, 'H2: 1175.68 interno (45) /  1535.90 (55)\nH3:  806.44 interno\nH5:  167.26 interno (6) / 245.62 (9) externo\n\nGWB:  1342.94 TN\nZ1J1:  806.44 TN', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-07-06 12:06:29'),
(355, '2026-07-05', 'N', 'YARD', 'Yard', NULL, 'CHENG KANG KOU', 20, 'ACTIVE', 'En Proceso', 1487.00, 53.00, NULL, NULL, NULL, '7 cigüeñas /  53 chasis', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-07-06 12:08:34'),
(356, '2026-07-06', 'D', 'BERTH', 'Berth 04', 70, NULL, 1, 'ACTIVE', 'Inicio', 2227.00, 1134.00, 1134.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-07-06 23:49:05'),
(357, '2026-07-06', 'D', 'YARD', 'Yard', 64, NULL, 14, 'ACTIVE', 'En Proceso', 17748.32, 3878.50, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-07-06 23:50:08'),
(358, '2026-07-06', 'D', 'YARD', 'Yard', 64, NULL, 16, 'ACTIVE', 'Inicio', 17748.32, 466.90, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-07-06 23:50:45'),
(359, '2026-07-06', 'D', 'YARD', 'Yard', NULL, 'CHENG KANG KOU', 20, 'ACTIVE', 'En Proceso', 1487.00, 4.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-07-06 23:51:09'),
(360, '2026-07-06', 'N', 'BERTH', 'Berth 04', 70, NULL, 1, 'ACTIVE', 'Culminado', 2227.00, 1090.00, 1090.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RISCO SEMINARIO GRECIA DEL MAR', '2026-07-07 12:08:03'),
(361, '2026-07-06', 'N', 'YARD', 'Yard', 64, NULL, 16, 'ACTIVE', 'En Proceso', 17748.32, 4111.26, NULL, NULL, NULL, 'MAÍZ: 1686.22\nSOYA : 2423.82', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RISCO SEMINARIO GRECIA DEL MAR', '2026-07-07 12:23:19'),
(362, '2026-07-06', 'N', 'YARD', 'Yard', NULL, 'COSCO MALAYSIA', 19, 'ACTIVE', 'Culminado', 60.00, 60.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RISCO SEMINARIO GRECIA DEL MAR', '2026-07-07 12:30:55'),
(363, '2026-07-06', 'N', 'YARD', 'Yard', NULL, 'CSCL SATURN', 19, 'ACTIVE', 'Culminado', 30.00, 30.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RISCO SEMINARIO GRECIA DEL MAR', '2026-07-07 12:33:16'),
(364, '2026-07-07', 'D', 'BERTH', 'Berth 04', 71, NULL, 1, 'ACTIVE', 'Inicio', 1048.00, 566.00, 566.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-07-08 00:08:08'),
(365, '2026-07-07', 'D', 'BERTH', 'Berth 03', 72, NULL, 1, 'ACTIVE', 'Inicio', 405.00, 98.00, 98.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-07-08 00:08:57'),
(366, '2026-07-07', 'D', 'YARD', 'Yard', 64, NULL, 14, 'ACTIVE', 'En Proceso', 17748.32, 3382.56, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-07-08 00:13:49'),
(367, '2026-07-07', 'D', 'YARD', 'Yard', 64, NULL, 16, 'ACTIVE', 'En Proceso', 17748.32, 1348.44, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-07-08 00:15:11'),
(368, '2026-07-07', 'D', 'YARD', 'Yard', NULL, 'COSCO THAMES', 20, 'ACTIVE', 'En Proceso', 392.00, 75.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-07-08 00:15:28'),
(369, '2026-07-07', 'N', 'BERTH', 'Berth 04', 71, NULL, 1, 'ACTIVE', 'Culminado', 1048.00, 619.00, 619.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RISCO SEMINARIO GRECIA DEL MAR', '2026-07-08 11:27:54'),
(370, '2026-07-07', 'N', 'YARD', 'Yard', 64, NULL, 16, 'ACTIVE', 'En Proceso', 17748.32, 941.94, NULL, NULL, NULL, '941.94 tn / 25 tolvas', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RISCO SEMINARIO GRECIA DEL MAR', '2026-07-08 11:29:49'),
(371, '2026-07-07', 'N', 'YARD', 'Yard', NULL, 'COSCO THAMES', 20, 'ACTIVE', 'En Proceso', 392.00, 13.00, NULL, NULL, NULL, '40 chasis / 6 Cigueñas', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RISCO SEMINARIO GRECIA DEL MAR', '2026-07-08 11:34:25'),
(372, '2026-07-07', 'N', 'BERTH', 'Berth 03', 72, NULL, 1, 'ACTIVE', 'En Proceso', 399.00, 326.00, 326.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RISCO SEMINARIO GRECIA DEL MAR', '2026-07-08 11:35:01'),
(373, '2026-07-08', 'D', 'BERTH', 'Berth 03', 72, NULL, 1, 'ACTIVE', 'Culminado', 405.00, 30.00, 30.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ROJAS RUIZ KAROLAY LISBETH', '2026-07-08 19:38:33'),
(374, '2026-07-08', 'D', 'BERTH', 'Berth 03', 73, NULL, 28, 'ACTIVE', 'Inicio', 1923.00, 860.00, 860.00, NULL, NULL, 'Hold 1: 400\nHold 2: 460', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ROJAS RUIZ KAROLAY LISBETH', '2026-07-08 19:41:04'),
(375, '2026-07-08', 'D', 'YARD', 'Yard', NULL, NULL, 9, 'ACTIVE', 'En Proceso', 196.00, 47.00, NULL, NULL, NULL, '47 containers from 6 bookings were closed.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ROJAS RUIZ KAROLAY LISBETH', '2026-07-09 00:29:21'),
(376, '2026-07-08', 'D', 'YARD', 'Yard', NULL, 'COSCO THAMES', 20, 'ACTIVE', 'En Proceso', 392.00, 45.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ROJAS RUIZ KAROLAY LISBETH', '2026-07-09 00:30:51'),
(377, '2026-07-08', 'D', 'YARD', 'Yard', NULL, 'CHENG KANG KOU', 20, 'ACTIVE', 'En Proceso', 1487.00, 3.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ROJAS RUIZ KAROLAY LISBETH', '2026-07-09 00:34:07'),
(378, '2026-07-08', 'N', 'BERTH', 'Berth 03', 73, NULL, 28, 'ACTIVE', 'En Proceso', 1923.00, 1063.00, 1063.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-07-09 03:56:40'),
(379, '2026-07-09', 'D', 'BERTH', 'Berth 02', 74, NULL, 7, 'ACTIVE', 'Inicio', 15000.00, 2213.24, NULL, 2213.24, NULL, 'SOLO HEMOS TRABAJADO EN EL TURNO HOLD 2', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ROJAS RUIZ KAROLAY LISBETH', '2026-07-10 00:12:16'),
(381, '2026-07-09', 'D', 'YARD', 'Yard', NULL, 'XIN OU ZHOU', 12, 'ACTIVE', 'Inicio', 198.00, 18.00, NULL, NULL, NULL, 'Se terminó el desconsolidado del día de ayer fueron 65 contenedores por 3 vehículos cada uno.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ROJAS RUIZ KAROLAY LISBETH', '2026-07-10 00:15:30'),
(382, '2026-07-09', 'D', 'YARD', 'Yard', NULL, NULL, 9, 'ACTIVE', 'Culminado', 51.00, 51.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ROJAS RUIZ KAROLAY LISBETH', '2026-07-10 00:16:47'),
(383, '2026-07-09', 'D', 'YARD', 'Yard', NULL, 'CHENG KANG KOU', 23, 'ACTIVE', 'En Proceso', 1487.00, 3.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ROJAS RUIZ KAROLAY LISBETH', '2026-07-10 00:19:50'),
(384, '2026-07-09', 'N', 'YARD', 'Yard', 74, NULL, 21, 'ACTIVE', 'Inicio', 15000.00, 4389.02, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-07-10 12:21:18'),
(385, '2026-07-10', 'D', 'YARD', 'Yard', 74, NULL, 21, 'ACTIVE', 'En Proceso', 15000.00, 4447.76, NULL, NULL, NULL, 'H2: 69 tolvas / 2560.42 TN\nH4:  53 tolvas / 1887.34 TN', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-07-10 23:37:48'),
(386, '2026-07-10', 'D', 'YARD', 'Yard', NULL, NULL, 9, 'ACTIVE', 'Culminado', 39.00, 39.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-07-10 23:38:11'),
(387, '2026-07-10', 'N', 'BERTH', 'Berth 03', 75, NULL, 28, 'ACTIVE', 'Culminado', 1200.00, 1200.00, 1200.00, NULL, NULL, 'Se trabajó todo el operativo sin observaciones ni errores de pedeteos.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ROJAS RUIZ KAROLAY LISBETH', '2026-07-11 11:57:27'),
(388, '2026-07-10', 'N', 'BERTH', 'Berth 04', 76, NULL, 1, 'ACTIVE', 'En Proceso', 433.00, 417.00, 417.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ROJAS RUIZ KAROLAY LISBETH', '2026-07-11 12:08:42'),
(389, '2026-07-11', 'D', 'BERTH', 'Berth 04', 76, NULL, 1, 'ACTIVE', 'Culminado', 433.00, 16.00, 16.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-07-11 12:49:07'),
(390, '2026-07-11', 'D', 'YARD', 'Yard', 74, NULL, 21, 'ACTIVE', 'Culminado', 15000.00, 388.72, NULL, NULL, NULL, '11 tolvas atendidas', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-07-11 20:08:23'),
(391, '2026-07-11', 'D', 'YARD', 'Yard', NULL, NULL, 9, 'ACTIVE', 'Culminado', 17.00, 17.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-07-11 20:10:18'),
(392, '2026-07-11', 'D', 'YARD', 'Yard', NULL, 'COSCO THAMES', 20, 'ACTIVE', 'En Proceso', 392.00, 41.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-07-11 23:41:13'),
(393, '2026-07-11', 'N', 'YARD', 'Yard', NULL, 'COSCO THAMES', 20, 'ACTIVE', 'En Proceso', 392.00, 28.00, NULL, NULL, NULL, 'se despacharon 28 vehículos por sus propios medios.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ROJAS RUIZ KAROLAY LISBETH', '2026-07-12 10:01:56'),
(394, '2026-07-12', 'D', 'BERTH', 'Berth 04', 77, NULL, 1, 'ACTIVE', 'Culminado', 226.00, 226.00, 226.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RISCO SEMINARIO GRECIA DEL MAR', '2026-07-12 23:59:40'),
(395, '2026-07-12', 'D', 'BERTH', 'Berth 02', 74, NULL, 7, 'ACTIVE', 'En Proceso', 15000.00, 7457.03, 7457.03, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RISCO SEMINARIO GRECIA DEL MAR', '2026-07-12 23:59:51'),
(396, '2026-07-12', 'D', 'BERTH', 'Berth 04', 78, NULL, 1, 'ACTIVE', 'Inicio', 635.00, 120.00, 120.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RISCO SEMINARIO GRECIA DEL MAR', '2026-07-13 00:01:06'),
(397, '2026-07-12', 'D', 'YARD', 'Yard', NULL, 'XIN OU ZHOU', 12, 'ACTIVE', 'En Proceso', 198.00, 81.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RISCO SEMINARIO GRECIA DEL MAR', '2026-07-13 00:07:04'),
(398, '2026-07-12', 'D', 'YARD', 'Yard', NULL, 'COSCO THAMES', 20, 'ACTIVE', 'En Proceso', 392.00, 7.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RISCO SEMINARIO GRECIA DEL MAR', '2026-07-13 00:07:38'),
(399, '2026-07-12', 'N', 'YARD', 'Yard', NULL, 'XIN OU ZHOU', 20, 'ACTIVE', 'Inicio', 198.00, 16.00, NULL, NULL, NULL, '2 cigüeñas / 16 autos', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-07-13 09:01:21'),
(400, '2026-07-12', 'N', 'BERTH', 'Berth 04', 78, NULL, 1, 'ACTIVE', 'Culminado', 635.00, 515.00, 515.00, NULL, NULL, '4 CDR', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-07-13 10:39:27'),
(401, '2026-07-12', 'N', 'YARD', 'Yard', NULL, 'COSCO THAMES', 20, 'ACTIVE', 'En Proceso', 392.00, 21.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-07-13 10:41:54'),
(402, '2026-07-12', 'N', 'BERTH', 'Berth 02', 74, NULL, 7, 'ACTIVE', 'Culminado', 15000.00, 7585.71, 7585.71, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-07-13 12:11:00'),
(403, '2026-07-13', 'D', 'BERTH', 'Berth 02', 79, NULL, 29, 'ACTIVE', 'Inicio', 4550.00, 1254.00, 1254.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RISCO SEMINARIO GRECIA DEL MAR', '2026-07-13 23:34:53'),
(404, '2026-07-13', 'D', 'BERTH', 'Berth 04', 80, NULL, 1, 'ACTIVE', 'Culminado', 155.00, 155.00, 155.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RISCO SEMINARIO GRECIA DEL MAR', '2026-07-13 23:36:25'),
(405, '2026-07-13', 'D', 'BERTH', 'Berth 04', 81, NULL, 1, 'ACTIVE', 'Inicio', 1886.00, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RISCO SEMINARIO GRECIA DEL MAR', '2026-07-13 23:37:55'),
(406, '2026-07-13', 'D', 'YARD', 'Yard', NULL, 'XIN OU ZHOU', 20, 'ACTIVE', 'En Proceso', 198.00, 61.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RISCO SEMINARIO GRECIA DEL MAR', '2026-07-13 23:56:01'),
(407, '2026-07-13', 'D', 'YARD', 'Yard', NULL, 'SEDNA DESGAGNES', 7, 'ACTIVE', 'En Proceso', 34.00, 3.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RISCO SEMINARIO GRECIA DEL MAR', '2026-07-13 23:58:34'),
(408, '2026-07-13', 'N', 'BERTH', 'Berth 02', 79, NULL, 29, 'ACTIVE', 'En Proceso', 4550.00, 2736.10, 2736.10, NULL, NULL, 'H2: 33 tolvas, 1133.3 TN\nH4: 44 tolvas, 1602.8 TN', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-07-14 04:26:18'),
(409, '2026-07-13', 'N', 'BERTH', 'Berth 04', 81, NULL, 1, 'ACTIVE', 'En Proceso', 1886.00, 1353.00, 1353.00, NULL, NULL, '13 CDR', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-07-14 04:26:33'),
(410, '2026-07-13', 'N', 'YARD', 'Yard', NULL, 'XIN OU ZHOU', 20, 'ACTIVE', 'En Proceso', 198.00, 65.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-07-14 04:27:02'),
(411, '2026-07-14', 'D', 'YARD', 'Yard', NULL, 'XIN OU ZHOU', 20, 'ACTIVE', 'En Proceso', 198.00, 33.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-07-14 22:15:54'),
(412, '2026-07-14', 'D', 'BERTH', 'Berth 02', 79, NULL, 29, 'ACTIVE', 'En Proceso', 4550.00, 532.40, 532.40, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-07-14 22:16:54'),
(413, '2026-07-14', 'D', 'BERTH', 'Berth 03', 82, NULL, 1, 'ACTIVE', 'Inicio', 560.00, 408.00, 408.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-07-14 22:18:38'),
(414, '2026-07-14', 'D', 'BERTH', 'Berth 04', 81, NULL, 1, 'ACTIVE', 'En Proceso', 1886.00, 533.00, 533.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-07-14 22:31:00'),
(415, '2026-07-14', 'N', 'BERTH', 'Berth 03', 82, NULL, 1, 'ACTIVE', 'Culminado', 560.00, 159.00, 159.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RISCO SEMINARIO GRECIA DEL MAR', '2026-07-15 10:02:50'),
(416, '2026-07-14', 'N', 'BERTH', 'Berth 04', 83, NULL, 1, 'ACTIVE', 'Inicio', 435.00, 152.00, 152.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RISCO SEMINARIO GRECIA DEL MAR', '2026-07-15 10:03:31'),
(417, '2026-07-15', 'D', 'BERTH', 'Berth 04', 83, NULL, 1, 'ACTIVE', 'En Proceso', 435.00, 283.00, 283.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-07-15 18:42:45'),
(418, '2026-07-15', 'D', 'BERTH', 'Berth 01', 84, NULL, 2, 'ACTIVE', 'Inicio', 36866.83, 2696.04, 1140.34, 1555.70, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-07-16 00:12:37'),
(420, '2026-07-15', 'D', 'YARD', 'Yard', NULL, 'XIN HONG KONG', 11, 'ACTIVE', 'Inicio', 87.00, 112.00, NULL, NULL, NULL, '112 VEHICULOS PLANIFICADOS PARA EL TURNO', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-07-16 00:21:07'),
(421, '2026-07-15', 'N', 'BERTH', 'Berth 01', 84, NULL, 2, 'ACTIVE', 'En Proceso', 36866.83, 10288.14, 10288.14, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RISCO SEMINARIO GRECIA DEL MAR', '2026-07-16 09:05:06'),
(422, '2026-07-16', 'D', 'YARD', 'Yard', NULL, NULL, 9, 'ACTIVE', 'Culminado', 44.00, 44.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ROJAS RUIZ KAROLAY LISBETH', '2026-07-16 23:47:36'),
(423, '2026-07-16', 'D', 'YARD', 'Yard', NULL, 'XIN HONG KONG', 11, 'ACTIVE', 'Culminado', 87.00, 87.00, NULL, NULL, NULL, '87 vehículos de 29 contenedores', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ROJAS RUIZ KAROLAY LISBETH', '2026-07-16 23:52:13'),
(424, '2026-07-16', 'D', 'BERTH', 'Berth 01', 84, NULL, 2, 'ACTIVE', 'En Proceso', 36866.83, 10168.64, 10168.64, NULL, NULL, 'Avance por HOLD:\nHOLD 1: 1061. 32\nHOLD 2: 2501.4\nHOLD 3: 3193.12\nHOLD 5: 3412.8', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ROJAS RUIZ KAROLAY LISBETH', '2026-07-17 00:17:54'),
(425, '2026-07-17', 'D', 'BERTH', 'Berth 01', 84, NULL, 2, 'ACTIVE', 'En Proceso', 36866.83, 6518.86, 6518.86, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-07-17 12:18:25'),
(426, '2026-07-16', 'N', 'BERTH', 'Berth 01', 84, NULL, 2, 'ACTIVE', 'En Proceso', 36866.83, 5204.16, 5204.16, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-07-17 12:31:00'),
(427, '2026-07-17', 'D', 'BERTH', 'Berth 01', 85, NULL, 1, 'ACTIVE', 'Inicio', 3037.00, 880.00, 880.00, NULL, NULL, 'Trabajando con 5 gruas 3 de descarga y 2 embarque', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ROJAS RUIZ KAROLAY LISBETH', '2026-07-18 00:34:21'),
(428, '2026-07-17', 'D', 'YARD', 'Yard', NULL, NULL, 9, 'ACTIVE', 'Culminado', 40.00, 40.00, NULL, NULL, NULL, 'Pendiente escanear precintos bultos y pesos.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ROJAS RUIZ KAROLAY LISBETH', '2026-07-18 00:35:02'),
(429, '2026-07-17', 'N', 'BERTH', 'Berth 01', 84, NULL, 2, 'ACTIVE', 'En Proceso', 36866.83, 1909.02, 1909.02, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-07-18 12:00:59'),
(430, '2026-07-17', 'N', 'YARD', 'Yard', 84, NULL, 14, 'ACTIVE', 'Inicio', 1555.70, 622.50, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-07-18 12:02:55'),
(431, '2026-07-17', 'N', 'BERTH', 'Berth 01', 85, NULL, 1, 'ACTIVE', 'En Proceso', 3037.00, 1498.00, 1498.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-07-18 12:07:20'),
(432, '2026-07-18', 'D', 'BERTH', 'Berth 01', 85, NULL, 1, 'ACTIVE', 'Culminado', 3606.00, 1190.00, 1190.00, NULL, NULL, '1 CDR', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-07-18 22:40:14'),
(433, '2026-07-18', 'D', 'YARD', 'Yard', 84, NULL, 14, 'ACTIVE', 'Culminado', 1555.70, 2575.00, NULL, NULL, NULL, '70 tolvas', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-07-18 22:43:58'),
(434, '2026-07-18', 'D', 'YARD', 'Yard', NULL, NULL, 9, 'ACTIVE', 'Culminado', 25.00, 25.00, NULL, NULL, NULL, '25 plataformas', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-07-18 23:28:06'),
(435, '2026-07-19', 'D', 'BERTH', 'Berth 04', 87, NULL, 1, 'ACTIVE', 'Culminado', 312.00, 312.00, 312.00, NULL, NULL, '3 QC\nNo CDR', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ROJAS RUIZ KAROLAY LISBETH', '2026-07-19 12:10:04'),
(436, '2026-07-19', 'D', 'YARD', 'Muelle 01', 84, NULL, 14, 'ACTIVE', 'En Proceso', 1555.70, 915.44, NULL, NULL, NULL, 'Seguimos despachando del almacén B. Se atendieron 24 tolvas', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ROJAS RUIZ KAROLAY LISBETH', '2026-07-19 12:11:17'),
(437, '2026-07-19', 'D', 'YARD', 'Yard', NULL, 'XIN HONG KONG', 20, 'ACTIVE', 'Inicio', 189.00, 104.00, NULL, NULL, NULL, '13 cigüeñas', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-07-19 13:04:02'),
(438, '2026-07-19', 'D', 'BERTH', 'Berth 03', 88, NULL, 1, 'ACTIVE', 'Inicio', 546.00, 284.00, 284.00, NULL, NULL, '3 QC\n1 CDR', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-07-19 20:46:08'),
(439, '2026-07-19', 'N', 'BERTH', 'Berth 03', 88, NULL, 1, 'ACTIVE', 'Culminado', 546.00, 263.00, 263.00, NULL, NULL, 'Nave trabajo con 3 grúas.\nTermino operaciones 3 am', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ROJAS RUIZ KAROLAY LISBETH', '2026-07-20 11:51:25'),
(440, '2026-07-19', 'N', 'YARD', 'Yard', 84, NULL, 14, 'ACTIVE', 'En Proceso', 1555.70, 1037.66, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ROJAS RUIZ KAROLAY LISBETH', '2026-07-20 11:52:53'),
(441, '2026-07-20', 'D', 'BERTH', 'Berth 3.5', 89, NULL, 1, 'ACTIVE', 'Inicio', 1809.00, 714.00, 714.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-07-20 23:37:24'),
(442, '2026-07-20', 'D', 'YARD', 'Yard', 84, NULL, 14, 'ACTIVE', 'En Proceso', 1555.70, 1109.82, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JHENIFER CONDOR', '2026-07-20 23:38:17'),
(443, '2026-07-20', 'N', 'BERTH', 'Berth 3.5', 89, NULL, 1, 'ACTIVE', 'Culminado', 1809.00, 1809.00, 1809.00, NULL, NULL, '12 CDR (incluyendo 4 relevados del turno día)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-07-21 07:39:08'),
(444, '2026-07-20', 'N', 'YARD', 'Yard', 84, NULL, 14, 'ACTIVE', 'En Proceso', 1555.70, 716.86, NULL, NULL, NULL, 'Se despacha del GWA', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-07-21 07:39:36'),
(445, '2026-07-20', 'N', 'YARD', 'Yard', NULL, 'XIN HONG KONG', 20, 'ACTIVE', 'En Proceso', 189.00, 1.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-07-21 07:43:24'),
(446, '2026-07-21', 'D', 'BERTH', 'Berth 03', 90, NULL, 1, 'ACTIVE', 'Inicio', 505.00, 303.00, 303.00, NULL, NULL, 'Trabajamos con 2 gruas todo el turno.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ROJAS RUIZ KAROLAY LISBETH', '2026-07-22 00:22:57'),
(447, '2026-07-21', 'D', 'YARD', 'Yard', 84, NULL, 14, 'ACTIVE', 'En Proceso', 1555.70, 1409.80, NULL, NULL, NULL, 'Se despacho del almacen A BL 02 esta pendiente una tolva del BL01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ROJAS RUIZ KAROLAY LISBETH', '2026-07-22 00:28:12'),
(448, '2026-07-21', 'D', 'BERTH', 'Berth 04', 91, NULL, 1, 'ACTIVE', 'Inicio', 784.00, 80.00, 80.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ROJAS RUIZ KAROLAY LISBETH', '2026-07-22 00:31:41'),
(449, '2026-07-21', 'N', 'BERTH', 'Berth 03', 90, NULL, 1, 'ACTIVE', 'Culminado', 505.00, 248.00, 248.00, NULL, NULL, '6 CDR', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-07-22 01:26:48'),
(450, '2026-07-21', 'N', 'BERTH', 'Berth 04', 91, NULL, 1, 'ACTIVE', 'En Proceso', 784.00, 512.00, 512.00, NULL, NULL, '2 CDR', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-07-22 01:27:15'),
(451, '2026-07-21', 'N', 'YARD', 'Yard', 84, NULL, 14, 'ACTIVE', 'En Proceso', 1555.70, 2100.00, NULL, NULL, NULL, '55 tolvas', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-07-22 01:27:36'),
(452, '2026-07-22', 'N', 'BERTH', 'Berth 01', 92, NULL, 2, 'ACTIVE', 'Inicio', 34089.99, 7884.80, 4449.64, 3435.16, NULL, 'GWB: 3435.16 / 120 tolvas', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'CONDOR FLORES JHENIFER', '2026-07-23 00:33:47'),
(453, '2026-07-22', 'N', 'BERTH', 'Berth 03', 93, NULL, 30, 'ACTIVE', 'Inicio', 1827.00, 1447.00, 678.00, 769.00, NULL, 'SE RELEVA:\nH1: 18 PENDIENTE PEDETEO + 7 TRASEGADOS\nH3: 13 PENDIENTE DE PEDETEO\n\nSe segrega en ruma por BL según dimensiones', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'CONDOR FLORES JHENIFER', '2026-07-23 00:35:56'),
(454, '2026-07-22', 'N', 'YARD', 'Yard', 84, NULL, 14, 'ACTIVE', 'En Proceso', 1555.70, 920.58, NULL, NULL, NULL, 'GWC: 25 tolvas turno noche\n\n1005.62 turno día', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'CONDOR FLORES JHENIFER', '2026-07-23 00:40:00'),
(455, '2026-07-22', 'N', 'YARD', 'Yard', 93, NULL, 19, 'ACTIVE', 'Inicio', 6.00, 0.00, NULL, NULL, NULL, 'Generado automáticamente desde muelle (descarga interna).', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-07-23 12:00:40'),
(456, '2026-07-22', 'N', 'YARD', 'Yard', 92, NULL, 14, 'ACTIVE', 'Inicio', 10428.62, 0.00, NULL, NULL, NULL, 'Generado automáticamente desde muelle (descarga interna).', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-07-23 12:20:15'),
(457, '2026-07-23', 'N', 'BERTH', 'Berth 03', 93, NULL, 30, 'ACTIVE', 'Culminado', 1827.00, 1827.00, 1827.00, NULL, NULL, 'NOTAR PARA DESPACHO SI EL CLIENTE REQUIERE SEGREGACIÓN POR CALIBRE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'TERRY PEREYRA EDGARD DAVID', '2026-07-24 01:16:02'),
(458, '2026-07-23', 'N', 'BERTH', 'Berth 04', 94, NULL, 1, 'ACTIVE', 'Inicio', 1375.00, 669.00, 669.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'TERRY PEREYRA EDGARD DAVID', '2026-07-24 01:17:42'),
(459, '2026-07-23', 'N', 'YARD', 'Yard', NULL, 'XIN OU ZHOU', 19, 'ACTIVE', 'Culminado', 198.00, 304.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'TERRY PEREYRA EDGARD DAVID', '2026-07-24 01:19:56'),
(460, '2026-07-23', 'N', 'YARD', 'Yard', NULL, 'BEIJING 115E', 1, 'ACTIVE', 'Inicio', 42.00, 42.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'TERRY PEREYRA EDGARD DAVID', '2026-07-24 01:23:33'),
(461, '2026-07-23', 'N', 'BERTH', 'Berth 01', 92, NULL, 2, 'ACTIVE', 'En Proceso', 34089.99, 24808.30, 24808.30, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'TERRY PEREYRA EDGARD DAVID', '2026-07-24 01:24:37'),
(462, '2026-07-23', 'N', 'YARD', 'Yard', 84, NULL, 14, 'ACTIVE', 'Inicio', 1555.70, 496.94, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'TERRY PEREYRA EDGARD DAVID', '2026-07-24 01:25:43'),
(463, '2026-07-24', 'D', 'BERTH', 'Berth 01', 92, NULL, 2, 'ACTIVE', 'Culminado', 34089.99, 6993.46, NULL, 6993.46, NULL, 'Almacenando en GWA: Maíz / GWB: Soya', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'URBINA MEDINA EVELYN LOLY', '2026-07-25 00:14:38'),
(464, '2026-07-24', 'D', 'YARD', 'Yard', 92, NULL, 14, 'ACTIVE', 'En Proceso', 10428.62, 0.00, NULL, NULL, NULL, 'Generado automáticamente desde muelle (descarga interna).', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'URBINA MEDINA EVELYN LOLY', '2026-07-25 00:14:38'),
(465, '2026-07-24', 'D', 'YARD', 'Yard', 93, NULL, 19, 'ACTIVE', 'En Proceso', 6.00, 46.00, NULL, NULL, NULL, '46 plataformas / 736 big bags Bolas de acero', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'URBINA MEDINA EVELYN LOLY', '2026-07-25 00:31:05'),
(466, '2026-07-24', 'D', 'YARD', 'Yard', NULL, 'BEIJING 115E', 11, 'ACTIVE', 'Culminado', 189.00, 189.00, NULL, NULL, NULL, 'Tarjados y precintados todos los vehículos', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'URBINA MEDINA EVELYN LOLY', '2026-07-25 00:39:41'),
(470, '2026-07-25', 'D', 'YARD', 'Yard', NULL, 'BEIJING 115E', 20, 'ACTIVE', 'En Proceso', 204.00, 30.00, NULL, NULL, NULL, 'Se despacharon 04 vehículos desconsolidados', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'CONDOR FLORES JHENIFER', '2026-07-25 12:18:26'),
(471, '2026-07-25', 'D', 'BERTH', 'Berth 01', 95, NULL, 2, 'ACTIVE', 'Inicio', 34089.99, 58.46, 58.46, NULL, NULL, 'Descarga bodega 01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'CONDOR FLORES JHENIFER', '2026-07-25 12:20:08'),
(472, '2026-07-25', 'D', 'YARD', 'Yard', 93, NULL, 19, 'ACTIVE', 'Culminado', 6.00, 6.00, NULL, NULL, NULL, 'Fin de despacho de bolas de acero', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'CONDOR FLORES JHENIFER', '2026-07-25 12:20:29'),
(473, '2026-07-25', 'D', 'BERTH', 'Berth 02', 96, NULL, 7, 'ACTIVE', 'Inicio', 30250.00, 1743.00, NULL, 1743.00, NULL, 'Se trabajó con las bodegas 02, 04 y 05', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'SOTO MONTALVO YADIRA MILAGROS', '2026-07-26 00:32:16'),
(474, '2026-07-25', 'D', 'YARD', 'Yard', 96, NULL, 19, 'ACTIVE', 'Inicio', 12480.00, 1683.00, NULL, NULL, NULL, 'Generado automáticamente desde muelle (descarga interna).', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'SOTO MONTALVO YADIRA MILAGROS', '2026-07-26 00:32:16'),
(475, '2026-07-25', 'D', 'YARD', 'Yard', 84, NULL, 14, 'ACTIVE', 'En Proceso', 1555.70, 112.72, NULL, NULL, NULL, 'Se realizó despacho del GWC', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'SOTO MONTALVO YADIRA MILAGROS', '2026-07-26 00:53:55'),
(476, '2026-07-25', 'D', 'YARD', 'Yard', 92, NULL, 14, 'ACTIVE', 'En Proceso', 10428.62, 349.54, NULL, NULL, NULL, 'Se despacharon 10 tolvas del almacén GWA', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'SOTO MONTALVO YADIRA MILAGROS', '2026-07-26 00:59:08'),
(477, '2026-07-25', 'D', 'YARD', 'Yard', 92, NULL, 16, 'ACTIVE', 'En Proceso', 10428.62, 2767.74, NULL, NULL, NULL, 'Se realizó despacho de 10 tolvas en el GWB', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'SOTO MONTALVO YADIRA MILAGROS', '2026-07-26 01:05:56'),
(478, '2026-07-26', 'D', 'BERTH', 'Berth 02', 96, NULL, 7, 'ACTIVE', 'En Proceso', 30250.00, 2537.00, NULL, 2537.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'CONDOR FLORES JHENIFER', '2026-07-26 12:11:16'),
(480, '2026-07-26', 'D', 'YARD', 'Yard', NULL, 'BEIJING 115E', 20, 'ACTIVE', 'En Proceso', 204.00, 103.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'CONDOR FLORES JHENIFER', '2026-07-26 12:13:04'),
(481, '2026-07-26', 'D', 'YARD', 'Yard', 92, NULL, 16, 'ACTIVE', 'En Proceso', 10428.62, 1449.48, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'CONDOR FLORES JHENIFER', '2026-07-26 12:13:31'),
(482, '2026-07-26', 'D', 'YARD', 'Yard', 84, NULL, 14, 'ACTIVE', 'En Proceso', 1555.70, 733.44, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'CONDOR FLORES JHENIFER', '2026-07-26 12:14:04'),
(483, '2026-07-26', 'D', 'YARD', 'Yard', 96, NULL, 19, 'ACTIVE', 'En Proceso', 12480.00, 2427.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'CONDOR FLORES JHENIFER', '2026-07-26 12:19:23'),
(484, '2026-07-26', 'D', 'YARD', 'Yard', 92, NULL, 14, 'ACTIVE', 'En Proceso', 10428.62, 3129.20, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'CONDOR FLORES JHENIFER', '2026-07-26 12:44:55'),
(485, '2026-07-26', 'D', 'BERTH', 'Berth 04', 97, NULL, 1, 'ACTIVE', 'Culminado', 152.00, 152.00, 152.00, NULL, NULL, 'No hubieron CDRS', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'SOTO MONTALVO YADIRA MILAGROS', '2026-07-26 21:40:52'),
(486, '2026-07-26', 'D', 'BERTH', 'Berth 03', 98, NULL, 1, 'ACTIVE', 'En Proceso', 829.00, 351.00, 351.00, NULL, NULL, 'Tuvimos daños que fueron reportados a planning para el CDR correspondiente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'SOTO MONTALVO YADIRA MILAGROS', '2026-07-27 01:12:16'),
(487, '2026-07-26', 'N', 'BERTH', 'Berth 02', 96, NULL, 7, 'ACTIVE', 'En Proceso', 30250.00, 2858.00, NULL, 2858.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'URBINA MEDINA EVELYN LOLY', '2026-07-27 12:15:36'),
(488, '2026-07-26', 'N', 'YARD', 'Yard', 96, NULL, 19, 'ACTIVE', 'En Proceso', 12480.00, 0.00, NULL, NULL, NULL, 'Generado automáticamente desde muelle (descarga interna).', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'URBINA MEDINA EVELYN LOLY', '2026-07-27 12:15:36'),
(489, '2026-07-26', 'N', 'BERTH', 'Berth 03', 98, NULL, 1, 'ACTIVE', 'Culminado', 829.00, 478.00, 478.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'URBINA MEDINA EVELYN LOLY', '2026-07-27 12:17:18'),
(490, '2026-07-26', 'N', 'YARD', 'Yard', 84, NULL, 14, 'ACTIVE', 'En Proceso', 1555.70, 71.54, NULL, NULL, NULL, 'Despacho de GWC - Maiz', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'URBINA MEDINA EVELYN LOLY', '2026-07-27 12:18:37'),
(491, '2026-07-26', 'N', 'YARD', 'Yard', 92, NULL, 14, 'ACTIVE', 'En Proceso', 10428.62, 2289.56, NULL, NULL, NULL, '65 tolvas atendidas', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'URBINA MEDINA EVELYN LOLY', '2026-07-27 12:20:26'),
(492, '2026-07-26', 'N', 'YARD', 'Yard', NULL, 'BEIJING 115E', 20, 'ACTIVE', 'En Proceso', 204.00, 14.00, NULL, NULL, NULL, '2 cigüeñas atendidas', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'URBINA MEDINA EVELYN LOLY', '2026-07-27 12:21:53'),
(493, '2026-07-27', 'D', 'BERTH', 'Berth 03', 99, NULL, 1, 'ACTIVE', 'Culminado', 247.00, 247.00, 247.00, NULL, NULL, 'Se reportaron 06 contenedores con daño para su respectivo CDR', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'SOTO MONTALVO YADIRA MILAGROS', '2026-07-27 21:45:19'),
(494, '2026-07-27', 'D', 'BERTH', 'Berth 02', 96, NULL, 7, 'ACTIVE', 'En Proceso', 30250.00, 5516.00, 2758.00, 2758.00, NULL, 'Se  activó la grúa nave y tuvimos 04 puntos de despacho', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'SOTO MONTALVO YADIRA MILAGROS', '2026-07-28 00:59:31'),
(495, '2026-07-27', 'D', 'YARD', 'Yard', 96, NULL, 19, 'ACTIVE', 'En Proceso', 12480.00, 2474.00, NULL, NULL, NULL, 'Generado automáticamente desde muelle (descarga interna).', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'SOTO MONTALVO YADIRA MILAGROS', '2026-07-28 00:59:31'),
(496, '2026-07-27', 'D', 'YARD', 'Yard', 84, NULL, 14, 'ACTIVE', 'En Proceso', 1555.70, 1410.36, NULL, NULL, NULL, 'Almacen CyD', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'SOTO MONTALVO YADIRA MILAGROS', '2026-07-28 01:10:40'),
(497, '2026-07-27', 'D', 'YARD', 'Yard', NULL, 'BEIJING 115E', 20, 'ACTIVE', 'Culminado', 204.00, 97.00, NULL, NULL, NULL, '14 cigueñas', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'SOTO MONTALVO YADIRA MILAGROS', '2026-07-28 01:13:34'),
(498, '2026-07-27', 'D', 'YARD', 'Yard', 92, NULL, 14, 'ACTIVE', 'Culminado', 10428.62, 10428.62, NULL, NULL, NULL, 'Ya no tenemos despacho maiz en el B/L 4', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'SOTO MONTALVO YADIRA MILAGROS', '2026-07-28 01:23:23'),
(499, '2026-07-27', 'D', 'YARD', 'Yard', 92, NULL, 16, 'ACTIVE', 'En Proceso', 10428.62, 805.22, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'SOTO MONTALVO YADIRA MILAGROS', '2026-07-28 01:25:02'),
(500, '2026-07-27', 'N', 'BERTH', 'Berth 04', 100, NULL, 1, 'ACTIVE', 'Inicio', 1698.00, 450.00, 450.00, NULL, NULL, 'Se trabajó con 4 QC full descarga', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Javier Gonzales', '2026-07-28 03:41:02'),
(501, '2026-07-27', 'N', 'YARD', 'Yard', NULL, 'BEIJING 115E', 20, 'ACTIVE', 'Culminado', 204.00, 35.00, NULL, NULL, NULL, 'Ingresaron cigüeña hasta las 12 am.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Javier Gonzales', '2026-07-28 11:29:42'),
(502, '2026-07-27', 'N', 'YARD', 'Yard', 84, NULL, 14, 'ACTIVE', 'En Proceso', 1555.70, 61.00, NULL, NULL, NULL, 'Solo está quedando maíz en el GWD', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Javier Gonzales', '2026-07-28 11:30:51'),
(503, '2026-07-27', 'N', 'BERTH', 'Berth 02', 96, NULL, 7, 'ACTIVE', 'En Proceso', 30250.00, 2800.00, 2800.00, NULL, NULL, 'Se ah trabajado h1,h2,h3 y h4', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Javier Gonzales', '2026-07-28 11:31:51'),
(504, '2026-07-27', 'N', 'YARD', 'Yard', 96, NULL, 19, 'ACTIVE', 'En Proceso', 12480.00, 2841.00, NULL, NULL, NULL, 'Generado automáticamente desde muelle (descarga interna).', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Javier Gonzales', '2026-07-28 11:58:15'),
(505, '2026-07-28', 'D', 'BERTH', 'Berth 02', 96, NULL, 6, 'ACTIVE', 'En Proceso', 30250.00, 0.00, 564.00, NULL, NULL, 'nave se fue a fondear por oleaje', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'SOTO MONTALVO YADIRA MILAGROS', '2026-07-28 20:34:25'),
(507, '2026-07-28', 'D', 'BERTH', 'Berth 04', 101, NULL, 1, 'ACTIVE', 'Inicio', 1693.00, 1049.00, 1049.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'SOTO MONTALVO YADIRA MILAGROS', '2026-07-28 20:37:14'),
(508, '2026-07-28', 'D', 'BERTH', 'Berth 03', 102, NULL, 28, 'ACTIVE', 'Inicio', 553.00, 170.00, 170.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'SOTO MONTALVO YADIRA MILAGROS', '2026-07-28 20:37:52'),
(509, '2026-07-28', 'D', 'YARD', 'Yard', NULL, 'BEIJING', 12, 'ACTIVE', 'Inicio', 106.00, 95.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'SOTO MONTALVO YADIRA MILAGROS', '2026-07-28 20:41:34'),
(510, '2026-07-28', 'D', 'YARD', 'Yard', NULL, 'GLOBAL CORAL V1', 14, 'ACTIVE', 'En Proceso', 25931.00, 2147.04, NULL, NULL, NULL, '57 tolvas de despacho', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'SOTO MONTALVO YADIRA MILAGROS', '2026-07-28 20:43:23'),
(511, '2026-07-28', 'N', 'YARD', 'Yard', NULL, 'BEIJING', 12, 'ACTIVE', 'Culminado', 106.00, 11.00, NULL, NULL, NULL, '3 cigüeñas / 11 chasis', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-07-29 01:56:25'),
(512, '2026-07-28', 'N', 'YARD', 'Yard', 84, NULL, 14, 'ACTIVE', 'En Proceso', 1555.70, 1067.20, NULL, NULL, NULL, '29 tolvas', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-07-29 01:58:06'),
(513, '2026-07-28', 'N', 'BERTH', 'Berth 04', 101, NULL, 1, 'ACTIVE', 'Culminado', 1693.00, 1693.00, 1693.00, NULL, NULL, 'Solo hicimos 132 moves de embarque', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-07-29 05:51:17'),
(514, '2026-07-28', 'N', 'BERTH', 'Berth 03', 103, NULL, 1, 'ACTIVE', 'Culminado', 553.00, 553.00, 553.00, NULL, NULL, 'Hicimos 484 moves \n2 CDR', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-07-29 07:18:13'),
(515, '2026-07-29', 'D', 'BERTH', 'Berth 04', 104, NULL, 1, 'ACTIVE', 'En Proceso', 1806.00, 1009.00, 1009.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'SOTO MONTALVO YADIRA MILAGROS', '2026-07-30 00:30:48'),
(516, '2026-07-29', 'D', 'BERTH', 'Berth 03', 105, NULL, 8, 'ACTIVE', 'En Proceso', 2741.00, 858.00, 858.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'SOTO MONTALVO YADIRA MILAGROS', '2026-07-30 00:32:06'),
(517, '2026-07-29', 'D', 'YARD', 'Yard', 84, NULL, 14, 'ACTIVE', 'En Proceso', 1555.70, 835.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'SOTO MONTALVO YADIRA MILAGROS', '2026-07-30 00:34:08'),
(518, '2026-07-29', 'N', 'BERTH', 'Berth 03', 105, NULL, 8, 'ACTIVE', 'En Proceso', 2741.00, 764.00, 764.00, NULL, NULL, 'Pendiente avanzar tarjas de A6 y M1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-07-30 07:45:23'),
(519, '2026-07-29', 'N', 'BERTH', 'Berth 04', 104, NULL, 1, 'ACTIVE', 'Culminado', 1806.00, 804.00, 804.00, NULL, NULL, 'Sin CDR', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-07-30 07:46:14'),
(520, '2026-07-29', 'N', 'YARD', 'Yard', 84, NULL, 14, 'ACTIVE', 'Inicio', 1555.70, 541.08, NULL, NULL, NULL, 'Se completó GWD', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ESCOBAR ROMERO LIZ MADELEINE', '2026-07-30 07:46:31'),
(524, '2026-07-30', 'D', 'BERTH', 'Berth 02', 96, NULL, 7, 'ACTIVE', 'En Proceso', 30250.00, 1655.00, 1655.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'CONDOR FLORES JHENIFER', '2026-07-31 00:54:08'),
(525, '2026-07-30', 'D', 'YARD', 'Yard', 96, NULL, 18, 'ACTIVE', 'Inicio', 12480.00, 1126.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'CONDOR FLORES JHENIFER', '2026-07-31 00:54:26'),
(526, '2026-07-30', 'D', 'BERTH', 'Berth 03', 105, NULL, 8, 'ACTIVE', 'En Proceso', 2741.00, 762.00, 762.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'CONDOR FLORES JHENIFER', '2026-07-31 00:54:46'),
(527, '2026-07-30', 'N', 'BERTH', 'Berth 02', 96, NULL, 7, 'ACTIVE', 'En Proceso', 30250.00, 2584.00, NULL, 2584.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'SOTO MONTALVO YADIRA MILAGROS', '2026-07-31 12:26:03'),
(528, '2026-07-30', 'N', 'YARD', 'Yard', 96, NULL, 19, 'ACTIVE', 'Culminado', 12480.00, 2684.00, NULL, NULL, NULL, 'Generado automáticamente desde muelle (descarga interna).', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'SOTO MONTALVO YADIRA MILAGROS', '2026-07-31 12:26:03'),
(529, '2026-07-30', 'N', 'BERTH', 'Berth 03', 105, NULL, 8, 'ACTIVE', 'Culminado', 2741.00, 2741.00, 2741.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'SOTO MONTALVO YADIRA MILAGROS', '2026-07-31 12:36:55'),
(534, '2026-07-31', 'D', 'BERTH', 'Berth 03', 107, NULL, 1, 'ACTIVE', 'Inicio', 376.00, 241.00, 241.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'TERRY PEREYRA EDGARD DAVID', '2026-08-01 00:57:27'),
(537, '2026-07-31', 'D', 'BERTH', 'Berth 02', 96, NULL, 7, 'ACTIVE', 'En Proceso', 30250.00, 3256.00, 3256.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'TERRY PEREYRA EDGARD DAVID', '2026-08-01 00:58:33'),
(538, '2026-07-31', 'D', 'YARD', 'Yard', 96, NULL, 18, 'ACTIVE', 'En Proceso', 12480.00, 2841.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'TERRY PEREYRA EDGARD DAVID', '2026-08-01 00:59:06'),
(539, '2026-07-31', 'D', 'YARD', 'Yard', NULL, 'MV WEN JING KOU V.6', 1, 'ACTIVE', 'Inicio', 2741.00, 228.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'TERRY PEREYRA EDGARD DAVID', '2026-08-01 00:59:52'),
(540, '2026-07-31', 'N', 'BERTH', 'Berth 02', 106, NULL, 1, 'ACTIVE', 'Culminado', 376.00, 376.00, 376.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'SOTO MONTALVO YADIRA MILAGROS', '2026-08-01 11:25:17'),
(541, '2026-07-31', 'N', 'BERTH', 'Berth 02', 96, NULL, 7, 'ACTIVE', 'En Proceso', 30250.00, 2368.00, 2368.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'SOTO MONTALVO YADIRA MILAGROS', '2026-08-01 11:52:51'),
(542, '2026-07-31', 'N', 'YARD', 'Yard', 96, NULL, 18, 'ACTIVE', 'En Proceso', 12480.00, 2057.00, NULL, NULL, NULL, 'se despacho un total de 127 plataformas', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'SOTO MONTALVO YADIRA MILAGROS', '2026-08-01 12:01:16'),
(543, '2026-07-31', 'N', 'YARD', 'Yard', NULL, 'MV WEN JING KOU V.6', 20, 'ACTIVE', 'En Proceso', 2741.00, 2405.00, NULL, NULL, NULL, 'despachos del A6 y muelle 01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'SOTO MONTALVO YADIRA MILAGROS', '2026-08-01 12:19:27'),
(544, '2026-08-01', 'D', 'BERTH', 'Berth 02', 96, NULL, 7, 'ACTIVE', 'En Proceso', 30250.00, 2607.00, 2607.00, NULL, NULL, 'Se está trabajando Hold 2,3y4', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Javier Gonzales', '2026-08-01 23:51:50'),
(545, '2026-08-01', 'D', 'YARD', 'Yard', NULL, 'MV WEN JING KOU V.6', 20, 'ACTIVE', 'En Proceso', 2741.00, 301.00, NULL, NULL, NULL, 'Despachos por sus propios medios 118 vehículos \n\nDespacho de cigüeñas : 22 cigüeñas / 183 vehículos', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Javier Gonzales', '2026-08-01 23:57:30'),
(546, '2026-08-01', 'D', 'YARD', 'Yard', NULL, 'CSCL ASIA', 12, 'ACTIVE', 'Culminado', 260.00, 260.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Javier Gonzales', '2026-08-02 00:01:42'),
(547, '2026-08-02', 'D', 'BERTH', 'Berth 02', 96, NULL, 7, 'ACTIVE', 'Inicio', 30250.00, 172.00, 172.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'TERRY PEREYRA EDGARD DAVID', '2026-08-02 12:38:13'),
(548, '2026-08-02', 'D', 'BERTH', 'Berth 03', 108, NULL, 1, 'ACTIVE', 'Culminado', 187.00, 187.00, 187.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'TERRY PEREYRA EDGARD DAVID', '2026-08-02 12:39:05'),
(549, '2026-08-02', 'D', 'YARD', 'Yard', NULL, 'MV WEN JING KOU V.6', 20, 'ACTIVE', 'En Proceso', 2741.00, 339.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'TERRY PEREYRA EDGARD DAVID', '2026-08-02 12:39:52'),
(550, '2026-08-02', 'D', 'YARD', 'Yard', 96, NULL, 18, 'ACTIVE', 'En Proceso', 12480.00, 1923.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'TERRY PEREYRA EDGARD DAVID', '2026-08-02 12:40:15');
INSERT INTO `tallyman_registros` (`id`, `fecha_turno`, `turno`, `ubicacion_tipo`, `ubicacion`, `nave_id`, `nave_patio`, `actividad_id`, `estado_pos`, `status_act`, `planned`, `executed`, `directa`, `interna`, `productivity`, `details`, `cargo_type`, `bl`, `producto`, `unidades`, `tons`, `ubic_codigo`, `coord_entrante`, `coord_saliente`, `registrado_por`, `fecha_registro`) VALUES
(552, '2026-08-02', 'D', 'BERTH', 'Berth 02', 109, NULL, 7, 'ACTIVE', 'Inicio', 111.00, 10.00, 10.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Javier Gonzales', '2026-08-02 23:58:30'),
(553, '2026-08-02', 'N', 'BERTH', 'Berth 02', 109, NULL, 7, 'ACTIVE', 'En Proceso', 111.00, 37.00, 37.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'CONDOR FLORES JHENIFER', '2026-08-03 07:29:41'),
(554, '2026-08-02', 'N', 'BERTH', 'Berth 04', 110, NULL, 1, 'ACTIVE', 'Inicio', 1321.00, 1101.00, 1101.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'CONDOR FLORES JHENIFER', '2026-08-03 07:30:45'),
(555, '2026-08-02', 'N', 'YARD', 'Yard', NULL, 'MV WEN JING KOU V.6', 20, 'ACTIVE', 'Inicio', 2741.00, 107.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'CONDOR FLORES JHENIFER', '2026-08-03 07:31:06'),
(556, '2026-08-02', 'N', 'YARD', 'Yard', 96, NULL, 18, 'ACTIVE', 'En Proceso', 12480.00, 51.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'CONDOR FLORES JHENIFER', '2026-08-03 12:08:19'),
(557, '2026-08-03', 'D', 'BERTH', 'Berth 02', 111, NULL, 7, 'ACTIVE', 'Inicio', 5500.00, 1070.00, 1070.00, NULL, NULL, 'Hold 3 : cloruro de potasio \nHold 5 : big bags', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Javier Gonzales', '2026-08-03 23:38:54'),
(558, '2026-08-03', 'D', 'BERTH', 'Berth 02', 109, NULL, 7, 'ACTIVE', 'En Proceso', 111.00, 37.00, 37.00, NULL, NULL, 'Solo está pendiente descargar H4', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Javier Gonzales', '2026-08-03 23:41:02'),
(559, '2026-08-03', 'D', 'YARD', 'Yard', NULL, 'MV WEN JING KOU V.6', 20, 'ACTIVE', 'Culminado', 2741.00, 185.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Javier Gonzales', '2026-08-03 23:41:47'),
(560, '2026-08-03', 'D', 'YARD', 'Yard', NULL, 'OOCL HO MINH', 1, 'ACTIVE', 'Culminado', 86.00, 86.00, NULL, NULL, NULL, 'Pendiente tarjas', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Javier Gonzales', '2026-08-03 23:44:02'),
(561, '2026-08-03', 'N', 'YARD', 'Yard', NULL, 'MV WEN JING KOU V.6', 20, 'ACTIVE', 'En Proceso', 2741.00, 74.00, NULL, NULL, NULL, '-BL: TJNCHA001 = 69 UNIDADES \n-BL: SHACHA132= 5 UNIDADES', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'SOTO MONTALVO YADIRA MILAGROS', '2026-08-04 11:18:22'),
(562, '2026-08-03', 'N', 'BERTH', 'Berth 04', 112, NULL, 1, 'ACTIVE', 'En Proceso', 484.00, 321.00, 321.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'SOTO MONTALVO YADIRA MILAGROS', '2026-08-04 12:26:42'),
(563, '2026-08-03', 'N', 'BERTH', 'Berth 02', 111, NULL, 7, 'ACTIVE', 'Culminado', 5500.00, 5500.00, 5500.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'SOTO MONTALVO YADIRA MILAGROS', '2026-08-04 12:28:45'),
(564, '2026-08-03', 'N', 'YARD', 'Yard', NULL, 'STAR MAINE', 18, 'ACTIVE', 'En Proceso', NULL, 255.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'SOTO MONTALVO YADIRA MILAGROS', '2026-08-04 12:30:30');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipos_nave`
--

CREATE TABLE `tipos_nave` (
  `id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(60) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `tipos_nave`
--

INSERT INTO `tipos_nave` (`id`, `nombre`, `activo`) VALUES
(1, 'Containera', 1),
(2, 'Granelera', 1),
(3, 'Sales', 0),
(4, 'Ro-Ro', 1),
(5, 'Cementero', 0),
(6, 'Carga General', 1);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `avances_nave`
--
ALTER TABLE `avances_nave`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_avances_nave_fecha` (`nave_id`,`fecha_registro`);

--
-- Indices de la tabla `campos_tipo_nave`
--
ALTER TABLE `campos_tipo_nave`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_campo_tipo_clave` (`tipo_nave_id`,`clave`),
  ADD KEY `idx_campos_tipo` (`tipo_nave_id`,`activo`,`orden`);

--
-- Indices de la tabla `naves`
--
ALTER TABLE `naves`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_naves_tipo` (`tipo_nave_id`),
  ADD KEY `idx_naves_estado` (`estado`),
  ADD KEY `idx_naves_eta` (`eta`),
  ADD KEY `idx_naves_nombre` (`nombre`),
  ADD KEY `fk_naves_actividad` (`actividad_id`);

--
-- Indices de la tabla `tallyman_actividades`
--
ALTER TABLE `tallyman_actividades`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_tallyman_act_nombre` (`nombre`);

--
-- Indices de la tabla `tallyman_incidencias`
--
ALTER TABLE `tallyman_incidencias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ti_turno` (`fecha_turno`,`turno`);

--
-- Indices de la tabla `tallyman_registros`
--
ALTER TABLE `tallyman_registros`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_tr_act` (`actividad_id`),
  ADD KEY `idx_tr_turno` (`fecha_turno`,`turno`),
  ADD KEY `idx_tr_nave` (`nave_id`),
  ADD KEY `idx_tr_acum` (`nave_id`,`actividad_id`,`ubicacion`,`fecha_turno`);

--
-- Indices de la tabla `tipos_nave`
--
ALTER TABLE `tipos_nave`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_tipos_nave_nombre` (`nombre`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `avances_nave`
--
ALTER TABLE `avances_nave`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `campos_tipo_nave`
--
ALTER TABLE `campos_tipo_nave`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `naves`
--
ALTER TABLE `naves`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=113;

--
-- AUTO_INCREMENT de la tabla `tallyman_actividades`
--
ALTER TABLE `tallyman_actividades`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT de la tabla `tallyman_incidencias`
--
ALTER TABLE `tallyman_incidencias`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `tallyman_registros`
--
ALTER TABLE `tallyman_registros`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=565;

--
-- AUTO_INCREMENT de la tabla `tipos_nave`
--
ALTER TABLE `tipos_nave`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `avances_nave`
--
ALTER TABLE `avances_nave`
  ADD CONSTRAINT `fk_avances_nave` FOREIGN KEY (`nave_id`) REFERENCES `naves` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `campos_tipo_nave`
--
ALTER TABLE `campos_tipo_nave`
  ADD CONSTRAINT `fk_campos_tipo_nave` FOREIGN KEY (`tipo_nave_id`) REFERENCES `tipos_nave` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `naves`
--
ALTER TABLE `naves`
  ADD CONSTRAINT `fk_naves_actividad` FOREIGN KEY (`actividad_id`) REFERENCES `tallyman_actividades` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_naves_tipo` FOREIGN KEY (`tipo_nave_id`) REFERENCES `tipos_nave` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `tallyman_registros`
--
ALTER TABLE `tallyman_registros`
  ADD CONSTRAINT `fk_tr_act` FOREIGN KEY (`actividad_id`) REFERENCES `tallyman_actividades` (`id`),
  ADD CONSTRAINT `fk_tr_nave` FOREIGN KEY (`nave_id`) REFERENCES `naves` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
