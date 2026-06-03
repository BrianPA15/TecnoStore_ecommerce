(function ($) {
    'use strict';

    /* ============================================================
       CARRITO — persistencia en localStorage
       ============================================================ */

    var CART_KEY = 'ts_cart';

    var tsCart = {
        get: function () {
            try { return JSON.parse(localStorage.getItem(CART_KEY)) || []; }
            catch (e) { return []; }
        },
        save: function (items) {
            localStorage.setItem(CART_KEY, JSON.stringify(items));
        },
        add: function (id, name, price, sku, qty) {
            qty = qty || 1;
            var items = this.get();
            var found = false;
            for (var i = 0; i < items.length; i++) {
                if (items[i].id === id) {
                    items[i].qty += qty;
                    found = true;
                    break;
                }
            }
            if (!found) {
                items.push({ id: id, name: name, price: parseFloat(price), sku: sku, qty: qty });
            }
            this.save(items);
            this.updateBadge();
        },
        remove: function (id) {
            var items = this.get().filter(function (it) { return it.id !== id; });
            this.save(items);
            this.updateBadge();
        },
        updateQty: function (id, qty) {
            qty = parseInt(qty, 10);
            if (isNaN(qty) || qty < 1) qty = 1;
            var items = this.get();
            for (var i = 0; i < items.length; i++) {
                if (items[i].id === id) { items[i].qty = qty; break; }
            }
            this.save(items);
            this.updateBadge();
        },
        count: function () {
            return this.get().reduce(function (s, it) { return s + it.qty; }, 0);
        },
        total: function () {
            return this.get().reduce(function (s, it) { return s + it.price * it.qty; }, 0);
        },
        clear: function () {
            localStorage.removeItem(CART_KEY);
            this.updateBadge();
        },
        updateBadge: function () {
            var n = this.count();
            var $badge = $('#ts-cart-count');
            if (n > 0) {
                $badge.text(n).show();
            } else {
                $badge.hide();
            }
        }
    };

    /* ============================================================
       TOAST
       ============================================================ */

    function showToast(msg, type) {
        var $t = $('#ts-toast');
        if (!$t.length) {
            $t = $('<div class="ts-toast" id="ts-toast" aria-live="polite"></div>').appendTo('body');
        }
        $t.attr('class', 'ts-toast ts-toast--' + (type || 'success')).text(msg).addClass('ts-toast--visible');
        clearTimeout($t.data('timer'));
        $t.data('timer', setTimeout(function () { $t.removeClass('ts-toast--visible'); }, 2800));
    }

    /* ============================================================
       FORMATO
       ============================================================ */

    function formatPrice(n) {
        return parseFloat(n).toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
    }

    /* ============================================================
       BOTÓN "AÑADIR AL CARRITO"
       ============================================================ */

    $(document).on('click', '.btn-add-cart', function () {
        var $btn   = $(this);
        var id     = parseInt($btn.data('id'), 10);
        var name   = $btn.data('name');
        var price  = $btn.data('price');
        var sku    = $btn.data('sku');
        var qtySource = $btn.data('qty-source');
        var qty   = 1;

        if (qtySource) {
            qty = parseInt($('#' + qtySource).val(), 10) || 1;
        }

        tsCart.add(id, name, price, sku, qty);
        showToast('Añadido al carrito: ' + name);

        $btn.addClass('btn-added');
        setTimeout(function () { $btn.removeClass('btn-added'); }, 1200);
    });

    /* ============================================================
       BOTÓN "COMPRAR AHORA"
       ============================================================ */

    $(document).on('click', '.btn-buy-now', function (e) {
        e.preventDefault();
        var $btn = $(this);
        tsCart.add(
            parseInt($btn.data('id'), 10),
            $btn.data('name'),
            $btn.data('price'),
            $btn.data('sku'),
            1
        );
        window.location.href = tsConfig.checkoutUrl;
    });

    /* ============================================================
       CONTROL DE CANTIDAD (página de producto)
       ============================================================ */

    $(document).on('click', '.qty-minus', function () {
        var $inp = $(this).siblings('.qty-input');
        var val  = parseInt($inp.val(), 10) || 1;
        if (val > 1) $inp.val(val - 1);
    });

    $(document).on('click', '.qty-plus', function () {
        var $inp = $(this).siblings('.qty-input');
        var max  = parseInt($inp.attr('max'), 10) || 999;
        var val  = parseInt($inp.val(), 10) || 1;
        if (val < max) $inp.val(val + 1);
    });

    /* ============================================================
       PÁGINA DE CARRITO
       ============================================================ */

    function renderCart() {
        var $empty   = $('#ts-cart-empty');
        var $content = $('#ts-cart-content');
        if (!$empty.length) return;

        var items = tsCart.get();
        if (items.length === 0) {
            $empty.show();
            $content.hide();
            return;
        }

        $empty.hide();
        $content.show();

        var rows = '';
        items.forEach(function (it) {
            rows += '<tr data-id="' + it.id + '">' +
                '<td class="cart-td-name">' +
                    '<strong>' + $('<div>').text(it.name).html() + '</strong>' +
                    (it.sku ? '<br><small class="product-sku-small">SKU: ' + $('<div>').text(it.sku).html() + '</small>' : '') +
                '</td>' +
                '<td>' + formatPrice(it.price) + '</td>' +
                '<td><div class="qty-control qty-control--small">' +
                    '<button class="qty-btn qty-minus-cart" type="button">&#8722;</button>' +
                    '<input class="qty-input qty-input-cart" type="number" value="' + it.qty + '" min="1" />' +
                    '<button class="qty-btn qty-plus-cart" type="button">&#43;</button>' +
                '</div></td>' +
                '<td>' + formatPrice(it.price * it.qty) + '</td>' +
                '<td><button class="cart-remove-btn" data-id="' + it.id + '" title="Eliminar">&#10005;</button></td>' +
            '</tr>';
        });
        $('#ts-cart-rows').html(rows);

        var subtotal = tsCart.total();
        var shipping = subtotal >= 49 ? 'Gratis' : '4,99 €';
        var shippingVal = subtotal >= 49 ? 0 : 4.99;
        $('#ts-cart-subtotal').text(formatPrice(subtotal));
        $('#ts-cart-shipping').text(shipping);
        $('#ts-cart-total').text(formatPrice(subtotal + shippingVal));
    }

    // Cambiar cantidad en carrito
    $(document).on('change', '.qty-input-cart', function () {
        var id  = parseInt($(this).closest('tr').data('id'), 10);
        var qty = parseInt($(this).val(), 10);
        tsCart.updateQty(id, qty);
        renderCart();
    });

    $(document).on('click', '.qty-minus-cart', function () {
        var $inp = $(this).siblings('.qty-input-cart');
        var val  = parseInt($inp.val(), 10) || 1;
        if (val > 1) { $inp.val(val - 1).trigger('change'); }
    });

    $(document).on('click', '.qty-plus-cart', function () {
        var $inp = $(this).siblings('.qty-input-cart');
        var val  = parseInt($inp.val(), 10) || 1;
        $inp.val(val + 1).trigger('change');
    });

    // Eliminar línea
    $(document).on('click', '.cart-remove-btn', function () {
        var id = parseInt($(this).data('id'), 10);
        tsCart.remove(id);
        renderCart();
    });

    // Vaciar carrito
    $(document).on('click', '#ts-cart-clear', function () {
        if (confirm('¿Vaciar el carrito?')) {
            tsCart.clear();
            renderCart();
        }
    });

    /* ============================================================
       PÁGINA DE CHECKOUT
       ============================================================ */

    function renderCheckoutSummary() {
        var $empty   = $('#ts-checkout-empty');
        var $content = $('#ts-checkout-content');
        if (!$empty.length) return;

        var items = tsCart.get();
        if (items.length === 0) {
            $empty.show();
            $content.hide();
            return;
        }

        $empty.hide();
        $content.show();

        var html = '';
        items.forEach(function (it) {
            html += '<div class="order-item">' +
                '<span class="order-item-name">' + $('<div>').text(it.name).html() +
                    (it.qty > 1 ? ' <em>x' + it.qty + '</em>' : '') + '</span>' +
                '<span class="order-item-price">' + formatPrice(it.price * it.qty) + '</span>' +
            '</div>';
        });
        $('#ts-order-items').html(html);

        var subtotal    = tsCart.total();
        var shippingVal = subtotal >= 49 ? 0 : 4.99;
        $('#ts-order-subtotal').text(formatPrice(subtotal));
        $('#ts-order-shipping').text(shippingVal === 0 ? 'Gratis' : formatPrice(shippingVal));
        $('#ts-order-total').text(formatPrice(subtotal + shippingVal));
    }

    // Formatear número de tarjeta
    $(document).on('input', '#co-card', function () {
        var v = $(this).val().replace(/\D/g, '').substring(0, 16);
        $(this).val(v.replace(/(.{4})/g, '$1 ').trim());
    });

    // Formatear caducidad
    $(document).on('input', '#co-exp', function () {
        var v = $(this).val().replace(/\D/g, '').substring(0, 4);
        if (v.length >= 3) v = v.substring(0, 2) + '/' + v.substring(2);
        $(this).val(v);
    });

    // Envío del formulario de checkout
    $(document).on('submit', '#ts-checkout-form', function (e) {
        e.preventDefault();

        var $err = $('#ts-checkout-error');
        $err.hide().text('');

        // Validación de campos obligatorios (tarjeta se acepta cualquier valor)
        var nombre    = $.trim($('#co-name').val());
        var apellidos = $.trim($('#co-surname').val());
        var email     = $.trim($('#co-email').val());
        var address   = $.trim($('#co-address').val());
        var city      = $.trim($('#co-city').val());
        var zip       = $.trim($('#co-zip').val());

        if (!nombre || !apellidos || !email || !address || !city || !zip) {
            $err.text('Por favor, completa todos los campos obligatorios.').show();
            return;
        }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            $err.text('El correo electrónico no es válido.').show();
            return;
        }

        var subtotal    = tsCart.total();
        var shippingVal = subtotal >= 49 ? 0 : 4.99;
        var total       = subtotal + shippingVal;

        var orderNum = 'TS-' + new Date().getFullYear() + '-' + Math.floor(10000 + Math.random() * 90000);

        var deliveryDate = new Date();
        var days = 0;
        while (days < 2) {
            deliveryDate.setDate(deliveryDate.getDate() + 1);
            var dow = deliveryDate.getDay();
            if (dow !== 0 && dow !== 6) days++;
        }
        var deliveryStr = deliveryDate.toLocaleDateString('es-ES', { weekday: 'long', day: 'numeric', month: 'long' });

        var orderData = {
            orderNum:  orderNum,
            email:     email,
            nombre:    nombre,
            apellidos: apellidos,
            direccion: $.trim($('#co-address').val()),
            ciudad:    $.trim($('#co-city').val()),
            cp:        $.trim($('#co-zip').val()),
            pais:      $('#co-country').val(),
            items:     tsCart.get(),
            subtotal:  subtotal,
            shipping:  shippingVal,
            total:     total,
            delivery:  deliveryStr
        };

        var $btn = $('#ts-btn-submit');
        $btn.prop('disabled', true).html('&#9203; Procesando pago...');

        function finalizarPedido() {
            sessionStorage.setItem('ts_order', JSON.stringify(orderData));
            tsCart.clear();
            window.location.href = tsConfig.confirmUrl;
        }

        // Registrar pedido en la base de datos de WordPress
        $.ajax({
            url:         tsConfig.restUrl + 'orders',
            method:      'POST',
            contentType: 'application/json',
            data:        JSON.stringify(orderData),
            timeout:     8000,
            success: function () {
                // ── STUB: petición al sistema de almacén ──────────────
                // Se implementará en una sesión posterior cuando se tenga
                // el contexto completo de la API del almacén.
                //
                // $.ajax({
                //     url:     '<WAREHOUSE_URL>/api/orders',
                //     method:  'POST',
                //     headers: { Authorization: 'Bearer <JWT>' },
                //     data:    JSON.stringify(orderData),
                // });
                // ──────────────────────────────────────────────────────
                finalizarPedido();
            },
            error: function () {
                // Si el endpoint falla el pedido se completa igual para el cliente
                finalizarPedido();
            }
        });
    });

    /* ============================================================
       PÁGINA DE CONFIRMACIÓN
       ============================================================ */

    function renderConfirmation() {
        if (!$('#ts-confirm-details').length) return;

        var raw = sessionStorage.getItem('ts_order');
        if (!raw) return;

        try {
            var order = JSON.parse(raw);
            $('#ts-confirm-order-number').text(order.orderNum);
            $('#ts-confirm-email').text(order.email);
            $('#ts-confirm-total').text(formatPrice(order.total));
            $('#ts-confirm-delivery').text(order.delivery);
            sessionStorage.removeItem('ts_order');
        } catch (e) {}
    }

    /* ============================================================
       STICKY HEADER
       ============================================================ */

    var $header      = $('.header-main');
    var headerOffset = $header.offset() ? $header.offset().top : 0;

    $(window).on('scroll', function () {
        if ($(this).scrollTop() > headerOffset) {
            $header.addClass('sticky');
        } else {
            $header.removeClass('sticky');
        }
    });

    /* ============================================================
       INIT
       ============================================================ */

    $(function () {
        tsCart.updateBadge();
        renderCart();
        renderCheckoutSummary();
        renderConfirmation();
    });

})(jQuery);
