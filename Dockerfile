FROM php:7.4.5-fpm-alpine

LABEL maintainer="Jim Winstead <jimw@trainedmonkey.com>"

RUN apk add --no-cache -X http://dl-cdn.alpinelinux.org/alpine/edge/testing \
      freetype-dev \
      gifsicle \
      jpegoptim \
      libjpeg-turbo-dev \
      libpng-dev \
      libzip-dev \
      optipng \
      php7-pecl-decimal \
      pngquant \
      mysql-client \
      tzdata \
      zip \
      zlib-dev

RUN docker-php-ext-install \
      bcmath \
      gd \
      mysqli \
      pdo \
      pdo_mysql \
      zip

WORKDIR /app

COPY . /app

RUN curl -sS https://getcomposer.org/installer | php \
        && mv composer.phar /usr/local/bin/ \
        && ln -s /usr/local/bin/composer.phar /usr/local/bin/composer

RUN composer install \
        --no-dev --no-interaction --no-progress --no-suggest \
        --optimize-autoloader --classmap-authoritative
