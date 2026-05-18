<?php
namespace App\Scrapers;

class RucScraper
{
    const BASE_URL = 'https://e-consultaruc.sunat.gob.pe/cl-ti-itmrconsruc/';
    const TIMEOUT  = 15;

    public function query(string $type, string $value): array
    {
        if ($type !== 'ruc') return ['success' => false, 'data' => null, 'source' => null];

        $numRnd = $this->getNumRnd();
        if (!$numRnd) throw new \Exception('No se pudo obtener numRnd de SUNAT');

        $url      = self::BASE_URL . 'jcrS00Alias';
        $postData = http_build_query([
            'accion' => 'consPorRuc',
            'nroRuc' => $value,
            'numRnd' => $numRnd,
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_HTTPHEADER     => [
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Referer: ' . self::BASE_URL . 'frameCriterioBusqueda.jsp',
                'Content-Type: application/x-www-form-urlencoded',
            ],
        ]);
        $html = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200 || empty($html)) {
            throw new \Exception("SUNAT respondió HTTP $code");
        }

        $data = $this->parseHtml($html, $value);
        return [
            'success' => !empty($data['razon_social']),
            'data'    => $data,
            'source'  => 'SUNAT_DIRECT',
        ];
    }

    private function getNumRnd(): ?string
    {
        $ch = curl_init(self::BASE_URL . 'jcrS00Alias?accion=consPorRazonSoc&razSoc=');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => ['User-Agent: Mozilla/5.0'],
        ]);
        $html = curl_exec($ch);
        curl_close($ch);

        if (!$html) return null;
        preg_match('/numRnd["\s]*:["\s]*["\'](\w+)["\']/', $html, $m);
        return $m[1] ?? null;
    }

    private function parseHtml(string $html, string $ruc): array
    {
        $doc = new \DOMDocument();
        @$doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new \DOMXPath($doc);

        $getValue = function (string $label) use ($xpath): string {
            $nodes = $xpath->query("//td[contains(normalize-space(text()),'$label')]/following-sibling::td[1]");
            return trim($nodes->item(0)?->textContent ?? '');
        };

        return [
            'ruc'           => $ruc,
            'razon_social'  => $getValue('Nombre Comercial') ?: $getValue('Apellidos y Nombres') ?: $getValue('Razón Social'),
            'tipo_contribu' => $getValue('Tipo Contribuyente'),
            'estado'        => $getValue('Estado del Contribuyente'),
            'condicion'     => $getValue('Condición del Contribuyente'),
            'direccion'     => $getValue('Domicilio Fiscal'),
            'departamento'  => $getValue('Departamento'),
            'provincia'     => $getValue('Provincia'),
            'distrito'      => $getValue('Distrito'),
            'ubigeo'        => $getValue('Código Ubigeo'),
            'actividad'     => $getValue('Actividad(es) Económica(s)'),
        ];
    }
}
