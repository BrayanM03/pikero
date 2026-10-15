-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:8889
-- Tiempo de generación: 22-09-2026 a las 22:38:33
-- Versión del servidor: 5.7.39
-- Versión de PHP: 8.2.0

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `colefy`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) DEFAULT NULL,
  `telefono` varchar(50) NOT NULL,
  `correo` varchar(100) DEFAULT NULL,
  `rol` varchar(100) DEFAULT NULL,
  `estatus` varchar(100) NOT NULL,
  `fecha_ingreso` date NOT NULL,
  `usuario` varchar(100) NOT NULL,
  `contraseña` varchar(100) DEFAULT NULL,
  `id_solicitud` int(11) DEFAULT NULL,
  `es_profesor` tinyint(1) DEFAULT '0',
  `id_grupo` int(11) DEFAULT NULL,
  `id_escuela` int(11) NOT NULL,
  `foto_perfil` varchar(100) DEFAULT NULL,
  `cargo` varchar(50) NOT NULL,
  `fecha_registro` date DEFAULT NULL,
  `id_rol` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `nombre`, `apellido`, `telefono`, `correo`, `rol`, `estatus`, `fecha_ingreso`, `usuario`, `contraseña`, `id_solicitud`, `es_profesor`, `id_grupo`, `id_escuela`, `foto_perfil`, `cargo`, `fecha_registro`, `id_rol`) VALUES
(1, 'Anel Marisol', 'Martínez Sánchez', '345345', 'anel.ms@cbcm.com', '2', '1', '2025-06-23', 'anel.martinez', '$2y$10$9DaN5jq.BvnB.UicFwVKdeqo06jFZRNXDIXm36vgm.D.hCliMzwIS', NULL, 1, NULL, 1, 'perfil_1_1779466119.jpg', '', NULL, 2),
(2, 'Betsy Michelle', 'Sánchez Salazar', '743253', 'betsy.ss@cbcm.com', '2', '1', '2025-06-23', 'betsy.sanchez', '$2y$12$Ze9llxFg3ehmDPy6EI2IXejlcTMyOOIX1WwvFMbXASBjnV/tUY74u', NULL, 1, NULL, 1, '', '', NULL, 2),
(3, 'Yazmin Kataline', 'Rico Rendón', '536353', 'yazmin.rr@cbcm.com', '2', '1', '2025-06-23', 'yazmin.rico', '$2y$10$9DaN5jq.BvnB.UicFwVKdeqo06jFZRNXDIXm36vgm.D.hCliMzwIS', NULL, 1, NULL, 1, '', '', NULL, 2),
(4, 'Rubí Alejandra', 'Reyna Frausto', '646333', 'rubi.rf@cbcm.com', '2', '1', '2025-06-23', 'rubi.reyna', '$2y$10$9DaN5jq.BvnB.UicFwVKdeqo06jFZRNXDIXm36vgm.D.hCliMzwIS', NULL, 1, NULL, 1, '', '', NULL, 2),
(5, 'Daisy Gabriela', 'de la Rosa Sustaita', '464333', 'daisy.rs@cbcm.com', '2', '1', '2025-06-30', 'daisy.delarosa', '$2y$12$Ze9llxFg3ehmDPy6EI2IXejlcTMyOOIX1WwvFMbXASBjnV/tUY74u', NULL, 1, NULL, 1, '', '', NULL, 2),
(6, 'Adriana', 'Zavala García', '353661', 'adriana.zg@cbcm.com', '2', '1', '2025-06-30', 'adriana.zavala', '$2y$12$Ze9llxFg3ehmDPy6EI2IXejlcTMyOOIX1WwvFMbXASBjnV/tUY74u', NULL, 1, NULL, 1, '', '', NULL, 2),
(7, 'Brayan', 'Maldonado', '8683471939', 'sistemas@cbcm.com', '1', '1', '2025-07-30', 'brayan.mld', '$2y$12$Ze9llxFg3ehmDPy6EI2IXejlcTMyOOIX1WwvFMbXASBjnV/tUY74u', NULL, 0, 0, 1, 'perfil_7_1771441124.png', 'Sistemas', NULL, 1),
(8, 'Josue', 'Garcia Tello', '0', 'josue.g@cbcm.com', '2', '1', '2025-08-13', 'josue.garcia', '$2y$12$Ze9llxFg3ehmDPy6EI2IXejlcTMyOOIX1WwvFMbXASBjnV/tUY74u', NULL, 1, NULL, 1, '', '', NULL, 2),
(9, 'Gilberto', 'Mireles', '0', 'josue.g@cbcm.com', '2', '1', '2025-08-13', 'gilberto.mireles', '$2y$10$9DaN5jq.BvnB.UicFwVKdeqo06jFZRNXDIXm36vgm.D.hCliMzwIS', NULL, 1, NULL, 1, '', '', NULL, 2),
(10, 'Gabriela', 'Ruiz Rodriguez', '8683677347', 'contabilidad@cbcm.com', '4', '1', '2025-10-24', 'gaby.ruiz', '$2y$10$9DaN5jq.BvnB.UicFwVKdeqo06jFZRNXDIXm36vgm.D.hCliMzwIS', 0, 0, 0, 1, '', '', NULL, 4),
(17, 'Brayan', 'Test', '8683471939', 'simon.vgs', '3', '1', '2026-02-27', 'simon.vgs', '$2y$10$rZZeINKLpktoONwRMYjoZ.wd.LLM0uaNoVhlNh/o15GoYVoOuGi02', NULL, 0, NULL, 16, 'perfil_17_1777672698.png', 'Pastor ', '2026-02-27', 3),
(18, 'Jose', 'Mata', '8683677347', NULL, NULL, '1', '2026-05-29', 'jose.mata', '$2y$12$Ze9llxFg3ehmDPy6EI2IXejlcTMyOOIX1WwvFMbXASBjnV/tUY74u', NULL, 1, NULL, 16, 'perfil_18_1780773259.jpg', 'Docente', '2026-05-29', 2),
(21, 'Rafael', 'Flores Gutierrez', '4543534534', NULL, NULL, '1', '2026-06-05', 'rafael.flores', '$2y$12$Ze9llxFg3ehmDPy6EI2IXejlcTMyOOIX1WwvFMbXASBjnV/tUY74u', NULL, 0, NULL, 1, NULL, 'Gerente', '2026-06-05', 3),
(22, 'Marcos ', 'Rodriguez Castro', '8683677347', NULL, NULL, '1', '2026-06-24', 'marcos.rdz', '$2y$10$FiZn6d1cSn3aC3M15OqL6erJwCwr8rJUxLy7eJnlHUjQuu3LeU0uK', NULL, 1, NULL, 16, NULL, 'Docente', '2026-06-24', 2),
(23, 'Pedro', 'Mata', '8683677347', NULL, NULL, '1', '2026-06-24', 'pedro.mata', '$2y$10$v73VJ/za1PfVPtoAqJhUBuWTazEhhMWGQozegftGGOlWDuz2B80nu', NULL, 1, NULL, 16, NULL, 'Docente', '2026-06-24', 2),
(24, 'Hector', 'Raliuga', '8683677347', NULL, NULL, '1', '2026-09-18', 'hector.raliuga', '$2y$10$9ZwBrbEq8f1FxzO2ag9eu.EHuo02WXQiYyfVs0.eErDqx/kcZkJAq', NULL, 0, NULL, 1, NULL, 'Prefecto', '2026-09-18', 5);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_usuarios_rol_id` (`id_rol`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
