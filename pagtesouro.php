<?php
/*
Plugin Name: PagTesouro para WooCommerce
Description: Adiciona o método de pagamento PagTesouro ao WooCommerce
Version: 1.0
Author: Rodrigo Crosara
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

add_action( 'plugins_loaded', 'init_pagtesouro_gateway' );
function init_pagtesouro_gateway() {
    // Verifique se o WooCommerce está ativo
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    // Inclua a classe WC_PagTesouro_Gateway
    require_once plugin_dir_path(__FILE__) . 'class-wc-pagtesouro-gateway.php';

    // Adicione o gateway PagTesouro ao WooCommerce
    add_filter('woocommerce_payment_gateways', 'add_pagtesouro_gateway');
    function add_pagtesouro_gateway($methods)
    {
        $methods[] = 'WC_PagTesouro_Gateway';
        return $methods;
    }

}
