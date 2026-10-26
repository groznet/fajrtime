<?php

// Defaults for values that admins can override in the `settings` table (see App\Models\Setting).
// Money is in kopecks.
return [

    // Site owner details published on /contact and in the footer
    // (149-FZ art. 10 part 2: name, location/address, email for art. 15.7 complaints).
    // Fill in the real legal entity / sole proprietor data via .env before launch.
    'operator' => [
        'name' => env('OPERATOR_NAME', 'FajrRise'),
        'legal_name' => env('OPERATOR_LEGAL_NAME'),
        'tax_id' => env('OPERATOR_INN'),
        'reg_number' => env('OPERATOR_OGRN'),
        'address' => env('OPERATOR_ADDRESS', 'г. Грозный, Чеченская Республика, Россия'),
        'email' => env('OPERATOR_EMAIL', 'info@fajrrise.ru'),
        'telegram' => env('OPERATOR_TELEGRAM', '@fajrrise'),
        'domain' => env('OPERATOR_DOMAIN', 'fajrrise.ru'),
    ],

    // Bump when the privacy policy / terms change materially.
    'legal_updated_at' => '2026-10-27',

    'defaults' => [
        'commission_percent' => 5,
        'topup_min' => 100_00,
        'topup_max' => 50_000_00,
        'withdrawal_min' => 100_00,
    ],

];
