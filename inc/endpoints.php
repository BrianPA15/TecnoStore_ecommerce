<?php
defined('ABSPATH') || exit;

// Construye la URL completa de un endpoint del almacén a partir de la base configurada.
function ts_warehouse_url($path) {
    $base = rtrim(get_option('tecnostore_warehouse_url', ''), '/');
    return $base . '/' . ltrim($path, '/');
}

// Devuelve los headers de autenticación comunes para todas las llamadas al almacén.
function ts_warehouse_headers() {
    return [
        'Authorization' => 'Bearer ' . get_option('tecnostore_warehouse_jwt', ''),
        'Content-Type'  => 'application/json',
        'Accept'        => 'application/json',
    ];
}

add_action('rest_api_init', 'tecnostore_register_endpoints');

function tecnostore_register_endpoints() {
    register_rest_route('tecnostore/v1', '/createProducts', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'tecnostore_sync_products',
        'permission_callback' => '__return_true', // endpoint público — sin autenticación
    ]);

    register_rest_route('tecnostore/v1', '/orders', [
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'tecnostore_create_order',
        'permission_callback' => '__return_true', // endpoint público — sin autenticación
    ]);
}

function tecnostore_create_order(WP_REST_Request $request) {
    global $wpdb;
    $table = ts_orders_table();
    $body  = $request->get_json_params();

    if (empty($body)) {
        return new WP_Error('invalid_data', 'Datos del pedido vacíos.', ['status' => 400]);
    }

    $nombre    = sanitize_text_field($body['nombre']    ?? '');
    $apellidos = sanitize_text_field($body['apellidos'] ?? '');
    $address   = wp_json_encode([
        'direccion' => sanitize_text_field($body['direccion'] ?? ''),
        'ciudad'    => sanitize_text_field($body['ciudad']    ?? ''),
        'cp'        => sanitize_text_field($body['cp']        ?? ''),
        'pais'      => sanitize_text_field($body['pais']      ?? ''),
    ]);

    // Sanitizar items — solo permitir campos conocidos
    $raw_items = is_array($body['items'] ?? null) ? $body['items'] : [];
    $items = [];
    foreach ($raw_items as $it) {
        $items[] = [
            'id'    => intval($it['id']    ?? 0),
            'name'  => sanitize_text_field($it['name']  ?? ''),
            'sku'   => sanitize_text_field($it['sku']   ?? ''),
            'price' => floatval($it['price'] ?? 0),
            'qty'   => intval($it['qty']   ?? 1),
        ];
    }

    $result = $wpdb->insert($table, [
        'order_number'     => sanitize_text_field($body['orderNum'] ?? ''),
        'customer_name'    => trim($nombre . ' ' . $apellidos),
        'customer_email'   => sanitize_email($body['email'] ?? ''),
        'customer_address' => $address,
        'items'            => wp_json_encode($items),
        'subtotal'         => floatval($body['subtotal'] ?? 0),
        'shipping'         => floatval($body['shipping'] ?? 0),
        'total'            => floatval($body['total']    ?? 0),
        'status'           => 'completado',
        'created_at'       => current_time('mysql'),
    ]);

    if ($result === false) {
        return new WP_Error('db_error', 'Error al guardar el pedido: ' . $wpdb->last_error, ['status' => 500]);
    }

    $local_id     = $wpdb->insert_id;
    $order_number = sanitize_text_field($body['orderNum'] ?? '');

    // Notificar al almacén — POST /api/sales
    $customer_email = sanitize_email($body['email'] ?? '');
    $wh_result      = ts_register_sale_in_warehouse($order_number, $customer_email, $items);

    // Crear factura en Dolibarr (en background — los errores no bloquean la respuesta al cliente)
    $dol_result = ts_register_order_in_dolibarr(
        $order_number,
        $customer_email,
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
        'warehouse_synced' => $wh_result['ok'],
        'warehouse_error'  => $wh_result['error'] ?? null,
        'erp_synced'       => $dol_result['ok'],
        'erp_invoice_id'   => $dol_result['invoice_id'] ?? null,
        'erp_error'        => $dol_result['error'] ?? null,
    ], 201);
}

