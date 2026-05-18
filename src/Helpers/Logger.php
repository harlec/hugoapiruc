<?php
namespace App\Helpers;

use App\DB;

class Logger
{
    public static function log(
        int    $tenantId,
        string $type,
        string $value,
        bool   $fromCache,
        int    $responseMs,
        string $status
    ): void {
        try {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR']
                ?? $_SERVER['REMOTE_ADDR']
                ?? '0.0.0.0';

            DB::connection()->prepare("
                INSERT INTO usage_log
                    (tenant_id, query_type, query_value, from_cache, response_ms, status, ip_address)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ")->execute([$tenantId, $type, $value, $fromCache ? 1 : 0, $responseMs, $status, $ip]);
        } catch (\Throwable $e) {
            error_log('[Logger] ' . $e->getMessage());
        }
    }
}
