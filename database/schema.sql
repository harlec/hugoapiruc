-- PERÚdata API — Esquema MySQL 8
-- ═══════════════════════════════════════════════════

SET NAMES utf8mb4;
SET time_zone = '-05:00'; -- Hora Perú

-- ══════════════════════════════════════
--  PLANES DE SUSCRIPCIÓN
-- ══════════════════════════════════════
CREATE TABLE IF NOT EXISTS plans (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(80)    NOT NULL,
    price_soles     DECIMAL(8,2)   NOT NULL,
    queries_per_day INT            NOT NULL,
    queries_per_mo  INT            NOT NULL,
    cache_ttl_hours INT            DEFAULT 24,
    features        JSON,
    is_active       TINYINT(1)     DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO plans (id, name, price_soles, queries_per_day, queries_per_mo, cache_ttl_hours, features) VALUES
(1, 'Trial',      0.00,    20,    600,  24, '{"ruc":true,"dni":false,"bulk":false}'),
(2, 'Básico',    49.90,   200,   3000,  24, '{"ruc":true,"dni":false,"bulk":false}'),
(3, 'Pro',       89.90,  1000,  20000,  48, '{"ruc":true,"dni":true,"bulk":false}'),
(4, 'Enterprise',199.90, 5000, 100000,  72, '{"ruc":true,"dni":true,"bulk":true}')
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- ══════════════════════════════════════
--  MULTI-TENANT: Clientes
-- ══════════════════════════════════════
CREATE TABLE IF NOT EXISTS tenants (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(150)   NOT NULL,
    email       VARCHAR(150)   UNIQUE NOT NULL,
    api_token   VARCHAR(64)    UNIQUE NOT NULL,
    plan_id     INT            NOT NULL DEFAULT 1,
    status      ENUM('active','suspended','trial') DEFAULT 'trial',
    created_at  DATETIME       DEFAULT CURRENT_TIMESTAMP,
    expires_at  DATETIME       NULL,
    INDEX idx_token (api_token),
    FOREIGN KEY (plan_id) REFERENCES plans(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Admin por defecto (token: cambiarlo en producción)
INSERT IGNORE INTO tenants (name, email, api_token, plan_id, status)
VALUES ('Admin', 'admin@perudata.pe', SHA2(CONCAT('admin', NOW(), RAND()), 256), 4, 'active');

-- ══════════════════════════════════════
--  CACHÉ DE CONSULTAS
-- ══════════════════════════════════════
CREATE TABLE IF NOT EXISTS query_cache (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    query_type  ENUM('ruc','dni') NOT NULL,
    query_value VARCHAR(20)       NOT NULL,
    response    JSON              NOT NULL,
    source_used VARCHAR(50),
    hits        INT               DEFAULT 1,
    created_at  DATETIME          DEFAULT CURRENT_TIMESTAMP,
    expires_at  DATETIME          NOT NULL,
    UNIQUE KEY uq_cache (query_type, query_value),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ══════════════════════════════════════
--  LOG DE USO POR TENANT
-- ══════════════════════════════════════
CREATE TABLE IF NOT EXISTS usage_log (
    id          BIGINT AUTO_INCREMENT PRIMARY KEY,
    tenant_id   INT             NOT NULL,
    query_type  ENUM('ruc','dni'),
    query_value VARCHAR(20),
    from_cache  TINYINT(1)      DEFAULT 0,
    response_ms INT,
    status      ENUM('ok','error','rate_limit'),
    ip_address  VARCHAR(45),
    created_at  DATETIME        DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenant_date (tenant_id, created_at),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ══════════════════════════════════════
--  MONITOR DE FUENTES
-- ══════════════════════════════════════
CREATE TABLE IF NOT EXISTS source_monitors (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    source_name          VARCHAR(80)  NOT NULL,
    source_url           VARCHAR(255) NOT NULL,
    last_check           DATETIME     NULL,
    last_status          ENUM('ok','error','slow','changed') DEFAULT 'ok',
    last_error           TEXT,
    response_ms          INT          DEFAULT 0,
    consecutive_failures INT          DEFAULT 0,
    alert_sent           TINYINT(1)   DEFAULT 0,
    created_at           DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO source_monitors (source_name, source_url) VALUES
('SUNAT_RUC',  'https://e-consultaruc.sunat.gob.pe/cl-ti-itmrconsruc/frameCriterioBusqueda.jsp'),
('RENIEC_DNI', 'https://eldni.com/pe/buscar-por-dni'),
('BACKUP_API', 'https://api.apis.net.pe/v1/ruc');

-- ══════════════════════════════════════
--  ADMINISTRADORES DEL PANEL
-- ══════════════════════════════════════
CREATE TABLE IF NOT EXISTS admins (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    username     VARCHAR(80)  UNIQUE NOT NULL,
    password     VARCHAR(255) NOT NULL,  -- bcrypt
    email        VARCHAR(150) NOT NULL,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Admin por defecto: usuario=admin, clave=Admin1234! (CAMBIAR EN PRODUCCIÓN)
INSERT IGNORE INTO admins (username, password, email)
VALUES ('admin', '$2y$12$Tz7n2wNO5YX8Lj7i.PLhzeq3.B6fVqZI8oYiJXyPhFqFSlCDQdOGu', 'admin@perudata.pe');
