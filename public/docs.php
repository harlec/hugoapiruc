<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';
$baseUrl = APP_URL;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PERÚdata API — Documentación</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
</head>
<body class="bg-gray-950 text-gray-100">

<nav class="bg-gray-900 border-b border-gray-800 px-6 py-4 flex items-center justify-between sticky top-0 z-50">
  <div class="flex items-center gap-3">
    <div class="w-8 h-8 bg-red-600 rounded-lg flex items-center justify-center font-bold text-sm">PE</div>
    <span class="font-semibold text-white">PERÚdata API</span>
    <span class="text-gray-500 text-sm">v1</span>
  </div>
  <div class="flex gap-4 text-sm">
    <a href="/playground.php" class="text-red-400 hover:text-red-300 font-medium transition">▶ Probar API</a>
    <a href="/admin/" class="text-gray-400 hover:text-white transition">Admin Panel</a>
  </div>
</nav>

<div class="flex max-w-7xl mx-auto">
  <!-- Sidebar -->
  <aside class="w-56 shrink-0 py-8 px-4 sticky top-16 h-[calc(100vh-4rem)] overflow-y-auto hidden lg:block">
    <nav class="space-y-1 text-sm">
      <p class="text-xs text-gray-500 uppercase tracking-wider mb-2">Introducción</p>
      <a href="#autenticacion" class="block px-3 py-1.5 rounded text-gray-400 hover:text-white hover:bg-gray-800">Autenticación</a>
      <a href="#rate-limiting" class="block px-3 py-1.5 rounded text-gray-400 hover:text-white hover:bg-gray-800">Rate Limiting</a>
      <a href="#errores" class="block px-3 py-1.5 rounded text-gray-400 hover:text-white hover:bg-gray-800">Errores</a>
      <p class="text-xs text-gray-500 uppercase tracking-wider mt-4 mb-2">Endpoints</p>
      <a href="#ruc" class="block px-3 py-1.5 rounded text-gray-400 hover:text-white hover:bg-gray-800">GET /ruc/{numero}</a>
      <a href="#dni" class="block px-3 py-1.5 rounded text-gray-400 hover:text-white hover:bg-gray-800">GET /dni/{numero}</a>
      <a href="#health" class="block px-3 py-1.5 rounded text-gray-400 hover:text-white hover:bg-gray-800">GET /health</a>
      <p class="text-xs text-gray-500 uppercase tracking-wider mt-4 mb-2">Ejemplos</p>
      <a href="#curl" class="block px-3 py-1.5 rounded text-gray-400 hover:text-white hover:bg-gray-800">cURL</a>
      <a href="#php" class="block px-3 py-1.5 rounded text-gray-400 hover:text-white hover:bg-gray-800">PHP</a>
      <a href="#javascript" class="block px-3 py-1.5 rounded text-gray-400 hover:text-white hover:bg-gray-800">JavaScript</a>
      <a href="#python" class="block px-3 py-1.5 rounded text-gray-400 hover:text-white hover:bg-gray-800">Python</a>
      <a href="#planes" class="block px-3 py-1.5 rounded text-gray-400 hover:text-white hover:bg-gray-800">Planes</a>
    </nav>
  </aside>

  <!-- Content -->
  <main class="flex-1 py-8 px-6 max-w-4xl">

    <h1 class="text-4xl font-bold text-white mb-2">Documentación de la API</h1>
    <p class="text-gray-400 mb-8">Versión 1.0 — Base URL: <code class="bg-gray-800 px-2 py-0.5 rounded text-red-400 text-sm"><?= $baseUrl ?></code></p>

    <!-- Autenticación -->
    <section id="autenticacion" class="mb-12">
      <h2 class="text-2xl font-bold text-white mb-4 pb-2 border-b border-gray-800">Autenticación</h2>
      <p class="text-gray-400 mb-4">Todas las peticiones requieren un <strong class="text-white">Bearer Token</strong> en el header <code class="bg-gray-800 px-1 rounded text-red-400">Authorization</code>.</p>
      <div class="bg-gray-900 rounded-xl border border-gray-800 overflow-hidden mb-4">
        <div class="px-4 py-2 bg-gray-800 text-xs text-gray-400">Header requerido</div>
        <pre class="p-4"><code class="language-http">Authorization: Bearer TU_API_TOKEN</code></pre>
      </div>
      <div class="bg-yellow-950 border border-yellow-800 rounded-xl p-4 text-sm text-yellow-200">
        Tu token lo encuentras en <strong>Admin Panel → Tenants</strong>. Mantenlo seguro, no lo compartas en código público.
      </div>
    </section>

    <!-- Rate Limiting -->
    <section id="rate-limiting" class="mb-12">
      <h2 class="text-2xl font-bold text-white mb-4 pb-2 border-b border-gray-800">Rate Limiting</h2>
      <p class="text-gray-400 mb-4">Los límites se aplican por tenant según tu plan. El campo <code class="bg-gray-800 px-1 rounded text-red-400">remaining</code> en cada respuesta indica cuántas consultas te quedan en el día.</p>
      <table class="w-full text-sm border border-gray-800 rounded-xl overflow-hidden">
        <thead class="bg-gray-800">
          <tr>
            <th class="px-4 py-2 text-left text-gray-300">Plan</th>
            <th class="px-4 py-2 text-left text-gray-300">Consultas/día</th>
            <th class="px-4 py-2 text-left text-gray-300">RUC</th>
            <th class="px-4 py-2 text-left text-gray-300">DNI</th>
            <th class="px-4 py-2 text-left text-gray-300">Caché TTL</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-800">
          <tr class="bg-gray-900"><td class="px-4 py-2 text-gray-300">Trial</td><td class="px-4 py-2 text-gray-400">20</td><td class="px-4 py-2 text-green-400">✓</td><td class="px-4 py-2 text-gray-500">✗</td><td class="px-4 py-2 text-gray-400">24h</td></tr>
          <tr class="bg-gray-900"><td class="px-4 py-2 text-gray-300">Básico</td><td class="px-4 py-2 text-gray-400">200</td><td class="px-4 py-2 text-green-400">✓</td><td class="px-4 py-2 text-gray-500">✗</td><td class="px-4 py-2 text-gray-400">24h</td></tr>
          <tr class="bg-gray-900"><td class="px-4 py-2 text-gray-300">Pro</td><td class="px-4 py-2 text-gray-400">1,000</td><td class="px-4 py-2 text-green-400">✓</td><td class="px-4 py-2 text-green-400">✓</td><td class="px-4 py-2 text-gray-400">48h</td></tr>
          <tr class="bg-gray-900"><td class="px-4 py-2 text-gray-300">Enterprise</td><td class="px-4 py-2 text-gray-400">5,000</td><td class="px-4 py-2 text-green-400">✓</td><td class="px-4 py-2 text-green-400">✓</td><td class="px-4 py-2 text-gray-400">72h</td></tr>
        </tbody>
      </table>
    </section>

    <!-- Endpoint RUC -->
    <section id="ruc" class="mb-12">
      <h2 class="text-2xl font-bold text-white mb-4 pb-2 border-b border-gray-800">GET /api/v1/ruc/{numero}</h2>
      <p class="text-gray-400 mb-4">Consulta datos de un contribuyente por su RUC de 11 dígitos desde SUNAT.</p>
      <div class="bg-gray-900 rounded-xl border border-gray-800 overflow-hidden mb-4">
        <div class="px-4 py-2 bg-gray-800 text-xs text-gray-400">Request</div>
        <pre class="p-4"><code class="language-http">GET <?= $baseUrl ?>/api/v1/ruc/20131312955
