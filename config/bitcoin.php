<?php

return [
    'network' => env('BITCOIN_NETWORK', 'testnet'),

    'explorer' => [
        'base_url' => env('BITCOIN_EXPLORER_BASE_URL', 'https://blockstream.info/testnet'),
    ],
];
