FROM wordpress:7.1.3-php8.3-apache
ADD https://github.com/wp-cli/wp-cli/releases/download/v2.12.0/wp-cli-2.12.0.phar /usr/local/bin/wp
RUN echo 'be928f6b8ca1e8dfb9d2f4b75a13aa4aee0896f8a9a0a1c45cd5d2c98605e6172e6d014dda2e27f88c98befc16c040cbb2bd1bfa121510ea5cdf5f6a30fe8832  /usr/local/bin/wp' | sha512sum -c - \
    && chmod 755 /usr/local/bin/wp
COPY php.ini /usr/local/etc/php/conf.d/ffp.ini
