# Mondu for Woocommerce

Increase your revenue with Mondu’s solution, without the operational burden.

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
wp i18n --allow-root update-po languages/mondu.pot languages/mondu-de_DE.po
wp i18n --allow-root update-po languages/mondu.pot languages/mondu-nl_NL.po
```

- Include the translated strings in the files `languages/mondu-de_DE.po` and `languages/mondu-nl_NL.po`.
- Run the following command to update `.mo` files:

```
wp i18n --allow-root make-mo languages
```
