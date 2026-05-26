-- Migración: tabla ruc_padron (padrón local de SUNAT)
-- Ejecutar una sola vez en phpMyAdmin o MySQL CLI
-- ─────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS ruc_padron (
    ruc             CHAR(11)        NOT NULL,
    razon_social    VARCHAR(250)    NOT NULL DEFAULT '',
    tipo_contribu   VARCHAR(100)    NOT NULL DEFAULT '',
    estado          VARCHAR(50)     NOT NULL DEFAULT '',
    condicion       VARCHAR(50)     NOT NULL DEFAULT '',
    departamento    VARCHAR(80)     NOT NULL DEFAULT '',
    provincia       VARCHAR(80)     NOT NULL DEFAULT '',
    distrito        VARCHAR(80)     NOT NULL DEFAULT '',
    ubigeo          VARCHAR(10)     NOT NULL DEFAULT '',
    direccion       VARCHAR(300)    NOT NULL DEFAULT '',
    actividad       VARCHAR(300)    NOT NULL DEFAULT '',
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (ruc),
    INDEX idx_razon (razon_social(50))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Padrón RUC importado desde SUNAT. Se actualiza semanalmente.';

-- Tabla de control de importaciones
CREATE TABLE IF NOT EXISTS padron_imports (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    filename        VARCHAR(200)    NOT NULL,
    total_rows      INT             DEFAULT 0,
    imported_rows   INT             DEFAULT 0,
    started_at      DATETIME        NOT NULL,
    finished_at     DATETIME        NULL,
    error           TEXT            NULL,
    INDEX idx_started (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
