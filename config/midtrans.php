<?php

return [
    'server_key' => env('MIDTRANS_SERVER_KEY'),
    'client_key' => env('MIDTRANS_CLIENT_KEY'),
    'is_production' => (bool) env('MIDTRANS_IS_PRODUCTION', false),
    'is_sanitized' => true,
    'is_3ds' => true,

    // BCA, Mandiri (echannel), BRI, BNI, Indomaret, Alfamart
    'enabled_payments' => ['bca_va', 'echannel', 'bri_va', 'bni_va', 'indomaret', 'alfamart'],
];