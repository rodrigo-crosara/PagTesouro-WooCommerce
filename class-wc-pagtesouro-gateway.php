<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class WC_PagTesouro_Gateway extends WC_Payment_Gateway
{

    public function __construct()
    {
        // Define o ID do gateway de pagamento
        $this->id = 'pagtesouro';
        // Define o título do gateway de pagamento
        $this->method_title = 'PagTesouro';
        // Define a descrição do gateway de pagamento
        $this->method_description = 'Permite pagamentos pelo PagTesouro';
        // Indica que o gateway tem campos personalizados
        $this->has_fields = true;

        // Carrega as configurações do gateway de pagamento
        $this->init_form_fields();
        $this->init_settings();

        // Defina as propriedades do gateway com base nas configurações
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->client_token = $this->get_option('client_token');
        $this->url_retorno = $this->get_option('url_retorno');
        $this->url_notificacao = $this->get_option('url_notificacao');

        // Adiciona uma ação para salvar as configurações quando o formulário for enviado
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    }

    public function init_form_fields()
    {
        // Define os campos do formulário de configurações do gateway de pagamento
        $this->form_fields = array(
            // Campo para ativar ou desativar o gateway de pagamento
            'enabled' => array(
                'title' => 'Habilitar/Desabilitar',
                'type' => 'checkbox',
                'label' => 'Habilitar PagTesouro',
                'default' => 'yes'
            ),
            // Campo para definir o título do gateway de pagamento
            'title' => array(
                'title' => 'Título',
                'type' => 'text',
                'description' => 'Título do gateway de pagamento exibido durante o checkout.',
                'default' => 'PagTesouro',
                'desc_tip' => true,
            ),
            // Campo para definir a descrição do gateway de pagamento
            'description' => array(
                'title' => 'Descrição',
                'type' => 'textarea',
                'description' => 'Descrição do gateway de pagamento exibida durante o checkout.',
                'default' => '',
                'desc_tip' => true,
            ),
            // Campo para definir o token de acesso do PagTesouro
            'client_token' => array(
                'title' => 'Token de Acesso',
                'type' => 'text',
                'description' => 'Token de acesso fornecido pelo PagTesouro é um parâmetro obrigatório.',
                'default' => '',
                'desc_tip' => true,
            ),
            // Campo para definir o codigoServico
            'codigo_servico' => array(
                'title' => 'Código do Serviço',
                'type' => 'text',
                'description' => 'Deve existir no cadastro de serviços da UG e não estar excluído é parâmetro obrigatório.',
                'default' => '',
                'desc_tip' => true,
            ),
            // Campo para definir o urlRetorno
            'url_retorno' => array(
                'title' => 'URL Retorno',
                'type' => 'text',
                'description' => 'URL do sistema cliente para onde o usuário será redirecionado ao selecionar a opção Concluir na tela de confirmação de pagamento do PagTesouro. Esta URL é obrigatória apenas quando for utilizado o parâmetro "modoNavegacao": "1". Exemplo: https://valpagtesouro.tesouro.gov.br/simulador',
                'default' => '',
                'desc_tip' => true,
            ),
            // Campo para definir o urlNotificacao
            'url_notificacao' => array(
                'title' => 'URL Notificação',
                'type' => 'text',
                'description' => 'URL do serviço opcionalmente implementado pelo sistema cliente para recebimento de notificações de pagamentos enviadas pelo PagTesouro. O domínio desta URL deverá estar em um dos padrões oficiais: .gov.br, .def.br, .jus.br, .leg.br, .mp.br, .tc.br, .mil.br ou .eb.br, .edu.br ou .br',
                'default' => '',
                'desc_tip' => true,
            ),
        );
    }

    public function payment_fields()
    {
        // Exibe a descrição do gateway de pagamento
        if ($this->description) {
            echo wpautop(wp_kses_post($this->description));
        }
        
        // Exibe o botão de pagamento
        ?>
        <p>
            <button type="submit" class="button-alt" name="woocommerce_checkout_place_order" id="place_order" value="Pagar com PagTesouro">Pagar com PagTesouro</button>
        </p>
        <?php
    }

    public function process_payment($order_id)
    {

        global $woocommerce;
        $order = new WC_Order($order_id);

        // Obtenha o valor do campo CPF adicionado pelo plugin 'Brazilian Market on WooCommerce'
        $cpf = get_post_meta($order_id, '_billing_cpf', true);

        // Obtenha o valor do campo de forma de pagamento
        $forma_pagamento = sanitize_text_field($_POST['pagtesouro_forma_pagamento']);

        // Obtenha um token de acesso
        $access_token = $this->client_token;

        // Obtém a data do pedido
        $order_date = $order->get_date_created();

        // Formata a data do pedido como competência
        $competencia = $order_date->format('mY');

        // Adiciona 7 dias à data do pedido
        $vencimento = $order_date->add(new DateInterval('P7D'));

        // Formata a data de vencimento como DDMMAAAA
        $vencimento = $vencimento->format('dmY');

        // Crie uma solicitação de pagamento
        $data = array(
            "codigoServico" => $this->settings['codigo_servico'],
            "referencia" => $order_id,
            "competencia" => $competencia,
            "vencimento" => $vencimento,
            "cnpjCpf" => $cpf,
            "nomeContribuinte" => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            "valorPrincipal" => $order->get_total(),
            "valorDescontos" => "0",
            "valorOutrasDeducoes" => "0",
            "valorMulta" => "0",
            "valorJuros" => "0",
            "valorOutrosAcrescimos" => "0",
            "modoNavegacao" => "2",
            'urlRetorno' => $this->settings['url_retorno'],
            "urlNotificacao" => $this->settings['url_notificacao']
        );
        $data_string = json_encode($data);

        // Inicia uma sessão cURL para fazer a requisição de pagamento
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://valpagtesouro.tesouro.gov.br/api/gru/solicitacao-pagamento');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Content-Type: application/json",
            "Authorization: Bearer $access_token"
        ));
        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new Exception(curl_error($ch));
        }
        curl_close($ch);

        // Verifique o resultado da solicitação de pagamento
        $result = json_decode($result);
        if (isset($result->error)) {
            // Exibe uma mensagem de erro se a solicitação de pagamento falhar
            wc_add_notice('Ocorreu um erro ao processar o pagamento: ' . $result->error_description, 'error');
            return array(
                'result' => 'fail',
                'redirect' => '',
            );
        } else {
            // Obtenha a URL de redirecionamento a partir da resposta da API
            $redirect_url = $result->proximaUrl;

            // Armazena o "idPagamento" para consultas posteriores ao status do pagamento
            update_post_meta($order_id, '_id_pagamento', $result->idPagamento);

            // Armazene a URL de redirecionamento nos metadados do pedido
            update_post_meta($order_id, '_pagtesouro_redirect_url', $redirect_url);
            // Adicione um meta dado ao pedido para armazenar o status do pagamento
            update_post_meta( $order_id, '_pagtesouro_payment_status', 'pending' );

            // Marcar o pedido como pendente e bloquear o estoque
            $order->update_status('pending', __('Aguardando pagamento via PagTesouro.', 'woocommerce'));
            wc_maybe_reduce_stock_levels( $order_id );

            // Esvazie o carrinho
            $woocommerce->cart->empty_cart();

            // Cria o HTML do iFrame para acionar a URL de redirecionamento
            $iframe_html = '<iframe src="' . esc_url($redirect_url) . '" width="100%" height="600px"></iframe>';

            // Cria o HTML do botão "Confirmar Pagamento"
            $button_html = '<button type="button" class="button-alt" id="confirm_payment">Confirmar Pagamento</button>';

            // Adiciona o script JavaScript para a consulta de pagamento
            $script_html = '<script>
                document.getElementById("confirm_payment").addEventListener("click", function() {
                    var idPagamento = "' . $result->idPagamento . '";
                    var token = "' . $access_token . '";
                    var xhr = new XMLHttpRequest();
                    xhr.open("GET", "https://valpagtesouro.tesouro.gov.br/api/gru/pagamentos/" + idPagamento);
                    xhr.setRequestHeader("Authorization", "Bearer " + token);
                    xhr.onreadystatechange = function() {
                        if (xhr.readyState === XMLHttpRequest.DONE && xhr.status === 200) {
                            var response = JSON.parse(xhr.responseText);
                            var situacao = response.situacao.codigo;

                            switch (situacao) {
                                case "CRIADO":
                                    // Atualizar o status do pedido para "pending" e liberar o estoque
                                    update_post_meta(' . $order_id . ', "_pagtesouro_payment_status", "pending");
                                    $order->update_status("pending", __("Aguardando confirmação de pagamento.", "woocommerce"));
                                    wc_maybe_increase_stock_levels(' . $order_id . ');
                                    break;

                                case "INICIADO":
                                    // Atualizar o status do pedido para "processing" e bloquear o estoque
                                    update_post_meta(' . $order_id . ', "_pagtesouro_payment_status", "processing");
                                    $order->update_status("processing", __("Pagamento em andamento.", "woocommerce"));
                                    wc_maybe_reduce_stock_levels(' . $order_id . ');
                                    break;

                                case "SUBMETIDO":
                                    // Atualizar o status do pedido para "processing" e bloquear o estoque
                                    update_post_meta(' . $order_id . ', "_pagtesouro_payment_status", "processing");
                                    $order->update_status("processing", __("Pagamento em andamento.", "woocommerce"));
                                    wc_maybe_reduce_stock_levels(' . $order_id . ');
                                    break;

                                case "CONCLUIDO":
                                    // Atualizar o status do pedido para "completed" e liberar o estoque
                                    update_post_meta(' . $order_id . ', "_pagtesouro_payment_status", "completed");
                                    $order->update_status("completed", __("Pagamento concluído.", "woocommerce"));
                                    wc_maybe_increase_stock_levels(' . $order_id . ');
                                    break;

                                case "REJEITADO":
                                    // Atualizar o status do pedido para "failed" e liberar o estoque
                                    update_post_meta(' . $order_id . ', "_pagtesouro_payment_status", "failed");
                                    $order->update_status("failed", __("Pagamento rejeitado.", "woocommerce"));
                                    wc_maybe_increase_stock_levels(' . $order_id . ');
                                    break;

                                case "CANCELADO":
                                    // Atualizar o status do pedido para "cancelled" e liberar o estoque
                                    update_post_meta(' . $order_id . ', "_pagtesouro_payment_status", "cancelled");
                                    $order->update_status("cancelled", __("Pagamento cancelado.", "woocommerce"));
                                    wc_maybe_increase_stock_levels(' . $order_id . ');
                                    break;

                                default:
                                    // Situação desconhecida, faça o tratamento adequado aqui
                                    break;
                            }
                        }
                    };
                    xhr.send();
                });
            </script>';

            // Abre o iframe de pagamento e mantém o resultado como 'pending'
            return array(
                'result' => 'pending',
                'redirect_html' => $iframe_html . $button_html . $script_html,
            );


        }
    }
}
