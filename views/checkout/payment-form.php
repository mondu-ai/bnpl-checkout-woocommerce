<!-- TODO: Is there a way to add this info in a different way? -->
<p>
	<?php
	printf(wp_kses(__('Information on the processing of your personal data by <strong>Mondu GmbH</strong> can be found <a href="https://mondu.ai/gdpr-notification-for-buyers" target="_blank">here</a>.', 'mondu'), [
		'a' => [
			'href'   => [],
			'target' => [],
		],
	]));
	?>
</p>
