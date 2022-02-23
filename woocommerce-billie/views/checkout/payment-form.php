<script>
  var checkBillieMount = false;

  function billieBlock() {
    jQuery('.woocommerce-checkout-payment, .woocommerce-checkout-review-order-table').block({
      message: null,
      overlayCSS: {
        background: '#fff',
        opacity: 0.6
      }
    });
  }

  function billieUnblock() {
    jQuery('.woocommerce-checkout-payment, .woocommerce-checkout-review-order-table').unblock();
    checkBillieMount = false;
  }

  function isGatewayBillie(currentGateway) {
    return currentGateway === 'billie';
  }

  function payWithMondu() {
    if (checkBillieMount) {
      return;
    }
    checkBillieMount = true;

    jQuery.ajax({
      type: 'POST',
      url: 'http://localhost:8080/index.php?rest_route=/mondu/v1/create_order',
      success: function(res) {
        console.log('Token: ' + res['token']);
        renderWidget(res['token']);
        return true;
      },
      fail: function(err) {
        return false;
      }
    });
  }

  function renderWidget(token) {
    window.monduCheckout.render({
      token: token,
      onClose: () => {
        checkBillieMount = false;
        <?php WC()->session->set( 'mondu_order_id', null ); ?>
      },
      onSuccess: () => {
        // jQuery.post('<?php echo \Billie\Plugin::get_callback_url( 'ajax-billie-success' ); ?>', ao, function () {
        //   jQuery('form.woocommerce-checkout').off('checkout_place_order');
        //   if (jQuery('#confirm-order-flag').length !== 0) {
        //     jQuery('#confirm-order-flag').val('');
        //   }

          jQuery('#place_order').parents('form').submit();
        // });
        // billieBlock()
      },
      onError: () => {
      //   jQuery.post('<?php echo \Billie\Plugin::get_callback_url( 'ajax-billie-error' ); ?>', err, function () {
      //     jQuery(document.body).trigger('wc_update_cart');
      //     jQuery(document.body).trigger('update_checkout');
      //   });
      //   console.log('Error occurred', err);
      //   billieUnblock();

      //   location.reload();
      },
    });
  }

  jQuery(document).ready(function () {
    jQuery(document.body).on('checkout_error', function () {
      let error_count = jQuery('.woocommerce-error li').length;

      jQuery('.woocommerce-error li').each(function () {
        let error_text = jQuery(this).text();
        if (error_text.includes('error_confirmation')) {
          jQuery(this).css('display', 'none');

          if (error_count === 1) {
            jQuery(this).parent().css('display', 'none');

            if (isGatewayBillie(jQuery('input[name=payment_method]:checked').val())) {
              jQuery('html, body').stop();
            }
          }
        }
      });

      if (error_count === 1 || error_count === 0) {
        let result = true;
        if (isGatewayBillie(jQuery('input[name=payment_method]:checked').val())) {
          billieBlock();
          result = payWithMondu();
          jQuery('html, body').stop();
        }

        if (result === true) billieUnblock();
      }
    });

    jQuery('form.woocommerce-checkout').on('checkout_place_order', function () {
      if (isGatewayBillie(jQuery('input[name=payment_method]:checked').val())) {
        if (jQuery('#confirm-order-flag').length === 0) {
          jQuery('form.woocommerce-checkout').append('<input type="hidden" id="confirm-order-flag" name="confirm-order-flag" value="1">');
        }
      } else if (jQuery('#confirm-order-flag').length === 1) {
        jQuery('#confirm-order-flag').val(0);
      }

      return true;
    });
  });
</script>
<style>
  #checkout_mondu_logo {
    max-height: 1em;
  }
</style>
<p>
  <?php if ( ! isset( $this->settings['hide_logo'] ) || $this->settings['hide_logo'] === 'no' ): ?>
    <img id="checkout_mondu_logo" src="<?php echo plugin_dir_url( __DIR__ ); ?>/mondu.svg" alt="Mondu">
  <?php endif; ?>
  <?php echo nl2br( $this->method_description ); ?>
</p>
