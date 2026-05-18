<?php
// Configuración estática de planes (refleja la tabla `plans`)
return [
    1 => [
        'name'            => 'Trial',
        'price_soles'     => 0.00,
        'queries_per_day' => 20,
        'queries_per_mo'  => 600,
        'cache_ttl_hours' => 24,
        'features'        => ['ruc' => true, 'dni' => false, 'bulk' => false],
    ],
    2 => [
        'name'            => 'Básico',
        'price_soles'     => 49.90,
        'queries_per_day' => 200,
        'queries_per_mo'  => 3000,
        'cache_ttl_hours' => 24,
        'features'        => ['ruc' => true, 'dni' => false, 'bulk' => false],
    ],
    3 => [
        'name'            => 'Pro',
        'price_soles'     => 89.90,
        'queries_per_day' => 1000,
        'queries_per_mo'  => 20000,
        'cache_ttl_hours' => 48,
        'features'        => ['ruc' => true, 'dni' => true, 'bulk' => false],
    ],
    4 => [
        'name'            => 'Enterprise',
        'price_soles'     => 199.90,
        'queries_per_day' => 5000,
        'queries_per_mo'  => 100000,
        'cache_ttl_hours' => 72,
        'features'        => ['ruc' => true, 'dni' => true, 'bulk' => true],
    ],
];
