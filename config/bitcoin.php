<?php

return [
    'network' => env('BITCOIN_NETWORK', 'testnet'),

    'explorer' => [
        'url' => env('BITCOIN_EXPLORER_URL', 'https://blockstream.info/testnet/api'),
    ]
];
