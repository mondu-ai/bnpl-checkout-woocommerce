# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

A higher-level, WordPress-wide CLAUDE.md lives at `../../../CLAUDE.md`; this file covers plugin-internal details not derivable from a single file.

## Commands

```bash
# Install dev deps (WPCS + WooCommerce sniffs)
composer install

# Lint / autofix against pbs-rules-set.xml (WordPress-Extra + WooCommerce-Core + PHPCompatibility)
composer lint
composer lint-fix

# Local stack (WP + Woo + MariaDB + phpMyAdmin + Mailcatcher, Xdebug on :9000)
docker-compose up -d --build
# WP: http://localhost:8080 · phpMyAdmin: :8081 · Mailcatcher: :1080

# Translations
wp i18n --allow-root make-pot . languages/mondu.pot
wp i18n --allow-root update-po languages/mondu.pot languages/
wp i18n --allow-root make-mo languages
wp i18n --allow-root make-json languages
```

There is **no unit test suite** in this repo. Verification is manual against the Dockerized WooCommerce stack plus a Mondu sandbox/mock API.

Note: the compose file points `MONDU_SANDBOX_URL`/`MONDU_PRODUCTION_URL` at `host.docker.internal:3000` and webhooks at `host.docker.internal:8390` — i.e. a local mock API, not Mondu's real sandbox. To hit the real sandbox, unset those env vars (defaults live in `mondu-buy-now-pay-later.php`: `api.demo.mondu.ai` / `api.mondu.ai`).

## Architecture

Entry: `mondu-buy-now-pay-later.php` defines `MONDU_*` constants, registers activation/deactivation hooks, declares HPOS + blocks compatibility, then instantiates `\Mondu\Plugin` on `plugins_loaded`. Autoload is PSR-4 via `src/autoload.php` — the root namespace `Mondu\` maps to `src/Mondu/`.

### Payment method registry — the one place to edit when adding a gateway

`src/Mondu/Config/PaymentMethodsConfig.php` is the **single source of truth** for every payment method: gateway class, WC gateway id (`mondu_invoice`, `mondu_installment`, `mondu_installment_by_invoice`, `mondu_direct_debit`, `mondu_pay_now`), checkout/admin icon filenames, and per-locale default titles. All iteration over methods in `Plugin.php`, `MonduBlocksSupport`, title migration, country gating, etc. goes through this config — don't hardcode gateway ids elsewhere.

Adding a new method = new `Gateway<Name>` subclass of `MonduGateway` + one entry in `METHODS`. `Plugin::init()` registers each class through `woocommerce_payment_gateways` automatically.

### Gateway title/description translations — legacy migration

Per-gateway titles and descriptions are stored as a repeatable list under keys `title_translations` / `description_translations` inside `woocommerce_<gateway_id>_settings`. On every boot, `Plugin::ensure_mondu_gateway_title_defaults()`:

1. Migrates old `title_en`/`title_de`/… (and description equivalents) into the `*_translations` array (one-time, skipped if the new array is already populated).
2. Fills missing defaults from `PaymentMethodsConfig::default_titles` — but **only once per plugin version**, tracked by `_mondu_gateway_title_defaults_version`. Bumping `MONDU_PLUGIN_VERSION` will re-trigger the fill for gateways that still have no translations.

If you change defaults in `PaymentMethodsConfig`, existing installs won't get them unless you also bump `MONDU_PLUGIN_VERSION` and their `title_translations` is still empty. Admins' edits are never overwritten.

The admin UI for these repeatable rows is driven by `assets/src/js/admin-gateway-titles.js`, enqueued only on `wc-settings → checkout → <mondu gateway section>` pages.

### Country gating

`Plugin::remove_gateway_if_country_unavailable()` filters `woocommerce_available_payment_gateways` at checkout by comparing `PaymentMethodsConfig::get_ids()` against the list returned by `MonduRequestWrapper::get_merchant_payment_methods()` (cached, falls back gracefully on 403). A method disabled in the Mondu merchant config will silently disappear from checkout — this is expected behaviour, not a bug.

### Order lifecycle

- **Creation**: `MonduRequestWrapper::create_order()` builds the payload via `Support\OrderData`, stores Mondu's UUID on the WC order as meta `_mondu_order_id` (`Plugin::ORDER_ID_KEY`). Invoice id is stored as `_mondu_invoice_id`.
- **Status sync out**: `woocommerce_order_status_changed` → `MonduRequestWrapper::order_status_changed()` mirrors WC status into Mondu (confirm/cancel/ship calls).
- **Status sync in**: Webhook `POST /wp-json/mondu/v1/webhooks/index` (registered in `WebhooksController`). `SignatureVerifier` HMACs the raw request body against `X-MONDU-SIGNATURE`; on mismatch the handler raises `MonduException` and returns before touching state. Topics: `order/pending|authorized|confirmed|declined|canceled`, `invoice/created|canceled|paid`.
- **REST callback**: `OrdersController` exposes `/wp-json/mondu/v1/orders/...` endpoints used by the hosted-checkout success flow.

### WCPDF integration

All `wpo_wcpdf_*` hooks in `Plugin::init()` are guarded by `class_exists('WPO_WCPDF')` — keep that guard when adding new WCPDF touchpoints. `wcpdf_add_mondu_payment_language_switch()` currently **hard-codes German** for PDF invoices (documented as a temporary fix in the code); watch for that when debugging PDF locale issues.

### Key option keys (wp_options)

- `mondu_account` — API credentials and global plugin settings (`Plugin::OPTION_NAME`).
- `_mondu_credentials_validated`, `_mondu_webhooks_registered` — cleared on deactivation.
- `woocommerce_<gateway_id>_settings` — per-gateway Woo settings incl. `title_translations` / `description_translations`. Also cleared on deactivation.
- `_mondu_gateway_title_defaults_version` — guard for the one-time title default seeding per plugin version.

### Order meta keys

`_mondu_order_id`, `_mondu_invoice_id` — always reference via the constants on `\Mondu\Plugin` rather than the literals.

## Release

Merging to `main` triggers `.github/workflows/release.yml`, which runs `releaser.sh -v <tag>` via the shared `mondu-ai/release-action` workflow. `releaser.sh` rsyncs the plugin into a temp dir, excluding dev-only files (`composer.*`, `vendor/`, `docker-compose.yml`, `Dockerfile`, `.github/`, `releaser.sh`, `pbs-rules-set.xml`), and zips it as `mondu-buy-now-pay-later-<version>.zip`. When bumping the plugin version you must update **three** places: `mondu-buy-now-pay-later.php` header + `MONDU_PLUGIN_VERSION` define, and `README.txt` `Stable tag`.

## Conventions worth knowing

- Short array syntax (`[]`) is enforced (`DisallowLongArraySyntax`); don't introduce `array()`.
- 4-space indent but **tabs** are used in most PHP files — match the surrounding file.
- `Helper::log()` in `src/Mondu/Mondu/Support/Helper.php` is the project's logger; webhook and API-wrapper flows already use it. Prefer it over `error_log`.
- Never reference a gateway id as a string literal outside `PaymentMethodsConfig`; use `PaymentMethodsConfig::get_gateway_ids()` or `::get_method_by_gateway_id()`.