Authorization: Bearer TU_TOKEN</code></pre>
      </div>
      <div class="bg-gray-900 rounded-xl border border-gray-800 overflow-hidden mb-4">
        <div class="px-4 py-2 bg-gray-800 text-xs text-gray-400">Response 200 OK</div>
        <pre class="p-4"><code class="language-json">{
  "ruc":           "20131312955",
  "razon_social":  "SUPERINTENDENCIA NACIONAL DE ADUANAS Y DE ADMINISTRACION TRIBUTARIA",
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
  "response_ms":   1240,
  "remaining":     198
}</code></pre>
      </div>
    </section>

    <!-- Endpoint DNI -->
    <section id="dni" class="mb-12">
      <h2 class="text-2xl font-bold text-white mb-4 pb-2 border-b border-gray-800">GET /api/v1/dni/{numero}</h2>
      <p class="text-gray-400 mb-4">Consulta datos de una persona por su DNI de 8 dígitos. Requiere plan Pro o superior.</p>
      <div class="bg-gray-900 rounded-xl border border-gray-800 overflow-hidden mb-4">
        <div class="px-4 py-2 bg-gray-800 text-xs text-gray-400">Response 200 OK</div>
        <pre class="p-4"><code class="language-json">{
  "dni":             "48004836",
  "nombres":         "ROBERTO CARLOS",
  "apellido_pat":    "SULCA",
  "apellido_mat":    "BASILIO",
  "nombre_completo": "SULCA BASILIO ROBERTO CARLOS",
  "from_cache":      true,
  "cached_at":       "2026-05-15 14:22:10",
  "cache_hits":      5,
  "response_ms":     3,
  "remaining":       987
}</code></pre>
      </div>
    </section>

    <!-- Errores -->
    <section id="errores" class="mb-12">
      <h2 class="text-2xl font-bold text-white mb-4 pb-2 border-b border-gray-800">Códigos de error</h2>
      <table class="w-full text-sm border border-gray-800 rounded-xl overflow-hidden">
        <thead class="bg-gray-800"><tr>
          <th class="px-4 py-2 text-left text-gray-300">HTTP</th>
          <th class="px-4 py-2 text-left text-gray-300">code</th>
          <th class="px-4 py-2 text-left text-gray-300">Descripción</th>
        </tr></thead>
        <tbody class="divide-y divide-gray-800 bg-gray-900">
          <tr><td class="px-4 py-2 text-gray-400">401</td><td class="px-4 py-2 font-mono text-red-400 text-xs">AUTH_REQUIRED</td><td class="px-4 py-2 text-gray-400">No se envió el token</td></tr>
          <tr><td class="px-4 py-2 text-gray-400">403</td><td class="px-4 py-2 font-mono text-red-400 text-xs">AUTH_INVALID</td><td class="px-4 py-2 text-gray-400">Token inválido o expirado</td></tr>
          <tr><td class="px-4 py-2 text-gray-400">403</td><td class="px-4 py-2 font-mono text-red-400 text-xs">PLAN_RESTRICTION</td><td class="px-4 py-2 text-gray-400">Tu plan no incluye este tipo de consulta</td></tr>
          <tr><td class="px-4 py-2 text-gray-400">400</td><td class="px-4 py-2 font-mono text-red-400 text-xs">INVALID_FORMAT</td><td class="px-4 py-2 text-gray-400">El número no tiene el formato correcto</td></tr>
          <tr><td class="px-4 py-2 text-gray-400">429</td><td class="px-4 py-2 font-mono text-red-400 text-xs">RATE_LIMIT</td><td class="px-4 py-2 text-gray-400">Límite diario alcanzado</td></tr>
          <tr><td class="px-4 py-2 text-gray-400">503</td><td class="px-4 py-2 font-mono text-red-400 text-xs">SOURCE_ERROR</td><td class="px-4 py-2 text-gray-400">SUNAT/RENIEC no disponible</td></tr>
        </tbody>
      </table>
    </section>

    <!-- cURL -->
    <section id="curl" class="mb-12">
      <h2 class="text-2xl font-bold text-white mb-4 pb-2 border-b border-gray-800">Ejemplos — cURL</h2>
      <div class="bg-gray-900 rounded-xl border border-gray-800 overflow-hidden mb-4">
        <div class="px-4 py-2 bg-gray-800 text-xs text-gray-400">Consulta RUC</div>
        <pre class="p-4 overflow-x-auto"><code class="language-bash">curl -X GET "<?= $baseUrl ?>/api/v1/ruc/20131312955" \
  -H "Authorization: Bearer TU_API_TOKEN" \
  -H "Accept: application/json"</code></pre>
      </div>
      <div class="bg-gray-900 rounded-xl border border-gray-800 overflow-hidden">
        <div class="px-4 py-2 bg-gray-800 text-xs text-gray-400">Consulta DNI</div>
        <pre class="p-4 overflow-x-auto"><code class="language-bash">curl -X GET "<?= $baseUrl ?>/api/v1/dni/48004836" \
  -H "Authorization: Bearer TU_API_TOKEN"</code></pre>
      </div>
    </section>

    <!-- PHP -->
    <section id="php" class="mb-12">
      <h2 class="text-2xl font-bold text-white mb-4 pb-2 border-b border-gray-800">Ejemplos — PHP</h2>
      <div class="bg-gray-900 rounded-xl border border-gray-800 overflow-hidden mb-4">
        <div class="px-4 py-2 bg-gray-800 text-xs text-gray-400">Con file_get_contents</div>
        <pre class="p-4 overflow-x-auto"><code class="language-php">&lt;?php
