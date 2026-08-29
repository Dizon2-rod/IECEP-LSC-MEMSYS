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
    && rm -f /etc/apache2/mods-enabled/mpm_*.load /etc/apache2/mods-enabled/mpm_*.conf /etc/apache2/mods-available/mpm_event.* /etc/apache2/mods-available/mpm_worker.* \
    && ln -sf /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load \
    && ln -sf /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf \
    && a2enmod rewrite headers \
    && echo "log_errors = On\nerror_log = /dev/stderr\ndisplay_errors = On\ndisplay_startup_errors = On" > /usr/local/etc/php/conf.d/docker-php-logging.ini \
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

# Copy and setup startup script
COPY start-server.sh /usr/local/bin/start-server.sh
RUN chmod +x /usr/local/bin/start-server.sh \
    && sed -i 's/\r$//' /usr/local/bin/start-server.sh

ENV PORT=80

EXPOSE 80 8080

CMD ["/usr/local/bin/start-server.sh"]
