<?php
namespace App\Scrapers;

class DniScraper
{
    const TIMEOUT = 12;

    public function query(string $type, string $value): array
    {
        if ($type !== 'dni') return ['success' => false, 'data' => null, 'source' => null];

        // Fuente 1: apis.net.pe (más confiable, no requiere scraping)
        $result = $this->tryApisNetPe($value);
        if ($result['success']) return $result;

        // Fuente 2: apiperu.dev
        $result = $this->tryApiPeru($value);
        if ($result['success']) return $result;

        // Fuente 3: eldni.com (scraping como último recurso)
        $result = $this->tryEldni($value);
        if ($result['success']) return $result;

        return ['success' => false, 'data' => null, 'source' => null];
    }

    /**
     * Fuente 1: api.apis.net.pe — JSON limpio, sin scraping
     */
    private function tryApisNetPe(string $dni): array
    {
        $apiKey = $_ENV['BACKUP_API_KEY'] ?? '';
        $url    = 'https://api.apis.net.pe/v2/dni?numero=' . urlencode($dni);

        $headers = ['User-Agent: PERUdata-API/1.0', 'Accept: application/json'];
        if ($apiKey) {
            $headers[] = 'Authorization: Bearer ' . $apiKey;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200 || empty($body)) return ['success' => false];

        $json = json_decode($body, true);
        if (empty($json) || isset($json['error']) || !isset($json['nombres'])) {
            return ['success' => false];
        }

        return [
            'success' => true,
            'data'    => $this->normalize($json, $dni),
            'source'  => 'APIS_NET_PE',
        ];
    }

    /**
     * Fuente 2: apiperu.dev
     */
    private function tryApiPeru(string $dni): array
    {
        $apiKey = $_ENV['APIPERU_KEY'] ?? '';
        if (!$apiKey) return ['success' => false];

        $ch = curl_init('https://apiperu.dev/api/dni/' . $dni);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $apiKey,
                'Accept: application/json',
            ],
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200 || empty($body)) return ['success' => false];

        $json = json_decode($body, true);
        if (empty($json['success']) || empty($json['data'])) return ['success' => false];

        $d = $json['data'];
        return [
            'success' => true,
            'data'    => [
                'dni'            => $dni,
                'nombres'        => $d['nombres']       ?? '',
                'apellido_pat'   => $d['apellidoPat']   ?? '',
                'apellido_mat'   => $d['apellidoMat']   ?? '',
                'nombre_completo'=> trim(($d['apellidoPat'] ?? '') . ' ' . ($d['apellidoMat'] ?? '') . ' ' . ($d['nombres'] ?? '')),
            ],
            'source' => 'APIPERU_DEV',
        ];
    }

    /**
     * Fuente 3: eldni.com — scraping HTML como fallback
     */
    private function tryEldni(string $dni): array
    {
        // Obtener CSRF token
        $ch = curl_init('https://eldni.com/pe/buscar-por-dni');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_COOKIEFILE     => '',
            CURLOPT_COOKIEJAR      => '/tmp/eldni_' . $dni . '.txt',
            CURLOPT_HTTPHEADER     => [
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept: text/html,application/xhtml+xml',
                'Accept-Language: es-PE,es;q=0.9',
            ],
        ]);
        $html = curl_exec($ch);
        curl_close($ch);

        if (!$html) return ['success' => false];

        // Extraer CSRF
        preg_match('/<input[^>]+name="_token"[^>]+value="([^"]+)"/', $html, $m);
        $csrf = $m[1] ?? null;
        if (!$csrf) {
            // Intentar meta tag
            preg_match('/<meta name="csrf-token" content="([^"]+)"/', $html, $m2);
            $csrf = $m2[1] ?? null;
        }
        if (!$csrf) return ['success' => false];

        // POST con DNI
        $cookieFile = '/tmp/eldni_' . $dni . '.txt';
        $ch = curl_init('https://eldni.com/pe/buscar-por-dni');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query(['_token' => $csrf, 'dni' => $dni]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_COOKIEFILE     => $cookieFile,
            CURLOPT_COOKIEJAR      => $cookieFile,
            CURLOPT_HTTPHEADER     => [
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Content-Type: application/x-www-form-urlencoded',
                'Referer: https://eldni.com/pe/buscar-por-dni',
                'X-Requested-With: XMLHttpRequest',
            ],
        ]);
        $html2 = curl_exec($ch);
        $code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        @unlink($cookieFile);

        if ($code !== 200 || empty($html2)) return ['success' => false];

        $data = $this->parseEldni($html2, $dni);
        return [
            'success' => !empty($data['nombres']),
            'data'    => $data,
            'source'  => 'RENIEC_ELDNI',
        ];
    }

    private function parseEldni(string $html, string $dni): array
    {
        $doc = new \DOMDocument();
        @$doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new \DOMXPath($doc);

        // Intentar tabla con th/td
        $nombres     = '';
        $apellidoPat = '';
        $apellidoMat = '';

        $rows = $xpath->query("//table//tr");
        foreach ($rows as $row) {
            $cells = $row->childNodes;
            $label = '';
            $val   = '';
            foreach ($cells as $c) {
                if ($c->nodeType !== XML_ELEMENT_NODE) continue;
                if (empty($label)) $label = trim($c->textContent);
                else               $val   = trim($c->textContent);
            }
            $labelUp = strtoupper($label);
            if (str_contains($labelUp, 'NOMBRE')) {
                if (str_contains($labelUp, 'PAT')) $apellidoPat = $val;
                elseif (str_contains($labelUp, 'MAT')) $apellidoMat = $val;
                else $nombres = $val;
            }
        }

        // Si no encontró con tabla, buscar en spans/divs
        if (!$nombres) {
            preg_match_all('/<td[^>]*>([^<]+)<\/td>/i', $html, $ms);
            $cells = array_map('trim', $ms[1] ?? []);
            for ($i = 0; $i < count($cells) - 1; $i++) {
                $lbl = strtoupper($cells[$i]);
                if (str_contains($lbl, 'NOMBRES'))        $nombres     = $cells[$i+1];
                if (str_contains($lbl, 'APELLIDO PAT'))   $apellidoPat = $cells[$i+1];
                if (str_contains($lbl, 'APELLIDO MAT'))   $apellidoMat = $cells[$i+1];
            }
        }

        return [
            'dni'            => $dni,
            'nombres'        => $nombres,
            'apellido_pat'   => $apellidoPat,
            'apellido_mat'   => $apellidoMat,
            'nombre_completo'=> trim("$apellidoPat $apellidoMat $nombres"),
        ];
    }

    private function normalize(array $json, string $dni): array
    {
        $nombres     = $json['nombres']      ?? '';
        $apellidoPat = $json['apellidoPat']  ?? $json['apellido_paterno'] ?? '';
        $apellidoMat = $json['apellidoMat']  ?? $json['apellido_materno'] ?? '';
        return [
            'dni'            => $dni,
            'nombres'        => $nombres,
            'apellido_pat'   => $apellidoPat,
            'apellido_mat'   => $apellidoMat,
            'nombre_completo'=> trim("$apellidoPat $apellidoMat $nombres"),
        ];
    }
}
