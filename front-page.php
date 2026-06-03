<?php get_header(); ?>

<!-- Hero -->
<section class="hero">
    <div class="container hero-inner">
        <div class="hero-text">
            <h1>Tecnología de alto rendimiento<br>al mejor precio</h1>
            <p>Portátiles, componentes, periféricos y mucho más. Envío en 24h y garantía oficial.</p>
            <a href="<?php echo esc_url(get_post_type_archive_link('ts_product')); ?>" class="btn btn-primary">
                Ver catálogo
            </a>
        </div>
        <div class="hero-image">
            <div class="hero-placeholder">&#128187;</div>
        </div>
    </div>
</section>

<!-- Categorías destacadas -->
<section class="categories-section">
    <div class="container">
        <h2 class="section-title">Categorías</h2>
        <div class="categories-grid">
            <?php
            $categories = [
                ['name' => 'Portátiles',     'icon' => '&#128187;', 'slug' => 'portatiles'],
                ['name' => 'Componentes',    'icon' => '&#129527;', 'slug' => 'componentes'],
                ['name' => 'Monitores',      'icon' => '&#128444;', 'slug' => 'monitores'],
                ['name' => 'Periféricos',    'icon' => '&#9000;',   'slug' => 'perifericos'],
                ['name' => 'Almacenamiento', 'icon' => '&#128190;', 'slug' => 'almacenamiento'],
                ['name' => 'Redes',          'icon' => '&#128225;', 'slug' => 'redes'],
            ];
            foreach ($categories as $cat):
                $term = get_term_by('slug', $cat['slug'], 'ts_product_cat');
                $url  = $term ? get_term_link($term) : get_post_type_archive_link('ts_product');
            ?>
            <a href="<?php echo esc_url($url); ?>" class="category-card">
                <span class="category-icon"><?php echo $cat['icon']; ?></span>
                <span class="category-name"><?php echo esc_html($cat['name']); ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Productos destacados -->
<section class="products-section">
    <div class="container">
        <h2 class="section-title">Productos destacados</h2>
        <?php
        $featured = new WP_Query([
            'post_type'      => 'ts_product',
            'posts_per_page' => 8,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'post_status'    => 'publish',
        ]);
        if ($featured->have_posts()):
        ?>
        <div class="products-grid">
        <?php while ($featured->have_posts()): $featured->the_post();
            $price = ts_get_price(get_the_ID());
            $stock = intval(get_post_meta(get_the_ID(), '_ts_stock', true));
            $sku   = get_post_meta(get_the_ID(), '_ts_sku', true);
        ?>
            <div class="product-card">
                <a href="<?php the_permalink(); ?>" class="product-card-inner">
                    <div class="product-thumb">
                        <?php if (has_post_thumbnail()): ?>
                            <?php the_post_thumbnail('medium'); ?>
                        <?php else: ?>
                            <div class="product-no-image">&#128187;</div>
                        <?php endif; ?>
                    </div>
                    <h3 class="product-title"><?php the_title(); ?></h3>
                    <div class="product-price"><?php echo number_format($price, 2, ',', '.'); ?> €</div>
                </a>
                <?php if ($stock > 0): ?>
                    <button class="btn btn-add-cart"
                        data-id="<?php echo get_the_ID(); ?>"
                        data-name="<?php echo esc_attr(get_the_title()); ?>"
                        data-price="<?php echo esc_attr($price); ?>"
                        data-sku="<?php echo esc_attr($sku); ?>">
                        &#128722; Añadir al carrito
                    </button>
                <?php else: ?>
                    <span class="product-out-of-stock">Agotado</span>
                <?php endif; ?>
            </div>
        <?php endwhile; wp_reset_postdata(); ?>
        </div>
        <div style="text-align:center;margin-top:40px;">
            <a href="<?php echo esc_url(get_post_type_archive_link('ts_product')); ?>" class="btn btn-secondary">Ver todos los productos</a>
        </div>
        <?php else: ?>
            <div class="notice-box">El catálogo está vacío. Ejecuta el script de seed para cargar los productos.</div>
        <?php endif; ?>
    </div>
</section>

<!-- Banners de confianza -->
<section class="trust-section">
    <div class="container trust-grid">
        <div class="trust-item">
            <span class="trust-icon">&#128274;</span>
            <div>
                <strong>Pago 100% seguro</strong>
                <p>Transacciones cifradas con SSL</p>
            </div>
        </div>
        <div class="trust-item">
            <span class="trust-icon">&#128230;</span>
            <div>
                <strong>Envío en 24h</strong>
                <p>En pedidos realizados antes de las 15:00</p>
            </div>
        </div>
        <div class="trust-item">
            <span class="trust-icon">&#128260;</span>
            <div>
                <strong>30 días de devolución</strong>
                <p>Sin preguntas, sin complicaciones</p>
            </div>
        </div>
        <div class="trust-item">
            <span class="trust-icon">&#127881;</span>
            <div>
                <strong>Garantía oficial</strong>
                <p>Todos nuestros productos son originales</p>
            </div>
        </div>
    </div>
</section>

<?php get_footer(); ?>
