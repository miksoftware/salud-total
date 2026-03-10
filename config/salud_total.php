<?php

return [
    'base_url' => env('SALUD_TOTAL_BASE_URL', 'https://transaccional.saludtotal.com.co/Transaccional'),
    'session_init_url' => env('SALUD_TOTAL_SESSION_URL', 'https://transaccional.saludtotal.com.co/Transaccional/inicio.aspx?q=cAfeQoJG6o2g5FuHsIH6N2XhaRUgm+pAXxR6dsWAk+c='),
    'delay_between_requests' => env('SALUD_TOTAL_DELAY', 1500), // ms between queries
    'timeout' => env('SALUD_TOTAL_TIMEOUT', 30),
];
