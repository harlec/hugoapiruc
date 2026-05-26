#!/usr/bin/env php
<?php
/**
 * cron/import_padron.php
 * Descarga e importa el Padrón RUC de SUNAT a la base de datos local.
 *
 * El padrón reducido es un archivo público de SUNAT (~150MB comprimido).
 * URL oficial: https://www.sunat.gob.pe/descargaPRR/padron_reducido_ruc.zip
 *
 * Formato del archivo (pipe-delimited, sin cabecera):
 * RUC|NOMBRE|TIPO_CONTRIB|ESTADO|CONDICION|UBIGEO|VIA_TIPO|VIA_NOM|
 * ZONA_COD|ZONA_TIPO|NRO|INT|LOTE|DPTO|MZN|KM|DEPARTAMENTO|PROVINCIA|DISTRITO
 *
 * Ejecución manual:   php cron/import_padron.php
 * Forzar re-import:   php cron/import_padron.php --force
 * Solo un archivo:    php cron/import_padron.php --file=/ruta/padron.txt
 *
 * Plesk Scheduled Task (semanal, domingos 3am):
 *   /opt/plesk/php/8.3/bin/php /var/www/vhosts/.../cron/import_padron.php
 */

declare(strict_types=1);

ini_set('memory_limit', '512M');
set_time_limit(0);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

use App\DB;

// ── Configuración ─────────────────────────────────────────────────────────────

const PADRON_URL    = 'https://www.sunat.gob.pe/descargaPRR/padron_reducido_ruc.zip';
const PADRON_BACKUP = 'https://files.apis.net.pe/padron/padron_reducido_ruc.zip'; // espejo alternativo
const BATCH_SIZE    = 1000;   // filas por INSERT batch (ajustar según RAM)
const TEMP_DIR      = '/tmp/perudata_padron';

// ── Argumentos ────────────────────────────────────────────────────────────────
$force     = in_array('--force', $argv ?? []);
$localFile = null;
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--file=')) {
        $localFile = substr($arg, 7);
    }
}

// ── Helper output ─────────────────────────────────────────────────────────────
function log_msg(string $msg): void {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
}

function abort(string $msg, \PDO $pdo = null, int $importId = 0): never {
    log_msg("ERROR: $msg");
    if ($pdo && $importId) {
        $pdo->prepare('UPDATE padron_imports SET error=?, finished_at=NOW() WHERE id=?')
            ->execute([$msg, $importId]);
    }
    exit(1);
}

// ── Verificar si ya se importó hoy ───────────────────────────────────────────
$pdo = DB::getInstance();

if (!$force && !$localFile) {
    $last = $pdo->query(
        "SELECT MAX(started_at) FROM padron_imports WHERE error IS NULL"
    )->fetchColumn();

    if ($last && strtotime($last) > strtotime('-6 days')) {
        log_msg("Padrón ya importado el $last. Usa --force para re-importar.");
        exit(0);
    }
}

// ── Registrar inicio ──────────────────────────────────────────────────────────
$pdo->prepare(
    'INSERT INTO padron_imports (filename, started_at) VALUES (?, NOW())'
)->execute([$localFile ?? PADRON_URL]);
$importId = (int)$pdo->lastInsertId();
log_msg("Import #$importId iniciado.");

// ── Preparar directorio temporal ──────────────────────────────────────────────
if (!is_dir(TEMP_DIR)) {
    mkdir(TEMP_DIR, 0700, true);
}

// ── Descargar o usar archivo local ────────────────────────────────────────────
if ($localFile) {
    $txtFile = $localFile;
    log_msg("Usando archivo local: $txtFile");
} else {
    $zipPath = TEMP_DIR . '/padron_reducido_ruc.zip';
    $txtFile = TEMP_DIR . '/padron_reducido_ruc.txt';

    if (!file_exists($zipPath) || $force) {
        log_msg("Descargando padrón desde SUNAT...");
        $downloaded = false;

        foreach ([PADRON_URL, PADRON_BACKUP] as $url) {
            log_msg("  Intentando: $url");
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_FILE           => fopen($zipPath, 'w'),
                CURLOPT_TIMEOUT        => 1800, // 30 min para archivo grande
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; PERUdata-Import/1.0)',
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            $ok   = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $size = curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);
            curl_close($ch);

            if ($ok && $code === 200 && $size > 1_000_000) {
                log_msg("  Descargado: " . number_format($size / 1024 / 1024, 1) . " MB");
                $downloaded = true;
                break;
            }
            log_msg("  Falló HTTP $code");
            @unlink($zipPath);
        }

        if (!$downloaded) {
            abort(
                "No se pudo descargar el padrón. Descárgalo manualmente desde\n" .
                "  https://www.sunat.gob.pe/descargaPRR/\n" .
                "y ejecuta: php cron/import_padron.php --file=/ruta/padron.txt",
                $pdo, $importId
            );
        }
    } else {
        log_msg("ZIP ya existe, reutilizando. (--force para re-descargar)");
    }

    // ── Descomprimir ──────────────────────────────────────────────────────────
    if (!file_exists($txtFile) || $force) {
        log_msg("Descomprimiendo...");
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            abort("No se pudo abrir el ZIP.", $pdo, $importId);
        }
        $zip->extractTo(TEMP_DIR);
        $zip->close();

        // El archivo puede tener nombre variable, buscar el .txt
        $found = glob(TEMP_DIR . '/*.txt') ?: glob(TEMP_DIR . '/*.TXT') ?: [];
        if (empty($found)) {
            abort("No se encontró archivo .txt en el ZIP.", $pdo, $importId);
        }
        $txtFile = $found[0];
        log_msg("Archivo extraído: $txtFile");
    }
}

