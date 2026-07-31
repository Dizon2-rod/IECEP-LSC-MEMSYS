FROM php:8.2-fpm

# Install system dependencies for PHP extensions
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

# Write php.ini
RUN { \
        echo "file_uploads=On"; \
        echo "upload_max_filesize=20M"; \
        echo "post_max_size=20M"; \
        echo "memory_limit=256M"; \
        echo "display_errors=Off"; \
        echo "error_reporting=E_ALL & ~E_DEPRECATED & ~E_STRICT"; \
        echo "date.timezone=Asia/Manila"; \
    } > /usr/local/etc/php/conf.d/custom.ini

# Write nginx.conf
RUN { \
        echo "worker_processes auto;"; \
        echo "error_log /var/log/nginx/error.log warn;"; \
        echo "pid /tmp/nginx.pid;"; \
        echo ""; \
        echo "events {"; \
        echo "    worker_connections 1024;"; \
        echo "}"; \
        echo ""; \
        echo "http {"; \
        echo "    include /etc/nginx/mime.types;"; \
        echo "    default_type application/octet-stream;"; \
        echo "    log_format main '\$remote_addr - \$remote_user [\$time_local] \"\$request\"';"; \
        echo "    access_log /var/log/nginx/access.log main;"; \
        echo "    sendfile on;"; \
        echo "    keepalive_timeout 65;"; \
        echo ""; \
        echo "    server {"; \
        echo "        listen 8080;"; \
        echo "        server_name _;"; \
        echo "        root /app/public;"; \
        echo "        index index.php index.html index.htm;"; \
        echo "        client_max_body_size 20M;"; \
        echo ""; \
        echo "        location / {"; \
        echo "            try_files \$uri \$uri/ /index.php?\$query_string;"; \
        echo "        }"; \
        echo ""; \
        echo "        location ~ \.php$ {"; \
        echo "            try_files \$uri =404;"; \
        echo "            fastcgi_pass 127.0.0.1:9000;"; \
        echo "            fastcgi_index index.php;"; \
        echo "            fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;"; \
        echo "            include fastcgi_params;"; \
        echo "            fastcgi_param HTTP_PROXY \"\";"; \
        echo "            fastcgi_read_timeout 300;"; \
        echo "            fastcgi_send_timeout 300;"; \
        echo "            fastcgi_connect_timeout 300;"; \
        echo "        }"; \
        echo ""; \
        echo "        location ~ /\.ht {"; \
        echo "            deny all;"; \
        echo "        }"; \
        echo ""; \
        echo "        location ~ /\.(env|git) {"; \
        echo "            deny all;"; \
        echo "        }"; \
        echo "    }"; \
        echo "}"; \
    } > /etc/nginx/nginx.conf

# Write supervisord.conf
RUN mkdir -p /etc/supervisor/conf.d && \
    echo "[supervisord]" > /etc/supervisor/conf.d/app.conf && \
    echo "nodaemon=true" >> /etc/supervisor/conf.d/app.conf && \
    echo "user=root" >> /etc/supervisor/conf.d/app.conf && \
    echo "logfile=/var/log/supervisor/supervisord.log" >> /etc/supervisor/conf.d/app.conf && \
    echo "pidfile=/var/run/supervisord.pid" >> /etc/supervisor/conf.d/app.conf && \
    echo "" >> /etc/supervisor/conf.d/app.conf && \
    echo "[program:php-fpm]" >> /etc/supervisor/conf.d/app.conf && \
    echo "command=php-fpm" >> /etc/supervisor/conf.d/app.conf && \
    echo "stdout_logfile=/dev/stdout" >> /etc/supervisor/conf.d/app.conf && \
    echo "stderr_logfile=/dev/stderr" >> /etc/supervisor/conf.d/app.conf && \
    echo "autostart=true" >> /etc/supervisor/conf.d/app.conf && \
    echo "autorestart=true" >> /etc/supervisor/conf.d/app.conf && \
    echo "priority=5" >> /etc/supervisor/conf.d/app.conf && \
    echo "" >> /etc/supervisor/conf.d/app.conf && \
    echo "[program:nginx]" >> /etc/supervisor/conf.d/app.conf && \
    echo "command=nginx -g 'daemon off;'" >> /etc/supervisor/conf.d/app.conf && \
    echo "stdout_logfile=/dev/stdout" >> /etc/supervisor/conf.d/app.conf && \
    echo "stderr_logfile=/dev/stderr" >> /etc/supervisor/conf.d/app.conf && \
    echo "autostart=true" >> /etc/supervisor/conf.d/app.conf && \
    echo "autorestart=true" >> /etc/supervisor/conf.d/app.conf && \
    echo "priority=10" >> /etc/supervisor/conf.d/app.conf

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy project files (minus ignored files)
COPY . /app

# Install Composer dependencies.
# If the lock file is stale (incompatible with this PHP version), regenerate it.
# Use --ignore-platform-reqs to bypass missing extensions and use service_role_key for Supabase
RUN COMPOSER_ALLOW_SUPERUSER=1 composer install --no-scripts --no-interaction --no-dev --ignore-platform-reqs 2>&1 \
    || (rm -f composer.lock && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-scripts --no-interaction --no-dev --ignore-platform-reqs 2>&1)

# Create required directories
RUN mkdir -p /app/public/storage /app/public/uploads /app/logs \
    && chown -R www-data:www-data /app/public/storage /app/public/uploads /app/logs

EXPOSE 8080

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/app.conf"]
