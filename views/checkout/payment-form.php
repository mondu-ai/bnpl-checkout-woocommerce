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

  function isMondu() {
    return isGatewayMondu(jQuery('input[name=payment_method]:checked').val());
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
        //because the widget does .onClose().then ...
        return new Promise((resolve) => resolve('ok'))
      },
      onSuccess: () => {
        console.log('Success');
        result = 'success';
        return new Promise((resolve) => resolve('ok'))
      },
      onError: (err) => {
        console.log('Error occurred', err);
        result = 'error';
        return new Promise((resolve) => resolve('ok'))
      },
    });
  }

  function checkoutCallback() {
    if (result == 'success') {
      if (jQuery('#mondu-confirm-order-flag').length !== 0) {
        jQuery('#mondu-confirm-order-flag').val('');
      }
      jQuery('#place_order').parents('form').submit();
    } else {
      jQuery(document.body).trigger('wc_update_cart');
      jQuery(document.body).trigger('update_checkout');
      window.monduCheckout.destroy();
    }
  }

  jQuery(document).ready(function () {
    jQuery(document.body).on('checkout_error', function () {
      let error_count = jQuery('.woocommerce-error li').length;

      jQuery('.woocommerce-error li').each(function () {
        let error_text = jQuery(this).text();
        jQuery(this).addClass('error');

        if (!error_text.includes('error_confirmation')) return;

        jQuery(this).css('display', 'none');

        if(error_count !== 1) return;

        jQuery(this).parent().css('display', 'none');

        if (isMondu()) {
          jQuery('html, body').stop();
        }
      });

      if (isMondu() && error_count === 1) {
        const element = jQuery('.woocommerce-error li')[0];

        if(jQuery(element).text().includes('error_confirmation')) {
          monduBlock();
          payWithMondu();
          jQuery('html, body').stop();
        }
      }
    });

    var checkout_form = jQuery('form.woocommerce-checkout');

    checkout_form.on('checkout_place_order', function (e) {
      if(!isMondu()) return true;

      if (jQuery('#mondu-confirm-order-flag').length == 0) {
        checkout_form.append('<input type="hidden" id="mondu-confirm-order-flag" name="mondu-confirm-order-flag" value="1">');
      } else if (result !=='success') {
        jQuery('#mondu-confirm-order-flag').val('1');
      }
    });
  });
</script>

<style>
  #checkout_mondu_logo {
    max-height: 1em;
  }
</style>

<p>
  <?php
    printf(wp_kses(__('Hinweise zur Verarbeitung Ihrer personenbezogenen Daten durch die Mondu GmbH finden Sie <a href="https://mondu.ai/de/datenschutzgrundverordnung-kaeufer" target="_blank">hier</a>.', 'mondu'), array('a' => array('href' => array(), 'target' => array()))));
  ?>
</p>
