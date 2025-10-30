FROM php:8.4-cli

# Set Composer to allow superuser
ENV COMPOSER_ALLOW_SUPERUSER=1

RUN apt update \
    && apt install -y \
        php8.4-common/stable \
        php8.4-sqlite3/stable \
        php8.4-bcmath/stable \
    && apt clean

RUN pecl install pcov \
    && docker-php-ext-enable pcov

# Install composer and phpunit
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
WORKDIR /app
