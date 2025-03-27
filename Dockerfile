# Базовый образ с PHP 8.2
FROM php:8.2-cli

# Устанавливаем зависимости
RUN apt-get update && \
    apt-get install -y \
    libxml2-dev \
    curl \
    && docker-php-ext-install dom

# Рабочая директория
WORKDIR /app

# Копируем файлы проекта
COPY . .

# Устанавливаем Composer
RUN curl -sS https://getcomposer.org/installer | php -- \
    --install-dir=/usr/local/bin \
    --filename=composer

# Ставим зависимости из composer.json
RUN composer install

# Команда для запуска
CMD ["php", "ozon_parser.php"]
