# Use official PHP-Apache base image
FROM php:8.2-apache

# Install system dependencies required for extensions
RUN apt-get update && apt-get install -y \
    libssl-dev \
    unzip \
    git \
    && rm -rf /var/lib/apt/lists/*

# Install PDO MySQL extension
RUN docker-php-ext-install pdo_mysql

# Install PECL extensions (MongoDB and Redis) and enable them
RUN pecl install mongodb redis \
    && docker-php-ext-enable mongodb redis

# Enable Apache rewrite module for clean routing if needed
RUN a2enmod rewrite

# Copy all project files into Apache's web root
COPY . /var/www/html/

# Copy the startup entrypoint script and make it executable
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Set working directory
WORKDIR /var/www/html/

# Expose default HTTP port
EXPOSE 80

# Use custom entrypoint to resolve multi-MPM conflicts at startup
ENTRYPOINT ["/entrypoint.sh"]
