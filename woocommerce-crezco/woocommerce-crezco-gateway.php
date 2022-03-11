<?php
/* Crezco Payment Gateway Class */
class WC_Payment_Gateway_Crezco extends WC_Payment_Gateway {

	// Setup our Gateway's id, description and other values
	function __construct() {

		// The global ID for this Payment method
		$this->id = "crezco";

		// The Title shown on the top of the Payment Gateways Page next to all the other Payment Gateways
		$this->method_title = __( "Crezco", 'woo-crezco' );

		// The description for this Payment Gateway, shown on the actual Payment options page on the backend
        $this->method_description = $this->define_method_description();

		// The title to be used for the vertical tabs that can be ordered top to bottom
		$this->title = __( "Crezco", 'woo-crezco' );

		// If you want to show an image next to the gateway's name on the frontend, enter a URL to an image.
		$this->icon = plugin_dir_url(__FILE__) . 'img/crezco-logo.png';

		// Bool. Can be set to true if you want payment fields to show on the checkout 
		// if doing a direct integration, which we are doing in this case
		$this->has_fields = true;

		// Supports the default credit card form
		//$this->supports = array( 'default_credit_card_form' );
        
        $this->enabled          = $this->get_option( 'enabled' );
                
        $this->apiKey           = $this->get_option( 'apiKey' );
        
        $this->apiURL           = $this->get_option( 'apiURL' );

        $this->merchantID       = $this->get_option( 'merchantID' );

        $this->crezco_partnercode = get_option( 'crezco_partnercode' );

        $this->crezco_user_id_sandbox     = get_option( 'crezco_user_id_sandbox' );

        $this->crezco_user_id_live        = get_option( 'crezco_user_id_live' );

        $this->env                  = $this->get_option( 'env' );

        /*$this->payeeName        = $this->get_option( 'payeeName' );

        $this->payeeiBan        = $this->get_option( 'payeeiBan' );

        $this->payeeAccountNumber = $this->get_option( 'payeeAccountNumber' );

        $this->payeeSortCode = $this->get_option( 'payeeSortCode' );*/

        $this->force_ssl        = $this->get_option( 'force_ssl' );
        
        // This basically defines your settings which are then loaded with init_settings()
		$this->init_form_fields();
        
		// After init_settings() is called, you can get the settings and load them into variables, e.g:
		// $this->title = $this->get_option( 'title' );
		$this->init_settings();
		
		// Turn these settings into variables we can use
		foreach ( $this->settings as $setting_key => $value ) {
			$this->$setting_key = $value;
		}
		
		
        //Actions
        add_action( 'woocommerce_receipt_' . $this->id, array( &$this, 'receipt_page' ) );
        add_action( 'woocommerce_api_approved_'.$this->id, array( $this, 'approved_response' ) );
        
        //add_action( 'woocommerce_thankyou', array( $this,'nv_check_status'), 10, 1);
		// Save settings
		if ( is_admin() ) {

			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            add_action( 'admin_notices', array( $this,	'do_ssl_check' ) );
            add_filter( 'woocommerce_get_settings_payments' , array( $this, 'crezco_get_merchantid') , 10, 2 );
           
		}	

        
        
        	
	} // End __construct()

    

    /**
	 * Defines the method description. If we are on the credit card tab in the settings, we want to change this.
	 *
	 * @return string
	 */
	private function define_method_description(): string {

        $crezco_partnercode         = get_option( 'crezco_partnercode' );
        $crezco_user_id_sandbox     = get_option( 'crezco_user_id_sandbox' );
        $crezco_user_id_live        = get_option( 'crezco_user_id_live' );

        $onboard_url                = admin_url().'admin.php?page=nv-manager-page';

        if(empty($crezco_partnercode))
        {
            $str = "<h2><strong><font color='red'>Please complete onboarding first</font></strong></h2> <a href='$onboard_url'>Complete Onboarding</a>";
        }
        else
        {
            $str = "<strong>Please complete the details below.</strong>";
        }
        
        return __(
			$str,
			'woo-crezco'
		);
	}

