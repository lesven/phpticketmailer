ARG DOCKER_PLATFORM=linux/amd64
FROM --platform=${DOCKER_PLATFORM} mariadb:10.6

# MariaDB wird als Alternative zu MySQL verwendet, da es bessere ARM-Unterstützung bietet
# Für ARM-Plattformen wie Raspberry Pi ist MariaDB oft stabiler

EXPOSE 3306

CMD ["mysqld"]