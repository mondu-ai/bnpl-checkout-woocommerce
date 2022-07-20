FROM wordpress:5.9.3
ENV WOOCOMMERCE_VERSION 6.5.1
ENV WOOCOMMERCE_PDF_INVOICES_VERSION 2.14.5

RUN apt update
RUN apt -y install wget
RUN apt -y install unzip
RUN apt -y install subversion

# To avoid problems with another plugins
RUN docker-php-ext-install mysqli pdo pdo_mysql

RUN rm -rf /usr/src/wordpress/wp-content/plugins/woocommerce

RUN wget https://downloads.wordpress.org/plugin/woocommerce.${WOOCOMMERCE_VERSION}.zip -O /tmp/woocommerce.zip \
  && cd /usr/src/wordpress/wp-content/plugins \
  && unzip /tmp/woocommerce.zip \
  && rm /tmp/woocommerce.zip

RUN rm -rf /usr/src/wordpress/wp-content/plugins/woocommerce-pdf-invoices-packing-slips

RUN cd /usr/src/wordpress/wp-content/plugins \
  && svn checkout https://plugins.svn.wordpress.org/woocommerce-pdf-invoices-packing-slips/tags/${WOOCOMMERCE_PDF_INVOICES_VERSION} \
  && mv ./${WOOCOMMERCE_PDF_INVOICES_VERSION} ./woocommerce-pdf-invoices-packing-slips
