<?php
namespace App\Auth;

use App\DB;

class TokenManager
{
    /**
     * Extrae el Bearer token del header Authorization.
     */
    public static function fromRequest(): ?string
    {
        // Apache + PHP-FPM puede entregar el header en distintas variables
        $header = $_SERVER['HTTP_AUTHORIZATION']           // estándar
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']     // tras RewriteRule
            ?? (function_exists('apache_request_headers')
                ? (apache_request_headers()['Authorization']
                   ?? apache_request_headers()['authorization']
                   ?? '')
                : '');

        if (preg_match('/^Bearer\s+(.+)$/i', trim($header), $m)) {
            return trim($m[1]);
        }
        return null;
    }

    /**
     * Valida el token y retorna los datos del tenant con su plan.
     * Retorna null si el token es inválido o el tenant está suspendido.
     */
    public static function validate(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/i', $token)) {
            return null;
        }

        $stmt = DB::connection()->prepare("
            SELECT t.*, p.queries_per_day, p.queries_per_mo,
                   p.cache_ttl_hours, p.features, p.name AS plan_name
            FROM tenants t
            JOIN plans p ON p.id = t.plan_id
            WHERE t.api_token = ?
              AND t.status IN ('active', 'trial')
              AND (t.expires_at IS NULL OR t.expires_at > NOW())
        ");
        $stmt->execute([$token]);
        $row = $stmt->fetch();

        if (!$row) return null;

        // Decodificar features del plan
        $row['features'] = json_decode($row['features'], true);
        return $row;
    }

    /**
     * Genera un nuevo token seguro de 64 caracteres hex.
     */
    public static function generate(): string
    {
        return hash('sha256', uniqid(random_bytes(16), true) . microtime());
    }
}
