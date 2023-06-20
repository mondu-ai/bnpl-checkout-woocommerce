<div class='wrap'>
	<h1><?php esc_html_e('Mondu Settings', 'mondu'); ?></h1>
	<?php settings_errors(); ?>
	<form method='post' action='options.php'>
		<?php
			settings_fields('mondu');
			do_settings_sections('mondu-settings-account');
			submit_button();
		?>
	</form>
	<h2><?php esc_html_e('Validate Credentials', 'mondu'); ?></h2>
	<?php if ( isset($validation_error) && null !== $validation_error ) : ?>
		<p><?php echo esc_html($validation_error); ?></p>
	<?php endif; ?>
	<?php if ( isset($credentials_validated) && false !== $credentials_validated ) : ?>
		<p> ✅ <?php esc_html_e('Credentials validated', 'mondu'); ?>:
		<?php echo esc_html(date_i18n(get_option('date_format'), $credentials_validated)); ?>
		</p>
	<?php endif; ?>
	<form method='post'>
		<?php
		wp_nonce_field('validate-credentials', 'validate-credentials');
		submit_button(__('Validate Credentials', 'mondu'));
		?>
	</form>
	<h2><?php esc_html_e('Register Webhooks', 'mondu'); ?></h2>
	<?php if ( isset($webhooks_error) && null !== $webhooks_error ) : ?>
		<p><?php echo esc_html($webhooks_error); ?></p>
	<?php endif; ?>
	<?php if ( isset($webhooks_registered) && false !== $webhooks_registered ) : ?>
		<p> ✅ <?php esc_html_e('Webhooks registered', 'mondu'); ?>:
		<?php echo esc_html(date_i18n(get_option('date_format'), $webhooks_registered)); ?>
		</p>
	<?php endif; ?>
	<form method='post'>
		<?php
		wp_nonce_field('register-webhooks', 'register-webhooks');
		submit_button(__('Register Webhooks', 'mondu'));
		?>
	</form>
	<h2><?php esc_html_e('Download Logs', 'mondu'); ?></h2>
	<form action='<?php echo esc_html(get_option('siteurl')); ?>/wp-admin/admin-post.php?action=download_logs' method='post'>
		<input type='hidden' name='action' value='download_logs' />
		<input type='hidden' name='security' value='<?php echo esc_html(wp_create_nonce( 'mondu-download-logs' )); ?>' />
		<tr>
		<th scope="row"><label for="date"><?php esc_html_e('Log date', 'mondu'); ?>:</label></th>
		<td>
			<input type='date' id='date' name='date' value="<?php echo esc_html(gmdate('Y-m-d')); ?>" required />
		</td>
		</tr>
		<?php submit_button(__('Download Logs', 'mondu')); ?>
	</form>
</div>