$token = 'TU_API_TOKEN';
$ruc   = '20131312955';

$ctx = stream_context_create([
    'http' => [
        'header' => "Authorization: Bearer $token\r\n",
        'method' => 'GET',
    ]
]);

$json = file_get_contents("<?= $baseUrl ?>/api/v1/ruc/$ruc", false, $ctx);
$data = json_decode($json, true);

echo $data['razon_social']; // SUNAT
echo $data['estado'];       // ACTIVO</code></pre>
      </div>
      <div class="bg-gray-900 rounded-xl border border-gray-800 overflow-hidden">
        <div class="px-4 py-2 bg-gray-800 text-xs text-gray-400">Con cURL</div>
        <pre class="p-4 overflow-x-auto"><code class="language-php">&lt;?php
function consultarRuc(string $ruc, string $token): array {
    $ch = curl_init("<?= $baseUrl ?>/api/v1/ruc/$ruc");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ["Authorization: Bearer $token"],
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    return json_decode($resp, true);
}

$data = consultarRuc('20131312955', 'TU_API_TOKEN');
echo $data['razon_social'];</code></pre>
      </div>
    </section>

    <!-- JavaScript -->
    <section id="javascript" class="mb-12">
      <h2 class="text-2xl font-bold text-white mb-4 pb-2 border-b border-gray-800">Ejemplos — JavaScript</h2>
      <div class="bg-gray-900 rounded-xl border border-gray-800 overflow-hidden mb-4">
        <div class="px-4 py-2 bg-gray-800 text-xs text-gray-400">Fetch API (browser / Node.js)</div>
        <pre class="p-4 overflow-x-auto"><code class="language-javascript">const TOKEN = 'TU_API_TOKEN';

