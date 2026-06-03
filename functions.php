<?php
defined('ABSPATH') || exit;

function tecnostore_setup() {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption']);

    register_nav_menus([
        'primary' => __('Menú Principal', 'tecnostore'),
        'footer'  => __('Menú Footer', 'tecnostore'),
    ]);
}
add_action('after_setup_theme', 'tecnostore_setup');

function tecnostore_register_cpt() {
    register_post_type('ts_product', [
        'labels' => [
            'name'               => 'Productos',
            'singular_name'      => 'Producto',
            'add_new_item'       => 'Añadir producto',
            'edit_item'          => 'Editar producto',
            'new_item'           => 'Nuevo producto',
            'view_item'          => 'Ver producto',
            'search_items'       => 'Buscar productos',
            'not_found'          => 'No se encontraron productos',
            'not_found_in_trash' => 'No hay productos en la papelera',
        ],
        'public'       => true,
        'has_archive'  => true,
        'rewrite'      => ['slug' => 'tienda'],
        'menu_icon'    => 'dashicons-cart',
        'supports'     => ['title', 'editor', 'thumbnail'],
        'show_in_rest' => true,
    ]);

    register_taxonomy('ts_product_cat', 'ts_product', [
        'labels' => [
            'name'          => 'Categorías',
            'singular_name' => 'Categoría',
            'search_items'  => 'Buscar categorías',
            'all_items'     => 'Todas las categorías',
            'edit_item'     => 'Editar categoría',
            'update_item'   => 'Actualizar categoría',
            'add_new_item'  => 'Añadir categoría',
            'new_item_name' => 'Nueva categoría',
            'menu_name'     => 'Categorías',
        ],
        'hierarchical' => true,
        'public'       => true,
        'rewrite'      => ['slug' => 'categoria'],
        'show_in_rest' => true,
    ]);
}
add_action('init', 'tecnostore_register_cpt');

function tecnostore_enqueue() {
    wp_enqueue_style('tecnostore-main', get_template_directory_uri() . '/assets/css/main.css', [], '1.1.0');
    wp_enqueue_script('tecnostore-main', get_template_directory_uri() . '/assets/js/main.js', ['jquery'], '1.1.0', true);

    wp_localize_script('tecnostore-main', 'tsConfig', [
        'cartUrl'    => home_url('/carrito/'),
        'checkoutUrl'=> home_url('/checkout/'),
        'confirmUrl' => home_url('/pedido-completado/'),
        'restUrl'    => get_rest_url(null, 'tecnostore/v1/'),
    ]);
}
add_action('wp_enqueue_scripts', 'tecnostore_enqueue');

function ts_get_price($post_id) {
    $price = get_post_meta($post_id, '_ts_price', true);
    return $price !== '' ? floatval($price) : 0.0;
}

// Fija el orden y la paginación de la query principal en el archivo de productos.
// Sin esto MySQL devuelve filas en orden arbitrario, lo que cambia entre páginas.
add_action('pre_get_posts', function ($query) {
    if (is_admin() || !$query->is_main_query()) return;
    if (!$query->is_post_type_archive('ts_product') && !$query->is_tax('ts_product_cat')) return;

    $query->set('orderby', 'title');
    $query->set('order',   'ASC');
    $query->set('posts_per_page', 12);
});

// Crea las páginas del flujo de compra si no existen.
// Se ejecuta al activar el tema y en wp_loaded (con flag para no repetir).
function tecnostore_create_pages() {
    if (get_option('tecnostore_pages_v1')) return;

    $pages = [
        ['title' => 'Carrito',           'slug' => 'carrito'],
        ['title' => 'Checkout',          'slug' => 'checkout'],
        ['title' => 'Pedido completado', 'slug' => 'pedido-completado'],
    ];

    foreach ($pages as $p) {
        if (!get_page_by_path($p['slug'])) {
            wp_insert_post([
                'post_title'  => $p['title'],
                'post_name'   => $p['slug'],
                'post_status' => 'publish',
                'post_type'   => 'page',
            ]);
        }
    }

    update_option('tecnostore_pages_v1', 1);
    flush_rewrite_rules();
}
add_action('after_switch_theme', 'tecnostore_create_pages');
add_action('wp_loaded',          'tecnostore_create_pages');

require_once get_template_directory() . '/inc/admin-settings.php';
require_once get_template_directory() . '/inc/admin-orders.php';
require_once get_template_directory() . '/inc/dolibarr-integration.php';
require_once get_template_directory() . '/inc/endpoints.php';
