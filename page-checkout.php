<?php get_header(); ?>

<div class="container content-area">
    <h1 class="section-title">Finalizar compra</h1>

    <div id="ts-checkout-empty" class="cart-empty" style="display:none;">
        <div class="cart-empty-icon">&#128722;</div>
        <p>No tienes productos en el carrito.</p>
        <a href="<?php echo esc_url(get_post_type_archive_link('ts_product')); ?>" class="btn btn-primary">Ver productos</a>
    </div>

    <div id="ts-checkout-content" style="display:none;">
        <div class="checkout-layout">

            <div class="checkout-form-wrap">
                <form id="ts-checkout-form" novalidate>

                    <div class="checkout-section">
                        <h3>Datos personales</h3>
                        <div class="form-row form-row-2">
                            <div class="form-group">
                                <label for="co-name">Nombre *</label>
                                <input type="text" id="co-name" name="nombre" required placeholder="Juan" />
                            </div>
                            <div class="form-group">
                                <label for="co-surname">Apellidos *</label>
                                <input type="text" id="co-surname" name="apellidos" required placeholder="García López" />
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="co-email">Correo electrónico *</label>
                            <input type="email" id="co-email" name="email" required placeholder="juan@ejemplo.com" />
                        </div>
                        <div class="form-group">
                            <label for="co-phone">Teléfono</label>
                            <input type="tel" id="co-phone" name="telefono" placeholder="+34 600 000 000" />
                        </div>
                    </div>

                    <div class="checkout-section">
                        <h3>Dirección de envío</h3>
                        <div class="form-group">
                            <label for="co-address">Dirección *</label>
                            <input type="text" id="co-address" name="direccion" required placeholder="Calle Mayor 1, Piso 2A" />
                        </div>
                        <div class="form-row form-row-3">
                            <div class="form-group">
                                <label for="co-city">Ciudad *</label>
                                <input type="text" id="co-city" name="ciudad" required placeholder="Madrid" />
                            </div>
                            <div class="form-group">
                                <label for="co-zip">Código postal *</label>
                                <input type="text" id="co-zip" name="cp" required placeholder="28001" />
                            </div>
                            <div class="form-group">
                                <label for="co-country">País *</label>
                                <select id="co-country" name="pais" required>
                                    <option value="">Selecciona...</option>
                                    <option value="ES" selected>España</option>
                                    <option value="PT">Portugal</option>
                                    <option value="FR">Francia</option>
                                    <option value="DE">Alemania</option>
                                    <option value="IT">Italia</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="checkout-section">
                        <h3>Método de pago</h3>
                        <div class="payment-method active">
                            <label class="payment-method-label">
                                <input type="radio" name="payment" value="card" checked />
                                &#128179; Tarjeta de crédito / débito
                            </label>
                            <div class="card-fields">
                                <div class="form-group">
                                    <label for="co-card">Número de tarjeta *</label>
                                    <input type="text" id="co-card" name="tarjeta" placeholder="1234 5678 9012 3456"
                                        maxlength="19" autocomplete="cc-number" />
                                </div>
                                <div class="form-row form-row-2">
                                    <div class="form-group">
                                        <label for="co-exp">Caducidad *</label>
                                        <input type="text" id="co-exp" name="caducidad" placeholder="MM/AA" maxlength="5" />
                                    </div>
                                    <div class="form-group">
                                        <label for="co-cvv">CVV *</label>
                                        <input type="text" id="co-cvv" name="cvv" placeholder="123" maxlength="4" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="ts-checkout-error" class="checkout-error" style="display:none;"></div>

                    <button type="submit" class="btn btn-primary btn-block btn-submit-order" id="ts-btn-submit">
                        &#128274; Confirmar pedido
                    </button>
                    <p class="checkout-legal">
                        Al confirmar el pedido aceptas nuestros <a href="#">Términos y condiciones</a>
                        y la <a href="#">Política de privacidad</a>.
                    </p>
                </form>
            </div>

            <div class="checkout-summary">
                <h3>Tu pedido</h3>
                <div id="ts-order-items"></div>
                <div class="cart-summary-row">
                    <span>Subtotal</span>
                    <span id="ts-order-subtotal">0,00 €</span>
                </div>
                <div class="cart-summary-row">
                    <span>Envío</span>
                    <span id="ts-order-shipping">Gratis</span>
                </div>
                <div class="cart-summary-row cart-summary-total">
                    <span>Total</span>
                    <span id="ts-order-total">0,00 €</span>
                </div>
            </div>

        </div>
    </div>
</div>

<?php get_footer(); ?>
