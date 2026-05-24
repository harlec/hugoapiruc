<?php
namespace App\Scrapers;

class ScraperRouter
{
    public function query(string $type, string $value): array
    {
        $sources = $this->getSources($type);

        foreach ($sources as $source) {
            try {
                $result = $source->query($type, $value);
                if ($result['success']) {
                    return $result;
                }
            } catch (\Throwable $e) {
                error_log('[ScraperRouter] ' . get_class($source) . ': ' . $e->getMessage());
            }
        }

        return ['success' => false, 'data' => null, 'source' => null];
    }

    private function getSources(string $type): array
    {
        if ($type === 'ruc') {
            // Estrategia API-first: no tocamos SUNAT hasta que todo lo demás falle.
            // BackupScraper usa apis.net.pe (JSON, sin scraping).
            // RucScraper es el último recurso (scraping directo a SUNAT).
            return [new BackupScraper(), new RucScraper()];
        }

        // DNI: cascade definido dentro de DniScraper (apis.net.pe → apiperu.dev → eldni.com)
        return [new DniScraper()];
    }
}
