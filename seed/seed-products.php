<?php
/**
 * Script de carga inicial de productos para TecnoStore.
 * Usa el Custom Post Type ts_product (sin WooCommerce).
 *
 * Uso desde la raíz de WordPress:
 *   wp eval-file wp-content/themes/tecnostore/seed/seed-products.php
 */

if (!defined('ABSPATH')) {
    $root = dirname(__FILE__, 5);
    if (!file_exists($root . '/wp-load.php')) {
        die("ERROR: No se encontró wp-load.php. Usa WP-CLI desde la raíz de WordPress.\n");
    }
    require_once $root . '/wp-load.php';
}

// Asegurarse de que el CPT está registrado
do_action('init');

// -----------------------------------------------------------------------
// Categorías (taxonomía ts_product_cat)
// -----------------------------------------------------------------------
$categories = [
    'portatiles'     => 'Portátiles',
    'componentes'    => 'Componentes',
    'monitores'      => 'Monitores',
    'perifericos'    => 'Periféricos',
    'almacenamiento' => 'Almacenamiento',
    'redes'          => 'Redes',
];

$cat_ids = [];
foreach ($categories as $slug => $name) {
    $term = get_term_by('slug', $slug, 'ts_product_cat');
    if (!$term) {
        $result = wp_insert_term($name, 'ts_product_cat', ['slug' => $slug]);
        $cat_ids[$slug] = is_wp_error($result) ? 0 : $result['term_id'];
        echo "  [nueva cat] {$name}\n";
    } else {
        $cat_ids[$slug] = $term->term_id;
        echo "  [cat ok]    {$name}\n";
    }
}

echo "\n";

