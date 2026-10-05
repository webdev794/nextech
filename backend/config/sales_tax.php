<?php

// US sales tax: each state's base (state-level) rate in basis points
// (725 = 7.25%). Local city/county taxes come on top in many states — the
// optional ZIP lookup (App\Support\SalesTax) covers those. Admin can edit
// any rate in Settings → Charges; these are only the starting values.
// Rates change rarely, but check them yearly.
return [
    // "state" (by where the order goes) or "flat" (one rate, Settings → Charges); admin can switch.
    'default_mode' => env('SALES_TAX_MODE', 'state'),

    'states' => [
        'AL' => 400, 'AK' => 0, 'AZ' => 560, 'AR' => 650, 'CA' => 725, 'CO' => 290, 'CT' => 635,
        'DE' => 0, 'DC' => 650, 'FL' => 600, 'GA' => 400, 'HI' => 400, 'ID' => 600, 'IL' => 625,
        'IN' => 700, 'IA' => 600, 'KS' => 650, 'KY' => 600, 'LA' => 500, 'ME' => 550, 'MD' => 600,
        'MA' => 625, 'MI' => 600, 'MN' => 688, 'MS' => 700, 'MO' => 423, 'MT' => 0, 'NE' => 550,
        'NV' => 685, 'NH' => 0, 'NJ' => 663, 'NM' => 488, 'NY' => 400, 'NC' => 475, 'ND' => 500,
        'OH' => 575, 'OK' => 450, 'OR' => 0, 'PA' => 600, 'RI' => 700, 'SC' => 600, 'SD' => 420,
        'TN' => 700, 'TX' => 625, 'UT' => 610, 'VT' => 600, 'VA' => 530, 'WA' => 650, 'WV' => 600,
        'WI' => 500, 'WY' => 400, 'PR' => 1150,
    ],

    // A ZIP code in each state's capital: "Fetch automatically" asks the lookup
    // for these to fill in each state's own (state-level) rate.
    'sample_zips' => [
        'AL' => '36104', 'AK' => '99801', 'AZ' => '85001', 'AR' => '72201', 'CA' => '95814', 'CO' => '80202', 'CT' => '06103',
        'DE' => '19901', 'DC' => '20001', 'FL' => '32301', 'GA' => '30303', 'HI' => '96813', 'ID' => '83702', 'IL' => '62701',
        'IN' => '46204', 'IA' => '50309', 'KS' => '66603', 'KY' => '40601', 'LA' => '70802', 'ME' => '04330', 'MD' => '21401',
        'MA' => '02108', 'MI' => '48933', 'MN' => '55102', 'MS' => '39201', 'MO' => '65101', 'MT' => '59601', 'NE' => '68508',
        'NV' => '89701', 'NH' => '03301', 'NJ' => '08608', 'NM' => '87501', 'NY' => '12207', 'NC' => '27601', 'ND' => '58501',
        'OH' => '43215', 'OK' => '73102', 'OR' => '97301', 'PA' => '17101', 'RI' => '02903', 'SC' => '29201', 'SD' => '57501',
        'TN' => '37219', 'TX' => '78701', 'UT' => '84111', 'VT' => '05602', 'VA' => '23219', 'WA' => '98501', 'WV' => '25301',
        'WI' => '53703', 'WY' => '82001', 'PR' => '00901',
    ],

    // Optional live ZIP-code lookup (combined state + local rate), cached per ZIP.
    'lookup' => [
        'url' => env('SALES_TAX_LOOKUP_URL', 'https://api.api-ninjas.com/v1/salestax'),
        'cache_days' => 30,
        'timeout_seconds' => 4,
    ],
];
