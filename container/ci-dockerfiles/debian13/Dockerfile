FROM docker.io/library/debian:13

RUN apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
        apache2 \
        ca-certificates \
        git \
        libapache2-mod-php \
        netcat-openbsd \
        php-apcu \
        php-cli \
        php-curl \
        php-gd \
        php-intl \
        php-mysql \
        php-pear \
        tzdata \
    && pear channel-update pear.php.net \
    && pear install Net_DNS2 \
    && a2enmod rewrite \
    && mkdir -p /usr/share/fonts/dejavu \
    && ln -s /usr/share/fonts/truetype/dejavu/DejaVuSansMono.ttf \
        /usr/share/fonts/dejavu/DejaVuSansMono.ttf \
    && rm -rf /var/lib/apt/lists/*

ENV DaGdConfigFile=../container/config.container.php

WORKDIR /srv/dagd

EXPOSE 80

ENTRYPOINT ["bash", "./container/entrypoint.sh"]
