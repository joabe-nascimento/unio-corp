FROM php:8.2-cli-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip curl \
        libzip-dev libicu-dev libxml2-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql zip intl \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY docker/validate/entrypoint.sh /usr/local/bin/validate-entrypoint
RUN chmod +x /usr/local/bin/validate-entrypoint

ENTRYPOINT ["validate-entrypoint"]
