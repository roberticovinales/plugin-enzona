<?php

/* Enzona Payment Gateway Class */
class ENZONA_GATEWAY_PLUGIN extends WC_Payment_Gateway
{

    // Setup our Gateway's id, description and other values
    public function __construct()
    {

        $plugin_dir = plugin_dir_url(__FILE__);
         
        // The global ID for this Payment method
        $this->id = "enzona_gateway";

        // The Title shown on the top of the Payment Gateways Page next to all the other Payment Gateways
        $this->method_title = __("Pago Enzona", 'enzona_gateway');

        // The description for this Payment Gateway, shown on the actual Payment options page on the backend
        $this->method_description = __("Pago Enzona Plug-in para WooCommerce", 'enzona_gateway');

        // The title to be used for the vertical tabs that can be ordered top to bottom
        $this->title = __("Pago Enzona", 'enzona_gateway');

        // If you want to show an image next to the gateway's name on the frontend, enter a URL to an image.
        $this->icon = apply_filters('woocommerce_gateway_icon', '' . $plugin_dir . '/assets/enzona.png');

        // Bool. Can be set to true if you want payment fields to show on the checkout
        // if doing a direct integration, which we are doing in this case
        $this->has_fields = false;

        // This basically defines your settings which are then loaded with init_settings()
        $this->init_form_fields();

        // After init_settings() is called, you can get the settings and load them into variables, e.g:
        $this->init_settings();

        // Turn these settings into variables we can use
        foreach ($this->settings as $setting_key => $value) {
            $this->$setting_key = $value;
        }

        // Lets check for SSL
       // add_action('admin_notices', array($this, 'do_ssl_check'));
        add_action('check_enzona', array($this, 'check_response'));
        // Save settings
        if (is_admin()) {

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        }
    } // End __construct()

    // Build the administration fields for this specific Gateway
    public function init_form_fields()
    {

        $this->form_fields = array(
            'separador_general'                 => array(
                'title' => '<hr width="700px" align="center" style="border-color: #a0ad99">',
                'type'  => 'hidden',
            ), //  ----- AESTHETIC SEPARATOR
            'h2_general'                        => array(
                'title' => '<h2 style="margin-top: -10px; margin-left: 20px">Ajustes Generales</h2>',
                'type'  => 'hidden',
            ), //  ------- SECTION HEADING
            'enabled'                           => array(
                'title'   => __('Habilitar / Desabilitar', 'enzona_gateway'),
                'label'   => __('Habilitar o desabilitar esta pasarela', 'enzona_gateway'),
                'type'    => 'checkbox',
                'default' => 'no',
            ),
            'title'                             => array(
                'title'    => __('Nombre', 'enzona_gateway'),
                'type'     => 'text',
                'desc_tip' => __('Nombre de la pasarela que el usuario verá durante el proceso de checkout.', 'enzona_gateway'),
                'default'  => __('Enzona', 'enzona_gateway'),
            ),
            'description'                       => array(
                'title'    => __('Descripción', 'enzona_gateway'),
                'type'     => 'textarea',
                'desc_tip' => __('Descripción de la pasarela que el usuario verá durante el proceso de checkout.', 'enzona_gateway'),
                'default'  => __('Pago seguro con tarjetas RED cubanas, mediante Enzona', 'enzona_gateway'),
                'css'      => 'max-width:350px;',
            ),

            'order_status_after_payment'        => array(
                'title'   => __('Estado de las órdenes al concluir un pago satisfactorio', 'enzona_gateway'),
                'type'    => 'select',
                'options' => wc_get_order_statuses(),
            ),
            'order_status_after_failed_payment' => array(
                'title'   => __('Estado de las órdenes al concluir un pago fallido o cancelado', 'enzona_gateway'),
                'type'    => 'select',
                'options' => wc_get_order_statuses(),
            ),
            'return_url'                        => array(
                'title'    => __('Url personalizada para redirigir al usuario después del proceso de checkout', 'enzona_gateway'),
                'type'     => 'text',
                'desc_tip' => __('Url personalizada para redirigir al usuario después del proceso de checkout', 'enzona_gateway'),
            ),
            'cancel_url'                        => array(
                'title'    => __('Url personalizada para cancelar un pago', 'enzona_gateway'),
                'type'     => 'text',
                'desc_tip' => __('Url personalizada para cancelar un pago', 'enzona_gateway'),
            ),
            'separador_pago_real'               => array(
                'title' => '<hr width="700px" align="center" style="border-color: #a0ad99">',
                'type'  => 'hidden',
            ),
            'h2_pago'                           => array(
                'title' => '<h2 style="margin-top: -10px; margin-left: 20px">Ajustes de Pago</h2>',
                'type'  => 'hidden',
            ), //  ------- SECTION HEADING OF PAYMENT
            'consumer_key'                      => array(
                'title'    => __('Enzona API Consumer Key', 'enzona_gateway'),
                'type'     => 'password',
                'desc_tip' => __('Campo consumer_key ofrecido por la consola de administración de la API. Se usará para la generación del token de acceso.', 'enzona_gateway'),
            ),
            'consumer_secret'                   => array(
                'title'    => __('Enzona API Consumer Secret', 'enzona_gateway'),
                'type'     => 'password',
                'desc_tip' => __('Campo consumer_secret ofrecido por la consola de administración de la API. Se usará para la generación del token de acceso.', 'enzona_gateway'),
            ),
        
        );

    }

