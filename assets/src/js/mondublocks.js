(function() {
    const { __ } = wp.i18n;
    const { createElement } = window.wp.element;
    const { decodeEntities } = window.wp.htmlEntities;
    const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
    const { registerPaymentMethodExtensionCallbacks } = window.wc.wcBlocksRegistry;
    const settings = window.wc.wcSettings.getSetting( 'mondu_blocks_data', {} );

    settings.available_countries = settings.available_countries || [];
    settings.gateways = settings.gateways || {};

    // This is not used anywhere, it's added for automatic translation generation with wp cli command
    const translations = [
        __('Mondu Invoice', 'mondu'),
        __('Mondu SEPA Direct Debit', 'mondu'),
        __('Mondu Installments', 'mondu'),
        __('Mondu Installments by Invoice', 'mondu'),
        __('Invoice - Pay later by bank transfer', 'mondu'),
        __('SEPA - Pay later by direct debit', 'mondu'),
        __('Split payments - Pay Later in Installments by Direct Debit', 'mondu'),
        __('Split payments - Pay Later in Installments by Bank Transfer', 'mondu')
    ];

    function Label(text) {
        return createElement('div', {}, Logo(), __(text, 'mondu'))
    }
    function Content(text) {
        return createElement('div', {}, __(text, 'mondu'), createElement('br', {}), DataProcessingNotice())
    }

    function Logo() {
        return createElement('img', { src: 'https://checkout.mondu.ai/logo.svg', style: { display: 'inline', height: '1rem', marginRight: '0.5rem'} })
    }

    function DataProcessingNotice() {
        return createElement('span', {
            dangerouslySetInnerHTML: {
                __html: __('Information on the processing of your personal data by <strong>Mondu GmbH</strong> can be found <a href="https://mondu.ai/gdpr-notification-for-buyers" target="_blank">here</a>.', 'mondu')
            }
        });
    }

    function createGatewayBlock(gatewayName, gatewayInfo) {
        return {
            name: gatewayName,
            label: Label(gatewayInfo.title),
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
            previousValue[currentValue] = function(arg) {
                return settings.available_countries.includes(arg.billingAddress.country)
            }

            return previousValue;
        }, {})
    )

    Object.entries(settings.gateways).forEach(([gatewayName, gatewayInfo]) => {
        registerPaymentMethod(createGatewayBlock(gatewayName, gatewayInfo))
    });
})();