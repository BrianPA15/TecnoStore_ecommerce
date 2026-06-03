<?php get_header(); ?>

<div class="container content-area">

    <div class="shop-header">
        <h1 class="section-title">
            <?php
            $current_cat = get_queried_object();
            if (is_tax('ts_product_cat') && $current_cat) {
                echo esc_html($current_cat->name);
            } else {
                echo 'Tienda';
            }
            ?>
        </h1>

        <!-- Filtro de categorías -->
        <div class="shop-filters">
            <a href="<?php echo esc_url(get_post_type_archive_link('ts_product')); ?>"
               class="filter-tag <?php echo !is_tax('ts_product_cat') ? 'active' : ''; ?>">
                Todos
            </a>
            <?php
            $cats = get_terms(['taxonomy' => 'ts_product_cat', 'hide_empty' => true]);
            foreach ($cats as $cat):
                $active = (is_tax('ts_product_cat') && get_queried_object_id() === $cat->term_id) ? 'active' : '';
            ?>
            <a href="<?php echo esc_url(get_term_link($cat)); ?>" class="filter-tag <?php echo $active; ?>">
                <?php echo esc_html($cat->name); ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (have_posts()): ?>
        <div class="products-grid">
        <?php while (have_posts()): the_post();
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
                    <?php if ($stock <= 0): ?>
                        <span class="product-badge-out">Agotado</span>
                    <?php elseif ($stock <= 5): ?>
                        <span class="product-badge-low">Últimas unidades</span>
                    <?php endif; ?>
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
        <?php endwhile; ?>
        </div>

        <div class="pagination">
            <?php
            echo paginate_links([
                'prev_text' => '&larr; Anterior',
                'next_text' => 'Siguiente &rarr;',
            ]);
            ?>
        </div>

    <?php else: ?>
        <div class="notice-box">No se encontraron productos en esta categoría.</div>
    <?php endif; ?>

</div>

<?php get_footer(); ?>
