-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 09-09-2026 a las 14:38:46
-- Versión del servidor: 8.0.32
-- Versión de PHP: 8.3.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `hr_control_stk`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `contacto`
--

DROP TABLE IF EXISTS `contacto`;
CREATE TABLE IF NOT EXISTS `contacto` (
  `idcontacto` int NOT NULL AUTO_INCREMENT,
  `geocerca` varchar(200) DEFAULT NULL,
  `nombre` varchar(100) DEFAULT NULL,
  `correo` varchar(100) DEFAULT NULL,
  `telefonos` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`idcontacto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalleruta`
--

DROP TABLE IF EXISTS `detalleruta`;
CREATE TABLE IF NOT EXISTS `detalleruta` (
  `iddetalleRuta` int NOT NULL AUTO_INCREMENT,
  `ruta_idruta` varchar(20) NOT NULL,
  `contacto_idcontacto` int NOT NULL,
  `geocerca` varchar(200) DEFAULT NULL,
  `coordenada` varchar(50) DEFAULT NULL,
  `kilometraje` varchar(10) DEFAULT NULL,
  `fhRegistro` datetime DEFAULT NULL,
  `fhIndicado` datetime DEFAULT NULL,
  `orden` int DEFAULT NULL,
  `observacion` varchar(500) DEFAULT NULL,
  `estado` varchar(2) DEFAULT NULL,
  PRIMARY KEY (`iddetalleRuta`),
  KEY `fk_detalleRuta_ruta_idx` (`ruta_idruta`),
  KEY `fk_detalleRuta_contacto1_idx` (`contacto_idcontacto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `docruta`
--

DROP TABLE IF EXISTS `docruta`;
CREATE TABLE IF NOT EXISTS `docruta` (
  `iddocRuta` int NOT NULL AUTO_INCREMENT,
  `detalleRuta_iddetalleRuta` int NOT NULL,
  `tipoDocumento_idtipoDocumento` int NOT NULL,
  `documento` varchar(50) DEFAULT NULL,
  `cantidad` varchar(50) DEFAULT NULL,
  `producto` varchar(50) DEFAULT NULL,
  `envase` varchar(50) DEFAULT NULL,
  `pesoNeto` varchar(50) DEFAULT NULL,
  `pesoBruto` varchar(50) DEFAULT NULL,
  `imagen` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`iddocRuta`),
  KEY `fk_docRuta_detalleRuta1_idx` (`detalleRuta_iddetalleRuta`),
  KEY `fk_docRuta_tipoDocumento1_idx` (`tipoDocumento_idtipoDocumento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ruta`
--

DROP TABLE IF EXISTS `ruta`;
CREATE TABLE IF NOT EXISTS `ruta` (
  `idruta` varchar(20) NOT NULL,
  `placa` varchar(50) DEFAULT NULL,
  `piloto` varchar(100) DEFAULT NULL,
  `copiloto` varchar(100) DEFAULT NULL,
  `precintos` varchar(50) DEFAULT NULL,
  `carreta` varchar(45) DEFAULT NULL,
  `estado` varchar(2) DEFAULT NULL,
  PRIMARY KEY (`idruta`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipodocumento`
--

DROP TABLE IF EXISTS `tipodocumento`;
CREATE TABLE IF NOT EXISTS `tipodocumento` (
  `idtipoDocumento` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) DEFAULT NULL,
  `condicionaFin` varchar(1) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  PRIMARY KEY (`idtipoDocumento`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb3;

--
-- Volcado de datos para la tabla `tipodocumento`
--

INSERT INTO `tipodocumento` (`idtipoDocumento`, `nombre`, `condicionaFin`) VALUES
(1, 'GUIA', '0'),
(2, 'RECIBO COMBUSTIBLE', '1');

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `detalleruta`
--
ALTER TABLE `detalleruta`
  ADD CONSTRAINT `fk_detalleRuta_contacto1` FOREIGN KEY (`contacto_idcontacto`) REFERENCES `contacto` (`idcontacto`),
  ADD CONSTRAINT `fk_detalleRuta_ruta` FOREIGN KEY (`ruta_idruta`) REFERENCES `ruta` (`idruta`);

--
-- Filtros para la tabla `docruta`
--
ALTER TABLE `docruta`
  ADD CONSTRAINT `fk_docRuta_detalleRuta1` FOREIGN KEY (`detalleRuta_iddetalleRuta`) REFERENCES `detalleruta` (`iddetalleRuta`),
  ADD CONSTRAINT `fk_docRuta_tipoDocumento1` FOREIGN KEY (`tipoDocumento_idtipoDocumento`) REFERENCES `tipodocumento` (`idtipoDocumento`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
