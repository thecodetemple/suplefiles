<?php
/**
 * Admin new order email
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/admin-new-order.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see 	    https://docs.woocommerce.com/document/template-structure/
 * @author WooThemes
 * @package WooCommerce/Templates/Emails/HTML
 * @version 2.5.0
 */

 if ( ! defined( 'ABSPATH' ) ) {
 	exit;
 }

 /**
  * @hooked WC_Emails::email_header() Output the email header
  */
 do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

 <p><?php printf( __( 'You have received an order from %s. The order is as follows:', 'woocommerce' ), $order->get_formatted_billing_full_name() ); ?></p>

 <?php

 /**
  * @hooked WC_Emails::order_details() Shows the order details table.
  * @hooked WC_Emails::order_schema_markup() Adds Schema.org markup.
  * @since 2.5.0
  */
 do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );

 /**
  * @hooked WC_Emails::order_meta() Shows order meta data.
  */


 /**
  * @hooked WC_Emails::customer_details() Shows customer details
  * @hooked WC_Emails::email_address() Shows email address
  */
 //do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );
 ?>
 <h2 style="">Datos y dirección del cliente:</h2>
<p><?php $superarray = $order->get_address();

    echo "<br /><b>Nombre y Apellido: </b> " . $superarray['first_name'] . " " . $superarray['last_name']; ?></p>
    <p><?php
    echo "<b>Compañía: </b> </b>" . $superarray['company']; ?></p>
       <p><?php
    echo "<b>Calle y Número Exterior: </b>" . $superarray['address_1']; ?></p>
    <p><?php
 echo "<b>Numero Interior: </b>" . $superarray['address_2']; ?></p>
 <p><?php
              
       do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );

?>
              <p>

              <?php
    echo "<b>Ciudad: </b>" . $superarray['city']; ?></p>
                  <p><?php
    echo "<b>Estado: </b>" . $superarray['state']; ?></p>
                  <p><?php
    echo "<b>Código Postal: </b>" . $superarray['postcode']; ?></p>
                        <p><?php
    echo "<b>Correo electrónico: </b>" . $superarray['email']; ?></p>
                         <p><?php
    echo "<b>Teléfono: </b>" . $superarray['phone']; ?></p>
                        <p><?php
    echo "<b>Notas del pedido: </b>" . $order->customer_note; ?></p>




<?php
 /**
  * @hooked WC_Emails::email_footer() Output the email footer
  */
 do_action( 'woocommerce_email_footer', $email );
