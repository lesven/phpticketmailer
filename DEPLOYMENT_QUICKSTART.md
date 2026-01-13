# Schnellstart-Anleitung: Automatisches Deployment

Diese Kurzanleitung beschreibt die wichtigsten Schritte zur Einrichtung des automatischen Deployments. Für Details siehe [DEPLOYMENT_WEBHOOK.md](DEPLOYMENT_WEBHOOK.md).

## 1. Webhook-Secret generieren

```bash
openssl rand -hex 32
```

Beispiel-Ausgabe: `a1b2c3d4e5f6...` (64 Zeichen)

**Wichtig:** Notieren Sie dieses Secret sicher!

## 2. Server vorbereiten

### 2.1 Projekt klonen

```bash
cd /var/www
# Ersetzen Sie YOUR_USER/YOUR_REPO mit Ihrer tatsächlichen Repository-URL
git clone https://github.com/YOUR_USER/YOUR_REPO.git phpticketmailer
cd phpticketmailer
git checkout develop
```

### 2.2 Webhook-Secret konfigurieren

```bash
# Secret zur .env hinzufügen
echo "WEBHOOK_SECRET=IHR_GENERIERTES_SECRET" >> .env
```

### 2.3 Webhook-Receiver installieren

```bash
# Webhook-Verzeichnis erstellen
mkdir -p /var/www/webhook
cp webhook-receiver.php /var/www/webhook/

# Optional: Projekt-Pfad über Umgebungsvariable setzen (falls Auto-Erkennung nicht funktioniert)
echo "PROJECT_ROOT=$(pwd)" >> .env

# Berechtigungen setzen
touch /var/www/webhook/webhook-deploy.log
chown -R www-data:www-data /var/www/webhook
chown -R www-data:www-data /var/www/phpticketmailer

# www-data zur docker-Gruppe hinzufügen
sudo usermod -aG docker www-data
```

### 2.4 Web-Server konfigurieren

**nginx:**

```nginx
# /etc/nginx/sites-available/webhook-deploy
server {
    listen 443 ssl http2;
    server_name ihr-server.example.com;
    
    ssl_certificate /etc/ssl/certs/cert.crt;
    ssl_certificate_key /etc/ssl/private/key.key;
    
    location /deploy-webhook {
        alias /var/www/webhook;
        index webhook-receiver.php;
        
        location ~ \.php$ {
            include snippets/fastcgi-php.conf;
            fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
            fastcgi_param SCRIPT_FILENAME /var/www/webhook/webhook-receiver.php;
        }
    }
}
```

```bash
# Konfiguration aktivieren
sudo ln -s /etc/nginx/sites-available/webhook-deploy /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
sudo systemctl restart php8.3-fpm
```

## 3. GitHub konfigurieren

1. Gehen Sie zu: https://github.com/YOUR_USER/YOUR_REPO/settings/secrets/actions
2. Klicken Sie auf **New repository secret**
3. Fügen Sie zwei Secrets hinzu:

**Secret 1:**
- Name: `DEPLOY_WEBHOOK_URL`
- Value: `https://ihr-server.example.com/deploy-webhook`

**Secret 2:**
- Name: `DEPLOY_WEBHOOK_SECRET`
- Value: `IHR_GENERIERTES_SECRET` (das gleiche wie auf dem Server)

## 4. Testen

### Manueller Test

```bash
# Verwenden Sie das test-webhook.sh Script
./test-webhook.sh \
  -u https://ihr-server.example.com/deploy-webhook \
  -s IHR_GENERIERTES_SECRET
```

### Test über Git Push

```bash
git checkout develop
echo "# Test" >> README.md
git add README.md
git commit -m "Test: Trigger auto deployment"
git push origin develop
```

### Logs überprüfen

```bash
# Webhook-Logs
tail -f /var/www/webhook/webhook-deploy.log

# Docker-Logs
cd /var/www/phpticketmailer
docker compose logs -f
```

## 5. GitHub Actions überwachen

- Gehen Sie zu: https://github.com/YOUR_USER/YOUR_REPO/actions
- Sehen Sie den Workflow "Deploy to Test Server (Develop)"
- Prüfen Sie die Logs bei Fehlern

## Häufige Probleme

### "Invalid signature"
- Prüfen Sie, ob das Secret in GitHub und auf dem Server identisch ist
- Keine Leerzeichen oder Zeilenumbrüche im Secret

### "Permission denied"
- Prüfen Sie: `groups www-data` (muss "docker" enthalten)
- Web-Server neu starten: `sudo systemctl restart nginx php8.3-fpm`

### "Connection refused"
- Firewall-Regel prüfen: `sudo ufw status`
- HTTPS-Zertifikat prüfen: `curl -v https://ihr-server.example.com/deploy-webhook`

## Vollständige Dokumentation

Siehe [DEPLOYMENT_WEBHOOK.md](DEPLOYMENT_WEBHOOK.md) für:
- Detaillierte Sicherheitskonfiguration
- Apache-Konfiguration
- Log-Rotation
- Erweiterte Optionen
- Troubleshooting

## Checkliste

- [ ] Webhook-Secret generiert
- [ ] Server: Projekt geklont
- [ ] Server: Webhook-Secret in .env
- [ ] Server: Webhook-Receiver installiert
- [ ] Server: Web-Server konfiguriert (nginx/Apache)
- [ ] Server: Berechtigungen gesetzt (www-data)
- [ ] Server: Web-Server neu gestartet
- [ ] GitHub: DEPLOY_WEBHOOK_URL Secret hinzugefügt
- [ ] GitHub: DEPLOY_WEBHOOK_SECRET Secret hinzugefügt
- [ ] Test: Manueller Webhook-Test erfolgreich
- [ ] Test: Git Push löst Deployment aus
- [ ] Logs: Deployment erfolgreich abgeschlossen
