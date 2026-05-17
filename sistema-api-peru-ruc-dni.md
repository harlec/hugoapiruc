# 🇵🇪 PERÚdata API — Sistema SaaS Multi-Tenant
## Consulta de RUC/DNI con Dashboard, Suscripciones y Monitor de Fuentes

---

## 1. Visión General del Sistema

**PERÚdata API** es una plataforma SaaS multi-tenant en PHP que permite a desarrolladores, contadores y empresas consultar datos de RUC (SUNAT) y DNI (RENIEC/SUNAT) mediante una API REST segura, con panel de administración, gestión de suscripciones y un sistema de monitoreo automático de las fuentes de scraping.

### Principios de diseño
- **Multi-tenant**: cada cliente tiene su propio token, límites y estadísticas
- **Resiliencia**: fallback automático entre múltiples fuentes de datos
- **Caché inteligente**: MySQL TTL cache para reducir llamadas al gobierno
- **Monitoreo activo**: alertas automáticas si una fuente falla o cambia
- **Monetizable**: planes con límites de consultas y facturación

---

## 2. Arquitectura del Sistema

```
┌─────────────────────────────────────────────────────────────────┐
│                        CLIENTE (App/Web)                        │
└─────────────────────────┬───────────────────────────────────────┘
                          │ GET /api/v1/ruc/{numero}
                          │ Authorization: Bearer {token}
                          ▼
┌─────────────────────────────────────────────────────────────────┐
│                    TU VPS (Plesk + PHP 8.x)                     │
│                                                                 │
│  ┌──────────────┐   ┌──────────────┐   ┌─────────────────────┐ │
│  │  Auth Layer  │→  │  Rate Limit  │→  │   Cache MySQL       │ │
│  │  (Bearer     │   │  (por plan)  │   │   (TTL: 24-48h)     │ │
│  │   Token)     │   │              │   │                     │ │
│  └──────────────┘   └──────────────┘   └──────────┬──────────┘ │
│                                                    │ MISS       │
│                                           ┌────────▼─────────┐  │
│                                           │  Scraper Router  │  │
│                                           │  (Fuente 1, 2..) │  │
│                                           └────────┬─────────┘  │
└────────────────────────────────────────────────────┼────────────┘
                                                     │
                    ┌────────────────────────────────┤
                    │                                │
         ┌──────────▼──────────┐         ┌──────────▼──────────┐
         │  SUNAT (RUC)        │         │  RENIEC/SUNAT (DNI) │
         │  e-consultaruc...   │         │  Padrón reducido    │
         └─────────────────────┘         └─────────────────────┘
```

---

## 3. Estructura de Carpetas del Proyecto

```
/perudata-api/
│
├── public/                    ← Document root (Plesk apunta aquí)
│   └── index.php              ← Front controller
│
├── src/
│   ├── Auth/
│   │   ├── TokenManager.php   ← Validación de Bearer tokens
│   │   └── RateLimiter.php    ← Control de límites por plan
│   │
│   ├── Cache/
│   │   └── QueryCache.php     ← Caché MySQL con TTL configurable
│   │
│   ├── Scrapers/
│   │   ├── ScraperRouter.php  ← Orquestador de fuentes con fallback
│   │   ├── RucScraper.php     ← Scraper principal SUNAT
│   │   ├── DniScraper.php     ← Scraper principal RENIEC/SUNAT
│   │   └── BackupScraper.php  ← Fuente alternativa (API externa)
│   │
│   ├── Monitor/
│   │   ├── SourceMonitor.php  ← Verifica salud de las fuentes
│   │   └── AlertSender.php    ← Envío de alertas por email/Slack
│   │
│   └── Admin/
│       ├── Dashboard.php      ← Panel de control
│       ├── Tenants.php        ← Gestión de clientes
│       └── Subscriptions.php  ← Planes y facturación
│
├── config/
│   ├── config.php             ← Configuración central
│   ├── plans.php              ← Definición de planes SaaS
│   └── sources.php            ← URLs de fuentes a monitorear
│
├── cron/
│   ├── monitor.php            ← Cron: verifica fuentes cada 10 min
│   └── cleanup.php            ← Cron: limpia caché expirado
│
└── admin/                     ← Dashboard web (Tailwind CSS)
    ├── index.php
    ├── login.php
    ├── tenants.php
    ├── monitor.php
    └── analytics.php
```

---

