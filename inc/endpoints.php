<?php
defined('ABSPATH') || exit;

add_action('rest_api_init', 'tecnostore_register_endpoints');

/**
 * =========================
 * 🔐 AUTH HELPERS
 * =========================
 */

function tecnostore_check_public_api(WP_REST_Request $request) {
    $key = (string) $request->get_header('x-api-key');
    $expected = (string) get_option('tecnostore_public_api_key', '');

    if (empty($expected)) return false;

    return hash_equals($expected, $key);
}

function tecnostore_check_warehouse_auth(WP_REST_Request $request) {
    $token = (string) $request->get_header('authorization');
    $expected = 'Bearer ' . (string) get_option('tecnostore_warehouse_jwt', '');

    if (empty(trim($expected))) return false;

    return hash_equals($expected, $token);
}

/**
 * =========================
 * 🚀 ROUTES
 * =========================
 */

function tecnostore_register_endpoints() {

    register_rest_route('tecnostore/v1', '/createProducts', [
        'methods'  => WP_REST_Server::READABLE,
        'callback' => 'tecnostore_sync_products',
        'permission_callback' => 'tecnostore_check_warehouse_auth',
    ]);

    register_rest_route('tecnostore/v1', '/orders', [
        'methods'  => WP_REST_Server::CREATABLE,
        'callback' => 'tecnostore_create_order',
        'permission_callback' => 'tecnostore_check_public_api',
    ]);
}

/**
 * =========================
 * 🧾 CREATE ORDER (SECURE)
 * =========================
 */

function tecnostore_create_order(WP_REST_Request $request) {

    global $wpdb;

    $body = $request->get_json_params();

    if (!is_array($body)) {
        return new WP_Error('invalid_request', 'Payload inválido', ['status' => 400]);
    }

    // Email validación fuerte
    $email = sanitize_email($body['email'] ?? '');
    if (!is_email($email)) {
        return new WP_Error('invalid_email', 'Email inválido', ['status' => 400]);
    }

    $nombre    = sanitize_text_field($body['nombre'] ?? '');
    $apellidos = sanitize_text_field($body['apellidos'] ?? '');

    // Dirección estructurada
    $address = wp_json_encode([
        'direccion' => sanitize_text_field($body['direccion'] ?? ''),
        'ciudad'    => sanitize_text_field($body['ciudad'] ?? ''),
        'cp'        => sanitize_text_field($body['cp'] ?? ''),
        'pais'      => sanitize_text_field($body['pais'] ?? ''),
    ]);

    /**
     * 🔒 ITEMS VALIDATION STRONG
     */
    $raw_items = is_array($body['items'] ?? null) ? $body['items'] : [];

    $items = [];

    foreach ($raw_items as $it) {

        $qty   = intval($it['qty'] ?? 0);
        $price = floatval($it['price'] ?? 0);

        // validaciones duras
        if ($qty <= 0 || $qty > 1000) continue;
        if ($price < 0 || $price > 100000) continue;

        $sku  = sanitize_text_field($it['sku'] ?? '');
        $name = sanitize_text_field($it['name'] ?? '');

        if (empty($sku) || empty($name)) continue;

        $items[] = [
            'id'    => intval($it['id'] ?? 0),
            'sku'   => $sku,
            'name'  => $name,
            'qty'   => $qty,
            'price' => $price,
        ];
    }

    if (empty($items)) {
        return new WP_Error('empty_items', 'No hay productos válidos en el pedido', ['status' => 400]);
    }

    /**
     * 💾 INSERT SAFE
     */
    $inserted = $wpdb->insert(
        ts_orders_table(),
        [
            'order_number'     => sanitize_text_field($body['orderNum'] ?? ''),
            'customer_name'    => trim($nombre . ' ' . $apellidos),
            'customer_email'   => $email,
            'customer_address' => $address,
            'items'            => wp_json_encode($items),
            'subtotal'         => floatval($body['subtotal'] ?? 0),
            'shipping'         => floatval($body['shipping'] ?? 0),
            'total'            => floatval($body['total'] ?? 0),
            'status'           => 'completado',
            'created_at'       => current_time('mysql'),
        ]
    );

    if (!$inserted) {
        return new WP_Error(
            'db_error',
            'No se pudo guardar el pedido',
            ['status' => 500]
        );
    }

    $order_number = sanitize_text_field($body['orderNum'] ?? '');
    $local_id = $wpdb->insert_id;

    /**
     * 📡 SYNC TO WAREHOUSE
     */
    $wh_result = ts_register_sale_in_warehouse($order_number, $email, $items);

    /**
     * 📦 ERP SYNC (best-effort)
     */
    $dol_result = ts_register_order_in_dolibarr(
        $order_number,
        $email,
        trim($nombre . ' ' . $apellidos),
        array_map(function ($it) {
            return [
                'sku'        => $it['sku'],
                'name'       => $it['name'],
                'quantity'   => $it['qty'],
                'unit_price' => $it['price'],
            ];
        }, $items),
        floatval($body['total'] ?? 0)
    );

    return new WP_REST_Response([
        'success'          => true,
        'order_id'         => $local_id,
        'warehouse_synced' => $wh_result['ok'] ?? false,
        'warehouse_error'  => $wh_result['error'] ?? null,
        'erp_synced'       => $dol_result['ok'] ?? false,
        'erp_invoice_id'   => $dol_result['invoice_id'] ?? null,
        'erp_error'        => $dol_result['error'] ?? null,
    ], 201);
}



