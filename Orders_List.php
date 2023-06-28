<?php
include_once 'woocommerce-enzona-gateway.php';
class Orders_List extends WP_List_Table
{
    private $enzona;

    /** Class constructor */
    public function __construct()
    {

        $this->enzona = new ENZONA_GATEWAY_PLUGIN(); //Acceso al plugin

        parent::__construct([
            'singular' => __('Order', 'sp'), //singular name of the listed records
            'plural'   => __('Orders', 'sp'), //plural name of the listed records
            'ajax'     => false, //should this table support ajax?

        ]);

    }
    /**
     * Retrieve orders data from the database
     *
     * @param int $per_page
     * @param int $page_number
     *
     * @return mixed
     */
    public static function get_orders($per_page = 5, $page_number = 1)
    {

        $query = new WC_Order_Query(array(
            'limit'          => $per_page,
            'payment_method' => 'enzona_gateway',
            'orderby'        => 'date_created',
            'order'          => !empty($_REQUEST['orderby']) ? $_REQUEST['orderby'] : "DESC",
            'offset'         => ($page_number - 1) * $per_page,
            'return'         => 'objects',
        ));
        $orders = $query->get_orders();

        $ords = [];
        foreach ($orders as $order) {
            //print_r($order);exit;
            $date_created  = $order->get_date_created()->format('m-d-Y h:i:s a');
            $date_modified = $order->get_date_modified()->format('m-d-Y h:i:s a');
            $status        = $order->get_status();
            switch ($status) {
                case 'on-hold':
                    $sttxt = 'En espera';
                    break;
                case 'completed':
                    $sttxt = 'Completado';
                    break;
                case 'cancelled':
                    $sttxt = 'Cancelado';
                    break;
                case 'pending':
                    $sttxt = 'Pendiente de pago';
                    break;
                case 'failed':
                    $sttxt = 'Fallido';
                    break;
                case 'refunded':
                    $sttxt = 'Reembolsado';
                    break;
            }
            $st = sprintf(
                '<span class="order-status status-%s tips" >%s</span>', $status, $sttxt
            );
            $orderid = $order->get_id();
            //print_r($order);exit;
            $id = sprintf(
                "<a href=\"post.php?post=%s&action=edit\">%d </a>", $orderid, $orderid
            );
            $total      = $order->get_total();
            $total_html = $status != 'refunded' ? "$$total" : "<del>$$total</del>";
            $ords[]     = [
                'id'             => $id,
                'orderid'        => $orderid,
                'transaction_id' => get_post_meta($orderid, 'transaction_uuid', true),
                'user'           => $order->get_user()->user_login,
                'payment_method' => $order->get_payment_method(),
                'total'          => $total,
                'total_html'     => $total_html,
                'currency'       => $order->get_currency(),
                'status'         => $st,
                'date_created'   => $date_created,
                'date_modified'  => $date_modified,
            ];
        }
        //print_r($ords);exit;

        return $ords;
    }
    /**
     * Delete a customer record.
     *
     * @param int $id customer ID
     */
    public static function delete_customer($id)
    {
        global $wpdb;

        $wpdb->delete(
            "{$wpdb->prefix}customers",
            ['ID' => $id],
            ['%d']
        );
    }
    /**
     * Returns the count of records in the database.
     *
     * @return null|string
     */
    public static function record_count()
    {
        $args = array(
            'status' => 'any', 
            'limit' => -1,
            'payment_method' => 'enzona_gateway', 
        );
        $query  = new WC_Order_Query($args);
        $orders = $query->get_orders();
      return count($orders);
    }
    /** Text displayed when no customer data is available */
    public function no_items()
    {
        _e('No orders avaliable.', 'sp');
    }

    /**
     * Method for name column
     *
     * @param array $item an array of DB data
     *
     * @return string
     */
    public function column_name($item)
    {

        //print_r($item);exit;
        // create a nonce
        $delete_nonce = wp_create_nonce('sp_delete_customer');

        $title = '<strong>' . $item['id'] . '</strong>';

        $actions = [
            'delete' => sprintf('<a href="?page=%s&action=%s&customer=%s&_wpnonce=%s">Delete</a>', esc_attr($_REQUEST['page']), 'delete', absint($item['ID']), $delete_nonce),
        ];

        return $title . $this->row_actions($actions);
    }

