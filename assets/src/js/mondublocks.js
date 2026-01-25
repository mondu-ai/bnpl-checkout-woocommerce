(function () {
	const { __ }                                      = wp.i18n;
	const { createElement }                           = window.wp.element;
	const { decodeEntities }                          = window.wp.htmlEntities;
	const { registerPaymentMethod }                   = window.wc.wcBlocksRegistry;
	const { registerPaymentMethodExtensionCallbacks } = window.wc.wcBlocksRegistry;
	const settings                                    = window.wc.wcSettings.getSetting( 'mondu_blocks_data', {} );

	settings.gateways = settings.gateways || {};

	// This is not used anywhere, it's added for automatic translation generation with wp cli command
	const translations = [
		__('Business instalments (3, 6, 12)', 'mondu'),
		__('SEPA direct debit (30 days)', 'mondu'),
		__('Installments (3, 6, 12 months)', 'mondu'),
		__('Invoice (30 days)', 'mondu'),
        __('Instant Pay', 'mondu')
	];

	function Label(text, iconUrl) {
		return createElement('div', {}, Logo(iconUrl), __(text, 'mondu'))
	}

	function Content(text) {
		return createElement('div', {}, __(text, 'mondu'))
	}

	function Logo(iconUrl) {
		const src = iconUrl || 'https://checkout.mondu.ai/logo.svg';
		return createElement('img', { src: src, style: { display: 'inline', maxHeight: '40px', marginRight: '0.5rem', position: 'relative', top: '5px'} })
	}

	function createGatewayBlock(gatewayName, gatewayInfo) {
		return {
			name: gatewayName,
			label: Label(gatewayInfo.title, gatewayInfo.icon),
			content: Content(gatewayInfo.description),
			edit: Content(gatewayInfo.description),
			canMakePayment: () => true,
			ariaLabel: decodeEntities(gatewayInfo.title),
			supports: {
				features: gatewayInfo.supports,
			},
		}
	}

	registerPaymentMethodExtensionCallbacks('mondu',
		Object.keys(settings.gateways).reduce((previousValue, currentValue) => {
			previousValue[currentValue] = function (arg) {
				return true;
			}

			return previousValue;
		}, {})
	)

	Object.entries(settings.gateways).forEach(([gatewayName, gatewayInfo]) => {
		registerPaymentMethod(createGatewayBlock(gatewayName, gatewayInfo))
	});
})();
