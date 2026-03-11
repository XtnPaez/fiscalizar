-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:3306
-- Tiempo de generación: 11-03-2026 a las 11:44:20
-- Versión del servidor: 10.6.20-MariaDB-cll-lve
-- Versión de PHP: 8.1.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `fiscaliz_padron`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `carreras`
--

CREATE TABLE `carreras` (
  `id` int(11) NOT NULL,
  `descripcion` varchar(50) NOT NULL COMMENT 'Nombre completo de la carrera',
  `sigla` varchar(5) NOT NULL COMMENT 'Sigla. Ej: CP, CS, RT, TS, CC'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Carreras de la Facultad de Ciencias Sociales UBA.';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `elecciones`
--

CREATE TABLE `elecciones` (
  `id` int(11) NOT NULL,
  `nombre` varchar(80) NOT NULL COMMENT 'Ej: Eleccion CD 2024',
  `tipo` enum('cd','cp') NOT NULL COMMENT 'Tipo de proceso electoral',
  `anio` year(4) NOT NULL DEFAULT 2024,
  `activa` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = eleccion en curso'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Catalogo de procesos electorales. Una sola activa por tipo en simultaneo.';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `mapeo_referentes`
--

CREATE TABLE `mapeo_referentes` (
  `id_viejo` int(11) NOT NULL,
  `id_nuevo` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `padron_cd`
--

CREATE TABLE `padron_cd` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `dni` int(10) UNSIGNED NOT NULL,
  `apellido` varchar(120) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `sigla` varchar(12) DEFAULT NULL COMMENT 'Sigla de la carrera segun el padron oficial'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Padron oficial de Consejo Directivo. Estructura exacta de la facultad.';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `padron_cp`
--

CREATE TABLE `padron_cp` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `dni` int(10) UNSIGNED NOT NULL,
  `apellido` varchar(120) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `auxiliar` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = docente auxiliar, 0 = graduado'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Padron oficial de Ciencia Politica. Estructura exacta de la facultad mas campo auxiliar.';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `participacion_electoral`
--

CREATE TABLE `participacion_electoral` (
  `id` int(11) NOT NULL,
  `dni` int(10) UNSIGNED NOT NULL,
  `id_eleccion` int(11) NOT NULL,
  `fecha_registro` date DEFAULT NULL COMMENT 'Fecha en que se registro el voto'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Historial de participacion electoral por DNI y eleccion.';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `partidos`
--

CREATE TABLE `partidos` (
  `id` int(11) NOT NULL,
  `nombre` varchar(80) NOT NULL,
  `aplica_cd` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 si aplica al padron CD',
  `aplica_cp` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 si aplica al padron CP',
  `activo` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 activo, 0 baja logica'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Catalogo de espacios politicos.';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `personas`
--

CREATE TABLE `personas` (
  `dni` int(10) UNSIGNED NOT NULL,
  `apellido` varchar(120) NOT NULL,
  `nombre` varchar(120) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Nucleo del esquema. Un registro por DNI. Nunca se elimina.';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `persona_partido`
--

CREATE TABLE `persona_partido` (
  `dni` int(10) UNSIGNED NOT NULL,
  `id_partido` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Vinculo dni -> partido politico. Un partido por persona.';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `persona_trabajo`
--

CREATE TABLE `persona_trabajo` (
  `dni` int(10) UNSIGNED NOT NULL,
  `id_trabajo` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Vinculo dni -> lugar de trabajo. Un trabajo por persona.';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `referentes`
--

CREATE TABLE `referentes` (
  `id` int(11) NOT NULL,
  `apellido` varchar(80) NOT NULL,
  `nombre` varchar(80) NOT NULL,
  `aplica_cd` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 si aplica al padron CD',
  `aplica_cp` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 si aplica al padron CP',
  `activo` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 activo, 0 baja logica'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Catalogo de referentes politicos. Apellido y nombre separados.';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `referentes_graduado`
--

CREATE TABLE `referentes_graduado` (
  `dni` int(10) UNSIGNED NOT NULL,
  `referente_1` int(11) DEFAULT NULL COMMENT 'Primer referente. NULL si no tiene.',
  `referente_2` int(11) DEFAULT NULL COMMENT 'Segundo referente. NULL si no tiene.',
  `referente_3` int(11) DEFAULT NULL COMMENT 'Tercer referente. NULL si no tiene.'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Hasta 3 referentes por DNI. Limite firme e historico.';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sede_laboral`
--

CREATE TABLE `sede_laboral` (
  `id` int(11) NOT NULL,
  `dni` int(10) UNSIGNED NOT NULL COMMENT 'Clave de cruce. Puede no matchear con personas hoy.',
  `apellido` varchar(120) NOT NULL COMMENT 'Para verificacion manual si el DNI no matchea.',
  `nombre` varchar(120) NOT NULL COMMENT 'Para verificacion manual si el DNI no matchea.',
  `sede` varchar(120) DEFAULT NULL,
  `fecha_carga` date NOT NULL COMMENT 'Fecha de incorporacion del listado.'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Sede laboral por DNI. Se sube completo, no solo los que matchean.';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `trabajos`
--

CREATE TABLE `trabajos` (
  `id` int(11) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `aplica_cd` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 si aplica al padron CD',
  `aplica_cp` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 si aplica al padron CP',
  `activo` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 activo, 0 baja logica'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Catalogo de lugares de trabajo. Incluye categorias administrativas.';

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_padron_cd`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_padron_cd` (
`dni` int(10) unsigned
,`apellido` varchar(120)
,`nombre` varchar(120)
,`carrera` varchar(12)
,`referente_1` varchar(161)
,`referente_2` varchar(161)
,`referente_3` varchar(161)
,`partido` varchar(80)
,`trabajo` varchar(120)
,`sede_laboral` varchar(120)
,`voto_cd_2021` varchar(2)
,`voto_cd_2024` varchar(2)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_padron_cp`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_padron_cp` (
`dni` int(10) unsigned
,`apellido` varchar(120)
,`nombre` varchar(120)
,`auxiliar` tinyint(1)
,`referente_1` varchar(161)
,`referente_2` varchar(161)
,`referente_3` varchar(161)
,`partido` varchar(80)
,`trabajo` varchar(120)
,`sede_laboral` varchar(120)
,`voto_cp_2017` varchar(2)
,`voto_cp_2019` varchar(2)
,`voto_cp_2021` varchar(2)
,`voto_cp_2024` varchar(2)
);

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_padron_cd`
--
DROP TABLE IF EXISTS `vista_padron_cd`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_padron_cd`  AS SELECT `pcd`.`dni` AS `dni`, `pcd`.`apellido` AS `apellido`, `pcd`.`nombre` AS `nombre`, `pcd`.`sigla` AS `carrera`, concat(`r1`.`apellido`,' ',`r1`.`nombre`) AS `referente_1`, concat(`r2`.`apellido`,' ',`r2`.`nombre`) AS `referente_2`, concat(`r3`.`apellido`,' ',`r3`.`nombre`) AS `referente_3`, `pt`.`nombre` AS `partido`, `tr`.`nombre` AS `trabajo`, `sl`.`sede` AS `sede_laboral`, CASE WHEN `pe21cd`.`dni` is not null THEN 'SI' ELSE 'NO' END AS `voto_cd_2021`, CASE WHEN `pe24cd`.`dni` is not null THEN 'SI' ELSE 'NO' END AS `voto_cd_2024` FROM (((((((((((`padron_cd` `pcd` left join `referentes_graduado` `rg` on(`pcd`.`dni` = `rg`.`dni`)) left join `referentes` `r1` on(`rg`.`referente_1` = `r1`.`id`)) left join `referentes` `r2` on(`rg`.`referente_2` = `r2`.`id`)) left join `referentes` `r3` on(`rg`.`referente_3` = `r3`.`id`)) left join `persona_partido` `pp` on(`pcd`.`dni` = `pp`.`dni`)) left join `partidos` `pt` on(`pp`.`id_partido` = `pt`.`id`)) left join `persona_trabajo` `ptt` on(`pcd`.`dni` = `ptt`.`dni`)) left join `trabajos` `tr` on(`ptt`.`id_trabajo` = `tr`.`id`)) left join `sede_laboral` `sl` on(`pcd`.`dni` = `sl`.`dni`)) left join `participacion_electoral` `pe21cd` on(`pcd`.`dni` = `pe21cd`.`dni` and `pe21cd`.`id_eleccion` = 3)) left join `participacion_electoral` `pe24cd` on(`pcd`.`dni` = `pe24cd`.`dni` and `pe24cd`.`id_eleccion` = 5)) ORDER BY `pcd`.`apellido` ASC, `pcd`.`nombre` ASC ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_padron_cp`
--
DROP TABLE IF EXISTS `vista_padron_cp`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_padron_cp`  AS SELECT `pcp`.`dni` AS `dni`, `pcp`.`apellido` AS `apellido`, `pcp`.`nombre` AS `nombre`, `pcp`.`auxiliar` AS `auxiliar`, concat(`r1`.`apellido`,' ',`r1`.`nombre`) AS `referente_1`, concat(`r2`.`apellido`,' ',`r2`.`nombre`) AS `referente_2`, concat(`r3`.`apellido`,' ',`r3`.`nombre`) AS `referente_3`, `pt`.`nombre` AS `partido`, `tr`.`nombre` AS `trabajo`, `sl`.`sede` AS `sede_laboral`, CASE WHEN `pe17`.`dni` is not null THEN 'SI' ELSE 'NO' END AS `voto_cp_2017`, CASE WHEN `pe19`.`dni` is not null THEN 'SI' ELSE 'NO' END AS `voto_cp_2019`, CASE WHEN `pe21`.`dni` is not null THEN 'SI' ELSE 'NO' END AS `voto_cp_2021`, CASE WHEN `pe24`.`dni` is not null THEN 'SI' ELSE 'NO' END AS `voto_cp_2024` FROM (((((((((((((`padron_cp` `pcp` left join `referentes_graduado` `rg` on(`pcp`.`dni` = `rg`.`dni`)) left join `referentes` `r1` on(`rg`.`referente_1` = `r1`.`id`)) left join `referentes` `r2` on(`rg`.`referente_2` = `r2`.`id`)) left join `referentes` `r3` on(`rg`.`referente_3` = `r3`.`id`)) left join `persona_partido` `pp` on(`pcp`.`dni` = `pp`.`dni`)) left join `partidos` `pt` on(`pp`.`id_partido` = `pt`.`id`)) left join `persona_trabajo` `ptt` on(`pcp`.`dni` = `ptt`.`dni`)) left join `trabajos` `tr` on(`ptt`.`id_trabajo` = `tr`.`id`)) left join `sede_laboral` `sl` on(`pcp`.`dni` = `sl`.`dni`)) left join `participacion_electoral` `pe17` on(`pcp`.`dni` = `pe17`.`dni` and `pe17`.`id_eleccion` = 1)) left join `participacion_electoral` `pe19` on(`pcp`.`dni` = `pe19`.`dni` and `pe19`.`id_eleccion` = 2)) left join `participacion_electoral` `pe21` on(`pcp`.`dni` = `pe21`.`dni` and `pe21`.`id_eleccion` = 4)) left join `participacion_electoral` `pe24` on(`pcp`.`dni` = `pe24`.`dni` and `pe24`.`id_eleccion` = 6)) ORDER BY `pcp`.`apellido` ASC, `pcp`.`nombre` ASC ;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `carreras`
--
ALTER TABLE `carreras`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_carreras_sigla` (`sigla`);

--
-- Indices de la tabla `elecciones`
--
ALTER TABLE `elecciones`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `mapeo_referentes`
--
ALTER TABLE `mapeo_referentes`
  ADD PRIMARY KEY (`id_viejo`);

--
-- Indices de la tabla `padron_cd`
--
ALTER TABLE `padron_cd`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_padron_cd_dni` (`dni`);

--
-- Indices de la tabla `padron_cp`
--
ALTER TABLE `padron_cp`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_padron_cp_dni` (`dni`);

--
-- Indices de la tabla `participacion_electoral`
--
ALTER TABLE `participacion_electoral`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_participacion` (`dni`,`id_eleccion`),
  ADD KEY `fk_pe_elecciones` (`id_eleccion`);

--
-- Indices de la tabla `partidos`
--
ALTER TABLE `partidos`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `personas`
--
ALTER TABLE `personas`
  ADD PRIMARY KEY (`dni`);

--
-- Indices de la tabla `persona_partido`
--
ALTER TABLE `persona_partido`
  ADD PRIMARY KEY (`dni`),
  ADD KEY `fk_pp_partidos` (`id_partido`);

--
-- Indices de la tabla `persona_trabajo`
--
ALTER TABLE `persona_trabajo`
  ADD PRIMARY KEY (`dni`),
  ADD KEY `fk_pt_trabajos` (`id_trabajo`);

--
-- Indices de la tabla `referentes`
--
ALTER TABLE `referentes`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `referentes_graduado`
--
ALTER TABLE `referentes_graduado`
  ADD PRIMARY KEY (`dni`),
  ADD KEY `fk_rg_referente_1` (`referente_1`),
  ADD KEY `fk_rg_referente_2` (`referente_2`),
  ADD KEY `fk_rg_referente_3` (`referente_3`);

--
-- Indices de la tabla `sede_laboral`
--
ALTER TABLE `sede_laboral`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sede_laboral_dni` (`dni`);

--
-- Indices de la tabla `trabajos`
--
ALTER TABLE `trabajos`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `carreras`
--
ALTER TABLE `carreras`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `elecciones`
--
ALTER TABLE `elecciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `padron_cd`
--
ALTER TABLE `padron_cd`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `padron_cp`
--
ALTER TABLE `padron_cp`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `participacion_electoral`
--
ALTER TABLE `participacion_electoral`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `partidos`
--
ALTER TABLE `partidos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `referentes`
--
ALTER TABLE `referentes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `sede_laboral`
--
ALTER TABLE `sede_laboral`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `trabajos`
--
ALTER TABLE `trabajos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `padron_cd`
--
ALTER TABLE `padron_cd`
  ADD CONSTRAINT `fk_padron_cd_personas` FOREIGN KEY (`dni`) REFERENCES `personas` (`dni`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `padron_cp`
--
ALTER TABLE `padron_cp`
  ADD CONSTRAINT `fk_padron_cp_personas` FOREIGN KEY (`dni`) REFERENCES `personas` (`dni`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `participacion_electoral`
--
ALTER TABLE `participacion_electoral`
  ADD CONSTRAINT `fk_pe_elecciones` FOREIGN KEY (`id_eleccion`) REFERENCES `elecciones` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pe_personas` FOREIGN KEY (`dni`) REFERENCES `personas` (`dni`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `persona_partido`
--
ALTER TABLE `persona_partido`
  ADD CONSTRAINT `fk_pp_partidos` FOREIGN KEY (`id_partido`) REFERENCES `partidos` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pp_personas` FOREIGN KEY (`dni`) REFERENCES `personas` (`dni`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `persona_trabajo`
--
ALTER TABLE `persona_trabajo`
  ADD CONSTRAINT `fk_pt_personas` FOREIGN KEY (`dni`) REFERENCES `personas` (`dni`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pt_trabajos` FOREIGN KEY (`id_trabajo`) REFERENCES `trabajos` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `referentes_graduado`
--
ALTER TABLE `referentes_graduado`
  ADD CONSTRAINT `fk_rg_personas` FOREIGN KEY (`dni`) REFERENCES `personas` (`dni`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_rg_referente_1` FOREIGN KEY (`referente_1`) REFERENCES `referentes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_rg_referente_2` FOREIGN KEY (`referente_2`) REFERENCES `referentes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_rg_referente_3` FOREIGN KEY (`referente_3`) REFERENCES `referentes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
