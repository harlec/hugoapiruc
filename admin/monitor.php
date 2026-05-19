<?php
// admin/monitor.php — Monitor de fuentes en tiempo real
require_once __DIR__ . '/_layout.php';

// Trigger manual de chequeo
if (isset($_GET['check'])) {
    (new \App\Monitor\SourceMonitor())->checkAll();
    header('Location: /admin/monitor.php?checked=1');
    exit;
}

$sources = $db->query("SELECT * FROM source_monitors ORDER BY id")->fetchAll();

$statusInfo = [
    'ok'      => ['label' => 'Operativa',   'color' => '#10b981', 'dotBg' => '#10b981', 'cardBg' => 'rgba(16,185,129,0.05)',  'cardBorder' => 'rgba(16,185,129,0.2)',  'pulse' => true],
    'slow'    => ['label' => 'Lenta',        'color' => '#f59e0b', 'dotBg' => '#f59e0b', 'cardBg' => 'rgba(245,158,11,0.05)', 'cardBorder' => 'rgba(245,158,11,0.2)', 'pulse' => false],
    'error'   => ['label' => 'Error',        'color' => '#ef4444', 'dotBg' => '#ef4444', 'cardBg' => 'rgba(239,68,68,0.05)',  'cardBorder' => 'rgba(239,68,68,0.2)',  'pulse' => false],
    'changed' => ['label' => 'Cambio HTML',  'color' => '#f97316', 'dotBg' => '#f97316', 'cardBg' => 'rgba(249,115,22,0.05)', 'cardBorder' => 'rgba(249,115,22,0.2)', 'pulse' => false],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor — PERÚdata Admin</title>
    <style>
    @keyframes pulse-dot {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.5; transform: scale(0.85); }
    }
    .dot-pulse { animation: pulse-dot 1.8s ease-in-out infinite; }
    </style>
</head>
<body>
<?php include __DIR__ . '/_sidebar.php'; ?>