    public function api_call($url, $method, $data)
    {
        $response = wp_remote_get( $url, array(
            'method'    => $method,
            'sslverify' => false,
            'body'      => $data,
			'headers'   => array(
                'Content-Type: application/json',
                'X-Crezco-Key: '.$this->apiKey
			),
		) );

		if ( is_wp_error( $response ) ) {
			
            $error_message = $response->get_error_message();
            wc_add_notice( $error_message, 'error'  );

		} else {

			$body    		 = wp_remote_retrieve_body( $response );
			$data            = json_decode( $body, true );
			
        }
    }
    
    public function approved_response() 
    {

        global $woocommerce;

        $order_status = wc_clean($_GET['status']);

        $order_id = wc_clean($_GET['oid']);

        $customer_order = new WC_Order( $order_id );

        if($order_status == "success")
        {

            $payment_demand_id = get_post_meta($order_id, '_crezco_payment_id', true);
                            
            $datetime = date('d-m-Y h:i:s a');
            
            $curl = curl_init();

            curl_setopt_array($curl, array(
            CURLOPT_URL => $this->apiURL.'/pay-demands/'.$payment_demand_id.'/payments',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'X-Crezco-Key: '.$this->apiKey
            ),
            ));

            $response = curl_exec($curl);
            $err = curl_error($curl);
           
            curl_close($curl);
            
            update_post_meta($order_id, '_crezco_webhook_response', $response);
            

            if ($err) 
            {
                //wp_remote_error
                $customer_order->add_order_note( __( ' Error :'. $error_msg ));
                if($customer_order -> status != 'completed')
                {
                    $this -> msg['message'] = 'A error occured please retry';
                    $this -> msg['class'] = 'woocommerce_error';
                    
                    $customer_order -> update_status('failed');
                    $customer_order -> add_order_note('Payment attempt unsuccessful :'. $status);	
                    wc_add_notice( $status, 'error'  );
                    $url = $customer_order->get_checkout_payment_url();
                    //wp_delete_post( $order_id, true );
                    wp_safe_redirect($url);
                    exit;
                }    
            }
            else
            {
                /**
                 * Retrieve body response
                 */
                            
                if ( $response )
                {
                    $output = json_decode($response, true); 
                    update_post_meta($order_id, '_crezco_webhook_success', $output);
                }

                $state     = $output[0]['status']['code'];
        
                if($state == "Completed")
                {
                    
                    if($customer_order -> status != 'completed'){     
                        $this -> msg['message'] = 'Your payment was processed successfully. Thank you for shopping with Banked. Status : '. $state;
                        $this -> msg['class'] = 'woocommerce_message';
                                        
                        // Mark order as Paid
                        $customer_order->payment_complete();
                    
                        $customer_order -> add_order_note('Payment processed successfully.');
                        // Empty the cart (important step)
                        $woocommerce->cart->empty_cart();
            
                        // Redirect to thank you page
                        //wc_add_notice( $status, 'success'  );
                        wp_safe_redirect($this->get_return_url( $customer_order));
                        exit;
                    }    
                    
                }
                else
                {
                    if($customer_order -> status != 'completed'){
                        $this -> msg['message'] = 'Your transaction was declined by Banked';
                        $this -> msg['class'] = 'woocommerce_error';
                        $customer_order -> update_status('failed');
                        $customer_order -> add_order_note('Payment attempt unsuccessful :'. $status);	
                        wc_add_notice( $status, 'error'  );
                        $url = $customer_order->get_checkout_payment_url();
                        //wp_delete_post( $order_id, true );
                        wp_safe_redirect($url);
                        exit;
                    }    
                }

            
            }
        }
        else
        {
            if($customer_order -> status != 'completed'){
                $this -> msg['message'] = 'Your transaction was declined by Banked';
                $this -> msg['class'] = 'woocommerce_error';
                $customer_order -> update_status('failed');
                $customer_order -> add_order_note('Payment attempt unsuccessful :'. $status);	
                wc_add_notice( $status, 'error'  );
                $url = $customer_order->get_checkout_payment_url();
                //wp_delete_post( $order_id, true );
                wp_safe_redirect($url);
                exit;
            }    
        }
        
