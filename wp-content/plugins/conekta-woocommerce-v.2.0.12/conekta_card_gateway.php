<?php

if (!class_exists('Conekta')) {
    require_once("lib/conekta-php/lib/Conekta.php");
}

/*
 * Title   : Conekta Payment extension for WooCommerce
 * Author  : Cristina Randall
 * Url     : https://wordpress.org/plugins/conekta-woocommerce
*/
class WC_Conekta_Card_Gateway extends WC_Conekta_Plugin
{
    protected $GATEWAY_NAME            = "WC_Conekta_Card_Gateway";
    protected $is_sandbox              = true;
    protected $order                   = null;
    protected $transaction_id          = null;
    protected $conektaOrderId          = null;
    protected $transactionErrorMessage = null;
    protected $currencies              = array('MXN', 'USD');

    public function __construct() {
        $this->id = 'conektacard';
        $this->method_title = __('Conekta Card', 'conektacard');
        $this->has_fields = true;

        $this->init_form_fields();
        $this->init_settings();

        $this->title       = $this->settings['title'];
        $this->description = '';
        $this->icon        = $this->settings['alternate_imageurl'] ? $this->settings['alternate_imageurl']  : WP_PLUGIN_URL . "/" . plugin_basename( dirname(__FILE__)) . '/images/credits.png';

        $this->usesandboxapi      = strcmp($this->settings['debug'], 'yes') == 0;
        $this->enablemeses        = strcmp($this->settings['meses'], 'yes') == 0;
        $this->testApiKey         = $this->settings['test_api_key'];
        $this->liveApiKey         = $this->settings['live_api_key'];
        $this->testPublishableKey = $this->settings['test_publishable_key'];
        $this->livePublishableKey = $this->settings['live_publishable_key'];
        $this->publishable_key    = $this->usesandboxapi ? $this->testPublishableKey : $this->livePublishableKey;
        $this->secret_key         = $this->usesandboxapi ? $this->testApiKey : $this->liveApiKey;

        $this->lang_options = parent::set_locale_options()->get_lang_options();        

        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
        add_action('woocommerce_update_options_payment_gateways_'.$this->id, array($this, 'process_admin_options'));
        add_action('admin_notices', array(&$this, 'perform_ssl_check'));

        if (!$this->validateCurrency()) {
            $this->enabled = false;
        }

        if(empty($this->secret_key)) {
          $this->enabled = false;
        }
    }

    /**
    * Checks to see if SSL is configured and if plugin is configured in production mode 
    * Forces use of SSL if not in testing 
    */ 
    public function perform_ssl_check()
    {
        if (!$this->usesandboxapi && get_option('woocommerce_force_ssl_checkout') == 'no' && $this->enabled == 'yes') {
            echo '<div class="error"><p>'.sprintf(__('%s sandbox testing is disabled and can performe live transactions but the <a href="%s">force SSL option</a> is disabled; your checkout is not secure! Please enable SSL and ensure your server has a valid SSL certificate.', 'woothemes'), $this->GATEWAY_NAME, admin_url('admin.php?page=settings')).'</p></div>';
        }
    }
    
    public function init_form_fields()
    {
        $this->form_fields = array(
         'enabled' => array(
          'type'        => 'checkbox',
          'title'       => __('Enable/Disable', 'woothemes'),
          'label'       => __('Enable Credit Card Payment', 'woothemes'),
          'default'     => 'yes'
          ),
         'meses' => array(
            'type'        => 'checkbox',
            'title'       => __('Meses sin Intereses', 'woothemes'),
            'label'       => __('Enable Meses sin Intereses', 'woothemes'),
            'default'     => 'no'
            ),
         'debug' => array(
            'type'        => 'checkbox',
            'title'       => __('Testing', 'woothemes'),
            'label'       => __('Turn on testing', 'woothemes'),
            'default'     => 'no'
            ),
         'title' => array(
            'type'        => 'text',
            'title'       => __('Title', 'woothemes'),
            'description' => __('This controls the title which the user sees during checkout.', 'woothemes'),
            'default'     => __('Credit Card Payment', 'woothemes')
            ),
         'test_api_key' => array(
             'type'        => 'password',
             'title'       => __('Conekta API Test Private key', 'woothemes'),
             'default'     => __('', 'woothemes')
             ),
         'test_publishable_key' => array(
             'type'        => 'text',
             'title'       => __('Conekta API Test Public key', 'woothemes'),
             'default'     => __('', 'woothemes')
             ),
         'live_api_key' => array(
             'type'        => 'password',
             'title'       => __('Conekta API Live Private key', 'woothemes'),
             'default'     => __('', 'woothemes')
             ),
         'live_publishable_key' => array(
             'type'        => 'text',
             'title'       => __('Conekta API Live Public key', 'woothemes'),
             'default'     => __('', 'woothemes')
             ),
         'alternate_imageurl' => array(
           'type'        => 'text',
           'title'       => __('Alternate Image to display on checkout, use fullly qualified url, served via https', 'woothemes'),
           'default'     => __('', 'woothemes')
           ),


         );
    }

    public function admin_options() {
        include_once('templates/admin.php');
    }

    public function payment_fields() {
        include_once('templates/payment.php');
    }

