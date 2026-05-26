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
            // Cascada RUC (de más a menos confiable):
            // 1. PadronScraper  — tu propia DB local. <5ms, sin Internet, sin ban.
            // 2. BackupScraper  — apis.net.pe (API JSON, no scraping a SUNAT).
            // 3. RucScraper     — scraping directo a SUNAT. Último recurso.
            return [new PadronScraper(), new BackupScraper(), new RucScraper()];
        }

        // DNI: cascade dentro de DniScraper ya maneja apis.net.pe → apiperu.dev → eldni.com
        return [new DniScraper()];
    }
}
