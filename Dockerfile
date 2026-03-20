FROM php:8.4-fpm

# 1. Install sistem dependencies & tools monitoring (procps)
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    zip \
    libzip-dev \
    unzip \
    git \
    curl \
    procps \
    libicu-dev \
    libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        gd \
        pdo_mysql \
        zip \
        intl \
        bcmath \
        mbstring

# 2. Ambil Composer terbaru
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 3. Set working directory
WORKDIR /var/www

# 4. Salin semua file proyek ke kontainer
COPY . /var/www

# 5. Gunakan user 'server' (UID 1000) agar sama dengan user NUC Anda
RUN groupadd -g 1000 server \
    && useradd -u 1000 -ms /bin/bash -g server server

# 6. Set owner ke 'server'
COPY --chown=server:server . /var/www

# Pastikan folder krusial bisa ditulis oleh user 1000
RUN chmod -R 775 /var/www/storage /var/www/bootstrap/cache /var/www/database

USER server

EXPOSE 9000

CMD ["php-fpm"]