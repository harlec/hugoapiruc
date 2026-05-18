<?php
namespace App\Monitor;

class AlertSender
{
    private string  $adminEmail;
    private ?string $slackWebhook;
    private string  $fromEmail;

    public function __construct()
    {
        $this->adminEmail   = $_ENV['ADMIN_EMAIL']    ?? 'admin@perudata.pe';
        $this->slackWebhook = $_ENV['SLACK_WEBHOOK']  ?? null;
        $this->fromEmail    = 'monitor@' . ($_ENV['APP_DOMAIN'] ?? 'perudata.pe');
    }

    public function sendAlert(string $source, string $status, string $error): void
    {
        $subject = "[PERUdata] ALERTA: Fuente '$source' falló ($status)";
        $body    = "La fuente $source está en estado '$status'.\n\nError: $error\n\nFecha: " . date('Y-m-d H:i:s T');

        mail($this->adminEmail, $subject, $body, "From: {$this->fromEmail}\r\nContent-Type: text/plain; charset=UTF-8");

        if ($this->slackWebhook) {
            $this->postToSlack(":rotating_light: *Fuente caída:* `$source` — Estado: `$status`\n```$error```");
        }
    }

    public function sendRecovery(string $source): void
    {
        $subject = "[PERUdata] RECUPERADO: '$source' volvió a funcionar";
        $body    = "La fuente $source está operativa nuevamente.\n\nFecha: " . date('Y-m-d H:i:s T');

        mail($this->adminEmail, $subject, $body, "From: {$this->fromEmail}\r\nContent-Type: text/plain; charset=UTF-8");

        if ($this->slackWebhook) {
            $this->postToSlack(":white_check_mark: *Fuente recuperada:* `$source` — operativa a las " . date('H:i:s T'));
        }
    }

    private function postToSlack(string $message): void
    {
        $ch = curl_init($this->slackWebhook);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode(['text' => $message]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
}
