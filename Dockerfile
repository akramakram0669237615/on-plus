FROM php:8.3-apache
RUN apt-get update && apt-get install -y libpq-dev curl     && docker-php-ext-install pdo pdo_pgsql curl     && a2enmod rewrite
WORKDIR /var/www/html
COPY . /var/www/html
RUN printf '<VirtualHost *:80>\nDocumentRoot /var/www/html/public\n<Directory /var/www/html/public>\nAllowOverride All\nRequire all granted\n</Directory>\n</VirtualHost>\n' > /etc/apache2/sites-available/000-default.conf
CMD ["sh","-c","P=${PORT:-10000}; sed -ri "s/^Listen .*/Listen ${P}/" /etc/apache2/ports.conf; sed -ri "s#<VirtualHost \*:.*>#<VirtualHost *:${P}>#" /etc/apache2/sites-available/000-default.conf; apache2-foreground"]
