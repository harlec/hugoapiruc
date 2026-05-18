<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PERÚdata API — Probador</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
</head>
<body class="bg-gray-950 text-gray-100 min-h-screen">

<!-- Navbar -->
<nav class="bg-gray-900 border-b border-gray-800 px-6 py-4 flex items-center justify-between">
  <div class="flex items-center gap-3">
    <div class="w-8 h-8 bg-red-600 rounded-lg flex items-center justify-center font-bold text-sm">PE</div>
    <span class="font-semibold text-white">PERÚdata API</span>
    <span class="text-gray-500 text-sm">— Probador Interactivo</span>
  </div>
  <div class="flex gap-4 text-sm">
    <a href="/docs.php" class="text-gray-400 hover:text-white transition">Documentación</a>
    <a href="/admin/" class="text-gray-400 hover:text-white transition">Admin Panel</a>
  </div>
</nav>

<div class="max-w-5xl mx-auto px-6 py-10">

  <!-- Hero -->
  <div class="text-center mb-10">
    <h1 class="text-3xl font-bold text-white mb-2">Prueba la API en tiempo real</h1>
    <p class="text-gray-400">Consulta RUC de SUNAT y DNI de RENIEC/SUNAT con tu token de acceso</p>
  </div>

  <!-- Form -->
  <div class="bg-gray-900 rounded-2xl border border-gray-800 p-6 mb-6">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
      <!-- Tipo -->
      <div>
        <label class="block text-xs text-gray-400 mb-1 uppercase tracking-wide">Tipo de consulta</label>
        <div class="flex rounded-lg overflow-hidden border border-gray-700">
          <button onclick="setTipo('ruc')" id="btn-ruc"
            class="flex-1 py-2.5 text-sm font-medium bg-red-600 text-white transition">RUC (11 dígitos)</button>
          <button onclick="setTipo('dni')" id="btn-dni"
            class="flex-1 py-2.5 text-sm font-medium bg-gray-800 text-gray-400 hover:bg-gray-700 transition">DNI (8 dígitos)</button>
        </div>
      </div>
      <!-- Número -->
      <div>
        <label class="block text-xs text-gray-400 mb-1 uppercase tracking-wide">Número</label>
        <input id="input-numero" type="text" maxlength="11" placeholder="20131312955"
          class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:border-red-500 focus:ring-1 focus:ring-red-500 font-mono">
      </div>
      <!-- Token -->
      <div>
        <label class="block text-xs text-gray-400 mb-1 uppercase tracking-wide">Bearer Token</label>
        <input id="input-token" type="text" placeholder="Tu API token..."
          class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:border-red-500 focus:ring-1 focus:ring-red-500 font-mono">
      </div>
    </div>
    <button onclick="consultar()"
      class="w-full bg-red-600 hover:bg-red-700 text-white font-semibold py-3 rounded-lg transition flex items-center justify-center gap-2" id="btn-consultar">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
      Consultar
    </button>
  </div>

  <!-- Resultados -->
  <div id="resultado" class="hidden">
    <!-- Métricas rápidas -->
    <div id="metricas" class="grid grid-cols-3 gap-4 mb-4"></div>
    <!-- Estado HTTP -->
    <div id="estado-http" class="mb-2"></div>
    <!-- URL consultada -->
    <div id="url-consultada" class="bg-gray-900 border border-gray-800 rounded-lg px-4 py-2 mb-4 text-xs font-mono text-gray-400"></div>
    <!-- JSON -->
    <div class="bg-gray-900 rounded-2xl border border-gray-800 overflow-hidden">
      <div class="flex items-center justify-between px-4 py-3 border-b border-gray-800">
        <span class="text-xs text-gray-400 uppercase tracking-wide">Respuesta JSON</span>
        <button onclick="copiarJson()" class="text-xs text-gray-500 hover:text-white transition flex items-center gap-1">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
          Copiar
        </button>
      </div>
      <pre class="p-4 overflow-x-auto text-sm"><code id="json-output" class="language-json"></code></pre>
    </div>
  </div>

  <!-- Ejemplos rápidos -->
  <div class="mt-8">
    <h3 class="text-sm text-gray-400 uppercase tracking-wide mb-3">Ejemplos de prueba</h3>
    <div class="flex flex-wrap gap-2">
      <button onclick="probar('ruc','20131312955')" class="px-3 py-1.5 bg-gray-800 hover:bg-gray-700 rounded-lg text-xs text-gray-300 font-mono transition">RUC: 20131312955 (SUNAT)</button>
      <button onclick="probar('ruc','20100070970')" class="px-3 py-1.5 bg-gray-800 hover:bg-gray-700 rounded-lg text-xs text-gray-300 font-mono transition">RUC: 20100070970 (BCP)</button>
      <button onclick="probar('ruc','20601234567')" class="px-3 py-1.5 bg-gray-800 hover:bg-gray-700 rounded-lg text-xs text-gray-300 font-mono transition">RUC: 20601234567</button>
      <button onclick="probar('dni','48004836')" class="px-3 py-1.5 bg-gray-800 hover:bg-gray-700 rounded-lg text-xs text-gray-300 font-mono transition">DNI: 48004836</button>
    </div>
  </div>
