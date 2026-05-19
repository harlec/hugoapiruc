<?php
// admin/plans.php — Gestión de planes
require_once __DIR__ . '/_layout.php';

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $db->prepare("
            INSERT INTO plans (name, price_soles, queries_per_day, queries_per_mo, cache_ttl_hours, features)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([
            trim($_POST['name']),
            (float)$_POST['price'],
            (int)$_POST['qday'],
            (int)$_POST['qmo'],
            (int)$_POST['ttl'],
            json_encode([
                'ruc'  => isset($_POST['ruc']),
                'dni'  => isset($_POST['dni']),
                'bulk' => isset($_POST['bulk']),
            ]),
        ]);
        $msg = 'Plan creado correctamente.';
    }

    if ($action === 'update' && isset($_POST['id'])) {
        $db->prepare("
            UPDATE plans SET price_soles=?, queries_per_day=?, queries_per_mo=?, cache_ttl_hours=?
            WHERE id=?
        ")->execute([
            (float)$_POST['price'],
            (int)$_POST['qday'],
            (int)$_POST['qmo'],
            (int)$_POST['ttl'],
            (int)$_POST['id'],
        ]);
        $msg = 'Plan actualizado correctamente.';
    }

    if ($action === 'toggle' && isset($_POST['id'])) {
        $db->prepare("UPDATE plans SET is_active = NOT is_active WHERE id = ?")
           ->execute([(int)$_POST['id']]);
        $msg = 'Estado del plan actualizado.';
    }
}

