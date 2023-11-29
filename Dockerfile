# Discomp2AbraFlexi

FROM php:8.2-cli
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions && install-php-extensions gettext intl zip
COPY src /usr/src/discomp2abraflexi/src
RUN sed -i -e 's/..\/.env//' /usr/src/discomp2abraflexi/src/*.php
COPY composer.json /usr/src/discomp2abraflexi
WORKDIR /usr/src/discomp2abraflexi
RUN curl -s https://getcomposer.org/installer | php
RUN ./composer.phar install
WORKDIR /usr/src/discomp2abraflexi/src
CMD [ "php", "./discomp2abraflexi.php" ]
