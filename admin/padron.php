<?php
require_once __DIR__ . '/_layout.php';

// ── Acción: trigger import via web ────────────────────────────────────────────
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'import') {
        $phpBin  = $_ENV['PHP_BIN'] ?? 'php';
        $script  = dirname(__DIR__) . '/cron/import_padron.php';
        $logFile = '/tmp/perudata_padron/import_' . date('Ymd_His') . '.log';
        shell_exec("$phpBin $script > $logFile 2>&1 &");
        $message = 'success:Importación iniciada en segundo plano. Revisa el estado en unos minutos.';
    }
    if ($_POST['action'] === 'force') {
        $phpBin  = $_ENV['PHP_BIN'] ?? 'php';
        $script  = dirname(__DIR__) . '/cron/import_padron.php';
        $logFile = '/tmp/perudata_padron/import_' . date('Ymd_His') . '.log';
        shell_exec("$phpBin $script --force > $logFile 2>&1 &");
        $message = 'success:Re-importación forzada iniciada en segundo plano.';
    }
}

// ── Datos del padrón ──────────────────────────────────────────────────────────
$pdo = App\DB::getInstance();

// Total en padrón
$total = 0;
$lastUpdate = null;
try {
    $total      = (int)$pdo->query('SELECT COUNT(*) FROM ruc_padron')->fetchColumn();
    $lastUpdate = $pdo->query('SELECT MAX(updated_at) FROM ruc_padron')->fetchColumn();
} catch (\Throwable $e) {}

// Historial de importaciones
$imports = [];
try {
    $imports = $pdo->query(
        'SELECT * FROM padron_imports ORDER BY started_at DESC LIMIT 10'
    )->fetchAll(\PDO::FETCH_ASSOC);
} catch (\Throwable $e) {}

// Última importación exitosa
$lastOk = null;
foreach ($imports as $imp) {
    if (!$imp['error'] && $imp['finished_at']) { $lastOk = $imp; break; }
}