    // Submit payment and handle response
    public function process_payment($order_id)
    {

        global $woocommerce;     

//Obtener el estado de la orden de los ajustes
        $order_status        = str_replace('wc-', '', $this->order_status_after_payment);
        $order_status_failed = str_replace('wc-', '', $this->order_status_after_failed_payment);

// Get this Order's information so that we know
        // who to charge and how much
        $customer_order = new WC_Order($order_id);
        $baseUrl        = $this->get_return_url($customer_order);
        // Decide which URL to post to
        $environment_url = 'https://api.enzona.net/payment/v1.0.0';
//Order Items
        $order_items     = $customer_order->get_items();
        $product_details = array();
        foreach ($order_items as $key => $product) {
            $product_details[] = [
                'name'        => 'Producto ' . $this->clean($product['name']) . " - Orden No. $order_id",
                'description' => 'Comprado mediante el plugin de Enzona para WooCommerce',
                'quantity'    => $product['qty'],
                'price'       => number_format((float) ($product['subtotal'] / $product['qty']), 2, '.', ''),
                'tax'         => number_format((float) $product['total_tax'], 2, '.', ''),
            ];

        }
// This is where the fun stuff begins
        $baseu   = base64_encode($baseUrl);
        $Url     = 'https://dipepremium.cu/enzona/return_enzona.php';
        $payload = array(
            // Orden
            'description'         => "Orden No. $order_id", //por determinar
            'currency'            => 'CUP',
            'amount'              => array(
                'total'   => number_format((float) $customer_order->order_total, 2, '.', ''),
                'details' => array(
                    'shipping' => number_format((float) $customer_order->shipping_total, 2, '.', ''),
                    'tax'      => number_format((float) $customer_order->total_tax, 2, '.', ''),
                    'discount' => number_format((float) $customer_order->discount_total, 2, '.', ''),
                    'tip'      => number_format((float) 0, 2, '.', ''), //Propina
                ),
            ),
            'items'               => $product_details,
            'merchant_op_id'      => 123456789123, //por determinar
            'invoice_number'      => $order_id, //str_replace( "#", "", $customer_order->get_order_number() ),
            'return_url'          => "$Url?baseurl=$baseu&action=confirm&order_id=$order_id", //por determinar
            'cancel_url'          => "$Url?baseurl=$baseu&action=cancel&order_id=$order_id", //por determinar
            'terminal_id'         => 12121, //por determinar
            'buyer_identity_code' => '', //por determinar
        );

        $payloadjson = $this->formatJSON($payload, $product_details);
        if (function_exists('is_checkout_page') && is_checkout_page()) {

        }
        $urlRetorno    = $payload['return_url'];
        $url_vps_token = 'https://dipepremium.cu/enzona/token_enzona.php';
        $access_token  = json_decode($this->requestAccessToken($url_vps_token, $urlRetorno), true);
        if (array_key_exists('error', $access_token)) {
            if (array_key_exists('message', $access_token)) {
                throw new Exception(__($access_token['message'], 'enzona_gateway'));exit;
            }
            throw new Exception(__($access_token['error_description'], 'enzona_gateway'));exit;
        }
        $access_token        = $access_token['access_token'];
        $response_vps        = $this->postToApi("$environment_url/payments", $access_token, $payloadjson);
        $response_vps_decode = json_decode($response_vps, true);
        // Parse the response into something we can read
        $api_response = json_decode($response_vps_decode['enzona_response'], true);
        if (array_key_exists('error', $api_response)) {
            throw new Exception(__($api_response['message'], 'enzona_gateway'));
        } else {
            $error_ocurred         = array_key_exists('fault', $api_response);
            $api_error_code        = array_key_exists('fault', $api_response) ? $api_response['fault']['code'] : $api_response['success']['code'];
            $api_error_message     = array_key_exists('fault', $api_response) ? $api_response['fault']['message'] : $api_response['success']['message'];
            $api_error_description = array_key_exists('fault', $api_response) ? $api_response['fault']['description'] : $api_response['success']['description'];

            //Si todo va bien
            if (!$error_ocurred) {
                // Get the values we need
                $tx_uuid = $api_response['transaction_uuid'];
                //Se almacena el uuid de la tx
                update_post_meta($order_id, 'transaction_uuid', $tx_uuid);
                $url_redirect = base64_encode($api_response['links'][0]['href']);
                $redirect_url = 'https://dipepremium.cu/enzona/redirect_enzona.php/?url=' . $url_redirect;
                return array(
                    'result'   => 'success',
                    'redirect' => $redirect_url,
                );
            } else {

                // Transaction was not succesful
                wc_add_notice($api_error_description . " " . $api_error_message, 'error');
                // Add note to the order for your reference
                $customer_order->add_order_note('Error: ' . $api_error_description . " " . $api_error_message);

            }
        }

    }

