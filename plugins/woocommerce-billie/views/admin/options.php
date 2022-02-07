<div class="wrap">
    <h1><?php _e( 'Billie Settings', 'billie' ); ?></h1>
	<?php settings_errors(); ?>
    <form method="post" action="options.php">
		<?php
		settings_fields( 'billie' );
		do_settings_sections( 'billie-settings-account' );
		submit_button();
		?>
    </form>
	<?php if ( isset( $oauthPossible ) && ( $oauthPossible === true ) ): ?>
        <h2><?php _e( 'Validate Credentials', 'billie' ); ?></h2>
		<?php if ( isset( $validationError ) && $validationError !== null ): ?>
            <p><?php echo $validationError; ?></p>
		<?php endif; ?>
		<?php if ( isset( $credentialsValidated ) && $credentialsValidated !== false ): ?>
            <p> ✅ <?php _e('Credentials validated:','billie');?>
                <?php echo date_i18n(get_option('date_format'), $credentialsValidated); ?>
            </p>
		<?php endif; ?>
        <form method="post">
			<?php
			wp_nonce_field( 'validate-credentials' );
			submit_button( __( 'Validate Credentials', 'billie' ) );
			?>
        </form>
	<?php endif; ?>
</div>
