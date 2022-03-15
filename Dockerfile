FROM wordpress:latest
ENV WOOCOMMERCE_VERSION 6.2.0

RUN apt update
RUN apt -y install wget
RUN apt -y install unzip

RUN rm -rf /usr/src/wordpress/wp-content/plugins/woocommerce

RUN wget https://downloads.wordpress.org/plugin/woocommerce.${WOOCOMMERCE_VERSION}.zip -O /tmp/temp.zip \
  && cd /usr/src/wordpress/wp-content/plugins \
  && unzip /tmp/temp.zip \
  && rm /tmp/temp.zip
