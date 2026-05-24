<?php
namespace App\Scrapers;

/**
 * BackupScraper — fuente primaria para RUC via apis.net.pe (JSON API).
 * Intenta v2 primero, cae a v1 si falla.
 * Para DNI actúa solo como fallback final (DniScraper tiene su propia cascada).
 */
class BackupScraper
{
    const BASE_V1  = 'https://api.apis.net.pe/v1';
    const BASE_V2  = 'https://api.apis.net.pe/v2';
    const TIMEOUT  = 12;

    public function query(string $type, string $value): array
    {
        $apiKey = $_ENV['BACKUP_API_KEY'] ?? '';

        if ($type === 'ruc') {
            return $this->queryRuc($value, $apiKey);
        }

        return $this->queryDni($value, $apiKey);
    }

    // ── RUC: intenta v2, fallback a v1 ───────────────────────────────────────
    private function queryRuc(string $ruc, string $apiKey): array
    {
        // Intento 1: v2
        try {
            $result = $this->fetch(self::BASE_V2 . '/ruc?numero=' . $ruc, $apiKey);
            if ($result && !isset($result['error']) && !empty($result['razonSocial'] ?? $result['nombre'] ?? '')) {
                return ['success' => true, 'data' => $this->normalizeRuc($result, $ruc), 'source' => 'APIS_NET_V2'];
            }
        } catch (\Throwable $e) {
            error_log('[BackupScraper] RUC v2: ' . $e->getMessage());
        }

        // Intento 2: v1
        $result = $this->fetch(self::BASE_V1 . '/ruc?numero=' . $ruc, $apiKey);
        if (!$result || isset($result['error'])) {
            return ['success' => false, 'data' => null, 'source' => 'APIS_NET'];
        }

        return ['success' => true, 'data' => $this->normalizeRuc($result, $ruc), 'source' => 'APIS_NET_V1'];
    }

    // ── DNI: solo v1 (DniScraper ya cubre v2) ────────────────────────────────
    private function queryDni(string $dni, string $apiKey): array
    {
        $result = $this->fetch(self::BASE_V1 . '/dni?numero=' . $dni, $apiKey);
        if (!$result || isset($result['error'])) {
            return ['success' => false, 'data' => null, 'source' => 'APIS_NET'];
        }

        return ['success' => true, 'data' => $this->normalizeDni($result, $dni), 'source' => 'APIS_NET_V1'];
    }

    // ── HTTP fetch ────────────────────────────────────────────────────────────
    private function fetch(string $url, string $apiKey): ?array
    {
        $headers = ['User-Agent: PERUdata-API/1.0', 'Accept: application/json'];
        if ($apiKey) {
            $headers[] = "Authorization: Bearer $apiKey";
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200 || empty($body)) {
            throw new \Exception("BackupScraper HTTP $code para $url");
        }

        return json_decode($body, true) ?: null;
    }

    // ── Normalizadores ────────────────────────────────────────────────────────
    private function normalizeRuc(array $j, string $ruc): array
    {
        return [
            'ruc'           => $ruc,
            'razon_social'  => $j['razonSocial']       ?? $j['nombre']            ?? '',
            'tipo_contribu' => $j['tipoContribuyente'] ?? $j['tipo_contribuyente'] ?? '',
            'estado'        => $j['estado']             ?? '',
            'condicion'     => $j['condicion']          ?? '',
            'direccion'     => $j['direccion']          ?? '',
            'departamento'  => $j['departamento']       ?? '',
            'provincia'     => $j['provincia']          ?? '',
            'distrito'      => $j['distrito']           ?? '',
            'ubigeo'        => $j['ubigeo']             ?? $j['codigoUbigeo']      ?? '',
            'actividad'     => $j['actividadEconomica'] ?? $j['actividad']         ?? '',
        ];
    }

    private function normalizeDni(array $j, string $dni): array
    {
        $nombres     = $j['nombres']     ?? '';
        $apellidoPat = $j['apellidoPat'] ?? $j['apellidoPaterno'] ?? '';
        $apellidoMat = $j['apellidoMat'] ?? $j['apellidoMaterno'] ?? '';

        return [
            'dni'             => $dni,
            'nombres'         => $nombres,
            'apellido_pat'    => $apellidoPat,
            'apellido_mat'    => $apellidoMat,
            'nombre_completo' => trim("$apellidoPat $apellidoMat $nombres"),
        ];
    }
}
