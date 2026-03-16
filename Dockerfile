FROM php:8.5-fpm

# 1. Instal dependencies sistem (OS Level)
# Hapus default-mysql-client, tambahkan sqlite3 jika butuh akses CLI
RUN apt-get update && apt-get install -y \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libjpeg-dev \
    libpng-dev \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    libicu-dev \
    zip \
    unzip \
    git \
    curl \
    sqlite3 \
    libsqlite3-dev \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# 2. Konfigurasi dan Install ekstensi PHP
# Hapus pdo_mysql, tambahkan pdo_sqlite
# Tambahkan 'intl' di baris instalasi ekstensi
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j$(nproc) \
    gd \
    intl \
    pdo_sqlite \
    mbstring \
    exif \
    pcntl \
    bcmath \
    zip

# 3. Salin konfigurasi PHP kustom
COPY ./uploads.ini /usr/local/etc/php/conf.d/uploads.ini

# 4. Install Node.js 20
RUN curl -sL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# 5. Ambil Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

EXPOSE 9000
CMD ["php-fpm"]