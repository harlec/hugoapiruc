<?php
require_once __DIR__ . '/_layout.php';

$todayQ = (int)$db->query("SELECT COUNT(*) FROM usage_log WHERE DATE(created_at)=CURDATE() AND status='ok'")->fetchColumn();
$yestQ  = (int)$db->query("SELECT COUNT(*) FROM usage_log WHERE DATE(created_at)=DATE_SUB(CURDATE(),INTERVAL 1 DAY) AND status='ok'")->fetchColumn();
$pct    = $yestQ > 0 ? round(($todayQ - $yestQ)/$yestQ*100,1) : 0;

$cStat  = $db->query("SELECT COUNT(*) AS t, SUM(from_cache) AS h FROM usage_log WHERE DATE(created_at)=CURDATE() AND status='ok'")->fetch();
$hitR   = $cStat['t'] > 0 ? round($cStat['h']/$cStat['t']*100,1) : 0;

$tenants= (int)$db->query("SELECT COUNT(*) FROM tenants WHERE status IN ('active','trial')")->fetchColumn();
$newMo  = (int)$db->query("SELECT COUNT(*) FROM tenants WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())")->fetchColumn();

$sources= $db->query("SELECT * FROM source_monitors ORDER BY id")->fetchAll();
$srcOk  = count(array_filter($sources, fn($s)=>$s['last_status']==='ok'));

// Ultimos 7 dias
$chartDays=[]; $chartLive=[]; $chartCache=[];
for($i=6;$i>=0;$i--){
  $day=$date=date('Y-m-d',strtotime("-$i days"));
  $r=$db->prepare("SELECT COUNT(*) AS t,SUM(from_cache) AS c FROM usage_log WHERE DATE(created_at)=? AND status='ok'");
  $r->execute([$day]);$row=$r->fetch();
  $chartDays[]=date('D',strtotime($day));
  $chartLive[]=(int)$row['t']-(int)$row['c'];
  $chartCache[]=(int)$row['c'];
}