        exit();
    }

    public function payment_fields()
	{
        echo $this->description;
	}

    function showMessage($content){
        return '<div class="box '.$this->msg['class'].'-box">'.$this->msg['message'].'</div>'.$content;
    }
    
    // Build the administration fields for this specific Gateway
	public function init_form_fields() {
	    
        $crezco_partnercode         = get_option( 'crezco_partnercode' );

        if(!empty($crezco_partnercode))
        {

                $selectoptions = ["sandbox" => "Sandbox" , "live" => "Live"];
                $this->form_fields = array(
                    'enabled' => array(
                        'title'		=> __( 'Enable / Disable', 'woo-crezco' ),
                        'label'		=> __( 'Enable this payment gateway', 'woo-crezco' ),
                        'type'		=> 'checkbox',
                        'default'	=> 'no',
                    ),
                    'force_ssl' => array(
                        'title'		=> __( 'Enable / Disable SSL', 'woo-crezco' ),
                        'label'		=> __( 'Enable SSL', 'woo-crezco' ),
                        'type'		=> 'checkbox',
                        'default'	=> 'no',
                    ),
                    'env' => array(
                        'title'		=> __( 'Environment', 'spyr-4csonline' ),
                        'label'		=> __( 'Environment', 'spyr-4csonline' ),
                        'type'		=> 'select',
                        'description' => __( 'Sanbox or Live', 'spyr-4csonline' ),
                        'options'   => $selectoptions,
                        'default'	=> $this->env,
                    ),
                    'title' => array(
                        'title'		=> __( 'Title', 'woo-crezco' ),
                        'type'		=> 'text',
                        'desc_tip'	=> __( 'The payment title the customer will see during the checkout process.', 'woo-crezco' ),
                        'default'	=> __( 'Banked Payments', 'woo-crezco' ),
                    ),
                    'description' => array(
                        'title'		=> __( 'Description', 'woo-crezco' ),
                        'type'		=> 'textarea',
                        'desc_tip'	=> __( 'The payment description shown to customers during the checkout process.', 'woo-crezco' ),
                        'default'	=> __( 'Pay securely with Visa or Mastercard.', 'woo-crezco' ),
                        'css'		=> 'max-width:350px;'
                    ),
                    'apiKey' => array(
                        'title'		=> __( 'API Key', 'woo-crezco' ),
                        'type'		=> 'text',
                        'desc_tip'	=> __( 'This is the merchant-specific API key supplied by Crezco.', 'woo-crezco' ),
                    ),
                    'apiURL' => array(
                        'title'		=> __( 'API Url', 'woo-crezco' ),
                        'type'		=> 'text',
                        'desc_tip'	=> __( "This URL is derived from Crezco API Specification.", 'woo-crezco' ),
                        'default'   => "https://api.sandbox.crezco.com/v1"
                    ),
                    /*
                    'payeeName' => array(
                        'title'		=> __( 'Payee Name', 'woo-crezco' ),
                        'type'		=> 'text',
                        'desc_tip'	=> __( "Payee Name", 'woo-crezco' )
                    ),
                    'payeeiBan' => array(
                        'title'		=> __( 'Payee IBan', 'woo-crezco' ),
                        'type'		=> 'text',
                        'desc_tip'	=> __( "Payee Iban", 'woo-crezco' )
                    ),
                    'payeeAccountNumber' => array(
                        'title'		=> __( 'Payee Account Number', 'woo-crezco' ),
                        'type'		=> 'text',
                        'desc_tip'	=> __( "Payee Account Number", 'woo-crezco' )
                    ),
                    'payeeSortCode' => array(
                        'title'		=> __( 'Payee Sort Code', 'woo-crezco' ),
                        'type'		=> 'text',
                        'desc_tip'	=> __( "Payee Sort Code", 'woo-crezco' )
                    )
                    */
                );		
         }
	}

    public function guidv4($data = null) {
        // Generate 16 bytes (128 bits) of random data or use the data passed into the function.
        $data = $data ?? random_bytes(16);
        assert(strlen($data) == 16);
    
        // Set version to 0100
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        // Set bits 6-7 to 10
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    
        // Output the 36 character UUID.
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
    
    
    public function receipt_page($order_id)
    {
        echo '<p>'.__("Thank you for your order. We are redirecting you to payment gateway to proceed with your payment.").'</p>';
        echo $this -> generate_crezco_form($order_id);
    }
    
    /**
	     * Generate button link
	**/
	public function generate_crezco_form($order_id){
        global $woocommerce;
        
		
		// Get this Order's information so that we know
		// who to charge and how much
		$customer_order = new WC_Order( $order_id );
		$currency       = $customer_order->get_currency();
		$order_total    = $customer_order->get_total();  
        $successURL     = site_url() . '/wc-api/approved_'.$this->id.'/?status=success&oid='.$order_id;
        $failureURL     = site_url() . '/wc-api/approved_'.$this->id.'/?status=failed&oid='.$order_id;
        $wp_user_id     = get_current_user_id();


        foreach ($customer_order->get_items() as $item_id => $item ) 
        {
            $product        = $item->get_product();
            $line_items[] = [
                "name"          => $item->get_name(),
                "amount"        => $product->get_price()*100,
                "currency"      => get_woocommerce_currency(),
                "quantity"      => $item->get_quantity()
            ];

        }

        $curl = curl_init();

        $myuuid = $this->guidv4();

        $data = '{
            "idempotencyId": "'.$myuuid.'",
            "request": {
                "eMail": "'.$customer_order->get_billing_email().'",
                "firstName": "'.$customer_order->get_billing_first_name().'",
                "lastName": "'.$customer_order->get_billing_last_name().'",
                "displayName": "'.$customer_order->get_billing_first_name().' '.$customer_order->get_billing_last_name().'"
                }
        }';

        curl_setopt_array($curl, array(
        CURLOPT_URL => $this->apiURL.'/users',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'Accept: application/json',
            'X-Crezco-Key: '.$this->apiKey
        ),
        ));

        $response = curl_exec($curl);

        if (curl_errno($curl)) {
            $error_msg = curl_error($curl);
        }
		curl_close($curl);

        if (isset($error_msg)) 
        {
            //wp_remote_error
            $customer_order->add_order_note( __( ' Error :'. $error_msg ));
            wc_add_notice( $error_msg, 'error' );
        }
        else
        {
            $user_id = str_replace('"','',$response);

            $log['_cz_user']= ['idempotencyId' => $myuuid, 'id' => $user_id];
            
            update_user_meta($wp_user_id, '_crezco_user_id', $user_id);
    
            if($this->env == "sandbox")
                $user_id = $this->crezco_user_id_sandbox;
            
            if($this->env == "live")
                $user_id = $this->crezco_user_id_live;

            if($user_id)
            {
                

                $log['_cz_user_create']= ['payload_url' => $this->apiURL.'/users', 'payload' => $data, 'idempotencyId' => $myuuid, 'payload_response_user_id' => $user_id, 'payload_response' => $response];

                $curl = curl_init();
                $myuuid = $this->guidv4();
                $url = $this->apiURL.'/users/'.$user_id.'/pay-demands/detail';
                
                $data = '{
                    "idempotencyId": "'.$myuuid.'",
                    "request": {
                        "amount": "'.$order_total.'",
                        "currency": "'.$customer_order->get_currency().'",
                        "useDefaultBeneficiaryAccount": true,
                        "reference": "'.$order_id.'",
                        "dueDate": "'.date('Y-m-d').'"
                    }
                }';

                /*
                "payeeName": "'.$this->payeeName.'",
                        "iban": "'.$this->payeeiBan.'",
                        "bban": {
                        "sortCode": "'.$this->payeeSortCode.'",
                        "accountNumber": "'.$this->payeeAccountNumber.'"
                        },
                */

                curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => $data,
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'X-Crezco-Key: '.$this->apiKey
                ),
                ));

                $response = curl_exec($curl);

                if (curl_errno($curl)) {
                    $error_msg = curl_error($curl);
                }
                curl_close($curl);
        
                if (isset($error_msg)) 
                {
                    //wp_remote_error
                    $customer_order->add_order_note( __( ' Error :'. $error_msg ));
                    wc_add_notice( $error_msg, 'error' );
                }
                else
                {
                
                    $body = json_decode($response);
                    //var_dump($body);
                    $payment_id = $body->payDemandId;
                   
                    $log['_cz_pay_demand']= ['payload_url' => $url, 'payload' => $data, 'idempotencyId' => $myuuid, 'payload_response_payment_id' => $payment_id, 'payload_response' => $body];
                    
                    if($payment_id)
                    {
                        
                        update_post_meta($order_id, '_crezco_payment_id', $payment_id);

                        $curl = curl_init();
                        
                        echo $url = $this->apiURL.'/users/'.$user_id.'/pay-demands/'.$payment_id.'/payment?payerEmail='.$customer_order->get_billing_email().'&initialScreen=BankSelection&finalScreen=PaymentStatus&successCallbackUri='.urlencode($successURL).'&failureCallbackUri='.urlencode($failureURL);
                        
                        $data = ['initialScreen' => 'BankSelection', 'finalScreen' => 'PaymentStatus', 'successCallbackUri' => $successURL, 'failureCallbackUri' => $failureURL];
                        
                        curl_setopt_array($curl, array(
                        CURLOPT_URL => $url,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => '',
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 0,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => 'POST',
                        CURLOPT_POSTFIELDS => '{}',
                        CURLOPT_HTTPHEADER => array(
                            "Content-Type: application/json",
                            'X-Crezco-Key: '.$this->apiKey
                        ),
                        ));

                        $response = curl_exec($curl);

                        if (curl_errno($curl)) {
                            $error_msg = curl_error($curl);
                        }
                        curl_close($curl);
                
                        if (isset($error_msg)) 
                        {
                            //wp_remote_error
                            $customer_order->add_order_note( __( ' Error :'. $error_msg ));
                            wc_add_notice( $error_msg, 'error' );
                        }
                        else
                        {
                        
                            $body = json_decode($response);
                            
                            $payment_url = $body->redirect;
                            
                            $log['_cz_pay_demand_custom'] = ['payload_url' => $url, 'payload' => $data, 'payload_response_payment_url' => $payment_url, 'payload_response' => $body];

                            update_post_meta($order_id, '_cz_request', $log);
                            
                            if($payment_url):
                                        
                                        $customer_order->add_order_note( __( ' URL: '.$payment_url, 'woo-crezco' ) );
                                        
                                        // Redirect to payment page
                                        wp_redirect($payment_url);

                                        exit;
                                        
                                        
                            else:
                                            
                                            wc_add_notice( "Something Went Wrong!!", 'error' );
                                            return false;
                
                            endif;

                        }
                    }

                }


            }

            update_post_meta($order_id, '_cz_request', $log);

        }
           

    }

	// Submit payment and handle response
	public function process_payment( $order_id ) {
        
            $order = new WC_Order($order_id);
			return array(
				'result' 	=> 'success',
				'redirect'	=> $order->get_checkout_payment_url( true )
			);

	}
        
	// Check if we are forcing SSL on checkout pages
	// Custom function not required by the Gateway
	public function do_ssl_check() {
		if( $this->force_ssl == "yes" ) {
			if( get_option( 'woocommerce_force_ssl_checkout' ) == "no" ) {
				echo "<div class=\"error\"><p>". sprintf( __( "<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href=\"%s\">forcing the checkout pages to be secured.</a>" ), $this->method_title, admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) ."</p></div>";	
			}
		}		
    }
    
} // End of Banked Class