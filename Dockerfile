FROM wordpress:6.3.1
ENV WOOCOMMERCE_VERSION 8.0.3
ENV WOOCOMMERCE_PDF_INVOICES_VERSION 3.6.3

RUN apt update
RUN apt -y install wget
RUN apt -y install unzip
RUN apt -y install nano

# To avoid problems with another plugins
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install WP CLI
RUN wget https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar -O /tmp/wp-cli.phar \
  && chmod +x /tmp/wp-cli.phar \
  && mv /tmp/wp-cli.phar /usr/local/bin/wp

RUN rm -rf /usr/src/wordpress/wp-content/plugins/woocommerce

RUN wget https://downloads.wordpress.org/plugin/woocommerce.${WOOCOMMERCE_VERSION}.zip -O /tmp/woocommerce.zip \
  && cd /usr/src/wordpress/wp-content/plugins \
  && unzip /tmp/woocommerce.zip \
  && rm /tmp/woocommerce.zip

RUN rm -rf /usr/src/wordpress/wp-content/plugins/woocommerce-pdf-invoices-packing-slips

RUN wget https://downloads.wordpress.org/plugin/woocommerce-pdf-invoices-packing-slips.${WOOCOMMERCE_PDF_INVOICES_VERSION}.zip -O /tmp/woocommerce-pdf-invoices-packing-slips.zip \
  && cd /usr/src/wordpress/wp-content/plugins \
  && unzip /tmp/woocommerce-pdf-invoices-packing-slips.zip \
  && rm /tmp/woocommerce-pdf-invoices-packing-slips.zip

RUN rm -rf /usr/src/wordpress/wp-content/plugins/wt-woocommerce-sequential-order-numbers

RUN wget https://downloads.wordpress.org/plugin/wt-woocommerce-sequential-order-numbers.1.5.2.zip -O /tmp/wt-woocommerce-sequential-order-numbers.zip \
  && cd /usr/src/wordpress/wp-content/plugins \
  && unzip /tmp/wt-woocommerce-sequential-order-numbers.zip \
  && rm /tmp/wt-woocommerce-sequential-order-numbers.zip
