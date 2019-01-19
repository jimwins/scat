FROM php:7.3.0-fpm-alpine

LABEL maintainer="Jim Winstead <jimw@trainedmonkey.com>"

RUN apk add --no-cache \
      gifsicle \
      jpegoptim \
      libzip-dev \
      optipng \
      pngquant \
      mysql-client \
      tzdata \
      zip \
      zlib-dev

RUN docker-php-ext-install \
      bcmath \
      mysqli \
      pdo \
      pdo_mysql \
      zip

WORKDIR /app

COPY . /app

RUN curl -sS https://getcomposer.org/installer | php \
        && mv composer.phar /usr/local/bin/ \
        && ln -s /usr/local/bin/composer.phar /usr/local/bin/composer

RUN composer install --prefer-source --no-interaction
