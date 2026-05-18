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
            return [new RucScraper(), new BackupScraper()];
        }
        return [new DniScraper(), new BackupScraper()];
    }
}
