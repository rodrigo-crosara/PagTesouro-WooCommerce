<?php
/*
Plugin Name: PagTesouro para WooCommerce
Description: Adiciona o método de pagamento PagTesouro ao WooCommerce
Version: 1.1
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

    // Alterar o conteúdo da coluna 'column-order_status'
    function custom_payment_status_content($order_status, $order)
    {
        // Verificar se é um pedido processado pelo PagTesouro
        if ('pagtesouro' === get_post_meta($order->get_id(), '_payment_method', true)) {
            // Obter o status de pagamento do PagTesouro
            $payment_status = get_post_meta($order->get_id(), '_pagtesouro_payment_status', true);

            // Verificar o status de pagamento e retornar o status personalizado
            switch ($payment_status) {
                case 'CRIADO':
                    $order_status = __('Pagamento Criado', 'woocommerce');
                    break;
                case 'INICIADO':
                    $order_status = __('Pagamento Iniciado', 'woocommerce');
                    break;
                case 'SUBMETIDO':
                    $order_status = __('Pagamento Submetido', 'woocommerce');
                    break;
                case 'CONCLUIDO':
                    $order_status = __('Pagamento Concluído', 'woocommerce');
                    break;
                case 'REJEITADO':
                    $order_status = __('Pagamento Rejeitado', 'woocommerce');
                    break;
                case 'CANCELADO':
                    $order_status = __('Pagamento Cancelado', 'woocommerce');
                    break;
            }
        }

        return $order_status;
    }
    add_filter('woocommerce_admin_order_status', 'custom_payment_status_content', 10, 2);

    // Adicione um botão personalizado na coluna "wc_actions"
    add_action('woocommerce_admin_order_actions', 'add_custom_order_action', 10, 2);
    function add_custom_order_action($actions, $order)
    {
        // Verifique se o pedido usa o gateway PagTesouro
        if ($order->get_payment_method() === 'pagtesouro') {
            $order_id = $order->get_id();
            $id_pagamento = get_post_meta($order_id, '_id_pagamento', true);

            // Verifique se o ID do pagamento está disponível
            if (!empty($id_pagamento)) {
                // Adicione o botão personalizado com o link de consulta do pagamento
                $actions['custom_payment_check'] = array(
                    'url'    => 'https://valpagtesouro.tesouro.gov.br/api/gru/pagamentos/' . $id_pagamento,
                    'name'   => __('Verificar Pagamento', 'text-domain'),
                    'action' => "custom_payment_check",
                );
            }
        }

        return $actions;
    }

    // Lide com o clique no botão "Verificar Pagamento"
    add_action('woocommerce_order_action_custom_payment_check', 'process_custom_payment_check');
    function process_custom_payment_check($order)
    {
        $order_id = $order->get_id();
        $id_pagamento = get_post_meta($order_id, '_id_pagamento', true);
        $access_token = $this->client_token;

        // Faça a consulta de pagamento ao PagTesouro
        $payment_data = get_pagamento_data($id_pagamento, $access_token);

        // Verifique a situação do pagamento retornado pela API do PagTesouro
        $situacao = $payment_data['situacao']['codigo'];
        switch ($situacao) {
            case 'CRIADO':
            case 'INICIADO':
            case 'SUBMETIDO':
                // Atualize o status do pedido e estoque
                $order->update_status('on-hold', __('Aguardando confirmação de pagamento.', 'text-domain'));
                wc_maybe_reduce_stock_levels($order_id);
                break;
            case 'CONCLUIDO':
                // Atualize o status do pedido e estoque
                $order->payment_complete();
                wc_maybe_reduce_stock_levels($order_id);
                break;
            case 'REJEITADO':
            case 'CANCELADO':
                // Atualize o status do pedido e estoque
                $order->update_status('failed', __('Pagamento rejeitado ou cancelado.', 'text-domain'));
                wc_maybe_increase_stock_levels($order_id);
                break;
            default:
                // Lógica para outras situações ou tratamento de erros
                break;
        }
    }

}
