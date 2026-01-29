# Use official PHP image with Apache
FROM php:8.1-apache

# Enable required PHP extensions
RUN apt-get update && apt-get install -y libpq-dev && \
	docker-php-ext-install mysqli pdo pdo_mysql pdo_pgsql pgsql && \
	rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite for URL rewriting
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Set permissions
RUN chown -R www-data:www-data /var/www/html

# Expose port
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
