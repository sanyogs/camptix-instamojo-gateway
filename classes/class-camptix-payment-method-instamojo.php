<?php

/**
 * CampTix Instamojo Payment Method
 *
 * This class handles all Instamojo integration for CampTix
 *
 * @category	Class
 * @author 		Sanyog Shelar (codexdemon)
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class CampTix_Payment_Method_Instamojo extends CampTix_Payment_Method {
	public $id = 'instamojo';
	public $name = 'Instamojo';
	public $description = 'Redefining Payments, Simplifying Lives! Empowering any business to collect money online within minutes.';
	public $supported_currencies = array( 'INR' );

	/**
	 * We can have an array to store our options.
	 * Use $this->get_payment_options() to retrieve them.
	 */
	protected $options = array();

	function camptix_init() {
		$this->options = array_merge( array(
			
			'Instamojo-Api-Key' => '',
			'Instamojo-Auth-Token' => '',
			
			'sandbox' => true
		), $this->get_payment_options() );

		// IPN Listener
		add_action( 'template_redirect', array( $this, 'template_redirect' ) );
	}

	function payment_settings_fields() {
		

		// code change by me start
		$this->add_settings_field_helper( 'Instamojo-Api-Key', 'Instamojo Api KEY', array( $this, 'field_text' ) );
		$this->add_settings_field_helper( 'Instamojo-Auth-Token', 'Instamojo Auth Token', array( $this, 'field_text' ) );
		// code change by me end

		$this->add_settings_field_helper( 'sandbox', __( 'Sandbox Mode', 'camptix' ), array( $this, 'field_yesno' ),
			__( "The Test Mode is a way to test payments. Any amount debited from your account should be re-credited within Five (5) working days.", 'camptix' )
		);
	}

	function validate_options( $input ) {
		$output = $this->options;

		if ( isset( $input['Instamojo-Api-Key'] ) )
			$output['Instamojo-Api-Key'] = $input['Instamojo-Api-Key'];
		if ( isset( $input['Instamojo-Auth-Token'] ) )
			$output['Instamojo-Auth-Token'] = $input['Instamojo-Auth-Token'];
	
		if ( isset( $input['sandbox'] ) )
			$output['sandbox'] = (bool) $input['sandbox'];

		return $output;
	}

	function template_redirect() {
		if ( ! isset( $_REQUEST['tix_payment_method'] ) || 'instamojo' != $_REQUEST['tix_payment_method'] )
			return;

		if ( isset( $_GET['tix_action'] ) ) {
			if ( 'payment_cancel' == $_GET['tix_action'] )
				$this->payment_cancel();

			if ( 'payment_return' == $_GET['tix_action'] )
				$this->payment_return();

			if ( 'payment_notify' == $_GET['tix_action'] )
				$this->payment_notify();
		}
	}

	function payment_return() {
		global $camptix;

		$this->log( sprintf( 'Running payment_return. Request data attached.' ), null, $_REQUEST );
		$this->log( sprintf( 'Running payment_return. Server data attached.' ), null, $_SERVER );

		$payment_token = ( isset( $_REQUEST['tix_payment_token'] ) ) ? trim( $_REQUEST['tix_payment_token'] ) : '';
		if ( empty( $payment_token ) )
			return;
		 if(isset($_REQUEST['payment_id'])){
		 	$this->payment_result( $_REQUEST['tix_payment_token'], CampTix_Plugin::PAYMENT_STATUS_COMPLETED );

		 }

		$attendees = get_posts(
			array(
				'posts_per_page' => 1,
				'post_type' => 'tix_attendee',
				'post_status' => array( 'draft', 'pending', 'publish', 'cancel', 'refund', 'failed' ),
				'meta_query' => array(
					array(
						'key' => 'tix_payment_token',
						'compare' => '=',
						'value' => $payment_token,
						'type' => 'CHAR',
					),
				),
			)
		);

		if ( empty( $attendees ) )
			return;

		$attendee = reset( $attendees );

		if ( 'draft' == $attendee->post_status ) {
			return $this->payment_result( $payment_token, CampTix_Plugin::PAYMENT_STATUS_PENDING );
		} else {
			$access_token = get_post_meta( $attendee->ID, 'tix_access_token', true );
			$url = add_query_arg( array(
				'tix_action' => 'access_tickets',
				'tix_access_token' => $access_token,
			), $camptix->get_tickets_url() );

			wp_safe_redirect( esc_url_raw( $url . '#tix' ) );
			die();
		}
	}

	/**
	 * Runs when PayU Money sends an ITN signal.
	 * Verify the payload and use $this->payment_result
	 * to signal a transaction result back to CampTix.
	 */
	function payment_notify() {
		global $camptix;

		$this->log( sprintf( 'Running payment_notify. Request data attached.' ), null, $_REQUEST );
		$this->log( sprintf( 'Running payment_notify. Server data attached.' ), null, $_SERVER );

		$payment_token = ( isset( $_REQUEST['tix_payment_token'] ) ) ? trim( $_REQUEST['tix_payment_token'] ) : '';

		$payload = stripslashes_deep( $_REQUEST );

		$instamojo_key = $this->options['Instamojo-Api-Key'];
		$instamojo_token = $this->options['Instamojo-Auth-Token'];

		 if(isset($_REQUEST['payment_id'])){
		 	$this->payment_result( $_REQUEST['payment_id'], CampTix_Plugin::PAYMENT_STATUS_COMPLETED );

		 }
	
	
	}

	public function payment_checkout( $payment_token ) {

		if ( ! $payment_token || empty( $payment_token ) )
			return false;

		if ( ! in_array( $this->camptix_options['currency'], $this->supported_currencies ) )
			die( __( 'The selected currency is not supported by this payment method.', 'camptix' ) );

		$return_url = add_query_arg( array(
			'tix_action' => 'payment_return',
			'tix_payment_token' => $payment_token,
			'tix_payment_method' => 'instamojo',
		), $this->get_tickets_url() );

		$cancel_url = add_query_arg( array(
			'tix_action' => 'payment_cancel',
			'tix_payment_token' => $payment_token,
			'tix_payment_method' => 'instamojo',
		), $this->get_tickets_url() );

		$notify_url = add_query_arg( array(
			'tix_action' => 'payment_notify',
			'tix_payment_token' => $payment_token,
			'tix_payment_method' => 'instamojo',
		), $this->get_tickets_url() );
		
		$order 			= $this->get_order( $payment_token );

		$instamojo_key 	= $this->options['Instamojo-Api-Key'];
		$instamojo_token 	= $this->options['Instamojo-Auth-Token'];

		$order_amount 	= $order['total'];
		if ( isset( $this->camptix_options['event_name'] ) ) {
			$productinfo = $this->camptix_options['event_name'];
		}else{
			$productinfo = 'Ticket for Order - '.$payment_token;
		}

		$attendees = get_posts(
			array(
				'post_type'		=> 'tix_attendee',
				'post_status'	=> 'any',
				'orderby' 		=> 'ID',
				'order'			=> 'ASC',
				'meta_query' => array(
					array(
						'key' => 'tix_payment_token',
						'compare' => '=',
						'value' => $payment_token
					)
				)
			)
		);
	
		foreach ( $attendees as $attendee ) {

			$email = $attendee->tix_email;
			$name = $attendee->tix_first_name.' '.$attendee->tix_last_name;

		}

			$ch = curl_init();

			$url = $this->options['sandbox'] ? 'https://test.instamojo.com/api/1.1/payment-requests/' : 'https://www.instamojo.com/api/1.1/payment-requests/';

			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_HEADER, FALSE);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
			curl_setopt($ch, CURLOPT_HTTPHEADER,
			            array("X-Api-Key:$instamojo_key",
			                  "X-Auth-Token:$instamojo_token"));


			$payload = Array(
			    'purpose' => $productinfo,
			    'amount' => $order_amount,
			    'phone' => $phone,
			    'buyer_name' =>$name,
			    'redirect_url' => $return_url,
			    'send_email' => false,
			    'webhook' => '',
			    'send_sms' => false,
			    'email' => $email,
			    'allow_repeated_payments' => false
			);


			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
			$response = curl_exec($ch);
			curl_close($ch); 

			$json_decode = json_decode($response , true);

			$long_url = $json_decode['payment_request']['longurl'];
			header('Location:'.$long_url);

		return;
	}

	/**
	 * Runs when the user cancels their payment during checkout at PayPal.
	 * his will simply tell CampTix to put the created attendee drafts into to Cancelled state.
	 */
	function payment_cancel() {
		global $camptix;

		$this->log( sprintf( 'Running payment_cancel. Request data attached.' ), null, $_REQUEST );
		$this->log( sprintf( 'Running payment_cancel. Server data attached.' ), null, $_SERVER );

		$payment_token = ( isset( $_REQUEST['tix_payment_token'] ) ) ? trim( $_REQUEST['tix_payment_token'] ) : '';

		if ( ! $payment_token )
			die( 'empty token' );
		// Set the associated attendees to cancelled.
		return $this->payment_result( $payment_token, CampTix_Plugin::PAYMENT_STATUS_CANCELLED );
	}
}
?>