<div class="wrap">
  <h1><?php _e('Mondu Settings', 'mondu'); ?></h1>
  <?php settings_errors(); ?>
    <form method="post" action="options.php">
      <?php
        settings_fields('mondu');
        do_settings_sections('mondu-settings-account');
        submit_button();
      ?>
    </form>
    <h2><?php _e('Validate Credentials', 'mondu'); ?></h2>
    <?php if (isset($validation_error) && $validation_error !== null): ?>
      <p><?php echo $validation_error; ?></p>
    <?php endif; ?>
    <?php if (isset($credentials_validated) && $credentials_validated !== false): ?>
      <p> ✅ <?php _e('Credentials validated:','mondu');?>
          <?php echo date_i18n(get_option('date_format'), $credentials_validated); ?>
      </p>
    <?php endif; ?>
    <form method="post">
      <?php
        wp_nonce_field('validate-credentials', 'validate-credentials');
        submit_button(__('Validate Credentials', 'mondu'));
      ?>
    </form>
  <?php ?>
</div>