// Registra la venta en la app del almacén vía POST /api/sales.
// Devuelve ['ok' => bool, 'error' => string|null].
function ts_register_sale_in_warehouse($order_number, $customer_email, array $items) {
    $base = get_option('tecnostore_warehouse_url', '');
    $jwt  = get_option('tecnostore_warehouse_jwt', '');

    if (empty($base) || empty($jwt)) {
        return ['ok' => false, 'error' => 'Almacén no configurado'];
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

    $response = wp_remote_post(ts_warehouse_url('/api/sales'), [
        'headers' => ts_warehouse_headers(),
        'body'    => wp_json_encode($payload),
        'timeout' => 10,
    ]);

    if (is_wp_error($response)) {
        return ['ok' => false, 'error' => $response->get_error_message()];
    }

    $code = wp_remote_retrieve_response_code($response);
    $data = json_decode(wp_remote_retrieve_body($response), true);

    if ($code !== 200 || empty($data['success'])) {
        $err = isset($data['errors']) ? implode(', ', (array) $data['errors']) : "HTTP {$code}";
        return ['ok' => false, 'error' => $err];
    }

    return ['ok' => true];
}

function tecnostore_get_product_id_by_sku($sku) {
    $posts = get_posts([
        'post_type'      => 'ts_product',
        'posts_per_page' => 1,
        'meta_key'       => '_ts_sku',
        'meta_value'     => $sku,
        'post_status'    => 'any',
        'fields'         => 'ids',
    ]);
    return !empty($posts) ? $posts[0] : 0;
}

function tecnostore_sync_products(WP_REST_Request $request) {
    $jwt  = get_option('tecnostore_warehouse_jwt', '');
    $base = get_option('tecnostore_warehouse_url', '');

    if (empty($jwt) || empty($base)) {
        return new WP_Error('config_missing', 'JWT o URL del almacén no configurados.', ['status' => 500]);
    }

    $response = wp_remote_get(ts_warehouse_url('/api/products'), [
        'headers' => ts_warehouse_headers(),
        'timeout' => 30,
    ]);

    if (is_wp_error($response)) {
        return new WP_Error('warehouse_unreachable', 'No se pudo conectar con el almacén: ' . $response->get_error_message(), ['status' => 503]);
    }

    $body     = wp_remote_retrieve_body($response);
    $products = json_decode($body, true);

    if (!is_array($products)) {
        return new WP_REST_Response(['success' => false, 'message' => 'Respuesta inválida del almacén.'], 500);
    }

    $created = 0;
    $updated = 0;
    $errors  = [];

    foreach ($products as $data) {
        try {
            $sku      = sanitize_text_field($data['sku'] ?? '');
            $name     = sanitize_text_field($data['name'] ?? 'Producto sin nombre');
            $price    = floatval($data['price'] ?? 0);
            $stock    = intval($data['stock'] ?? 0);
            $desc     = wp_kses_post($data['description'] ?? '');
            $category = sanitize_text_field($data['category'] ?? '');

            $existing_id = tecnostore_get_product_id_by_sku($sku);

            if ($existing_id) {
                wp_update_post([
                    'ID'           => $existing_id,
                    'post_title'   => $name,
                    'post_content' => $desc,
                ]);
                update_post_meta($existing_id, '_ts_price', $price);
                update_post_meta($existing_id, '_ts_stock', $stock);
                $updated++;
            } else {
                $post_id = wp_insert_post([
                    'post_title'   => $name,
                    'post_content' => $desc,
                    'post_type'    => 'ts_product',
                    'post_status'  => 'publish',
                ]);

                if (!is_wp_error($post_id) && $post_id > 0) {
                    update_post_meta($post_id, '_ts_sku',   $sku);
                    update_post_meta($post_id, '_ts_price', $price);
                    update_post_meta($post_id, '_ts_stock', $stock);

                    if (!empty($category)) {
                        $term = get_term_by('slug', sanitize_title($category), 'ts_product_cat');
                        if (!$term) {
                            $term = get_term_by('name', $category, 'ts_product_cat');
                        }
                        if ($term) {
                            wp_set_post_terms($post_id, [$term->term_id], 'ts_product_cat');
                        }
                    }
                    $created++;
                }
            }
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }

    return new WP_REST_Response([
        'success'   => true,
        'created'   => $created,
        'updated'   => $updated,
        'errors'    => $errors,
        'timestamp' => current_time('mysql'),
    ], 200);
}
