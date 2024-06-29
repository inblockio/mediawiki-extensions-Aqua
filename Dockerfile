FROM ghcr.io/inblockio/aqua-docker-mediawiki:v1.0.7-alpha
#FROM inblockio/micro-pkc-mediawiki:1.0.0-alpha.5
WORKDIR /var/www/html
RUN mkdir ./extensions/DataAccounting
COPY . ./extensions/DataAccounting/
