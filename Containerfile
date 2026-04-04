FROM fedora:43

# 1. Install sistem dependencies & tools monitoring (procps)
RUN dnf -y install \
    php-fpm php-cli php-gd php-mbstring php-xml php-zip \
    php-pdo php-sqlite3 php-bcmath php-intl php-json \
    nodejs npm \
    unzip git curl procps-ng iproute \
    && dnf clean all
# 2. Setup User & Group
RUN useradd nginx || true

RUN mkdir -p /run/php-fpm /var/log/php-fpm && \
    chown -R 1000:1000 /run/php-fpm /var/log/php-fpm

# 2. Konfigurasi PHP-FPM Global
RUN sed -i 's|^error_log = .*|error_log = /proc/self/fd/2|g' /etc/php-fpm.conf && \
    sed -i 's|^pid = .*|pid = /run/php-fpm/php-fpm.pid|g' /etc/php-fpm.conf

# 3. Konfigurasi Pool (www.conf)
RUN sed -i 's|^listen = .*|listen = 9001|g' /etc/php-fpm.d/www.conf && \
    # Kuncinya di sini: Matikan user/group bawaan agar mengikuti user dari Podman
    sed -i 's|^user = .*|;user = 1000|g' /etc/php-fpm.d/www.conf && \
    sed -i 's|^group = .*|;group = 1000|g' /etc/php-fpm.d/www.conf && \
    # Optimasi Log & Env
    echo "catch_workers_output = yes" >> /etc/php-fpm.d/www.conf && \
    echo "clear_env = no" >> /etc/php-fpm.d/www.conf

RUN mkdir -p /run/php-fpm /var/www/storage /var/www/bootstrap/cache && \
    chmod -R 775 /tmp && \
    chmod -R 775 /run/php-fpm && \
    chown -R nginx:nginx /var/www/storage /var/www/bootstrap/cache

COPY --from=docker.io/library/composer:latest /usr/bin/composer /usr/bin/composer

USER 1000

WORKDIR /var/www

EXPOSE 9001

CMD ["php-fpm", "-F"]