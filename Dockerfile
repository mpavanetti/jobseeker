FROM php:7.4-fpm-alpine

RUN docker-php-ext-install mysqli pdo pdo_mysql && docker-php-ext-enable pdo_mysql

RUN apk add --no-cache zip libzip-dev
RUN docker-php-ext-configure zip
RUN docker-php-ext-install zip


RUN mkdir /php && mkdir /php/data

RUN chown -R www-data:www-data /php/data

RUN chmod -R 755 /php/data


RUN addgroup --gid 1000 mygroup
RUN adduser --system --no-create-home --disabled-password --uid 1000 --ingroup mygroup myuser

RUN chown -R myuser:mygroup /var/www

USER myuser