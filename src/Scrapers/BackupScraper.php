<?php
namespace App\Scrapers;

class BackupScraper
{
    const BASE_URL = 'https://api.apis.net.pe/v1';
    const TIMEOUT  = 10;

    public function query(string $type, string $value): array
    {
        $apiKey = $_ENV['BACKUP_API_KEY'] ?? '';
        $endpoint = $type === 'ruc' ? '/ruc?numero=' : '/dni?numero=';
        $url = self::BASE_URL . $endpoint . urlencode($value);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_HTTPHEADER     => [
                'User-Agent: PERUdata-API/1.0',
                $apiKey ? "Authorization: Bearer $apiKey" : '',
            ],
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200 || empty($body)) {
            throw new \Exception("BackupScraper: HTTP $code");
        }

        $json = json_decode($body, true);
        if (empty($json) || isset($json['error'])) {
            return ['success' => false, 'data' => null, 'source' => 'BACKUP_API'];
        }

        // Normalizar respuesta al formato estándar
        $data = $type === 'ruc' ? $this->normalizeRuc($json, $value) : $this->normalizeDni($json, $value);

        return ['success' => true, 'data' => $data, 'source' => 'BACKUP_API'];
    }

    private function normalizeRuc(array $json, string $ruc): array
    {
        return [
            'ruc'           => $ruc,
            'razon_social'  => $json['razonSocial']      ?? $json['nombre'] ?? '',
            'tipo_contribu' => $json['tipoContribuyente'] ?? '',
            'estado'        => $json['estado']            ?? '',
            'condicion'     => $json['condicion']         ?? '',
            'direccion'     => $json['direccion']         ?? '',
            'departamento'  => $json['departamento']      ?? '',
            'provincia'     => $json['provincia']         ?? '',
            'distrito'      => $json['distrito']          ?? '',
            'ubigeo'        => $json['ubigeo']            ?? '',
            'actividad'     => $json['actividadEconomica'] ?? '',
        ];
    }

    private function normalizeDni(array $json, string $dni): array
    {
        $nombres     = $json['nombres']      ?? '';
        $apellidoPat = $json['apellidoPat']  ?? $json['apellidoPaterno'] ?? '';
        $apellidoMat = $json['apellidoMat']  ?? $json['apellidoMaterno'] ?? '';
        return [
            'dni'            => $dni,
            'nombres'        => $nombres,
            'apellido_pat'   => $apellidoPat,
            'apellido_mat'   => $apellidoMat,
            'nombre_completo'=> trim("$apellidoPat $apellidoMat $nombres"),
        ];
    }
}