</div>

<script>
let tipoActual = 'ruc';
let lastJson = '';

function setTipo(t) {
  tipoActual = t;
  const btnRuc = document.getElementById('btn-ruc');
  const btnDni = document.getElementById('btn-dni');
  const inp = document.getElementById('input-numero');
  if (t === 'ruc') {
    btnRuc.className = 'flex-1 py-2.5 text-sm font-medium bg-red-600 text-white transition';
    btnDni.className = 'flex-1 py-2.5 text-sm font-medium bg-gray-800 text-gray-400 hover:bg-gray-700 transition';
    inp.maxLength = 11; inp.placeholder = '20131312955';
  } else {
    btnDni.className = 'flex-1 py-2.5 text-sm font-medium bg-red-600 text-white transition';
    btnRuc.className = 'flex-1 py-2.5 text-sm font-medium bg-gray-800 text-gray-400 hover:bg-gray-700 transition';
    inp.maxLength = 8; inp.placeholder = '48004836';
  }
}

function probar(tipo, num) {
  setTipo(tipo);
  document.getElementById('input-numero').value = num;
}

async function consultar() {
  const numero = document.getElementById('input-numero').value.trim();
  const token  = document.getElementById('input-token').value.trim();
  const btn    = document.getElementById('btn-consultar');

  if (!numero) { alert('Ingresa un número'); return; }
  if (!token)  { alert('Ingresa tu Bearer Token'); return; }

  btn.disabled = true;
  btn.innerHTML = '<svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg> Consultando...';

  const url = `/api/v1/${tipoActual}/${numero}`;
  const t0  = performance.now();

  try {
    const resp = await fetch(url, {
      headers: { 'Authorization': 'Bearer ' + token }
    });
    const ms   = Math.round(performance.now() - t0);
    const data = await resp.json();
    lastJson   = JSON.stringify(data, null, 2);

    document.getElementById('resultado').classList.remove('hidden');
    document.getElementById('json-output').textContent = lastJson;
    hljs.highlightElement(document.getElementById('json-output'));

    // URL consultada
    document.getElementById('url-consultada').textContent = 'GET ' + window.location.origin + url;

    // Estado HTTP
    const ok = resp.ok;
    document.getElementById('estado-http').innerHTML =
      `<span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-mono font-bold ${ok ? 'bg-green-900 text-green-300' : 'bg-red-900 text-red-300'}">
        ${resp.status} ${resp.statusText}
      </span>`;

    // Métricas
    const fromCache = data.from_cache ? '✓ Caché' : '↗ En vivo';
    const remaining = data.remaining ?? '—';
    document.getElementById('metricas').innerHTML = `
      <div class="bg-gray-900 border border-gray-800 rounded-xl p-4 text-center">
        <div class="text-2xl font-bold text-white">${ms} ms</div>
        <div class="text-xs text-gray-500 mt-1">Tiempo respuesta</div>
      </div>
      <div class="bg-gray-900 border border-gray-800 rounded-xl p-4 text-center">
        <div class="text-2xl font-bold ${data.from_cache ? 'text-blue-400' : 'text-green-400'}">${fromCache}</div>
        <div class="text-xs text-gray-500 mt-1">Origen</div>
      </div>
      <div class="bg-gray-900 border border-gray-800 rounded-xl p-4 text-center">
        <div class="text-2xl font-bold text-yellow-400">${remaining}</div>
        <div class="text-xs text-gray-500 mt-1">Consultas restantes</div>
      </div>`;
  } catch(e) {
    document.getElementById('resultado').classList.remove('hidden');
    document.getElementById('json-output').textContent = JSON.stringify({error: e.message}, null, 2);
  }

  btn.disabled = false;
  btn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg> Consultar';
}

function copiarJson() {
  navigator.clipboard.writeText(lastJson).then(() => alert('Copiado al portapapeles'));
}

document.getElementById('input-numero').addEventListener('keydown', e => {
  if (e.key === 'Enter') consultar();
});
</script>
</body>
</html>