async function consultarRuc(ruc) {
  const res = await fetch(`<?= $baseUrl ?>/api/v1/ruc/${ruc}`, {
    headers: { 'Authorization': `Bearer ${TOKEN}` }
  });
  if (!res.ok) throw new Error(`Error ${res.status}`);
  return await res.json();
}

// Uso
const data = await consultarRuc('20131312955');
console.log(data.razon_social); // SUNAT
console.log(data.estado);       // ACTIVO</code></pre>
      </div>
      <div class="bg-gray-900 rounded-xl border border-gray-800 overflow-hidden">
        <div class="px-4 py-2 bg-gray-800 text-xs text-gray-400">Axios (Node.js / browser)</div>
        <pre class="p-4 overflow-x-auto"><code class="language-javascript">import axios from 'axios';

const api = axios.create({
  baseURL: '<?= $baseUrl ?>/api/v1',
  headers: { Authorization: 'Bearer TU_API_TOKEN' }
});

const { data } = await api.get('/ruc/20131312955');
console.log(data.razon_social);</code></pre>
      </div>
    </section>

    <!-- Python -->
    <section id="python" class="mb-12">
      <h2 class="text-2xl font-bold text-white mb-4 pb-2 border-b border-gray-800">Ejemplos — Python</h2>
      <div class="bg-gray-900 rounded-xl border border-gray-800 overflow-hidden">
        <div class="px-4 py-2 bg-gray-800 text-xs text-gray-400">requests</div>
        <pre class="p-4 overflow-x-auto"><code class="language-python">import requests

TOKEN   = 'TU_API_TOKEN'
BASE    = '<?= $baseUrl ?>/api/v1'
headers = {'Authorization': f'Bearer {TOKEN}'}

# Consultar RUC
r = requests.get(f'{BASE}/ruc/20131312955', headers=headers)
r.raise_for_status()
data = r.json()
print(data['razon_social'])  # SUNAT

# Consultar DNI
r = requests.get(f'{BASE}/dni/48004836', headers=headers)
data = r.json()
print(data['nombre_completo'])</code></pre>
      </div>
    </section>

    <!-- Planes -->
    <section id="planes" class="mb-12">
      <h2 class="text-2xl font-bold text-white mb-4 pb-2 border-b border-gray-800">Planes disponibles</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <?php
        $planes = [
          ['Trial','S/ 0','20/día','RUC only','bg-gray-800'],
          ['Básico','S/ 49.90/mes','200/día','RUC only','bg-gray-800'],
          ['Pro','S/ 89.90/mes','1,000/día','RUC + DNI','bg-red-950 border-red-800'],
          ['Enterprise','S/ 199.90/mes','5,000/día','RUC + DNI + Bulk','bg-gray-800'],
        ];
        foreach($planes as $p): ?>
        <div class="<?= $p[4] ?> border border-gray-700 rounded-xl p-4">
          <div class="font-bold text-white mb-1"><?= $p[0] ?></div>
          <div class="text-2xl font-bold text-red-400 mb-2"><?= $p[1] ?></div>
          <div class="text-xs text-gray-400"><?= $p[2] ?><br><?= $p[3] ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <p class="text-gray-500 text-sm mt-4">Contacta a <a href="mailto:<?= ADMIN_EMAIL ?>" class="text-red-400 hover:underline"><?= ADMIN_EMAIL ?></a> para adquirir un plan.</p>
    </section>

  </main>
</div>

<script>document.addEventListener('DOMContentLoaded', () => hljs.highlightAll());</script>
</body>
</html>
