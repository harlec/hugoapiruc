<?php
namespace App\Scrapers;

class RucScraper
{
    const BASE_URL = 'https://e-consultaruc.sunat.gob.pe/cl-ti-itmrconsruc/';
    const TIMEOUT  = 20;

    // User-Agents reales de Chrome en distintas versiones
    private static array $userAgents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:125.0) Gecko/20100101 Firefox/125.0',
    ];

    // Throttle: compartido entre instancias via archivo temporal
    private static string $throttleFile = '';

    public function query(string $type, string $value): array
    {
        if ($type !== 'ruc') return ['success' => false, 'data' => null, 'source' => null];

        $this->throttle();

        $cookieFile = $this->getCookieFile();
        $ua         = self::$userAgents[array_rand(self::$userAgents)];

        [$numRnd, $sessionOk] = $this->getNumRnd($cookieFile, $ua);
        if (!$numRnd) {
            $this->clearCookieFile($cookieFile);
            throw new \Exception('No se pudo obtener numRnd de SUNAT');
        }

        $data = $this->postRuc($value, $numRnd, $cookieFile, $ua);

        return [
            'success' => !empty($data['razon_social']),
            'data'    => $data,
            'source'  => 'SUNAT_DIRECT',
        ];
    }

    // ── Throttle global: máximo 1 request a SUNAT por segundo ────────────────
    private function throttle(): void
    {
        if (!self::$throttleFile) {
            self::$throttleFile = sys_get_temp_dir() . '/perudata_sunat_throttle';
        }

        $last = (float)@file_get_contents(self::$throttleFile);
        $diff = microtime(true) - $last;

        if ($diff < 1.0) {
            $sleep = (int)ceil((1.0 - $diff) * 1_000_000);
            usleep($sleep);
        }

        file_put_contents(self::$throttleFile, microtime(true), LOCK_EX);
    }

    // ── Cookie jar: un archivo por proceso, se reutiliza para la sesión ──────
    private function getCookieFile(): string
    {
        $path = sys_get_temp_dir() . '/perudata_sunat_session.txt';
        if (!file_exists($path)) {
            touch($path);
        }
        return $path;
    }

    private function clearCookieFile(string $path): void
    {
        @file_put_contents($path, '');
    }

    // ── Paso 1: GET para obtener numRnd y establecer sesión con cookies ───────
    private function getNumRnd(string $cookieFile, string $ua): array
    {
        // Primero se visita la página principal para que SUNAT asigne sesión
        $ch = curl_init(self::BASE_URL . 'frameCriterioBusqueda.jsp');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_COOKIEFILE     => $cookieFile,
            CURLOPT_COOKIEJAR      => $cookieFile,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER     => $this->buildHeaders($ua, self::BASE_URL),
        ]);
        curl_exec($ch);
        curl_close($ch);

        // Pequeña pausa para simular comportamiento humano (100-300ms)
        usleep(random_int(100_000, 300_000));

        // Luego obtenemos el numRnd con la sesión ya establecida
        $url = self::BASE_URL . 'jcrS00Alias?accion=consPorRazonSoc&razSoc=';
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_COOKIEFILE     => $cookieFile,
            CURLOPT_COOKIEJAR      => $cookieFile,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER     => array_merge(
                $this->buildHeaders($ua, self::BASE_URL . 'frameCriterioBusqueda.jsp'),
                ['X-Requested-With: XMLHttpRequest']
            ),
        ]);
        $html = curl_exec($ch);
        curl_close($ch);

        if (!$html) return [null, false];

        preg_match('/numRnd["\s]*[=:]["\s]*["\'](\w+)["\']/', $html, $m);
        return [$m[1] ?? null, true];
    }

    // ── Paso 2: POST con session cookie para obtener los datos del RUC ────────
    private function postRuc(string $ruc, string $numRnd, string $cookieFile, string $ua): array
    {
        usleep(random_int(200_000, 500_000)); // pausa humana antes del POST

        $url      = self::BASE_URL . 'jcrS00Alias';
        $postData = http_build_query([
            'accion' => 'consPorRuc',
            'nroRuc' => $ruc,
            'numRnd' => $numRnd,
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_COOKIEFILE     => $cookieFile,
            CURLOPT_COOKIEJAR      => $cookieFile,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER     => array_merge(
                $this->buildHeaders($ua, self::BASE_URL . 'frameCriterioBusqueda.jsp'),
                [
                    'Content-Type: application/x-www-form-urlencoded',
                    'X-Requested-With: XMLHttpRequest',
                ]
            ),
        ]);

        $html = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200 || empty($html)) {
            throw new \Exception("SUNAT respondió HTTP $code");
        }

        return $this->parseHtml($html, $ruc);
    }

    // ── Headers que simulan un Chrome real ────────────────────────────────────
    private function buildHeaders(string $ua, string $referer = ''): array
    {
        $headers = [
            "User-Agent: $ua",
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language: es-PE,es;q=0.9,en;q=0.8',
            'Accept-Encoding: gzip, deflate, br',
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: same-origin',
            'Cache-Control: no-cache',
            'Pragma: no-cache',
        ];

        if ($referer) {
            $headers[] = "Referer: $referer";
        }

        return $headers;
    }

    // ── Parser HTML con XPath ─────────────────────────────────────────────────
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