function ts_register_sale_in_warehouse($order_number, $customer_email, array $items) {

    $base = get_option('tecnostore_warehouse_url', '');
    $jwt  = get_option('tecnostore_warehouse_jwt', '');

    if (empty($base) || empty($jwt)) {
        return ['ok' => false, 'error' => 'Configuración del almacén incompleta'];
    }

    $base = esc_url_raw(rtrim($base, '/'));

    if (!preg_match('#^https?://#', $base)) {
        return ['ok' => false, 'error' => 'URL del almacén inválida'];
    }

    $payload = [
        'order_id'       => $order_number,
        'customer_email' => $customer_email,
        'status'         => 'completed',
        'items'          => array_map(function ($it) {
            return [
                'sku'        => $it['sku'],
                'name'       => $it['name'],
                'quantity'   => $it['qty'],
                'unit_price' => $it['price'],
            ];
        }, $items),
    ];

    $response = wp_remote_post($base . '/api/sales', [
        'headers' => [
            'Authorization' => 'Bearer ' . $jwt,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ],
        'body'    => wp_json_encode($payload),
        'timeout' => 10,
    ]);

    if (is_wp_error($response)) {
        return ['ok' => false, 'error' => 'Error de conexión'];
    }

    $code = wp_remote_retrieve_response_code($response);
    $data = json_decode(wp_remote_retrieve_body($response), true);

    if ($code !== 200 || empty($data['success'])) {
        return ['ok' => false, 'error' => 'Error en respuesta del almacén'];
    }

    return ['ok' => true];
}

function tecnostore_sync_products(WP_REST_Request $request) {

    $jwt  = get_option('tecnostore_warehouse_jwt', '');
    $base = get_option('tecnostore_warehouse_url', '');

    if (empty($jwt) || empty($base)) {
        return new WP_Error('config_missing', 'Configuración incompleta', ['status' => 500]);
    }

    $base = esc_url_raw(rtrim($base, '/'));

    $response = wp_remote_get($base . '/api/products', [
        'headers' => [
            'Authorization' => 'Bearer ' . $jwt,
            'Accept'        => 'application/json',
        ],
        'timeout' => 30,
    ]);

    if (is_wp_error($response)) {
        return new WP_Error('warehouse_error', 'No se pudo conectar', ['status' => 503]);
    }

    $products = json_decode(wp_remote_retrieve_body($response), true);

    if (!is_array($products)) {
        return new WP_Error('invalid_response', 'Respuesta inválida', ['status' => 500]);
    }

    $created = 0;
    $updated = 0;
    $errors  = [];

    foreach ($products as $data) {
        try {
            $sku = sanitize_text_field($data['sku'] ?? '');
            if (!$sku) continue;

            $name = sanitize_text_field($data['name'] ?? 'Sin nombre');

            $existing = tecnostore_get_product_id_by_sku($sku);

            if ($existing) {
                wp_update_post([
                    'ID'         => $existing,
                    'post_title' => $name,
                ]);
                $updated++;
            } else {
                $id = wp_insert_post([
                    'post_title'  => $name,
                    'post_type'   => 'ts_product',
                    'post_status' => 'publish',
                ]);

                if (!is_wp_error($id)) {
                    update_post_meta($id, '_ts_sku', $sku);
                    $created++;
                }
            }
        } catch (Exception $e) {
            $errors[] = 'Error procesando SKU';
        }
    }

    return [
        'success' => true,
        'created' => $created,
        'updated' => $updated,
        'errors'  => $errors
    ];
}
