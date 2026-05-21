FROM php:8.4-cli-alpine

WORKDIR /app

COPY docker/provider/server.php /app/server.php
COPY docker/provider/generator.php /app/generator.php
COPY resources /app/resources

EXPOSE 8080

CMD ["php", "-S", "0.0.0.0:8080", "server.php"]
