ARG DOCKER_PLATFORM=linux/amd64
FROM --platform=${DOCKER_PLATFORM} nginx:alpine

# Kopiere die Nginx-Konfiguration
COPY conf.d /etc/nginx/conf.d/

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]