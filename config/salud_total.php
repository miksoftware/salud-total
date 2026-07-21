<?php

return [
    // ── Método de inicio de sesión ────────────────────────────────────────────
    // 'url'      → URL mágica con token cifrado (portal Transaccional clásico)
    // 'commerce' → Usuario + contraseña (nuevo portal SaludTotal.Comerce)
    'login_method' => env('SALUD_TOTAL_LOGIN_METHOD', 'url'),

    // ── Método: URL de sesión (legado) ────────────────────────────────────────
    'base_url'         => env('SALUD_TOTAL_BASE_URL', 'https://transaccional.saludtotal.com.co/Transaccional'),
    'session_init_url' => env('SALUD_TOTAL_SESSION_URL', 'https://transaccional.saludtotal.com.co/Transaccional/inicio.aspx?q=cAfeQoJG6o2g5FuHsIH6N2XhaRUgm+pAXxR6dsWAk+c='),

    // ── Método: Usuario + Contraseña (nuevo portal Commerce) ─────────────────
    'commerce_base_url' => env('SALUD_TOTAL_COMMERCE_URL', 'https://transaccional.saludtotal.com.co/SaludTotal.Comerce'),
    'commerce_username' => env('SALUD_TOTAL_USERNAME', ''),
    'commerce_password' => env('SALUD_TOTAL_PASSWORD', ''),

    // ── Configuración general ─────────────────────────────────────────────────
    'delay_between_requests' => env('SALUD_TOTAL_DELAY', 1500),   // ms entre consultas
    'timeout'                => env('SALUD_TOTAL_TIMEOUT', 30),
];
