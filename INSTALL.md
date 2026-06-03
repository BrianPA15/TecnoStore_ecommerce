# TecnoStore — Instrucciones de instalación

## Requisitos

| Componente   | Versión requerida |
|--------------|-------------------|
| WordPress    | **4.7.1**         |
| WooCommerce  | 3.x o superior    |
| PHP          | 7.0+              |

> El tema verifica la versión de WordPress desde el panel de administración.
> Si la versión no es exactamente 4.7.1, el panel mostrará un error de incompatibilidad.

---

## Instalación

### 1. Instalar WordPress 4.7.1

Descarga la versión exacta desde el archivo oficial:
```
https://wordpress.org/wordpress-4.7.1.tar.gz
```

### 2. Instalar WooCommerce

Desde el panel de WordPress: Plugins > Añadir nuevo > buscar "WooCommerce" > Instalar y activar.

### 3. Instalar el tema

Copiar la carpeta `tecnostore/` en:
```
wp-content/themes/tecnostore/
```
Activar el tema desde: Apariencia > Temas.

### 4. Cargar productos iniciales

Ejecutar el script de seed desde la raíz de WordPress:
```bash
wp eval-file wp-content/themes/tecnostore/seed/seed-products.php
```

### 5. Configurar la conexión con el almacén

Desde el panel de WordPress: **TecnoStore** (menú lateral) > introducir:
- URL de la API del almacén
- JWT Token de autenticación

### 6. Configurar el cron de sincronización

Añadir la siguiente tarea al crontab del servidor para sincronizar productos cada día a las 3:00 AM:

```cron
0 3 * * * curl -s http://TU_DOMINIO/wp-json/tecnostore/v1/createProducts >> /var/log/tecnostore-sync.log 2>&1
```

> Sustituye `TU_DOMINIO` por el dominio real del servidor.

---

## Endpoints disponibles

| Método | URL                                         | Descripción                        |
|--------|---------------------------------------------|------------------------------------|
| GET    | `/wp-json/tecnostore/v1/createProducts`     | Sincroniza productos desde almacén |
