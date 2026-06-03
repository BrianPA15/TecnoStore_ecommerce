<?php
/**
 * Script de configuración inicial de TecnoStore.
 * Crea el usuario administrador por defecto y las páginas del flujo de compra.
 *
 * Uso:
 *   wp eval-file wp-content/themes/tecnostore/seed/setup-defaults.php
 */

if (!defined('ABSPATH')) {
    $root = dirname(__FILE__, 5);
    if (!file_exists($root . '/wp-load.php')) {
        die("ERROR: No se encontró wp-load.php.\n");
    }
    require_once $root . '/wp-load.php';
}

// -----------------------------------------------------------------------
// Usuario administrador por defecto (vector: credenciales por defecto)
// -----------------------------------------------------------------------

$default_username = 'developer';
$default_password = 'masterjoyfe2026';
$default_email    = 'developer@tecnostore.local';

$existing = get_user_by('login', $default_username);

if ($existing) {
    echo "[skip] El usuario '{$default_username}' ya existe (ID: {$existing->ID}).\n";
} else {
    $user_id = wp_create_user($default_username, $default_password, $default_email);

    if (is_wp_error($user_id)) {
        echo "[error] No se pudo crear el usuario: " . $user_id->get_error_message() . "\n";
    } else {
        $user = new WP_User($user_id);
        $user->set_role('administrator');
        wp_update_user([
            'ID'           => $user_id,
            'display_name' => 'Developer',
            'first_name'   => 'Developer',
            'last_name'    => 'TecnoStore',
        ]);
        echo "[ok] Usuario administrador creado:\n";
        echo "     Login:    {$default_username}\n";
        echo "     Password: {$default_password}\n";
        echo "     Email:    {$default_email}\n";
        echo "     Rol:      administrator\n";
    }
}

echo "\n";

// -----------------------------------------------------------------------
// Páginas del flujo de compra
// -----------------------------------------------------------------------

$pages = [
    [
        'title'   => 'Carrito',
        'slug'    => 'carrito',
        'content' => '',
    ],
    [
        'title'   => 'Checkout',
        'slug'    => 'checkout',
        'content' => '',
    ],
    [
        'title'   => 'Pedido completado',
        'slug'    => 'pedido-completado',
        'content' => '',
    ],
];

foreach ($pages as $page_data) {
    $existing_page = get_page_by_path($page_data['slug']);
    if ($existing_page) {
        echo "[skip] Página '{$page_data['title']}' ya existe.\n";
    } else {
        $page_id = wp_insert_post([
            'post_title'   => $page_data['title'],
            'post_name'    => $page_data['slug'],
            'post_content' => $page_data['content'],
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ]);
        if (is_wp_error($page_id)) {
            echo "[error] Página '{$page_data['title']}': " . $page_id->get_error_message() . "\n";
        } else {
            echo "[ok] Página '{$page_data['title']}' creada (slug: /{$page_data['slug']}/, ID: {$page_id})\n";
        }
    }
}

echo "\n";

// -----------------------------------------------------------------------
// Flush de rewrite rules para el CPT
// -----------------------------------------------------------------------
flush_rewrite_rules();
echo "[ok] Rewrite rules actualizados.\n";

echo "\n==============================================\n";
echo "Setup completado.\n";
echo "Recuerda ejecutar seed-products.php para cargar el catálogo.\n";
echo "==============================================\n";