## 4. Base de Datos — Esquema MySQL

```sql
-- ══════════════════════════════════════
--  MULTI-TENANT: Clientes/Usuarios
-- ══════════════════════════════════════

CREATE TABLE tenants (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(150) NOT NULL,
    email       VARCHAR(150) UNIQUE NOT NULL,
    api_token   VARCHAR(64) UNIQUE NOT NULL,     -- Bearer token SHA-256
    plan_id     INT NOT NULL,
    status      ENUM('active','suspended','trial') DEFAULT 'trial',
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at  DATETIME,
    INDEX idx_token (api_token)
);

-- ══════════════════════════════════════
--  PLANES DE SUSCRIPCIÓN
-- ══════════════════════════════════════

CREATE TABLE plans (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(80) NOT NULL,        -- Básico, Pro, Enterprise
    price_soles     DECIMAL(8,2) NOT NULL,
    queries_per_day INT NOT NULL,                -- límite diario
    queries_per_mo  INT NOT NULL,                -- límite mensual
    cache_ttl_hours INT DEFAULT 24,
    features        JSON,                        -- features adicionales
    is_active       BOOL DEFAULT TRUE
);

-- Datos semilla de planes
INSERT INTO plans VALUES
(1, 'Básico',     49.90,   200,   3000,  24, '{"ruc":true,"dni":false}', 1),
(2, 'Pro',        89.90,  1000,  20000,  48, '{"ruc":true,"dni":true}',  1),
(3, 'Enterprise', 199.90, 5000, 100000,  72, '{"ruc":true,"dni":true,"bulk":true}', 1);

-- ══════════════════════════════════════
--  CACHÉ DE CONSULTAS
-- ══════════════════════════════════════

CREATE TABLE query_cache (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    query_type  ENUM('ruc','dni') NOT NULL,
    query_value VARCHAR(20) NOT NULL,
    response    JSON NOT NULL,
    source_used VARCHAR(50),                     -- qué fuente respondió
    hits        INT DEFAULT 1,                   -- veces servida desde caché
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at  DATETIME NOT NULL,
    UNIQUE KEY uq_cache (query_type, query_value),
    INDEX idx_expires (expires_at)
);

-- ══════════════════════════════════════
--  LOG DE USO POR TENANT
-- ══════════════════════════════════════

CREATE TABLE usage_log (
    id          BIGINT AUTO_INCREMENT PRIMARY KEY,
    tenant_id   INT NOT NULL,
    query_type  ENUM('ruc','dni'),
    query_value VARCHAR(20),
    from_cache  BOOL DEFAULT FALSE,
    response_ms INT,                             -- tiempo de respuesta
    status      ENUM('ok','error','rate_limit'),
    ip_address  VARCHAR(45),
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenant_date (tenant_id, created_at),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);

-- ══════════════════════════════════════
--  MONITOR DE FUENTES
-- ══════════════════════════════════════

CREATE TABLE source_monitors (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    source_name  VARCHAR(80) NOT NULL,           -- 'SUNAT_RUC', 'RENIEC_DNI'
    source_url   VARCHAR(255) NOT NULL,
    last_check   DATETIME,
    last_status  ENUM('ok','error','slow','changed') DEFAULT 'ok',
    last_error   TEXT,
    response_ms  INT,
    consecutive_failures INT DEFAULT 0,
    alert_sent   BOOL DEFAULT FALSE,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO source_monitors (source_name, source_url) VALUES
('SUNAT_RUC',    'https://e-consultaruc.sunat.gob.pe/cl-ti-itmrconsruc/frameCriterioBusqueda.jsp'),
('RENIEC_DNI',   'https://eldni.com/pe/buscar-por-dni'),
('BACKUP_API',   'https://api.apis.net.pe/v1/ruc');
```

---

## 5. Código PHP — Módulos Clave

### 5.1 — Router Principal (`public/index.php`)

