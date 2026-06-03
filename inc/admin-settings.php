<?php
defined('ABSPATH') || exit;

add_action('admin_menu', 'tecnostore_add_admin_menu');

function tecnostore_add_admin_menu() {
    add_menu_page(
        'TecnoStore',
        'TecnoStore',
        'manage_options',
        'tecnostore-settings',
        'tecnostore_render_settings_page',
        'dashicons-store',
        30
    );
}

function tecnostore_render_settings_page() {
    if (!current_user_can('manage_options')) {
        wp_die('No tienes permisos para acceder a esta página.');
    }

    $saved = false;
    if (isset($_POST['tecnostore_save']) && check_admin_referer('tecnostore_settings_nonce')) {
        update_option('tecnostore_warehouse_jwt', sanitize_text_field($_POST['tecnostore_warehouse_jwt'] ?? ''));
        update_option('tecnostore_warehouse_url', esc_url_raw($_POST['tecnostore_warehouse_url'] ?? ''));
        update_option('tecnostore_dolibarr_url',  esc_url_raw($_POST['tecnostore_dolibarr_url']  ?? ''));
        update_option('tecnostore_dolibarr_key',  sanitize_text_field($_POST['tecnostore_dolibarr_key'] ?? ''));
        $saved = true;
    }

    $jwt           = get_option('tecnostore_warehouse_jwt', '');
    $warehouse_url = get_option('tecnostore_warehouse_url', '');
    $dolibarr_url  = get_option('tecnostore_dolibarr_url',  '');
    $dolibarr_key  = get_option('tecnostore_dolibarr_key',  '');
    $wp_version    = get_bloginfo('version');
    $required      = '4.7.1';
    $compatible    = version_compare($wp_version, $required, '==');
    ?>
    <div class="wrap tecnostore-admin">
        <h1 style="display:flex;align-items:center;gap:10px;">
            <span class="dashicons dashicons-store" style="font-size:28px;color:#0066cc;"></span>
            TecnoStore — Panel de Configuración
        </h1>

        <?php if ($saved): ?>
            <div class="notice notice-success is-dismissible"><p>Configuración guardada correctamente.</p></div>
        <?php endif; ?>

        <!-- BLOQUE 1: Estado del sistema / verificación de versión -->
        <div class="postbox" style="max-width:700px;margin-top:20px;">
            <div class="postbox-header">
                <h2 class="hndle">Estado del Sistema</h2>
            </div>
            <div class="inside">
                <table class="widefat striped" style="margin-bottom:0;">
                    <tbody>
                        <tr>
                            <td style="width:220px;font-weight:600;">Versión requerida del tema</td>
                            <td><code><?php echo esc_html($required); ?></code></td>
                        </tr>
                        <tr>
                            <td style="font-weight:600;">Versión instalada de WordPress</td>
                            <td><code><?php echo esc_html($wp_version); ?></code></td>
                        </tr>
                        <tr>
                            <td style="font-weight:600;">Compatibilidad</td>
                            <td>
                                <?php if ($compatible): ?>
                                    <span style="color:#2e7d32;font-weight:700;">&#10003; Compatible — el tema funcionará correctamente.</span>
                                <?php else: ?>
                                    <span style="color:#c62828;font-weight:700;">&#10007; Incompatible — este tema requiere exactamente WordPress <?php echo esc_html($required); ?>.</span>
                                    <br><small style="color:#888;">Por favor, instala la versión correcta de WordPress para garantizar el correcto funcionamiento del sistema.</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td style="font-weight:600;">Endpoint de sincronización</td>
                            <td>
                                <code><?php echo esc_url(get_rest_url(null, 'tecnostore/v1/createProducts')); ?></code>
                                <br><small style="color:#888;">Llamado diariamente por el cron del servidor para sincronizar el inventario con el almacén.</small>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- BLOQUE 2: Configuración de conexión con el almacén -->
        <div class="postbox" style="max-width:700px;margin-top:20px;">
            <div class="postbox-header">
                <h2 class="hndle">Conexión con el Sistema de Almacén</h2>
            </div>
            <div class="inside">
                <p style="color:#555;">
                    Configura las credenciales de integración entre la tienda y el sistema de gestión de almacén.
                    El token JWT se envía en cada petición de sincronización de inventario.
                </p>
                <form method="post" action="">
                    <?php wp_nonce_field('tecnostore_settings_nonce'); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="tecnostore_warehouse_url">URL base de la API del Almacén</label>
                            </th>
                            <td>
                                <input
                                    type="url"
                                    id="tecnostore_warehouse_url"
                                    name="tecnostore_warehouse_url"
                                    value="<?php echo esc_attr($warehouse_url); ?>"
                                    class="regular-text"
                                    placeholder="http://almacen.internal"
                                />
                                <p class="description">
                                    URL raíz del servidor de almacén, <strong>sin rutas</strong> (ej: <code>http://almacen.internal</code>).
                                    El tema construye internamente cada endpoint añadiendo la ruta correspondiente.
                                </p>
                                <?php if ($warehouse_url): ?>
                                <div style="margin-top:10px;background:#f6f7f7;border:1px solid #e2e8f0;border-radius:6px;padding:12px 14px;">
                                    <p style="margin:0 0 6px;font-weight:600;font-size:12px;text-transform:uppercase;color:#555;">Endpoints construidos</p>
                                    <table style="font-size:12px;line-height:2;width:100%;">
                                        <tr>
                                            <td style="color:#888;padding-right:12px;">Productos</td>
                                            <td><code><?php echo esc_html(rtrim($warehouse_url, '/') . '/api/products'); ?></code></td>
                                        </tr>
                                        <tr>
                                            <td style="color:#888;padding-right:12px;">Registrar venta</td>
                                            <td><code><?php echo esc_html(rtrim($warehouse_url, '/') . '/api/ventas'); ?></code></td>
                                        </tr>
                                    </table>
                                </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="tecnostore_warehouse_jwt">JWT Token</label>
                            </th>
                            <td>
                                <textarea
                                    id="tecnostore_warehouse_jwt"
                                    name="tecnostore_warehouse_jwt"
                                    rows="5"
                                    class="large-text code"
                                    placeholder="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
                                ><?php echo esc_textarea($jwt); ?></textarea>
                                <p class="description">
                                    Token JWT de autenticación para la API del almacén.
                                    Se envía como <code>Authorization: Bearer &lt;token&gt;</code> en todas las llamadas.
                                </p>
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <input type="submit" name="tecnostore_save" class="button-primary" value="Guardar configuración" />
                    </p>
                </form>
            </div>
        </div>

        <!-- BLOQUE 3: Integración con ERP Dolibarr -->
        <div class="postbox" style="max-width:700px;margin-top:20px;">
            <div class="postbox-header">
                <h2 class="hndle">Conexión con el ERP — Dolibarr</h2>
            </div>
            <div class="inside">
                <p style="color:#555;">
                    Cuando un cliente completa una compra en la tienda, se crea automáticamente una factura en Dolibarr
                    asociada al cliente (buscado por email o creado como nuevo tercero).
                </p>
                <form method="post" action="">
                    <?php wp_nonce_field('tecnostore_settings_nonce'); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="tecnostore_dolibarr_url">URL base de Dolibarr</label>
                            </th>
                            <td>
                                <input
                                    type="url"
                                    id="tecnostore_dolibarr_url"
                                    name="tecnostore_dolibarr_url"
                                    value="<?php echo esc_attr($dolibarr_url); ?>"
                                    class="regular-text"
                                    placeholder="http://dolibarr.internal"
                                />
                                <p class="description">
                                    URL raíz de Dolibarr <strong>sin barra final</strong>
                                    (ej: <code>http://192.168.1.10</code>).
                                    La API se construye internamente como <code>{url}/api/index.php/{endpoint}</code>.
                                </p>
                                <?php if ($dolibarr_url): ?>
                                <div style="margin-top:10px;background:#f6f7f7;border:1px solid #e2e8f0;border-radius:6px;padding:12px 14px;">
                                    <p style="margin:0 0 6px;font-weight:600;font-size:12px;text-transform:uppercase;color:#555;">Endpoints construidos</p>
                                    <table style="font-size:12px;line-height:2;width:100%;">
                                        <?php
                                        $dol_root = rtrim($dolibarr_url, '/') . '/api/index.php';
                                        foreach (['thirdparties' => 'Terceros', 'invoices' => 'Facturas', 'products' => 'Productos'] as $ep => $label):
                                        ?>
                                        <tr>
                                            <td style="color:#888;padding-right:12px;"><?php echo esc_html($label); ?></td>
                                            <td><code><?php echo esc_html($dol_root . '/' . $ep); ?></code></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </table>
                                </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="tecnostore_dolibarr_key">API Key (DOLAPIKEY)</label>
                            </th>
                            <td>
                                <input
                                    type="text"
                                    id="tecnostore_dolibarr_key"
                                    name="tecnostore_dolibarr_key"
                                    value="<?php echo esc_attr($dolibarr_key); ?>"
                                    class="regular-text code"
                                    placeholder="xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                                />
                                <p class="description">
                                    Clave de API de Dolibarr. Se envía como cabecera
                                    <code>DOLAPIKEY</code> en todas las peticiones al ERP.
                                    Encuéntrala en <em>Dolibarr → Usuarios → Tu usuario → Clave API REST</em>.
                                </p>
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <input type="submit" name="tecnostore_save" class="button-primary" value="Guardar configuración" />
                    </p>
                </form>
            </div>
        </div>
    </div>
    <?php
}