    public function payment_scripts() {
        if (!is_checkout()) {
            return;
        }

        wp_enqueue_script('conekta_js', 'https://conektaapi.s3.amazonaws.com/v0.3.2/js/conekta.js', '', '', true);
        wp_enqueue_script('tokenize', WP_PLUGIN_URL."/".plugin_basename(dirname(__FILE__)).'/assets/js/tokenize.js', '', '1.0', true);

        $params = array(
            'public_key' => $this->publishable_key
        );

        wp_localize_script('tokenize', 'wc_conekta_params', $params);
    }

    protected function send_to_conekta()
    {
        global $woocommerce;
        include_once('conekta_gateway_helper.php');
        \Conekta\Conekta::setApiKey($this->secret_key);
        \Conekta\Conekta::setApiVersion('2.0.0');
        \Conekta\Conekta::setPlugin('WooCommerce');
        \Conekta\Conekta::setLocale('es');

        $data             = getRequestData($this->order);
        $amount           = $data['amount'];
        $items            = $this->order->get_items();
        $taxes            = $this->order->get_taxes();
        $line_items       = build_line_items($items, parent::get_version());
        $discount_lines   = build_discount_lines($data);
        $shipping_lines   = build_shipping_lines($data);
        $shipping_contact = build_shipping_contact($data);
        $tax_lines        = build_tax_lines($taxes);
        $customer_info    = build_customer_info($data);
        $order_metadata   = build_order_metadata($data);
        $order_details    = array(
            'currency'         => $data['currency'],
            'line_items'       => $line_items,
            'customer_info'    => $customer_info,
            'shipping_lines'   => $shipping_lines,
            'discount_lines'   => $discount_lines,
            'pre_authorize'    => true,
            'tax_lines'        => $tax_lines
        );

        if (!empty($shipping_contact)) {
            $order_details = array_merge($order_details, array('shipping_contact' => $shipping_contact));
        }


        if (!empty($order_metadata)) {
            $order_details = array_merge($order_details, array('metadata' => $order_metadata));
        }

        $order_details = check_balance($order_details, $amount);

        try {
            $conekta_order_id = get_post_meta($this->order->id, 'conekta-order-id', true);
            if (!empty($conekta_order_id)) {
                $order = \Conekta\Order::find($conekta_order_id);
                $order->update($order_details);
            } else {
                $order = \Conekta\Order::create($order_details);
            }

            update_post_meta($this->order->id, 'conekta-order-id', $order->id);

            $charge_details = array(
                'payment_method' => array(
                    'type'     => 'card',
                    'token_id' => $data['token']
                ),
                'amount' => $amount
            );

            $monthly_installments = $data['monthly_installments'];
            if ($monthly_installments > 1) {
                $charge_details['payment_method']['monthly_installments'] = $monthly_installments;
            }

            $charge = $order->createCharge($charge_details);

            $this->transactionId = $charge->id;
            if ($data['monthly_installments'] > 1) {
                update_post_meta( $this->order->id, 'meses-sin-intereses', $data['monthly_installments']);
            }
            update_post_meta( $this->order->id, 'transaction_id', $this->transactionId);
            return true;

        } catch(Conekta_Error $e) {
            $description = $e->message_to_purchaser;
            global $wp_version;
            if (version_compare($wp_version, '4.1', '>=')) {
                wc_add_notice(__('Error: ', 'woothemes') . $description , $notice_type = 'error');
            } else {
                error_log('Gateway Error:' . $description . "\n");
                $woocommerce->add_error(__('Error: ', 'woothemes') . $description);
            }
            return false;
        }
    }

    protected function markAsFailedPayment()
    {
        $this->order->add_order_note(
         sprintf(
             "%s Credit Card Payment Failed : '%s'",
             $this->GATEWAY_NAME,
             $this->transactionErrorMessage
             )
         );
    }

    protected function completeOrder()
    {
        global $woocommerce;

        if ($this->order->status == 'completed')
            return;

            // adjust stock levels and change order status
        $this->order->payment_complete();
        $woocommerce->cart->empty_cart();

        $this->order->add_order_note(
           sprintf(
               "%s payment completed with Transaction Id of '%s'",
               $this->GATEWAY_NAME,
               $this->transactionId
               )
           );

        unset($_SESSION['order_awaiting_payment']);
    }

    public function process_payment($order_id)
    {
        global $woocommerce;
        $this->order        = new WC_Order($order_id);
        if ($this->send_to_conekta())
        {
            $this->completeOrder();

            $result = array(
                'result' => 'success',
                'redirect' => $this->get_return_url($this->order)
                );
            return $result;
        }
        else
        {
            $this->markAsFailedPayment();
        }
    }

    public function createCharge($customer, $charge_request) {

    }

    /**
     * Checks if woocommerce has enabled available currencies for plugin
     *
     * @access public
     * @return bool
     */
    public function validateCurrency() {
        return in_array(get_woocommerce_currency(), $this->currencies);
    }

    public function isNullOrEmptyString($string) {
        return (!isset($string) || trim($string) === '');
    }
}

function conekta_card_add_gateway($methods) {
    array_push($methods, 'WC_Conekta_Card_Gateway');
    return $methods;
}

add_filter('woocommerce_payment_gateways', 'conekta_card_add_gateway');