// Estadísticas de uso del padrón vs backup
try {
    $srcStats = $pdo->query(
        "SELECT source, COUNT(*) as cnt FROM usage_log
         WHERE created_at >= NOW() - INTERVAL 7 DAY AND type='ruc'
         GROUP BY source ORDER BY cnt DESC"
    )->fetchAll(\PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
    $srcStats = [];
}

[$msgType, $msgText] = $message ? explode(':', $message, 2) : ['', ''];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Padrón RUC — PERÚdata Admin</title>
</head>
<body>
<?php include __DIR__ . '/_sidebar.php'; ?>

<div class="main-wrapper">
  <div class="topbar">
    <div>
      <h1 style="font-size:1.25rem;font-weight:700;color:var(--text-1);margin:0">Padrón RUC Local</h1>
      <p style="font-size:.8rem;color:var(--text-2);margin:.2rem 0 0">Base de datos propia de SUNAT — sin depender de scraping externo</p>
    </div>
    <form method="POST" style="display:flex;gap:.5rem">
      <button type="submit" name="action" value="import" class="btn-icon"
              style="background:var(--accent);color:#fff;padding:.45rem 1rem;border:none;border-radius:6px;cursor:pointer;font-size:.85rem">
        ⬇ Importar ahora
      </button>
      <button type="submit" name="action" value="force"
              style="background:var(--bg-card);border:1px solid var(--border);color:var(--text-2);padding:.45rem 1rem;border-radius:6px;cursor:pointer;font-size:.85rem">
        ↺ Forzar re-descarga
      </button>
    </form>
  </div>

  <div class="main-content">

    <?php if ($msgText): ?>
    <div style="background:<?= $msgType==='success'?'#ecfdf5':'#fef2f2' ?>;
                border:1px solid <?= $msgType==='success'?'#6ee7b7':'#fca5a5' ?>;
                color:<?= $msgType==='success'?'#065f46':'#991b1b' ?>;
                padding:.75rem 1rem;border-radius:8px;margin-bottom:1.2rem;font-size:.875rem">
      <?= htmlspecialchars($msgText) ?>
    </div>
    <?php endif; ?>

    <!-- KPIs -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin-bottom:1.5rem">
      <?php
      $kpis = [
        ['RUCs en padrón', number_format($total), '🗄', '#0d9488'],
        ['Última actualización', $lastUpdate ? date('d/m/Y', strtotime($lastUpdate)) : '—', '📅', '#f59e0b'],
        ['Estado', $total > 0 ? 'Activo' : 'Sin datos', $total > 0 ? '✅' : '❌', $total > 0 ? '#10b981' : '#ef4444'],
        ['Próxima actualiz.', $lastOk ? date('d/m/Y', strtotime($lastOk['started_at'] . ' +7 days')) : '—', '⏰', '#8b5cf6'],
      ];
      foreach ($kpis as [$label, $val, $icon, $color]): ?>
      <div class="card" style="padding:1rem;text-align:center">
        <div style="font-size:1.5rem;margin-bottom:.3rem"><?= $icon ?></div>
        <div style="font-size:1.4rem;font-weight:700;color:<?= $color ?>"><?= $val ?></div>
        <div style="font-size:.75rem;color:var(--text-2);margin-top:.2rem"><?= $label ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.2rem">

      <!-- Fuentes de consulta RUC ultimos 7 dias -->
      <div class="card" style="padding:1.2rem">
        <h3 style="font-size:.9rem;font-weight:600;color:var(--text-1);margin:0 0 1rem">
          Fuentes RUC — últimos 7 días
        </h3>
        <?php if (empty($srcStats)): ?>
          <p style="color:var(--text-3);font-size:.85rem">Sin datos de uso aún.</p>
        <?php else:
          $total7 = array_sum(array_column($srcStats, 'cnt'));
          foreach ($srcStats as $s):
            $pct = $total7 > 0 ? round($s['cnt'] / $total7 * 100) : 0;
            $color = match($s['source']) {
              'PADRON_LOCAL'  => '#0d9488',
              'APIS_NET_V2', 'APIS_NET_V1', 'BACKUP_API' => '#3b82f6',
              'SUNAT_DIRECT'  => '#f59e0b',
              default         => '#94a3b8',
            };
        ?>
          <div style="margin-bottom:.8rem">
            <div style="display:flex;justify-content:space-between;font-size:.8rem;margin-bottom:.25rem">
              <span style="color:var(--text-1);font-weight:500"><?= htmlspecialchars($s['source']) ?></span>
              <span style="color:var(--text-2)"><?= number_format($s['cnt']) ?> (<?= $pct ?>%)</span>
            </div>
            <div style="height:6px;background:var(--border);border-radius:3px">
              <div style="height:6px;border-radius:3px;background:<?= $color ?>;width:<?= $pct ?>%"></div>
            </div>
          </div>
        <?php endforeach; endif; ?>

        <div style="margin-top:1rem;padding-top:1rem;border-top:1px solid var(--border);font-size:.78rem;color:var(--text-3)">
          <strong style="color:var(--accent)">PADRON_LOCAL</strong> = tu DB propia (ideal)<br>
          <strong style="color:#3b82f6">APIS_NET</strong> = API externa (fallback OK)<br>
          <strong style="color:#f59e0b">SUNAT_DIRECT</strong> = scraping SUNAT (evitar)
        </div>
      </div>

      <!-- Cómo funciona -->
      <div class="card" style="padding:1.2rem">
        <h3 style="font-size:.9rem;font-weight:600;color:var(--text-1);margin:0 0 1rem">
          ¿Cómo funciona?
        </h3>
        <div style="font-size:.82rem;color:var(--text-2);line-height:1.7">
          <p style="margin:0 0 .8rem">
            SUNAT publica el <strong style="color:var(--text-1)">Padrón Reducido RUC</strong>:
            un archivo con <em>todos</em> los contribuyentes registrados (~8 millones de RUCs).
          </p>
          <p style="margin:0 0 .8rem">
            Lo importamos semanalmente a nuestra propia base de datos.
            Cada consulta RUC se resuelve en <strong style="color:var(--accent)">&lt;5ms</strong>
            sin tocar los servidores de SUNAT → sin riesgo de ban.
          </p>
          <div style="background:var(--bg-page);border-radius:6px;padding:.75rem;font-family:monospace;font-size:.78rem;color:var(--text-1)">
            1. Padrón local   → &lt;5ms  ✅ (preferido)<br>
            2. apis.net.pe    → ~1s   ✅ (fallback)<br>
            3. SUNAT directo  → 3-8s  ⚠️ (último recurso)
          </div>
          <p style="margin:.8rem 0 0;font-size:.78rem">
            <strong>Descarga manual:</strong> Si la descarga automática falla,
            ve a <code style="font-size:.75rem">sunat.gob.pe/descargaPRR</code> → descarga el ZIP
            → sube al servidor y ejecuta:<br>
            <code style="font-size:.75rem">php cron/import_padron.php --file=/ruta/padron.txt</code>
          </p>
        </div>
      </div>
    </div>

    <!-- Historial de importaciones -->
    <div class="card" style="padding:1.2rem;margin-top:1.2rem">
      <h3 style="font-size:.9rem;font-weight:600;color:var(--text-1);margin:0 0 1rem">
        Historial de importaciones
      </h3>
      <?php if (empty($imports)): ?>
        <p style="color:var(--text-3);font-size:.85rem">Aún no se ha realizado ninguna importación.</p>
      <?php else: ?>
      <table class="data-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Fecha inicio</th>
            <th>Finalizado</th>
            <th>Importados</th>
            <th>Total</th>
            <th>Estado</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($imports as $imp): ?>
          <tr>
            <td style="color:var(--text-3)"><?= $imp['id'] ?></td>
            <td><?= date('d/m/Y H:i', strtotime($imp['started_at'])) ?></td>
            <td><?= $imp['finished_at'] ? date('d/m/Y H:i', strtotime($imp['finished_at'])) : '<span style="color:var(--text-3)">En curso…</span>' ?></td>
            <td><?= number_format((int)$imp['imported_rows']) ?></td>
            <td><?= $imp['total_rows'] ? number_format((int)$imp['total_rows']) : '—' ?></td>
            <td>
              <?php if ($imp['error']): ?>
                <span class="pill" style="background:#fef2f2;color:#dc2626" title="<?= htmlspecialchars($imp['error']) ?>">Error</span>
              <?php elseif ($imp['finished_at']): ?>
                <span class="pill" style="background:#ecfdf5;color:#059669">Completado</span>
              <?php else: ?>
                <span class="pill" style="background:#fffbeb;color:#d97706">En curso</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>

  </div><!-- main-content -->
</div><!-- main-wrapper -->
</body>
</html>
