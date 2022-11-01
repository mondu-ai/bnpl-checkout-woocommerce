<div class='wrap'>
  <h1><?php _e('Mondu Settings', 'mondu'); ?></h1>
  <?php settings_errors(); ?>
    <form method='post' action='options.php'>
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
      <p> ✅ <?php _e('Credentials validated','mondu'); ?>:
        <?php echo date_i18n(get_option('date_format'), $credentials_validated); ?>
      </p>
    <?php endif; ?>
    <form method='post'>
      <?php
        wp_nonce_field('validate-credentials', 'validate-credentials');
        submit_button(__('Validate Credentials', 'mondu'));
      ?>
    </form>
    <h2><?php _e('Register Webhooks', 'mondu'); ?></h2>
    <?php if (isset($webhooks_error) && $webhooks_error !== null): ?>
      <p><?php echo $webhooks_error; ?></p>
    <?php endif; ?>
    <?php if (isset($webhooks_registered) && $webhooks_registered !== false): ?>
      <p> ✅ <?php _e('Webhooks registered','mondu'); ?>:
        <?php echo date_i18n(get_option('date_format'), $webhooks_registered); ?>
      </p>
    <?php endif; ?>
    <form method='post'>
      <?php
        wp_nonce_field('register-webhooks', 'register-webhooks');
        submit_button(__('Register Webhooks', 'mondu'));
      ?>
    </form>
    <h2><?php _e('Download Logs', 'mondu'); ?></h2>
    <form action='<?php echo get_option('siteurl'); ?>/wp-admin/admin-post.php?action=download_logs' method='post'>
      <input type='hidden' name='action' value='download_logs' />
      <label for='date'><?php _e('Log date', 'mondu'); ?>:</label>
      <input type='date' id='date' name='date' value="<?php echo date('Y-m-d'); ?>" required />
      <?php submit_button(__('Download Logs', 'mondu')); ?>
    </form>
  <?php ?>
</div>
