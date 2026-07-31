FROM php:8.2-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libcurl4-openssl-dev \
    libssl-dev \
    zip \
    unzip \
    git \
    supervisor \
    nginx \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        gd \
        mbstring \
        exif \
        pdo_mysql \
        zip \
        curl \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy project files (minus ignored files)
COPY . /app

# Install Composer dependencies.
# If the lock file is stale (incompatible with this PHP version), regenerate it.
RUN composer install --no-scripts --no-interaction --no-dev 2>&1 \
    || (rm -f composer.lock && composer install --no-scripts --no-interaction --no-dev 2>&1)

# Copy application configuration
COPY php.ini /usr/local/etc/php/conf.d/custom.ini
COPY nginx.conf /etc/nginx/nginx.conf

# Copy supervisor configuration
COPY .docker/supervisord.conf /etc/supervisor/conf.d/app.conf

# Create required directories
RUN mkdir -p /app/public/storage /app/public/uploads /app/logs \
    && chown -R www-data:www-data /app/public/storage /app/public/uploads /app/logs

EXPOSE 8080

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/app.conf"]