    /**
     * Render a column when no column specific method exists.
     *
     * @param array $item
     * @param string $column_name
     *
     * @return mixed
     */
    public function column_default($item, $column_name)
    {
        //return $item[ $column_name ];
        switch ($column_name) {
            case 'id':
            case 'user':
            case 'payment_method':
            case 'transaction_id':
            case 'date_created':
            case 'date_modified':
            case 'currency':
            case 'total':
            case 'total_html':
            case 'status':
                return $item[$column_name];
            default:
                return print_r($item, true); //Show the whole array for troubleshooting purposes
        }
    }
    /**
     * Render the bulk edit checkbox
     *
     * @param array $item
     *
     * @return string
     */
    public function column_cb($item)
    {

        return sprintf(
            '<input type="checkbox" name="items[]" value="%s" />', $item['orderid']
        );
    }
    /**
     *  Associative array of columns
     *
     * @return array
     */
    public function get_columns()
    {
        $columns = [
            'cb'             => '<input type="checkbox" />',
            //'payment_method'    => __( 'Metodo', 'sp' ),
            'id'             => __('Orden No.', 'sp'),
            'user'           => __('Cliente', 'sp'),
            'date_created'   => __('Fecha de creación', 'sp'),
            'date_modified'  => __('Fecha de modificación', 'sp'),
            'transaction_id' => __('Número de transacción', 'sp'),
            'status'         => __('Estado', 'sp'),
            'total_html'     => __('Monto', 'sp'),
            'currency'       => __('Moneda', 'sp'),
        ];

        //print_r($columns);exit;
        return $columns;
    }
    /**
     * Columns to make sortable.
     *
     * @return array
     */
    public function get_sortable_columns()
    {
        $sortable_columns = array(
            'id'             => array('id', false),
            'user'           => array('user', false),
            'status'         => array('status', false),
            'total_html'     => array('total_html', false),
            'currency'       => array('currency', false),
            'transaction_id' => array('transaction_id', false),
            'date_created'   => array('date_created', false),
            'date_modified'  => array('date_modified', false),
        );

        return $sortable_columns;
    }

    public function usort_reorder($a, $b)
    {
        // If no sort, default to title
        $orderby = (!empty($_GET['orderby'])) ? $_GET['orderby'] : '';
        // If no order, default to asc
        $order = (!empty($_GET['order'])) ? $_GET['order'] : 'asc';
        // Determine sort order
        $result = strcmp($a[$orderby], $b[$orderby]);
        // Send final sort direction to usort
        return ($order === 'asc') ? $result : -$result;
    }

    /**
     * Returns an associative array containing the bulk action
     *
     * @return array
     */
    public function get_bulk_actions()
    {
        $actions = [
            //'bulk-delete' => 'Eliminar',
            'bulk-refund' => 'Reembolsar',
        ];

        return $actions;
    }
    /**
     * Handles data query and filter, sorting, and pagination.
     */
    public function prepare_items()
    {

        $columns  = $this->get_columns();
        $sortable = $this->get_sortable_columns();
        $hidden   = [];

        $this->_column_headers = [
            $columns, $hidden, $sortable,
        ];

        //$this->_column_headers = $this->get_column_info();

        /** Process bulk action */
        $this->process_bulk_action();

        $per_page     = $this->get_items_per_page('orders_per_page', 10);
        $current_page = $this->get_pagenum();
        $total_items  = self::record_count();

        //print_r($current_page);exit;
        $search = isset($_POST['s']) && !empty($_POST['s']) ? $_POST['s'] : null;
        //print_r($search);exit;

        $items = $this->get_orders($per_page, $current_page);

        $searchresultkeys = [];
        $searchResults    = [];
        if ($search) {
            $keys = $this->searcharray($search, 'id', $items);
            print_r($keys);exit;
        }

        //$items = $search?$searchResults:$items;
        //$items = $this->found_data;
        // only ncessary because we have sample data
        //$this->found_data = array_slice($items,(($current_page-1)*$per_page),$per_page);
        $this->set_pagination_args([
            'total_items' => $total_items, //WE have to calculate the total number of items
            'per_page'    => $per_page, //WE have to determine how many items to show on a page
        ]);

        usort($items, array(&$this, 'usort_reorder'));
        $this->items = $items;
    }

