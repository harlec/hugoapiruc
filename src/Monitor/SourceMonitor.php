<?php
namespace App\Monitor;

use App\DB;
use PDO;

class SourceMonitor
{
    private PDO         $db;
    private AlertSender $alert;

    const FAILURE_THRESHOLD = 3;
    const SLOW_THRESHOLD_MS = 5000;

    public function __construct()
    {
        $this->db    = DB::connection();
        $this->alert = new AlertSender();
    }

    public function checkAll(): void
    {
        $sources = $this->db->query("SELECT * FROM source_monitors")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($sources as $source) {
            $result   = $this->checkSource($source['source_url']);
            $status   = $this->evaluateResult($result);
            $failures = ($status !== 'ok') ? $source['consecutive_failures'] + 1 : 0;

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
                $source['id'],
            ]);

            // Enviar alerta al superar el umbral
            if ($failures >= self::FAILURE_THRESHOLD && !$source['alert_sent']) {
                $this->alert->sendAlert($source['source_name'], $status, $result['error'] ?? '');
            }

            // Notificar recuperación
            if ($status === 'ok' && $source['consecutive_failures'] >= self::FAILURE_THRESHOLD) {
                $this->alert->sendRecovery($source['source_name']);
                $this->db->prepare(
                    "UPDATE source_monitors SET alert_sent = 0 WHERE id = ?"
                )->execute([$source['id']]);
            }
        }
    }

    public function getStatus(): array
    {
        return $this->db->query("SELECT * FROM source_monitors ORDER BY source_name")->fetchAll(PDO::FETCH_ASSOC);
    }

    private function checkSource(string $url): array
    {
        $start = microtime(true);
        $ch    = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER     => ['User-Agent: PERUdata-Monitor/1.0'],
        ]);
        $body  = curl_exec($ch);
        $code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $ms    = (int) round((microtime(true) - $start) * 1000);
        curl_close($ch);

        return [
            'code'  => $code,
            'body'  => $body ?: '',
            'ms'    => $ms,
            'error' => $error ?: null,
        ];
    }

    private function evaluateResult(array $r): string
    {
        if ($r['error'] || $r['code'] < 200 || $r['code'] >= 400) return 'error';
        if ($r['ms'] > self::SLOW_THRESHOLD_MS) return 'slow';
        if ($r['body'] && !str_contains($r['body'], 'RUC') && !str_contains($r['body'], 'nroRuc') && !str_contains($r['body'], 'dni')) {
            return 'changed';
        }
        return 'ok';
    }
}
