FROM php:8.2-fpm

ARG uid
ARG username

RUN docker-php-ext-install exif pdo pdo_mysql
RUN curl -fsSL https://deb.nodesource.com/setup_current.x | bash - \
    && apt-get install -y nodejs git zip unzip \
    && pecl install redis && docker-php-ext-enable redis

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN useradd -G www-data,root -u $uid -d /home/$username $username
RUN mkdir -p /home/$username/.composer && \
    chown -R $username:$username /home/$username
