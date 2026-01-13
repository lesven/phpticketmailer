# Automatisches Deployment via GitHub Actions und Webhook

Dieses Dokument beschreibt die Einrichtung und Konfiguration des automatischen Deployments für das Ticketumfrage-Tool.

## Übersicht

Das automatische Deployment funktioniert wie folgt:

1. **Push auf develop Branch** → Löst GitHub Actions Workflow aus
2. **GitHub Actions** → Sendet signierten Webhook-Request an Test-Server
3. **Test-Server** → Empfängt Webhook, validiert Signatur und führt `make deploy` aus

## Sicherheit

- ✅ HTTPS-only Kommunikation
- ✅ HMAC-SHA256 Signaturvalidierung
- ✅ Secret-basierte Authentifizierung
- ✅ Logging aller Deployment-Aktivitäten
- ✅ Timing-Attack-sichere Signaturprüfung

## Voraussetzungen

- GitHub Repository mit Admin-Zugriff (für Secrets)
- Test-Server mit HTTPS-Zugang
- PHP 7.4+ auf dem Test-Server
- Web-Server (nginx oder Apache) mit HTTPS konfiguriert
- Git auf dem Test-Server installiert
- Docker und Docker Compose auf dem Test-Server installiert

## Installation und Konfiguration

### 1. Server-Vorbereitung

#### 1.1 Projekt auf Server klonen

```bash
# Als Benutzer mit entsprechenden Rechten
cd /var/www
# Ersetzen Sie YOUR_USER/YOUR_REPO mit Ihrer tatsächlichen Repository-URL
git clone https://github.com/YOUR_USER/YOUR_REPO.git phpticketmailer
cd phpticketmailer
git checkout develop
```

#### 1.2 Webhook-Secret generieren

Generieren Sie ein sicheres Secret (mindestens 32 Zeichen):

```bash
openssl rand -hex 32
```

Speichern Sie dieses Secret sicher - es wird sowohl auf dem Server als auch in GitHub benötigt.

#### 1.3 Webhook-Secret auf Server konfigurieren

Fügen Sie das Secret zur `.env` Datei hinzu:

```bash
cd /var/www/phpticketmailer
echo "WEBHOOK_SECRET=IHR_GENERIERTES_SECRET_HIER" >> .env
```

Oder setzen Sie es als Umgebungsvariable:

```bash
export WEBHOOK_SECRET="IHR_GENERIERTES_SECRET_HIER"
```

#### 1.4 Webhook-Verzeichnis einrichten

```bash
# Webhook-Empfänger-Skript an zugängliche Stelle kopieren oder symlinken
mkdir -p /var/www/webhook
cp webhook-receiver.php /var/www/webhook/
chmod 755 /var/www/webhook
chmod 644 /var/www/webhook/webhook-receiver.php
```

#### 1.5 Projekt-Pfad konfigurieren (Optional)

Der Webhook-Receiver erkennt automatisch den Projekt-Pfad, wenn er im Projekt-Verzeichnis liegt.

**Automatische Erkennung:**
- Wenn `webhook-receiver.php` im Projekt-Root liegt, wird der Pfad automatisch erkannt
- Wenn es in einem Unterverzeichnis liegt (z.B. `/var/www/webhook/`), versucht es `__DIR__ . '/..`

**Manuelle Konfiguration (falls nötig):**

Option 1 - Umgebungsvariable (empfohlen):
```bash
# In nginx/apache PHP-FPM Konfiguration oder systemd service
fastcgi_param PROJECT_ROOT /var/www/phpticketmailer;
```

Option 2 - .env Datei:
```bash
echo "PROJECT_ROOT=/var/www/phpticketmailer" >> /var/www/phpticketmailer/.env
```

Option 3 - Direkt in webhook-receiver.php anpassen (Zeile ~50):
```php
define('PROJECT_ROOT', '/var/www/phpticketmailer');
```

### 2. Web-Server Konfiguration

#### Option A: nginx

Erstellen oder erweitern Sie Ihre nginx-Konfiguration:

