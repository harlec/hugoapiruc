<?php
// admin/tenants.php — Gestión de clientes multi-tenant
require_once __DIR__ . '/_layout.php';

use App\Auth\TokenManager;

$msg = '';
$err = '';

// ── Acciones POST ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name    = trim($_POST['name'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $planId  = (int)($_POST['plan_id'] ?? 1);
        $status  = $_POST['status'] ?? 'trial';
        $expires = $_POST['expires_at'] ?: null;
        $token   = TokenManager::generate();

        try {
            $db->prepare("
                INSERT INTO tenants (name, email, api_token, plan_id, status, expires_at)
                VALUES (?, ?, ?, ?, ?, ?)
            ")->execute([$name, $email, $token, $planId, $status, $expires]);
            $msg = "Tenant creado. Token: <code style='font-family:monospace;background:var(--bg-hover);padding:2px 6px;border-radius:6px;font-size:12px;border:1px solid var(--border);'>$token</code>";
        } catch (\Exception $e) {
            $err = 'Error: ' . $e->getMessage();
        }
    }

    if ($action === 'suspend' && isset($_POST['id'])) {
        $db->prepare("UPDATE tenants SET status = 'suspended' WHERE id = ?")
           ->execute([(int)$_POST['id']]);
        $msg = 'Tenant suspendido.';
    }

    if ($action === 'activate' && isset($_POST['id'])) {
        $db->prepare("UPDATE tenants SET status = 'active' WHERE id = ?")
           ->execute([(int)$_POST['id']]);
        $msg = 'Tenant activado.';
    }

    if ($action === 'regen_token' && isset($_POST['id'])) {
        $new = TokenManager::generate();
        $db->prepare("UPDATE tenants SET api_token = ? WHERE id = ?")
           ->execute([$new, (int)$_POST['id']]);
        $msg = "Token regenerado: <code style='font-family:monospace;background:var(--bg-hover);padding:2px 6px;border-radius:6px;font-size:12px;border:1px solid var(--border);'>$new</code>";
    }
}

// ── Datos ─────────────────────────────────────────────────────────────────────
$tenants = $db->query("
    SELECT t.*, p.name AS plan_name,
        (SELECT COUNT(*) FROM usage_log u WHERE u.tenant_id = t.id AND DATE(u.created_at) = CURDATE()) AS today_uses
    FROM tenants t JOIN plans p ON p.id = t.plan_id
    ORDER BY t.created_at DESC
")->fetchAll();

$plans = $db->query("SELECT * FROM plans WHERE is_active = 1")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tenants — PERÚdata Admin</title>
</head>
<body>
<?php include __DIR__ . '/_sidebar.php'; ?>

<div class="main-wrapper">
    <header class="topbar">
        <div style="flex:1;">
            <h1 style="font-size:17px;font-weight:700;color:var(--text-1);line-height:1.2;">Tenants</h1>
            <p style="font-size:12px;color:var(--text-2);margin-top:1px;">Gestión de clientes y accesos API</p>
        </div>
        <button onclick="document.getElementById('modalCreate').style.display='flex'"
            style="background:var(--accent);color:#fff;border:none;border-radius:10px;padding:8px 16px;font-size:13px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px;">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Nuevo Tenant
        </button>
    </header>

    <main class="main-content">

        <?php if ($msg): ?>
        <div style="margin-bottom:16px;padding:12px 16px;background:rgba(13,148,136,0.08);border:1px solid rgba(13,148,136,0.25);border-radius:10px;color:var(--accent);font-size:13px;">
            <?= $msg ?>
        </div>
        <?php endif; ?>
        <?php if ($err): ?>
        <div style="margin-bottom:16px;padding:12px 16px;background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.25);border-radius:10px;color:#ef4444;font-size:13px;">
            <?= htmlspecialchars($err) ?>
        </div>
        <?php endif; ?>

        <!-- Tabla de tenants -->
        <div class="card" style="overflow:hidden;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Nombre / Email</th>
                        <th>Plan</th>
                        <th>Estado</th>
                        <th>Usos hoy</th>
                        <th>Vence</th>
                        <th>Token</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tenants as $t): ?>
                    <?php
                        if ($t['status'] === 'active') {
                            $pillBg = 'rgba(16,185,129,0.1)'; $pillColor = '#10b981'; $pillBorder = 'rgba(16,185,129,0.3)';
                        } elseif ($t['status'] === 'trial') {
                            $pillBg = 'rgba(59,130,246,0.1)'; $pillColor = '#3b82f6'; $pillBorder = 'rgba(59,130,246,0.3)';
                        } else {
                            $pillBg = 'rgba(239,68,68,0.1)'; $pillColor = '#ef4444'; $pillBorder = 'rgba(239,68,68,0.3)';
                        }
                    ?>
                    <tr>
                        <td>
                            <div style="font-weight:600;color:var(--text-1);"><?= htmlspecialchars($t['name']) ?></div>
                            <div style="font-size:12px;color:var(--text-2);margin-top:2px;"><?= htmlspecialchars($t['email']) ?></div>
                        </td>
                        <td style="color:var(--text-2);"><?= htmlspecialchars($t['plan_name']) ?></td>
                        <td>
                            <span class="pill" style="background:<?= $pillBg ?>;color:<?= $pillColor ?>;border:1px solid <?= $pillBorder ?>;">
                                <?= $t['status'] ?>
                            </span>
                        </td>
                        <td style="font-weight:600;"><?= number_format($t['today_uses']) ?></td>
                        <td style="font-size:12px;color:var(--text-2);">
                            <?= $t['expires_at'] ? date('d/m/Y', strtotime($t['expires_at'])) : '—' ?>
                        </td>
                        <td>
                            <span style="font-family:monospace;font-size:11px;color:var(--text-2);cursor:pointer;padding:3px 8px;background:var(--bg-hover);border:1px solid var(--border);border-radius:6px;"
                                title="<?= htmlspecialchars($t['api_token']) ?>"
                                onclick="navigator.clipboard.writeText('<?= htmlspecialchars($t['api_token']) ?>').then(()=>alert('Token copiado'))">
                                <?= substr($t['api_token'], 0, 12) ?>...
                            </span>
                        </td>
                        <td>
                            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                                <?php if ($t['status'] === 'suspended'): ?>
                                <form method="POST" style="margin:0;">
                                    <input type="hidden" name="action" value="activate">
                                    <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                    <button style="background:none;border:none;cursor:pointer;font-size:12px;font-weight:600;color:#10b981;padding:0;">Activar</button>
                                </form>
                                <?php elseif ($t['status'] !== 'suspended'): ?>
                                <form method="POST" style="margin:0;">
                                    <input type="hidden" name="action" value="suspend">
                                    <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                    <button style="background:none;border:none;cursor:pointer;font-size:12px;font-weight:600;color:#ef4444;padding:0;">Suspender</button>
                                </form>
                                <?php endif; ?>
                                <form method="POST" style="margin:0;" onsubmit="return confirm('¿Regenerar token? El anterior dejará de funcionar.')">
                                    <input type="hidden" name="action" value="regen_token">
                                    <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                    <button style="background:none;border:none;cursor:pointer;font-size:12px;font-weight:600;color:#f59e0b;padding:0;">Regen token</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($tenants)): ?>
                    <tr>
                        <td colspan="7" style="text-align:center;color:var(--text-3);padding:40px;">No hay tenants registrados aún.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </main>
