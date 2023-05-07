<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class WC_PagTesouro_Gateway extends WC_Payment_Gateway
{

    /*A função __construct é o construtor da classe WC_PagTesouro_Gateway que estende a classe WC_Payment_Gateway. Ela é chamada quando um objeto da classe é criado.*/

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
        $this->client_id = $this->get_option('client_id');
        $this->client_secret = $this->get_option('client_secret');

        // Adiciona uma ação para salvar as configurações quando o formulário for enviado
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    }

    /*A função init_form_fields inicializa os campos do formulário de configurações do gateway de pagamento. Ela define a propriedade form_fields da classe com um array associativo contendo as opções de configuração do gateway.

    De acordo com a documentação da API do PagTesouro, é necessário obter um token de acesso para utilizar a API. O token de acesso é obtido enviando uma requisição POST para a URL https://valpagtesouro.tesouro.gov.br/auth/realms/pagtesouro/protocol/openid-connect/token com os parâmetros grant_type=client_credentials, client_id e client_secret no corpo da requisição.

    Portanto, é necessário manter os campos client_id e client_secret na função init_form_fields para que o usuário possa inserir as credenciais do cliente fornecidas pelo PagTesouro. Essas credenciais são usadas para obter o token de acesso, que é enviado no cabeçalho Authorization das requisições para a API do PagTesouro.

    A função init_form_fields pode permanecer como está, pois ela já define os campos client_id e client_secret corretamente. Os dois campos são obrigatórios para obter o token de acesso e utilizar a API do PagTesouro.*/

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
            // Campo para definir o Client ID do PagTesouro
            'client_id' => array(
                'title' => 'Client ID',
                'type' => 'text',
                'description' => 'Client ID fornecido pelo PagTesouro é parâmetro obrigatório.',
                'default' => '',
                'desc_tip' => true,
            ),
            // Campo para definir o Client Secret do PagTesouro
            'client_secret' => array(
                'title' => 'Client Secret',
                'type' => 'password',
                'description' => 'Client Secret fornecido pelo PagTesouro é parâmetro obrigatório..',
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

    /*A função payment_fields é responsável por exibir os campos personalizados do gateway de pagamento durante o checkout. Ela exibe a descrição do gateway e os campos de CPF e forma de pagamento.*/

    public function payment_fields()
    {
        // Exibe a descrição do gateway de pagamento
        if ($this->description) {
            echo wpautop(wp_kses_post($this->description));
        }
        // Exibe o campo de forma de pagamento
        ?>
        <p>
            <label for="pagtesouro_forma_pagamento">Forma de pagamento <span class="required">*</span></label>
            <select name="pagtesouro_forma_pagamento" id="pagtesouro_forma_pagamento">
                <option value="">Selecione uma opção</option>
                <option value="PIX">PIX</option>
                <option value="CARTAO_CREDITO">Cartão de crédito</option>
                <option value="BOLETO">Boleto</option>
            </select>
        </p>
        <?php
    }

    /*A função validate_fields é responsável por validar os campos personalizados do gateway de pagamento durante o checkout. Ela verifica se os campos forma de pagamento foram preenchidos e exibe uma mensagem de erro caso algum deles esteja vazio.*/

    public function validate_fields()
    {
        // Valida o campo de forma de pagamento
        if (empty($_POST['pagtesouro_forma_pagamento'])) {
            wc_add_notice('Por favor, selecione uma forma de pagamento.', 'error');
            return false;
        }
        // Retorna verdadeiro se todos os campos foram validados com sucesso
        return true;
    }

    /*A função process_payment é responsável por processar o pagamento do pedido. Ela recebe como parâmetro o ID do pedido e deve retornar um array associativo contendo o status do pagamento e a URL de redirecionamento.*/

    public function process_payment($order_id)
    {

        /*Nesta parte da função, o código obtém os valores dos campos de CPF e forma de pagamento a partir dos dados enviados pelo usuário durante o checkout. Em seguida, ele faz uma requisição para a API do PagTesouro para obter um token de acesso usando as credenciais do cliente (Client ID e Client Secret) fornecidas pelo PagTesouro. O token de acesso é armazenado na variável $access_token e será usado posteriormente para fazer outras requisições para a API do PagTesouro.*/

        global $woocommerce;
        $order = new WC_Order($order_id);

        // Obtenha o valor do campo CPF adicionado pelo plugin 'Brazilian Market on WooCommerce'
        $cpf = get_post_meta($order_id, '_billing_cpf', true);

        // Obtenha o valor do campo de forma de pagamento
        $forma_pagamento = sanitize_text_field($_POST['pagtesouro_forma_pagamento']);

        // Faça a integração com a API do PagTesouro aqui e processe o pagamento
        // Substitua os valores de $client_id e $client_secret pelos valores fornecidos pelo PagTesouro
        $client_id = $this->client_id;
        $client_secret = $this->client_secret;

        // Obtenha um token de acesso
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://valpagtesouro.tesouro.gov.br/auth/realms/pagtesouro/protocol/openid-connect/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials&client_id=$client_id&client_secret=$client_secret");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new Exception(curl_error($ch));
        }
        curl_close($ch);
        $result = json_decode($result);
        $access_token = $result->access_token;

        /*Nesta parte da função, o código cria uma solicitação de pagamento na API do PagTesouro usando os dados do pedido e do cliente. Ele envia uma requisição POST para a API com os dados da solicitação de pagamento no corpo da requisição em formato JSON. O token de acesso obtido anteriormente é enviado no cabeçalho Authorization da requisição.*/

        // Crie uma solicitação de pagamento
        $data = array(
            'valor' => $order->get_total(),
            'cpf' => $cpf,
            'codigoServico' => $this->settings['codigo_servico'],
            'nome' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'email' => $order->get_billing_email(),
            'telefone' => $order->get_billing_phone(),
            'formaPagamento' => $forma_pagamento,
            'urlRetorno' => $this->settings['url_retorno'], 
            'urlNotificacao' => $this->settings['url_notificacao'],
        );
        $data_string = json_encode($data);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://valpagtesouro.tesouro.gov.br/api/pagamento/v1/solicitacao');
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

        /*Nesta parte da função, o código verifica se a solicitação de pagamento foi criada com sucesso. Se a solicitação falhar, ele exibe uma mensagem de erro e retorna um array associativo indicando que o pagamento falhou. Caso contrário, ele marca o pedido como pago usando o método payment_complete da classe WC_Order e passando como parâmetro o ID do pagamento retornado pela API do PagTesouro. Em seguida, ele reduz o estoque dos produtos do pedido usando a função wc_reduce_stock_levels e esvazia o carrinho usando o método empty_cart da classe WC_Cart. Por fim, ele retorna um array associativo indicando que o pagamento foi bem-sucedido e contendo a URL de redirecionamento para a página de agradecimento.*/

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

            // Armazene a URL de redirecionamento nos metadados do pedido
            update_post_meta($order_id, '_pagtesouro_redirect_url', $redirect_url);
            // Adicione um meta dado ao pedido para armazenar o status do pagamento
            update_post_meta( $order_id, '_pagtesouro_payment_status', 'pending' );

            // Marcar o pedido como pendente e bloquear o estoque de forma temporária dessa forma, o estoque será bloqueado até que o pagamento seja confirmado e o status do pedido seja atualizado para 'processing' ou 'completed'.
            $order->update_status('pending', __('Aguardando pagamento via PagTesouro.', 'woocommerce'));
            wc_maybe_reduce_stock_levels( $order_id );

            // Esvazie o carrinho
            $woocommerce->cart->empty_cart();

            // Redirecione para a URL de pagamento
            return array(
                'result' => 'pending',
                'redirect' => $redirect_url,
            );

        }
    }
}
