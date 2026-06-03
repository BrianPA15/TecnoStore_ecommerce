<?php get_header(); the_post(); ?>

<?php
$price     = ts_get_price(get_the_ID());
$stock     = intval(get_post_meta(get_the_ID(), '_ts_stock', true));
$sku       = get_post_meta(get_the_ID(), '_ts_sku', true);
$cats      = get_the_terms(get_the_ID(), 'ts_product_cat');
$cat_links = [];
if ($cats && !is_wp_error($cats)) {
    foreach ($cats as $c) {
        $cat_links[] = '<a href="' . esc_url(get_term_link($c)) . '">' . esc_html($c->name) . '</a>';
    }
}
?>

<div class="container content-area">

    <nav class="breadcrumb">
        <a href="<?php echo esc_url(home_url('/')); ?>">Inicio</a>
        <span>&rsaquo;</span>
        <a href="<?php echo esc_url(get_post_type_archive_link('ts_product')); ?>">Tienda</a>
        <?php if ($cat_links): ?>
            <span>&rsaquo;</span>
            <?php echo implode(', ', $cat_links); ?>
        <?php endif; ?>
        <span>&rsaquo;</span>
        <span><?php the_title(); ?></span>
    </nav>

    <div class="product-single">

        <div class="product-single-image">
            <?php if (has_post_thumbnail()): ?>
                <?php the_post_thumbnail('large'); ?>
            <?php else: ?>
                <div class="product-no-image product-no-image--large">&#128187;</div>
            <?php endif; ?>
        </div>

        <div class="product-single-info">
            <h1 class="product-single-title"><?php the_title(); ?></h1>

            <?php if ($cat_links): ?>
                <div class="product-single-cats"><?php echo implode(' &bull; ', $cat_links); ?></div>
            <?php endif; ?>

            <div class="product-single-price"><?php echo number_format($price, 2, ',', '.'); ?> €</div>

            <?php if ($stock > 0): ?>
                <div class="product-stock product-stock--in">
                    &#10003; En stock
                    <?php if ($stock <= 5): ?>
                        <span class="stock-warning">(quedan <?php echo $stock; ?> unidades)</span>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="product-stock product-stock--out">&#10007; Agotado</div>
            <?php endif; ?>

            <?php if ($sku): ?>
                <div class="product-sku">SKU: <code><?php echo esc_html($sku); ?></code></div>
            <?php endif; ?>

            <?php if ($stock > 0): ?>
                <div class="product-add-to-cart">
                    <div class="qty-control">
                        <button class="qty-btn qty-minus" type="button">&#8722;</button>
                        <input class="qty-input" id="ts-qty" type="number" value="1" min="1" max="<?php echo $stock; ?>" />
                        <button class="qty-btn qty-plus" type="button">&#43;</button>
                    </div>
                    <button class="btn btn-add-cart btn-add-cart--large"
                        data-id="<?php echo get_the_ID(); ?>"
                        data-name="<?php echo esc_attr(get_the_title()); ?>"
                        data-price="<?php echo esc_attr($price); ?>"
                        data-sku="<?php echo esc_attr($sku); ?>"
                        data-qty-source="ts-qty">
                        &#128722; Añadir al carrito
                    </button>
                </div>
                <a href="<?php echo esc_url(home_url('/checkout/')); ?>"
                   class="btn btn-buy-now"
                   data-id="<?php echo get_the_ID(); ?>"
                   data-name="<?php echo esc_attr(get_the_title()); ?>"
                   data-price="<?php echo esc_attr($price); ?>"
                   data-sku="<?php echo esc_attr($sku); ?>">
                    Comprar ahora
                </a>
            <?php else: ?>
                <button class="btn btn-disabled" disabled>Agotado</button>
            <?php endif; ?>

            <div class="product-trust-badges">
                <span>&#128274; Pago seguro</span>
                <span>&#128230; Envío en 24h</span>
                <span>&#128260; 30 días devolución</span>
            </div>
        </div>

    </div>

    <?php if (get_the_content()): ?>
        <div class="product-description">
            <h2>Descripción del producto</h2>
            <div class="product-description-body">
                <?php the_content(); ?>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php get_footer(); ?>
