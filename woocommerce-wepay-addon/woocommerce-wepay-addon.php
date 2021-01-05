<?php
/*
  Plugin Name: WePay Woocommerce addon
  Plugin URI: http://clariontechnologies.co.in
  Description: This extension allows you to accept payments in WooCommerce via WePay Payment Gateway.
  Version: 3.0.0
  Author: Yogesh Pawar, Clarion Technologies
  Author URI: http://clariontechnologies.co.in
 */

require'classes/wepay.php';

/**
 * 
 * @return typeFunction to add our Payment Option in WooCommerce
 */
function woocommerce_wepay_payment_init()
{

    /**
     * Check if WooCommerce payment gateway class exists
     */
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    /**
     * We Pay Payment Class
     */
    class WC_WePay_Gateway extends WC_Payment_Gateway
    {

        /**
         * Constructor of the class
         */
        public function __construct()
        {

            $this->id = 'wepay';
            $this->method_title = __('WePay', 'woocommerce');
            $this->has_fields = false;
            $this->redirect_uri = WC()->api_request_url('WC_WePay_Gateway');

            $this->init_form_fields();
            $this->init_settings();

            $this->title = __($this->settings['title'], 'woocommerce');
            $this->description = __($this->settings['description'], 'woocommerce');
            $this->enviroment = $this->settings['enviroment'];

            $this->api_version = '';

            $this->type = $this->settings['type'];
            $this->mode = $this->settings['mode'];
            $this->auto_capture = true;
            $this->short_description = $this->settings['short_description'];


            $this->account_id = $this->settings['account_id'];
            $this->client_id = $this->settings['client_id'];
            $this->client_secret = $this->settings['client_secret'];
            $this->token = $this->settings['access_token'];



            $this->debugMode = __($this->settings['debugMode'], 'woocommerce');


            $this->msg['message'] = '';
            $this->msg['class'] = '';

            if (isset($this->settings['orderstatus'])) {
                $this->orderstatus = $this->settings['orderstatus'];
            } else {
                $this->orderstatus = 1;
            }

            if ($this->debugMode == 'on') {
                $this->logs = new WC_Logger();
            }

            if ($this->enviroment == 'sandbox') {
                $this->wepay_jsdomain = "https://stage.wepay.com/js/iframe.wepay.js";
            } else {
                $this->wepay_jsdomain = "https://wepay.com/js/iframe.wepay.js";
            }

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_receipt_wepay', array($this, 'receipt_page'));
            add_action('woocommerce_api_wc_wepay_gateway', array($this, 'callback'));
        }

        /**
         * Initialise Form Fields
         */
        public function init_form_fields()
        {

            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Enable WePay Payment Gateway.', 'woocommerce'),
                    'default' => 'no'
                ),
                'title' => array(
                    'title' => __('Title:', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('This title is shown to the user during checkout.', 'woocommerce'),
                    'default' => __('WePay', 'woocommerce')
                ),
                'description' => array(
                    'title' => __('Description:', 'woocommerce'),
                    'type' => 'textarea',
                    'description' => __('This description is shown to the user during checkout.', 'woocommerce'),
                    'default' => __('Pay using WePay Payment Gateway.', 'woocommerce')
                ),
                'enviroment' => array(
                    'title' => __('Gateway Environment', 'woocommerce'),
                    'type' => 'select',
                    'description' => '',
                    'options' => array(
                        'sandbox' => __('Sandbox (Testing Environment)', 'woocommerce'),
                        'production' => __('Production (Live Environment)', 'woocommerce')
                    )),
                'orderstatus' => array(
                    'title' => __('Order Status', 'woocommerce'),
                    'type' => 'select',
                    'description' => 'The order status which will be updated after payment completion.',
                    'default' => '1',
                    'desc_tip' => true,
                    'options' => array(
                        '1' => __('completed', 'woocommerce'),
                        '2' => __('processing', 'woocommerce')
                    )),
                'client_id' => array(
                    'title' => __('Client ID', 'woocommerce'),
                    'type' => 'text',
                    'description' => __("The Client ID of the WePay payment account. ", 'woocommerce'),
                    'required' => true,
                    'desc_tip' => true,
                ),
                'client_secret' => array(
                    'title' => __('Client Secret', 'woocommerce'),
                    'type' => 'text',
                    'description' => __("The Client Secret key of the WePay payment account. ", 'woocommerce'),
                    'required' => true,
                    'desc_tip' => true,
                ),
                'access_token' => array(
                    'title' => __('Access Token', 'woocommerce'),
                    'type' => 'text',
                    'description' => __("The Access Token key of the WePay payment account. ", 'woocommerce'),
                    'required' => true,
                    'desc_tip' => true,
                ),
                'account_id' => array(
                    'title' => __('Account Id', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('The Account number of the WePay payment account.', 'woocommerce'),
                    'required' => true,
                    'desc_tip' => true,
                ),
                'short_description' => array(
                    'title' => __('Short Description ', 'woocommerce'),
                    'type' => 'text',
                    'description' => __("Short Description that will be shown during checkout to user. ", 'woocommerce'),
                    'required' => true,
                    'desc_tip' => true,
                    'default' => 'Order #{order_id}'
                ),
                'mode' => array(
                    'title' => __('Checkout Mode', 'woocommerce'),
                    'type' => 'select',
                    'description' => __("WePay supports 2 mode of payment ie 'IFrame and Regular'. Select one of them that is required,", "woocommerce"),
                    'default' => 'regular',
                    'desc_tip' => true,
                    'options' => array(
                        'regular' => __('Redirect', 'woocommerce'),
                        'iframe' => __('iFrame', 'woocommerce')
                    )),
                'type' => array(
                    'title' => __('Transaction Type', 'woocommerce'),
                    'type' => 'select',
                    'description' => __("The the checkout type (one of the following: Goods, Service, Donation, Event or Personal)", "woocommerce"),
                    'default' => 'goods',
                    'desc_tip' => true,
                    'options' => array(
                        'goods' => __('Goods', 'woocommerce'),
                        'service' => __('Service', 'woocommerce'),
                        'donation' => __('Donation', 'woocommerce'),
                        'event' => __('Event', 'woocommerce'),
                        'personal' => __('Personal', 'woocommerce')
                    )),
                'debugMode' => array(
                    'title' => __('Debug Mode', 'woocommerce'),
                    'type' => 'select',
                    'description' => '',
                    'options' => array(
                        'off' => __('Off', 'woocommerce'),
                        'on' => __('On', 'woocommerce')
                    ))
            );
        }

        /**
         * Admin Options
         */
        public function admin_options()
        {

            if ($this->enviroment == 'production' && get_option('woocommerce_force_ssl_checkout') == 'no' && $this->enabled == 'yes') {
                echo '<div class="error"><p>' . sprintf(__('%s WePay Sandbox testing is disabled and can performe live transactions but the <a href="%s">force SSL option</a> is disabled; your checkout is not secure! Please enable SSL and ensure your server has a valid SSL certificate.', 'woothemes'), $this->method_title, admin_url('admin.php?page=wc-settings&tab=checkout')) . '</p></div>';
            }

            echo '<h3>' . __('WePay Payment Gateway', 'woocommerce') . '</h3>';

            $available_currencies = array(
                'USD'
            );

            if (!in_array(get_woocommerce_currency(), $available_currencies)) {
                echo '<div class="error"><p><strong>' . __('Gateway Disabled: ', 'woocommerce') . '</strong> ' . sprintf(__('%s does not support your store currency. Please check for currencies supported by WePay', 'woothemes'), $this->method_title) . '</p></div>';
            } else {
                echo '<table class="form-table">';
                $this->generate_settings_html();
                echo '</table>';
            }
        }

        /**
         * Process Payment
         * @global type $woocommerce
         * @global type $wp_rewrite
         * @param type $order_id
         * @return type
         */
        public function process_payment($order_id)
        {

            global $woocommerce;
            global $wp_rewrite;

            $order = new WC_Order($order_id);
            $total_amount = $order->get_total();

            if ($this->enviroment == "sandbox") {
                Wepay::useStaging($this->client_id, $this->client_secret);
            } else {
                Wepay::useProduction($this->client_id, $this->client_secret);
            }

            $wepay = new WePay($this->token);

            if ($this->mode == 'iframe') {

                try {

                    if ($wp_rewrite->permalink_structure == '') {
                        $wepay_checkout_url = $woocommerce->cart->get_checkout_url() . '&order-pay=' . $order_id . '&key=' . $order->order_key;
                    } else {
                        $wepay_checkout_url = $woocommerce->cart->get_checkout_url() . '/order-pay/' . $order_id . '?key=' . $order->order_key;
                    }

                    return array(
                        'result' => 'success',
                        'redirect' => $wepay_checkout_url
                    );
                } catch (Exception $wepay_error) {
                    $error = $wepay_error->getMessage();
                    $order->add_order_note(sprintf("%s Payments Failed: '%s'", $this->method_title, $error));
                    wc_add_notice(__(sprintf("%s '%s'", $this->method_title, $error), 'woocommerce'), "error");
                }
            } else {

                try {

                    $wepay_checkout = $wepay->request('checkout/create', array(
                        'account_id' => $this->account_id,
                        'amount' => $total_amount,
                        'currency' => get_woocommerce_currency(),
                        'short_description' => __(str_replace("{order_id}", $order_id, $this->short_description), "woocommerce"),
                        'type' => $this->type,
                        'hosted_checkout' => array(
                            'redirect_uri' => $this->redirect_uri . "?order_id=" . $order_id,
                        ),
                    ));

                    $wepay_checkout_url = $wepay_checkout->hosted_checkout;

                    return array(
                        'result' => 'success',
                        'redirect' => $wepay_checkout_url->checkout_uri
                    );
                } catch (Exception $wepay_error) {
                    $error = $wepay_error->getMessage();
                    $order->add_order_note(sprintf("%s Payments Failed: '%s'", $this->method_title, $error));
                    wc_add_notice(__(sprintf("%s '%s'", $this->method_title, $error), 'woocommerce'), "error");
                }
            }
        }

        /**
         * Generate Receipt
         * @global type $woocommerce
         * @param type $order_id
         * @return boolean
         */
        public function receipt_page($order_id)
        {

            global $woocommerce;

            $order = new WC_Order($order_id);
            $total_amount = $order->get_total();

            if ($this->enviroment == "sandbox") {
                Wepay::useStaging($this->client_id, $this->client_secret);
            } else {
                Wepay::useProduction($this->client_id, $this->client_secret);
            }

            $wepay = new WePay($this->token);

            $wepay_checkout = $wepay->request('checkout/create', array(
                'account_id' => $this->account_id,
                'amount' => $total_amount,
                'currency' => get_woocommerce_currency(),
                'auto_capture' => $this->auto_capture,
                'short_description' => __(str_replace("{order_id}", $order_id, $this->short_description), "woocommerce"),
                'type' => $this->type,
                'hosted_checkout' => array(
                    'redirect_uri' => $this->redirect_uri . "?order_id=" . $order_id,
                ),
            ));

            $wepay_checkout_url = $wepay_checkout->hosted_checkout;

            if (isset($error)) {

                ?>
                <h2 style="color:red">ERROR: <?php echo $error ?></h2>
                <?php
            } else {

                ?>
                <div id="wepay_checkout_div"></div>                
                <script type="text/javascript" src="<?php echo $this->wepay_jsdomain; ?>"></script>                
                <script type="text/javascript">
                    WePay.iframe_checkout("wepay_checkout_div", "<?php echo $wepay_checkout_url->checkout_uri ?>");
                </script>
                <?php
            }

            return false;
        }

        /**
         * Call back Function 
         * @global type $woocommerce
         */
        public function callback()
        {

            @ob_clean();
            global $woocommerce;

            if ($this->enviroment == "sandbox") {
                Wepay::useStaging($this->client_id, $this->client_secret);
            } else {
                Wepay::useProduction($this->client_id, $this->client_secret);
            }

            $wepay = new WePay($this->token);

            $request = !empty($_REQUEST) ? $_REQUEST : false;

            if ($request) {

                $checkout_id = woocommerce_clean($request['checkout_id']);
                $order_id = woocommerce_clean($request['order_id']);

                try {

                    $response = $wepay->request('checkout', array(
                        'checkout_id' => $checkout_id,
                    ));

                    $order = new WC_Order($order_id);

                    if ($response->state == 'captured') {

                        $order->payment_complete();
                        $order->update_status('completed');
                        $order->add_order_note(
                            sprintf(
                                "%s Payment Completed with Checkout Id of '%s'", $this->method_title, $checkout_id
                            )
                        );
                    } elseif ($response->state == 'authorized') {

                        $order->payment_complete();
                        $order->update_status('completed');
                        $order->add_order_note(
                            sprintf(
                                "%s Payment Completed with Checkout Id of '%s'", $this->method_title, $checkout_id
                            )
                        );
                    } elseif ($response->state == 'reserved') {

                        $order->payment_complete();
                        $order->update_status('completed');
                        $order->add_order_note(
                            sprintf(
                                "%s Payment Completed with Checkout Id of '%s'", $this->method_title, $checkout_id
                            )
                        );
                    } elseif ($response->state == 'refunded') {

                        $order->payment_complete();
                        $order->update_status('refunded');
                        $order->add_order_note(
                            sprintf(
                                "%s Payment Refunded with Checkout Id of '%s'", $this->method_title, $checkout_id
                            )
                        );
                    } elseif ($response->state == 'cancelled') {

                        $order->payment_complete();
                        $order->update_status('cancelled');
                        $order->add_order_note(
                            sprintf(
                                "%s Payment Cancelled with Checkout Id of '%s'", $this->method_title, $checkout_id
                            )
                        );
                    } elseif ($response->state == 'failed') {

                        $order->payment_complete();
                        $order->update_status('failed');
                        $order->add_order_note(
                            sprintf(
                                "%s Payment Failed with Checkout Id of '%s'", $this->method_title, $checkout_id
                            )
                        );
                    } elseif ($response->state == 'expired') {
                        $order->update_status('pending', __('Checkouts get expired if they are still in state new after 30 minutes (ie they have been abandoned). VVPLOCKER.COM', 'woocommerce'));
                        $woocommerce->cart->empty_cart();
                    }

                    $woocommerce->cart->empty_cart();
                    wp_redirect($this->get_return_url($order));
                    exit;
                } catch (Exception $e) {
                    $error = $e->getMessage();
                    $order->add_order_note(sprintf("%s Payments Failed: '%s'", $this->method_title, $error));
                    wc_add_notice(__(sprintf("%s '%s'", $this->method_title, $error), 'woocommerce'), "error");
                    wp_redirect($woocommerce->cart->get_checkout_url());
                    exit;
                }
            }

            exit;
        }
    }

    /**
     * Function to add payment method
     * @param array $methods
     * @return string
     */
    function woocommerce_add_wepay_gateway_method($methods)
    {
        $methods[] = 'WC_WePay_Gateway';
        return $methods;
    }
    add_filter('woocommerce_payment_gateways', 'woocommerce_add_wepay_gateway_method');
}
add_action('plugins_loaded', 'woocommerce_wepay_payment_init', 0);
