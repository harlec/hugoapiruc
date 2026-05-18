<?php
// Fuentes de datos configuradas
return [
    'ruc' => [
        [
            'name'    => 'SUNAT_DIRECT',
            'class'   => \App\Scrapers\RucScraper::class,
            'enabled' => true,
            'priority'=> 1,
        ],
        [
            'name'    => 'BACKUP_API',
            'class'   => \App\Scrapers\BackupScraper::class,
            'enabled' => true,
            'priority'=> 2,
        ],
    ],
    'dni' => [
        [
            'name'    => 'RENIEC_ELDNI',
            'class'   => \App\Scrapers\DniScraper::class,
            'enabled' => true,
            'priority'=> 1,
        ],
        [
            'name'    => 'BACKUP_API',
            'class'   => \App\Scrapers\BackupScraper::class,
            'enabled' => true,
            'priority'=> 2,
        ],
    ],
];
