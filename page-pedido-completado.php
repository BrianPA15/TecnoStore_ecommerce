<?php get_header(); ?>

<div class="container content-area">
    <div class="order-confirm">
        <div class="order-confirm-icon">&#10003;</div>
        <h1>¡Pedido confirmado!</h1>
        <p class="order-confirm-sub">Gracias por tu compra. Recibirás un correo de confirmación en breve.</p>

        <div class="order-confirm-details" id="ts-confirm-details">
            <div class="order-confirm-row">
                <span>Número de pedido</span>
                <strong id="ts-confirm-order-number">—</strong>
            </div>
            <div class="order-confirm-row">
                <span>Correo de confirmación</span>
                <strong id="ts-confirm-email">—</strong>
            </div>
            <div class="order-confirm-row">
                <span>Total pagado</span>
                <strong id="ts-confirm-total">—</strong>
            </div>
            <div class="order-confirm-row">
                <span>Entrega estimada</span>
                <strong id="ts-confirm-delivery">—</strong>
            </div>
        </div>

        <div class="order-confirm-actions">
            <a href="<?php echo esc_url(home_url('/')); ?>" class="btn btn-primary">Volver al inicio</a>
            <a href="<?php echo esc_url(get_post_type_archive_link('ts_product')); ?>" class="btn btn-secondary">Seguir comprando</a>
        </div>

        <div class="order-confirm-trust">
            <span>&#128230; Tu pedido será enviado en 24h</span>
            <span>&#128274; Transacción protegida</span>
            <span>&#128260; 30 días para devolver</span>
        </div>
    </div>
</div>

<?php get_footer(); ?>
