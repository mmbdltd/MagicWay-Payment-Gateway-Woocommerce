<?php
if (!class_exists('WC_Payment_Gateway')) return;

class Magicway_Payment extends WC_Payment_Gateway
{
    public function __construct()
    {
        $this->id = 'magicway';
        $this->medthod_title = 'magicway';
        $this->has_fields = false;

        $this->init_form_fields();
        $this->init_settings();
        $this->title = sanitize_text_field($this->settings['title']);
        $this->description = sanitize_textarea_field($this->settings['description']);
        $this->store_id = sanitize_text_field($this->settings['store_id']);
        $this->store_password = sanitize_text_field($this->settings['store_password']);
        $this->username = sanitize_text_field($this->settings['username']);
        $this->email = sanitize_email($this->settings['email']);
        $this->testmode = $this->get_option('testmode');
        $this->testurl = "https://sandbox.magicway.io/api/V1/payment-initiate";
        $this->liveurl = "https://securepay.magicway.io/api/V1/payment-initiate";
        $this->redirect_page_id = $this->settings['redirect_page_id'];
        $this->fail_page_id = $this->settings['fail_page_id'];
        $this->msg['message'] = "";
        $this->msg['class'] = "";
        if(!empty($this->email) && !is_email($this->email)){
            echo  __('Please input an valid email for store user');exit;
        }
        add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'check_ipn_response')); // for IPN/callback
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options')); // save settings
        add_action('woocommerce_receipt_magicway', array($this, 'receipt_page'));
    }

    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'magicway'),
                'type' => 'checkbox',
                'label' => __('Enable MagicWay Payment Gateway.', 'magicway'),
                'default' => 'yes'
            ),
            'testmode' => array(
                'title' => __('Testmode', 'woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable Testmode', 'woocommerce'),
                'default' => 'yes',
                'description' => __('Use Sandbox (testmode) API for development purposes. Don\'t forget to uncheck before going live.'),
            ),
            'title' => array(
                'title' => __('Title to show', 'magicway'),
                'type' => 'text',
                'description' => __('This will be shown as the payment method name on the checkout page.'),
                'default' => __('MagicWay Payment Gateway ( Credit/Debit card or Mobile banking ).', 'magicway')
            ),
            'description' => array(
                'title' => __('Description to show', 'magicway'),
                'type' => 'textarea',
                'description' => __('This will be shown as the payment method description on the checkout page.'),
                'default' => __('Pay securely by Credit/Debit card, Internet banking, or Mobile banking through MagicWay Payment Gateway.', 'magicway')
            ),
            'store_id' => array(
                'title' => __('Store ID', 'magicway'),
                'type' => 'text',
                'description' => __('API store id. You should obtain this info from MagicWay.')
            ),
            'store_password' => array(
                'title' => __('Store Password', 'magicway'),
                'type' => 'text',
                'description' => __('API store password. You should obtain this info from MagicWay.')
            ),
            'username' => array(
                'title' => __('Username', 'magicway'),
                'type' => 'text',
                'description' => __('Store user name. You should obtain this info from MagicWay.')
            ),
            'email' => array(
                'title' => __('Email', 'magicway'),
                'type' => 'email',
                'description' => __('Store user email. You should obtain this info from MagicWay.')
            ),
            'redirect_page_id' => array(
                'title' => __('Select Success Page'),
                'type' => 'select',
                'options' => $this->get_pages('Select Success Page'),
                'description' => __("User will be redirected here after a successful payment. We strongly recommend <span style='color: green;'><b>Checkout Page</b></span>.")
            ),
            'fail_page_id' => array(
                'title' => __('Fail / Cancel Page'),
                'type' => 'select',
                'options' => $this->get_pages('Select Fail / Cancel Page'),
                'description' => __("User will be redirected here if transaction fails or get canceled. We recommend <span style='color: green;'><b>Cart Page</b></span>.")
            )
        );
    }

    public function admin_options()
    {
        echo '<h2>' . __('MagicWay Payment Gateway', 'magicway') . '</h2>';
        echo '<p>' . __('Configure parameters to start accepting payments.') . '</p><hr>';
        echo '<table class="form-table">';
        $this->generate_settings_html();
        echo '</table>';
    }

    function plugins_url($path = '', $plugin = '')
    {
        $path = wp_normalize_path($path);
        $plugin = wp_normalize_path($plugin);
        $mu_plugin_dir = wp_normalize_path(WPMU_PLUGIN_DIR);

        if (!empty($plugin) && 0 === strpos($plugin, $mu_plugin_dir)) {
            $url = WPMU_PLUGIN_URL;
        } else {
            $url = WP_PLUGIN_URL;
        }

        $url = set_url_scheme($url);

        if (!empty($plugin) && is_string($plugin)) {
            $folder = dirname(plugin_basename($plugin));
            if ('.' != $folder) {
                $url .= '/' . ltrim($folder, '/');
            }
        }

        if ($path && is_string($path)) {
            $url .= '/' . ltrim($path, '/');
        }

        /**
         * Filters the URL to the plugins directory.
         *
         * @param string $url The complete URL to the plugins directory including scheme and path.
         * @param string $path Path relative to the URL to the plugins directory. Blank string
         *                       if no path is specified.
         * @param string $plugin The plugin file path to be relative to. Blank string if no plugin
         *                       is specified.
         *
         */
        return apply_filters('plugins_url', $url, $path, $plugin);
    }

    /**
     *  There are no payment fields for magicway, but we want to show the description if set.
     **/
    function payment_fields()
    {
        if ($this->description) echo wpautop(wptexturize($this->description));
    }

    /**
     * Receipt Page
     **/
    function receipt_page($order)
    {
        echo '<p>' . __('Thank you for your order, please click the button below to pay with magicway.', 'magicway') . '</p>';
        echo $this->generate_magicway_form($order);
    }


    /**
     * Generate magicway button link
     **/
    public function generate_magicway_form($order_id)
    {
        global $woocommerce;
        $order = new WC_Order($order_id);
        $redirect_url = ($this->redirect_page_id == "" || $this->redirect_page_id == 0) ? get_site_url() . "/" : get_permalink($this->redirect_page_id);
        $fail_url = ($this->fail_page_id == "" || $this->fail_page_id == 0) ? get_site_url() . "/" : get_permalink($this->fail_page_id);
        $redirect_url = add_query_arg('wc-api', get_class($this), $redirect_url);
        // $fail_url = add_query_arg('wc-api', get_class($this), $fail_url);
        $declineURL = $order->get_cancel_order_url();

        $items = $woocommerce->cart->get_cart();

        $product_title = array();

        foreach ($items as $item => $values) {
            $_product = wc_get_product($values['data']->get_id());
            $product_title[] = $_product->get_title();
        }

        $product_name = implode(",", $product_title);

        $post_data = array(
            'store_id' => $this->store_id,
            'amount' => $order->get_total(),
            'order_id' => $order_id,
            'success_url' => $redirect_url,
            'fail_url' => $fail_url,
            'cancel_url' => $declineURL,
            'ipn_url' => '', // currently it's empty, in next version we insert value this field
            'cus_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'cus_address' => trim($order->get_billing_address_1(), ','),
            'cus_country' => wc()->countries->countries[$order->get_billing_country()],
            'cus_state' => $order->get_billing_state(),
            'cus_city' => $order->get_billing_city(),
            'cus_postcode' => $order->get_billing_postcode(),
            'msisdn' => $order->get_billing_phone(),
            'email' => $order->get_billing_email(),
            'currency' => "BDT",
            'num_of_item' => $woocommerce->cart->cart_contents_count,
            'product_name' => $product_name
        );

        if ($this->testmode === 'yes') {
            $liveurl = $this->testurl;
        } else {
            $liveurl = $this->liveurl;
        }
        # REQUEST SEND TO MAGIC WAY
        $response = wp_remote_post($liveurl, array(
                'method' => 'POST',
                'timeout' => 30,
                'body' => $post_data
            )
        );
        if ($response['response']['code'] === 200) {
            $magic_way_response = json_decode($response['body'], true);
            if ($magic_way_response['success'] !== true || $magic_way_response['status_code'] !== 200) {
                echo __("FAILED TO CONNECT WITH MAGIC WAY API");
                echo __("<br/>Failed Reason: ") . esc_html($magic_way_response['message']);
                exit;
            }
        } else {
            if (is_wp_error($response)) {
                echo esc_html($response->get_error_message());
            }
            echo __("Error Code: ") . esc_html($response['response']['code']);
            echo __("<br/>FAILED TO CONNECT WITH MAGIC WAY API");
            exit;
        }

        return '<form action="' . $magic_way_response['checkout_url'] . '" method="post" id="magic_way_payment_form">
	                <input type="submit" class="button-alt" id="submit_magic_way_payment_form" value="' . __('Pay via magic way') . '" /> <a class="button cancel" href="' . $order->get_cancel_order_url() . '">' . __('Cancel order &amp; restore cart') . '</a>
	                <script type="text/javascript">
	                    jQuery(function(){
	                        jQuery("body").block({
	                            message: "' . __('Thank you for your order. We are now redirecting you to Payment Gateway to make payment.', 'magicway') . '",
	                            overlayCSS: {
	                                background: "#fff",
	                                    opacity: 0.6
	                            },
	                            css: {
	                                padding:        20,
	                                textAlign:      "center",
	                                color:          "#555",
	                                border:         "3px solid #aaa",
	                                backgroundColor:"#fff",
	                                cursor:         "wait",
	                                lineHeight:"32px"
	                            }
	                        });
	                        jQuery("#submit_magic_way_payment_form").click();
	                    });
	                </script>
	            </form>';
    }

    /**
     * Process the payment and return the result
     **/
    function process_payment($order_id)
    {
        global $woocommerce;
        $order = new WC_Order($order_id);
        return array('result' => 'success', 'redirect' => $order->get_checkout_payment_url(true));
    }

    /**
     * Check for valid magic way server callback
     **/
    function check_ipn_response()
    {
        global $woocommerce;
        $ecom_order_id = $pay_status = $message = "";
        $tran_id = sanitize_text_field($_POST['order_id']);
        $val_id = sanitize_text_field($_POST['payment_ref_id']);
        $payment_channel = sanitize_text_field($_POST['opr']);
        if (isset($tran_id) && isset($val_id) && isset($payment_channel)) {
            $redirect_url = ($this->redirect_page_id == "" || $this->redirect_page_id == 0) ? get_site_url() . "/" : get_permalink($this->redirect_page_id);
            $fail_url = ($this->fail_page_id == "" || $this->fail_page_id == 0) ? get_site_url() . "/" : get_permalink($this->fail_page_id);
            if ($this->testmode === 'yes') {
                $access_token_url = "https://sandbox.magicway.io/api/V1/auth/token";
            } else {
                $access_token_url = "https://securepay.magicway.io/api/V1/auth/token";
            }
            $access_token_data = array(
                'store_id' => $this->store_id,
                'store_secret' => $this->store_password,
                'grant_type' => "password",
                'username' => $this->username,
                'email' => $this->email
            );
            $result = wp_remote_post(
                $access_token_url,
                array(
                    'method' => 'POST',
                    'timeout' => 30,
                    'body' => $access_token_data
                )
            );
            if ($result['response']['code'] === 200) {
                $result = json_decode($result['body']);
                if ($result->success === true) {
                    $access_token = $result->access_token;
                    if ($this->testmode === 'yes') {
                        $charge_verification_url = "https://sandbox.magicway.io/api/V1/charge/status";
                    } else {
                        $charge_verification_url = "https://securepay.magicway.io/api/V1/charge/status";
                    }
                    $charge_verification_data = array(
                        'opr' => $payment_channel,
                        'order_id' => $tran_id,
                        'reference_id' => $val_id,
                        'store_id' => $this->store_id,
                        'is_plugin' => "YES"
                    );
                    $hearers = array(
                        'Authorization' => "Bearer $access_token"
                    );
                    $charge_verification_response = wp_remote_post(
                        $charge_verification_url,
                        array(
                            'method' => 'POST',
                            'timeout' => 30,
                            'headers' => $hearers,
                            'body' => $charge_verification_data
                        )
                    );
                    if ($charge_verification_response['response']['code'] === 200) {
                        $charge_verification_response = json_decode($charge_verification_response['body']);
                        if ($charge_verification_response->success === true) {
                            $pay_status = $charge_verification_response->charge_status === "Success" ? "success" : "failed";
                            $ecom_order_id = $charge_verification_response->ecom_order_id;
                            $transaction_time = $charge_verification_response->transactionTime;
                        } else {
                            echo __("Failed Reason: ") . esc_html($charge_verification_response->message);
                            exit;
                        }
                    } else {
                        echo __("Failed Reason: ") . esc_html($charge_verification_response->message);
                        exit;
                    }
                } else {
                    echo __("Failed Reason: ") . esc_html($result->message);
                    exit;
                }
                $message .= 'Payment Status = ' . $pay_status . "\n";
                $message .= 'Payment Channel = ' . $payment_channel . "\n";
                $message .= 'Ecommerce Order id = ' . $ecom_order_id . "\n";
                $message .= 'Bank Transaction_id = ' . $val_id . "\n";
                $message .= 'Secure Pay Order_id = ' . $tran_id . "\n";
                $message .= 'Payment Transaction time = ' . $transaction_time;
            }

            if ($ecom_order_id != '') {
                try {
                    $order = wc_get_order($ecom_order_id);
                    if ($pay_status == "success") {
                        $this->msg['message'] = "Thank you for shopping with us. Your account has been charged and your transaction is successful. We will be shipping your order to you soon.";
                        $this->msg['class'] = 'success';

                        if ($order->get_status() === 'pending')
                        {
                            $order->update_status('Processing');
                            $order->payment_complete();
                        }
                        $order->add_order_note($message);
                        $order->add_order_note($this->msg['message']);
                        $woocommerce->cart->empty_cart();
                        $return_url = $order->get_checkout_order_received_url();
                        $redirect_url = str_replace('http:', 'http:', $return_url);
                    } else {
                        $order->update_status('Failed');
                        $order->add_order_note($message);
                        wc_add_notice(__('Unfortunately your card was declined and the order could not be processed. Please try again with a different card or payment method.', 'woocommerce'), 'error');
                        $redirect_url = $fail_url;
                    }

                } catch (Exception $e) {
                    $msg = "Error";
                }
            }
            wp_redirect($redirect_url);
        }
    }

    function showMessage($content)
    {
        return '<div class="box ' . $this->msg['class'] . '-box">' . $this->msg['message'] . '</div>' . $content;
    }

    # get all pages
    function get_pages($title = false, $indent = true)
    {
        $wp_pages = get_pages('sort_column=menu_order');
        $page_list = array();
        if ($title) $page_list[] = $title;
        foreach ($wp_pages as $page) {
            $prefix = '';
            # show indented child pages?
            if ($indent) {
                $has_parent = $page->post_parent;
                while ($has_parent) {
                    $prefix .= ' - ';
                    $next_page = get_page($has_parent);
                    $has_parent = $next_page->post_parent;
                }
            }
            # add to page list array array
            $page_list[$page->ID] = $prefix . $page->post_title;
        }
        return $page_list;
    }
}
