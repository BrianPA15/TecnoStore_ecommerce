# TecnoStore

Plataforma de ecommerce para venta de productos informáticos construida sobre WordPress con un tema personalizado. Integra un sistema de gestión de almacén (Node.js + SQLite) y un ERP Dolibarr para la gestión de inventario y facturación.

---

## Requisitos

| Componente | Versión                 |
|------------|-------------------------|
| WordPress  | **4.7.1** (obligatoria) |
| PHP        | 7.0 o superior          |
| MySQL      | 5.6 o superior          |
| WP-CLI     | cualquier versión estable |

> **Importante:** el tema `tecnostore` requiere exactamente WordPress 4.7.1. El propio panel de administración verifica esta versión al acceder a la configuración. Usar una versión distinta causará errores de compatibilidad.

---

## Estructura del repositorio

```
tecnostore/
  ├── inc/
  │   ├── admin-settings.php       ← panel de configuración (almacén + ERP)
  │   ├── admin-orders.php         ← listado de pedidos en el admin
  │   ├── endpoints.php            ← endpoints REST (sincronización + checkout)
  │   └── dolibarr-integration.php ← integración con la API de Dolibarr
  ├── assets/
  │   ├── css/main.css
  │   └── js/main.js               ← carrito, checkout, toasts
  └── seed/
      ├── setup-defaults.php       ← usuario por defecto + páginas
      └── seed-products.php        ← catálogo de productos
```

---

## Instalación

### 1. Descargar WordPress 4.7.1

```bash
wget https://wordpress.org/wordpress-4.7.1.tar.gz
tar -xzf wordpress-4.7.1.tar.gz
```

Configura la base de datos y el archivo `wp-config.php` siguiendo el proceso estándar de instalación de WordPress.

### 2. Instalar el tema

Copia la carpeta `tecnostore` en el directorio de temas de WordPress:

```bash
cp -r wordpress/themes/tecnostore /var/www/html/wp-content/themes/
```

Activa el tema desde el panel de administración:

```
Apariencia > Temas > TecnoStore > Activar
```

Al activarse, el tema crea automáticamente las páginas del flujo de compra: **Carrito**, **Checkout** y **Pedido completado**.

---

## Carga inicial de datos

Los scripts de seed se encuentran en `seed/` y se ejecutan con WP-CLI desde la raíz de la instalación de WordPress.

### 1. Setup inicial (usuario por defecto + páginas)

```bash
wp eval-file wp-content/themes/tecnostore/seed/setup-defaults.php
```

Crea:
- **Usuario administrador del equipo de desarrollo**:
  - Login: `developer`
  - Contraseña: `masterjoyfe2026`
  - Rol: administrador
- Las páginas de navegación del flujo de compra si no existen ya.

### 2. Catálogo de productos

```bash
wp eval-file wp-content/themes/tecnostore/seed/seed-products.php
```

Carga el catálogo completo de productos informáticos organizados en 6 categorías:

- Portátiles
- Componentes
- Monitores
- Periféricos
- Almacenamiento
- Redes

Ambos scripts son idempotentes: pueden ejecutarse múltiples veces sin duplicar datos.

### Orden de ejecución recomendado

```bash
# 1. Setup inicial
wp eval-file wp-content/themes/tecnostore/seed/setup-defaults.php

# 2. Catálogo completo
wp eval-file wp-content/themes/tecnostore/seed/seed-products.php
```

---

## Configuración del panel de administración

Una vez activo el tema, accede a **Panel de administración > TecnoStore** para configurar las integraciones.

### Conexión con el sistema de almacén

Introduce la URL base y el token JWT de la API del almacén. El token se genera desde la propia aplicación de almacén en **Integración WP > Generar token**.

| Campo              | Descripción                                        |
|--------------------|----------------------------------------------------|
| URL base del almacén | URL raíz del servidor de almacén (sin rutas)      |
| JWT Token          | Token de autenticación para la API del almacén     |

### Conexión con el ERP — Dolibarr

Introduce la URL y la API Key de la instancia de Dolibarr. La API Key se obtiene en Dolibarr desde **Usuarios > Tu usuario > Clave API REST**.

| Campo           | Descripción                                            |
|-----------------|--------------------------------------------------------|
| URL base de Dolibarr | URL raíz de Dolibarr, sin barra final             |
| API Key (DOLAPIKEY) | Clave de API para autenticarse en el ERP          |

---

## Flujo de integración

### WordPress → ERP (pedidos / facturación)

Cuando un cliente completa una compra en la tienda:

1. El pedido se guarda en la base de datos de WordPress.
2. Se notifica al sistema de almacén (`POST /api/sales`) para registrar el movimiento de stock.
3. Se crea automáticamente una factura en Dolibarr asociada al cliente (buscado por email; si no existe, se crea como nuevo tercero).