$plans = $db->query("
    SELECT p.*, COUNT(t.id) AS tenant_count
    FROM plans p LEFT JOIN tenants t ON t.plan_id = p.id
    GROUP BY p.id ORDER BY p.price_soles
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planes — PERÚdata Admin</title>
    <style>
        .plan-card { transition: box-shadow 0.15s; }
        .plan-card:hover { box-shadow: var(--shadow-md); }
        .feature-yes { color: var(--accent); font-size:13px; }
        .feature-no  { color: var(--text-3); font-size:13px; }
        .modal-overlay { position:fixed;inset:0;background:rgba(0,0,0,0.45);display:flex;align-items:center;justify-content:center;z-index:100; }
        .form-input {
            width:100%;background:var(--bg-hover);border:1px solid var(--border);
            color:var(--text-1);border-radius:8px;padding:7px 10px;font-size:13px;outline:none;
            transition:border-color 0.15s;
        }
        .form-input:focus { border-color:var(--accent); }
        .form-label { font-size:11px;font-weight:600;color:var(--text-2);display:block;margin-bottom:4px; }
        .btn-primary {
            background:var(--accent);color:#fff;border:none;border-radius:8px;
            padding:8px 18px;font-size:13px;font-weight:600;cursor:pointer;transition:opacity 0.15s;
        }
        .btn-primary:hover { opacity:0.88; }
        .btn-secondary {
            background:var(--bg-hover);color:var(--text-2);border:1px solid var(--border);
            border-radius:8px;padding:8px 18px;font-size:13px;font-weight:600;cursor:pointer;
            transition:background 0.15s;
        }
        .btn-secondary:hover { background:var(--border); }
        .btn-sm {
            background:var(--bg-hover);color:var(--text-2);border:1px solid var(--border);
            border-radius:6px;padding:4px 10px;font-size:12px;cursor:pointer;transition:all 0.15s;
        }
        .btn-sm:hover { border-color:var(--accent);color:var(--accent); }
    </style>
</head>
<body>
<?php include __DIR__ . '/_sidebar.php'; ?>

<div class="main-wrapper">
    <header class="topbar">
        <h1 style="font-size:18px;font-weight:700;color:var(--text-1);flex:1;">Planes</h1>
        <button onclick="document.getElementById('modalPlan').style.display='flex'"
            class="btn-primary">+ Nuevo plan</button>
    </header>

    <main class="main-content">

        <?php if ($msg): ?>
        <div style="margin-bottom:16px;padding:10px 16px;background:rgba(13,148,136,0.1);border:1px solid var(--accent);border-radius:10px;font-size:13px;color:var(--accent);">
            <?= htmlspecialchars($msg) ?>
        </div>
        <?php endif; ?>

        <!-- Cards de planes -->
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px;margin-bottom:28px;">
            <?php foreach ($plans as $plan): ?>
            <?php $features = json_decode($plan['features'], true) ?? []; ?>
            <div class="card plan-card" style="padding:20px;<?= !$plan['is_active'] ? 'opacity:0.6;' : '' ?>">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px;">
                    <h3 style="font-weight:700;font-size:16px;color:var(--text-1);"><?= htmlspecialchars($plan['name']) ?></h3>
                    <?php if (!$plan['is_active']): ?>
                    <span class="pill" style="background:#fee2e2;color:#dc2626;font-size:10px;">inactivo</span>
                    <?php else: ?>
                    <span class="pill" style="background:var(--accent-bg);color:var(--accent);font-size:10px;">activo</span>
                    <?php endif; ?>
                </div>
                <div style="font-size:26px;font-weight:700;color:var(--accent);margin-bottom:16px;">
                    S/ <?= number_format($plan['price_soles'], 2) ?>
                    <span style="font-size:12px;color:var(--text-3);font-weight:400;">/mes</span>
                </div>
                <div style="display:flex;flex-direction:column;gap:6px;font-size:13px;margin-bottom:14px;">
                    <div style="display:flex;justify-content:space-between;">
                        <span style="color:var(--text-2);">Consultas/día</span>
                        <span style="font-weight:600;color:var(--text-1);"><?= number_format($plan['queries_per_day']) ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;">
                        <span style="color:var(--text-2);">Consultas/mes</span>
                        <span style="font-weight:600;color:var(--text-1);"><?= number_format($plan['queries_per_mo']) ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;">
                        <span style="color:var(--text-2);">Caché TTL</span>
                        <span style="font-weight:600;color:var(--text-1);"><?= $plan['cache_ttl_hours'] ?>h</span>
                    </div>
                </div>
                <div style="display:flex;flex-direction:column;gap:4px;padding-top:12px;border-top:1px solid var(--border);margin-bottom:14px;">
                    <span class="<?= ($features['ruc'] ?? false) ? 'feature-yes' : 'feature-no' ?>">
                        <?= ($features['ruc'] ?? false) ? '✓' : '–' ?> RUC SUNAT
                    </span>
                    <span class="<?= ($features['dni'] ?? false) ? 'feature-yes' : 'feature-no' ?>">
                        <?= ($features['dni'] ?? false) ? '✓' : '–' ?> DNI RENIEC
                    </span>
                    <span class="<?= ($features['bulk'] ?? false) ? 'feature-yes' : 'feature-no' ?>">
                        <?= ($features['bulk'] ?? false) ? '✓' : '–' ?> Consultas masivas
                    </span>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <span style="font-size:12px;color:var(--text-3);"><?= $plan['tenant_count'] ?> tenants</span>
                    <form method="POST" style="margin:0;">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="<?= $plan['id'] ?>">
                        <button class="btn-sm"><?= $plan['is_active'] ? 'Desactivar' : 'Activar' ?></button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Tabla editable de planes -->
        <div class="card" style="overflow:hidden;">
            <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;">
                <h2 style="font-size:13px;font-weight:600;color:var(--text-1);">Editar límites y precios</h2>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th style="text-align:right;">Precio (S/)</th>
                        <th style="text-align:right;">Consultas/día</th>
                        <th style="text-align:right;">Consultas/mes</th>
                        <th style="text-align:right;">TTL caché (h)</th>
                        <th style="text-align:center;">Activo</th>
                        <th style="text-align:center;">Guardar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($plans as $plan): ?>
                    <tr>
                        <form method="POST">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" value="<?= $plan['id'] ?>">
                        <td style="font-weight:600;"><?= htmlspecialchars($plan['name']) ?></td>
                        <td style="text-align:right;">
                            <input type="number" name="price" step="0.01" min="0"
                                value="<?= $plan['price_soles'] ?>"
                                class="form-input" style="width:90px;text-align:right;">
                        </td>
                        <td style="text-align:right;">
                            <input type="number" name="qday" min="1"
                                value="<?= $plan['queries_per_day'] ?>"
                                class="form-input" style="width:90px;text-align:right;">
                        </td>
                        <td style="text-align:right;">
                            <input type="number" name="qmo" min="1"
                                value="<?= $plan['queries_per_mo'] ?>"
                                class="form-input" style="width:100px;text-align:right;">
                        </td>
                        <td style="text-align:right;">
                            <input type="number" name="ttl" min="1"
                                value="<?= $plan['cache_ttl_hours'] ?>"
                                class="form-input" style="width:70px;text-align:right;">
                        </td>
                        <td style="text-align:center;">
                            <span class="pill" style="<?= $plan['is_active']
                                ? 'background:var(--accent-bg);color:var(--accent);'
                                : 'background:#fee2e2;color:#dc2626;' ?>">
                                <?= $plan['is_active'] ? 'Sí' : 'No' ?>
                            </span>
                        </td>
                        <td style="text-align:center;">
                            <button type="submit" class="btn-primary" style="padding:5px 14px;font-size:12px;">Guardar</button>
                        </td>
                        </form>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </main>
</div>

<!-- Modal nuevo plan -->
<div id="modalPlan" class="modal-overlay" style="display:none;">
    <div class="card" style="padding:24px;width:100%;max-width:440px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
            <h2 style="font-size:16px;font-weight:700;color:var(--text-1);">Nuevo Plan</h2>
            <button onclick="document.getElementById('modalPlan').style.display='none'"
                style="background:none;border:none;font-size:20px;color:var(--text-3);cursor:pointer;line-height:1;">&times;</button>
        </div>
        <form method="POST" style="display:flex;flex-direction:column;gap:14px;">
            <input type="hidden" name="action" value="create">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div>
                    <label class="form-label">Nombre</label>
                    <input type="text" name="name" required class="form-input">
                </div>
                <div>
                    <label class="form-label">Precio (S/)</label>
                    <input type="number" name="price" step="0.01" min="0" required class="form-input">
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                <div>
                    <label class="form-label">Cons./día</label>
                    <input type="number" name="qday" min="1" required class="form-input">
                </div>
                <div>
                    <label class="form-label">Cons./mes</label>
                    <input type="number" name="qmo" min="1" required class="form-input">
                </div>
                <div>
                    <label class="form-label">TTL caché (h)</label>
                    <input type="number" name="ttl" min="1" value="24" required class="form-input">
                </div>
            </div>
            <div>
                <label class="form-label" style="margin-bottom:8px;">Características</label>
                <div style="display:flex;gap:16px;">
                    <label style="display:flex;align-items:center;gap:6px;font-size:13px;color:var(--text-1);cursor:pointer;">
                        <input type="checkbox" name="ruc" checked style="accent-color:var(--accent);"> RUC
                    </label>
                    <label style="display:flex;align-items:center;gap:6px;font-size:13px;color:var(--text-1);cursor:pointer;">
                        <input type="checkbox" name="dni" style="accent-color:var(--accent);"> DNI
                    </label>
                    <label style="display:flex;align-items:center;gap:6px;font-size:13px;color:var(--text-1);cursor:pointer;">
                        <input type="checkbox" name="bulk" style="accent-color:var(--accent);"> Bulk
                    </label>
                </div>
            </div>
            <div style="display:flex;gap:10px;padding-top:6px;">
                <button type="submit" class="btn-primary" style="flex:1;">Crear plan</button>
                <button type="button" onclick="document.getElementById('modalPlan').style.display='none'"
                    class="btn-secondary" style="flex:1;">Cancelar</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>
