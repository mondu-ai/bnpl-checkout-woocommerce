<script>
  jQuery(document).ready(function($) {
    $('#mondu-cancel-invoice-button').on('click', function (e) {
      e.preventDefault();

      if(confirm('Are you sure you want to cancel the invoice? This action can not be undone')) {
        var attributes = JSON.parse($('#mondu-cancel-invoice-button')[0].attributes['data-mondu'].value);

        jQuery.ajax({
          type: 'POST',
          url: ajaxurl,
          data: {
            action: 'cancel_invoice',
            ...attributes
          },
          success: function(res) {
            if(res.error) {
              alert(res.message);
            } else {
              location.reload();
            }
          },
          fail: function() {
            // location.reload();
          }
        });
      }
    });
    $('#mondu-create-invoice-button').on('click', function (e) {
      e.preventDefault();

      if(confirm('Are you sure you want to create the invoice?')) {
        var attributes = JSON.parse($('#mondu-create-invoice-button')[0].attributes['data-mondu'].value);

        jQuery.ajax({
          type: 'POST',
          url: ajaxurl,
          data: {
            action: 'create_invoice',
            ...attributes
          },
          success: function(res) {
            if(res.error) {
              alert(res.message);
            } else {
              location.reload();
            }
          },
          fail: function() {
            // location.reload();
          }
        });
      }
    })
  });
</script>
