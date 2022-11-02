<?php
/*
 * Plugin Name: Vietnam Payment Gateways for Woocommerce
 * Plugin URI: https://thuanbui.me
 * Description: Vietnam Payment Gateways for Woocommerce
 * Author: Thuan Bui
 * Author URI: https://thuanbui.me
 * Text Domain: vnpg
 * Domain Path: /languages
 * Version: 1.0.0
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
            $this->id                 = 'vnpg';
            $this->icon               = apply_filters( 'woocommerce_vnpg_icon', '' );
            $this->has_fields         = false;
            $this->method_title       = __( 'Vietnam Bank Transfer (VietQR)', 'vnpg' );
            $this->method_description = __( 'Take payments by scanning QR code with Vietnamese banking App.', 'vnpg' );

            //Get bank list from VietQR API
            $this->bank_list = $this->get_vietqr_bank_list();

            // Load the settings.
		    $this->init_form_fields();
		    $this->init_settings();

            // Define user set variables.
            $this->title        = $this->get_option( 'title' );
            $this->description  = $this->get_option( 'description' );
            $this->account_name = $this->get_option( 'account_name' );
            $this->account_number = $this->get_option( 'account_number' );
            $this->template_id = $this->get_option( 'template_id' );
            $this->prefix = $this->get_option('prefix');
            $this->bank = $this->get_option('bank');

            // Actions.
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            //add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'save_account_details' ) );
            add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );

		    // Customer Emails.
		    add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
	
 		}

 		/**
 		* Initialise Gateway Settings Form Fields.
 		*/
        public function init_form_fields(){

            //Tự động sinh prefix đơn hàng cho website.
            $server_domain = $_SERVER['SERVER_NAME'];
            $shopname = preg_replace('#^.+://[^/]+#', '', $server_domain);
            $shopname = str_replace(".","",$shopname);

            //Tạo danh sách tên ngân hàng cho select form
            $bank_name = [];
            foreach ($this->bank_list['data'] as $bank) {
                $bank_name[$bank['short_name']] = $bank['short_name'];
            }

		    $this->form_fields = array(
                'enabled'         => array(
                    'title'   => __( 'Enable/Disable', 'woocommerce' ),
                    'type'    => 'checkbox',
                    'label'   => __( 'Enable Vietnam Payment Gateway', 'vnpg' ),
                    'default' => 'no',
                ),
                'title'           => array(
                    'title'       => __( 'Title', 'woocommerce' ),
                    'type'        => 'text',
                    'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
                    'default'     => __( 'Direct Bank Transfer via Vietcombank (VietQR)', 'vnpg' ),
                    'desc_tip'    => true,
                ),
                'description'     => array(
                    'title'       => __( 'Description', 'woocommerce' ),
                    'type'        => 'textarea',
                    'description' => __( 'Payment method description that the customer will see on your checkout.', 'woocommerce' ),
                    'default'     => __( 'Make your payment directly into our bank account. Please use your Order ID as the payment reference. Your order will not be shipped until the funds have cleared in our account.', 'woocommerce' ),
                    'desc_tip'    => true,
                ),
                'bank'           => array(
                    'title'       => __('Bank Name', 'vnpg'),
                    'type'        => 'select',
                    'options'     => $bank_name,
                  ),
                'account_number' => array(
                    'title' => __( 'Account Number', 'vnpg'),
                    'type' => 'text',
                  ),
                 'account_name' => array(
                    'title' => __( 'Account Name', 'vnpg'),
                    'type' => 'text'
                  ),
                 'prefix'           => array(
                    'title'       => __('Prefix', 'vnpg'),
                    'type'        => 'text',
                    'description' => __('Prefix used to combine with order code to create money transfer content, Set rules: no spaces, no more than 15 characters and no special characters. Violations will be deleted', 'vnpg'),
                    'default'     => $shopname,
                    'desc_tip'    => true,
                  ),
                  'template_id' => array(
                    'title' => __( 'VietQR Template ID', 'vnpg'),
                    'type' => 'text',
                    'default' => 'compact'
                  ),
                  
            
            );
	
	 	}
		
        /**
         * Output for the order received page.
         *
         * @param int $order_id Order ID.
         */
        public function thankyou_page( $order_id ) {
            $this->payment_details( $order_id );
        }
        
        /**
         * Add content to the WC emails.
         *
         * @param WC_Order $order Order object.
         * @param bool     $sent_to_admin Sent to admin.
         * @param bool     $plain_text Email format: plain text or HTML.
         */
        public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
            if (!$sent_to_admin && 'vnpg' === $order->get_payment_method() && $order->has_status('on-hold')) {
                $this->payment_details($order->get_id());
            }
        }

        private function payment_details($order_id) {

            // Get order and store in $order.
		    $order = wc_get_order($order_id);

            // Get VietQR Image URL and Pay URL
            $data = $this->get_vietqr_img_url($order_id);
			$qrcode_image_url  = $data['img_url'];
			$qrcode_page_url = $data['pay_url'];

            $html  = '';        
            $html .= '<section class="vnpg-payment">';
            $html .= '<h3>Chuyển khoản ngân hàng</h3>';
            $html .= '<div class="vnpg-payment-detail">
                        <div class="vnpg-qr-code"><p>Bạn cần sử dụng một App ngân hàng bất kỳ để chuyển khoản theo nội dung bên dưới. Vui lòng nhập đúng nội dung  <strong>' .  $this->prefix . $order_id   .'</strong> để đơn hàng được xử lý nhanh nhất.</h3>';
            
            if ($qrcode_image_url) {
                $html .= '<div id="qrcode">
                        <img src="' . esc_html($qrcode_image_url) . '"  alt="VietQR QR Image" width="400px" />
                      </div>';
            }
            $html .= '<div class="bank-name"><span>Ngân hàng:</span><span> '. $this->bank . '</span></div>';
            $html .= '</div>';
            //$html .= '<ul>';
            //$html .= '<li class="order-amount">Số tiền: '. $order->get_total() . '</li>';
            //$html .= '<li class="bank-name">Ngân hàng: '. $this->bank . '</li>';
            //$html .= '<li class="account-number">Số tài khoản: '. $this->account_number . '</li>';
            //$html .= '<li class="account-name">Chủ tài khoản: '. $this->account_name . '</li>';
            //$html .= '<li class="prefix">Nội dung: '. $this->prefix . $order->get_order_number() .'</li>';
            //$html .= '</ul>';

            //$html .= '<div class="bank-info">';
            $html .= '<div class="order-amount"><span>Số tiền:</span><span> '. $order->get_total() . '</span></div>';
            $html .= '<div class="account-number"><span>Số tài khoản:</span><span> '. $this->account_number . '</span></div>';
            $html .= '<div class="account-name"><span>Chủ tài khoản:</span><span> '. $this->account_name . '</span></div>';
            $html .= '<div class="prefix"><span>Nội dung:</span> <span>'. $this->prefix . $order->get_order_number() .'</span></div>';

            //$html .= '</div>';

            $html .= '</div></section>';

            $html .= '<!-- STYLE CSS-->
                        <style>
                            .vnpg-payment {
                                display: flex;
                                flex-direction: column;
                                align-items: center;
                                margin: 40px auto;
                                max-width: 800px;
                            }

                            .vnpg-payment-detail {
                                border: 1px solid #DDD;
                                margin-top: 20px;
                            }

                            .vnpg-payment-detail > div {
                                padding: 20px;
                                border-bottom: 1px solid #DDD;
                            }

                            .vnpg-payment-detail > div:last-of-type {       
                                border-bottom: 0;
                            }
                            
                            #qrcode {
                                background: #FFF;
                                position: relative;
                                display: flex;
                                justify-content: center;
                                max-width: 400px;
                                margin: 40px auto;
                            }
                            
                            #qrcode:before {
                                content: "";
                                position: absolute;
                                top: 0; right: 0; bottom: 0; left: 0;
                                z-index: -1;
                                margin: -10px;
                                border-radius: inherit;
                                background: linear-gradient(to right, #21447B, #A2CA46);
                                border-radius: 15px;
                            }
                            #qrcode img {
                                padding: 10px 0;
                            }
                            .bank-info {
                                width: 100%;
                            }
                            .bank-info div {
                                border-bottom: 1px solid #EEE;
                                padding: 5px 0;
                            }
                            
                            .bank-info span:nth-child(2) {
                                font-weight: bold;
                            }
                            </style>';

            echo $html;
        }

        /**
         * Process the payment and return the result.
         *
         * @param int $order_id Order ID.
         * @return array
         */
        public function process_payment( $order_id ) {
    
            $order = wc_get_order( $order_id );
    
            if ( $order->get_total() > 0 ) {
                // Mark as on-hold (we're awaiting the payment).
                $order->update_status( apply_filters( 'woocommerce_vnpg_process_payment_order_status', 'on-hold', $order ), __( 'Awaiting BACS payment', 'woocommerce' ) );
            } else {
                $order->payment_complete();
            }
    
            // Remove cart.
            WC()->cart->empty_cart();
    
            // Return thankyou redirect.
            return array(
                'result'   => 'success',
                'redirect' => $this->get_return_url( $order ),
            );
    
        }

        public function get_vietqr_img_url($order_id) {

            // Get order and store in $order.
		    $order = wc_get_order($order_id);

            $accountNo = $this->account_number;
            $accountName = $this->account_name;
            $bank = $this->bank;
            $amount = $order->get_total();
            $info = $this->prefix . $order_id;
            
            $template = $this->template_id;

            $img_url = get_transient( 'vietqr_img_url_'.$order_id );
            $pay_url = get_transient( 'vietqr_pay_url_'.$order_id );

            if ( false === $img_url ) {
                $img_url = "https://img.vietqr.io/image/{$bank}-{$accountNo}-{$template}.jpg?amount={$amount}&addInfo={$info}&accountName={$accountName}";
            }

            if ( false === $pay_url ) {
                $pay_url = "https://api.vietqr.io/{$bank}/{$accountNo}/{$amount}/{$info}";
            }

            set_transient( 'vietqr_img_url_'.$order_id, $img_url, DAY_IN_SECONDS );
            set_transient( 'vietqr_pay_url_'.$order_id, $pay_url, DAY_IN_SECONDS );

            return array(
                "img_url" => $img_url,
                "pay_url" => $pay_url,
            );
	    }

        public function get_vietqr_bank_list() {

            $body = get_transient( 'vietqr_banklist' );
            
            if ( false === $body ) {
                $url = "https://api.vietqr.io/v2/banks";
                $response = wp_remote_get($url );
            
                if (200 !== wp_remote_retrieve_response_code($response)) {
                    return;
                }
            
                $body = wp_remote_retrieve_body($response);
                set_transient( 'vietqr_banklist', $body, DAY_IN_SECONDS );
            }

            $bank_list = json_decode($body, true);
            return $bank_list;
        }

    }
}