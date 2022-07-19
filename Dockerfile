FROM wordpress:5.9.3
ENV WOOCOMMERCE_VERSION 6.5.1
ENV WOOCOMMERCE_PDF_INVOICES_VERSION 2.14.5

RUN apt update
RUN apt -y install wget
RUN apt -y install unzip

# To avoid problems with another plugins
RUN docker-php-ext-install mysqli pdo pdo_mysql

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
