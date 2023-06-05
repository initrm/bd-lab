# immagine di partenza apache server con php installato
FROM php:8.2.6-apache
# installazione pg php 
RUN apt-get update && apt-get install libpq-dev -y && docker-php-ext-install pgsql