```nginx
server {
    listen 443 ssl http2;
    server_name ihr-testserver.example.com;
    
    # SSL-Zertifikate
    ssl_certificate /etc/ssl/certs/ihr-zertifikat.crt;
    ssl_certificate_key /etc/ssl/private/ihr-schluessel.key;
    
    # SSL-Sicherheit
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;
    
    # Webhook-Endpoint
    location /deploy-webhook {
        alias /var/www/webhook;
        index webhook-receiver.php;
        
        # Zugriff nur auf PHP-Datei
        location ~ \.php$ {
            include snippets/fastcgi-php.conf;
            fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
            fastcgi_param SCRIPT_FILENAME /var/www/webhook/webhook-receiver.php;
        }
        
        # Keine anderen Dateien zugänglich machen
        location ~ /\. {
            deny all;
        }
    }
    
    # Hauptanwendung (falls auf gleichem Server)
    location / {
        proxy_pass http://localhost:8090;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

Nginx neu laden:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

#### Option B: Apache

Erstellen oder erweitern Sie Ihre Apache-Konfiguration:

```apache
<VirtualHost *:443>
    ServerName ihr-testserver.example.com
    
    # SSL-Konfiguration
    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/ihr-zertifikat.crt
    SSLCertificateKeyFile /etc/ssl/private/ihr-schluessel.key
    
    # Webhook-Verzeichnis
    Alias /deploy-webhook /var/www/webhook/webhook-receiver.php
    <Directory /var/www/webhook>
        Options -Indexes +FollowSymLinks
        AllowOverride None
        Require all granted
        
        <FilesMatch "\.php$">
            SetHandler "proxy:unix:/var/run/php/php8.3-fpm.sock|fcgi://localhost"
        </FilesMatch>
    </Directory>
    
    # Hauptanwendung (falls auf gleichem Server)
    ProxyPreserveHost On
    ProxyPass /deploy-webhook !
    ProxyPass / http://localhost:8090/
    ProxyPassReverse / http://localhost:8090/
</VirtualHost>
```

Apache neu laden:

```bash
sudo apachectl configtest
sudo systemctl reload apache2
```

### 3. Berechtigungen einrichten

Der Web-Server-Benutzer (normalerweise `www-data` oder `nginx`) benötigt Berechtigungen für:

1. Schreibzugriff auf Log-Datei
2. Lesezugriff auf Projekt-Verzeichnis
3. Ausführungsrechte für Docker-Befehle

```bash
# Log-Datei vorbereiten
touch /var/www/webhook/webhook-deploy.log
chown www-data:www-data /var/www/webhook/webhook-deploy.log
chmod 644 /var/www/webhook/webhook-deploy.log

# Projektverzeichnis
chown -R www-data:www-data /var/www/phpticketmailer

# Docker-Berechtigung (Option 1: www-data zur docker-Gruppe hinzufügen)
sudo usermod -aG docker www-data

# Oder Option 2: sudo ohne Passwort für spezifische Befehle
sudo visudo
# Fügen Sie hinzu:
# www-data ALL=(ALL) NOPASSWD: /usr/bin/docker, /usr/bin/docker-compose, /usr/bin/make
```

**Wichtig:** Nach Änderung der Benutzergruppen Web-Server neu starten:

```bash
sudo systemctl restart nginx  # oder apache2
sudo systemctl restart php8.3-fpm
```

### 4. GitHub Secrets konfigurieren

1. Gehen Sie zu Ihrem GitHub Repository: `https://github.com/YOUR_USER/YOUR_REPO`
2. Navigieren Sie zu **Settings** → **Secrets and variables** → **Actions**
3. Klicken Sie auf **New repository secret**
4. Fügen Sie zwei Secrets hinzu:

**DEPLOY_WEBHOOK_URL:**
```
https://ihr-testserver.example.com/deploy-webhook
```

**DEPLOY_WEBHOOK_SECRET:**
```
IHR_GENERIERTES_SECRET_HIER
```

(Das gleiche Secret, das Sie vorher generiert und auf dem Server konfiguriert haben)

### 5. Deployment testen

#### 5.1 Manueller Test des Webhook-Empfängers

```bash
# Test-Payload erstellen (ersetzen Sie YOUR_USER/YOUR_REPO mit Ihrem Repository)
PAYLOAD='{"ref":"refs/heads/develop","repository":"YOUR_USER/YOUR_REPO","commit":"test123","commit_message":"Test deployment","pusher":"test-user","timestamp":1234567890}'

# Secret (ersetzen Sie mit Ihrem echten Secret)
SECRET="IHR_GENERIERTES_SECRET_HIER"

# Signatur generieren
SIGNATURE=$(echo -n "$PAYLOAD" | openssl dgst -sha256 -hmac "$SECRET" | sed 's/^.* //')

# Webhook-Request senden
curl -X POST \
  -H "Content-Type: application/json" \
  -H "X-Hub-Signature-256: sha256=$SIGNATURE" \
  -H "X-GitHub-Event: push" \
  --data "$PAYLOAD" \
  https://ihr-testserver.example.com/deploy-webhook

# Log-Datei prüfen
cat /var/www/webhook/webhook-deploy.log
```

#### 5.2 Test über GitHub Actions

1. Machen Sie eine kleine Änderung im develop Branch
2. Committen und pushen Sie:

```bash
git checkout develop
echo "# Test deployment" >> README.md
git add README.md
git commit -m "Test: Trigger deployment workflow"
git push origin develop
```

3. Überprüfen Sie den Workflow:
   - Gehen Sie zu **Actions** im GitHub Repository
   - Sehen Sie sich den laufenden/abgeschlossenen Workflow an
   - Prüfen Sie die Logs