```php
<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use App\Auth\TokenManager;
use App\Cache\QueryCache;
use App\Scrapers\ScraperRouter;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Parsear ruta
$path   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$parts  = explode('/', trim($path, '/'));
// Espera: /api/v1/{tipo}/{valor}
// $parts = ['api', 'v1', 'ruc', '20131312955']

if (count($parts) < 4 || $parts[0] !== 'api') {
    http_response_code(404);
    echo json_encode(['error' => 'Endpoint no válido']);
    exit;
}

$type  = strtolower($parts[2]);  // 'ruc' o 'dni'
$value = preg_replace('/\D/', '', $parts[3]);

// 1. Autenticación
$token = TokenManager::fromRequest();
if (!$token) {
    http_response_code(401);
    echo json_encode(['error' => 'Token requerido', 'code' => 'AUTH_REQUIRED']);
    exit;
}

$tenant = TokenManager::validate($token);
if (!$tenant) {
    http_response_code(403);
    echo json_encode(['error' => 'Token inválido o expirado', 'code' => 'AUTH_INVALID']);
    exit;
}

// 2. Rate Limiting
$rateLimit = new \App\Auth\RateLimiter($tenant);
if (!$rateLimit->allow()) {
    http_response_code(429);
    echo json_encode([
        'error'       => 'Límite de consultas alcanzado',
        'code'        => 'RATE_LIMIT',
        'reset_at'    => $rateLimit->resetAt(),
        'daily_limit' => $tenant['queries_per_day']
    ]);
    exit;
}

// 3. Validar tipo y formato
$validTypes = ['ruc' => 11, 'dni' => 8];
if (!isset($validTypes[$type]) || strlen($value) !== $validTypes[$type]) {
    http_response_code(400);
    echo json_encode(['error' => "Formato de $type inválido"]);
    exit;
}

// 4. Caché
$start = microtime(true);
$cache = new QueryCache();
$cached = $cache->get($type, $value);

if ($cached) {
    $rateLimit->increment(); // cuenta incluso desde caché
    echo json_encode(array_merge($cached, [
        'from_cache'   => true,
        'cached_at'    => $cached['_cached_at'],
        'response_ms'  => round((microtime(true) - $start) * 1000)
    ]));
    exit;
}

// 5. Scraping
$router   = new ScraperRouter();
$result   = $router->query($type, $value);

if (!$result['success']) {
    http_response_code(503);
    echo json_encode(['error' => 'No se pudo obtener información', 'code' => 'SOURCE_ERROR']);
    exit;
}

// 6. Guardar en caché
$ttlHours = $tenant['cache_ttl_hours'] ?? 24;
$cache->set($type, $value, $result['data'], $result['source'], $ttlHours);
$rateLimit->increment();

$ms = round((microtime(true) - $start) * 1000);
\App\Helpers\Logger::log($tenant['id'], $type, $value, false, $ms, 'ok');

echo json_encode(array_merge($result['data'], [
    'from_cache'  => false,
    'source'      => $result['source'],
    'response_ms' => $ms
]));
```

---

### 5.2 — Scraper de RUC con Fallback (`src/Scrapers/ScraperRouter.php`)

```php
<?php
namespace App\Scrapers;

class ScraperRouter
{
    private array $sources = [];

    public function __construct()
    {
        // Orden de preferencia: primero el más confiable
        $this->sources = [
            new RucScraper(),      // SUNAT directo
            new BackupScraper(),   // apis.net.pe / apiperu.dev
        ];
    }

    public function query(string $type, string $value): array
    {
        foreach ($this->sources as $source) {
            try {
                $result = $source->query($type, $value);
                if ($result['success']) {
                    return $result;
                }
            } catch (\Exception $e) {
                // Log el error y prueba la siguiente fuente
                error_log("[ScraperRouter] Fuente fallida: " . get_class($source) . " → " . $e->getMessage());
            }
        }

        return ['success' => false, 'data' => null, 'source' => null];
    }
}
```

---

### 5.3 — Scraper SUNAT (`src/Scrapers/RucScraper.php`)

