<?php
namespace App\Cache;

use App\DB;
use PDO;

class QueryCache
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = DB::connection();
    }

    public function get(string $type, string $value): ?array
    {
        $stmt = $this->db->prepare("
            SELECT response, source_used, created_at, hits
            FROM query_cache
            WHERE query_type = ? AND query_value = ?
              AND expires_at > NOW()
        ");
        $stmt->execute([$type, $value]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) return null;

        // Incrementar hits en background (ignorar error)
        try {
            $this->db->prepare(
                "UPDATE query_cache SET hits = hits + 1 WHERE query_type = ? AND query_value = ?"
            )->execute([$type, $value]);
        } catch (\Throwable) {}

        $data = json_decode($row['response'], true);
        $data['_cached_at']  = $row['created_at'];
        $data['_cache_hits'] = $row['hits'] + 1;
        return $data;
    }

    public function set(string $type, string $value, array $data, string $source, int $ttlHours = 24): void
    {
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$ttlHours} hours"));

        $this->db->prepare("
            INSERT INTO query_cache (query_type, query_value, response, source_used, expires_at)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                response    = VALUES(response),
                source_used = VALUES(source_used),
                expires_at  = VALUES(expires_at),
                hits        = 1,
                created_at  = NOW()
        ")->execute([$type, $value, json_encode($data, JSON_UNESCAPED_UNICODE), $source, $expiresAt]);
    }

    public function invalidate(string $type, string $value): void
    {
        $this->db->prepare(
            "DELETE FROM query_cache WHERE query_type = ? AND query_value = ?"
        )->execute([$type, $value]);
    }

    public function stats(): array
    {
        return $this->db->query("
            SELECT
                COUNT(*) AS total_cached,
                SUM(hits) AS total_hits,
                ROUND(AVG(hits), 1) AS avg_hits,
                COUNT(CASE WHEN expires_at < NOW() THEN 1 END) AS expired
            FROM query_cache
        ")->fetch(PDO::FETCH_ASSOC);
    }

    public function cleanup(): int
    {
        return $this->db->exec("DELETE FROM query_cache WHERE expires_at < NOW()");
    }
}
