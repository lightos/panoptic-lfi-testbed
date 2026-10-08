FROM php:8.5-apache@sha256:eacc0d98992683cb46e4f8f44b2418a0323855dc8b59d32dc54f7a9b90a966dd

# This image is deliberately vulnerable at the application layer. Keep the
# runtime current so unrelated, accidental vulnerabilities do not become part
# of the test surface.
RUN a2enmod rewrite

RUN { \
        echo "display_errors=1"; \
        echo "error_reporting=E_ALL"; \
        echo "expose_php=0"; \
    } > /usr/local/etc/php/conf.d/panoptic-testbed.ini

ENV APACHE_DOCUMENT_ROOT=/var/www/html
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

COPY src/ /var/www/html/
RUN useradd --no-create-home --home-dir /home/panoptic --shell /bin/sh panoptic \
    && install -d -o panoptic -g panoptic -m 0755 /home/panoptic \
    && install -d -m 0755 /home/hostile-ok

COPY fixtures/proof.txt /opt/panoptic-fixtures/proof.txt
COPY fixtures/windows-win.ini /opt/panoptic-fixtures/windows-win.ini
COPY fixtures/passwd /opt/panoptic-fixtures/passwd
COPY fixtures/passwd-hostile /opt/panoptic-fixtures/passwd-hostile
COPY fixtures/bash_history /home/panoptic/.bash_history
COPY fixtures/mysql-bin.index /var/log/mysql-bin.index
COPY fixtures/mysql-bin.000001 /var/log/mysql-bin.000001
COPY fixtures/hostile_profile /home/hostile-ok/.profile

# Router and base directory for the "raw" Compose service, which runs PHP's
# built-in server so literal ../ request paths reach PHP unnormalized.
COPY raw/ /opt/panoptic-raw/

RUN chown panoptic:panoptic /home/panoptic/.bash_history \
    && chmod 0644 \
        /opt/panoptic-fixtures/proof.txt \
        /opt/panoptic-fixtures/windows-win.ini \
        /opt/panoptic-fixtures/passwd \
        /opt/panoptic-fixtures/passwd-hostile \
        /home/hostile-ok/.profile \
        /opt/panoptic-raw/router.php \
        /opt/panoptic-raw/files/placeholder.txt \
        /home/panoptic/.bash_history \
        /var/log/mysql-bin.index \
        /var/log/mysql-bin.000001 \
    && chown -R www-data:www-data /var/www/html

RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

RUN echo "AllowEncodedSlashes NoDecode" >> /etc/apache2/apache2.conf