// Top tenants hoy
$topTenants=$db->query("
  SELECT t.name,p.name AS plan,p.queries_per_day,COUNT(u.id) AS used
  FROM tenants t JOIN plans p ON p.id=t.plan_id
  LEFT JOIN usage_log u ON u.tenant_id=t.id AND DATE(u.created_at)=CURDATE()
  WHERE t.status IN('active','trial') GROUP BY t.id ORDER BY used DESC LIMIT 6
")->fetchAll();

// Ultimas 8 consultas
$recent=$db->query("
  SELECT u.query_type,u.query_value,u.from_cache,u.response_ms,u.status,u.created_at,t.name AS tname
  FROM usage_log u JOIN tenants t ON t.id=u.tenant_id ORDER BY u.created_at DESC LIMIT 8
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard — PERUdata Admin</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
</head>
<body>
<?php include __DIR__.'/_sidebar.php'; ?>

<div class="main-wrapper">
  <!-- Topbar -->
  <header class="topbar">
    <div style="position:relative;flex:1;max-width:320px;">
      <svg style="position:absolute;left:10px;top:50%;transform:translateY(-50%);width:15px;height:15px;color:var(--text-3);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
      <input class="search-input" type="text" placeholder="Buscar tenant, RUC, DNI...">
    </div>
    <div style="margin-left:auto;display:flex;align-items:center;gap:10px;">
      <?php if($srcOk < count($sources)): ?>
      <a href="/admin/monitor.php" style="position:relative;">
        <button class="btn-icon" title="Fuentes con problemas">
          <svg style="width:18px;height:18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
          <span style="position:absolute;top:-4px;right:-4px;background:#ef4444;color:white;width:16px;height:16px;border-radius:50%;font-size:10px;font-weight:700;display:flex;align-items:center;justify-content:center;"><?= count($sources)-$srcOk ?></span>
        </button>
      </a>
      <?php endif; ?>
      <div style="display:flex;align-items:center;gap:8px;padding:6px 12px;background:var(--bg-hover);border:1px solid var(--border);border-radius:10px;">
        <div style="width:28px;height:28px;background:linear-gradient(135deg,#0d9488,#0891b2);border-radius:8px;display:flex;align-items:center;justify-content:center;color:white;font-size:12px;font-weight:700;">
          <?= strtoupper(substr($_SESSION['admin_user']??'A',0,1)) ?>
        </div>
        <div style="line-height:1.3;">
          <div style="font-size:13px;font-weight:600;color:var(--text-1);"><?= htmlspecialchars($_SESSION['admin_user']??'') ?></div>
          <div style="font-size:11px;color:var(--text-2);">Admin</div>
        </div>
      </div>
    </div>
  </header>

  <main class="main-content">
    <!-- Page header -->
    <div style="margin-bottom:24px;">
      <h1 style="font-size:22px;font-weight:700;color:var(--text-1);">Dashboard</h1>
      <p style="font-size:13px;color:var(--text-2);margin-top:2px;"><?= date('l, d \d\e F \d\e Y') ?></p>
    </div>

    <!-- KPI Cards -->
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:20px;">

      <!-- Consultas hoy -->
      <div class="card" style="padding:20px;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px;">
          <div>
            <p style="font-size:12px;color:var(--text-2);font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">Consultas Hoy</p>
            <span style="font-size:11px;color:<?= $pct>=0?'#0d9488':'#ef4444' ?>;font-weight:600;"><?= $pct>=0?'&uarr;':'&darr;' ?> <?= abs($pct) ?>%</span>
          </div>
          <div class="kpi-badge" style="background:rgba(13,148,136,0.1);">
            <svg style="width:22px;height:22px;color:#0d9488;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
          </div>
        </div>
        <div style="font-size:32px;font-weight:700;color:var(--text-1);"><?= number_format($todayQ) ?></div>
        <p style="font-size:12px;color:var(--text-2);margin-top:4px;">vs <?= number_format($yestQ) ?> ayer</p>
      </div>

      <!-- Cache Hit Rate -->
      <div class="card" style="padding:20px;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px;">
          <div>
            <p style="font-size:12px;color:var(--text-2);font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">Cache Hit Rate</p>
            <span style="font-size:11px;color:<?= $hitR<30?'#f97316':'#0d9488' ?>;font-weight:600;"><?= $hitR<30?'Bajo':'&Oacute;ptimo' ?></span>
          </div>
          <div class="kpi-badge" style="background:rgba(249,115,22,0.1);">
            <svg style="width:22px;height:22px;color:#f97316;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
          </div>
        </div>
        <div style="font-size:32px;font-weight:700;color:var(--text-1);"><?= $hitR ?>%</div>
        <p style="font-size:12px;color:var(--text-2);margin-top:4px;"><?= number_format($cStat['h']) ?> desde cach&eacute; hoy</p>
      </div>

      <!-- Tenants -->
      <div class="card" style="padding:20px;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px;">
          <div>
            <p style="font-size:12px;color:var(--text-2);font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">Tenants Activos</p>
            <span style="font-size:11px;color:#8b5cf6;font-weight:600;">Active</span>
          </div>
          <div class="kpi-badge" style="background:rgba(139,92,246,0.1);">
            <svg style="width:22px;height:22px;color:#8b5cf6;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
          </div>
        </div>
        <div style="font-size:32px;font-weight:700;color:var(--text-1);"><?= $tenants ?></div>
        <p style="font-size:12px;color:#0d9488;margin-top:4px;">+<?= $newMo ?> este mes</p>
      </div>

      <!-- Fuentes -->
      <div class="card" style="padding:20px;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px;">
          <div>
            <p style="font-size:12px;color:var(--text-2);font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">Fuentes API</p>
            <span style="font-size:11px;color:<?= $srcOk===count($sources)?'#0d9488':'#f59e0b' ?>;font-weight:600;"><?= $srcOk===count($sources)?round($srcOk/max(count($sources),1)*100).'%':'Alerta' ?></span>
          </div>
          <div class="kpi-badge" style="background:rgba(59,130,246,0.1);">
            <svg style="width:22px;height:22px;color:#3b82f6;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/></svg>
          </div>
        </div>
        <div style="font-size:32px;font-weight:700;color:<?= $srcOk<count($sources)?'#f59e0b':'var(--text-1)' ?>;"><?= $srcOk ?>/<?= count($sources) ?></div>
        <p style="font-size:12px;color:var(--text-2);margin-top:4px;"><?= $srcOk<count($sources)?count($sources)-$srcOk.' con problemas':'Todas operativas' ?></p>
      </div>
    </div>

    <!-- Row 2: Chart + Monitor -->
    <div style="display:grid;grid-template-columns:2fr 1fr;gap:16px;margin-bottom:20px;">

      <!-- Chart -->
      <div class="card" style="padding:24px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
          <div>
            <h2 style="font-size:14px;font-weight:600;color:var(--text-1);">Actividad Semanal</h2>
            <p style="font-size:12px;color:var(--text-2);margin-top:2px;">Consultas en vivo vs desde cach&eacute;</p>
          </div>
          <div style="display:flex;gap:16px;font-size:12px;color:var(--text-2);">
            <span style="display:flex;align-items:center;gap:6px;"><span style="width:24px;height:3px;background:#0d9488;border-radius:2px;display:inline-block;"></span>En vivo</span>
            <span style="display:flex;align-items:center;gap:6px;"><span style="width:24px;height:3px;background:#8b5cf6;border-radius:2px;display:inline-block;"></span>Cach&eacute;</span>
          </div>
        </div>
        <canvas id="chart" height="95"></canvas>
      </div>

      <!-- Source monitor -->
      <div class="card" style="padding:24px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
          <h2 style="font-size:14px;font-weight:600;color:var(--text-1);">Estado de Fuentes</h2>
          <a href="/admin/monitor.php" style="font-size:12px;color:var(--accent);text-decoration:none;">Ver todo &rarr;</a>
        </div>
        <?php
        $sc=['ok'=>['#0d9488','rgba(13,148,136,0.08)','Operativa'],'slow'=>['#f59e0b','rgba(245,158,11,0.08)','Lenta'],'error'=>['#ef4444','rgba(239,68,68,0.08)','Error'],'changed'=>['#f97316','rgba(249,115,22,0.08)','Cambio']];
        foreach($sources as $src):
          [$color,$bg,$label]=$sc[$src['last_status']]??$sc['error'];
        ?>
        <div style="background:<?= $bg ?>;border-radius:12px;padding:14px;margin-bottom:10px;">
          <div style="display:flex;align-items:center;justify-content:space-between;">
            <div style="display:flex;align-items:center;gap:10px;">
              <div style="width:8px;height:8px;border-radius:50%;background:<?= $color ?>;<?= $src['last_status']==='ok'?'animation:pulse 2s infinite':'' ?>"></div>
              <div>
                <div style="font-size:13px;font-weight:600;color:var(--text-1);"><?= htmlspecialchars($src['source_name']) ?></div>
                <div style="font-size:11px;color:var(--text-2);"><?= $src['last_check']?date('H:i',strtotime($src['last_check'])):'Sin chequeo' ?></div>
              </div>
            </div>
            <div style="text-align:right;">
              <div style="font-size:15px;font-weight:700;color:<?= $color ?>;"><?= $src['response_ms'] ?>ms</div>
              <div style="font-size:11px;color:<?= $color ?>;"><?= $label ?></div>
            </div>
          </div>
          <?php if($src['consecutive_failures']>0): ?>
          <div style="margin-top:8px;font-size:11px;color:#ef4444;display:flex;align-items:center;gap:4px;">
            <span>&#9679;</span> <?= $src['consecutive_failures'] ?> fallos consecutivos
          </div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Row 3: Tenant usage + Recent -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">

      <!-- Tenant usage bars -->
      <div class="card" style="padding:24px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
          <h2 style="font-size:14px;font-weight:600;color:var(--text-1);">Consumo por Tenant &mdash; Hoy</h2>
          <a href="/admin/api-keys.php" style="font-size:12px;color:var(--accent);text-decoration:none;">Ver todos &rarr;</a>
        </div>
        <?php foreach($topTenants as $t):
          $pct2=$t['queries_per_day']>0?min(100,round($t['used']/$t['queries_per_day']*100)):0;
          $bar=$pct2>=90?'#ef4444':($pct2>=70?'#f59e0b':'#0d9488');
        ?>
        <div style="margin-bottom:18px;">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">
            <div style="display:flex;align-items:center;gap:8px;">
              <span style="font-size:13.5px;font-weight:600;color:var(--text-1);"><?= htmlspecialchars($t['name']) ?></span>
              <span style="font-size:10px;background:var(--bg-hover);border:1px solid var(--border);color:var(--text-2);padding:1px 6px;border-radius:99px;"><?= htmlspecialchars($t['plan']) ?></span>
            </div>
            <span style="font-size:13px;font-weight:700;color:<?= $bar ?>;"><?= $pct2 ?>%</span>
          </div>
          <div style="font-size:11px;color:var(--text-2);margin-bottom:6px;"><?= number_format($t['used']) ?> / <?= number_format($t['queries_per_day']) ?> consultas</div>
          <div style="height:7px;background:var(--bg-hover);border-radius:99px;border:1px solid var(--border);">
            <div style="height:100%;width:<?= $pct2 ?>%;background:<?= $bar ?>;border-radius:99px;transition:width 0.5s;"></div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if(empty($topTenants)): ?><p style="color:var(--text-3);font-size:13px;text-align:center;padding:24px 0;">Sin consultas hoy</p><?php endif; ?>
      </div>

      <!-- Recent activity -->
      <div class="card" style="padding:24px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
          <h2 style="font-size:14px;font-weight:600;color:var(--text-1);">Actividad Reciente</h2>
          <a href="/admin/logs.php" style="font-size:12px;color:var(--accent);text-decoration:none;">Ver logs &rarr;</a>
        </div>
        <?php foreach($recent as $log):
          $dot=['ok'=>'#0d9488','error'=>'#ef4444','rate_limit'=>'#f59e0b'][$log['status']]??'#94a3b8';
          $typeColor=$log['query_type']==='ruc'?'#0d9488':'#8b5cf6';
        ?>
        <div style="display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid var(--border);">
          <span style="width:7px;height:7px;border-radius:50%;background:<?= $dot ?>;flex-shrink:0;"></span>
          <span style="font-size:11px;font-weight:700;font-family:monospace;color:<?= $typeColor ?>;width:28px;flex-shrink:0;"><?= strtoupper($log['query_type']??'') ?></span>
          <span style="font-size:13px;font-family:monospace;color:var(--text-1);flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($log['query_value']??'') ?></span>
          <span style="font-size:11px;color:var(--text-2);flex-shrink:0;"><?= $log['from_cache']?'cache':'live' ?></span>
          <span style="font-size:11px;color:var(--text-2);flex-shrink:0;width:40px;text-align:right;"><?= $log['response_ms'] ?>ms</span>
          <span style="font-size:11px;color:var(--text-3);flex-shrink:0;"><?= date('H:i',strtotime($log['created_at'])) ?></span>
        </div>
        <?php endforeach; ?>
        <?php if(empty($recent)): ?><p style="color:var(--text-3);font-size:13px;text-align:center;padding:24px 0;">Sin actividad a&uacute;n</p><?php endif; ?>
      </div>
    </div>
  </main>
</div>

<script>
Chart.defaults.font.family = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif";
const isDark = document.body.classList.contains('dark-mode');
const gridColor = isDark ? '#1e2536' : '#f1f5f9';
const tickColor = isDark ? '#475569' : '#94a3b8';

new Chart(document.getElementById('chart'), {
  type: 'line',
  data: {
    labels: <?= json_encode($chartDays) ?>,
    datasets: [
      { label:'En vivo', data:<?= json_encode($chartLive) ?>, borderColor:'#0d9488', backgroundColor:'rgba(13,148,136,0.06)', pointBackgroundColor:'#0d9488', pointBorderColor:'#fff', pointBorderWidth:2, pointRadius:5, tension:0.4, fill:true },
      { label:'Cache',   data:<?= json_encode($chartCache) ?>, borderColor:'#8b5cf6', backgroundColor:'rgba(139,92,246,0.06)', pointBackgroundColor:'#8b5cf6', pointBorderColor:'#fff', pointBorderWidth:2, pointRadius:5, tension:0.4, fill:true },
    ]
  },
  options: {
    responsive:true,
    interaction:{mode:'index',intersect:false},
    plugins:{
      legend:{display:false},
      tooltip:{backgroundColor:'var(--bg-card)',borderColor:'var(--border)',borderWidth:1,titleColor:'var(--text-1)',bodyColor:'var(--text-2)',padding:10,boxPadding:4}
    },
    scales:{
      x:{grid:{color:gridColor},ticks:{color:tickColor,font:{size:12}}},
      y:{grid:{color:gridColor},ticks:{color:tickColor,font:{size:12}},beginAtZero:true}
    }
  }
});
</script>
<style>@keyframes pulse{0%,100%{opacity:1}50%{opacity:0.4}}</style>
</body>
</html>
