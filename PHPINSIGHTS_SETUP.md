# PHPInsights Code Quality Analysis

## Überblick

PHPInsights ist ein automatisiertes Code-Quality-Analysis-Tool für PHP-Projekte. Es analysiert Code-Qualität, Komplexität, Architektur und Code-Style.

**Installation:** ✅ Abgeschlossen (v2.13.3)  
**Preset:** Symfony  
**Analysierte Pfade:** `src/`  
**Ausgenommene Pfade:** `vendor/`, `tests/`, `migrations/`, `templates/`, usw.

---

## Verwendung

### 1. **Einfache Console-Analyse**

```bash
make phpinsights
```

oder manuell:

```bash
docker compose exec -T php ./vendor/bin/phpinsights analyse src/ --no-interaction
```

### 2. **HTML-Report generieren**

```bash
make phpinsights-html
```

Der Report wird unter `build/phpinsights/report.html` abgespeichert.

### 3. **Analyse mit erweiterten Optionen**

```bash
# JSON-Format (für CI/CD)
docker compose exec -T php ./vendor/bin/phpinsights analyse src/ --format=json

# Verbose Output
docker compose exec -T php ./vendor/bin/phpinsights analyse src/ -v
```

---

## Ergebnisse der Initialanalyse (27.02.2026)

| Kategorie | Score | Status |
|-----------|-------|--------|
| **Gesamtergebnis** | 68.7% | ⚠️ Akzeptabel |
| Code | 69.0 pts | ⚠️ Mittelmäßig |
| Complexity | 81.2 pts | ✅ Gut |
| Architecture | 64.7 pts | ⚠️ Verbesserungsbedürftig |
| Style | - | - |

### Code-Qualität Details

- **Comments:** 60.7% (Dokumentation ausreichend)
- **Classes:** 30.9% (Verbesserungspotential)
- **Functions:** 0.0% (Wenige globale Funktionen - gut)
- **Globally:** 8.4% (Saubere Struktur)

### Komplexität

- **Durchschnittliche zyklomatische Komplexität:** 2.00 (Sehr gut)

### Architektur

- **Classes:** 96.3% (Sehr gut)
- **Interfaces:** 3.7% (Minimal)

---

## Konfiguration

### Datei: `phpinsights.php`

```php
<?php
return [
    'paths' => ['src'],
    'exclude' => ['vendor', 'tests', 'migrations', ...],
    'preset' => 'symfony',
];
```

### Anpassungen

Um die Analyse anzupassen:

1. **Weitere Verzeichnisse hinzufügen:**
   ```php
   'paths' => ['src', 'config', 'App'],
   ```

2. **Spezifische Insights deaktivieren:**
   ```php
   'remove' => [
       \PHP_CodeSniffer\Standards\PSR2\Sniffs\ControlStructures\ElseIfDeclarationSniff::class,
   ],
   ```

3. **Komplexitätslimits setzen:**
   ```php
   'config' => [
       \NunoMaduro\PhpInsights\Domain\Insights\Complexity\CyclomaticComplexityIsHigh::class => [
           'max' => 10,
       ],
   ],
   ```

---

## CI/CD Integration

### GitHub Actions Beispiel

```yaml
name: Code Quality

on: [push, pull_request]

jobs:
  phpinsights:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
      - run: composer install
      - run: ./vendor/bin/phpinsights analyse src/ --format=json
```

---

## Stolpersteine & Lösungen

### Problem: "Invalid configuration"
**Lösung:** Sicherstellen, dass `phpinsights.php` die richtigen Keys verwendet (`paths`, `exclude`, `preset` statt `default`, `insights`).

### Problem: Sehr langsame Analysen
**Lösung:**
- Threads erhöhen: `--threads=4`
- Smaller subsets analysieren (z.B. nur `src/Service/`)
- Verzeichnisse ausschließen

### Problem: Zu viele False Positives
**Lösung:** Insights in `phpinsights.php` im `remove` Array deaktivieren.

---

## Wartung

### Regelmäßige Analysen

```bash
# Wöchentlich
make phpinsights

# Mit automatischem HTML-Report
make phpinsights-html
```

### Baseline aktualisieren

Wenn das Team beschließt, einen neuen Standard zu setzen:

1. Führt `make phpinsights-html` aus
2. Reviewt den Report
3. Aktualisiert `phpinsights.php` bei Bedarf
4. Commitet die Änderungen

---

## Weitere Ressourcen

- [PHPInsights Dokumentation](https://phpinsights.com)
- [Symfony Preset Einstellungen](https://phpinsights.com/docs/presets)

---

**Letzte Aktualisierung:** 27.02.2026  
**Version:** PHPInsights v2.13.3 mit Symfony-Preset