// -----------------------------------------------------------------------
// Productos
// -----------------------------------------------------------------------
$products = [
    // Portátiles
    [
        'name'        => 'Laptop Dell XPS 15 (2023) — Intel Core i7, 16GB RAM, 512GB SSD',
        'sku'         => 'DLXPS15-I7-16-512',
        'price'       => 1349.00,
        'stock'       => 12,
        'category'    => 'portatiles',
        'description' => 'Portátil premium con pantalla OLED 15.6", procesador Intel Core i7-13700H, 16GB DDR5 y SSD NVMe 512GB. Ideal para profesionales y creadores de contenido.',
    ],
    [
        'name'        => 'Lenovo ThinkPad X1 Carbon Gen 11 — Intel Core i5, 8GB, 256GB',
        'sku'         => 'LNTPX1C-I5-8-256',
        'price'       => 1099.00,
        'stock'       => 8,
        'category'    => 'portatiles',
        'description' => 'Ultrabook empresarial ligero (1.12 kg), pantalla IPS 14", teclado ergonómico y batería de 57Wh con hasta 15h de autonomía.',
    ],
    [
        'name'        => 'HP EliteBook 840 G10 — AMD Ryzen 7, 16GB, 512GB',
        'sku'         => 'HPEB840-R7-16-512',
        'price'       => 1199.00,
        'stock'       => 6,
        'category'    => 'portatiles',
        'description' => 'Portátil profesional con AMD Ryzen 7 7730U, pantalla FHD antirreflejo, seguridad HP Sure Start y conectividad WiFi 6E.',
    ],
    [
        'name'        => 'ASUS ROG Zephyrus G14 — Ryzen 9, RTX 4060, 16GB, 1TB',
        'sku'         => 'ASRGZ14-R9-4060-16',
        'price'       => 1649.00,
        'stock'       => 5,
        'category'    => 'portatiles',
        'description' => 'Portátil gaming ultracompacto con AMD Ryzen 9 7940HS, NVIDIA RTX 4060, pantalla QHD 165Hz y MUX Switch para máximo rendimiento.',
    ],

    // Componentes
    [
        'name'        => 'Procesador Intel Core i9-13900K — LGA1700, 24 núcleos',
        'sku'         => 'CPU-I9-13900K',
        'price'       => 549.00,
        'stock'       => 15,
        'category'    => 'componentes',
        'description' => 'CPU de alto rendimiento con 8P+16E núcleos (24 total), hasta 5.8GHz turbo, compatible con DDR4 y DDR5. Sin cooler incluido.',
    ],
    [
        'name'        => 'AMD Ryzen 9 7950X — AM5, 16 núcleos, 4.5GHz base',
        'sku'         => 'CPU-R9-7950X',
        'price'       => 629.00,
        'stock'       => 10,
        'category'    => 'componentes',
        'description' => '16 núcleos / 32 hilos, hasta 5.7GHz boost, proceso TSMC 5nm, TDP 170W. La opción definitiva para workstations y estaciones de trabajo.',
    ],
    [
        'name'        => 'Tarjeta Gráfica NVIDIA GeForce RTX 4070 Ti 12GB GDDR6X',
        'sku'         => 'GPU-RTX4070TI-12G',
        'price'       => 799.00,
        'stock'       => 7,
        'category'    => 'componentes',
        'description' => 'GPU NVIDIA Ada Lovelace con 12GB GDDR6X, DLSS 3.0, ray tracing de última generación. Perfecta para gaming 4K y cargas de trabajo en IA.',
    ],
    [
        'name'        => 'RAM Corsair Vengeance DDR5 32GB (2x16GB) 5600MHz CL36',
        'sku'         => 'RAM-COR-DDR5-32G',
        'price'       => 129.00,
        'stock'       => 25,
        'category'    => 'componentes',
        'description' => 'Kit de memoria DDR5 de alto rendimiento con XMP 3.0, disipadores de aluminio y compatibilidad con Intel 12ª/13ª gen y AMD AM5.',
    ],
    [
        'name'        => 'Placa Base ASUS ROG Strix Z790-E Gaming WiFi — LGA1700',
        'sku'         => 'MB-ASROGZ790E',
        'price'       => 449.00,
        'stock'       => 9,
        'category'    => 'componentes',
        'description' => 'Placa base ATX para Intel 12ª/13ª gen, VRM 18+1, PCIe 5.0, WiFi 6E, Bluetooth 5.3 y soporte para DDR5 hasta 7800MHz OC.',
    ],

    // Monitores
    [
        'name'        => 'Monitor LG 27GP950-B — 27" 4K UHD, 144Hz, IPS, 1ms',
        'sku'         => 'MON-LG27GP950',
        'price'       => 699.00,
        'stock'       => 11,
        'category'    => 'monitores',
        'description' => 'Monitor gaming 4K con panel Nano IPS, 144Hz, NVIDIA G-Sync Compatible, HDMI 2.1 y DisplayPort 1.4. Cobertura DCI-P3 98%.',
    ],
    [
        'name'        => 'Monitor Samsung Odyssey G9 — 49" Curved DQHD 240Hz',
        'sku'         => 'MON-SAM-G9-49',
        'price'       => 1299.00,
        'stock'       => 4,
        'category'    => 'monitores',
        'description' => 'Monitor curvo ultrawide 49" con resolución DQHD (5120x1440), 240Hz, 1ms GtG, HDR1000, compatibilidad con G-Sync y FreeSync Premium Pro.',
    ],
    [
        'name'        => 'Monitor BenQ PD2705Q — 27" QHD 2560x1440 IPS, Diseño',
        'sku'         => 'MON-BNQ-PD2705Q',
        'price'       => 379.00,
        'stock'       => 14,
        'category'    => 'monitores',
        'description' => 'Monitor profesional para diseñadores con calibración de fábrica, sRGB 100%, AdobeRGB 95%, USB-C 65W y hub USB 3.1.',
    ],

    // Periféricos
    [
        'name'        => 'Teclado Mecánico Logitech MX Mechanical — Brown Switches',
        'sku'         => 'TEC-LOG-MXMEC-BRN',
        'price'       => 149.00,
        'stock'       => 20,
        'category'    => 'perifericos',
        'description' => 'Teclado mecánico inalámbrico con switches táctiles silenciosos, retroiluminación LED inteligente, Bluetooth Multi-Device y batería para 15 días.',
    ],
    [
        'name'        => 'Ratón Logitech MX Master 3S — 8000 DPI, Silencioso',
        'sku'         => 'RAT-LOG-MXM3S',
        'price'       => 99.00,
        'stock'       => 30,
        'category'    => 'perifericos',
        'description' => 'Ratón ergonómico de alto rendimiento con sensor óptico 8000 DPI, rueda MagSpeed electromagnética, clic silencioso y Bluetooth Multi-Device.',
    ],
    [
        'name'        => 'Auriculares Sony WH-1000XM5 — ANC, 30h batería',
        'sku'         => 'AUR-SNY-WH1000XM5',
        'price'       => 349.00,
        'stock'       => 16,
        'category'    => 'perifericos',
        'description' => 'Auriculares over-ear con cancelación de ruido adaptativa líder del sector, 30h de batería, carga rápida y micrófono con IA para llamadas.',
    ],
    [
        'name'        => 'Webcam Logitech Brio 4K Ultra HD — 90fps, HDR',
        'sku'         => 'CAM-LOG-BRIO4K',
        'price'       => 199.00,
        'stock'       => 18,
        'category'    => 'perifericos',
        'description' => 'Cámara web 4K con HDR, zoom digital 5x, corrección automática de luz y micrófono estéreo con cancelación de ruido. Plug-and-play USB.',
    ],

    // Almacenamiento
    [
        'name'        => 'SSD Samsung 990 Pro NVMe M.2 2TB — 7450 MB/s',
        'sku'         => 'SSD-SAM-990PRO-2T',
        'price'       => 189.00,
        'stock'       => 22,
        'category'    => 'almacenamiento',
        'description' => 'SSD NVMe PCIe 4.0 con velocidad de lectura secuencial hasta 7450 MB/s y escritura 6900 MB/s. Ideal para gaming, edición de vídeo y cargas de trabajo intensivas.',
    ],
    [
        'name'        => 'SSD WD Black SN850X NVMe M.2 1TB — PCIe 4.0',
        'sku'         => 'SSD-WD-SN850X-1T',
        'price'       => 109.00,
        'stock'       => 28,
        'category'    => 'almacenamiento',
        'description' => 'SSD gaming de alto rendimiento con Game Mode 2.0, lectura 7300 MB/s y escritura 6300 MB/s. Compatible con PS5 y PC PCIe 4.0.',
    ],
    [
        'name'        => 'HDD Seagate BarraCuda 4TB — 3.5", 5400 RPM, SATA III',
        'sku'         => 'HDD-SEA-BARCUDA-4T',
        'price'       => 79.00,
        'stock'       => 35,
        'category'    => 'almacenamiento',
        'description' => 'Disco duro de almacenamiento masivo 4TB con caché 256MB, interfaz SATA 6Gb/s y tecnología MultiTier Caching para optimizar rendimiento.',
    ],

    // Redes
    [
        'name'        => 'Router ASUS RT-AX88U — WiFi 6, AX6000, 8 puertos LAN',
        'sku'         => 'ROU-ASUS-AX88U',
        'price'       => 299.00,
        'stock'       => 13,
        'category'    => 'redes',
        'description' => 'Router gaming WiFi 6 con velocidad AX6000, procesador quad-core 1.8GHz, soporte para hasta 512 dispositivos y VPN integrada.',
    ],
    [
        'name'        => 'Switch TP-Link TL-SG1024D — 24 puertos Gigabit no gestionable',
        'sku'         => 'SWI-TPL-SG1024D',
        'price'       => 89.00,
        'stock'       => 19,
        'category'    => 'redes',
        'description' => 'Switch de escritorio 24 puertos Gigabit con tecnología Energy Efficient Ethernet (EEE) y carcasa metálica robusta.',
    ],
];