La respuesta al cliente no se bloquea si alguna de las integraciones falla: los errores quedan registrados en la respuesta del endpoint pero no afectan al proceso de compra.

### Almacén → ERP (productos)

Cuando se crea un producto nuevo en la aplicación de almacén, este se sincroniza automáticamente con el catálogo de Dolibarr usando el SKU como referencia. Si el producto ya existe en el ERP, no se duplica.

### Almacén → WordPress (inventario)

El tema expone un endpoint REST para sincronizar el catálogo de productos desde el almacén hacia WordPress:

```
GET /wp-json/tecnostore/v1/createProducts
```

**Este endpoint debe configurarse obligatoriamente como tarea cron en el servidor.** Sin él, los productos creados o modificados en el almacén no se reflejarán en la tienda online, y el catálogo quedará desactualizado.

---

## Configuración del cron de sincronización

> **Paso obligatorio.** La sincronización de inventario no es automática: depende de que esta tarea esté activa en el servidor. Configúrala inmediatamente después de desplegar WordPress.

### Por qué es necesario

La tienda WordPress y la aplicación de almacén son sistemas independientes. El catálogo de productos que ve el cliente en la tienda se construye a partir de los datos almacenados en WordPress, no directamente desde el almacén. Para que ambos sistemas estén sincronizados — precios, stock, nuevos productos — es necesario ejecutar periódicamente el endpoint de sincronización, que lee el inventario del almacén y actualiza WordPress en consecuencia.

Si el cron no está activo:
- Los productos nuevos creados en el almacén **no aparecerán en la tienda**.
- Los cambios de precio o stock **no se propagarán**.
- La web mostrará información desactualizada a los clientes.

### Configuración en Linux (crontab)

Edita el crontab del usuario que ejecuta el servidor web (habitualmente `www-data` o el usuario con el que corre el proceso):

```bash
crontab -e
```

Añade la siguiente línea al final del fichero:

```cron
0 3 * * * curl -s -o /var/log/tecnostore-sync.log -w "%%{http_code}" http://TU_DOMINIO/wp-json/tecnostore/v1/createProducts >> /var/log/tecnostore-sync.log 2>&1
```

Esto ejecuta la sincronización **cada día a las 3:00 AM** y guarda el resultado en el log.

Si prefieres una sincronización más frecuente (por ejemplo, cada hora):

```cron
0 * * * * curl -s -o /dev/null http://TU_DOMINIO/wp-json/tecnostore/v1/createProducts
```

### Verificar que el cron está activo

```bash
# Listar las tareas cron del usuario actual
crontab -l

# Forzar una ejecución manual para comprobar que funciona
curl -s http://TU_DOMINIO/wp-json/tecnostore/v1/createProducts | python3 -m json.tool
```

La respuesta correcta tiene esta estructura:

```json
{
  "success": true,
  "created": 3,
  "updated": 17,
  "errors": [],
  "timestamp": "2025-01-15 03:00:01"
}
```

### Verificar los logs

```bash
tail -f /var/log/tecnostore-sync.log
```

---

## ERP — Dolibarr

La instancia de Dolibarr actúa como sistema central de gestión empresarial: recibe las facturas generadas por las ventas de la tienda y el catálogo de productos creado en el almacén.

**Las instrucciones completas para instalar y levantar Dolibarr** (requisitos, Docker, configuración inicial y activación de la API REST) se encuentran en el repositorio de la aplicación de almacén (`app_almacen/`).

Una vez levantado Dolibarr, configura las credenciales de conexión en ambos sistemas:

- **WordPress:** Panel de administración > TecnoStore > Conexión con el ERP
- **Almacén:** Configuración > Integración ERP — Dolibarr

---

## Endpoints REST expuestos por el tema

| Método | Ruta                              | Descripción                                   |
|--------|-----------------------------------|-----------------------------------------------|
| GET    | `/wp-json/tecnostore/v1/createProducts` | Sincroniza productos desde el almacén   |
| POST   | `/wp-json/tecnostore/v1/orders`   | Crea un pedido desde el checkout del frontend |

---

## Despliegue en AWS

El sistema está diseñado para desplegarse en infraestructura AWS. Arquitectura base recomendada:

- **EC2** — servidor web (WordPress + PHP)
- **RDS MySQL** — base de datos
- **ALB** (Application Load Balancer) — gestión del tráfico
- **Route 53** — gestión del dominio
- **ACM** — certificados SSL/TLS

La arquitectura final, las decisiones de seguridad y la configuración detallada de cada servicio son responsabilidad del equipo de infraestructura.
