<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Supported currencies
    |--------------------------------------------------------------------------
    */

    'currencies' => ['USD', 'EUR', 'RUB'],

    /*
    |--------------------------------------------------------------------------
    | Plans
    |--------------------------------------------------------------------------
    |
    | Test entries only. Real plans will be managed through the CMS once
    | the admin panel for billing is built; this list keeps the API usable
    | for the future hoffman-web client in the meantime.
    |
    | Prices are stored in the minor unit of each currency (cents / kopecks).
    |
    */

    'plans' => [
        [
            'id' => 'monthly',
            'name' => 'Месячный доступ',
            'interval' => 'month',
            'prices' => [
                'USD' => 999,
                'EUR' => 899,
                'RUB' => 79900,
            ],
        ],
        [
            'id' => 'yearly',
            'name' => 'Годовой доступ',
            'interval' => 'year',
            'prices' => [
                'USD' => 8999,
                'EUR' => 7999,
                'RUB' => 699900,
            ],
        ],
        [
            'id' => 'lifetime',
            'name' => 'Пожизненный доступ',
            'interval' => null,
            'prices' => [
                'USD' => 19999,
                'EUR' => 17999,
                'RUB' => 1499900,
            ],
        ],
    ],

];
