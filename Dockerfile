FROM php:8.4-cli-bookworm

ARG NODE_MAJOR=22

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        default-mysql-client \
        git \
        gnupg \
        unzip \
        zip \
        libzip-dev \
    && docker-php-ext-install bcmath pcntl pdo_mysql zip \
    && mkdir -p /etc/apt/keyrings \
    && curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key \
        | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg \
    && echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_${NODE_MAJOR}.x nodistro main" \
        > /etc/apt/sources.list.d/nodesource.list \
    && apt-get update \
    && apt-get install -y --no-install-recommends nodejs \
    && npm install -g npm@latest \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* \
    && git config --global --add safe.directory /var/www/html

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/entrypoint.sh /usr/local/bin/market-platform-entrypoint
RUN chmod +x /usr/local/bin/market-platform-entrypoint

EXPOSE 8000 5173

ENTRYPOINT ["market-platform-entrypoint"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
