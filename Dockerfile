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

# 5. Atur kepemilikan agar sesuai dengan www-data
# Ini memperbaiki masalah "Permission Denied" dan "tempnam()"
RUN chown -R www-data:www-data /var/www \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# 6. Jalankan kontainer sebagai user www-data
USER www-data

EXPOSE 9000

CMD ["php-fpm"]