</div>

<!-- Modal crear tenant -->
<div id="modalCreate" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);align-items:center;justify-content:center;z-index:1000;">
    <div class="card" style="width:100%;max-width:460px;padding:24px;box-shadow:0 20px 60px rgba(0,0,0,0.2);">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
            <h2 style="font-size:16px;font-weight:700;color:var(--text-1);">Nuevo Tenant</h2>
            <button onclick="document.getElementById('modalCreate').style.display='none'"
                style="background:none;border:none;cursor:pointer;color:var(--text-2);font-size:20px;line-height:1;">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div style="margin-bottom:14px;">
                <label style="display:block;font-size:12px;font-weight:600;color:var(--text-2);margin-bottom:5px;">Nombre empresa / cliente</label>
                <input type="text" name="name" required
                    style="width:100%;background:var(--bg-hover);border:1px solid var(--border);border-radius:8px;padding:9px 12px;font-size:13px;color:var(--text-1);outline:none;"
                    onfocus="this.style.borderColor='var(--accent)'" onblur="this.style.borderColor='var(--border)'">
            </div>
            <div style="margin-bottom:14px;">
                <label style="display:block;font-size:12px;font-weight:600;color:var(--text-2);margin-bottom:5px;">Email</label>
                <input type="email" name="email" required
                    style="width:100%;background:var(--bg-hover);border:1px solid var(--border);border-radius:8px;padding:9px 12px;font-size:13px;color:var(--text-1);outline:none;"
                    onfocus="this.style.borderColor='var(--accent)'" onblur="this.style.borderColor='var(--border)'">
            </div>
            <div style="margin-bottom:14px;">
                <label style="display:block;font-size:12px;font-weight:600;color:var(--text-2);margin-bottom:5px;">Plan</label>
                <select name="plan_id"
                    style="width:100%;background:var(--bg-hover);border:1px solid var(--border);border-radius:8px;padding:9px 12px;font-size:13px;color:var(--text-1);outline:none;">
                    <?php foreach ($plans as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?> — S/ <?= $p['price_soles'] ?>/mes</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:20px;">
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;color:var(--text-2);margin-bottom:5px;">Estado</label>
                    <select name="status"
                        style="width:100%;background:var(--bg-hover);border:1px solid var(--border);border-radius:8px;padding:9px 12px;font-size:13px;color:var(--text-1);outline:none;">
                        <option value="trial">Trial</option>
                        <option value="active">Activo</option>
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;color:var(--text-2);margin-bottom:5px;">Vence el (opcional)</label>
                    <input type="date" name="expires_at"
                        style="width:100%;background:var(--bg-hover);border:1px solid var(--border);border-radius:8px;padding:9px 12px;font-size:13px;color:var(--text-1);outline:none;">
                </div>
            </div>
            <div style="display:flex;gap:10px;">
                <button type="submit"
                    style="flex:1;background:var(--accent);color:#fff;border:none;border-radius:10px;padding:10px;font-size:13px;font-weight:600;cursor:pointer;">
                    Crear tenant
                </button>
                <button type="button"
                    onclick="document.getElementById('modalCreate').style.display='none'"
                    style="flex:1;background:var(--bg-hover);color:var(--text-1);border:1px solid var(--border);border-radius:10px;padding:10px;font-size:13px;font-weight:600;cursor:pointer;">
                    Cancelar
                </button>
            </div>
        </form>
    </div>
</div>

</body>
</html>