// -----------------------------------------------------------------------
// Creación de productos
// -----------------------------------------------------------------------
$created = 0;
$skipped = 0;

foreach ($products as $data) {
    // Buscar por SKU
    $existing = get_posts([
        'post_type'      => 'ts_product',
        'posts_per_page' => 1,
        'meta_key'       => '_ts_sku',
        'meta_value'     => $data['sku'],
        'post_status'    => 'any',
        'fields'         => 'ids',
    ]);

    if (!empty($existing)) {
        echo "  [skip] {$data['sku']} ya existe.\n";
        $skipped++;
        continue;
    }

    $post_id = wp_insert_post([
        'post_title'   => $data['name'],
        'post_content' => $data['description'],
        'post_type'    => 'ts_product',
        'post_status'  => 'publish',
    ]);

    if (is_wp_error($post_id)) {
        echo "  [error] {$data['sku']}: " . $post_id->get_error_message() . "\n";
        continue;
    }

    update_post_meta($post_id, '_ts_sku',   $data['sku']);
    update_post_meta($post_id, '_ts_price', $data['price']);
    update_post_meta($post_id, '_ts_stock', $data['stock']);

    $cat_slug = $data['category'];
    if (!empty($cat_ids[$cat_slug])) {
        wp_set_post_terms($post_id, [$cat_ids[$cat_slug]], 'ts_product_cat');
    }

    echo "  [ok]   {$data['sku']} — {$data['name']} (ID: {$post_id})\n";
    $created++;
}

echo "\n==============================================\n";
echo "Seed completado: {$created} creados, {$skipped} omitidos.\n";
echo "==============================================\n";
