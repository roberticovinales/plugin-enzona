<?php

/*
Plugin Name: Plugin-Enzona
Plugin URI: http://dipe.cu/
Description: Plugin que le permite vender productos en su tienda woocommerce usando la pasarela de pago enzona
Version: 2.0.0
Author: dipe
Author URI: https://dipe.cu/
 */

// Include our Gateway Class and register Payment Gateway with WooCommerce
add_action('plugins_loaded', 'enzona_gateway_init', 0);
function enzona_gateway_init()
{
    // If the parent WC_Payment_Gateway class doesn't exist
    // it means WooCommerce is not installed on the site
    // so do nothing
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    // If we made it this far, then include our Gateway Class
    include_once 'woocommerce-enzona-gateway.php';

    // Now that we have successfully included our class,
    // Lets add it too WooCommerce
    add_filter('woocommerce_payment_gateways', 'add_enzona_gateway');
    function add_enzona_gateway($methods)
    {
        $methods[] = 'ENZONA_GATEWAY_PLUGIN';
        return $methods;
    }
}

// Add custom action links
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'enzona_gateway_action_links');
function enzona_gateway_action_links($links)
{
    $plugin_links = array(
        '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout') . '">' . __('Settings', 'enzona_gateway') . '</a>',
    );

    // Merge our new link with the default ones
    return array_merge($plugin_links, $links);
}
$uri = $_SERVER['REQUEST_URI'];

add_action('init', 'check_for_payment');
function check_for_payment()
{
    if (isset($_GET['action'])) {
        // Start the gateways
        WC()->payment_gateways();
        do_action('check_enzona');
    }
}

// Registrar menu del plugin
add_action('admin_menu', 'register_menu');

function register_menu()
{
    $plugin_dir = plugin_dir_url(__FILE__);
    $icon       = apply_filters('woocommerce_gateway_icon', '' . $plugin_dir . '/assets/enzona_small.png');

    add_menu_page(
        'Plugin de pagos con Enzona',
        'Pedidos',
        'manage_options',
        'enzona_gateway',
        'display_orders',
        $icon,
        6
    );
}

function display_orders($args)
{
    global $woocommerce;
    $orders = wc_get_orders($args);
    foreach ($orders as $order) {
        $payment_method = wc_get_payment_gateway_by_order($order);
      
    }

    if (!class_exists('WP_List_Table')) {
        require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
    }
    include_once 'Orders_List.php';

    $orders_obj = new Orders_List();
   
   ?>
    <div class="wrap">
           <h2>Tabla de pedidos realizados con Enzona</h2>
        <p>Solo los pedidos marcados como completados se pueden reembolsar.</p>         
        <div><h3>Nos encantaria que opinara sobre el plugin enzona en <a href="https://es.trustpilot.com/review/dipe.cu" target="https://es.trustpilot.com/review/dipe.cu">
            <img  src="<?php echo plugin_dir_url(__FILE__);?>/img/TrustPilot.svg" width="100" >
        </a> </h3></div>
        <div id="poststuff">
                <div id="post-body-content">
                    <div class="meta-box-sortables ui-sortable">
                        <form method="post">
                        <input type="hidden" name="page" value="<?=esc_attr($_REQUEST['paged'])?>"/>
                            <?php
$orders_obj->prepare_items();
        $orders_obj->display();?>
                        </form>
                    </div>
                </div>
            <br class="clear">
        </div>
    </div>
    
<?php

}
require 'plugin-updates/plugin-update-checker.php';
$ExampleUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
    'http://dipepremium.cu/updates/info.json',
    __FILE__
);
