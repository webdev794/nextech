<?php

return [
    // Which CourierProvider implementation Courier::provider() hands back.
    // 'mock' is the only one today; a real carrier plugs in as another arm.
    'provider' => env('COURIER_PROVIDER', 'mock'),
];
