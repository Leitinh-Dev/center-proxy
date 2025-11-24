FROM php:8.2-cli

WORKDIR /app

# Copiar o arquivo proxy
COPY render_proxy.php .

# Expor a porta (Render.com usa variável $PORT dinâmica)
# Não precisa EXPOSE fixo, Render.com gerencia isso

# Comando para iniciar o servidor PHP usando a porta do Render.com
# O Render.com fornece a variável $PORT automaticamente
CMD php -S 0.0.0.0:${PORT} render_proxy.php

