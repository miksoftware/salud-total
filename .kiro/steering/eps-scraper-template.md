---
inclusion: manual
---

# Plantilla: Scraper de Portal EPS Colombiana

Este documento es una guía reutilizable para crear aplicaciones de consulta masiva de afiliados en portales transaccionales de EPS colombianas. Está basado en la implementación exitosa del scraper de Salud Total EPS.

Úsalo como prompt/contexto al iniciar un nuevo proyecto para otra EPS.

---

## 1. Descripción General

La aplicación permite:
- Subir un archivo Excel/CSV con números de cédula
- Consultar cada cédula contra el portal transaccional de la EPS (scraping)
- Mostrar resultados en tiempo real mientras se procesan
- Exportar todos los resultados a Excel/CSV
- Buscar resultados por cédula en un panel de consultas
- Gestionar usuarios con roles (admin y consulta)

## 2. Stack Tecnológico

| Componente | Tecnología |
|---|---|
| Backend | PHP 8.2+ / Laravel 12 |
| Base de datos | SQLite (archivo único, sin servidor) |
| Import/Export Excel | Maatwebsite/Excel 3.1 |
| HTTP Client (scraping) | Guzzle HTTP |
| Frontend | Blade + CSS custom (tema oscuro glassmorphism) + JavaScript vanilla con fetch API |
| Servidor local | Laragon (Windows) con Apache |
| Producción | Docker (PHP-FPM + Nginx) en Ubuntu VPS |

**Sin frameworks JS.** Todo el frontend es vanilla JavaScript con fetch API.

## 3. Arquitectura de Archivos

```
app/
├── Http/Controllers/
│   ├── AuthController.php          # Login/logout
│   ├── UserController.php          # CRUD usuarios
│   └── ConsultaController.php      # Orquestador principal
├── Http/Middleware/
│   └── RoleMiddleware.php          # Control de acceso por rol
├── Services/
│   └── {Eps}Service.php            # Cliente HTTP scraping (el core)
├── Imports/
│   └── CedulasImport.php           # Importador Excel (ToCollection)
├── Exports/
│   └── ResultsExport.php           # Exportador Excel con estilos
├── Models/
│   ├── User.php                    # Con campo role (admin/consulta)
│   ├── Consulta.php                # Lote de consulta
│   └── ConsultaResult.php          # Resultado individual (20+ campos)
config/
└── {eps}.php                       # URLs del portal, delay, timeout
resources/views/
├── layouts/app.blade.php           # Layout con navbar por rol
├── auth/login.blade.php            # Login
├── users/index.blade.php           # CRUD usuarios (admin)
└── consultas/
    ├── index.blade.php             # Subir archivo + procesar (admin)
    ├── search.blade.php            # Buscar por cédula (ambos roles)
    └── files.blade.php             # Listar archivos exportables
```

## 4. Flujo de Scraping (3 pasos)

Este es el patrón central. Cada EPS tendrá URLs y HTML diferentes, pero el flujo es el mismo:

### Paso 1: Consulta de Grupo Familiar
1. **Iniciar sesión** en el portal (GET a URL mágica con token)
2. **Navegar** a la página del formulario de consulta (GET con parámetros)
3. **Parsear campos ocultos** del formulario (ViewState, EventValidation, etc.)
4. **POST** el formulario con tipo de documento + número de cédula
5. **Parsear tabla de resultados** → extraer miembros del grupo familiar
6. De cada fila extraer: tipo_documento, identificación, nombres, parentesco, estado, y **URLs de detalle**

### Paso 2: Detalle del Afiliado
1. **GET** a la URL de detalle extraída de la tabla (ej: FGDetail.aspx?q=...)
2. **Parsear** la página de detalle → extraer campos individuales
3. Campos típicos: fecha_nacimiento, edad, sexo, dirección, teléfono, ciudad, IPS asignada, etc.

### Paso 3: Detalle del Contrato Laboral
1. **GET** a la URL de contrato extraída de la tabla (ej: ContratoLaboral.aspx?q=...)
2. **Parsear** tabla simple de label/valor
3. Campos típicos: empresa, ARP, AFP, cargo, último pago, ingreso base, cotización, periodos mora

### Diagrama del flujo:
```
initSession() → navigateToFormPage() → queryFamilyGroup(cedula)
                                              ↓
                                    parseFamilyGroupTable(html)
                                              ↓
                                    getPersonDetail(detail_url)
                                              ↓
                                    parseDetailPage(html)
                                              ↓
                                    getContractDetail(contract_url)
                                              ↓
                                    parseContractPage(html)
```

