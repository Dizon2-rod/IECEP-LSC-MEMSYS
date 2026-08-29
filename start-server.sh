#!/bin/sh
set -e

PORT="${PORT:-80}"

# 1. Purge any conflicting MPM modules to enforce strictly mpm_prefork
rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.* 2>/dev/null || true
rm -f /etc/apache2/mods-available/mpm_event.* /etc/apache2/mods-available/mpm_worker.* 2>/dev/null || true
rm -f /etc/apache2/mods-enabled/mpm_*.load /etc/apache2/mods-enabled/mpm_*.conf 2>/dev/null || true

ln -sf /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load
if [ -f /etc/apache2/mods-available/mpm_prefork.conf ]; then
    ln -sf /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf
fi

# 2. Configure ports.conf to listen on dynamic $PORT and port 80
echo "Listen ${PORT}" > /etc/apache2/ports.conf
if [ "${PORT}" != "80" ]; then
    echo "Listen 80" >> /etc/apache2/ports.conf
fi

# 3. Configure ServerName globally to prevent AH00558
echo "ServerName localhost" > /etc/apache2/conf-available/servername.conf
ln -sf /etc/apache2/conf-available/servername.conf /etc/apache2/conf-enabled/servername.conf 2>/dev/null || true

# 4. Configure default VirtualHost with proper logging and AllowOverride
cat <<EOF > /etc/apache2/sites-available/000-default.conf
<VirtualHost *:${PORT} *:80>
    ServerName localhost
    DocumentRoot /var/www/html
    DirectoryIndex index.php index.html
    <Directory /var/www/html>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    ErrorLog /dev/stderr
    CustomLog /dev/stdout combined
</VirtualHost>
EOF

# 5. Ensure required directories and permissions exist
mkdir -p /var/www/html/logs /var/www/html/storage /var/www/html/storage/keys
chown -R www-data:www-data /var/www/html/logs /var/www/html/storage 2>/dev/null || true
chmod -R 775 /var/www/html/logs /var/www/html/storage 2>/dev/null || true

echo "Starting Apache server on port ${PORT} (and 80) with mpm_prefork..."
exec apache2-foreground
