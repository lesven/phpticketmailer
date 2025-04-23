ARG DOCKER_PLATFORM=linux/arm64v8
FROM --platform=${DOCKER_PLATFORM} yobasystems/alpine-mariadb:10.5.12

# Alpine-basiertes MariaDB-Image mit Multiarch-Support inkl. ARM
# yobasystems bietet ARM-kompatible Images

EXPOSE 3306

CMD ["mysqld"]