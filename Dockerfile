FROM php:7.1.0-fpm-alpine

RUN docker-php-ext-install mysqli pdo pdo_mysql && docker-php-ext-enable pdo_mysql


RUN mkdir /php && mkdir /php/data

RUN chown -R www-data:www-data /php/data

RUN chmod -R 755 /php/data
