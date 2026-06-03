<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>

<header class="site-header">
    <div class="header-top">
        <div class="container">
            <span class="header-top-text">Envío gratis en pedidos superiores a 49€</span>
            <span class="header-top-text">Soporte técnico: soporte@tecnostore.local</span>
        </div>
    </div>
    <div class="header-main">
        <div class="container header-main-inner">
            <a href="<?php echo esc_url(home_url('/')); ?>" class="site-logo">
                <span class="logo-icon">&#128187;</span>
                <span class="logo-text">Tecno<strong>Store</strong></span>
            </a>

            <div class="header-search">
                <form role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
                    <input type="search" placeholder="Buscar productos..." value="<?php echo get_search_query(); ?>" name="s" />
                    <input type="hidden" name="post_type" value="ts_product" />
                    <button type="submit">&#128269;</button>
                </form>
            </div>

            <div class="header-actions">
                <a href="<?php echo esc_url(home_url('/carrito/')); ?>" class="cart-icon" id="ts-cart-link">
                    <span class="cart-icon-symbol">&#128722;</span>
                    <span class="cart-count" id="ts-cart-count" style="display:none;">0</span>
                </a>
            </div>
        </div>
    </div>

    <nav class="site-nav">
        <div class="container">
            <?php
            wp_nav_menu([
                'theme_location' => 'primary',
                'menu_class'     => 'nav-menu',
                'fallback_cb'    => 'tecnostore_fallback_nav',
            ]);
            ?>
        </div>
    </nav>
</header>

<main class="site-main">
<?php

function tecnostore_fallback_nav() {
    echo '<ul class="nav-menu">';
    echo '<li><a href="' . esc_url(home_url('/')) . '">Inicio</a></li>';
    echo '<li><a href="' . esc_url(home_url('/tienda/')) . '">Tienda</a></li>';
    echo '</ul>';
}
