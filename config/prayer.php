<?php

return [

    /*
    | Calculation methods. A city's `calc_method` column points to a key here.
    | Angles are degrees below the horizon. Offsets are minutes added to the
    | calculated time.
    */
    'methods' => [
        // Spiritual Administration of Muslims of Russia (Aladhan method 14).
        'russia' => [
            'name' => 'ДУМ России',
            'fajr_angle' => 16.0,
            'isha_angle' => 15.0,
            'offsets' => [],
        ],
        // Muslim World League.
        'mwl' => [
            'name' => 'Всемирная исламская лига',
            'fajr_angle' => 18.0,
            'isha_angle' => 17.0,
            'offsets' => [],
        ],
    ],

    // How long calculated times stay cached (per city per day).
    'cache_ttl_hours' => 48,

];