// ── Contar filas ──────────────────────────────────────────────────────────────
log_msg("Contando filas...");
$totalRows = 0;
$handle    = fopen($txtFile, 'r');
if (!$handle) abort("No se puede leer: $txtFile", $pdo, $importId);
while (!feof($handle)) { fgets($handle); $totalRows++; }
rewind($handle);
log_msg("Total filas: " . number_format($totalRows));
$pdo->prepare('UPDATE padron_imports SET total_rows=? WHERE id=?')
    ->execute([$totalRows, $importId]);

// ── Importar en batches ───────────────────────────────────────────────────────
log_msg("Importando (batch=" . BATCH_SIZE . ")...");

$imported  = 0;
$skipped   = 0;
$batch     = [];
$startTime = time();

// Detectar encoding del archivo (SUNAT usa Latin-1 / ISO-8859-1)
$firstLine = fgets($handle);
rewind($handle);
$encoding  = mb_detect_encoding($firstLine, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
if ($encoding !== 'UTF-8') {
    log_msg("Encoding detectado: $encoding → convirtiendo a UTF-8");
}

$flush = function (array &$rows) use ($pdo, &$imported): void {
    if (empty($rows)) return;

    $placeholders = implode(',', array_fill(0, count($rows), '(?,?,?,?,?,?,?,?,?,?,?)'));
    $values       = array_merge(...$rows);

    $pdo->prepare(
        "INSERT INTO ruc_padron
         (ruc,razon_social,tipo_contribu,estado,condicion,departamento,provincia,distrito,ubigeo,direccion,actividad)
         VALUES $placeholders
         ON DUPLICATE KEY UPDATE
           razon_social=VALUES(razon_social), estado=VALUES(estado),
           condicion=VALUES(condicion), departamento=VALUES(departamento),
           provincia=VALUES(provincia), distrito=VALUES(distrito),
           direccion=VALUES(direccion), updated_at=NOW()"
    )->execute($values);

    $imported += count($rows);
    $rows = [];
};

while (($line = fgets($handle)) !== false) {
    $line = trim($line);
    if ($line === '') { $skipped++; continue; }

    // Convertir encoding si necesario
    if ($encoding !== 'UTF-8') {
        $line = mb_convert_encoding($line, 'UTF-8', $encoding);
    }

    // Formato SUNAT: campos separados por | o por posición fija
    $cols = explode('|', $line);
    if (count($cols) < 5) { $skipped++; continue; }

    $ruc         = trim($cols[0] ?? '');
    $nombre      = mb_substr(trim($cols[1] ?? ''), 0, 250);
    $tipoContrib = mb_substr(trim($cols[2] ?? ''), 0, 100);
    $estado      = mb_substr(trim($cols[3] ?? ''), 0, 50);
    $condicion   = mb_substr(trim($cols[4] ?? ''), 0, 50);

    // Columns 5-16: dirección desglosada
    $ubigeo      = trim($cols[5]  ?? '');
    $viaTipo     = trim($cols[6]  ?? '');
    $viaNom      = trim($cols[7]  ?? '');
    $nro         = trim($cols[10] ?? '');
    $depto       = mb_substr(trim($cols[16] ?? ''), 0, 80);
    $prov        = mb_substr(trim($cols[17] ?? ''), 0, 80);
    $dist        = mb_substr(trim($cols[18] ?? ''), 0, 80);

    $direccion   = mb_substr(trim("$viaTipo $viaNom $nro"), 0, 300);

    if (!preg_match('/^\d{11}$/', $ruc)) { $skipped++; continue; }

    $batch[] = [$ruc, $nombre, $tipoContrib, $estado, $condicion, $depto, $prov, $dist, $ubigeo, $direccion, ''];

    if (count($batch) >= BATCH_SIZE) {
        $flush($batch);
        if ($imported % 100_000 === 0) {
            $elapsed = time() - $startTime;
            $pct     = $totalRows > 0 ? round($imported / $totalRows * 100, 1) : 0;
            log_msg("  {$imported} importados ({$pct}%) — {$elapsed}s");
            $pdo->prepare('UPDATE padron_imports SET imported_rows=? WHERE id=?')
                ->execute([$imported, $importId]);
        }
    }
}
$flush($batch);
fclose($handle);

// ── Finalizar ─────────────────────────────────────────────────────────────────
$elapsed = time() - $startTime;
$pdo->prepare('UPDATE padron_imports SET imported_rows=?, finished_at=NOW() WHERE id=?')
    ->execute([$imported, $importId]);

log_msg("─────────────────────────────────────────");
log_msg("Importación completada:");
log_msg("  Importados : " . number_format($imported));
log_msg("  Omitidos   : " . number_format($skipped));
log_msg("  Tiempo     : {$elapsed}s");
log_msg("─────────────────────────────────────────");
