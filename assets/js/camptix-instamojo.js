/**
 * Give - Instamojo Popup Checkout JS
 */
var camptix_l10n, camptix_instamojo_para;

/**
 * On document ready setup Instamojo events.
 */
jQuery(document).ready(function ($) {
	// Cache donation button title early to reset it if instamojo checkout popup close.
	var instamojo_handler = [],
		$container       = $('#tix'),
		$form            = $('form', $container),
		ticket_quantity  = $('.tix_tickets_table td.tix-column-quantity', $container).text();
		
	/**
	 * Validate extra attendee information fields.
	 *
	 * @returns {boolean}
	 */
	var validate_fields = function () {
		for (var i = 1; i <= ticket_quantity; i++) {
			if (!$('input[name="tix_attendee_info[' + i + '][phone]"]', $form).val()) {
				return false;
			}
		}

		return true;
	};

	/**
	 * Show errors.
	 *
	 * @param error_html
	 */
	var show_errors = function (error_html) {
		var $errors = '';

		// Remove old errors html.
		$('#tix-errors', $container).remove();

		// Set new error html.
		$errors = $('<div id="tix-errors"></div>').html(error_html);
		$container.prepend($errors);

		// Scroll to error div.
		$('html,body').animate({
				scrollTop: $container.offset().top
			},
			'slow'
		);
	};

	/**
	 * Show/ Hide extra attendee fields.
	 *
	 * @param show
	 */
	var show_custom_attendee_fields = function (show) {
		var $field_container;

		for (var i = 1; i <= 2; i++) {
			$field_container = $('input[name="tix_attendee_info[' + i + '][phone]"]', $form).closest('tr');

			if (show) {
				$field_container.show();
			} else {
				$field_container.hide();
			}
		}
	};

	/**
	 * Show extra attendee fields only if instamojo selected
	 */
	$('select[name="tix_payment_method"]', $form).on('change', function () {
		// var payment_gateway = $(this).val()
		// // Bailout.
		// if (camptix_instamojo_para.gateway_id === $('select[name="tix_payment_method"]', $form).val() && payment_gateway != 'instamojo') {
		// 	show_custom_attendee_fields(true);
		// } else {
		// 	show_custom_attendee_fields(false);
		// }
	}).change();

	/**
	 * Increase instamojo's z-index to appear above Give's modal.
	 */
	$('.instamojo-container').css('z-index', '2147483543');

	/**
	 * On form submit prevent submission for Instamojo only.
	 */
	$form.on('submit', function (e) {


		var phone = $('.mobile').val();
        console.log(phone);
        phone = phone.replace(/[^0-9]/g,'');

        if(!($.isNumeric(phone))){
        	console.log('test');
            //alert('Phone number must be 10 digits.');
            $('.message').text('Please Enter Only Numbers');
            $('.message').css('color','red')
            
            $('.mobile').val('');
            $('.mobile').focus();
            //alert();
            e.preventDefault();

		return false;


        }else
        if (phone.length != 10 )
        {
        	console.log('test');
            //alert('Phone number must be 10 digits.');
            $('.message').text('Please Enter 10 Digit Number');
            $('.message').css('color','red')
            
            $('.mobile').val('');
            $('.mobile').focus();
            //alert();
            e.preventDefault();

		return false;

        } 
     
		// Bailout.
		if (camptix_instamojo_para.gateway_id !== $('select[name="tix_payment_method"]', $form).val()) {
			return true;
		}
	//$cf = $('.mobile');
        

		e.preventDefault();

		return false;
	});

	/**
	 * When the submit button is clicked.
	 */
	$form.on('click touchend', 'input[type="submit"]', function (e) {
		// Bailout.
		if (camptix_instamojo_para.gateway_id !== $('select[name="tix_payment_method"]', $form).val()) {
			return true;
		}

		e.preventDefault();

		// Validate custom attendee information fields.
		if (!validate_fields()) {
			show_errors('<div class="tix-error">' + camptix_instamojo_para.errors.phone + '</div>');

			return false;
		}

		var $submit_button = $(this),
			$response;

		$.post($form.attr('action'), $form.serialize())
			.done(function (response) {
				// Bailout.
				if (!response.success) {
					var $el = $('<div></div>').html(response);

					show_errors($('#tix-errors', $el).html());

					return false;
				}

				// Cache response for internal use in Instamojo.
				$response = response;

				instamojo_handler = new Instamojo({
					'key'     : camptix_instamojo_para.merchant_key_id,
					//'order_id': order_id,
					'name'    : $response.data.popup_title,
					'image'   : camptix_instamojo_para.popup.image,
					// 'description' : '',
					'handler' : function (response) {
						// Remove loading animations.
						// $form.find('.give-loading-animation').hide();
						// Disable form submit button.
						$submit_button.prop('disabled', true);

						// Submit form after charge token brought back from Instamojo.
						// Redirect to success page.
						window.location.assign($response.data.return_url + '&transaction_id=' + order_id + '&receipt_id=' + receipt_id);
					},

					// You can add custom data here and fields limited to 15.
					// 'notes': {
					// 'extra_information' : $response.data
					// },
					'prefill': {
						'name'   : $response.data.fullname,
						'email'  : $response.data.email,
						'contact': $response.data.phone
					},

					// 'modal': {
					// 	'ondismiss': function () {
					// 		// Remove loading animations.
					// 		$form.find('.give-loading-animation').hide();

					// 		// Re-enable submit button and add back text.
					// 		$submit_button.prop('disabled', false);
					// 	}
					// },

					// 'theme': {
					// 	'color'        : camptix_instamojo_para.popup.color,
					// 	'image_padding': false
					// }
				});

				instamojo_handler.open();
			})
			.fail(function () {
			})
			.always(function () {
				// Enable form submit button.
				$submit_button.prop('disabled', false);
			});

		return false;
	});
});
