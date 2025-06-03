FROM php:8.2-fpm-alpine

# Get the current user's UID and GID on the host
ARG USER_ID
ARG GROUP_ID

WORKDIR /app

RUN apk add --no-cache --update libpq-dev postgresql-client

# Install necessary extensions
RUN docker-php-ext-install pdo pdo_mysql pdo_pgsql

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copy the application code
COPY . /app/

# Change the UID and GID of the existing www-data user and group
RUN if [ "$(id -u www-data)" = "82" ] && [ "$(id -g www-data)" = "82" ]; then \
        sed -i "s/www-data:x:82:82/www-data:x:${USER_ID}:${GROUP_ID}/" /etc/passwd && \
        sed -i "s/www-data:x:82/www-data:x:${GROUP_ID}/" /etc/group; \
    fi

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Copy environment file
COPY .env.example .env

# Generate application key
RUN php artisan key:generate --ansi

# Set the user to www-data and set correct permissions
RUN chown -R www-data:www-data /app
RUN chmod -R 755 /app/bootstrap/cache /app/storage

# Expose port 8000 (Laravel's default)
EXPOSE 8000

# Set the user to www-data
USER www-data

# Serve the application using Artisan serve (for development)
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]