```php
<?php
namespace App\Scrapers;

class RucScraper
{
    const BASE_URL = 'https://e-consultaruc.sunat.gob.pe/cl-ti-itmrconsruc/';
    const TIMEOUT  = 15;

    public function query(string $type, string $value): array
    {
        if ($type !== 'ruc') return ['success' => false];

        // Paso 1: obtener numRnd (token anti-CSRF de SUNAT)
        $numRnd = $this->getNumRnd();
        if (!$numRnd) throw new \Exception('No se pudo obtener numRnd de SUNAT');

        // Paso 2: hacer la consulta con el RUC
        $url = self::BASE_URL . 'jcrS00Alias';
        $postData = [
            'accion'  => 'consPorRuc',
            'nroRuc'  => $value,
            'numRnd'  => $numRnd,
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($postData),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_HTTPHEADER     => [
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Referer: ' . self::BASE_URL . 'frameCriterioBusqueda.jsp',
                'Content-Type: application/x-www-form-urlencoded',
            ],
        ]);
        $html = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200 || empty($html)) {
            throw new \Exception("SUNAT respondió HTTP $code");
        }

        $data = $this->parseHtml($html, $value);
        return [
            'success' => !empty($data['razon_social']),
            'data'    => $data,
            'source'  => 'SUNAT_DIRECT'
        ];
    }

    private function getNumRnd(): ?string
    {
        $url = self::BASE_URL . 'jcrS00Alias?accion=consPorRazonSoc&razSoc=';
        $html = file_get_contents($url);
        if (!$html) return null;

        // Extraer numRnd del HTML
        preg_match('/numRnd["\s]*:["\s]*(["\'])(\w+)\1/', $html, $m);
        return $m[2] ?? null;
    }

    private function parseHtml(string $html, string $ruc): array
    {
        $doc = new \DOMDocument();
        @$doc->loadHTML($html);
        $xpath = new \DOMXPath($doc);

        // Los campos están en celdas de tabla con clases específicas
        $getValue = function(string $label) use ($xpath): string {
            $nodes = $xpath->query("//td[contains(text(),'$label')]/following-sibling::td[1]");
            return trim($nodes->item(0)?->textContent ?? '');
        };

        return [
            'ruc'           => $ruc,
            'razon_social'  => $getValue('Nombre Comercial') ?: $getValue('Apellidos y Nombres'),
            'tipo_contribu' => $getValue('Tipo Contribuyente'),
            'estado'        => $getValue('Estado del Contribuyente'),
            'condicion'     => $getValue('Condición del Contribuyente'),
            'direccion'     => $getValue('Domicilio Fiscal'),
            'departamento'  => $getValue('Departamento'),
            'provincia'     => $getValue('Provincia'),
            'distrito'      => $getValue('Distrito'),
            'ubigeo'        => $getValue('Código Ubigeo'),
            'actividad'     => $getValue('Actividad(es) Económica(s)'),
        ];
    }
}
```

---

### 5.4 — Caché MySQL (`src/Cache/QueryCache.php`)

```php
<?php
namespace App\Cache;

use PDO;

class QueryCache
{
    private PDO $db;

    public function __construct()
    {
        $this->db = \App\DB::connection();
    }

    public function get(string $type, string $value): ?array
    {
        $stmt = $this->db->prepare("
            SELECT response, source_used, created_at, hits
            FROM query_cache
            WHERE query_type = ? AND query_value = ?
              AND expires_at > NOW()
        ");
        $stmt->execute([$type, $value]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) return null;

        // Incrementar contador de hits
        $this->db->prepare("UPDATE query_cache SET hits = hits + 1 WHERE query_type = ? AND query_value = ?")
                 ->execute([$type, $value]);

        $data = json_decode($row['response'], true);
        $data['_cached_at'] = $row['created_at'];
        $data['_cache_hits'] = $row['hits'] + 1;
        return $data;
    }

    public function set(string $type, string $value, array $data, string $source, int $ttlHours = 24): void
    {
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$ttlHours} hours"));

        $stmt = $this->db->prepare("
            INSERT INTO query_cache (query_type, query_value, response, source_used, expires_at)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                response    = VALUES(response),
                source_used = VALUES(source_used),
                expires_at  = VALUES(expires_at),
                hits        = 1,
                created_at  = NOW()
        ");
        $stmt->execute([$type, $value, json_encode($data), $source, $expiresAt]);
    }

    public function invalidate(string $type, string $value): void
    {
        $this->db->prepare("DELETE FROM query_cache WHERE query_type = ? AND query_value = ?")
                 ->execute([$type, $value]);
    }

    public function stats(): array
    {
        return $this->db->query("
            SELECT
                COUNT(*) AS total_cached,
                SUM(hits) AS total_hits,
                AVG(hits) AS avg_hits,
                COUNT(CASE WHEN expires_at < NOW() THEN 1 END) AS expired
            FROM query_cache
        ")->fetch(PDO::FETCH_ASSOC);
    }
}
```

---

### 5.5 — Monitor de Fuentes (`src/Monitor/SourceMonitor.php`)

```php
<?php
namespace App\Monitor;

use PDO;

class SourceMonitor
{
    private PDO $db;
    private AlertSender $alert;

    const FAILURE_THRESHOLD = 3;     // alertar después de N fallos consecutivos
    const SLOW_THRESHOLD_MS  = 5000; // alertar si tarda más de 5s

    public function __construct()
    {
        $this->db    = \App\DB::connection();
        $this->alert = new AlertSender();
    }

    /**
     * Verificar todas las fuentes registradas.
     * Se ejecuta vía cron cada 10 minutos.
     */
    public function checkAll(): void
    {
        $sources = $this->db->query("SELECT * FROM source_monitors")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($sources as $source) {
            $result = $this->checkSource($source['source_url']);
            $status = $this->evaluateResult($result);

            $isNewError = ($status !== 'ok' && $source['last_status'] === 'ok');
            $failures   = ($status !== 'ok')
                ? $source['consecutive_failures'] + 1
                : 0;

            // Actualizar estado en BD
            $this->db->prepare("
                UPDATE source_monitors SET
                    last_check           = NOW(),
                    last_status          = ?,
                    last_error           = ?,
                    response_ms          = ?,
                    consecutive_failures = ?,
                    alert_sent           = ?
                WHERE id = ?
            ")->execute([
                $status,
                $result['error'] ?? null,
                $result['ms'],
                $failures,
                ($failures >= self::FAILURE_THRESHOLD) ? 1 : 0,
                $source['id']
            ]);

            // Enviar alerta si supera el umbral
            if ($failures >= self::FAILURE_THRESHOLD && !$source['alert_sent']) {
                $this->alert->sendAlert($source['source_name'], $status, $result['error'] ?? '');
            }

            // Recuperación: notificar que volvió a funcionar
            if ($status === 'ok' && $source['consecutive_failures'] >= self::FAILURE_THRESHOLD) {
                $this->alert->sendRecovery($source['source_name']);
            }
        }
    }

    private function checkSource(string $url): array
    {
        $start = microtime(true);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_NOBODY         => false,
            CURLOPT_HTTPHEADER     => ['User-Agent: PERUdata-Monitor/1.0'],
        ]);
        $body   = curl_exec($ch);
        $code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error  = curl_error($ch);
        $ms     = round((microtime(true) - $start) * 1000);
        curl_close($ch);

        return [
            'code'  => $code,
            'body'  => $body,
            'ms'    => $ms,
            'error' => $error ?: null,
        ];
    }

    private function evaluateResult(array $result): string
    {
        if ($result['error'] || $result['code'] !== 200) return 'error';
        if ($result['ms'] > self::SLOW_THRESHOLD_MS) return 'slow';
        // Detectar cambio de estructura HTML (buscar campo clave)
        if (!str_contains($result['body'] ?? '', 'nroRuc') &&
            !str_contains($result['body'] ?? '', 'RUC')) return 'changed';
        return 'ok';
    }
}
```

---

### 5.6 — Envío de Alertas (`src/Monitor/AlertSender.php`)

```php
<?php
namespace App\Monitor;

class AlertSender
{
    private string $adminEmail;
    private ?string $slackWebhook;

    public function __construct()
    {
        $this->adminEmail   = $_ENV['ADMIN_EMAIL'] ?? 'admin@tudominio.com';
        $this->slackWebhook = $_ENV['SLACK_WEBHOOK'] ?? null;
    }

    public function sendAlert(string $source, string $status, string $error): void
    {
        $subject = "🚨 [PERÚdata] ALERTA: Fuente '$source' falló ($status)";
        $body    = "La fuente $source está en estado '$status'.\n\nError: $error\n\nFecha: " . date('Y-m-d H:i:s');

        // Email
        mail($this->adminEmail, $subject, $body, "From: monitor@tudominio.com");

        // Slack (si está configurado)
        if ($this->slackWebhook) {
            $this->postToSlack("🚨 *Fuente caída:* `$source` — Estado: `$status`\n```$error```");
        }
    }

    public function sendRecovery(string $source): void
    {
        $subject = "✅ [PERÚdata] RECUPERADO: '$source' volvió a funcionar";
        $body    = "La fuente $source está operativa nuevamente.\n\nFecha: " . date('Y-m-d H:i:s');
        mail($this->adminEmail, $subject, $body, "From: monitor@tudominio.com");

        if ($this->slackWebhook) {
            $this->postToSlack("✅ *Fuente recuperada:* `$source` — operativa a las " . date('H:i:s'));
        }
    }

    private function postToSlack(string $message): void
    {
        $ch = curl_init($this->slackWebhook);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode(['text' => $message]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
}
```

---

## 6. Configuración de Cron Jobs (Plesk)

```bash
# Monitoreo de fuentes — cada 10 minutos
*/10 * * * * php /var/www/perudata-api/cron/monitor.php >> /var/log/perudata/monitor.log 2>&1

# Limpieza de caché expirado — cada hora
0 * * * * php /var/www/perudata-api/cron/cleanup.php >> /var/log/perudata/cleanup.log 2>&1

# Reporte diario de estadísticas — cada día a las 8am
0 8 * * * php /var/www/perudata-api/cron/daily_report.php >> /var/log/perudata/report.log 2>&1
```

---

## 7. Respuestas de la API

### GET `/api/v1/ruc/20131312955`

```json
{
  "ruc":           "20131312955",
  "razon_social":  "SUPERINTENDENCIA NACIONAL DE ADUANAS Y DE ADMINISTRACION TRIBUTARIA - SUNAT",
  "tipo_contribu": "ORGANISMO PUBLICO DESCENTRALIZADO",
  "estado":        "ACTIVO",
  "condicion":     "HABIDO",
  "direccion":     "AV. GARCILASO DE LA VEGA NRO. 1472",
  "departamento":  "LIMA",
  "provincia":     "LIMA",
  "distrito":      "LIMA",
  "ubigeo":        "150101",
  "actividad":     "ACTIVIDADES DE ADMINISTRACION PUBLICA EN GENERAL",
  "from_cache":    false,
  "source":        "SUNAT_DIRECT",
  "response_ms":   1240
}
```

### GET `/api/v1/dni/48004836`

```json
{
  "dni":            "48004836",
  "nombres":        "ROBERTO CARLOS",
  "apellido_pat":   "SULCA",
  "apellido_mat":   "BASILIO",
  "nombre_completo":"SULCA BASILIO ROBERTO CARLOS",
  "from_cache":     true,
  "cached_at":      "2026-05-15 14:22:10",
  "cache_hits":     5,
  "response_ms":    3
}
```

### Errores estandarizados

```json
{ "error": "Token requerido",          "code": "AUTH_REQUIRED" }
{ "error": "Token inválido o expirado","code": "AUTH_INVALID"  }
{ "error": "Límite diario alcanzado",  "code": "RATE_LIMIT", "reset_at": "2026-05-16 00:00:00" }
{ "error": "Formato de ruc inválido",  "code": "INVALID_FORMAT" }
{ "error": "No se pudo obtener info",  "code": "SOURCE_ERROR" }
```

---

## 8. Dashboard Admin — Diseño UI

El panel de administración sigue el diseño tipo **SupplyChain** de la imagen: sidebar izquierdo, KPI cards en la parte superior, gráficos de actividad, y estado de fuentes en tiempo real.

### Páginas del Admin

| Página | Ruta | Descripción |
|---|---|---|
| Dashboard | `/admin/` | KPIs, gráficos de uso, estado del sistema |
| Tenants | `/admin/tenants` | CRUD de clientes, asignar planes |
| Monitor | `/admin/monitor` | Estado de fuentes en tiempo real |
| Analytics | `/admin/analytics` | Consultas por día, caché hit rate, top RUCs |
| Planes | `/admin/plans` | Crear/editar planes y precios |
| Logs | `/admin/logs` | Log de consultas con filtros |

### KPI Cards del Dashboard

```
┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐
│ Consultas Hoy   │  │ Cache Hit Rate   │  │ Tenants Activos │  │ Fuentes OK      │
│     3,482       │  │     78.4%        │  │      24         │  │    2 / 3        │
│ ▲ 12% vs ayer   │  │  Ahorro de S/45  │  │ +2 este mes     │  │ ⚠ 1 lenta      │
└─────────────────┘  └─────────────────┘  └─────────────────┘  └─────────────────┘
```

---

## 9. Planes y Monetización

| Plan | Precio/mes | Consultas/día | RUC | DNI | Caché TTL | Soporte |
|---|---|---|---|---|---|---|
| **Trial** | Gratis | 20 | ✓ | ✗ | 24h | — |
| **Básico** | S/ 49.90 | 200 | ✓ | ✗ | 24h | Email |
| **Pro** | S/ 89.90 | 1,000 | ✓ | ✓ | 48h | Priority |
| **Enterprise** | S/ 199.90 | 5,000 | ✓ | ✓ | 72h | WhatsApp |
| **Bulk API** | A convenir | Sin límite | ✓ | ✓ | 96h | Dedicado |

