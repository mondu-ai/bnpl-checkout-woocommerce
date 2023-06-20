# Mondu for Woocommerce

Mondu provides B2B E-commerce and B2B marketplaces with an online payment solution to enable their customers to pay with their preferred payment methods and flexible payment terms.

## Installation

- Run docker compose:

```
docker-compose up -d --build
```

- Open Wordpress admin url `http://localhost:8080/wp-admin`
- Activate Woocommerce and Mondu plugins `http://localhost:8080/wp-admin/plugins.php`

## Update translations

- Navigate to plugin's folder
- Run the following command to update `.pot` file:

```
wp i18n --allow-root make-pot . languages/mondu.pot
wp i18n --allow-root update-po languages/mondu.pot languages/
```

- Include the translated strings in the `languages/*.po` files.
- Run the following command to update `.mo` files:

```
wp i18n --allow-root make-mo languages
```

## Before pushing your changes
- run `composer install` ( one time to install dev dependencies )
- run `composer lint` and fix all the linting errors if present
