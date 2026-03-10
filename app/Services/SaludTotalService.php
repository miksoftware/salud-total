<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use GuzzleHttp\Cookie\CookieJar;

class SaludTotalService
{
    protected string $baseUrl;
    protected string $sessionInitUrl;
    protected CookieJar $cookieJar;
    protected int $timeout;
    protected int $delay;
    protected bool $sessionActive = false;
    protected int $maxRetries = 3;

    public function __construct()
    {
        $this->baseUrl = config('salud_total.base_url');
        $this->sessionInitUrl = config('salud_total.session_init_url');
        $this->timeout = config('salud_total.timeout');
        $this->delay = config('salud_total.delay_between_requests');
        $this->cookieJar = new CookieJar();

        // Restore cookies from cache if available
        $this->restoreCookies();
    }

    /**
     * Save cookies to cache so they persist between HTTP requests.
     */
    protected function saveCookies(): void
    {
        $cookieData = [];
        foreach ($this->cookieJar->toArray() as $cookie) {
            $cookieData[] = $cookie;
        }
        Cache::put('salud_total_cookies', json_encode($cookieData), 1800); // 30 min
        Cache::put('salud_total_session_active', true, 1800);
    }

    /**
     * Restore cookies from cache.
     */
    protected function restoreCookies(): void
    {
        $cached = Cache::get('salud_total_cookies');
        if ($cached) {
            $cookieData = json_decode($cached, true);
            if (is_array($cookieData) && !empty($cookieData)) {
                foreach ($cookieData as $cookie) {
                    $this->cookieJar->setCookie(new \GuzzleHttp\Cookie\SetCookie($cookie));
                }
                $this->sessionActive = (bool) Cache::get('salud_total_session_active', false);
                Log::debug('SaludTotal: Restored ' . count($cookieData) . ' cookies from cache');
            }
        }
    }

