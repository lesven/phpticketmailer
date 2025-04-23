ARG DOCKER_PLATFORM=linux/arm64v8
FROM --platform=${DOCKER_PLATFORM} nginx:alpine

# Kopiere die Nginx-Konfiguration
COPY conf.d /etc/nginx/conf.d/

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]