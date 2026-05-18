<?php
namespace App\Scrapers;

class DniScraper
{
    const BASE_URL = 'https://eldni.com/pe/buscar-por-dni';
    const TIMEOUT  = 15;

    public function query(string $type, string $value): array
    {
        if ($type !== 'dni') return ['success' => false, 'data' => null, 'source' => null];

        // Paso 1: obtener token CSRF
        $csrf = $this->getCsrfToken();
        if (!$csrf) throw new \Exception('No se pudo obtener CSRF de eldni.com');

        // Paso 2: enviar consulta
        $ch = curl_init(self::BASE_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                '_token' => $csrf,
                'dni'    => $value,
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_COOKIEFILE     => '',
            CURLOPT_COOKIEJAR      => '',
            CURLOPT_HTTPHEADER     => [
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                'Referer: ' . self::BASE_URL,
                'Content-Type: application/x-www-form-urlencoded',
            ],
        ]);
        $html = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200 || empty($html)) {
            throw new \Exception("eldni.com respondió HTTP $code");
        }

        $data = $this->parseHtml($html, $value);
        return [
            'success' => !empty($data['nombres']),
            'data'    => $data,
            'source'  => 'RENIEC_ELDNI',
        ];
    }

    private function getCsrfToken(): ?string
    {
        $ch = curl_init(self::BASE_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_COOKIEFILE     => '',
            CURLOPT_COOKIEJAR      => '',
            CURLOPT_HTTPHEADER     => ['User-Agent: Mozilla/5.0'],
        ]);
        $html = curl_exec($ch);
        curl_close($ch);

        if (!$html) return null;
        preg_match('/<meta name="csrf-token" content="([^"]+)"/', $html, $m);
        return $m[1] ?? null;
    }

    private function parseHtml(string $html, string $dni): array
    {
        $doc = new \DOMDocument();
        @$doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new \DOMXPath($doc);

        $getValue = function (string $label) use ($xpath): string {
            $nodes = $xpath->query("//td[contains(normalize-space(text()),'$label')]/following-sibling::td[1]");
            return trim($nodes->item(0)?->textContent ?? '');
        };

        $nombres      = $getValue('Nombres');
        $apellidoPat  = $getValue('Apellido Paterno');
        $apellidoMat  = $getValue('Apellido Materno');

        return [
            'dni'            => $dni,
            'nombres'        => $nombres,
            'apellido_pat'   => $apellidoPat,
            'apellido_mat'   => $apellidoMat,
            'nombre_completo'=> trim("$apellidoPat $apellidoMat $nombres"),
        ];
    }
}
