=== mondu-checkout-woocommerce ===
Contributors: mondu-ai, arthurmmoreira, tikohov20
Tags: mondu, woocommerce, e-commerce, ecommerce, store, sales, sell, woo, shop, cart, checkout, payment, payments, bnpl, b2b
Requires at least: 5.9.0
Tested up to: 6.1.0
Stable tag: 1.3.0
Requires PHP: 7.4.0

Increase your revenue with Mondu’s solution, without the operational burden.

== Installation ==

1. In your admin panel, go to Plugins -> Add New and click on the 'Upload Plugin' button.
2. Select the Woocommerce-Mondu-<VERSION>.zip file downloaded from our Github page: https://github.com/mondu-ai/bnpl-checkout-woocommerce/releases and click on the 'Install Now' button.
3. Click on the 'Activate Plugin' button to use the plugin right away.
4. Navigate to the new menu Mondu in your admin panel.
5. Insert the API Token provided by Mondu and the other settings.
6. Save the changes, validate the credentials and register the webhooks.
7. Navigate to WooCommerce -> Settings in your admin panel.
8. Open the Payments tab and enable the Mondu payment methods.
9. Save the changes.
10. Read more about the configuration process in the [installation guide](https://docs.mondu.ai/docs/woocommerce-installation-guide).

== Changelog ==

=== 1.3.0 ===

* Add fallback if the wcpdf_get_document number is not found
* Fix webhooks signature
* Allow send products with value zero
* Remove title and description from payment method gateway's configuration to dynamically change the language
* Include instruction in the created order email
* Add wordpress language to mondu filter
* Minor fixes

=== 1.2.1 ===

* Add cache if payment method endpoint returns 403

=== 1.2.0 ===

* WCPDF: Add filter for template extension
* Mondu only block order if we have an actually payment
* Add French translations
* Add Austria i18n
* Only show Mondu if it is validated

=== 1.1.4 ===

* Added WCPDF invoice extensions for our payment methods

=== 1.1.3 ===

* Handle not found WCPDF class
* Use hosted remote logo instead of a local one

=== 1.1.2 ===

* WCPDF Invoice: Language change and formatting
* Non mondu orders crash with an error message
* Support Order Pay page
* Use cart hash as external_reference_id
* Minor fixes

=== 1.1.1 ===

* Hotfix in checkout validation
* Allow user to change title, description and instructions in payment gateways
* Include payment method title and description in english by default
* Enhance wcpdf data
* Minor fixes

=== 1.1.0 ===

* Send language param when creating Mondu order
* Remove checkout error validation message
* Minor fixes

=== 1.0.5 ===

* Add a configuration field to disable sending line items to Mondu
* Verify if WooCommerce is active before activate Mondu
* Add uninstaller to remove Mondu data
* Add activate and deactivate functions to plugin
* Add transient on merchant payment methods
* Minor fixes

=== 1.0.4 ===

* Send errors to mondu API
* Use order number as external reference id
* Add gateway id in the icon's filter

=== 1.0.3 ===

* Add gross amount cents to Order's API
* Add DE and NL translations

=== 1.0.2 ===

* Bugfixes and improvements
* Validates company name and country on Mondu payments

=== 1.0.1 ===

* Check for empty values before sending to Mondu’s API

=== 1.0.0 ===

* First plugin version

=== 0.0.5 ===

* Bugfixes and improvements

=== 0.0.4 ===

* Include direct debit payment

=== 0.0.3 ===

* Bugfixes

=== 0.0.2 ===

* Included webhooks, order adjusting and more features

=== 0.0.1 ===

* First version of plugin
