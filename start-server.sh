#!/bin/sh
set -e

PORT="${PORT:-80}"

# Purge any conflicting MPM modules
rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.* 2>/dev/null || true
rm -f /etc/apache2/mods-available/mpm_event.* /etc/apache2/mods-available/mpm_worker.* 2>/dev/null || true

# Enforce only prefork MPM
rm -f /etc/apache2/mods-enabled/mpm_*.load /etc/apache2/mods-enabled/mpm_*.conf 2>/dev/null || true
ln -sf /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load
if [ -f /etc/apache2/mods-available/mpm_prefork.conf ]; then
    ln -sf /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf
fi

# Configure dynamic port in Apache
sed -i "s/Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:.*/<VirtualHost \*:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Allow .htaccess override
sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf 2>/dev/null || true

echo "Starting Apache on port ${PORT} with mpm_prefork..."
exec apache2-foreground
