-- ============================================================
--  AeroVista · Esquema de Base de Datos
--  Versión : 1.0.0
--  Descripción: Sistema de reservas de vuelos
--  Compatibilidad: MySQL 5.7+ / MariaDB 10.3+
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET NAMES utf8mb4;

-- ============================================================
-- 1. CREACIÓN DE LA BASE DE DATOS
-- ============================================================
CREATE DATABASE IF NOT EXISTS `aerovista`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `aerovista`;

-- ============================================================
-- 2. TABLA: aeropuertos
-- ============================================================
CREATE TABLE `aeropuertos` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `codigo`     VARCHAR(3)   NOT NULL COMMENT 'Código IATA (ej. GYE)',
  `nombre`     VARCHAR(150) NOT NULL,
  `ciudad`     VARCHAR(100) NOT NULL,
  `pais`       VARCHAR(100) NOT NULL,
  `pais_codigo` VARCHAR(2)  NOT NULL COMMENT 'Código ISO del país (ej. EC)',
  `activo`     TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_aeropuerto_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Catálogo de aeropuertos';

-- ============================================================
-- 3. TABLA: aerolineas
-- ============================================================
CREATE TABLE `aerolineas` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `codigo`     VARCHAR(10)  NOT NULL COMMENT 'Código IATA de la aerolínea',
  `nombre`     VARCHAR(100) NOT NULL,
  `logo_url`   VARCHAR(255) DEFAULT NULL,
  `activo`     TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_aerolinea_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Catálogo de aerolíneas';

-- ============================================================
-- 4. TABLA: usuarios
-- ============================================================
CREATE TABLE `usuarios` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `nombre`        VARCHAR(100) NOT NULL,
  `apellido`      VARCHAR(100) NOT NULL,
  `email`         VARCHAR(150) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `telefono`      VARCHAR(25)  DEFAULT NULL,
  `rol`           ENUM('cliente','admin') NOT NULL DEFAULT 'cliente',
  `activo`        TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_usuario_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Usuarios del sistema (clientes y administradores)';

