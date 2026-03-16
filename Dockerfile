FROM php:8.5-fpm

# 1. Instal dependencies sistem yang benar-benar esensial
RUN apt-get update && apt-get install -y \
    zip \
    unzip \
    git \
    curl \
    sqlite3 \
    libsqlite3-dev \
    procps \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# 2. Konfigurasi dan Install ekstensi PHP
# 2. Install ekstensi PHP (Tanpa GD)
RUN docker-php-ext-configure intl \
    && docker-php-ext-install -j$(nproc) \
    intl \
    pdo_sqlite \
    mbstring \
    exif \
    pcntl \
    bcmath \
    zip \
    opcache

# 3. Salin konfigurasi PHP kustom
COPY ./uploads.ini /usr/local/etc/php/conf.d/uploads.ini

# 4. Install Node.js 20
RUN curl -sL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# 5. Ambil Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# --- TAMBAHAN PENTING: User Permissions ---
# Sesuaikan agar file yang dibuat kontainer bisa diedit oleh user NUC Anda
# Tambahkan user agar sinkron dengan host
RUN useradd -G www-data,root -u 1000 -d /home/jonathan jonathan

# Pindah ke user tersebut agar tidak menjalankan perintah sebagai root
USER jonathan

WORKDIR /var/www

EXPOSE 9000
CMD ["php-fpm"] 