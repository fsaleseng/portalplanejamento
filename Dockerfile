FROM php:8.2-cli

# Instala o servidor embutido do PHP e define o diretório de trabalho
WORKDIR /app

COPY . /app

# Exponha a porta do servidor PHP
EXPOSE 8000

# Comando para iniciar o servidor
CMD ["php", "-S", "0.0.0.0:8000"]
