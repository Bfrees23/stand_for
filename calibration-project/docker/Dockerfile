FROM php:8.2-apache

# Install extensions and serial port tools
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    git \
    setserial \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd mysqli pdo pdo_mysql \
    && a2enmod rewrite headers

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY src/ /var/www/html/

# Set permissions for serial ports and application
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && usermod -a -G dialout www-data

# Apache configuration
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Expose port 80
EXPOSE 80

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

CMD ["apache2-foreground"]
