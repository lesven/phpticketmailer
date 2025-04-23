ARG DOCKER_PLATFORM=linux/amd64
FROM --platform=${DOCKER_PLATFORM} mariadb:10.5

# MariaDB 10.5 bietet gute ARM-Unterstützung für Raspberry Pi

EXPOSE 3306

CMD ["mysqld"]