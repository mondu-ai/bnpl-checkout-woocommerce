<?php
if ( !isset($order_id) ) {
	$order_id = '';
}

/**
 * Get locale
 *
 * @since 1.3.2
 */
$lang = apply_filters('mondu_order_locale', get_locale());
?>

<script>
	var shouldShowMondu = true;
	var orderpay = false;
	var result = '';
	var url = '<?php echo esc_html(get_site_url(null, '/index.php')); ?>';

	function monduBlock() {
		shouldShowMondu = false;
		checkoutForm().block({
			message: null,
			overlayCSS: {
			background: '#fff',
			opacity: 0.6
			}
		});
	}

	function monduUnblock() {
		checkoutForm().unblock();
		shouldShowMondu = true;
	}

	function isGatewayMondu(currentGateway) {
		return ['mondu_invoice', 'mondu_direct_debit', 'mondu_installment'].includes(currentGateway);
	}

	function isMondu() {
		return isGatewayMondu(jQuery('input[name=payment_method]:checked').val());
	}

	function checkoutForm() {
		if (orderpay === true)
			return jQuery('form#order_review');
		else
			return jQuery('form.woocommerce-checkout');
		}

	function payWithMondu() {
		if (shouldShowMondu === false) {
			return;
		}
		monduBlock();
		if (orderpay === true) {
			orderId = <?php echo esc_html($order_id); ?>;
			data = checkoutForm().serialize() + "&orderpay=true" + "&order_id=" + orderId;
		} else {
			data = checkoutForm().serialize();
		}
		var lang = '<?php echo esc_html($lang); ?>'
		data += '&lang=' + lang;

		jQuery.ajax({
			type: 'POST',
			url: `${url}?rest_route=/mondu/v1/orders/create`,
			data: data,
			dataType: 'json',
			success: function(res) {
				// TODO: check refresh and reload
				if (res['token']) {
					let token = res['token'];
					renderWidget(token);
				} else {
					monduUnblock();
					handleErrors(res.errors);
				}
			},
			fail: function(err) {
				handleErrors();
			}
		});
	}

	function renderWidget(token) {
		window.monduCheckout.render({
			token,
			onClose: () => {
				monduUnblock();
				checkoutCallback();
				return new Promise((resolve) => resolve())
			},
			onSuccess: () => {
				console.log('Success');
				result = 'success';
				return new Promise((resolve) => resolve())
			},
			onError: (err) => {
				console.log('Error occurred', err);
				result = 'error';
				return new Promise((resolve) => resolve())
			},
		});
	}

	function checkoutCallback() {
		if (result === 'success') {
			checkoutForm().submit();
		} else {
			jQuery(document.body).trigger('wc_update_cart');
			jQuery(document.body).trigger('update_checkout');
			window.monduCheckout.destroy();
		}
	}

	function handleErrors(errorMessage = null) {
	var form = checkoutForm();

	if (!errorMessage) {
		errorMessage =
			'<div class="woocommerce-error">' +
			'<?php echo esc_html__('Error processing checkout. Please try again.', 'mondu'); ?>' +
			'</div>';
	}

	// from woocommerce checkout.js submit_error function line 570
	jQuery('.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message').remove();
	form.prepend('<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' + errorMessage + '</div>');
	form.removeClass('processing').unblock();
	form.find('.input-text, select, input:checkbox').trigger('validate').trigger('blur');

	var scrollElement = jQuery('.woocommerce-NoticeGroup-updateOrderReview, .woocommerce-NoticeGroup-checkout');
	jQuery.scroll_to_notices(scrollElement);

	jQuery(document.body).trigger('checkout_error', [errorMessage]);
	}

	jQuery(document).ready(function () {
	if (jQuery(document.body).hasClass('woocommerce-order-pay')) {
		orderpay = true;

		jQuery('form#order_review').on('submit', function (e) {
			if (!isMondu()) return;
			if (result ==='success') return;

			if (shouldShowMondu === true) payWithMondu();

			e.preventDefault();
		});
	} else {
		jQuery('form.woocommerce-checkout').on('checkout_place_order', function (e) {
			if (!isMondu()) return true;
			if (result ==='success') return true;

			if (shouldShowMondu === true) payWithMondu();

			return false;
		});
	}
	});
</script>

<p>
	<?php
	printf(wp_kses(__('Information on the processing of your personal data by <strong>Mondu GmbH</strong> can be found <a href="https://mondu.ai/gdpr-notification-for-buyers" target="_blank">here</a>.', 'mondu'), array(
		'a' => array(
			'href'   => array(),
			'target' => array(), 
		),
	)));
	?>
</p>
