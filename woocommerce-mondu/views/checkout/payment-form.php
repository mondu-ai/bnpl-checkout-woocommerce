<script>
  var checkMonduMount = false;
  var result = '';
  var url = '<?php echo get_site_url( null, '/index.php' ); ?>';

  function monduBlock() {
    jQuery('.woocommerce-checkout-payment, .woocommerce-checkout-review-order-table').block({
      message: null,
      overlayCSS: {
        background: '#fff',
        opacity: 0.6
      }
    });
  }

  function monduUnblock() {
    jQuery('.woocommerce-checkout-payment, .woocommerce-checkout-review-order-table').unblock();
    checkMonduMount = false;
  }

  function isGatewayMondu(currentGateway) {
    return currentGateway === 'mondu';
  }

  function payWithMondu() {
    
    if (checkMonduMount) {
      return;
    }
    console.log(3)
    checkMonduMount = true;

    jQuery.ajax({
      type: 'POST',
      url: `${url}?rest_route=/mondu/v1/create_order`,
      success: function(res) {
        console.log(1)
        renderWidget(res['token']);
        return true;
      },
      fail: function(err) {
        console.log(2)
        return false;
      }
    });
  }

  function renderWidget(token) {
    window.monduCheckout.render({
      token: token,
      onClose: () => {
        checkMonduMount = false;
        monduUnblock();
        checkoutCallback();
        result = '';
      },
      onSuccess: () => {
        console.log('Success');
        result = 'success';
      },
      onError: (err) => {
        console.log('Error occurred', err);
        result = 'error';
      },
    });
  }

  function checkoutCallback() {
    return;
    if (result == 'success') {
      jQuery.ajax({
        type: 'POST',
        url: `${url}?rest_route=/mondu/v1/checkout_callback`,
        data: { type: 'success' },
        success: function(res) {
          jQuery('form.woocommerce-checkout').off('checkout_place_order');
          if (jQuery('#confirm-order-flag').length !== 0) {
            jQuery('#confirm-order-flag').val('');
          }

          jQuery('#place_order').parents('form').submit();
        }
      });
    } else {
      jQuery.ajax({
        type: 'POST',
        url: `${url}?rest_route=/mondu/v1/checkout_callback`,
        data: { type: 'error' },
        success: function(res) {
          jQuery(document.body).trigger('wc_update_cart');
          jQuery(document.body).trigger('update_checkout');
        }
      });

      // location.reload();
    }
  }

  jQuery(document).ready(function () {
    
      jQuery(document.body).on('checkout_error', function () {
        let error_count = jQuery('.woocommerce-error li').length;
        
          jQuery('.woocommerce-error li').each(function () {
          let error_text = jQuery(this).text();
          jQuery(this).addClass('error');
          if (error_text.includes('error_confirmation')) {
            if (error_count === 1) {

              if (isGatewayMondu(jQuery('input[name=payment_method]:checked').val())) {
                jQuery('html, body').stop();
              }
            }
          }
        });

        if (error_count === 1 || error_count === 0) {
          let result = true;
          if (isGatewayMondu(jQuery('input[name=payment_method]:checked').val())) {
            monduBlock();
            result = payWithMondu();
            jQuery('html, body').stop();
          }

          if (result === true) monduUnblock();
        }
      
    });

    jQuery('form.woocommerce-checkout').on('checkout_place_order', function () {
      if (isGatewayMondu(jQuery('input[name=payment_method]:checked').val())) {
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
  .error{
    color:#dc3545;
  }
</style>
<p>
  <?php if ( ! isset( $this->settings['hide_logo'] ) || $this->settings['hide_logo'] === 'no' ): ?>
    <img id="checkout_mondu_logo" src="<?php echo plugin_dir_url( __DIR__ ); ?>/mondu.svg" alt="Mondu">
  <?php endif; ?>
  <?php echo nl2br( $this->method_description ); ?>
</p>
