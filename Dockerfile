# 운영과 비슷한 환경: PHP 8.3 + Apache. 웹에 공개되는 폴더는 public/ 하나뿐이다.
FROM php:8.3-apache

RUN docker-php-ext-install pdo_mysql \
 && a2enmod headers \
 && sed -ri 's!/var/www/html!/var/www/app/public!g' /etc/apache2/sites-available/000-default.conf \
 && printf 'ServerTokens Prod\nServerSignature Off\n' > /etc/apache2/conf-enabled/zz-security.conf \
 && printf 'expose_php = Off\ndisplay_errors = Off\nlog_errors = On\nsession.use_strict_mode = 1\ndate.timezone = Asia/Seoul\n' > /usr/local/etc/php/conf.d/app.ini

WORKDIR /var/www/app
COPY src/ src/
COPY views/ views/
COPY public/ public/
COPY bin/ bin/
COPY db/ db/

RUN chown -R www-data:www-data /var/www/app
