FROM php:8.3-apache

RUN apt-get update && apt-get install -y \
    libpq-dev \
    curl \
    unzip \
    && docker-php-ext-install pdo pdo_pgsql \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY . /var/www/html/

RUN printf '%s\n' \
    '<VirtualHost *:80>' \
    '    DocumentRoot /var/www/html/public' \
    '    <Directory /var/www/html/public>' \
    '        AllowOverride All' \
    '        Require all granted' \
    '    </Directory>' \
    '</VirtualHost>' \
    > /etc/apache2/sites-available/000-default.conf

EXPOSE 10000

CMD ["sh", "-c", "P=${PORT:-10000}; sed -ri \"s/^Listen .*/Listen ${P}/\" /etc/apache2/ports.conf; sed -ri \"s#<VirtualHost \\*:.*>#<VirtualHost *:${P}>#\" /etc/apache2/sites-available/000-default.conf; apache2-foreground"]
