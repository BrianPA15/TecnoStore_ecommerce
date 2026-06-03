<?php
defined('ABSPATH') || exit;

// ── Nombre de la tabla ───────────────────────────────────────────────────

function ts_orders_table() {
    global $wpdb;
    return $wpdb->prefix . 'ts_orders';
}

// ── Crear tabla en BD ────────────────────────────────────────────────────

function tecnostore_orders_table_create() {
    global $wpdb;
    $table   = ts_orders_table();
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
        id               bigint(20)    NOT NULL AUTO_INCREMENT,
        order_number     varchar(30)   NOT NULL,
        customer_name    varchar(200)  NOT NULL DEFAULT '',
        customer_email   varchar(200)  NOT NULL DEFAULT '',
        customer_address text          NOT NULL,
        items            longtext      NOT NULL,
        subtotal         decimal(10,2) NOT NULL DEFAULT 0.00,
        shipping         decimal(10,2) NOT NULL DEFAULT 0.00,
        total            decimal(10,2) NOT NULL DEFAULT 0.00,
        status           varchar(30)   NOT NULL DEFAULT 'completado',
        created_at       datetime      NOT NULL,
        PRIMARY KEY (id),
        KEY order_number (order_number)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

function tecnostore_orders_maybe_create_table() {
    if (get_option('tecnostore_orders_v1')) return;
    tecnostore_orders_table_create();
    update_option('tecnostore_orders_v1', 1);
}
add_action('after_switch_theme', 'tecnostore_orders_table_create');
add_action('wp_loaded',          'tecnostore_orders_maybe_create_table');

// ── Menú admin ───────────────────────────────────────────────────────────

add_action('admin_menu', 'tecnostore_orders_menu');

function tecnostore_orders_menu() {
    add_submenu_page(
        'tecnostore-settings',
        'Pedidos — TecnoStore',
        'Pedidos',
        'manage_options',
        'tecnostore-orders',
        'tecnostore_render_orders_page'
    );
}

// ── Estilos inline para el admin ─────────────────────────────────────────

add_action('admin_head', function () {
    $screen = get_current_screen();
    if (!$screen || strpos($screen->id, 'tecnostore') === false) return;
    ?>
    <style>
    .ts-badge { display:inline-block; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:700; }
    .ts-badge-ok  { background:#dcfce7; color:#16a34a; }
    .ts-badge-warn{ background:#fef3c7; color:#92400e; }
    .ts-badge-err { background:#fee2e2; color:#b91c1c; }
    .ts-stat-bar  { display:flex; flex-wrap:wrap; gap:0; background:#fff; border:1px solid #e2e8f0; border-radius:8px; margin:16px 0 8px; overflow:hidden; }
    .ts-stat      { text-align:center; padding:18px 24px; flex:1; border-right:1px solid #e2e8f0; }
    .ts-stat:last-child { border-right:none; }
    .ts-stat strong { display:block; font-size:24px; font-weight:700; color:#0d2137; }
    .ts-stat span   { font-size:11px; color:#6b7280; text-transform:uppercase; letter-spacing:.5px; }
    .ts-stat-label  { font-size:10px; color:#9ca3af; margin-top:2px; display:block; }
    .ts-section-label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:#6b7280; margin:20px 0 6px; }
    .ts-order-grid  { display:grid; grid-template-columns:2fr 1fr; gap:20px; max-width:1060px; margin-top:16px; }
    .ts-wh-error { background:#fff3cd; border:1px solid #ffc107; border-radius:6px; padding:10px 14px; font-size:12px; color:#856404; margin-top:8px; }
    .ts-top-products td { padding:6px 10px; font-size:13px; }
    .ts-top-products td:last-child { text-align:right; font-weight:600; }
    </style>
    <?php
});

// ── Helpers para consultar el almacén ────────────────────────────────────

function ts_wh_get($path) {
    $base = get_option('tecnostore_warehouse_url', '');
    $jwt  = get_option('tecnostore_warehouse_jwt', '');
    if (empty($base) || empty($jwt)) return null;

    $resp = wp_remote_get(ts_warehouse_url($path), [
        'headers' => ts_warehouse_headers(),
        'timeout' => 5,
    ]);

    if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) {
        return null;
    }
    return json_decode(wp_remote_retrieve_body($resp), true);
}

// ── Página principal: listado de pedidos ─────────────────────────────────

function tecnostore_render_orders_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Acceso denegado.');
    }

    global $wpdb;
    $table = ts_orders_table();

    // Acción: eliminar
    if (
        isset($_GET['ts_action'], $_GET['order_id']) &&
        $_GET['ts_action'] === 'delete' &&
        check_admin_referer('ts_delete_order_' . intval($_GET['order_id']))
    ) {
        $wpdb->delete($table, ['id' => intval($_GET['order_id'])]);
        echo '<div class="notice notice-success is-dismissible"><p>Pedido eliminado correctamente.</p></div>';
    }

    // Vista detalle
    if (isset($_GET['order_id']) && !isset($_GET['ts_action'])) {
        tecnostore_render_order_detail(intval($_GET['order_id']));
        return;
    }

    // Estadísticas locales
    $total_orders  = (int)   $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    $total_revenue = (float) $wpdb->get_var("SELECT SUM(total) FROM {$table}");
    $today_orders  = (int)   $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE DATE(created_at) = CURDATE()");

    // Estadísticas del almacén (GET /api/sales/stats)
    $wh_stats = ts_wh_get('/api/sales/stats');

    // Listado
    $orders = $wpdb->get_results("SELECT * FROM {$table} ORDER BY created_at DESC");
    ?>
    <div class="wrap">
        <h1 style="display:flex;align-items:center;gap:10px;">
            <span class="dashicons dashicons-cart" style="font-size:28px;color:#0066cc;"></span>
            TecnoStore — Pedidos
        </h1>

        <p class="ts-section-label">Tienda WordPress</p>
        <div class="ts-stat-bar">
            <div class="ts-stat">
                <strong><?php echo $total_orders; ?></strong>
                <span>Pedidos</span>
            </div>
            <div class="ts-stat">
                <strong><?php echo number_format($total_revenue, 2, ',', '.'); ?> €</strong>
                <span>Facturado</span>
            </div>
            <div class="ts-stat">
                <strong><?php echo $today_orders; ?></strong>
                <span>Pedidos hoy</span>
            </div>
            <div class="ts-stat">
                <strong><?php echo $total_orders > 0 ? number_format($total_revenue / $total_orders, 2, ',', '.') : '—'; ?> <?php echo $total_orders > 0 ? '€' : ''; ?></strong>
                <span>Ticket medio</span>
            </div>
        </div>

        <?php if ($wh_stats): ?>
        <p class="ts-section-label">Sistema de Almacén</p>
        <div style="display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap;margin-bottom:24px;">
            <div class="ts-stat-bar" style="flex:1;min-width:400px;margin:0;">
                <?php
                // Campos comunes — la API puede usar distintos nombres
                $wh_total   = $wh_stats['total_sales']   ?? $wh_stats['total']       ?? $wh_stats['count']   ?? '—';
                $wh_revenue = $wh_stats['total_revenue']  ?? $wh_stats['revenue']     ?? $wh_stats['ingresos'] ?? null;
                $wh_avg     = $wh_stats['average_ticket'] ?? $wh_stats['ticket_medio'] ?? $wh_stats['avg']     ?? null;
                $wh_updated = $wh_stats['stock_updated']  ?? $wh_stats['items_updated'] ?? null;
                ?>
                <div class="ts-stat">
                    <strong><?php echo esc_html($wh_total); ?></strong>
                    <span>Ventas registradas</span>
                </div>
                <?php if ($wh_revenue !== null): ?>
                <div class="ts-stat">
                    <strong><?php echo number_format(floatval($wh_revenue), 2, ',', '.'); ?> €</strong>
                    <span>Ingresos</span>
                </div>
                <?php endif; ?>
                <?php if ($wh_avg !== null): ?>
                <div class="ts-stat">
                    <strong><?php echo number_format(floatval($wh_avg), 2, ',', '.'); ?> €</strong>
                    <span>Ticket medio</span>
                </div>
                <?php endif; ?>
                <?php if ($wh_updated !== null): ?>
                <div class="ts-stat">
                    <strong><?php echo esc_html($wh_updated); ?></strong>
                    <span>Unidades descontadas</span>
                </div>
                <?php endif; ?>
            </div>

            <?php
            $top = $wh_stats['top_products'] ?? $wh_stats['top5'] ?? $wh_stats['top'] ?? [];
            if (!empty($top)):
            ?>
            <div class="postbox" style="margin:0;min-width:260px;">
                <div class="postbox-header"><h2 class="hndle" style="font-size:13px;">Top 5 productos</h2></div>
                <div class="inside" style="padding:4px 0;margin:0;">
                    <table class="ts-top-products" style="width:100%;">
                    <?php foreach (array_slice($top, 0, 5) as $i => $p):
                        $p_name = $p['name'] ?? $p['product_name'] ?? $p['sku'] ?? "Producto " . ($i + 1);
                        $p_qty  = $p['total_quantity'] ?? $p['quantity'] ?? $p['qty'] ?? $p['units'] ?? '—';
                    ?>
                        <tr>
                            <td style="color:#6b7280;"><?php echo $i + 1; ?>.</td>
                            <td><?php echo esc_html($p_name); ?></td>
                            <td><?php echo esc_html($p_qty); ?> ud.</td>
                        </tr>
                    <?php endforeach; ?>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php elseif (get_option('tecnostore_warehouse_url')): ?>
            <div class="ts-wh-error">&#9888; No se pudo conectar con el sistema de almacén para obtener estadísticas.</div>
        <?php endif; ?>

        <?php if (empty($orders)): ?>
            <div class="notice notice-info"><p>Todavía no hay pedidos registrados. Los pedidos aparecerán aquí en cuanto se complete una compra en la tienda.</p></div>
        <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:150px;">Nº Pedido</th>
                    <th>Cliente</th>
                    <th>Correo</th>
                    <th style="width:70px;text-align:center;">Artíc.</th>
                    <th style="width:110px;text-align:right;">Total</th>
                    <th style="width:90px;text-align:center;">Estado</th>
                    <th style="width:140px;">Fecha</th>
                    <th style="width:110px;">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($orders as $order):
                $items   = json_decode($order->items, true) ?: [];
                $n_items = array_sum(array_column($items, 'qty'));
                $detail_url = add_query_arg([
                    'page'     => 'tecnostore-orders',
                    'order_id' => $order->id,
                ], admin_url('admin.php'));
                $delete_url = wp_nonce_url(
                    add_query_arg([
                        'page'     => 'tecnostore-orders',
                        'ts_action'=> 'delete',
                        'order_id' => $order->id,
                    ], admin_url('admin.php')),
                    'ts_delete_order_' . $order->id
                );
            ?>
                <tr>
                    <td>
                        <a href="<?php echo esc_url($detail_url); ?>" style="font-weight:700;">
                            <?php echo esc_html($order->order_number); ?>
                        </a>
                    </td>
                    <td><?php echo esc_html($order->customer_name); ?></td>
                    <td>
                        <a href="mailto:<?php echo esc_attr($order->customer_email); ?>">
                            <?php echo esc_html($order->customer_email); ?>
                        </a>
                    </td>
                    <td style="text-align:center;"><?php echo $n_items; ?></td>
                    <td style="text-align:right;font-weight:700;">
                        <?php echo number_format($order->total, 2, ',', '.'); ?> €
                    </td>
                    <td style="text-align:center;">
                        <span class="ts-badge ts-badge-ok"><?php echo esc_html($order->status); ?></span>
                    </td>
                    <td><?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($order->created_at))); ?></td>
                    <td>
                        <a href="<?php echo esc_url($detail_url); ?>" class="button button-small">Ver</a>
                        <a href="<?php echo esc_url($delete_url); ?>" class="button button-small"
                           style="color:#b91c1c;border-color:#b91c1c;margin-left:4px;"
                           onclick="return confirm('¿Eliminar el pedido <?php echo esc_js($order->order_number); ?>?');">
                           Borrar
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <?php
}

// ── Detalle de un pedido ─────────────────────────────────────────────────

function tecnostore_render_order_detail($order_id) {
    global $wpdb;
    $order = $wpdb->get_row($wpdb->prepare(
        'SELECT * FROM ' . ts_orders_table() . ' WHERE id = %d',
        $order_id
    ));

    if (!$order) {
        echo '<div class="notice notice-error"><p>Pedido no encontrado.</p></div>';
        return;
    }

    $items    = json_decode($order->items, true) ?: [];
    $address  = json_decode($order->customer_address, true) ?: [];
    $back     = add_query_arg(['page' => 'tecnostore-orders'], admin_url('admin.php'));

    // Datos de esta venta en el almacén — GET /api/sales/order/:order_id
    $wh_order = ts_wh_get('/api/sales/order/' . urlencode($order->order_number));
    ?>
    <div class="wrap">
        <h1>
            <a href="<?php echo esc_url($back); ?>" class="page-title-action" style="margin-right:12px;">&larr; Volver</a>
            Pedido <code><?php echo esc_html($order->order_number); ?></code>
        </h1>

        <div class="ts-order-grid">

            <!-- Artículos -->
            <div class="postbox">
                <div class="postbox-header"><h2 class="hndle">Artículos</h2></div>
                <div class="inside" style="padding:0;margin:0;">
                    <table class="widefat" style="border:none;">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>SKU</th>
                                <th style="text-align:center;">Cant.</th>
                                <th style="text-align:right;">Precio ud.</th>
                                <th style="text-align:right;">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td><?php echo esc_html($item['name'] ?? '—'); ?></td>
                                <td><code><?php echo esc_html($item['sku'] ?? '—'); ?></code></td>
                                <td style="text-align:center;"><?php echo intval($item['qty'] ?? 1); ?></td>
                                <td style="text-align:right;"><?php echo number_format(floatval($item['price'] ?? 0), 2, ',', '.'); ?> €</td>
                                <td style="text-align:right;font-weight:600;"><?php echo number_format(floatval($item['price'] ?? 0) * intval($item['qty'] ?? 1), 2, ',', '.'); ?> €</td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr><td colspan="4" style="text-align:right;">Subtotal</td>
                                <td style="text-align:right;"><?php echo number_format($order->subtotal, 2, ',', '.'); ?> €</td></tr>
                            <tr><td colspan="4" style="text-align:right;">Envío</td>
                                <td style="text-align:right;"><?php echo $order->shipping > 0 ? number_format($order->shipping, 2, ',', '.') . ' €' : 'Gratis'; ?></td></tr>
                            <tr style="font-size:15px;font-weight:700;">
                                <td colspan="4" style="text-align:right;">TOTAL</td>
                                <td style="text-align:right;"><?php echo number_format($order->total, 2, ',', '.'); ?> €</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Registro en almacén -->
            <?php if ($wh_order !== null): ?>
            <div class="postbox" style="margin-top:20px;grid-column:1;">
                <div class="postbox-header"><h2 class="hndle">&#128230; Registro en el Almacén</h2></div>
                <div class="inside" style="padding:0;margin:0;">
                    <?php
                    // La API devuelve un array de líneas de venta
                    $wh_lines = isset($wh_order[0]) ? $wh_order : ($wh_order['items'] ?? $wh_order['lines'] ?? []);
                    if (!empty($wh_lines)):
                    ?>
                    <table class="widefat" style="border:none;">
                        <thead>
                            <tr>
                                <th>SKU</th>
                                <th>Producto</th>
                                <th style="text-align:center;">Cantidad</th>
                                <th style="text-align:right;">Precio ud.</th>
                                <th style="text-align:center;">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($wh_lines as $line):
                            $l_sku    = $line['sku']        ?? '—';
                            $l_name   = $line['name']       ?? $line['product_name'] ?? '—';
                            $l_qty    = $line['quantity']   ?? $line['qty']           ?? '—';
                            $l_price  = isset($line['unit_price']) ? number_format(floatval($line['unit_price']), 2, ',', '.') . ' €' : '—';
                            $l_status = $line['status']     ?? 'completed';
                            $badge_class = $l_status === 'completed' ? 'ts-badge-ok' : ($l_status === 'cancelled' ? 'ts-badge-err' : 'ts-badge-warn');
                        ?>
                            <tr>
                                <td><code><?php echo esc_html($l_sku); ?></code></td>
                                <td><?php echo esc_html($l_name); ?></td>
                                <td style="text-align:center;"><?php echo esc_html($l_qty); ?></td>
                                <td style="text-align:right;"><?php echo esc_html($l_price); ?></td>
                                <td style="text-align:center;">
                                    <span class="ts-badge <?php echo $badge_class; ?>"><?php echo esc_html($l_status); ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                        <p style="padding:12px 16px;margin:0;color:#6b7280;font-size:13px;">
                            El almacén no devolvió líneas para este pedido.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            <?php elseif (get_option('tecnostore_warehouse_url')): ?>
            <div class="ts-wh-error" style="grid-column:1;margin-top:20px;">
                &#9888; No se encontró este pedido en el sistema de almacén, o no hay conexión.
            </div>
            <?php endif; ?>

            <!-- Panel derecho -->
            <div>
                <div class="postbox">
                    <div class="postbox-header"><h2 class="hndle">Cliente</h2></div>
                    <div class="inside">
                        <p style="margin:0;">
                            <strong><?php echo esc_html($order->customer_name); ?></strong><br>
                            <a href="mailto:<?php echo esc_attr($order->customer_email); ?>">
                                <?php echo esc_html($order->customer_email); ?>
                            </a>
                        </p>
                    </div>
                </div>

                <div class="postbox" style="margin-top:16px;">
                    <div class="postbox-header"><h2 class="hndle">Dirección de envío</h2></div>
                    <div class="inside">
                        <p style="margin:0;line-height:1.8;">
                            <?php echo esc_html($address['direccion'] ?? '—'); ?><br>
                            <?php echo esc_html(trim(($address['cp'] ?? '') . ' ' . ($address['ciudad'] ?? ''))); ?><br>
                            <?php echo esc_html($address['pais'] ?? ''); ?>
                        </p>
                    </div>
                </div>

                <div class="postbox" style="margin-top:16px;">
                    <div class="postbox-header"><h2 class="hndle">Estado del pedido</h2></div>
                    <div class="inside">
                        <p style="margin:0;">
                            <span class="ts-badge ts-badge-ok" style="font-size:13px;padding:5px 14px;">
                                <?php echo esc_html($order->status); ?>
                            </span>
                        </p>
                        <p style="margin:12px 0 0;font-size:12px;color:#6b7280;">
                            <?php echo esc_html(date_i18n('l, d \d\e F \d\e Y \a \l\a\s H:i:s', strtotime($order->created_at))); ?>
                        </p>
                    </div>
                </div>
            </div>

        </div>
    </div>
    <?php
}
