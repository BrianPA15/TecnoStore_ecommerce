<?php get_header(); ?>

<div class="container content-area">
    <h1 class="section-title">Carrito de compra</h1>

    <div id="ts-cart-empty" class="cart-empty" style="display:none;">
        <div class="cart-empty-icon">&#128722;</div>
        <p>Tu carrito está vacío.</p>
        <a href="<?php echo esc_url(get_post_type_archive_link('ts_product')); ?>" class="btn btn-primary">Ver productos</a>
    </div>

    <div id="ts-cart-content" style="display:none;">
        <div class="cart-layout">
            <div class="cart-items">
                <table class="cart-table">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Precio</th>
                            <th>Cantidad</th>
                            <th>Subtotal</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="ts-cart-rows"></tbody>
                </table>
                <div class="cart-actions">
                    <a href="<?php echo esc_url(get_post_type_archive_link('ts_product')); ?>" class="btn btn-secondary">
                        &larr; Seguir comprando
                    </a>
                    <button class="btn btn-outline-danger" id="ts-cart-clear">Vaciar carrito</button>
                </div>
            </div>

            <div class="cart-summary">
                <h3>Resumen del pedido</h3>
                <div class="cart-summary-row">
                    <span>Subtotal</span>
                    <span id="ts-cart-subtotal">0,00 €</span>
                </div>
                <div class="cart-summary-row">
                    <span>Envío</span>
                    <span id="ts-cart-shipping">Calculado en el siguiente paso</span>
                </div>
                <div class="cart-summary-row cart-summary-total">
                    <span>Total</span>
                    <span id="ts-cart-total">0,00 €</span>
                </div>
                <a href="<?php echo esc_url(home_url('/checkout/')); ?>" class="btn btn-primary btn-block" id="ts-btn-checkout">
                    Proceder al pago &rarr;
                </a>
                <div class="cart-secure-badge">&#128274; Pago 100% seguro</div>
            </div>
        </div>
    </div>
</div>

<?php get_footer(); ?>