    public function searcharray($value, $key, $array)
    {
        $return = [];
        foreach ($array as $k => $val) {
            foreach ($val as $v) {
                if ($v == $value) {
                    $return[] = $k;
                }
            }

        }
        return $return;
    }
    public function process_bulk_action()
    {

        //Detect when a bulk action is being triggered...
        $action = $this->current_action();
        switch ($action) {
            case 'bulk-refund': //Reembolsar

                $refund_ids = esc_sql($_POST['items']); //Ordenes a reembolsar
                //print_r(count($refund_ids));exit;
                if (!$refund_ids) {
                    echo
                    '<div class="error">
          <p>' . __("Error! Debe seleccionar al menos un pedido para ejecutar esta acción") . '</p>
        </div>';
                    break;
                }
                foreach ($refund_ids as $orderid) {
                    //print_r($orderid);exit;
                    $order = new WC_Order($orderid);
                    if ($order) {
                        //Datos de la orden
                        $transaction_uuid = get_post_meta($orderid, 'transaction_uuid', true);
                        $status           = $order->get_status();
                        $payload          = [
                            'amount'      => [
                                'total' => number_format($order->get_total(), 2),
                            ],
                            'description' => 'Devolución manual ejecutada por un administrador de la tienda.', //Motivo del reembolso, esto se debe permitir modificar por el usuario
                        ];

                        //Ejecutar el reembolso solo si la orden se ha marcado como completada
                        if ($status !== 'completed') {

                            echo '<div class="error">
              <p>' . __("Error! El pedido No. $orderid no se pudo reembolsar porque no se ha marcado como completado. <br> Solo los pedidos marcados como completados se pueden rembolsar.") . '</p>
            </div>';
                            break;
                        }
                        //Obtener el entorno
                        $environment = ($this->enzona->environment == "yes") ? 'TRUE' : 'FALSE';
                        $simulation  = ($this->enzona->simulation == "yes") ? 'TRUE' : 'FALSE';

                        // Decide which URL to post to
                        $environment_url = ("FALSE" == $environment)
                        ? 'https://api.enzona.net/payment/v1.0.0'
                        : 'https://apisandbox.enzona.net/payment/v1.0.0';

                        $url_vps_token = 'https://dipepremium.cu/enzona/token_enzona.php'; 
                        //Request a token
                    
                        $access_token = json_decode($this->enzona->requestAccessToken($url_vps_token, $urlRetorno), true);


                        // $access_token      = json_decode($this->enzona->requestAccessToken("$environment_url_for_tokens/token"), true);
                       $access_token = $access_token['access_token'];

            //Enviar solicitud de reembolso
                        $payload = json_encode($payload, JSON_UNESCAPED_SLASHES);
                        $environment_url . '<br>';
                        $transaction_uuid . '<br>';
                        $payload . '<br>';
                        $access_token . '<br>';

                        $response = $this->enzona->postToApi("$environment_url/payments/$transaction_uuid/refund", $access_token, $payload);

                        // Parse the response into something we can read
                        $api_response = json_decode($response, true);
                      
                        $error_ocurred         = array_key_exists('fault', $api_response) ? array_key_exists('fault', $api_response) : false;
                        $error_ocurred         = array_key_exists('error', $api_response) ? $api_response['error'] : $error_ocurred;
                        $api_error_code        = array_key_exists('fault', $api_response) ? $api_response['fault']['code'] : $api_response['success']['code'];
                        $api_error_message     = array_key_exists('fault', $api_response) ? $api_response['fault']['message'] : $api_response['success']['message'];
                        $api_error_message     = array_key_exists('error', $api_response) ? $api_response['message'] : $api_error_message;
                        $api_error_description = array_key_exists('fault', $api_response) ? $api_response['fault']['description'] : $api_response['success']['description'];
                        $message_to_display    = empty($api_error_message) ? $api_error_description : "$api_error_code $api_error_message Transacción: $transaction_uuid";

                        if (array_key_exists('fault', $api_response)) {
                            $order->add_order_note('Error en la solicitud de reembolso: ' . $message_to_display);
                        }
                        //Si todo va bien
                        if (!$error_ocurred) {
                            $order->update_status('refunded', 'Refund success');
                            // Add order note
                            $order->add_order_note("Pedido reembolsado correctamente. ID de transacción $transaction_uuid");
                           
                            echo
                            '<div class="updated">
              <p>' . __("Correcto! El pedido No. $orderid se ha reembolsado correctamente") . '</p>
            </div>';
                        } else {
                            
                            echo
                            '<div class="error">
              <p>' . __("Error! El pedido No. $orderid no se pudo reembolsar debido al siguiente error: $api_error_description ") . '</p>
            </div>';
                        }
                    }
                }
                //print_r($refund_ids);exit;
                //$this->enzona->test();
                break;
        }
        if ('delete' === $this->current_action()) {

            // In our file that handles the request, verify the nonce.
            $nonce = esc_attr($_REQUEST['_wpnonce']);

            if (!wp_verify_nonce($nonce, 'sp_delete_customer')) {
                die('Go get a life script kiddies');
            } else {
                self::delete_customer(absint($_GET['order']));

                wp_redirect(esc_url(add_query_arg()));
                exit;
            }

        }

        // If the delete bulk action is triggered
        if ((isset($_POST['action']) && $_POST['action'] == 'bulk-delete')
            || (isset($_POST['action2']) && $_POST['action2'] == 'bulk-delete')
        ) {

            $delete_ids = esc_sql($_POST['bulk-delete']);

            // loop over the array of record IDs and delete them
            foreach ($delete_ids as $id) {
                self::delete_customer($id);

            }

            wp_redirect(esc_url(add_query_arg()));
            exit;
        }

    }
}
