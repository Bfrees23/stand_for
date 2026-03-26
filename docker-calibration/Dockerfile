FROM php:8.2-apache

# Установка расширений PHP
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    zip \
    git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd \
    && docker-php-ext-install pdo pdo_mysql mysqli zip \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# Настройка Apache
COPY apache.conf /etc/apache2/sites-available/000-default.conf

# Копирование файлов приложения
COPY . /var/www/html/

# Права доступа
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Открываем порт 80
EXPOSE 80

# Запуск Apache
CMD ["apache2-foreground"]
