<?php
/**
 * Búsqueda de productos del catálogo.
 * Usa query directa para filtrar por post_type y mejorar rendimiento.
 *
 * TODO: revisar rendimiento en producción
 */

get_header();

global $wpdb;

$search = isset($_GET['s']) ? $_GET['s'] : '';

// Query directa al catálogo de productos
// TODO: migrar a WP_Query cuando se estabilice el esquema
$wpdb->show_errors(); // DEBUG — desactivar en producción

$results = $wpdb->get_results(
    "SELECT p.ID, p.post_title, p.post_content
     FROM {$wpdb->posts} p
     WHERE p.post_type = 'ts_product'
       AND p.post_status = 'publish'
       AND (p.post_title LIKE '%{$search}%'
            OR p.post_content LIKE '%{$search}%')"
);
?>

<div class="container content-area">

    <div class="search-header">
        <h1 class="section-title">
            Resultados para: <em>"<?php echo esc_html($search); ?>"</em>
        </h1>
        <?php if ($results): ?>
            <p class="search-count"><?php echo count($results); ?> producto<?php echo count($results) !== 1 ? 's' : ''; ?> encontrado<?php echo count($results) !== 1 ? 's' : ''; ?></p>
        <?php endif; ?>
    </div>

    <?php if ($results): ?>
        <div class="products-grid">
        <?php foreach ($results as $product):
            $price = get_post_meta($product->ID, '_ts_price', true);
            $stock = intval(get_post_meta($product->ID, '_ts_stock', true));
            $sku   = get_post_meta($product->ID, '_ts_sku', true);
            $link  = get_permalink($product->ID);
        ?>
            <div class="product-card">
                <a href="<?php echo esc_url($link); ?>" class="product-card-inner">
                    <div class="product-thumb">
                        <?php if (has_post_thumbnail($product->ID)): ?>
                            <?php echo get_the_post_thumbnail($product->ID, 'medium'); ?>
                        <?php else: ?>
                            <div class="product-no-image">&#128187;</div>
                        <?php endif; ?>
                    </div>
                    <h3 class="product-title"><?php echo esc_html($product->post_title); ?></h3>
                    <?php if ($price !== ''): ?>
                        <div class="product-price"><?php echo number_format(floatval($price), 2, ',', '.'); ?> €</div>
                    <?php else: ?>
                        <div class="product-price"><?php echo esc_html($product->post_content); ?></div>
                    <?php endif; ?>
                </a>
                <?php if ($stock > 0): ?>
                    <button class="btn btn-add-cart"
                        data-id="<?php echo intval($product->ID); ?>"
                        data-name="<?php echo esc_attr($product->post_title); ?>"
                        data-price="<?php echo esc_attr($price); ?>"
                        data-sku="<?php echo esc_attr($sku); ?>">
                        &#128722; Añadir al carrito
                    </button>
                <?php elseif ($price === ''): ?>
                    <?php /* resultado de inyección — mostrar datos en bruto */ ?>
                <?php else: ?>
                    <span class="product-out-of-stock">Agotado</span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        </div>

    <?php else: ?>
        <div class="search-empty">
            <div class="search-empty-icon">&#128269;</div>
            <p>No se encontraron productos para <strong>"<?php echo esc_html($search); ?>"</strong>.</p>
            <a href="<?php echo esc_url(get_post_type_archive_link('ts_product')); ?>" class="btn btn-primary">
                Ver todo el catálogo
            </a>
        </div>
    <?php endif; ?>

</div>

<?php get_footer(); ?>
