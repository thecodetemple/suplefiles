<?php
// Add custom Theme Functions here

add_filter( 'woocommerce_currencies', 'add_my_currency' );

function add_my_currency( $currencies ) {
     $currencies['MXN'] = __( 'Currency name', 'woocommerce' );
     return $currencies;
}

add_filter('woocommerce_currency_symbol', 'add_my_currency_symbol', 10, 2);

function add_my_currency_symbol( $currency_symbol, $currency ) {
     switch( $currency ) {
          case 'MXN': $currency_symbol = '$MXN'; break;
     }
     return $currency_symbol;
}


//SHOW CONEKTA CAPTURE PAYMENT BUTTON IN ADMIN
add_filter( 'woocommerce_admin_order_actions', 'add_cancel_order_actions_button', PHP_INT_MAX, 2 );
function add_cancel_order_actions_button( $actions, $the_order ) {
	if ( (get_post_meta($the_order->id, 'conekta-order-id', true))){
	\Conekta\Conekta::setApiKey("key_cqfsaprTuxBGDkSy4BimCg");
	$order = \Conekta\Order::find(get_post_meta($the_order->id, 'conekta-order-id', true));
	$payment_super_status = $order->payment_status;
	    if ( (get_post_meta($the_order->id, 'conekta-order-id', true)) && ($payment_super_status == 'pre_authorized') ) { // if order is not cancelled yet...
		            $actions['my_ajax'] = array(
		'url'       => wp_nonce_url( admin_url( 'admin-ajax.php?action=my_ajax&super_order_id=' . get_post_meta($the_order->id, 'conekta-order-id', true) ), 'my_ajax' ),
		'name'      => __( 'Capturar Pago', 'woocommerce' ),
		'action'    => "capture", // setting "view" for proper button CSS
		);
	    }
	}
	return $actions;
	
}
add_action( 'admin_head', 'add_cancel_order_actions_button_css' );
function add_cancel_order_actions_button_css() {
	echo '<style>.capture { background-color:green;color:white; }</style>';
}


//SEND CAPTURE REQUEST TO CONKETA ONCE THE BUTTON IS PRESSED

add_action( 'wp_ajax_my_ajax', 'my_ajax' );

function my_ajax() {
	try {
	
	\Conekta\Conekta::setApiKey("key_cqfsaprTuxBGDkSy4BimCg");
	$order = \Conekta\Order::find($_GET['super_order_id']);
	} catch (\Conekta\ErrorList $errorList){
		  foreach($errorList->details as &$errorDetail) {
			      echo $errorDetail->getMessage();
			        }
	}	
	$order->capture();
	 wp_redirect( $_SERVER['HTTP_REFERER'] );
	    exit();

}
