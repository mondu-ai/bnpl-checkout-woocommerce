=== Mondu Buy Now Pay Later ===
Contributors: mondu-ai, arthurmmoreira, tikohov20
Tags: mondu, woocommerce, e-commerce, ecommerce, store, sales, sell, woo, woo commerce, shop, cart, shopping cart, sell online, checkout, payment, payments, bnpl, b2b
Requires at least: 5.9.0
Tested up to: 6.2.2
Stable tag: 2.1.0
Requires PHP: 7.4
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Mondu provides B2B E-commerce and B2B marketplaces with an online payment solution to buy now and pay later.

== Description ==

Mondu provides B2B E-commerce and B2B marketplaces with an online payment solution to enable their customers to pay with their preferred payment methods and flexible payment terms.

== Features ==

1. Increase your B2B ecommerce revenue - by offering your business buyers their favorite payment option with flexible net terms, you can increase your revenues and customer loyalty.
2. Improved cash flow - Get paid upfront, we pay you as the merchant as soon as the products are shipped or the service has been performed.
3. High acceptance rate - Enable >90% of your business buyers to pay with flexible payment terms.
4. Variety of payment methods - B2B payment methods are diverse - we support all most popular B2B payment methods.
5. Real-time credit check without the financial risk - The proprietary risk engine runs credit risk checks in real time and approves buyers in checkout with high limits.

== Installation ==

1. In your admin panel, go to Plugins -> Add New and click on the 'Upload Plugin' button.
2. Select the mondu-buy-now-pay-later-<VERSION>.zip file downloaded from our Github page: https://github.com/mondu-ai/bnpl-checkout-woocommerce/releases and click on the 'Install Now' button.
3. Click on the 'Activate Plugin' button to use the plugin right away.
4. Navigate to the new menu Mondu in your admin panel.
5. Insert the API Token provided by Mondu and the other settings.
6. Save the changes, validate the credentials and register the webhooks.
7. Navigate to WooCommerce -> Settings in your admin panel.
8. Open the Payments tab and enable the Mondu payment methods.
9. Save the changes.
10. Read more about the configuration process in the [installation guide](https://docs.mondu.ai/docs/woocommerce-installation-guide).

== Frequently Asked Questions ==

= Where can I find an introdution about Mondu? =

You can know more about Mondu at [Introdution to Paying with Mondu](https://mondu.ai/introduction-to-paying-with-mondu).

= Where can I find Mondu installation guide? =

For help setting up and configuring Mondu, please refer to [Installation Guide](https://docs.mondu.ai/docs/woocommerce-installation-guide).

= Where can I find Mondu frequently asked questions? =

Check out [Frequently Asked Questions](https://www.mondu.ai/faq) in the Mondu website.

== Screenshots ==

1. Creating new B2B moments.
2. Turn payments into your B2B growth engine.
3. Grow now. Pay better.

== Changelog ==

= 2.1.0 =

* Extend getting order by order number
* Update order to on-hold when call Mondu confirm
* Fix EU and UK info showing when UK buyer

= 2.0.5 =

* Adapt UK standards

= 2.0.4 =

* Ensure that ampersand is escaped in the webhooks signature verifier

= 2.0.3 =

* Use home_url instead of site_url
* Empty cart on order confirm
* Rollback changes on invoice external reference id

= 2.0.2 =

* Use company shipping address as a fallback when company billing address is null

= 2.0.1 =

* Fix sandbox or production setting name

= 2.0.0 =

* Hosted checkout and lot of fixes
* Rename plugin and separate changelog from readme
* Fix hosted checkout issues

= 1.3.4 =

* Changes for B2B market plugin compatibility

= 1.3.3 =

* Changes on plugin to update to the WordPress marketplace
* Add code sniffer and fix the issues

= 1.3.2 =

* Add early return on credit note creation
* Minor fixes on the credit note creation/listing

= 1.3.1 =

* Add payment method name to the enabled label
* Remove instruction field to make it automatically localised
* Add tips to admin fields
* Include more supported countries in the list

= 1.3.0 =

* Add fallback if the wcpdf_get_document number is not found
* Fix webhooks signature
* Allow send products with value zero
* Remove title and description from payment method gateway's configuration to dynamically change the language
* Include instruction in the created order email
* Add wordpress language to mondu filter
* Minor fixes

= 1.2.1 =

* Add cache if payment method endpoint returns 403

= 1.2.0 =

* WCPDF: Add filter for template extension
* Mondu only block order if we have an actually payment
* Add French translations
* Add Austria i18n
* Only show Mondu if it is validated

= 1.1.4 =

* Added WCPDF invoice extensions for our payment methods

= 1.1.3 =

* Handle not found WCPDF class
* Use hosted remote logo instead of a local one

= 1.1.2 =

* WCPDF Invoice: Language change and formatting
* Non mondu orders crash with an error message
* Support Order Pay page
* Use cart hash as external_reference_id
* Minor fixes

= 1.1.1 =

* Hotfix in checkout validation
* Allow user to change title, description and instructions in payment gateways
* Include payment method title and description in english by default
* Enhance wcpdf data
* Minor fixes

= 1.1.0 =

* Send language param when creating Mondu order
* Remove checkout error validation message
* Minor fixes

= 1.0.5 =

* Add a configuration field to disable sending line items to Mondu
* Verify if WooCommerce is active before activate Mondu
* Add uninstaller to remove Mondu data
* Add activate and deactivate functions to plugin
* Add transient on merchant payment methods
* Minor fixes

= 1.0.4 =

* Send errors to mondu API
* Use order number as external reference id
* Add gateway id in the icon's filter

= 1.0.3 =

* Add gross amount cents to Order's API
* Add DE and NL translations

= 1.0.2 =

* Bugfixes and improvements
* Validates company name and country on Mondu payments

= 1.0.1 =

* Check for empty values before sending to Mondu’s API

= 1.0.0 =

* First plugin version

= 0.0.5 =

* Bugfixes and improvements

= 0.0.4 =

* Include direct debit payment

= 0.0.3 =

* Bugfixes

= 0.0.2 =

* Included webhooks, order adjusting and more features

= 0.0.1 =

* First version of plugin

== Upgrade Notice ==

= 2.0.0 =

If you were using the old Mondu plugin for WooCommerce, please remove it first before activating this new one. You will also need to reenter the Mondu API key and reenable the Payment Method on WooCommerce settings.
