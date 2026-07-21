<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;

/**
 * SaludTotalCommerceService
 *
 * Scraper for the NEW Salud Total Commerce portal:
 *   https://transaccional.saludtotal.com.co/SaludTotal.Comerce/login.aspx
 *
 * Authentication: usuario + contraseña (ASP.NET WebForms POST).
 * Once logged in the session cookies are cached so they survive
 * multiple PHP requests (same as the legacy service).
 */
class SaludTotalCommerceService
{
    protected const BASE_URL      = 'https://transaccional.saludtotal.com.co/SaludTotal.Comerce';
    protected const LOGIN_URL     = 'https://transaccional.saludtotal.com.co/SaludTotal.Comerce/login.aspx';
    protected const CACHE_COOKIES = 'st_commerce_cookies';
    protected const CACHE_ACTIVE  = 'st_commerce_session_active';
    protected const CACHE_TTL     = 1800; // 30 minutes

    protected string $username;
    protected string $password;
    protected CookieJar $cookieJar;
    protected int $timeout;
    protected int $delay;
    protected bool $sessionActive = false;
    protected int $maxRetries = 3;

    public function __construct()
    {
        $this->username  = config('salud_total.commerce_username', '');
        $this->password  = config('salud_total.commerce_password', '');
        $this->timeout   = config('salud_total.timeout', 30);
        $this->delay     = config('salud_total.delay_between_requests', 1500);
        $this->cookieJar = new CookieJar();

        $this->restoreCookies();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Cookie persistence
    // ─────────────────────────────────────────────────────────────────────────

    protected function saveCookies(): void
    {
        $data = $this->cookieJar->toArray();
        Cache::put(self::CACHE_COOKIES, json_encode($data), self::CACHE_TTL);
        Cache::put(self::CACHE_ACTIVE, true, self::CACHE_TTL);
    }

    protected function restoreCookies(): void
    {
        $cached = Cache::get(self::CACHE_COOKIES);
        if ($cached) {
            $data = json_decode($cached, true);
            if (is_array($data) && !empty($data)) {
                foreach ($data as $c) {
                    $this->cookieJar->setCookie(new SetCookie($c));
                }
                $this->sessionActive = (bool) Cache::get(self::CACHE_ACTIVE, false);
                Log::debug('STCommerce: Restored ' . count($data) . ' cookies from cache');
            }
        }
    }

    protected function clearCookies(): void
    {
        Cache::forget(self::CACHE_COOKIES);
        Cache::forget(self::CACHE_ACTIVE);
        $this->cookieJar  = new CookieJar();
        $this->sessionActive = false;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Guzzle client factory
    // ─────────────────────────────────────────────────────────────────────────

    protected function guzzle(bool $followRedirects = true): Client
    {
        $options = [
            'cookies'         => $this->cookieJar,
            'verify'          => false,
            'timeout'         => $this->timeout,
            'http_errors'     => false,   // don't throw on 4xx/5xx, let us handle the body
            'allow_redirects' => $followRedirects
                ? ['max' => 10, 'track_redirects' => true]
                : false,
            'headers'         => [
                'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36',
                'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Accept-Language' => 'es-419,es;q=0.9',
                'Cache-Control'   => 'no-cache',
            ],
        ];

        return new Client($options);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ASP.NET hidden-field helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Parse ALL hidden inputs from an ASP.NET page.
     */
    public function parseAspNetFields(string $html): array
    {
        $fields = [];

        if (preg_match_all('/<input[^>]*type="hidden"[^>]*>/si', $html, $inputs)) {
            foreach ($inputs[0] as $input) {
                $name = $value = null;
                if (preg_match('/name="([^"]*)"/', $input, $m)) {
                    $name = $m[1];
                }
                if (preg_match('/value="([^"]*)"/', $input, $m)) {
                    $value = $m[1];
                }
                if ($name !== null) {
                    $fields[$name] = $value ?? '';
                }
            }
        }

        foreach (['__VIEWSTATE', '__VIEWSTATEGENERATOR', '__EVENTVALIDATION', '__EVENTTARGET', '__EVENTARGUMENT'] as $f) {
            if (!isset($fields[$f])) {
                $fields[$f] = '';
            }
        }

        Log::debug('STCommerce: Parsed ASP fields', [
            'count'            => count($fields),
            'viewstate_length' => strlen($fields['__VIEWSTATE']),
        ]);

        return $fields;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Session management
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Full login flow:
     *  1. GET login page → extract __VIEWSTATE etc.
     *  2. POST credentials → follow redirect to Default.aspx
     *
     * @return bool  true if login succeeded
     */
    public function login(): bool
    {
        try {
            Log::info('STCommerce: Starting login flow', ['url' => self::LOGIN_URL]);

            $this->clearCookies();

            $http = $this->guzzle(followRedirects: true);

            // ── Step 1: GET the login page ───────────────────────────────────
            $getResp  = $http->get(self::LOGIN_URL);
            $loginHtml = (string) $getResp->getBody();

            if ($getResp->getStatusCode() !== 200) {
                Log::error('STCommerce: Could not load login page', ['status' => $getResp->getStatusCode()]);
                return false;
            }

            $aspFields = $this->parseAspNetFields($loginHtml);

            Log::debug('STCommerce: Login page loaded', [
                'viewstate_len' => strlen($aspFields['__VIEWSTATE']),
                'has_validation' => !empty($aspFields['__EVENTVALIDATION']),
            ]);

            // ── Step 2: POST credentials ─────────────────────────────────────
            $postData = array_merge($aspFields, [
                '__EVENTTARGET'   => '',
                '__EVENTARGUMENT' => '',
                'lgnMain$UserName'    => $this->username,
                'lgnMain$Password'    => $this->password,
                'lgnMain$LoginButton' => 'Ingresar',
            ]);

            // Use a no-redirect client so we can capture the 302 manually
            $httpNoRedir = $this->guzzle(followRedirects: false);

            $postResp = $httpNoRedir->post(self::LOGIN_URL, [
                'form_params' => $postData,
                'headers'     => [
                    'Referer' => self::LOGIN_URL,
                    'Origin'  => 'https://transaccional.saludtotal.com.co',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
            ]);

            $status   = $postResp->getStatusCode();
            $location = $postResp->getHeaderLine('Location');

            Log::debug('STCommerce: POST response', [
                'status'   => $status,
                'location' => $location,
            ]);

            // ASP.NET typically redirects (302) on success to Default.aspx
            if (in_array($status, [301, 302, 303, 307, 308]) && $location) {
                $redirectUrl = $this->resolveUrl($location, self::BASE_URL);

                Log::debug('STCommerce: Following login redirect', ['url' => $redirectUrl]);

                $redirResp = $this->guzzle(followRedirects: true)->get($redirectUrl, [
                    'headers' => ['Referer' => self::LOGIN_URL],
                ]);

                $body   = (string) $redirResp->getBody();
                $status = $redirResp->getStatusCode();

                Log::debug('STCommerce: Redirect landing', [
                    'status' => $status,
                    'length' => strlen($body),
                ]);

                if ($this->isLoginPage($body)) {
                    Log::error('STCommerce: Login failed – landed back on login page (wrong credentials?)');
                    return false;
                }

                $this->sessionActive = true;
                $this->saveCookies();

                Log::info('STCommerce: Login successful', [
                    'cookies' => count($this->cookieJar->toArray()),
                    'landing' => $redirectUrl,
                ]);

                return true;
            }

            // 200 back on the same login page = bad credentials
            if ($status === 200) {
                $body = (string) $postResp->getBody();
                if ($this->isLoginPage($body)) {
                    Log::error('STCommerce: Login returned 200 on login page – credentials rejected');
                    return false;
                }

                // Stayed on a page that is not login → success
                $this->sessionActive = true;
                $this->saveCookies();
                return true;
            }

            Log::error('STCommerce: Unexpected login response', ['status' => $status]);
            return false;

        } catch (\Throwable $e) {
            Log::error('STCommerce: Login exception', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Make sure a session is active; (re)login if needed.
     */
    public function ensureSession(): bool
    {
        if ($this->sessionActive) {
            return true;
        }

        return $this->login();
    }

    /**
     * Force a fresh login (clear cached session first).
     */
    public function refreshSession(): bool
    {
        $this->clearCookies();
        return $this->login();
    }

    public function isSessionActive(): bool
    {
        return $this->sessionActive;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    protected function isLoginPage(string $html): bool
    {
        return str_contains($html, 'lgnMain$LoginButton')
            || str_contains($html, 'lgnMain$UserName')
            || str_contains($html, 'login.aspx');
    }

    protected function isSessionExpired(string $body): bool
    {
        if (strlen($body) < 2000 && $this->isLoginPage($body)) {
            return true;
        }
        if (str_contains($body, 'Session has expired') || str_contains($body, 'sesion ha expirado')) {
            return true;
        }
        return false;
    }

    protected function resolveUrl(string $url, string $base): string
    {
        if (str_starts_with($url, 'http')) {
            return $url;
        }
        if (str_starts_with($url, '/')) {
            return 'https://transaccional.saludtotal.com.co' . $url;
        }
        return rtrim($base, '/') . '/' . ltrim($url, './');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Page navigation helpers (to be extended later for scraping)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Perform a GET request with session guard.
     * @param array $query       URL query parameters
     * @param array $headers     Extra headers (e.g. Referer)
     * @param bool  $accept500   If true, a 500 response with sizeable body is treated as success
     * Returns the response body or null on failure.
     */
    public function get(string $url, array $query = [], array $headers = [], bool $accept500 = false): ?string
    {
        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                if (!$this->ensureSession()) {
                    return null;
                }

                $http = $this->guzzle();
                $options = [
                    'headers' => $headers,
                ];
                if (!empty($query)) {
                    $options['query'] = $query;
                }
                $resp = $http->get($url, $options);
                $status = $resp->getStatusCode();
                $body   = (string) $resp->getBody();

                $this->saveCookies();

                Log::debug("STCommerce: GET $status", ['url' => $url, 'length' => strlen($body)]);

                // Some pages return HTTP 500 but render full HTML content.
                // If accept500=true and the body is substantial, treat it as success.
                if ($status >= 400) {
                    if ($accept500 && $status === 500 && strlen($body) > 5000) {
                        Log::info("STCommerce: Accepting 500 with body (len=" . strlen($body) . ")", ['url' => $url]);
                        // Still check session expiry
                        if (!$this->isSessionExpired($body)) {
                            return $body;
                        }
                    }

                    Log::warning("STCommerce: GET HTTP $status (attempt $attempt)", ['url' => $url]);
                    if ($attempt < $this->maxRetries) {
                        usleep(1_500_000);
                        continue;
                    }
                    return null;
                }

                if ($this->isSessionExpired($body)) {
                    Log::warning("STCommerce: Session expired on GET (attempt $attempt)", ['url' => $url]);
                    $this->refreshSession();
                    if ($attempt < $this->maxRetries) {
                        usleep(1_500_000);
                        continue;
                    }
                    return null;
                }

                return $body;

            } catch (\Throwable $e) {
                Log::error("STCommerce: GET error (attempt $attempt)", ['url' => $url, 'error' => $e->getMessage()]);
                if ($attempt < $this->maxRetries) {
                    usleep(1_500_000);
                }
            }
        }

        return null;
    }

    /**
     * POST a form with ASP.NET fields, following the 302 redirect.
     * Returns the final body or null on failure.
     */
    public function postForm(string $url, array $extraData = [], string $referer = ''): ?string
    {
        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                if (!$this->ensureSession()) {
                    return null;
                }

                // GET the page first to obtain fresh ViewState
                $getBody = $this->get($url);
                if (!$getBody) {
                    return null;
                }

                $aspFields = $this->parseAspNetFields($getBody);
                $postData  = array_merge($aspFields, $extraData);

                $http     = $this->guzzle(followRedirects: false);
                $postResp = $http->post($url, [
                    'form_params' => $postData,
                    'headers'     => [
                        'Referer' => $referer ?: $url,
                        'Origin'  => 'https://transaccional.saludtotal.com.co',
                    ],
                ]);

                $status   = $postResp->getStatusCode();
                $location = $postResp->getHeaderLine('Location');

                if (in_array($status, [301, 302, 303, 307, 308]) && $location) {
                    $redirectUrl = $this->resolveUrl($location, self::BASE_URL);
                    $body = $this->get($redirectUrl);
                } else {
                    $body = (string) $postResp->getBody();
                }

                $this->saveCookies();

                if ($body && $this->isSessionExpired($body)) {
                    $this->refreshSession();
                    if ($attempt < $this->maxRetries) {
                        usleep(1_500_000);
                        continue;
                    }
                    return null;
                }

                return $body ?: null;

            } catch (\Throwable $e) {
                Log::error("STCommerce: POST error (attempt $attempt)", ['url' => $url, 'error' => $e->getMessage()]);
                if ($attempt < $this->maxRetries) {
                    usleep(1_500_000);
                }
            }
        }

        return null;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Scraping: Grupo Familiar
    // ─────────────────────────────────────────────────────────────────────────

    protected const QUERIES_URL = self::BASE_URL . '/Comercial/Consultas';

    /**
     * GET the GrupoFamiliar.aspx page to extract ViewState.
     */
    public function navigateToFamilyGroupPage(): ?string
    {
        if (!$this->ensureSession()) {
            return null;
        }

        $url  = self::QUERIES_URL . '/GrupoFamiliar.aspx';
        $body = $this->get($url);

        if (!$body) {
            return null;
        }

        Log::debug('STCommerce: Navigated to GrupoFamiliar', [
            'length'   => strlen($body),
            'has_form' => str_contains($body, 'txtIdentification'),
        ]);

        return $body;
    }

    /**
     * Query the family group for a given cedula.
     * Mirrors SaludTotalService::queryFamilyGroup() but for the Commerce portal.
     */
    public function queryFamilyGroup(string $cedula, string $tipoDoc = 'C'): ?array
    {
        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                // Step 1: GET form page
                $formHtml = $this->navigateToFamilyGroupPage();

                if (!$formHtml) {
                    Log::warning("STCommerce: Attempt $attempt - Failed to get form page for $cedula");
                    if ($attempt < $this->maxRetries) {
                        $this->refreshSession();
                        usleep(2_000_000);
                        continue;
                    }
                    return null;
                }

                if (!str_contains($formHtml, 'txtIdentification')) {
                    Log::warning("STCommerce: Form missing txtIdentification (attempt $attempt)");
                    if ($attempt < $this->maxRetries) {
                        $this->refreshSession();
                        usleep(2_000_000);
                        continue;
                    }
                    return null;
                }

                $aspFields = $this->parseAspNetFields($formHtml);
                usleep(500_000);

                // Step 2: POST the search form
                $postData = array_merge($aspFields, [
                    '__EVENTTARGET'                       => 'ctl00$MainContent$btnAceptar',
                    '__EVENTARGUMENT'                     => '',
                    'ctl00$MainContent$ddlIdentification' => $tipoDoc,
                    'ctl00$MainContent$txtIdentification' => $cedula,
                ]);

                $queryUrl = self::QUERIES_URL . '/GrupoFamiliar.aspx';

                $http     = $this->guzzle(followRedirects: false);
                $postResp = $http->post($queryUrl, [
                    'form_params' => $postData,
                    'headers'     => [
                        'User-Agent'   => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36',
                        'Accept'       => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                        'Referer'      => $queryUrl,
                        'Origin'       => 'https://transaccional.saludtotal.com.co',
                        'Content-Type' => 'application/x-www-form-urlencoded',
                    ],
                ]);

                $status   = $postResp->getStatusCode();
                $location = $postResp->getHeaderLine('Location');
                $body     = (string) $postResp->getBody();

                // Follow 302 redirect if present (sometimes the portal redirects)
                if (in_array($status, [301, 302, 303, 307, 308]) && $location) {
                    $redirectUrl = $this->resolveUrl($location, self::QUERIES_URL);
                    Log::debug("STCommerce: Following POST redirect", ['url' => $redirectUrl]);
                    $body = $this->get($redirectUrl) ?? $body;
                }
                // 200 with results inline — use body as-is

                $this->saveCookies();

                // Save for debugging
                @file_put_contents(storage_path('logs/commerce_grupo_familiar.html'), $body);

                Log::debug("STCommerce: POST GrupoFamiliar for $cedula", [
                    'status'         => $status,
                    'length'         => strlen($body),
                    'has_GFDetalle'  => str_contains($body, 'GFDetalle'),
                ]);

                if ($this->isSessionExpired($body)) {
                    $this->refreshSession();
                    if ($attempt < $this->maxRetries) {
                        usleep(2_000_000);
                        continue;
                    }
                    return ['error' => 'Sesión expirada después de reintentos.'];
                }

                return $this->parseFamilyGroupTable($body);

            } catch (\Throwable $e) {
                Log::error("STCommerce: queryFamilyGroup error (attempt $attempt)", [
                    'cedula' => $cedula,
                    'error'  => $e->getMessage(),
                ]);
                if ($attempt < $this->maxRetries) {
                    $this->refreshSession();
                    usleep(2_000_000);
                    continue;
                }
                return null;
            }
        }

        return null;
    }

    /**
     * Parse the result table from GrupoFamiliar.aspx.
     * The table has id="MainContent_dgrdResult" and cells wrap text in <font> tags.
     * Columns: Tipo Documento | Identificación | Consecutivo | Nombres | Parentesco | Estado Detallado
     */
    public function parseFamilyGroupTable(string $html): array
    {
        if (preg_match('/No\s+se\s+encontr/i', $html)) {
            return ['error' => 'No se encontraron datos para esta cédula'];
        }

        $tableHtml = null;

        // Strategy 1: exact table ID used by the Commerce portal
        if (preg_match('/<table[^>]*id="MainContent_dgrdResult"[^>]*>(.*?)<\/table>/si', $html, $m)) {
            $tableHtml = $m[0];
        }
        // Strategy 2: any table containing GFDetalle links (grab from <table to matching </table>)
        elseif (preg_match('/<table\b[^>]*>((?:[^<]|<(?!\/table>))*GFDetalle(?:[^<]|<(?!\/table>))*)<\/table>/si', $html, $m)) {
            $tableHtml = $m[0];
        }
        // Strategy 3: table with dgrd in id
        elseif (preg_match('/<table[^>]*id="[^"]*dgrd[^"]*"[^>]*>.+?<\/table>/si', $html, $m)) {
            $tableHtml = $m[0];
        }

        if (!$tableHtml) {
            Log::error('STCommerce: Could not find family group table', [
                'has_GFDetalle' => str_contains($html, 'GFDetalle'),
                'html_length'   => strlen($html),
            ]);
            return ['error' => 'No se pudo encontrar la tabla de grupo familiar'];
        }

        Log::debug('STCommerce: Table found', ['length' => strlen($tableHtml)]);

        preg_match_all('/<tr[^>]*>(.*?)<\/tr>/si', $tableHtml, $rows);

        if (count($rows[1]) < 2) {
            return ['error' => 'Tabla sin resultados'];
        }

        $members = [];

        // Skip header row (index 0)
        for ($i = 1; $i < count($rows[1]); $i++) {
            $rowHtml = $rows[1][$i];

            if (preg_match('/colspan/i', $rowHtml)) {
                continue;
            }

            preg_match_all('/<td[^>]*>(.*?)<\/td>/si', $rowHtml, $cells);

            if (count($cells[1]) < 4) {
                continue;
            }

            // Extract GFDetalle link
            $detailUrl = null;
            if (preg_match('/href="([^"]*GFDetalle[^"]*)"/i', $rowHtml, $lm)) {
                $detailUrl = html_entity_decode($lm[1], ENT_QUOTES, 'UTF-8');
            }

            // Extract ContratoLaboral link
            $contractUrl = null;
            if (preg_match('/href="([^"]*ContratoLaboral[^"]*)"/i', $rowHtml, $cm)) {
                $contractUrl = html_entity_decode($cm[1], ENT_QUOTES, 'UTF-8');
            }

            // Clean cell text: strip ALL nested tags (font, b, u, a, etc.)
            $cleanCell = function (string $cellHtml): string {
                $text = strip_tags($cellHtml);
                $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
                $text = preg_replace('/\s+/', ' ', $text);
                return trim($text, "\xC2\xA0 ");
            };

            $member = [
                'tipo_documento'       => $cleanCell($cells[1][0] ?? ''),
                'identificacion'       => $cleanCell($cells[1][1] ?? ''),
                'consecutivo'          => $cleanCell($cells[1][2] ?? ''),
                'nombres'              => $cleanCell($cells[1][3] ?? ''),
                'parentesco'           => $cleanCell($cells[1][4] ?? ''),
                'estado_detallado'     => $cleanCell($cells[1][5] ?? ''),
                'documentos_faltantes' => '',
                'detail_url'           => $detailUrl,
                'contract_url'         => $contractUrl,
            ];

            if (!empty($member['identificacion'])) {
                $members[] = $member;
                Log::debug('STCommerce: Found member', [
                    'id'         => $member['identificacion'],
                    'name'       => $member['nombres'],
                    'detail_url' => $member['detail_url'],
                ]);
            }
        }

        if (empty($members)) {
            return ['error' => 'No se encontraron miembros en la tabla'];
        }

        return ['members' => $members];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Scraping: Detalle de Grupo Familiar (GFDetalle.aspx)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Fetch and parse the GFDetalle.aspx page.
     * IDs: MainContent_tdXxx / MainContent_lblXxx
     */
    public function getPersonDetail(string $detailUrl): ?array
    {
        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                $fullUrl = $this->resolveUrl($detailUrl, self::QUERIES_URL);
                Log::debug("STCommerce: Fetching GFDetalle", ['url' => $fullUrl]);

                // Send GrupoFamiliar as Referer so the server-side session check passes
                // accept500=true because the portal returns 500 with full HTML for direct GETs
                $referer = self::QUERIES_URL . '/GrupoFamiliar.aspx';
                $body    = $this->get($fullUrl, [], ['Referer' => $referer], true);

                if (!$body) {
                    if ($attempt < $this->maxRetries) { usleep(2_000_000); continue; }
                    return null;
                }

                @file_put_contents(storage_path('logs/commerce_detail.html'), $body);

                $parsed = $this->parseDetailPage($body);
                if (!empty($parsed)) {
                    return $parsed;
                }

                Log::warning("STCommerce: Detail parsed empty (attempt $attempt)", [
                    'url'    => $fullUrl,
                    'length' => strlen($body),
                    'preview'=> substr($body, 0, 300),
                ]);
                if ($attempt < $this->maxRetries) { usleep(2_000_000); continue; }
                return null;

            } catch (\Throwable $e) {
                Log::error("STCommerce: getPersonDetail error (attempt $attempt)", ['error' => $e->getMessage()]);
                if ($attempt < $this->maxRetries) { usleep(2_000_000); }
            }
        }
        return null;
    }

    /**
     * Build the GFDetalle URL from cedula parameters.
     */
    public function getPersonDetailByParams(string $bi, string $bc = 'C', string $con = '0'): ?array
    {
        $url = self::QUERIES_URL . '/GFDetalle.aspx?' . http_build_query([
            'bi'         => $bi,
            'bc'         => $bc,
            'con'        => $con,
            'pOperation' => 'GrupoDetalle',
        ]);

        return $this->getPersonDetail($url);
    }

    /**
     * Parse the GFDetalle.aspx HTML.
     * The new portal uses MainContent_ prefix (not ctl00_ContentPlaceHolder1_).
     */
    public function parseDetailPage(string $html): array
    {
        $data = [];

        // Map: our field key → span ID suffix (MainContent_lblXxx)
        $spanMappings = [
            'identificacion'          => 'MainContent_lblIdentificacion',
            'nombres'                 => 'MainContent_lblNombre',
            'fecha_nacimiento'        => 'MainContent_lblFchC',
            'edad'                    => 'MainContent_lblEdad',
            'sexo'                    => 'MainContent_lblSexo',
            'antiguedad_salud_total'  => 'MainContent_lblAntST',
            'fecha_afiliacion'        => 'MainContent_lblFchAfiliacion',
            'eps_anterior'            => 'MainContent_lblEpsAnt',
            'antiguedad_otra_eps'     => 'MainContent_lblAntOtraEPS',
            'direccion'               => 'MainContent_lblDireccion',
            'telefono'                => 'MainContent_lblTelefono',
            'ciudad'                  => 'MainContent_lblCiudad',
            'ips_medica_asignada'     => 'MainContent_lblIPSMedica',
            'ips_odontologica_asignada' => 'MainContent_lblIPSOdont',
        ];

        foreach ($spanMappings as $key => $spanId) {
            $pattern = '#id="' . preg_quote($spanId, '#') . '"[^>]*>([^<]*)#si';
            if (preg_match($pattern, $html, $match)) {
                $value = trim(html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'));
                $value = preg_replace('/\s+/', ' ', $value);
                $value = trim($value, "\xC2\xA0 ");
                if (!empty($value) && $value !== '&nbsp;') {
                    $data[$key] = $value;
                }
            }
        }

        Log::debug('STCommerce: Parsed GFDetalle', [
            'fields_found' => count($data),
            'keys'         => array_keys($data),
        ]);

        return $data;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Scraping: Contrato Laboral (ContratoLaboral.aspx)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Fetch and parse ContratoLaboral.aspx.
     */
    public function getContractDetail(string $contractUrl): ?array
    {
        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                $fullUrl = $this->resolveUrl($contractUrl, self::QUERIES_URL);
                Log::debug("STCommerce: Fetching ContratoLaboral", ['url' => $fullUrl]);

                // Referer must be GrupoFamiliar so the server context is correct
                // accept500=true because the portal returns 500 with full HTML for direct GETs
                $referer = self::QUERIES_URL . '/GrupoFamiliar.aspx';
                $body    = $this->get($fullUrl, [], ['Referer' => $referer], true);

                if (!$body) {
                    if ($attempt < $this->maxRetries) { usleep(2_000_000); continue; }
                    return null;
                }

                @file_put_contents(storage_path('logs/commerce_contract.html'), $body);

                $parsed = $this->parseContractPage($body);
                if (!empty($parsed)) {
                    return $parsed;
                }

                Log::warning("STCommerce: Contract parsed empty (attempt $attempt)", [
                    'url'     => $fullUrl,
                    'length'  => strlen($body),
                    'preview' => substr($body, 0, 300),
                ]);
                if ($attempt < $this->maxRetries) { usleep(2_000_000); continue; }
                return null;

            } catch (\Throwable $e) {
                Log::error("STCommerce: getContractDetail error (attempt $attempt)", ['error' => $e->getMessage()]);
                if ($attempt < $this->maxRetries) { usleep(2_000_000); }
            }
        }
        return null;
    }

    /**
     * Parse ContratoLaboral.aspx HTML.
     * Span IDs: MainContent_lblXxx
     */
    public function parseContractPage(string $html): array
    {
        $data = [];

        $spanMappings = [
            'contrato_empresa_id'        => 'MainContent_lblIdentificacion',
            'contrato_empresa_nombre'    => 'MainContent_lblNombre',
            'contrato_arp'               => 'MainContent_lblARL',   // ARL in new portal
            'contrato_afp'               => 'MainContent_lblAFP',
            'contrato_cargo'             => 'MainContent_lblCargo',
            'contrato_ultimo_pago'       => 'MainContent_lblUltpago',
            'contrato_ingreso_base'      => 'MainContent_lblIngBase',
            'contrato_cotizacion_pagada' => 'MainContent_lblCotPag',
            'contrato_periodos_mora'     => 'MainContent_lblMora',
            'contrato_fecha_primer_pago' => 'MainContent_lblppago',
        ];

        foreach ($spanMappings as $key => $spanId) {
            $pattern = '#id="' . preg_quote($spanId, '#') . '"[^>]*>([^<]*)#si';
            if (preg_match($pattern, $html, $match)) {
                $value = trim(html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'));
                $value = preg_replace('/\s+/', ' ', $value);
                $value = trim($value, "\xC2\xA0 ");
                if (!empty($value) && $value !== '&nbsp;') {
                    $data[$key] = $value;
                }
            }
        }

        Log::debug('STCommerce: Parsed ContratoLaboral', [
            'fields_found' => count($data),
            'keys'         => array_keys($data),
        ]);

        return $data;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Main pipeline: process a single cedula
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Full pipeline: query family group → get person detail → get contract detail.
     * Returns the same structure as SaludTotalService::processCedula().
     */
    public function processCedula(string $cedula): array
    {
        $result = [
            'cedula' => $cedula,
            'status' => 'error',
            'data'   => [],
            'error'  => null,
        ];

        Log::info("STCommerce: Processing cedula $cedula");

        // ── Step 1: Query family group ────────────────────────────────────────
        $familyResult = $this->queryFamilyGroup($cedula);

        if (!$familyResult) {
            $result['error'] = 'Error al consultar el grupo familiar.';
            return $result;
        }

        if (isset($familyResult['error'])) {
            $result['error'] = $familyResult['error'];
            return $result;
        }

        if (empty($familyResult['members'])) {
            $result['error'] = 'No se encontraron miembros en el grupo familiar';
            return $result;
        }

        // Find the member matching the cedula (or use first)
        $targetMember = null;
        foreach ($familyResult['members'] as $member) {
            $memberNum = preg_replace('/[^0-9]/', '', $member['identificacion'] ?? '');
            $searchNum = preg_replace('/[^0-9]/', '', $cedula);
            if ($memberNum === $searchNum) {
                $targetMember = $member;
                break;
            }
        }
        if (!$targetMember) {
            $targetMember = $familyResult['members'][0];
            Log::info("STCommerce: Exact match not found for $cedula, using first member");
        }

        $result['data'] = $targetMember;

        // ── Step 2: Person detail page ────────────────────────────────────────
        usleep($this->delay * 1000);

        $detailData = null;

        if (!empty($targetMember['detail_url'])) {
            $detailData = $this->getPersonDetail($targetMember['detail_url']);
        }

        // Fallback: build URL from params
        if (!$detailData && !empty($targetMember['identificacion'])) {
            $bc      = 'C';
            $tipoDoc = strtoupper(trim($targetMember['tipo_documento'] ?? ''));
            if (str_contains($tipoDoc, 'EXTRANJERIA') || str_contains($tipoDoc, 'EXTRANJERA')) $bc = 'E';
            elseif (str_contains($tipoDoc, 'TARJETA'))   $bc = 'T';
            elseif (str_contains($tipoDoc, 'REGISTRO'))  $bc = 'R';
            elseif (str_contains($tipoDoc, 'PASAPORTE')) $bc = 'P';

            $detailData = $this->getPersonDetailByParams(
                trim($targetMember['identificacion']),
                $bc,
                trim($targetMember['consecutivo'] ?? '0')
            );
        }

        if ($detailData && !empty($detailData)) {
            $result['data'] = array_merge($result['data'], $detailData);
            $result['status'] = 'success';
        } else {
            $result['status'] = 'success';
            $result['error']  = 'Información parcial (sin detalle individual)';
        }

        // ── Step 3: Contract / labor detail ──────────────────────────────────
        usleep($this->delay * 1000);

        $contractUrl = null;

        if (!empty($targetMember['contract_url'])) {
            $contractUrl = $targetMember['contract_url'];
        } elseif (!empty($targetMember['detail_url'])) {
            // Derive contract URL from detail URL
            $derived = str_replace('GFDetalle.aspx', 'ContratoLaboral.aspx', $targetMember['detail_url']);
            if ($derived !== $targetMember['detail_url']) {
                $contractUrl = $derived;
            }
        }

        if (!$contractUrl && !empty($targetMember['identificacion'])) {
            // Build from params
            $bc      = 'C';
            $tipoDoc = strtoupper(trim($targetMember['tipo_documento'] ?? ''));
            if (str_contains($tipoDoc, 'EXTRANJERIA') || str_contains($tipoDoc, 'EXTRANJERA')) $bc = 'E';
            elseif (str_contains($tipoDoc, 'TARJETA'))   $bc = 'T';
            elseif (str_contains($tipoDoc, 'REGISTRO'))  $bc = 'R';
            elseif (str_contains($tipoDoc, 'PASAPORTE')) $bc = 'P';

            $contractUrl = self::QUERIES_URL . '/ContratoLaboral.aspx?' . http_build_query([
                'bi'         => trim($targetMember['identificacion']),
                'bc'         => $bc,
                'con'        => trim($targetMember['consecutivo'] ?? '0'),
                'pOperation' => 'GrupoDetalle',
            ]);
        }

        if ($contractUrl) {
            Log::debug("STCommerce: Fetching contract", ['url' => $contractUrl]);
            $contractData = $this->getContractDetail($contractUrl);
            if ($contractData && !empty($contractData)) {
                $result['data'] = array_merge($result['data'], $contractData);
                Log::info("STCommerce: Contract data obtained for $cedula");
            }
        }

        Log::info("STCommerce: Finished processing $cedula", [
            'status' => $result['status'],
            'fields' => count($result['data']),
        ]);

        return $result;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Public getters (mirror the legacy service API)
    // ─────────────────────────────────────────────────────────────────────────

    public function getDelay(): int
    {
        return $this->delay;
    }

    public function getUsername(): string
    {
        return $this->username;
    }
}

