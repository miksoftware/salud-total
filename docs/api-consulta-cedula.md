# API Salud Total — Consulta de Afiliado por Cédula

## Descripción

Retorna el **historial completo** de consultas realizadas a Salud Total para un número de cédula específico, ordenado del registro **más reciente al más antiguo**. Solo se incluyen consultas con estado `success`.

---

## Endpoint

```
GET /api/consulta/cedula/{cedula}
```

### Parámetros de ruta

| Parámetro | Tipo   | Requerido | Descripción                     |
|-----------|--------|-----------|---------------------------------|
| `cedula`  | string | Sí        | Número de cédula (solo dígitos) |

### Autenticación

Requiere token **Bearer** de Sanctum en el header de la petición.

```
Authorization: Bearer <token>
```

---

## Ejemplo de petición

```http
GET /api/consulta/cedula/1234567890
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
Accept: application/json
```

---

## Respuestas

### 200 — Consulta exitosa

```json
{
  "success": true,
  "message": "Consulta exitosa.",
  "total": 2,
  "data": [
    {
      "cedula": "1234567890",
      "tipo_documento": "CC",
      "identificacion": "1234567890",
      "consecutivo": null,
      "nombres": "LAURA PATRICIA MENDOZA RÍOS",
      "parentesco": "COTIZANTE",
      "estado_detallado": "ACTIVO - COTIZANTE",
      "documentos_faltantes": null,
      "fecha_nacimiento": "1988-11-05",
      "edad": 37,
      "sexo": "F",
      "antiguedad_salud_total": "5 AÑOS 3 MESES",
      "fecha_afiliacion": "2021-01-15",
      "eps_anterior": "SURA EPS",
      "antiguedad_otra_eps": null,
      "direccion": "CRA 45 # 100-20 APTO 301",
      "telefono": "3156789012",
      "ciudad": "BOGOTÁ D.C.",
      "ips_medica_asignada": "CLÍNICA SALUD TOTAL NORTE",
      "ips_odontologica_asignada": "ODONTOLOGÍA SALUD TOTAL",
      "contrato_empresa_id": null,
      "contrato_empresa_nombre": "EMPRESA XYZ S.A.S.",
      "contrato_arp": null,
      "contrato_afp": null,
      "contrato_cargo": null,
      "contrato_ultimo_pago": null,
      "contrato_ingreso_base": null,
      "contrato_cotizacion_pagada": null,
      "contrato_periodos_mora": null,
      "contrato_fecha_primer_pago": null,
      "status": "success",
      "error_message": null,
      "consultado_en": "2026-04-27T10:30:00+00:00"
    },
    {
      "cedula": "1234567890",
      "tipo_documento": "CC",
      "identificacion": "1234567890",
      "consecutivo": null,
      "nombres": "LAURA PATRICIA MENDOZA RÍOS",
      "parentesco": "COTIZANTE",
      "estado_detallado": "ACTIVO - COTIZANTE",
      "documentos_faltantes": null,
      "fecha_nacimiento": "1988-11-05",
      "edad": 37,
      "sexo": "F",
      "antiguedad_salud_total": "5 AÑOS 2 MESES",
      "fecha_afiliacion": "2021-01-15",
      "eps_anterior": "SURA EPS",
      "antiguedad_otra_eps": null,
      "direccion": "CRA 45 # 100-20 APTO 301",
      "telefono": "3156789012",
      "ciudad": "BOGOTÁ D.C.",
      "ips_medica_asignada": "CLÍNICA SALUD TOTAL NORTE",
      "ips_odontologica_asignada": "ODONTOLOGÍA SALUD TOTAL",
      "contrato_empresa_id": null,
      "contrato_empresa_nombre": "EMPRESA XYZ S.A.S.",
      "contrato_arp": null,
      "contrato_afp": null,
      "contrato_cargo": null,
      "contrato_ultimo_pago": null,
      "contrato_ingreso_base": null,
      "contrato_cotizacion_pagada": null,
      "contrato_periodos_mora": null,
      "contrato_fecha_primer_pago": null,
      "status": "success",
      "error_message": null,
      "consultado_en": "2026-03-20T09:00:00+00:00"
    }
  ]
}
```

### 404 — Sin resultados

```json
{
  "success": false,
  "message": "No se encontraron resultados para la cédula proporcionada.",
  "data": null
}
```

---

## Descripción de campos del JSON de respuesta

### Nivel raíz

