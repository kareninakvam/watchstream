FROM composer:2@sha256:b6dcd72ca98fa19d0e5b8476de179859704891fad06dd9e0b96833d4c430a5fc AS composer
FROM php:8.4-cli-bookworm@sha256:54b467ede1db96c66675e7b78457dd43729d0e7be31978d4a1e5c069ceabcf76
RUN apt-get update && apt-get install -y --no-install-recommends git unzip procps libzip-dev libicu-dev default-mysql-client \
    && docker-php-ext-install pdo_mysql pcntl posix intl zip \
    && pecl install redis-6.3.0 && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
COPY docker/php.ini /usr/local/etc/php/conf.d/watchstream.ini
COPY docker/entrypoint.sh /usr/local/bin/watchstream-entrypoint
RUN chmod +x /usr/local/bin/watchstream-entrypoint
ENTRYPOINT ["watchstream-entrypoint"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