    // Validate fields
    public function validate_fields()
    {
        return true;
    }

    /**
     * Captura de regreso luego de la redireccion de la pasarela para verificar el pago
     */
    public function check_response()
    {
        global $woocommerce;
          //Obtener el estado de la orden de los ajustes
        $order_status        = str_replace('wc-', '', $this->order_status_after_payment);
        $order_status_failed = str_replace('wc-', '', $this->order_status_after_failed_payment);
        // Are we testing right now or is it a real transaction
        $environment      = ($this->environment == "yes") ? 'TRUE' : 'FALSE';
        $action           = $_GET['action'];
        $order_id         = $_GET['order_id'];
        $transaction_uuid = isset($_GET['transaction_uuid']) ? $_GET['transaction_uuid'] : null;

        $order = new WC_Order($order_id);

        if ($order->has_status('completed') || $order->has_status('processing')) {
            return;
        }
        if ($transaction_uuid == null) {
            $order->add_order_note('El ID de la transacción no se pudo obtener de la respuesta de la API.');
            return;
        }
        $url_vps_token = 'https://dipepremium.cu/enzona/respuesta_enzona.php';
        $access_token  = json_decode($this->retornaAccessToken($url_vps_token), true);
        //    $access_token=json_decode($this->requestAccessToken($),true);

        if ($action == 'confirm') {
            $paymentId = $_GET['paymentId'];
            //debug
            if ('TRUE' == $simulation) {
                $order->update_status($order_status, "Transaction $order_status");
                if ($order_status == 'completed') {
                    $order->payment_complete();
                }

                // Add order note
                $order->add_order_note(sprintf(__('%s payment approved in simulation mode! Order ID: %s', 'enzona_gateway'), $this->title, $order_id));
                // Remove cart
                $woocommerce->cart->empty_cart();return;
            }

            //Ejecutar el pago
            if (array_key_exists('error', $access_token)) {
                $order->update_status($order_status_failed, 'Transaction failed in token request: ' . $access_token['error_description']);
                //wc_add_notice( $api_error_description, 'error' );
                $order->add_order_note('Transaction failed in token request: ' . $access_token['error_description']);
            }

            $access_token = $access_token['access_token'];

            //Se completa el pago
            $url = "https://api.enzona.net/payment/v1.0.0/payments/$transaction_uuid/complete";
            
            $response = $this->postToApi($url, $access_token, '');
            // Parse the response into something we can read
            $api_response = json_decode($response, true);
            //print_r($api_response['fault']['message']);exit;
            $api_response          = json_decode($api_response['enzona_response'], true);
            $error_ocurred         = array_key_exists('fault', $api_response);
            $api_error_code        = array_key_exists('fault', $api_response) ? $api_response['fault']['code'] : $api_response['success']['code'];
            $api_error_message     = array_key_exists('fault', $api_response) ? $api_response['fault']['message'] : $api_response['success']['message'];
            $api_error_description = array_key_exists('fault', $api_response) ? $api_response['fault']['description'] : $api_response['success']['description'];
            $message_to_display    = empty($api_error_message) ? $api_error_description : "$api_error_code $api_error_message Transacción: $transaction_uuid";

            //Si todo va bien
            if (!$error_ocurred) {
                $order->update_status($order_status, 'Transaction success');

                // Payment complete
                if ($order_status == 'completed') {
                    $order->payment_complete();
                }

                // Add order note
                $order->add_order_note("Pago confirmado correctamente. ID de transacción $transaction_uuid");

                // Remove cart
                $woocommerce->cart->empty_cart();

                //enviar mail confirmación
                WC()->mailer()->get_emails()['WC_Email_New_Order']->trigger($order_id);
                WC()->mailer()->get_emails()['WC_Email_Customer_Processing_Order']->trigger($order_id);

            }

        }

        //Cancelacion del pedido
        if ($action == 'cancel') {
            $response = $access_token != '' ? $this->postToApi("https://api.enzona.net/payment/v1.0.0/payments/$transaction_uuid/cancel", $access_token, '') : null;
            $order->add_order_note("Solicitud de cancelación de orden. Id de transacción $transaction_uuid");
            //$json      = $response ? json_decode($response, true) : false;
            $json =  json_decode(json_encode($response),true);
            $cancelled = $json && array_key_exists('transaction_uuid', $json) ? true : false;
            if ($cancelled) {
                $order->add_order_note("La orden se canceló manualmente desde EnZona.  Id de transacción $transaction_uuid");
                $order->update_status($order_status_failed, sprintf(__('%s el pago se canceló manualmente desde EnZona! Order ID: %d', 'enzona_gateway'), $this->title, $order_id));

            } else {
                $order->update_status($order_status_failed, sprintf(__('%s el pago se canceló en la tienda correctamente.! Order ID: %d', 'enzona_gateway'), $this->title, $order_id));
            }
            add_filter('the_title', 'woo_title_order_received', 10, 2);
            //Orden cancelada correctamente
            function woo_title_order_received($title, $id)
            {
                if (function_exists('is_order_received_page') &&
                    is_order_received_page() && get_the_ID() === $id) {
                    $title = "Su pedido ha sido cancelado";
                }
                return $title;
            }
        }
        return;
    }