-- ============================================================
-- 5. TABLA: vuelos
-- ============================================================
CREATE TABLE `vuelos` (
  `id`                   INT AUTO_INCREMENT PRIMARY KEY,
  `numero_vuelo`         VARCHAR(20)    NOT NULL,
  `aerolinea_id`         INT            NOT NULL,
  `origen_id`            INT            NOT NULL,
  `destino_id`           INT            NOT NULL,
  `fecha_salida`         DATETIME       NOT NULL,
  `fecha_llegada`        DATETIME       NOT NULL,
  `precio_base`          DECIMAL(10,2)  NOT NULL,
  `impuestos`            DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  `capacidad`            INT            NOT NULL DEFAULT 180,
  `asientos_disponibles` INT            NOT NULL DEFAULT 180,
  `avion`                VARCHAR(100)   DEFAULT NULL COMMENT 'Modelo del avión (ej. Airbus A321neo)',
  `estado`               ENUM('programado','a_tiempo','retrasado','cancelado','completado')
                         NOT NULL DEFAULT 'programado',
  `created_at`           TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_vuelo_origen`   (`origen_id`),
  KEY `idx_vuelo_destino`  (`destino_id`),
  KEY `idx_vuelo_fecha`    (`fecha_salida`),
  KEY `idx_vuelo_estado`   (`estado`),
  CONSTRAINT `fk_vuelo_aerolinea` FOREIGN KEY (`aerolinea_id`) REFERENCES `aerolineas` (`id`),
  CONSTRAINT `fk_vuelo_origen`    FOREIGN KEY (`origen_id`)    REFERENCES `aeropuertos` (`id`),
  CONSTRAINT `fk_vuelo_destino`   FOREIGN KEY (`destino_id`)   REFERENCES `aeropuertos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Vuelos disponibles en el sistema';

-- ============================================================
-- 6. TABLA: asientos
-- ============================================================
CREATE TABLE `asientos` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `vuelo_id`     INT          NOT NULL,
  `numero`       VARCHAR(5)   NOT NULL COMMENT 'Ej: 12A',
  `fila`         INT          NOT NULL,
  `columna`      VARCHAR(2)   NOT NULL COMMENT 'A, B, C, D, E, F',
  `tipo`         ENUM('ventana','pasillo','central') NOT NULL DEFAULT 'central',
  `categoria`    ENUM('economica','business','primera') NOT NULL DEFAULT 'economica',
  `es_emergencia` TINYINT(1)  NOT NULL DEFAULT 0,
  `precio_extra` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `estado`       ENUM('disponible','ocupado','reservado') NOT NULL DEFAULT 'disponible',
  UNIQUE KEY `uq_asiento_vuelo_numero` (`vuelo_id`, `numero`),
  KEY `idx_asiento_estado` (`estado`),
  CONSTRAINT `fk_asiento_vuelo` FOREIGN KEY (`vuelo_id`) REFERENCES `vuelos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Mapa de asientos por vuelo';

-- ============================================================
-- 7. TABLA: reservas
-- ============================================================
CREATE TABLE `reservas` (
  `id`               INT AUTO_INCREMENT PRIMARY KEY,
  `codigo_pnr`       VARCHAR(10)   NOT NULL COMMENT 'Código de reserva único (ej. AXV789)',
  `usuario_id`       INT           DEFAULT NULL COMMENT 'NULL si reserva como invitado',
  `vuelo_id`         INT           NOT NULL,
  `vuelo_regreso_id` INT           DEFAULT NULL COMMENT 'Vuelo de regreso para ida y vuelta',
  `tipo_viaje`       ENUM('ida','ida_vuelta') NOT NULL DEFAULT 'ida',
  `precio_base`      DECIMAL(10,2) NOT NULL,
  `impuestos`        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `precio_asientos`  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `precio_total`     DECIMAL(10,2) NOT NULL,
  `email_contacto`   VARCHAR(150)  NOT NULL,
  `telefono_contacto` VARCHAR(25)  DEFAULT NULL,
  `estado`           ENUM('pendiente','confirmada','cancelada','completada') NOT NULL DEFAULT 'pendiente',
  `created_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_reserva_pnr` (`codigo_pnr`),
  KEY `idx_reserva_usuario`  (`usuario_id`),
  KEY `idx_reserva_vuelo`    (`vuelo_id`),
  KEY `idx_reserva_estado`   (`estado`),
  CONSTRAINT `fk_reserva_usuario`        FOREIGN KEY (`usuario_id`)       REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_reserva_vuelo`          FOREIGN KEY (`vuelo_id`)         REFERENCES `vuelos`   (`id`),
  CONSTRAINT `fk_reserva_vuelo_regreso`  FOREIGN KEY (`vuelo_regreso_id`) REFERENCES `vuelos`   (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Reservas de vuelos';

-- ============================================================
-- 8. TABLA: pasajeros
-- ============================================================
CREATE TABLE `pasajeros` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `reserva_id`      INT          NOT NULL,
  `nombre`          VARCHAR(100) NOT NULL,
  `apellido`        VARCHAR(100) NOT NULL,
  `fecha_nacimiento` DATE        NOT NULL,
  `documento`       VARCHAR(50)  NOT NULL COMMENT 'Cédula o pasaporte',
  `tipo_pasajero`   ENUM('adulto','nino','infante') NOT NULL DEFAULT 'adulto',
  CONSTRAINT `fk_pasajero_reserva` FOREIGN KEY (`reserva_id`) REFERENCES `reservas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Pasajeros incluidos en una reserva';

-- ============================================================
-- 9. TABLA: reserva_asientos (relación reserva ↔ asiento)
-- ============================================================
CREATE TABLE `reserva_asientos` (
  `id`             INT AUTO_INCREMENT PRIMARY KEY,
  `reserva_id`     INT          NOT NULL,
  `pasajero_id`    INT          NOT NULL,
  `asiento_id`     INT          NOT NULL,
  `precio_asiento` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  UNIQUE KEY `uq_reserva_asiento` (`asiento_id`),
  CONSTRAINT `fk_ra_reserva`   FOREIGN KEY (`reserva_id`)  REFERENCES `reservas`  (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ra_pasajero`  FOREIGN KEY (`pasajero_id`) REFERENCES `pasajeros` (`id`),
  CONSTRAINT `fk_ra_asiento`   FOREIGN KEY (`asiento_id`)  REFERENCES `asientos`  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Asientos asignados por pasajero en una reserva';

-- ============================================================
-- 10. TABLA: pagos
-- ============================================================
CREATE TABLE `pagos` (
  `id`               INT AUTO_INCREMENT PRIMARY KEY,
  `reserva_id`       INT           NOT NULL,
  `monto`            DECIMAL(10,2) NOT NULL,
  `metodo`           ENUM('tarjeta_credito','tarjeta_debito','transferencia') NOT NULL DEFAULT 'tarjeta_credito',
  `estado`           ENUM('pendiente','procesado','fallido','reembolsado') NOT NULL DEFAULT 'pendiente',
  `referencia_pago`  VARCHAR(100)  DEFAULT NULL COMMENT 'ID de transacción externo',
  `nombre_tarjeta`   VARCHAR(100)  DEFAULT NULL,
  `ultimos_digitos`  VARCHAR(4)    DEFAULT NULL,
  `fecha_pago`       TIMESTAMP     NULL DEFAULT NULL,
  `created_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_pago_reserva` FOREIGN KEY (`reserva_id`) REFERENCES `reservas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Registro de pagos de reservas';

-- ============================================================
-- 11. DATOS INICIALES (SEED DATA)
-- ============================================================

-- Aeropuertos
INSERT INTO `aeropuertos` (`codigo`, `nombre`, `ciudad`, `pais`, `pais_codigo`) VALUES
('GYE', 'Aeropuerto José Joaquín de Olmedo', 'Guayaquil', 'Ecuador', 'EC'),
('UIO', 'Aeropuerto Mariscal Sucre', 'Quito', 'Ecuador', 'EC'),
('CUE', 'Aeropuerto Mariscal Lamar', 'Cuenca', 'Ecuador', 'EC'),
('BOG', 'Aeropuerto El Dorado', 'Bogotá', 'Colombia', 'CO'),
('LIM', 'Aeropuerto Jorge Chávez', 'Lima', 'Perú', 'PE'),
('SCL', 'Aeropuerto Arturo Merino Benítez', 'Santiago', 'Chile', 'CL'),
('GRU', 'Aeropuerto São Paulo-Guarulhos', 'São Paulo', 'Brasil', 'BR'),
('EZE', 'Aeropuerto Ministro Pistarini', 'Buenos Aires', 'Argentina', 'AR'),
('MAD', 'Aeropuerto Adolfo Suárez Madrid-Barajas', 'Madrid', 'España', 'ES'),
('BCN', 'Aeropuerto Josep Tarradellas Barcelona', 'Barcelona', 'España', 'ES'),
('LHR', 'Aeropuerto Heathrow', 'Londres', 'Reino Unido', 'GB'),
('CDG', 'Aeropuerto Charles de Gaulle', 'París', 'Francia', 'FR'),
('JFK', 'Aeropuerto John F. Kennedy', 'Nueva York', 'Estados Unidos', 'US'),
('MIA', 'Aeropuerto Internacional de Miami', 'Miami', 'Estados Unidos', 'US'),
('DXB', 'Aeropuerto Internacional de Dubái', 'Dubái', 'Emiratos Árabes', 'AE'),
('MEX', 'Aeropuerto Benito Juárez', 'Ciudad de México', 'México', 'MX'),
('PTY', 'Aeropuerto Internacional de Tocumen', 'Ciudad de Panamá', 'Panamá', 'PA');

-- Aerolíneas
INSERT INTO `aerolineas` (`codigo`, `nombre`, `logo_url`) VALUES
('AV',  'Avianca',          'https://upload.wikimedia.org/wikipedia/commons/thumb/6/6e/Avianca_logo.svg/320px-Avianca_logo.svg.png'),
('LA',  'LATAM Airlines',   'https://upload.wikimedia.org/wikipedia/commons/thumb/9/96/Latam_airlines_logo.svg/320px-Latam_airlines_logo.svg.png'),
('AA',  'American Airlines','https://upload.wikimedia.org/wikipedia/commons/thumb/1/11/American_Airlines_logo_2013.svg/320px-American_Airlines_logo_2013.svg.png'),
('IB',  'Iberia',           'https://upload.wikimedia.org/wikipedia/commons/thumb/0/06/Iberia_logo.svg/320px-Iberia_logo.svg.png'),
('CM',  'Copa Airlines',    'https://upload.wikimedia.org/wikipedia/commons/thumb/5/57/Copa_Airlines_Logo.svg/320px-Copa_Airlines_Logo.svg.png');

-- Usuario Administrador (password: Admin@2024)
INSERT INTO `usuarios` (`nombre`, `apellido`, `email`, `password_hash`, `telefono`, `rol`) VALUES
('Admin', 'AeroVista', 'admin@aerovista.com', '$2y$12$XH7/9F0EqfZcR.OkC8hDkOVEtbzW5KFxuRkl4oUOdZ95eQeT.JqGi', '+593 99 000 0000', 'admin');

-- Usuario de prueba (password: Test@1234)
INSERT INTO `usuarios` (`nombre`, `apellido`, `email`, `password_hash`, `telefono`, `rol`) VALUES
('Carlos', 'Estévez', 'carlos@example.com', '$2y$12$6HZvV2FkjfYJNjFE0vCbCeRFT5VzN3fflE5eK/lFnJ0PEQ.x3VkXy', '+593 99 123 4567', 'cliente');

-- Vuelos de ejemplo
INSERT INTO `vuelos` (`numero_vuelo`, `aerolinea_id`, `origen_id`, `destino_id`, `fecha_salida`, `fecha_llegada`, `precio_base`, `impuestos`, `capacidad`, `asientos_disponibles`, `avion`, `estado`) VALUES
('AV-402', 1, 1, 13, DATE_ADD(NOW(), INTERVAL 7  DAY), DATE_ADD(DATE_ADD(NOW(), INTERVAL 7  DAY), INTERVAL 6  HOUR),  450.00, 55.00, 180, 168, 'Airbus A321neo', 'programado'),
('LA-201', 2, 1, 13, DATE_ADD(NOW(), INTERVAL 7  DAY), DATE_ADD(DATE_ADD(NOW(), INTERVAL 7  DAY), INTERVAL 6  HOUR),  520.00, 60.00, 180, 172, 'Boeing 787-9', 'programado'),
('AV-105', 1, 1,  9, DATE_ADD(NOW(), INTERVAL 8  DAY), DATE_ADD(DATE_ADD(NOW(), INTERVAL 8  DAY), INTERVAL 10 HOUR),  620.00, 75.00, 200, 155, 'Airbus A330-200', 'programado'),
('CM-301', 5, 1, 16, DATE_ADD(NOW(), INTERVAL 5  DAY), DATE_ADD(DATE_ADD(NOW(), INTERVAL 5  DAY), INTERVAL 4  HOUR),  280.00, 35.00, 160, 143, 'Boeing 737-800', 'programado'),
('LA-088', 2, 2,  5, DATE_ADD(NOW(), INTERVAL 3  DAY), DATE_ADD(DATE_ADD(NOW(), INTERVAL 3  DAY), INTERVAL 3  HOUR),  190.00, 22.00, 180, 161, 'Airbus A319', 'programado'),
('AV-733', 1, 1,  4, DATE_ADD(NOW(), INTERVAL 2  DAY), DATE_ADD(DATE_ADD(NOW(), INTERVAL 2  DAY), INTERVAL 2  HOUR),  150.00, 18.00, 160, 134, 'Airbus A320', 'a_tiempo'),
('AA-910', 3, 13, 1, DATE_ADD(NOW(), INTERVAL 14 DAY), DATE_ADD(DATE_ADD(NOW(), INTERVAL 14 DAY), INTERVAL 6 HOUR),   480.00, 58.00, 200, 187, 'Boeing 777-200', 'programado'),
('IB-654', 4,  9, 1, DATE_ADD(NOW(), INTERVAL 10 DAY), DATE_ADD(DATE_ADD(NOW(), INTERVAL 10 DAY), INTERVAL 10 HOUR),  590.00, 70.00, 220, 198, 'Airbus A340', 'programado');

-- ============================================================
-- 12. PROCEDIMIENTO: Generar asientos para un vuelo
-- ============================================================
DELIMITER $$

CREATE PROCEDURE `generar_asientos` (IN p_vuelo_id INT, IN p_capacidad INT)
BEGIN
  DECLARE v_fila    INT DEFAULT 1;
  DECLARE v_col     VARCHAR(2);
  DECLARE v_num     VARCHAR(5);
  DECLARE v_tipo    VARCHAR(10);
  DECLARE v_extra   DECIMAL(10,2);
  DECLARE v_emerg   TINYINT(1);
  DECLARE v_filas   INT;

  SET v_filas = CEIL(p_capacidad / 6);

  fila_loop: WHILE v_fila <= v_filas DO
    SET v_emerg = IF(v_fila = 12, 1, 0);

    col_loop: BEGIN
      DECLARE cols CURSOR FOR SELECT col FROM (
        SELECT 'A' AS col UNION ALL SELECT 'B' UNION ALL SELECT 'C'
        UNION ALL SELECT 'D' UNION ALL SELECT 'E' UNION ALL SELECT 'F'
      ) t;
      DECLARE CONTINUE HANDLER FOR NOT FOUND BEGIN END;

      OPEN cols;
      read_col: LOOP
        FETCH cols INTO v_col;
        SET v_num  = CONCAT(v_fila, v_col);
        SET v_tipo = CASE v_col WHEN 'A' THEN 'ventana' WHEN 'F' THEN 'ventana'
                                WHEN 'C' THEN 'pasillo' WHEN 'D' THEN 'pasillo'
                                ELSE 'central' END;
        SET v_extra = CASE WHEN v_emerg = 1 THEN 25.00
                           WHEN v_fila <= 3 THEN 15.00
                           ELSE 0.00 END;

        INSERT IGNORE INTO `asientos`
          (`vuelo_id`, `numero`, `fila`, `columna`, `tipo`, `categoria`, `es_emergencia`, `precio_extra`, `estado`)
        VALUES
          (p_vuelo_id, v_num, v_fila, v_col, v_tipo, 'economica', v_emerg, v_extra, 'disponible');
      END LOOP read_col;
      CLOSE cols;
    END col_loop;

    SET v_fila = v_fila + 1;
  END WHILE fila_loop;
END$$

DELIMITER ;

-- Generar asientos para los vuelos de ejemplo
CALL generar_asientos(1, 180);
CALL generar_asientos(2, 180);
CALL generar_asientos(3, 200);
CALL generar_asientos(4, 160);
CALL generar_asientos(5, 180);
CALL generar_asientos(6, 160);
CALL generar_asientos(7, 200);
CALL generar_asientos(8, 220);

-- Marcar algunos asientos como ocupados (datos de demostración)
UPDATE `asientos` SET `estado` = 'ocupado'
WHERE `vuelo_id` = 1
  AND `numero` IN ('1A','1C','2B','2D','3A','3F','4C','5E','6A','6B','7D','8F');

-- ============================================================
-- 13. VISTA: resumen_vuelos (para queries frecuentes)
-- ============================================================
CREATE VIEW `v_vuelos_detalle` AS
SELECT
  v.id,
  v.numero_vuelo,
  al.nombre           AS aerolinea,
  al.logo_url         AS aerolinea_logo,
  ap_o.codigo         AS origen_codigo,
  ap_o.ciudad         AS origen_ciudad,
  ap_o.pais           AS origen_pais,
  ap_d.codigo         AS destino_codigo,
  ap_d.ciudad         AS destino_ciudad,
  ap_d.pais           AS destino_pais,
  v.fecha_salida,
  v.fecha_llegada,
  TIMESTAMPDIFF(MINUTE, v.fecha_salida, v.fecha_llegada) AS duracion_minutos,
  v.precio_base,
  v.impuestos,
  (v.precio_base + v.impuestos)  AS precio_total,
  v.asientos_disponibles,
  v.avion,
  v.estado
FROM `vuelos` v
JOIN `aerolineas`   al   ON al.id  = v.aerolinea_id
JOIN `aeropuertos`  ap_o ON ap_o.id = v.origen_id
JOIN `aeropuertos`  ap_d ON ap_d.id = v.destino_id;

-- ============================================================
-- FIN DEL SCRIPT
-- ============================================================