| Campo     | Tipo    | Descripción                                                    |
|-----------|---------|----------------------------------------------------------------|
| `success` | boolean | `true` si la operación fue exitosa, `false` en caso contrario |
| `message` | string  | Mensaje descriptivo del resultado                              |
| `total`   | integer | Cantidad total de registros retornados                         |
| `data`    | array   | Arreglo de objetos con el historial de consultas               |

### Objeto dentro de `data[]`

| Campo                      | Tipo            | Descripción                                                                            |
|----------------------------|-----------------|----------------------------------------------------------------------------------------|
| `cedula`                   | string          | Número de documento del afiliado                                                       |
| `tipo_documento`           | string / null   | Tipo de documento (ej. `CC`, `TI`, `CE`, `PA`)                                        |
| `identificacion`           | string / null   | Número de identificación tal como aparece en el portal de Salud Total                 |
| `consecutivo`              | string / null   | Consecutivo interno de Salud Total                                                     |
| `nombres`                  | string / null   | Nombre completo del afiliado                                                           |
| `parentesco`               | string / null   | Relación del afiliado con el cotizante (ej. `COTIZANTE`, `BENEFICIARIO`)               |
| `estado_detallado`         | string / null   | Estado de afiliación con descripción extendida (ej. `ACTIVO - COTIZANTE`)              |
| `documentos_faltantes`     | string / null   | Lista de documentos faltantes requeridos por la EPS                                    |
| `fecha_nacimiento`         | string / null   | Fecha de nacimiento en formato `YYYY-MM-DD`                                            |
| `edad`                     | integer / null  | Edad del afiliado en años al momento de la consulta                                    |
| `sexo`                     | string / null   | Sexo del afiliado (`M` = Masculino, `F` = Femenino)                                   |
| `antiguedad_salud_total`   | string / null   | Tiempo de permanencia como afiliado en Salud Total (ej. `5 AÑOS 3 MESES`)             |
| `fecha_afiliacion`         | string / null   | Fecha de inicio de afiliación en Salud Total (`YYYY-MM-DD`)                            |
| `eps_anterior`             | string / null   | Nombre de la EPS de la que proviene el afiliado. `null` si no aplica                  |
| `antiguedad_otra_eps`      | string / null   | Antigüedad registrada en la EPS anterior                                               |
| `direccion`                | string / null   | Dirección de residencia registrada                                                     |
| `telefono`                 | string / null   | Teléfono de contacto registrado                                                        |
| `ciudad`                   | string / null   | Ciudad de residencia registrada                                                        |
| `ips_medica_asignada`      | string / null   | IPS médica primaria asignada al afiliado                                               |
| `ips_odontologica_asignada`| string / null   | IPS odontológica asignada al afiliado                                                  |
| `contrato_empresa_id`      | string / null   | Identificación de la empresa vinculada al contrato                                     |
| `contrato_empresa_nombre`  | string / null   | Nombre de la empresa empleadora vinculada al contrato del cotizante. `null` si no aplica |
| `contrato_arp`             | string / null   | ARP asociada al contrato                                                               |
| `contrato_afp`             | string / null   | AFP asociada al contrato                                                               |
| `contrato_cargo`           | string / null   | Cargo registrado en el contrato                                                        |
| `contrato_ultimo_pago`     | string / null   | Fecha del último pago registrado                                                       |
| `contrato_ingreso_base`    | string / null   | IBC (Ingreso Base de Cotización) registrado                                            |
| `contrato_cotizacion_pagada`| string / null  | Valor de la última cotización pagada                                                   |
| `contrato_periodos_mora`   | string / null   | Número de periodos en mora registrados                                                 |
| `contrato_fecha_primer_pago`| string / null  | Fecha en que se exigió el primer pago                                                  |
| `status`                   | string          | Estado final de la consulta (ej. `success`, `error`)                                   |
| `error_message`            | string / null   | Detalle del error si el status es `error`                                              |
| `consultado_en`            | string ISO 8601 | Fecha y hora en que se realizó la consulta (UTC)                                       |

---

## Notas

- Los registros se ordenan de **más reciente a más antiguo** según el campo `consultado_en`.
- Solo se retornan consultas con `status = 'success'`.
- Si la cédula no tiene registros en la base de datos, se retorna HTTP `404`.
- El campo `cedula` en la URL solo acepta dígitos numéricos; cualquier otro carácter retorna `404` automáticamente.
