<?php
namespace App\Scrapers;

use App\DB;

/**
 * PadronScraper — consulta el padrón RUC importado localmente desde SUNAT.
 * Respuesta en <5ms, sin dependencia de Internet, sin riesgo de ban.
 * Si el RUC no está en el padrón, devuelve success=false para que
 * ScraperRouter intente la siguiente fuente.
 */
class PadronScraper
{
    public function query(string $type, string $value): array
    {
        if ($type !== 'ruc') {
            return ['success' => false, 'data' => null, 'source' => null];
        }

        if (!$this->tablExists()) {
            return ['success' => false, 'data' => null, 'source' => null];
        }

        $pdo  = DB::connection();
        $stmt = $pdo->prepare(
            'SELECT ruc, razon_social, tipo_contribu, estado, condicion,
                    departamento, provincia, distrito, ubigeo, direccion, actividad
             FROM ruc_padron WHERE ruc = ? LIMIT 1'
        );
        $stmt->execute([$value]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row || empty($row['razon_social'])) {
            return ['success' => false, 'data' => null, 'source' => null];
        }

        return [
            'success' => true,
            'data'    => $row,
            'source'  => 'PADRON_LOCAL',
        ];
    }

    private function tablExists(): bool
    {
        try {
            DB::connection()->query('SELECT 1 FROM ruc_padron LIMIT 1');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