    //Formatear el payload para hacerlo compatible con la API de Enzona
    public function formatJSON($payload, $items)
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES); //Formatear JSON sin caracteres de escape
        $json = str_replace('"total":"' . $payload['amount']['total'] . '"', '"total":' . $payload['amount']['total'], $json);
        $json = str_replace('"shipping":"' . $payload['amount']['details']['shipping'] . '"', '"shipping":' . $payload['amount']['details']['shipping'], $json);
        $json = str_replace('"tax":"' . $payload['amount']['details']['tax'] . '"', '"tax":' . $payload['amount']['details']['tax'], $json);
        $json = str_replace('"discount":"' . $payload['amount']['details']['discount'] . '"', '"discount":' . $payload['amount']['details']['discount'], $json);
        $json = str_replace('"tip":"' . $payload['amount']['details']['tip'] . '"', '"tip":' . $payload['amount']['details']['tip'], $json);

        foreach ($items as $item) {

            $json = str_replace('"price":"' . $item['price'] . '"', '"price":' . $item['price'], $json);
            $json = str_replace('"tax":"' . $item['tax'] . '"', '"tax":' . $item['tax'] . '', $json);
        }
        return $json;
    }

    /**
     * Funcion para limpiar una cadena de caracteres especiales que Enzona no soporta en sus entradas.
     * @param string $string La cadena a limpiar.
     * @return string Una cadena sin caracteres especiales
     */
    public function clean($string)
    {
        $string = iconv('UTF-8', 'ASCII//TRANSLIT', $string); //Eliminar las tildes que producen error en preg_replace
        return preg_replace('/[^A-Za-z0-9\-\ñ\Ñ\ ]/', '', $string); // Removes special chars.
    }

    public function requestAccessToken($url, $urlRetorno)
    {

        $data_plugin =
            [
            'consumer'   => $this->consumer_key,
            'secret'     => $this->consumer_secret,
            'licencia'   => $this->licencia,
            'urlRetorno' => $urlRetorno,

        ];
        $data_encode = json_encode($data_plugin);
        $sesion      = curl_init($url);
        curl_setopt($sesion, CURLOPT_HEADER, false);
        curl_setopt($sesion, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($sesion, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($sesion, CURLOPT_POST, true);
        curl_setopt($sesion, CURLOPT_POSTFIELDS, $data_encode);
        $response = curl_exec($sesion);
        curl_close($sesion);

        return $response;
    }

    public function retornaAccessToken($url)
    {

        $data_plugin =
            [
            'consumer' => $this->consumer_key,
            'secret'   => $this->consumer_secret,

        ];
        $data_encode = json_encode($data_plugin);
        $sesion      = curl_init($url);
        curl_setopt($sesion, CURLOPT_HEADER, false);
        curl_setopt($sesion, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($sesion, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($sesion, CURLOPT_POST, true);
        curl_setopt($sesion, CURLOPT_POSTFIELDS, $data_encode);
        $response = curl_exec($sesion);
        curl_close($sesion);

        return $response;
    }
    public function postToApi($url, $token, $data)
    {
        
        $url_vps = 'https://dipepremium.cu/enzona/pago_enzona.php';

        $data_plugin =
            [
            'url'   => $url,
            'token' => $token,
            'data'  => $data,
        ];

        $data_encode = json_encode($data_plugin);

        $sesion = curl_init($url_vps);
        curl_setopt($sesion, CURLOPT_HEADER, false);
        curl_setopt($sesion, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($sesion, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($sesion, CURLOPT_POST, true);
        curl_setopt($sesion, CURLOPT_POSTFIELDS, $data_encode);

        $response = curl_exec($sesion);

        $httpcode = curl_getinfo($sesion, CURLINFO_HTTP_CODE);
        $curerror = curl_error($sesion);
        curl_close($sesion);

        if ($response !== "") {
            return $response;
        } else {
            return array(
                'httpcode' => $httpcode,
                'error'    => $curerror,
            );
        }
    }

}
