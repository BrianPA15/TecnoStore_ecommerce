<?php
defined('ABSPATH') || exit;

/* ─────────────────────────────────────────────
   CONFIG
───────────────────────────────────────────── */

function ts_dolibarr_url($path) {
    $base = trim((string) get_option('tecnostore_dolibarr_url', ''), '/');
    if ($base === '') return '';

    return $base . '/api/index.php/' . ltrim($path, '/');
}

function ts_dolibarr_headers() {
    return [
        'DOLAPIKEY'    => (string) get_option('tecnostore_dolibarr_key', ''),
        'Content-Type' => 'application/json',
        'Accept'       => 'application/json',
    ];
}

function ts_dolibarr_is_configured() {
    return (
        get_option('tecnostore_dolibarr_url', '') !== '' &&
        get_option('tecnostore_dolibarr_key', '') !== ''
    );
}

/* ─────────────────────────────────────────────
   HTTP WRAPPER (ROBUSTO)
───────────────────────────────────────────── */

function ts_dolibarr_request($method, $path, $body = null) {
    if (!ts_dolibarr_is_configured()) {
        return ['ok' => false, 'error' => 'ERP no configurado'];
    }

    $url = ts_dolibarr_url($path);
    if (!$url) {
        return ['ok' => false, 'error' => 'URL Dolibarr inválida'];
    }

    $args = [
        'method'  => strtoupper($method),
        'headers' => ts_dolibarr_headers(),
        'timeout' => 15,
    ];

    if ($body !== null) {
        $args['body'] = wp_json_encode($body);
    }

    $response = wp_remote_request($url, $args);

    if (is_wp_error($response)) {
        return [
            'ok' => false,
            'error' => $response->get_error_message()
        ];
    }

    $code = wp_remote_retrieve_response_code($response);
    $raw  = wp_remote_retrieve_body($response);

    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $data = null;
    }

    return [
        'ok'    => ($code >= 200 && $code < 300),
        'code'  => $code,
        'data'  => $data,
        'error' => ($code >= 200 && $code < 300) ? null : "ERP HTTP {$code}"
    ];
}

/* ─────────────────────────────────────────────
   THIRD PARTY
───────────────────────────────────────────── */

function ts_dolibarr_find_or_create_thirdparty($email, $name) {
    $email = sanitize_email($email);
    $name  = sanitize_text_field($name);

    if (!$email) return 0;

    // SEARCH (sin inyección SQL en query string)
    $search = ts_dolibarr_request(
        'GET',
        'thirdparties?limit=1&sqlfilters=' . rawurlencode("t.email:='".$email."'")
    );

    if ($search['ok'] && !empty($search['data'][0]['id'])) {
        return (int) $search['data'][0]['id'];
    }

    // CREATE
    $create = ts_dolibarr_request('POST', 'thirdparties', [
        'name'        => $name ?: $email,
        'email'       => $email,
        'client'      => 1,
        'fournisseur' => 0,
        'status'      => 1,
    ]);

    if (!$create['ok']) {
        return 0;
    }

    // Dolibarr a veces devuelve ID directo o array
    if (is_numeric($create['data'])) {
        return (int) $create['data'];
    }

    if (is_array($create['data']) && isset($create['data']['id'])) {
        return (int) $create['data']['id'];
    }

    return 0;
}


function ts_register_order_in_dolibarr($order_number, $email, $name, array $items, $total) {
    if (!ts_dolibarr_is_configured()) {
        return ['ok' => false, 'error' => 'ERP no configurado'];
    }

    $email = sanitize_email($email);
    $name  = sanitize_text_field($name);
    $order_number = sanitize_text_field($order_number);

    $thirdparty_id = ts_dolibarr_find_or_create_thirdparty($email, $name);

    if (!$thirdparty_id) {
        return ['ok' => false, 'error' => 'Cliente ERP no disponible'];
    }

    $lines = [];

    foreach ($items as $item) {
        if (!is_array($item)) continue;

        $lines[] = [
            'desc'        => sanitize_text_field($item['name'] ?? ''),
            'subprice'    => (float) ($item['unit_price'] ?? 0),
            'qty'         => (int) ($item['quantity'] ?? 1),
            'tva_tx'      => 0,
            'product_ref' => sanitize_text_field($item['sku'] ?? ''),
        ];
    }

    if (empty($lines)) {
        return ['ok' => false, 'error' => 'Factura sin líneas válidas'];
    }

    $invoice = ts_dolibarr_request('POST', 'invoices', [
        'socid'      => $thirdparty_id,
        'ref_client' => $order_number,
        'type'       => 0,
        'lines'      => $lines,
    ]);

    if (!$invoice['ok']) {
        return [
            'ok' => false,
            'error' => $invoice['error'] ?? 'Error creando factura'
        ];
    }

    $invoice_id = 0;

    if (is_numeric($invoice['data'])) {
        $invoice_id = (int) $invoice['data'];
    } elseif (is_array($invoice['data']) && isset($invoice['data']['id'])) {
        $invoice_id = (int) $invoice['data']['id'];
    }

    if (!$invoice_id) {
        return ['ok' => false, 'error' => 'Respuesta inválida del ERP'];
    }

    ts_dolibarr_request('POST', "invoices/{$invoice_id}/validate", [
        'idwarehouse' => 0
    ]);

    return [
        'ok' => true,
        'invoice_id' => $invoice_id
    ];
}
