<?php
/**
 * Plugin Name: CampTix Instamojo Payment Gateway
 * Plugin URI: https://www.sanyog.in/instamojo
 * Description: Simple and Flexible payment ticketing for Camptix using Instamojo Indian Payment Gateway
 * Author: codexdemon
 * Author URI: http://www.sanyog.in/
 * Version: 1.0
 * License: GPLv2 or later
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Add INR currency
add_filter( 'camptix_currencies', 'camptix_add_inr_currency' );
function camptix_add_inr_currency( $currencies ) {
	$currencies['INR'] = array(
		'label' => __( 'Indian Rupees', 'camptix' ),
		'format' => 'Rs. %s',
	);
	return $currencies;
}

// Load the Instamojo Payment Method
add_action( 'camptix_load_addons', 'camptix_instamojo_load_payment_method' );
function camptix_instamojo_load_payment_method() {
	if ( ! class_exists( 'CampTix_Payment_Method_Instamojo' ) )
		require_once plugin_dir_path( __FILE__ ) . 'classes/class-camptix-payment-method-instamojo.php';
	camptix_register_addon( 'CampTix_Payment_Method_Instamojo' );
}

?>