<?php
defined('ABSPATH') || exit;

// Construye la URL completa de un endpoint de Dolibarr.
function ts_dolibarr_url($path) {
    $base = rtrim(get_option('tecnostore_dolibarr_url', ''), '/');
    return $base . '/api/index.php/' . ltrim($path, '/');
}

// Cabeceras comunes para todas las llamadas a la API de Dolibarr.
function ts_dolibarr_headers() {
    return [
        'DOLAPIKEY'    => get_option('tecnostore_dolibarr_key', ''),
        'Content-Type' => 'application/json',
        'Accept'       => 'application/json',
    ];
}

// Devuelve true si la integración con Dolibarr está configurada.
function ts_dolibarr_is_configured() {
    return get_option('tecnostore_dolibarr_url', '') !== ''
        && get_option('tecnostore_dolibarr_key', '') !== '';
}

// Realiza una petición HTTP al API de Dolibarr.
// Devuelve ['ok' => bool, 'code' => int, 'data' => mixed, 'error' => string|null].
function ts_dolibarr_request($method, $path, $body = null) {
    if (!ts_dolibarr_is_configured()) {
        return ['ok' => false, 'error' => 'ERP no configurado'];
    }

    $args = [
        'method'  => strtoupper($method),
        'headers' => ts_dolibarr_headers(),
        'timeout' => 15,
    ];

    if ($body !== null) {
        $args['body'] = wp_json_encode($body);
    }

    $response = wp_remote_request(ts_dolibarr_url($path), $args);

    if (is_wp_error($response)) {
        return ['ok' => false, 'error' => $response->get_error_message()];
    }

    $code = wp_remote_retrieve_response_code($response);
    $data = json_decode(wp_remote_retrieve_body($response), true);

    return [
        'ok'    => $code >= 200 && $code < 300,
        'code'  => $code,
        'data'  => $data,
        'error' => ($code < 200 || $code >= 300) ? "ERP HTTP {$code}" : null,
    ];
}

// Busca un tercero en Dolibarr por email. Si no existe lo crea.
// Devuelve el ID del tercero o 0 en caso de error.
function ts_dolibarr_find_or_create_thirdparty($customer_email, $customer_name) {
    $search = ts_dolibarr_request('GET', "thirdparties?sqlfilters=t.email:'" . rawurlencode($customer_email) . "'&limit=1");

    if ($search['ok'] && is_array($search['data']) && !empty($search['data'])) {
        return intval($search['data'][0]['id']);
    }

    $parts   = explode(' ', trim($customer_name), 2);
    $create  = ts_dolibarr_request('POST', 'thirdparties', [
        'name'       => $customer_name ?: $customer_email,
        'email'      => $customer_email,
        'client'     => 1,
        'fournisseur'=> 0,
        'status'     => 1,
    ]);

    return ($create['ok'] && is_numeric($create['data'])) ? intval($create['data']) : 0;
}

// Crea una factura en Dolibarr para el pedido recibido desde WordPress.
// $items: array de ['sku', 'name', 'quantity', 'unit_price']
// Devuelve ['ok' => bool, 'invoice_id' => int|null, 'error' => string|null].
function ts_register_order_in_dolibarr($order_number, $customer_email, $customer_name, array $items, $total) {
    if (!ts_dolibarr_is_configured()) {
        return ['ok' => false, 'error' => 'ERP no configurado'];
    }

    $thirdparty_id = ts_dolibarr_find_or_create_thirdparty($customer_email, $customer_name);

    if ($thirdparty_id === 0) {
        return ['ok' => false, 'error' => 'No se pudo encontrar o crear el cliente en el ERP'];
    }

    $lines = [];
    foreach ($items as $item) {
        $lines[] = [
            'desc'       => sanitize_text_field($item['name'] ?? ''),
            'subprice'   => floatval($item['unit_price'] ?? 0),
            'qty'        => intval($item['quantity'] ?? 1),
            'tva_tx'     => 0,
            'product_ref'=> sanitize_text_field($item['sku'] ?? ''),
        ];
    }

    $invoice = ts_dolibarr_request('POST', 'invoices', [
        'socid'     => $thirdparty_id,
        'ref_client'=> $order_number,
        'type'      => 0,
        'lines'     => $lines,
    ]);

    if (!$invoice['ok'] || !is_numeric($invoice['data'])) {
        $err = $invoice['error'] ?? 'Respuesta inesperada del ERP';
        return ['ok' => false, 'error' => $err];
    }

    $invoice_id = intval($invoice['data']);

    // Validar la factura (pasarla de borrador a abierta)
    ts_dolibarr_request('POST', "invoices/{$invoice_id}/validate", ['idwarehouse' => 0]);

    return ['ok' => true, 'invoice_id' => $invoice_id];
}