## 5. Patrón del Servicio HTTP (el core del scraping)

```php
class EpsService
{
    protected string $baseUrl;
    protected string $sessionInitUrl;
    protected CookieJar $cookieJar;      // Guzzle CookieJar
    protected int $timeout;
    protected int $delay;                 // ms entre requests
    protected int $maxRetries = 3;

    // Cookies se persisten en Cache (Laravel) por 30 min
    // Esto permite que la sesión sobreviva entre requests HTTP del frontend
}
```

### Métodos clave del servicio:

| Método | Responsabilidad |
|---|---|
| `initSession()` | GET a URL mágica para obtener cookies de sesión |
| `ensureSession()` | Verificar sesión activa, reiniciar si expiró |
| `refreshSession()` | Limpiar cookies + reiniciar sesión |
| `isSessionExpired($response, $body)` | Detectar si la sesión expiró |
| `saveCookies()` / `restoreCookies()` | Persistir cookies en Cache de Laravel |
| `parseAspNetFields($html)` | Extraer TODOS los inputs hidden del formulario |
| `navigateToFormPage()` | GET a la página del formulario |
| `queryFamilyGroup($cedula)` | POST formulario + parsear tabla de resultados |
| `parseFamilyGroupTable($html)` | Extraer filas de la tabla DataGrid |
| `getPersonDetail($url)` | GET página de detalle individual |
| `parseDetailPage($html)` | Extraer campos del detalle |
| `getContractDetail($url)` | GET página de contrato laboral |
| `parseContractPage($html)` | Extraer campos del contrato |
| `processCedula($cedula)` | Orquestador: ejecuta los 3 pasos para una cédula |

## 6. Lecciones Aprendidas y Trampas Comunes

### 6.1 Regex: Usar `#` como delimitador, NUNCA `/`
El HTML contiene `</td>`, `</tr>`, URLs con `/`. Si usas `/` como delimitador de regex, se rompe.
```php
// ❌ MAL - el / en </td> rompe el regex
preg_match('/id="tdName"[^>]*>([^<]*)/', $html, $match);

// ✅ BIEN - usar # como delimitador
preg_match('#id="tdName"[^>]*>([^<]*)#si', $html, $match);
```

### 6.2 ASP.NET ViewState
Los portales ASP.NET requieren enviar TODOS los campos hidden del formulario en el POST, no solo ViewState. Parsear con:
```php
preg_match_all('/<input[^>]*type="hidden"[^>]*>/si', $html, $hiddenInputs);
```
Y extraer name/value de cada uno. Campos críticos: `__VIEWSTATE`, `__VIEWSTATEGENERATOR`, `__EVENTVALIDATION`, `__EVENTTARGET`, `__EVENTARGUMENT`.

### 6.3 Detección de sesión expirada
NO confiar en la presencia de `TimeOutSesion` o `inicio.aspx` en el HTML — estos aparecen en TODAS las páginas del portal (son parte del menú/scripts). Solo detectar expiración real:
- Status 401/403
- Texto "no existen datos validos para cargar la pagina"
- Texto "Session has expired" / "sesion ha expirado"
- Respuesta muy corta (<2000 chars) con redirect a inicio

### 6.4 Manejo de redirects en POST
Guzzle con `allow_redirects: true` puede perder cookies/contexto en redirects POST→GET. Mejor usar `allow_redirects: false` y seguir redirects manualmente:
```php
$guzzle = new \GuzzleHttp\Client([
    'cookies' => $this->cookieJar,
    'allow_redirects' => false,
]);
$response = $guzzle->post($url, ['form_params' => $data]);
if (in_array($response->getStatusCode(), [301, 302, 303, 307])) {
    $redirectUrl = $response->getHeaderLine('Location');
    // Seguir manualmente con GET
}
```

### 6.5 Retry automático
Cada operación HTTP debe tener retry (3 intentos). Entre reintentos: refresh de sesión + sleep de 2 segundos.

### 6.6 Delay entre requests
Configurar delay entre requests (default 1500ms) para evitar bloqueo/rate-limiting del portal. Usar `usleep($this->delay * 1000)`.

### 6.7 Persistencia de cookies
Las cookies del portal deben persistir entre requests HTTP del frontend (cada cédula es un request separado). Usar `Cache::put()` de Laravel con TTL de 30 minutos.

