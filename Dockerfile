FROM php:8.2-cli

# Instala o driver PDO para MySQL
RUN docker-php-ext-install pdo pdo_mysql

WORKDIR /app

COPY . /app

EXPOSE 8000
CMD ["php", "-S", "0.0.0.0:8000", "index.php"]

