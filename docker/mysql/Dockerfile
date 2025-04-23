ARG DOCKER_PLATFORM=linux/amd64
FROM --platform=${DOCKER_PLATFORM} linuxserver/mariadb:latest

# linuxserver/mariadb bietet Multi-Arch-Unterstützung (inkl. ARM)
# WICHTIG: linuxserver/mariadb verwendet andere Umgebungsvariablen, 
# daher müssen die Variablen in der docker-compose.yml angepasst werden

EXPOSE 3306

CMD ["mysqld"]