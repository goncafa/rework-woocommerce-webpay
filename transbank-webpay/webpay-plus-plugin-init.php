<?php
/**
 * @wordpress-plugin
 * Plugin Name: Webpay Plus
 * Plugin URI: https://www.transbankdevelopers.cl/plugin/woocommerce/webpay
 * Description: Recibe pagos en l&iacute;nea con Tarjetas de Cr&eacute;dito y Redcompra en tu WooCommerce a trav&eacute;s de Webpay Plus.
 * Version: 2.3.0
 * Author: Gonzalo Castillo | gonzalo.castillo@continuum.cl
 * Author URI: https://github.com/goncafa
 * WC requires at least: 3.4.0
 * WC tested up to: 3.5.4
 */

/**
 * Exit if accessed directly.
 */
if (!defined('ABSPATH')) {
    exit();
}

if (!defined('WEBPAY_PLUS_FOR_WOOCOMMERCE_PLUGIN_DIR')) {
    define('WEBPAY_PLUS_FOR_WOOCOMMERCE_PLUGIN_DIR', dirname(__FILE__));
}

// Make sure WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) return;

function webpay_pluss_add_to_gateways($methods) {
    foreach ($methods as $key=>$method){
        if (in_array($method, array('WC_Gateway_Webpay_Plus'))) {
            unset($methods[$key]);
            break;
        }
    }

    $methods[] = 'WC_Gateway_Webpay_Plus';
    return $methods;
}

function webpay_plus_init() {
    if (!class_exists("WC_Payment_Gateway")) return;
    include_once (WEBPAY_PLUS_FOR_WOOCOMMERCE_PLUGIN_DIR . '/vendor/autoload.php');
    include_once (WEBPAY_PLUS_FOR_WOOCOMMERCE_PLUGIN_DIR . '/classes/WC_Gateway_Webpay_Plus.php' );
    include_once (WEBPAY_PLUS_FOR_WOOCOMMERCE_PLUGIN_DIR . '/classes/LogHandler.php' );
    add_filter('woocommerce_payment_gateways','webpay_pluss_add_to_gateways', 1000);
}

/*
 * This ensures that if WooCommerce is active (which we’ve just checked for), we load our 
 * class after WooCommerce core (making this a secondary check against fatal errors). 
 * This ensures that, not only is WooCommerce active, but we’re loading after it so the 
 * WC_Payment_Gateway class is available.
 */
add_action('plugins_loaded', 'webpay_plus_init', 11);

function add_action_links ($links) {
    $newLinks = array(
        '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=webpay_plus' ) . '">Settings</a>',
    );
    return array_merge( $links, $newLinks );
}

add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'add_action_links');