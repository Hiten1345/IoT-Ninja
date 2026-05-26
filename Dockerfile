FROM php:8.2-apache

# Install system dependencies, Node.js (v18), and NPM
RUN apt-get update && apt-get install -y \
    curl \
    gnupg \
    git \
    unzip \
    && curl -fsSL https://deb.nodesource.com/setup_18.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache rewrite and proxy modules for WebSockets
RUN a2enmod rewrite proxy proxy_http proxy_wstunnel headers

# Set Apache DocumentRoot to /var/www/html
ENV APACHE_DOCUMENT_ROOT /var/www/html
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Configure custom PHP settings (upload limits, timezone, etc.)
RUN echo "file_uploads = On\nmemory_limit = 256M\nupload_max_filesize = 64M\npost_max_size = 64M\nmax_execution_time = 600\ndate.timezone = Asia/Kolkata" > /usr/local/etc/php/conf.d/custom.ini

WORKDIR /var/www/html

# Copy all application files
COPY . .

# Install Node.js dependencies
RUN npm install --production

# Create data directory and ensure correct permissions for SQLite
RUN mkdir -p /var/www/html/data && chmod -R 777 /var/www/html/data

# Make start script executable
RUN chmod +x start.sh

# Expose HTTP port 80
EXPOSE 80

# Command to execute the startup script
CMD ["./start.sh"]
