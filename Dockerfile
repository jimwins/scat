FROM php:8.1.16-fpm-alpine

LABEL maintainer="Jim Winstead <jimw@trainedmonkey.com>"

RUN apk add --no-cache -X http://dl-cdn.alpinelinux.org/alpine/edge/testing \
        freetype-dev \
        gifsicle \
        git \
        jpegoptim \
        libjpeg-turbo-dev \
        libpng-dev \
        libzip-dev \
        optipng \
        mpdecimal-dev \
        pngquant \
        mysql-client \
        tzdata \
        zip \
        zlib-dev \
        ${PHPIZE_DEPS} \
      && pecl install decimal \
      && docker-php-ext-enable decimal \
      && docker-php-ext-install \
          bcmath \
          gd \
          mysqli \
          pdo \
          pdo_mysql \
          zip \
      && apk del -dev ${PHPIZE_DEPS}

WORKDIR /app

COPY . /app

COPY log.conf /usr/local/etc/php-fpm.d/

RUN curl -sS https://getcomposer.org/installer | php \
        && mv composer.phar /usr/local/bin/ \
        && ln -s /usr/local/bin/composer.phar /usr/local/bin/composer

RUN composer install \
        --no-dev --no-interaction --no-progress \
        --optimize-autoloader --classmap-authoritative