    /**
     * Get the HTTP client configured with cookies and settings.
     */
    protected function client()
    {
        return Http::withOptions([
            'cookies' => $this->cookieJar,
            'verify' => false,
            'timeout' => $this->timeout,
            'allow_redirects' => [
                'max' => 10,
                'track_redirects' => true,
            ],
        ])->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
            'Accept-Language' => 'es-419,es;q=0.9',
            'Cache-Control' => 'no-cache',
            'Referer' => $this->baseUrl . '/default.aspx',
        ]);
    }

    /**
     * Initialize session by hitting the magic session URL.
     */
    public function initSession(): bool
    {
        try {
            Log::info('SaludTotal: Initializing session...', ['url' => $this->sessionInitUrl]);

            // Reset cookies for fresh session
            $this->cookieJar = new CookieJar();

            $response = $this->client()->get($this->sessionInitUrl);

            if (!$response->successful()) {
                Log::error('SaludTotal: Session init failed', ['status' => $response->status()]);
                return false;
            }

            $body = $response->body();

            // Check we didn't land on an error page
            if (str_contains($body, 'no existen datos validos')) {
                Log::error('SaludTotal: Session URL is invalid or expired');
                return false;
            }

            $this->sessionActive = true;
            $this->saveCookies();

            Log::info('SaludTotal: Session initialized successfully', [
                'cookies' => count($this->cookieJar->toArray()),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('SaludTotal: Session init error', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Ensure session is active, reinitialize if needed.
     */
    protected function ensureSession(): bool
    {
        if (!$this->sessionActive) {
            return $this->initSession();
        }
        return true;
    }

    /**
     * Refresh the session (called when session expires).
     */
    public function refreshSession(): bool
    {
        Log::info('SaludTotal: Refreshing session...');
        $this->sessionActive = false;
        Cache::forget('salud_total_cookies');
        Cache::forget('salud_total_session_active');
        $this->cookieJar = new CookieJar();
        return $this->initSession();
    }

    /**
     * Check if response indicates session expired.
     */
    protected function isSessionExpired($response, string $body = ''): bool
    {
        if (empty($body) && $response) {
            $body = $response->body();
        }

        if ($response) {
            $status = is_object($response) && method_exists($response, 'status') ? $response->status() : 0;
            if ($status === 401 || $status === 403) {
                return true;
            }
        }

        // Only check for actual session expiration indicators
        if (str_contains($body, 'no existen datos validos para cargar la pagina')) {
            return true;
        }

        if (str_contains($body, 'Session has expired') || str_contains($body, 'sesion ha expirado')) {
            return true;
        }

        // Page is a redirect to login (tiny response without real content)
        if (strlen($body) < 2000 && str_contains($body, 'inicio.aspx')) {
            return true;
        }

        return false;
    }

    /**
     * Parse ASP.NET hidden fields from HTML (ViewState, EventValidation, etc.)
     */
    public function parseAspNetFields(string $html): array
    {
        $fields = [];

        // Extract ALL hidden input fields from the form (ASP.NET requires them all)
        if (preg_match_all('/<input[^>]*type="hidden"[^>]*>/si', $html, $hiddenInputs)) {
            foreach ($hiddenInputs[0] as $input) {
                $name = null;
                $value = '';
                if (preg_match('/name="([^"]*)"/', $input, $nm)) {
                    $name = $nm[1];
                }
                if (preg_match('/value="([^"]*)"/', $input, $vm)) {
                    $value = $vm[1];
                }
                if ($name) {
                    $fields[$name] = $value;
                }
            }
        }

        // Ensure critical ASP.NET fields exist (even if empty)
        foreach (['__VIEWSTATE', '__VIEWSTATEGENERATOR', '__EVENTVALIDATION', '__EVENTTARGET', '__EVENTARGUMENT'] as $name) {
            if (!isset($fields[$name])) {
                $fields[$name] = '';
            }
        }

        Log::debug('SaludTotal: Parsed ASP.NET fields', [
            'field_count' => count($fields),
            'viewstate_length' => strlen($fields['__VIEWSTATE'] ?? ''),
            'has_eventvalidation' => !empty($fields['__EVENTVALIDATION']),
        ]);

        return $fields;
    }

    /**
     * Navigate to the family group query page and return the HTML form.
     */
    public function navigateToFamilyGroupPage(): ?string
    {
        if (!$this->ensureSession()) {
            return null;
        }

        try {
            $response = $this->client()->get(
                $this->baseUrl . '/Queries/FamiliarGroup.aspx',
                [
                    'sDate' => now()->format('m/d/Y'),
                    'Origen' => ' ',
                    'pID' => '20',
                    'pOperation' => '27',
                ]
            );

            $body = $response->body();

            if ($this->isSessionExpired($response, $body)) {
                Log::warning('SaludTotal: Session expired during navigation, refreshing...');
                if ($this->refreshSession()) {
                    $response = $this->client()->get(
                        $this->baseUrl . '/Queries/FamiliarGroup.aspx',
                        [
                            'sDate' => now()->format('m/d/Y'),
                            'Origen' => ' ',
                            'pID' => '20',
                            'pOperation' => '27',
                        ]
                    );
                    $body = $response->body();
                } else {
                    return null;
                }
            }

            if ($response->successful()) {
                $this->saveCookies();
                Log::debug('SaludTotal: Navigation to FamilyGroup OK', [
                    'body_length' => strlen($body),
                    'has_form' => str_contains($body, 'txtIdentification'),
                ]);
                return $body;
            }

            Log::error('SaludTotal: Failed to navigate to FamilyGroup page', [
                'status' => $response->status(),
            ]);
            return null;
        } catch (\Exception $e) {
            Log::error('SaludTotal: Navigation error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Query the family group for a given cedula with auto-retry.
     */
    
        public function queryFamilyGroup(string $cedula, string $tipoDoc = 'C'): ?array
        {
            for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
                try {
                    // Step 1: GET the form page to obtain ViewState
                    $formHtml = $this->navigateToFamilyGroupPage();
                    if (!$formHtml) {
                        Log::warning("SaludTotal: Attempt $attempt - Failed to get form page for $cedula");
                        if ($attempt < $this->maxRetries) {
                            $this->refreshSession();
                            usleep(2000000);
                            continue;
                        }
                        return null;
                    }

                    if (!str_contains($formHtml, 'txtIdentification') && !str_contains($formHtml, 'Identificaci')) {
                        Log::warning("SaludTotal: Form page doesn't contain expected fields, attempt $attempt");
                        if ($attempt < $this->maxRetries) {
                            $this->refreshSession();
                            usleep(2000000);
                            continue;
                        }
                        return null;
                    }

                    $aspFields = $this->parseAspNetFields($formHtml);
                    Log::debug("SaludTotal: ViewState length: " . strlen($aspFields['__VIEWSTATE'] ?? ''));

                    usleep(500000);

                    // Step 2: POST using Guzzle directly with manual redirect handling
                    $postData = array_merge($aspFields, [
                        'ctl00$ContentPlaceHolder1$ddlIdentificationType' => $tipoDoc,
                        'ctl00$ContentPlaceHolder1$txtIdentification' => $cedula,
                        'ctl00$ContentPlaceHolder1$btnAceptar' => 'Aceptar',
                    ]);

                    $guzzle = new \GuzzleHttp\Client([
                        'cookies' => $this->cookieJar,
                        'verify' => false,
                        'timeout' => $this->timeout,
                        'allow_redirects' => false,
                    ]);

                    $postUrl = $this->baseUrl . '/Queries/FamiliarGroup.aspx?pOperation=27';

                    $guzzleResponse = $guzzle->post($postUrl, [
                        'form_params' => $postData,
                        'headers' => [
                            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36',
                            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                            'Accept-Language' => 'es-419,es;q=0.9',
                            'Referer' => $postUrl,
                            'Origin' => 'https://transaccional.saludtotal.com.co',
                        ],
                    ]);

                    $statusCode = $guzzleResponse->getStatusCode();
                    $body = (string) $guzzleResponse->getBody();

                    Log::debug("SaludTotal: POST response for $cedula", [
                        'status' => $statusCode,
                        'length' => strlen($body),
                    ]);

                    // Follow redirect manually if 302/301
                    if (in_array($statusCode, [301, 302, 303, 307])) {
                        $redirectUrl = $guzzleResponse->getHeaderLine('Location');
                        if ($redirectUrl) {
                            if (!str_starts_with($redirectUrl, 'http')) {
                                if (str_starts_with($redirectUrl, '/')) {
                                    $redirectUrl = 'https://transaccional.saludtotal.com.co' . $redirectUrl;
                                } else {
                                    $redirectUrl = $this->baseUrl . '/Queries/' . ltrim($redirectUrl, './');
                                }
                            }
                            Log::debug("SaludTotal: Following POST redirect", ['url' => $redirectUrl]);

                            $redirectResponse = $guzzle->get($redirectUrl, [
                                'headers' => [
                                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                                    'Referer' => $postUrl,
                                ],
                                'allow_redirects' => ['max' => 5],
                            ]);

                            $statusCode = $redirectResponse->getStatusCode();
                            $body = (string) $redirectResponse->getBody();

                            Log::debug("SaludTotal: Redirect final for $cedula", [
                                'status' => $statusCode,
                                'length' => strlen($body),
                            ]);
                        }
                    }

                    $this->saveCookies();

                    Log::debug("SaludTotal: Final response for $cedula", [
                        'status' => $statusCode,
                        'length' => strlen($body),
                        'has_dgrdResult' => str_contains($body, 'dgrdResult'),
                        'has_FGDetail' => str_contains($body, 'FGDetail'),
                    ]);

                    if ($this->isSessionExpired(null, $body)) {
                        Log::warning("SaludTotal: Session expired during query (attempt $attempt)", [
                            'body_preview' => substr($body, 0, 300),
                        ]);
                        $this->refreshSession();
                        if ($attempt < $this->maxRetries) {
                            usleep(2000000);
                            continue;
                        }
                        return ['error' => 'Sesión expirada después de reintentos.'];
                    }

                    if ($statusCode !== 200) {
                        Log::warning("SaludTotal: Non-200 for $cedula", ['status' => $statusCode, 'body' => substr($body, 0, 300)]);
                        if ($attempt < $this->maxRetries) {
                            usleep(2000000);
                            continue;
                        }
                        return null;
                    }

                    return $this->parseFamilyGroupTable($body);
                } catch (\Exception $e) {
                    Log::error("SaludTotal: Query error (attempt $attempt)", [
                        'cedula' => $cedula,
                        'error' => $e->getMessage(),
                    ]);
                    if ($attempt < $this->maxRetries) {
                        $this->refreshSession();
                        usleep(2000000);
                        continue;
                    }
                    return null;
                }
            }

            return null;
        }



    /**
     * Parse the family group results table from the portal HTML.
     * The portal renders an ASP.NET GridView with columns:
     * Tipo Documento | Identificación | Consec. | Nombres | Parentesco | Estado Detallado | Documentos Faltantes
     * The "Nombres" column contains an <a> link to the detail page.
     */
    
        public function parseFamilyGroupTable(string $html): array
        {
            // Check for "no data" messages
            if (preg_match('/No\s+se\s+encontr/i', $html)) {
                return ['error' => 'No se encontraron datos para esta cédula'];
            }

            Log::debug('SaludTotal: Parsing family group table', [
                'html_length' => strlen($html),
                'contains_dgrdResult' => str_contains($html, 'dgrdResult'),
                'contains_FGDetail' => str_contains($html, 'FGDetail'),
            ]);

            $tableHtml = null;

            // Strategy 1: Match the actual ASP.NET DataGrid ID (ctl00_ContentPlaceHolder1_dgrdResult)
            if (preg_match('/<table[^>]*id="[^"]*(?:dgrd|DataGrid|gv|GridView|grid|Familiar|Result)[^"]*"[^>]*>(.+?)<\/table>/si', $html, $m)) {
                $tableHtml = $m[0];
            }
            // Strategy 2: Find table that contains FGDetail links (most reliable indicator)
            elseif (preg_match('/<table[^>]*>(?=.*?FGDetail).+?<\/table>/si', $html, $m)) {
                $tableHtml = $m[0];
            }
            // Strategy 3: Find table that contains "Tipo Documento" header
            elseif (preg_match('/<table[^>]*>(?=.*?Tipo\s*Doc)(.+?)<\/table>/si', $html, $m)) {
                $tableHtml = $m[0];
            }
            // Strategy 4: Find table after "Grupo Familiar" heading
            elseif (preg_match('/Grupo\s*Familiar.*?(<table[^>]*>.+?<\/table>)/si', $html, $m)) {
                $tableHtml = $m[1];
            }

            if (!$tableHtml) {
                $debugPath = storage_path('logs/last_family_group_response.html');
                @file_put_contents($debugPath, $html);
                Log::error('SaludTotal: Could not find family group table', [
                    'debug_saved_to' => $debugPath,
                ]);
                return ['error' => 'No se pudo encontrar la tabla de grupo familiar en la respuesta'];
            }

            Log::debug('SaludTotal: Found table HTML', ['length' => strlen($tableHtml)]);

            // Extract all rows
            preg_match_all('/<tr[^>]*>(.*?)<\/tr>/si', $tableHtml, $rows);

            if (count($rows[1]) < 2) {
                Log::warning('SaludTotal: Table found but has less than 2 rows');
                return ['error' => 'Tabla sin resultados'];
            }

            $members = [];
            // Skip first row (header) and process data rows
            for ($i = 1; $i < count($rows[1]); $i++) {
                $rowHtml = $rows[1][$i];

                // Skip footer/pager rows (they have colspan)
                if (preg_match('/colspan/i', $rowHtml)) {
                    continue;
                }

                // Extract all cells (td)
                preg_match_all('/<td[^>]*>(.*?)<\/td>/si', $rowHtml, $cells);

                if (count($cells[1]) < 5) {
                    continue;
                }

                // Extract the FGDetail link from the row
                $detailUrl = null;
                if (preg_match('/href="([^"]*FGDetail[^"]*)"/', $rowHtml, $linkMatch)) {
                    $detailUrl = html_entity_decode($linkMatch[1], ENT_QUOTES, 'UTF-8');
                }

                // Extract the ContratoLaboral link from the "Estado Detallado" column
                $contractUrl = null;
                if (preg_match('/href="([^"]*ContratoLaboral[^"]*)"/', $rowHtml, $contractMatch)) {
                    $contractUrl = html_entity_decode($contractMatch[1], ENT_QUOTES, 'UTF-8');
                }

                $member = [
                    'tipo_documento' => strip_tags(trim($cells[1][0] ?? '')),
                    'identificacion' => strip_tags(trim($cells[1][1] ?? '')),
                    'consecutivo' => strip_tags(trim($cells[1][2] ?? '')),
                    'nombres' => strip_tags(trim($cells[1][3] ?? '')),
                    'parentesco' => strip_tags(trim($cells[1][4] ?? '')),
                    'estado_detallado' => strip_tags(trim($cells[1][5] ?? '')),
                    'documentos_faltantes' => strip_tags(trim($cells[1][6] ?? '')),
                    'detail_url' => $detailUrl,
                    'contract_url' => $contractUrl,
                ];

                // Clean up whitespace
                foreach ($member as $key => $value) {
                    if (is_string($value)) {
                        $member[$key] = preg_replace('/\s+/', ' ', trim($value));
                    }
                }

                // Handle &nbsp; values
                foreach ($member as $key => $value) {
                    if (is_string($value) && ($value === '&nbsp;' || $value === "\xC2\xA0" || $value === '')) {
                        $member[$key] = '';
                    }
                }

                if (!empty($member['identificacion'])) {
                    $members[] = $member;
                    Log::debug("SaludTotal: Found member", [
                        'identificacion' => $member['identificacion'],
                        'nombres' => $member['nombres'],
                        'detail_url' => $member['detail_url'],
                    ]);
                }
            }

            if (empty($members)) {
                return ['error' => 'No se encontraron miembros en la tabla'];
            }

            return ['members' => $members];
        }



    /**
     * Get the detail page for a specific family member.
     */
    public function getPersonDetail(string $detailUrl): ?array
    {
        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                // Build full URL if relative
                if (strpos($detailUrl, 'http') !== 0) {
                    if (strpos($detailUrl, '/') === 0) {
                        $detailUrl = 'https://transaccional.saludtotal.com.co' . $detailUrl;
                    } else {
                        $detailUrl = $this->baseUrl . '/Queries/' . ltrim($detailUrl, '/');
                    }
                }

                Log::debug("SaludTotal: Fetching detail page", ['url' => $detailUrl]);

                $response = $this->client()->get($detailUrl);
                $body = $response->body();

                $this->saveCookies();

                if ($this->isSessionExpired($response, $body)) {
                    if ($attempt < $this->maxRetries) {
                        $this->refreshSession();
                        usleep(2000000);
                        continue;
                    }
                    return null;
                }

                if ($response->successful()) {
                    // Save detail HTML for debugging (overwrite each time)
                    $debugPath = storage_path('logs/detail_response.html');
                    @file_put_contents($debugPath, $body);

                    Log::debug("SaludTotal: Detail page received", [
                        'url' => $detailUrl,
                        'body_length' => strlen($body),
                        'has_tdAge' => str_contains($body, 'tdAge'),
                        'has_tdAddress' => str_contains($body, 'tdAddress'),
                        'has_tdPhone' => str_contains($body, 'tdPhone'),
                        'has_contenidoCont' => str_contains($body, 'contenidoCont'),
                    ]);

                    $parsed = $this->parseDetailPage($body);
                    if (!empty($parsed)) {
                        return $parsed;
                    }
                    Log::warning("SaludTotal: Detail page parsed but empty", [
                        'url' => $detailUrl,
                        'body_length' => strlen($body),
                        'body_preview' => substr($body, 0, 500),
                    ]);
                }

                if ($attempt < $this->maxRetries) {
                    usleep(2000000);
                    continue;
                }
                return null;
            } catch (\Exception $e) {
                Log::error("SaludTotal: Detail error (attempt $attempt)", [
                    'url' => $detailUrl,
                    'error' => $e->getMessage(),
                ]);
                if ($attempt < $this->maxRetries) {
                    $this->refreshSession();
                    usleep(2000000);
                    continue;
                }
                return null;
            }
        }

        return null;
    }

    /**
     * Get person detail using cedula parameters directly.
     * URL format: FGDetail.aspx?bi={cedula}&bc={tipo}&con={consecutivo}&pOperation=27
     */
    public function getPersonDetailByParams(string $bi, string $bc = 'C', string $con = '0'): ?array
    {
        $url = $this->baseUrl . '/Queries/FGDetail.aspx?' . http_build_query([
            'bi' => $bi,
            'bc' => $bc,
            'con' => $con,
            'pOperation' => '27',
        ]);

        return $this->getPersonDetail($url);
    }

    /**
     * Parse the detail page HTML and extract all fields.
     * The detail page shows a table with label:value pairs like:
     *   Identificación: 1121148057
     *   Nombres: JOSE MIGUEL MENDEZ CASANOVA
     *   Fecha nacimiento (Mes/Dia/Año): 05/07/1993
     *   etc.
     */
    public function parseDetailPage(string $html): array
    {
        $data = [];

        // The detail page uses <td id="ctl00_ContentPlaceHolder1_tdXxx">value</td>
        $tdMappings = [
            'identificacion' => 'tdIdentificacion',
            'nombres' => 'tdName',
            'fecha_nacimiento' => 'tdBirthDay',
            'edad' => 'tdAge',
            'sexo' => 'tdGender',
            'antiguedad_salud_total' => 'tdSTAntiquity',
            'fecha_afiliacion' => 'tdFechaAfiliacion',
            'eps_anterior' => 'tdPreviousEPS',
            'antiguedad_otra_eps' => 'tdOtherAntiquity',
            'direccion' => 'tdAddress',
            'telefono' => 'tdPhone',
            'ciudad' => 'tdCity',
            'ips_medica_asignada' => 'tdMedicalIPS',
            'ips_odontologica_asignada' => 'tdOdontologyIPS',
        ];

        foreach ($tdMappings as $key => $tdId) {
            // Use # as regex delimiter to avoid conflict with / in HTML
            $pattern = '#id="ctl00_ContentPlaceHolder1_' . preg_quote($tdId, '#') . '"[^>]*>([^<]*)#si';
            if (preg_match($pattern, $html, $match)) {
                $value = trim(html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'));
                $value = preg_replace('/\s+/', ' ', $value);
                if (!empty($value) && $value !== '&nbsp;' && $value !== "\xC2\xA0") {
                    $data[$key] = $value;
                }
            }
        }

        Log::debug('SaludTotal: Parsed detail page', [
            'fields_found' => count($data),
            'keys' => array_keys($data),
            'missing' => array_keys(array_diff_key($tdMappings, $data)),
            'edad' => $data['edad'] ?? 'NOT FOUND',
            'direccion' => $data['direccion'] ?? 'NOT FOUND',
            'telefono' => $data['telefono'] ?? 'NOT FOUND',
        ]);

        return $data;
    }

    /**
     * Get the contract/labor detail page for a family member.
     * URL: ContratoLaboral.aspx?q=...
     */
    public function getContractDetail(string $contractUrl): ?array
    {
        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                if (strpos($contractUrl, 'http') !== 0) {
                    if (strpos($contractUrl, '/') === 0) {
                        $contractUrl = 'https://transaccional.saludtotal.com.co' . $contractUrl;
                    } else {
                        $contractUrl = $this->baseUrl . '/Queries/' . ltrim($contractUrl, '/');
                    }
                }

                Log::debug("SaludTotal: Fetching contract page", ['url' => $contractUrl]);

                $response = $this->client()->get($contractUrl);
                $body = $response->body();

                $this->saveCookies();

                if ($this->isSessionExpired($response, $body)) {
                    if ($attempt < $this->maxRetries) {
                        $this->refreshSession();
                        usleep(2000000);
                        continue;
                    }
                    return null;
                }

                if ($response->successful()) {
                    // Save for debugging
                    @file_put_contents(storage_path('logs/contract_response.html'), $body);

                    $parsed = $this->parseContractPage($body);
                    if (!empty($parsed)) {
                        return $parsed;
                    }
                    Log::warning("SaludTotal: Contract page parsed but empty", [
                        'url' => $contractUrl,
                        'body_length' => strlen($body),
                    ]);
                }

                if ($attempt < $this->maxRetries) {
                    usleep(2000000);
                    continue;
                }
                return null;
            } catch (\Exception $e) {
                Log::error("SaludTotal: Contract error (attempt $attempt)", [
                    'url' => $contractUrl,
                    'error' => $e->getMessage(),
                ]);
                if ($attempt < $this->maxRetries) {
                    $this->refreshSession();
                    usleep(2000000);
                    continue;
                }
                return null;
            }
        }
        return null;
    }

    /**
     * Parse the ContratoLaboral page HTML.
     * The page has a simple table with label/value rows:
     *   <tr><td>Identificación</td><td>N-830024478</td></tr>
     *   <tr><td>Nombre</td><td>AVIZOR SEGURIDAD LTDA</td></tr>
     *   etc.
     */
    public function parseContractPage(string $html): array
    {
        $data = [];

        // Map label text → field key
        $labelMappings = [
            'Identificación' => 'contrato_empresa_id',
            'Nombre' => 'contrato_empresa_nombre',
            'ARP' => 'contrato_arp',
            'AFP' => 'contrato_afp',
            'Cargo' => 'contrato_cargo',
            'Último Pago' => 'contrato_ultimo_pago',
            'Ingreso Base' => 'contrato_ingreso_base',
            'Cotización pagada' => 'contrato_cotizacion_pagada',
            'No. periodos mora' => 'contrato_periodos_mora',
            'Fecha de Primer Pago Exigido' => 'contrato_fecha_primer_pago',
        ];

        // Extract all table rows with two <td> cells
        if (preg_match_all('#<tr[^>]*>\s*<td[^>]*>(.*?)</td>\s*<td[^>]*>(.*?)</td>\s*</tr>#si', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $label = trim(strip_tags($match[1]));
                $value = trim(strip_tags($match[2]));
                $value = preg_replace('/\s+/', ' ', $value);

                foreach ($labelMappings as $searchLabel => $fieldKey) {
                    if (strcasecmp($label, $searchLabel) === 0 || str_contains($label, $searchLabel)) {
                        if (!empty($value) && $value !== '&nbsp;' && $value !== "\xC2\xA0") {
                            $data[$fieldKey] = $value;
                        }
                        break;
                    }
                }
            }
        }

        Log::debug('SaludTotal: Parsed contract page', [
            'fields_found' => count($data),
            'keys' => array_keys($data),
        ]);

        return $data;
    }

    /**
     * Process a single cedula: query family group + get details for matching member.
     */
    public function processCedula(string $cedula): array
    {
        $result = [
            'cedula' => $cedula,
            'status' => 'error',
            'data' => [],
            'error' => null,
        ];

        Log::info("SaludTotal: Processing cedula $cedula");

        // Query the family group
        $familyResult = $this->queryFamilyGroup($cedula);

        if (!$familyResult) {
            $result['error'] = 'Error al consultar el grupo familiar. La sesión pudo haber expirado.';
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

        // Find the member matching the cedula
        $targetMember = null;
        foreach ($familyResult['members'] as $member) {
            $memberCedula = preg_replace('/[^0-9]/', '', $member['identificacion'] ?? '');
            $searchCedula = preg_replace('/[^0-9]/', '', $cedula);
            if ($memberCedula === $searchCedula) {
                $targetMember = $member;
                break;
            }
        }

        // If exact match not found, use first member
        if (!$targetMember) {
            $targetMember = $familyResult['members'][0];
            Log::info("SaludTotal: Exact match not found for $cedula, using first member: " . ($targetMember['identificacion'] ?? 'unknown'));
        }

        $result['data'] = $targetMember;

        // Delay before detail request
        usleep($this->delay * 1000);

        // Get detail page - try detail_url first, then construct URL from params
        $detailData = null;

        if (!empty($targetMember['detail_url'])) {
            Log::debug("SaludTotal: Using detail URL from table link", ['url' => $targetMember['detail_url']]);
            $detailData = $this->getPersonDetail($targetMember['detail_url']);
        }

        // Fallback: construct the detail URL from parameters
        if (!$detailData && !empty($targetMember['identificacion'])) {
            $bc = 'C'; // Default: Cedula de Ciudadania
            $tipoDoc = strtoupper(trim($targetMember['tipo_documento'] ?? ''));
            if (str_contains($tipoDoc, 'EXTRANJERIA') || str_contains($tipoDoc, 'EXTRANJERA')) $bc = 'E';
            elseif (str_contains($tipoDoc, 'TARJETA')) $bc = 'T';
            elseif (str_contains($tipoDoc, 'REGISTRO')) $bc = 'R';
            elseif (str_contains($tipoDoc, 'PASAPORTE')) $bc = 'P';

            Log::debug("SaludTotal: Constructing detail URL from params", [
                'bi' => $targetMember['identificacion'],
                'bc' => $bc,
                'con' => $targetMember['consecutivo'] ?? '0',
            ]);

            $detailData = $this->getPersonDetailByParams(
                trim($targetMember['identificacion']),
                $bc,
                trim($targetMember['consecutivo'] ?? '0')
            );
        }

        if ($detailData && !empty($detailData)) {
            $result['data'] = array_merge($result['data'], $detailData);
            $result['status'] = 'success';
            Log::info("SaludTotal: Successfully processed $cedula with full detail");
        } else {
            // We still have the basic info from the family group table
            $result['status'] = 'success';
            $result['error'] = 'Información parcial (sin detalle individual)';
            Log::info("SaludTotal: Processed $cedula with partial info (no detail page)");
        }

        // Step 3: Get contract/labor detail (ContratoLaboral.aspx)
        if (!empty($targetMember['contract_url'])) {
            usleep($this->delay * 1000);

            Log::debug("SaludTotal: Fetching contract detail", ['url' => $targetMember['contract_url']]);
            $contractData = $this->getContractDetail($targetMember['contract_url']);

            if ($contractData && !empty($contractData)) {
                $result['data'] = array_merge($result['data'], $contractData);
                Log::info("SaludTotal: Contract data obtained for $cedula", ['fields' => count($contractData)]);
            } else {
                Log::info("SaludTotal: No contract data for $cedula (may not have active contract)");
            }
        } elseif (!empty($targetMember['detail_url'])) {
            // Try constructing contract URL from detail URL (same q= parameter)
            $contractUrl = str_replace('FGDetail.aspx', 'ContratoLaboral.aspx', $targetMember['detail_url']);
            if ($contractUrl !== $targetMember['detail_url']) {
                usleep($this->delay * 1000);

                Log::debug("SaludTotal: Trying contract URL from detail URL", ['url' => $contractUrl]);
                $contractData = $this->getContractDetail($contractUrl);

                if ($contractData && !empty($contractData)) {
                    $result['data'] = array_merge($result['data'], $contractData);
                    Log::info("SaludTotal: Contract data obtained for $cedula (from detail URL)");
                }
            }
        }

        return $result;
    }

    /**
     * Check if the session is currently active.
     */
    public function isSessionActive(): bool
    {
        return $this->sessionActive;
    }

    public function getDelay(): int
    {
        return $this->delay;
    }
}