### 6.8 User-Agent realista
Siempre enviar un User-Agent de navegador real. Sin esto, muchos portales rechazan las peticiones.

## 7. Patrón del Frontend (Procesamiento en Tiempo Real)

El frontend procesa cédulas **una a una** via AJAX en un loop. Esto permite:
- Mostrar progreso en tiempo real
- No hacer timeout en el servidor (cada request es rápido)
- Poder pausar/reanudar

```javascript
async function processLoop(consultaId) {
    while (true) {
        const response = await fetch(`/process/${consultaId}`, { method: 'POST' });
        const data = await response.json();

        if (data.completed) break;

        // Actualizar UI: agregar fila a tabla, actualizar barra de progreso
        updateProgress(data.stats);
        addResultRow(data.result);
    }
}
```

Componentes del frontend:
- **Área de upload** con drag & drop
- **Barra de progreso** con animación shimmer
- **Tabla de resultados** que crece en tiempo real
- **Stats cards** (total, exitosos, fallidos, pendientes)
- **Log en vivo** estilo consola

## 8. Sistema de Autenticación y Roles

### Dos roles:
| Rol | Permisos |
|---|---|
| `admin` | Subir archivos, procesar, gestionar usuarios, consultar, exportar |
| `consulta` | Solo buscar por cédula y exportar archivos existentes |

### Implementación:
- Campo `role` en tabla `users` (string: 'admin' o 'consulta')
- `RoleMiddleware` que verifica `auth()->user()->role`
- Método `isAdmin()` en modelo User
- Navbar condicional según rol
- Rutas agrupadas con middleware `role:admin`
- Seeder para crear usuario admin inicial

## 9. Estructura de Rutas

```php
// Auth
Route::get('/login', [AuthController::class, 'showLogin']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout']);

Route::middleware('auth')->group(function () {
    // Dashboard redirect por rol
    Route::get('/', fn() => auth()->user()->isAdmin()
        ? redirect()->route('admin.consultas')
        : redirect()->route('consultas.search'));

    // Admin only
    Route::middleware('role:admin')->group(function () {
        Route::get('/admin/consultas', ...);     // Vista subir archivo
        Route::post('/upload', ...);              // Subir Excel/CSV
        Route::post('/process/{id}', ...);        // Procesar siguiente cédula
        Route::get('/status/{id}', ...);          // Estado de consulta
        Route::get('/export/{id}', ...);          // Exportar a Excel
        Route::post('/test-connection', ...);     // Probar conexión al portal
        Route::post('/resume/{id}', ...);         // Reanudar consulta
        Route::delete('/consulta/{id}', ...);     // Eliminar consulta
        // CRUD usuarios
        Route::get('/usuarios', ...);
        Route::post('/usuarios', ...);
        Route::put('/usuarios/{id}', ...);
        Route::delete('/usuarios/{id}', ...);
    });

    // Ambos roles
    Route::get('/consultas', ...);                // Buscar por cédula
    Route::get('/consultas/buscar', ...);         // API búsqueda
    Route::get('/consultas/archivos', ...);       // Listar archivos
    Route::get('/consultas/archivos/{id}/export', ...); // Exportar archivo
});
```

## 10. Modelos y Base de Datos

### Tabla `consultas` (lote de consulta)
```
id, filename, total_cedulas, processed, successful, failed, status, timestamps
```

### Tabla `consulta_results` (resultado individual)
```
id, consulta_id, cedula, status, error_message,
// Datos del grupo familiar:
tipo_documento, identificacion, consecutivo, nombres, parentesco,
estado_detallado, documentos_faltantes,
// Datos del detalle:
fecha_nacimiento, edad, sexo, antiguedad_salud_total, fecha_afiliacion,
eps_anterior, antiguedad_otra_eps, direccion, telefono, ciudad,
ips_medica_asignada, ips_odontologica_asignada,
// Datos del contrato:
contrato_empresa_id, contrato_empresa_nombre, contrato_arp, contrato_afp,
contrato_cargo, contrato_ultimo_pago, contrato_ingreso_base,
contrato_cotizacion_pagada, contrato_periodos_mora, contrato_fecha_primer_pago,
timestamps
```

## 11. Configuración por EPS

