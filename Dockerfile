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
