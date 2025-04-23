FROM alpine:3.18

# Installiere Postfix für einen einfachen SMTP-Server
RUN apk add --no-cache postfix

# Konfiguriere Postfix für ein einfaches Setup
RUN postconf -e 'inet_interfaces = all' \
    && postconf -e 'inet_protocols = ipv4' \
    && postconf -e 'mydestination = localhost.localdomain, localhost' \
    && postconf -e 'mynetworks = 0.0.0.0/0' \
    && postconf -e 'smtpd_recipient_restrictions = permit_mynetworks' \
    && postconf -e 'maillog_file = /dev/stdout'

# Erstelle ein Startskript
RUN echo '#!/bin/sh' > /start.sh \
    && echo 'postfix start-fg' >> /start.sh \
    && chmod +x /start.sh

EXPOSE 25

CMD ["/start.sh"]