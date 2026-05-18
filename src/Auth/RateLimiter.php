<?php
namespace App\Auth;

use App\DB;

class RateLimiter
{
    private array $tenant;
    private int   $usedToday;

    public function __construct(array $tenant)
    {
        $this->tenant    = $tenant;
        $this->usedToday = $this->countToday();
    }

    /**
     * Verifica si el tenant puede hacer una consulta más.
     */
    public function allow(): bool
    {
        return $this->usedToday < $this->tenant['queries_per_day'];
    }

    /**
     * Registra una consulta (incrementa el contador).
     */
    public function increment(): void
    {
        $this->usedToday++;
    }

    /**
     * Timestamp de reset del límite diario (medianoche Lima).
     */
    public function resetAt(): string
    {
        return date('Y-m-d') . ' 23:59:59';
    }

    public function remaining(): int
    {
        return max(0, $this->tenant['queries_per_day'] - $this->usedToday);
    }

    private function countToday(): int
    {
        $stmt = DB::connection()->prepare("
            SELECT COUNT(*) FROM usage_log
            WHERE tenant_id = ?
              AND created_at >= CURDATE()
              AND status != 'rate_limit'
        ");
        $stmt->execute([$this->tenant['id']]);
        return (int) $stmt->fetchColumn();
    }
}
