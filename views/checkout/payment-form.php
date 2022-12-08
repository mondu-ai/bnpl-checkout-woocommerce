<script>
  var checkMonduMount = false;
  var result = '';
  var url = '<?php echo get_site_url(null, '/index.php'); ?>';

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
    return ['mondu_invoice', 'mondu_direct_debit', 'mondu_installment'].includes(currentGateway);
  }

  function payWithMondu() {
    if (checkMonduMount) {
      return false;
    }

    checkMonduMount = true;

    jQuery.ajax({
      type: 'POST',
      url: `${url}?rest_route=/mondu/v1/orders/create`,
      success: function(res) {
        if (!res['error']) {
          let token = res['token'];
          jQuery('#mondu_order_id').val(token);
          renderWidget(token);
          return true;
        } else {
          monduUnblock();
          jQuery([document.documentElement, document.body]).animate({
            scrollTop: jQuery('.woocommerce-error').offset().top - 100
          }, 500);
          return false;
        }
      },
      fail: function(err) {
        return false;
      }
    });
  }

  function renderWidget(token) {
    window.monduCheckout.render({
      token,
      onClose: () => {
        checkMonduMount = false;
        monduUnblock();
        checkoutCallback();
        result = '';
        //because the witget does .onClose().then ...
        return new Promise((resolve) => resolve('ok'))
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
    if (result == 'success') {
      jQuery('#place_order').parents('form').submit();
    } else {
      jQuery(document.body).trigger('wc_update_cart');
      jQuery(document.body).trigger('update_checkout');
      window.monduCheckout.destroy();
    }
  }

  jQuery(document).ready(function () {
    jQuery(document.body).on('checkout_error', function () {
      isGatewayMondu(jQuery('input[name=payment_method]:checked').val()) && (result = 'error');
    });
    jQuery('form.woocommerce-checkout').on('checkout_place_order', function (e) {
      if (result !== 'success' && isGatewayMondu(jQuery('input[name=payment_method]:checked').val())) {
        monduBlock();
        payWithMondu();
        // woocommerce stops checkout process if checkout_place_order returns false
        return false;
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

<input id='mondu_order_id' value="<?php echo WC()->session->get('mondu_order_id'); ?>" hidden />
<p>
  <?php
    printf(wp_kses(__('Hinweise zur Verarbeitung Ihrer personenbezogenen Daten durch die Mondu GmbH finden Sie <a href="https://mondu.ai/de/datenschutzgrundverordnung-kaeufer" target="_blank">hier</a>.', 'mondu'), array('a' => array('href' => array(), 'target' => array()))));
  ?>
</p>
