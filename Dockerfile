FROM php:8.2-apache

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libonig-dev \
    zip \
    unzip \
    curl \
    git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo pdo_mysql mbstring zip \
    && a2dismod -f mpm_event mpm_worker 2>/dev/null || true \
    && a2enmod mpm_prefork rewrite headers \
    && find /etc/apache2/mods-enabled/ -name "mpm_*.load" ! -name "mpm_prefork.load" -delete 2>/dev/null || true \
    && find /etc/apache2/mods-enabled/ -name "mpm_*.conf" ! -name "mpm_prefork.conf" -delete 2>/dev/null || true \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer v2
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files
COPY composer.json ./

# Install PHP dependencies without blocking
RUN COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction --no-scripts --ignore-platform-reqs --no-audit || true

# Copy application files
COPY . /var/www/html/

# Set ownership and permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Create startup script to bind Apache to dynamic $PORT and enforce single MPM
RUN printf '#!/bin/sh\n\
set -e\n\
export PORT="${PORT:-80}"\n\
a2dismod -f mpm_event mpm_worker 2>/dev/null || true\n\
a2enmod mpm_prefork 2>/dev/null || true\n\
find /etc/apache2/mods-enabled/ -name "mpm_*.load" ! -name "mpm_prefork.load" -delete 2>/dev/null || true\n\
find /etc/apache2/mods-enabled/ -name "mpm_*.conf" ! -name "mpm_prefork.conf" -delete 2>/dev/null || true\n\
sed -i "s/Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf\n\
sed -i "s/<VirtualHost \\*:.*/<VirtualHost \\*:${PORT}>/" /etc/apache2/sites-available/000-default.conf\n\
sed -i "/<Directory \\/var\\/www\\/>/,/<\\/Directory>/ s/AllowOverride None/AllowOverride All/" /etc/apache2/apache2.conf 2>/dev/null || true\n\
exec apache2-foreground\n' > /usr/local/bin/start-server.sh \
    && chmod +x /usr/local/bin/start-server.sh

ENV PORT=80

EXPOSE ${PORT}

CMD ["/usr/local/bin/start-server.sh"]
