<div class="wrap">
    <h1><?php _e( 'Mondu Settings', 'mondu' ); ?></h1>
    <?php settings_errors(); ?>
    <form method="post" action="options.php">
        <?php
        settings_fields( 'mondu' );
        do_settings_sections( 'mondu-settings-account' );
        submit_button();
        ?>
    </form>
</div>
