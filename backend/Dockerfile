FROM php:8.3-fpm-alpine

WORKDIR /var/www

RUN apk add --no-cache \
    postgresql-client postgresql-dev \
    libpq \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    oniguruma-dev \
    icu-dev \
    linux-headers \
    $PHPIZE_DEPS

RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    pgsql \
    zip \
    mbstring \
    bcmath \
    intl \
    opcache \
    pcntl \
    sockets

RUN pecl install redis && docker-php-ext-enable redis

COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

RUN addgroup -g 1000 -S www && adduser -u 1000 -S www -G www

COPY --chown=www:www . .

RUN if [ -f "composer.json" ]; then \
    composer install --no-interaction --no-plugins --no-scripts --prefer-dist --optimize-autoloader; \
    fi

USER www

EXPOSE 9000

CMD ["php-fpm"]
