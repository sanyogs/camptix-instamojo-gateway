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
			'mobile-no' => '',
			
			'sandbox' => true,
		), $this->get_payment_options() );

		// IPN Listener
		add_action( 'template_redirect', array( $this, 'template_redirect' ) );
	}

	function payment_settings_fields() {
		

		// code change by me start
		$this->add_settings_field_helper( 'Instamojo-Api-Key', 'Instamojo Api KEY', array( $this, 'field_text' ) );
		$this->add_settings_field_helper( 'Instamojo-Auth-Token', 'Instamojo Auth Token', array( $this, 'field_text' ) );
		$this->add_settings_field_helper( 'mobile-no', 'Mobile Field Name', array( $this, 'field_text' ) );
		
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
		if ( isset( $input['mobile-no'] ) )
			$output['mobile-no'] = $input['mobile-no'];
	
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
		$payment_id = ( isset( $_REQUEST['payment_id'] ) ) ? trim( $_REQUEST['payment_id'] ) : '';
		
		if ( empty( $payment_token ) )
			return;
		 // if(!empty($payment_id)){
		 // 	$this->payment_result( $_REQUEST['tix_payment_token'], CampTix_Plugin::PAYMENT_STATUS_COMPLETED );

		 // }else{
			// $this->payment_result( $_REQUEST['tix_payment_token'], CampTix_Plugin::PAYMENT_STATUS_PENDING );		 	
		 // }

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

		/*
		Basic PHP script to handle Instamojo RAP webhook.
		*/

		$data = $_POST;
		$mac_provided = $data['mac'];  // Get the MAC from the POST data
		unset($data['mac']);  // Remove the MAC key from the data.
		$ver = explode('.', phpversion());
		$major = (int) $ver[0];
		$minor = (int) $ver[1];
		if($major >= 5 and $minor >= 4){
		     ksort($data, SORT_STRING | SORT_FLAG_CASE);
		}
		else{
		     uksort($data, 'strcasecmp');
		}
		// You can get the 'salt' from Instamojo's developers page(make sure to log in first): https://www.instamojo.com/developers
		// Pass the 'salt' without <>
		$mac_calculated = hash_hmac("sha1", implode("|", $data), "<YOUR_SALT>");
		// if($mac_provided == $mac_calculated){
		    if($data['status'] == "Credit"){
		        // Payment was successful, mark it as successful in your database.
		        // You can acess payment_request_id, purpose etc here. 
		        $this->payment_result( $_REQUEST['tix_payment_token'], CampTix_Plugin::PAYMENT_STATUS_COMPLETED );
		    }
		    else{
		        // Payment was unsuccessful, mark it as failed in your database.
		        // You can acess payment_request_id, purpose etc here.
		        $this->payment_result( $_REQUEST['tix_payment_token'], CampTix_Plugin::PAYMENT_STATUS_PENDING );
		    }
		// }
		// else{
		    // $this->payment_result( $_REQUEST['tix_payment_token'], CampTix_Plugin::PAYMENT_STATUS_COMPLETED );
		// }

		$instamojo_key = $this->options['Instamojo-Api-Key'];
		$instamojo_token = $this->options['Instamojo-Auth-Token'];
		$mobile_no = $this->options['mobile-no'];


		 // if(isset($_REQUEST['payment_id'])){
		 // 	$this->payment_result( $_REQUEST['payment_id'], CampTix_Plugin::PAYMENT_STATUS_COMPLETED );

		 // }
	
	
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
		$mobile_no 	= $this->options['mobile-no'];
		
		
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
				$tix_id = get_post( get_post_meta( $attendee->ID, 'tix_ticket_id', true ) );
			$attendee_questions = get_post_meta( $attendee->ID, 'tix_questions', true ); // Array of Attendee Questons
$j=0;
		$k=0;
		$g=0;
		for($i=0; $i<strlen($mobile_no); $i++){
			if($mobile_no[$i] == "[")
			{
				$j++;

			}
			if($mobile_no[$i] == "]")
			{
				$k++;

			}
			if($j==2)
			{
				$openpos=$i;
				$sub = substr($mobile_no, $i+1,strlen($mobile_no));
    			$mobile_no_fieldid = substr($sub,0,strpos($sub,"]"));
				
				$j=0;
			}
			if($k==2)
			{
				$closepos=$i;
				$k=0;
			}
		
		}
		
			 if( $mobile_no != '' ) { // Check if Setup for Mobile is set?

				$attendee_info_mobile = $attendee_questions[$mobile_no_fieldid];

			 } else {
			 	$attendee_info_mobile = '';
			}
			
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
			    'phone' => $attendee_info_mobile,
			    'buyer_name' =>$name,
			    'redirect_url' => $return_url,
			    'send_email' => false,
			    'webhook' => $notify_url,
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