FROM php:8.2-cli

WORKDIR /app

# Copiar o arquivo proxy
COPY render_proxy.php .

# Expor a porta
EXPOSE 8080

# Comando para iniciar o servidor PHP
CMD ["php", "-S", "0.0.0.0:8080", "render_proxy.php"]

