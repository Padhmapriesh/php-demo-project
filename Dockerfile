FROM php:8.2-apache

# Install mysqli extension (needed for MySQL/RDS connectivity)
RUN docker-php-ext-install mysqli \
    && docker-php-ext-enable mysqli

# Copy application source into Apache's web root
COPY src/ /var/www/html/

# Apache permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]
