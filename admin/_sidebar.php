<?php
// _sidebar.php — sidebar + CSS global compartido en todo el admin
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$nav = [
    ['page'=>'index',     'href'=>'/admin/index.php',     'icon'=>'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6', 'label'=>'Dashboard'],
    ['page'=>'tenants',   'href'=>'/admin/tenants.php',   'icon'=>'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z', 'label'=>'Tenants'],
    ['page'=>'api-keys',  'href'=>'/admin/api-keys.php',  'icon'=>'M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z', 'label'=>'API Keys'],
    ['page'=>'monitor',   'href'=>'/admin/monitor.php',   'icon'=>'M9 3H5a2 2 0 00-2 2v4m2-6h10a2 2 0 012 2v4M9 3v4m0 0H5m4 0h6m0-4v4m0 0h4M9 7h6m0 0v10a2 2 0 01-2 2H9a2 2 0 01-2-2V7', 'label'=>'Monitor'],
    ['page'=>'analytics', 'href'=>'/admin/analytics.php', 'icon'=>'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z', 'label'=>'Analytics'],
    ['page'=>'plans',     'href'=>'/admin/plans.php',     'icon'=>'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z', 'label'=>'Planes'],
    ['page'=>'logs',      'href'=>'/admin/logs.php',      'icon'=>'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2', 'label'=>'Logs'],
];
?>
<style>
:root {
  --bg-page:    #f0f4f8;
  --bg-sidebar: #ffffff;
  --bg-card:    #ffffff;
  --bg-hover:   #f8fafc;
  --bg-active:  #f0fdfa;
  --text-1:     #0f172a;
  --text-2:     #64748b;
  --text-3:     #94a3b8;
  --border:     #e2e8f0;
  --accent:     #0d9488;
  --accent-bg:  rgba(13,148,136,0.1);
  --shadow:     0 1px 3px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.04);
  --shadow-md:  0 4px 6px rgba(0,0,0,0.07), 0 2px 4px rgba(0,0,0,0.04);
}
.dark-mode {
  --bg-page:    #0d1117;
  --bg-sidebar: #161b27;
  --bg-card:    #1a2035;
  --bg-hover:   #1e2536;
  --bg-active:  #162032;
  --text-1:     #f1f5f9;
  --text-2:     #94a3b8;
  --text-3:     #475569;
  --border:     #1e2536;
  --accent:     #14b8a6;
  --accent-bg:  rgba(20,184,166,0.12);
  --shadow:     0 1px 3px rgba(0,0,0,0.3);
  --shadow-md:  0 4px 6px rgba(0,0,0,0.4);
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body { height: 100%; }
body {
  background: var(--bg-page);
  color: var(--text-1);
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  display: flex;
  transition: background 0.2s, color 0.2s;
}
/* Cards */
.card {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 16px;
  box-shadow: var(--shadow);
}
/* Topbar */
.topbar {
  background: var(--bg-card);
  border-bottom: 1px solid var(--border);
  padding: 12px 24px;
  display: flex;
  align-items: center;
  gap: 12px;
  box-shadow: var(--shadow);
}
/* Sidebar */
.sidebar {
  width: 240px;
  min-width: 240px;
  background: var(--bg-sidebar);
  border-right: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  height: 100vh;
  position: sticky;
  top: 0;
  overflow-y: auto;
}
.sidebar-logo {
  padding: 20px 16px;
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  gap: 10px;
}
.logo-icon {
  width: 36px; height: 36px;
  background: linear-gradient(135deg, #0d9488, #0891b2);
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  font-weight: 700; color: white; font-size: 12px; letter-spacing: 0.5px;
}
.logo-text { font-weight: 700; font-size: 14px; color: var(--text-1); line-height: 1.2; }
.logo-sub  { font-size: 11px; color: var(--text-2); }
.nav-section { padding: 12px 10px; flex: 1; }
.nav-label { font-size: 10px; font-weight: 600; color: var(--text-3); text-transform: uppercase; letter-spacing: 0.08em; padding: 0 8px; margin-bottom: 4px; margin-top: 8px; }
.nav-item {
  display: flex; align-items: center; gap: 10px;
  padding: 9px 10px; border-radius: 10px; margin-bottom: 2px;
  font-size: 13.5px; font-weight: 500; color: var(--text-2);
  text-decoration: none; cursor: pointer;
  transition: background 0.15s, color 0.15s;
}
.nav-item:hover { background: var(--bg-hover); color: var(--text-1); }
.nav-item.active { background: var(--bg-active); color: var(--accent); font-weight: 600; }
.nav-item svg { width: 17px; height: 17px; flex-shrink: 0; }
.sidebar-footer { padding: 12px 10px; border-top: 1px solid var(--border); }
.main-wrapper { flex: 1; display: flex; flex-direction: column; min-width: 0; height: 100vh; overflow: hidden; }
.main-content { flex: 1; overflow-y: auto; padding: 24px; }
/* Search input */
.search-input {
  background: var(--bg-hover);
  border: 1px solid var(--border);
  color: var(--text-1);
  border-radius: 10px;
  padding: 8px 12px 8px 36px;
  font-size: 13px;
  width: 100%; max-width: 320px;
  outline: none; transition: border-color 0.15s;
}
.search-input:focus { border-color: var(--accent); }
.search-input::placeholder { color: var(--text-3); }
/* Btn icon */
.btn-icon {
  background: var(--bg-hover); border: 1px solid var(--border);
  color: var(--text-2); border-radius: 10px;
  padding: 7px; cursor: pointer; display: flex; align-items: center; transition: all 0.15s;
}
.btn-icon:hover { background: var(--bg-active); color: var(--accent); border-color: var(--accent); }
/* KPI badge */
.kpi-badge {
  width: 44px; height: 44px; border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
}
.kpi-badge svg { width: 22px; height: 22px; }
/* Table */
.data-table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
.data-table th { padding: 10px 14px; text-align: left; font-size: 11px; font-weight: 600; color: var(--text-2); text-transform: uppercase; letter-spacing: 0.06em; border-bottom: 1px solid var(--border); background: var(--bg-hover); }
.data-table td { padding: 12px 14px; border-bottom: 1px solid var(--border); color: var(--text-1); }
.data-table tr:last-child td { border-bottom: none; }
.data-table tr:hover td { background: var(--bg-hover); }
/* Badge/pill */
.pill { display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 99px; font-size: 11px; font-weight: 600; }
/* Scrollbar */
::-webkit-scrollbar { width: 5px; height: 5px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--border); border-radius: 99px; }
</style>

<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="logo-icon">PE</div>
    <div>
      <div class="logo-text">PERÚdata</div>
      <div class="logo-sub">Panel Admin</div>
    </div>
  </div>

  <nav class="nav-section">
    <div class="nav-label">Principal</div>
    <?php foreach ($nav as $item):
      $active = $currentPage === $item['page'] ? 'active' : '';
    ?>
    <a href="<?= $item['href'] ?>" class="nav-item <?= $active ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
        <path stroke-linecap="round" stroke-linejoin="round" d="<?= $item['icon'] ?>"/>
      </svg>
      <?= $item['label'] ?>
    </a>
    <?php endforeach; ?>

    <div class="nav-label" style="margin-top:16px;">Herramientas</div>
    <a href="/playground.php" target="_blank" class="nav-item">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      API Tester
    </a>
    <a href="/docs.php" target="_blank" class="nav-item">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
      Documentación
    </a>
  </nav>

  <div class="sidebar-footer">
    <button onclick="toggleTheme()" class="nav-item" style="width:100%;border:none;background:none;text-align:left;" id="theme-btn">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8" id="theme-icon-moon"><path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
      <span id="theme-label">Modo oscuro</span>
    </button>
    <a href="/admin/logout.php" class="nav-item" style="color:#ef4444;">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
      Cerrar sesión
    </a>
  </div>
</aside>

<script>
(function(){
  const saved = localStorage.getItem('perudata-theme');
  if (saved === 'dark') {
    document.body.classList.add('dark-mode');
    const lbl = document.getElementById('theme-label');
    const ico = document.getElementById('theme-icon-moon');
    if (lbl) lbl.textContent = 'Modo claro';
    if (ico) ico.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707M17.657 17.657l-.707-.707M6.343 6.343l-.707-.707M12 8a4 4 0 100 8 4 4 0 000-8z"/>';
  }
})();
function toggleTheme() {
  const isDark = document.body.classList.toggle('dark-mode');
  localStorage.setItem('perudata-theme', isDark ? 'dark' : 'light');
  const lbl = document.getElementById('theme-label');
  const ico = document.getElementById('theme-icon-moon');
  if (isDark) {
    if (lbl) lbl.textContent = 'Modo claro';
    if (ico) ico.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707M17.657 17.657l-.707-.707M6.343 6.343l-.707-.707M12 8a4 4 0 100 8 4 4 0 000-8z"/>';
  } else {
    if (lbl) lbl.textContent = 'Modo oscuro';
    if (ico) ico.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>';
  }
}
</script>
