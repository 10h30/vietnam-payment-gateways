<?php
/*
 * Plugin Name: Vietnam Payment Gateways for Woocommerce
 * Plugin URI: https://thuanbui.me
 * Description: Vietnam Payment Gateways for Woocommerce
 * Author: Thuan Bui
 * Author URI: https://thuanbui.me
 * Text Domain: vnpg
 * Domain Path: /languages
 * Version: 2.1.1
 * Tested up to: 6.0
 * License: GNU General Public License v3.0
 */
/*
* This action hook registers our PHP class as a WooCommerce payment gateway
*/
add_filter( 'woocommerce_payment_gateways', 'vnpg_add_gateway_class' );
function vnpg_add_gateway_class( $gateways ) {
	$gateways[] = 'WC_VNPG_YCB'; // your class name is here
	return $gateways;
}

/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action( 'plugins_loaded', 'vnpg_init_gateway_class' );
function vnpg_init_gateway_class() {
	class WC_VNPG_YCB extends WC_Payment_Gateway {
 		public function __construct() {

 		}

 		/**
 		* Initialise Gateway Settings Form Fields.
 		*/
 		public function init_form_fields(){

	 	}
		 /**
         * Output for the order received page.
         *
         * @param int $order_id Order ID.
         */
        public function thankyou_page( $order_id ) {
        
        }
        
        /**
         * Add content to the WC emails.
         *
         * @param WC_Order $order Order object.
         * @param bool     $sent_to_admin Sent to admin.
         * @param bool     $plain_text Email format: plain text or HTML.
         */
        public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
        
        }

    }
}