<div class="main-wrapper">
    <header class="topbar">
        <div style="flex:1;">
            <h1 style="font-size:17px;font-weight:700;color:var(--text-1);line-height:1.2;">Monitor de Fuentes</h1>
            <p style="font-size:12px;color:var(--text-2);margin-top:1px;">Estado en tiempo real de las fuentes de datos</p>
        </div>
        <a href="/admin/monitor.php?check=1"
            style="display:inline-flex;align-items:center;gap:6px;background:var(--bg-hover);border:1px solid var(--border);color:var(--text-1);border-radius:10px;padding:8px 16px;font-size:13px;font-weight:600;text-decoration:none;transition:all 0.15s;"
            onmouseover="this.style.borderColor='var(--accent)';this.style.color='var(--accent)'"
            onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--text-1)'">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            Verificar ahora
        </a>
    </header>

    <main class="main-content">

        <?php if (isset($_GET['checked'])): ?>
        <div style="margin-bottom:20px;padding:12px 16px;background:rgba(13,148,136,0.08);border:1px solid rgba(13,148,136,0.25);border-radius:10px;color:var(--accent);font-size:13px;">
            Verificación completada — <?= date('H:i:s') ?>
        </div>
        <?php endif; ?>

        <!-- Cards de fuentes -->
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px;margin-bottom:24px;">
            <?php foreach ($sources as $src): ?>
            <?php $si = $statusInfo[$src['last_status']] ?? $statusInfo['error']; ?>
            <div style="background:<?= $si['cardBg'] ?>;border:1px solid <?= $si['cardBorder'] ?>;border-radius:16px;padding:20px;box-shadow:var(--shadow);">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:14px;">
                    <div style="flex:1;min-width:0;">
                        <h3 style="font-size:14px;font-weight:700;color:var(--text-1);margin-bottom:4px;">
                            <?= htmlspecialchars($src['source_name']) ?>
                        </h3>
                        <p style="font-size:11px;color:var(--text-2);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($src['source_url']) ?>">
                            <?= htmlspecialchars($src['source_url']) ?>
                        </p>
                    </div>
                    <div style="margin-left:12px;flex-shrink:0;display:flex;align-items:center;gap:6px;">
                        <span style="font-size:12px;font-weight:600;color:<?= $si['color'] ?>;"><?= $si['label'] ?></span>
                        <span style="width:10px;height:10px;border-radius:50%;background:<?= $si['dotBg'] ?>;display:inline-block;<?= $si['pulse'] ? '' : '' ?>"
                            class="<?= $si['pulse'] ? 'dot-pulse' : '' ?>"></span>
                    </div>
                </div>

                <?php if ($src['consecutive_failures'] > 0): ?>
                <div style="margin-bottom:12px;">
                    <span class="pill" style="background:rgba(239,68,68,0.1);color:#ef4444;border:1px solid rgba(239,68,68,0.3);">
                        <?= $src['consecutive_failures'] ?> fallos consecutivos
                    </span>
                </div>
                <?php endif; ?>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                    <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:10px;padding:10px;">
                        <div style="font-size:10px;font-weight:600;color:var(--text-3);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:4px;">Respuesta</div>
                        <div style="font-size:15px;font-weight:700;color:<?= ($src['response_ms'] ?? 0) > 5000 ? '#f59e0b' : 'var(--text-1)' ?>;">
                            <?= number_format($src['response_ms'] ?? 0) ?>ms
                        </div>
                    </div>
                    <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:10px;padding:10px;">
                        <div style="font-size:10px;font-weight:600;color:var(--text-3);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:4px;">Último chequeo</div>
                        <div style="font-size:15px;font-weight:700;color:var(--text-1);">
                            <?= $src['last_check'] ? date('H:i', strtotime($src['last_check'])) : '—' ?>
                        </div>
                    </div>
                </div>

                <?php if ($src['last_error']): ?>
                <div style="margin-top:12px;padding:10px;background:rgba(239,68,68,0.07);border:1px solid rgba(239,68,68,0.2);border-radius:8px;">
                    <p style="font-size:11px;color:#ef4444;font-family:monospace;word-break:break-all;margin:0;">
                        <?= htmlspecialchars($src['last_error']) ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

            <?php if (empty($sources)): ?>
            <div style="grid-column:1/-1;text-align:center;padding:60px 20px;color:var(--text-3);">
                No hay fuentes configuradas en la base de datos.
            </div>
            <?php endif; ?>
        </div>

        <!-- Info cron -->
        <div class="card" style="padding:20px;">
            <h2 style="font-size:13px;font-weight:700;color:var(--text-1);margin-bottom:14px;">Configuración de Cron Jobs (Plesk)</h2>
            <div style="display:flex;flex-direction:column;gap:8px;">
                <div style="background:var(--bg-hover);border:1px solid var(--border);border-radius:10px;padding:12px 16px;font-family:monospace;font-size:12px;color:var(--text-1);">
                    <div style="color:var(--text-3);margin-bottom:4px;"># Monitor fuentes — cada 10 min</div>
                    */10 * * * * php <?= dirname(dirname($_SERVER['SCRIPT_FILENAME'])) ?>/cron/monitor.php
                </div>
                <div style="background:var(--bg-hover);border:1px solid var(--border);border-radius:10px;padding:12px 16px;font-family:monospace;font-size:12px;color:var(--text-1);">
                    <div style="color:var(--text-3);margin-bottom:4px;"># Limpieza caché — cada hora</div>
                    0 * * * * php <?= dirname(dirname($_SERVER['SCRIPT_FILENAME'])) ?>/cron/cleanup.php
                </div>
                <div style="background:var(--bg-hover);border:1px solid var(--border);border-radius:10px;padding:12px 16px;font-family:monospace;font-size:12px;color:var(--text-1);">
                    <div style="color:var(--text-3);margin-bottom:4px;"># Reporte diario — 8am Lima</div>
                    0 8 * * * php <?= dirname(dirname($_SERVER['SCRIPT_FILENAME'])) ?>/cron/daily_report.php
                </div>
            </div>
        </div>

    </main>
</div>

</body>
</html>