Archivo `config/{eps}.php`:
```php
return [
    'base_url' => env('EPS_BASE_URL', 'https://portal.eps.com.co/Transaccional'),
    'session_init_url' => env('EPS_SESSION_URL', 'https://portal.eps.com.co/...'),
    'delay_between_requests' => env('EPS_DELAY', 1500),
    'timeout' => env('EPS_TIMEOUT', 30),
];
```

## 12. Qué Personalizar por Cada EPS

Al crear un scraper para una nueva EPS, estos son los puntos que cambian:

| Qué cambia | Dónde |
|---|---|
| URLs del portal (base, sesión, formulario, detalle, contrato) | `config/{eps}.php` y `.env` |
| Nombre del servicio | `app/Services/{Eps}Service.php` |
| Método de inicio de sesión (URL mágica, login form, token, etc.) | `initSession()` |
| Estructura HTML del formulario de consulta | `navigateToFormPage()` + `queryFamilyGroup()` |
| IDs de campos del formulario (ej: `ctl00$ContentPlaceHolder1$txtIdentification`) | `queryFamilyGroup()` |
| Estructura HTML de la tabla de resultados | `parseFamilyGroupTable()` |
| Estructura HTML de la página de detalle | `parseDetailPage()` |
| Estructura HTML de la página de contrato | `parseContractPage()` |
| Campos extraídos (pueden ser más o menos según la EPS) | Modelos, migraciones, export, vistas |
| Indicadores de sesión expirada | `isSessionExpired()` |
| Tipo de tecnología del portal (ASP.NET, Java, PHP, etc.) | Afecta el manejo de campos ocultos y cookies |

**Lo que NO cambia:**
- Arquitectura general (Controller → Service → Models)
- Patrón de procesamiento (una cédula a la vez via AJAX)
- Sistema de auth/roles
- Import/Export Excel
- Frontend (solo ajustar columnas de tabla)
- Deploy (Docker + SQLite)

## 13. Deploy en Producción

### Docker Compose (PHP-FPM + Nginx)
- Contenedores: `{proyecto}_php` y `{proyecto}_nginx`
- SQLite como BD (sin contenedor de BD)
- Script `deploy.sh` automatizado:
  1. Git pull
  2. Composer install (--no-dev)
  3. Crear SQLite si no existe + migraciones + seeder admin
  4. Permisos (storage, bootstrap/cache, database)
  5. Cache (config, routes, views)
  6. Restart servicios

### Permisos críticos en producción:
```bash
chown -R www-data:www-data storage bootstrap/cache database
chmod -R 775 storage bootstrap/cache database
chmod 664 database/database.sqlite
```

## 14. Convenciones de Código

- Nombres de clases/métodos: **inglés**
- Mensajes al usuario: **español**
- Respuestas JSON: `{ success: bool, message: string, ... }`
- Manejo de errores: try/catch con mensajes descriptivos en español
- Logs: prefijo `"{Eps}:"` para filtrado fácil
- CSS: variables CSS con tema oscuro glassmorphism, todo inline en el layout
- JS: vanilla, sin frameworks, fetch API

## 15. Checklist para Nueva EPS

1. [ ] Crear proyecto Laravel nuevo con SQLite
2. [ ] Instalar dependencias: `maatwebsite/excel`, `guzzlehttp/guzzle`
3. [ ] Investigar el portal de la EPS:
   - ¿Qué tecnología usa? (ASP.NET, Java, PHP)
   - ¿Cómo se inicia sesión? (URL mágica, login form, token)
   - ¿Cuál es la URL del formulario de consulta?
   - ¿Qué campos tiene el formulario?
   - ¿Cómo es la tabla de resultados? (IDs, clases, estructura)
   - ¿Hay página de detalle? ¿Cómo se accede?
   - ¿Hay página de contrato? ¿Cómo se accede?
4. [ ] Crear `config/{eps}.php` con URLs
5. [ ] Crear `{Eps}Service.php` adaptando los métodos de parseo
6. [ ] Crear migraciones con los campos específicos de la EPS
7. [ ] Crear modelos (Consulta, ConsultaResult)
8. [ ] Crear CedulasImport y ResultsExport
9. [ ] Crear ConsultaController (copiar patrón, ajustar campos)
10. [ ] Crear vistas (copiar layout, ajustar columnas de tablas)
11. [ ] Crear sistema de auth (AuthController, UserController, RoleMiddleware)
12. [ ] Crear rutas con middleware
13. [ ] Crear AdminSeeder
14. [ ] Probar en local con Laragon
15. [ ] Configurar Docker + deploy.sh para producción