4. Überprüfen Sie auf dem Server:

```bash
# Deployment-Log prüfen
tail -f /var/www/webhook/webhook-deploy.log

# Docker-Container-Status prüfen
cd /var/www/phpticketmailer
docker compose ps

# Git-Status prüfen
git log -1
```

## Troubleshooting

### Problem: "Invalid signature" Fehler

**Lösung:**
- Stellen Sie sicher, dass das Secret in GitHub und auf dem Server identisch ist
- Prüfen Sie, ob keine zusätzlichen Leerzeichen im Secret sind
- Testen Sie mit dem manuellen curl-Befehl oben

### Problem: "Permission denied" beim Deployment

**Lösung:**
```bash
# Prüfen Sie Berechtigungen
ls -la /var/www/phpticketmailer
ls -la /var/www/webhook

# Prüfen Sie Web-Server-Benutzer
ps aux | grep nginx  # oder apache2

# Stellen Sie sicher, dass www-data in docker-Gruppe ist
groups www-data

# Web-Server neu starten nach Gruppenänderungen
sudo systemctl restart nginx php8.3-fpm
```

### Problem: Webhook wird nicht aufgerufen

**Lösung:**
- Prüfen Sie die GitHub Actions Logs
- Stellen Sie sicher, dass DEPLOY_WEBHOOK_URL korrekt ist (inklusive https://)
- Testen Sie den Webhook-Endpoint manuell mit curl
- Prüfen Sie Firewall-Regeln auf dem Server

### Problem: make deploy schlägt fehl

**Lösung:**
```bash
# Manuell testen
cd /var/www/phpticketmailer
make deploy

# Docker-Logs prüfen
docker compose logs

# Git-Status prüfen
git status
```

### Log-Dateien überprüfen

```bash
# Webhook-Logs
tail -f /var/www/webhook/webhook-deploy.log

# nginx-Logs
sudo tail -f /var/log/nginx/access.log
sudo tail -f /var/log/nginx/error.log

# PHP-FPM-Logs
sudo tail -f /var/log/php8.3-fpm.log

# Docker-Logs
cd /var/www/phpticketmailer
docker compose logs -f
```

## Wartung

### Log-Rotation einrichten

Erstellen Sie `/etc/logrotate.d/webhook-deploy`:

```
/var/www/webhook/webhook-deploy.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0644 www-data www-data
}
```

### Deployment-History überwachen

```bash
# Deployment-Log analysieren
grep "SUCCESS" /var/www/webhook/webhook-deploy.log | tail -10
grep "ERROR" /var/www/webhook/webhook-deploy.log | tail -10

# Letzte Deployments
grep "Deployment triggered by" /var/www/webhook/webhook-deploy.log | tail -5
```

## Sicherheitshinweise

1. **Webhook-Secret sicher aufbewahren** - Niemals in Git committen
2. **HTTPS verwenden** - Keine unverschlüsselte Kommunikation
3. **Firewall konfigurieren** - Nur notwendige Ports öffnen
4. **Logs überwachen** - Regelmäßig auf verdächtige Aktivitäten prüfen
5. **Berechtigungen minimal halten** - Web-Server-Benutzer nur notwendige Rechte geben
6. **Regelmäßige Updates** - Server, PHP, Docker auf aktuellem Stand halten

## Erweiterte Konfiguration

### Nur von GitHub IPs akzeptieren

Nginx-Konfiguration erweitern:

```nginx
location /deploy-webhook {
    # GitHub IP-Bereiche (regelmäßig aktualisieren!)
    allow 140.82.112.0/20;
    allow 143.55.64.0/20;
    allow 185.199.108.0/22;
    allow 192.30.252.0/22;
    deny all;
    
    # Rest der Konfiguration...
}
```

### E-Mail-Benachrichtigung bei Deployment

Ergänzen Sie `webhook-receiver.php` nach erfolgreichem Deployment:

```php
// Nach erfolgreichem Deployment (vor sendResponse)
mail('admin@example.com', 
     'Deployment erfolgreich', 
     "Deployment wurde erfolgreich ausgeführt.\n\nCommit: " . $data['commit']);
```

### Slack-Benachrichtigung

```bash
# In webhook-receiver.php oder als separates Script
curl -X POST -H 'Content-type: application/json' \
  --data '{"text":"Deployment auf Test-Server erfolgreich!"}' \
  https://hooks.slack.com/services/YOUR/SLACK/WEBHOOK
```

## Weitere Ressourcen

- [GitHub Actions Documentation](https://docs.github.com/en/actions)
- [Securing webhooks](https://docs.github.com/en/webhooks/using-webhooks/securing-your-webhooks)
- [nginx SSL Configuration](https://nginx.org/en/docs/http/configuring_https_servers.html)
