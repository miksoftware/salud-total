# Proyecto: Salud Total - Consultor Automático

## Descripción
Aplicación Laravel para consulta masiva de afiliados en el portal transaccional de Salud Total EPS.
El usuario sube un archivo Excel/CSV con cédulas, el sistema consulta cada una contra el portal y exporta los resultados.

## Stack Tecnológico
- PHP 8.2+ / Laravel 12
- SQLite (por defecto)
- Maatwebsite/Excel 3.1 (importación/exportación)
- Guzzle HTTP (scraping del portal Salud Total)
- Frontend: Blade + CSS custom (tema oscuro), JavaScript vanilla con fetch API
- Servidor: Laragon (Windows) con Apache

## Arquitectura
- **ConsultaController**: Orquestador principal (upload, process, export, status)
- **SaludTotalService**: Cliente HTTP con manejo de sesión ASP.NET, cookies, retry automático
- **CedulasImport**: Importador Maatwebsite Excel (ToCollection)
- **ResultsExport**: Exportador con estilos (FromQuery, WithHeadings, WithMapping, WithStyles)
- **Modelos**: Consulta (lote) y ConsultaResult (resultado individual con 20+ campos)

## Flujo Principal
1. Usuario sube archivo Excel/CSV con columna "cedula"
2. CedulasImport parsea las cédulas (limpia, deduplica, valida mínimo 5 dígitos)
3. Se crea un registro Consulta + N registros ConsultaResult (status: pending)
4. Frontend llama `/process/{id}` en loop (una cédula a la vez)
5. SaludTotalService: init sesión → navegar a FamiliarGroup.aspx → POST formulario → parsear tabla → obtener detalle
6. Resultados se guardan en BD y se muestran en tiempo real
7. Al finalizar, se puede exportar a Excel/CSV

## Rutas
- `GET /` → index (vista principal)
- `POST /upload` → subir archivo de cédulas
- `POST /process/{id}` → procesar siguiente cédula pendiente
- `GET /status/{id}` → estado de la consulta
- `GET /export/{id}` → exportar resultados a Excel/CSV
- `POST /test-connection` → probar conexión al portal
- `POST /resume/{id}` → reanudar consulta interrumpida

## Configuración
- `config/salud_total.php`: URLs del portal, delay entre requests, timeout
- Variables de entorno: SALUD_TOTAL_BASE_URL, SALUD_TOTAL_SESSION_URL, SALUD_TOTAL_DELAY, SALUD_TOTAL_TIMEOUT

## Requisitos PHP
- Extensión `zip` (php_zip) habilitada para archivos .xlsx/.xls
- Extensión `gd` o `imagick` (opcional, para PhpSpreadsheet)
- Si php_zip no está disponible, el sistema acepta CSV como alternativa

## Convenciones de Código
- Idioma del código: inglés para nombres de clases/métodos, español para mensajes al usuario
- Respuestas JSON con estructura: { success: bool, message: string, ... }
- Manejo de errores con try/catch y mensajes descriptivos en español
- Logs con prefijo "SaludTotal:" para facilitar filtrado
- Frontend sin frameworks JS, solo vanilla JavaScript con fetch API
- CSS custom con variables CSS (tema oscuro estilo glassmorphism)

## Notas Importantes
- El portal Salud Total usa ASP.NET con ViewState, requiere parseo de campos ocultos
- La sesión del portal expira frecuentemente, el servicio tiene auto-refresh
- Retry automático (3 intentos) en cada operación HTTP
- Delay configurable entre requests para evitar bloqueo (default: 1500ms)
- El frontend procesa cédulas una a una via AJAX para mostrar progreso en tiempo real
