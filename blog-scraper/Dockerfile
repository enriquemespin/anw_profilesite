FROM php:8.2-cli

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_sqlite curl xml

# Set working directory
WORKDIR /app

# Copy application files
COPY . .

# Create data directory with proper permissions
RUN mkdir -p /app/data/cache && chmod 755 /app/data

# Make run.php executable
RUN chmod +x run.php

# Default command
CMD ["php", "run.php", "help"]