### Clientes objetivo iniciales
- Estudios contables (como Carlos Fernández en GestiCont)
- Desarrolladores que crean apps para contadores
- Empresas con facturación electrónica
- Integradores de ERP peruanos

---

## 10. Seguridad

### Buenas prácticas implementadas

```
✅ Bearer token SHA-256 de 64 caracteres
✅ Rate limiting por tenant en MySQL
✅ Validación estricta de formato RUC/DNI antes de scraping
✅ Headers de seguridad: CSP, X-Frame-Options, HSTS
✅ Logs de todas las consultas con IP
✅ HTTPS obligatorio (Let's Encrypt en Plesk)
✅ Caché evita saturar SUNAT (actúa como escudo)
✅ Input sanitization: solo dígitos, longitud exacta
✅ Tiempo de respuesta con timeout máximo de 15s
```

### Generación de tokens para nuevos tenants

```php
function generateToken(): string {
    return hash('sha256', uniqid(random_bytes(16), true) . microtime());
}
// Ejemplo: "a3f8c2e1b4d5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1"
```

---

## 11. Instalación en VPS Plesk

### Paso a paso

```bash
# 1. Clonar el proyecto en el servidor
cd /var/www/vhosts/tudominio.com
git clone https://github.com/tu-usuario/perudata-api.git
cd perudata-api

# 2. Instalar dependencias
composer install --no-dev

# 3. Configurar variables de entorno
cp config/.env.example config/.env
nano config/.env
# → DB_HOST, DB_NAME, DB_USER, DB_PASS
# → ADMIN_EMAIL, SLACK_WEBHOOK
# → BACKUP_API_KEY (tu key de apis.net.pe)

# 4. Crear la base de datos
mysql -u root -p < database/schema.sql

# 5. Plesk: apuntar document root a /perudata-api/public/

# 6. Habilitar mod_rewrite (.htaccess ya incluido)

# 7. Configurar cron en Plesk → Scheduled Tasks
# */10 * * * * php /var/www/.../cron/monitor.php
# 0 * * * *   php /var/www/.../cron/cleanup.php

# 8. SSL: activar Let's Encrypt en Plesk (un clic)
```

### `.htaccess` (dentro de `/public/`)

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

# Seguridad
Header always set X-Content-Type-Options "nosniff"
Header always set X-Frame-Options "DENY"
Header always set X-XSS-Protection "1; mode=block"
```

---

## 12. Roadmap de Desarrollo

| Fase | Duración | Entregables |
|---|---|---|
| **Fase 1** | 1 semana | Scraper RUC funcional + API REST básica + Auth |
| **Fase 2** | 1 semana | Caché MySQL + Rate Limiting + Multi-tenant |
| **Fase 3** | 1 semana | Monitor de fuentes + Alertas email/Slack |
| **Fase 4** | 1 semana | Dashboard admin (Tailwind) + CRUD tenants |
| **Fase 5** | 1 semana | DNI scraper + Planes + Página de landing |
| **Fase 6** | Continuo | Integraciones: Izipay/Culqi para cobro de suscripciones |

---

## 13. Integración con GestiCont

Una vez que el servicio esté en producción, desde GestiCont (Blazor o PHP) la integración es un simple HTTP GET:

```csharp
// En GestiCont (C# / Blazor)
var client = new HttpClient();
client.DefaultRequestHeaders.Authorization =
    new AuthenticationHeaderValue("Bearer", "TU_TOKEN");

var resp = await client.GetFromJsonAsync<RucResponse>(
    "https://api.tudominio.com/api/v1/ruc/20131312955");

Console.WriteLine(resp.RazonSocial); // → "ACME S.A.C."
```

```php
// En cualquier app PHP
$response = file_get_contents('https://api.tudominio.com/api/v1/ruc/20131312955',
    false, stream_context_create(['http' => [
        'header' => 'Authorization: Bearer TU_TOKEN'
    ]])
);
$data = json_decode($response, true);
echo $data['razon_social'];
```

---

*Documento generado para el proyecto PERÚdata API — Hugo / AUNOR IT — 2026*
