# Use an official PHP image as the base image
FROM php:8.0-apache

# Set the working directory
WORKDIR /var/www/html

# Copy the application code into the container
COPY . .

# Install dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd \
    && docker-php-ext-install pdo pdo_mysql zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install application dependencies
RUN composer install

# Expose port 80
EXPOSE 80

# Start the Apache server
CMD ["apache2-foreground"]

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set memory limit for Composer
ENV COMPOSER_MEMORY_LIMIT=-1

# Install application dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction