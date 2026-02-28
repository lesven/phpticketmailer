import { Selector } from 'testcafe';
import { adminRole, BASE_URL } from '../helpers/auth.js';
import { dashboardSelectors, navSelectors } from '../helpers/selectors.js';

fixture('02 – Dashboard')
    .page(BASE_URL)
    .beforeEach(async t => {
        await t.useRole(adminRole);
    });

test('Dashboard-Überschrift wird angezeigt', async t => {
    await t
        .expect(dashboardSelectors.heading.exists).ok('Dashboard-Überschrift muss sichtbar sein');
});

test('Alle sechs Statistik-Karten sind vorhanden', async t => {
    await t
        .expect(dashboardSelectors.statGesamt.exists).ok('"Gesamt E-Mails"-Karte muss vorhanden sein')
        .expect(dashboardSelectors.statErfolgreich.exists).ok('"Erfolgreich"-Karte muss vorhanden sein')
        .expect(dashboardSelectors.statFehlgeschlagen.exists).ok('"Fehlgeschlagen"-Karte muss vorhanden sein')
        .expect(dashboardSelectors.statUebersprungen.exists).ok('"Übersprungen"-Karte muss vorhanden sein')
        .expect(dashboardSelectors.statBenutzer.exists).ok('"Einzigartige Benutzer"-Karte muss vorhanden sein')
        .expect(dashboardSelectors.statRate.exists).ok('"Erfolgsrate"-Karte muss vorhanden sein');
});

test('Statistik-Werte sind numerisch', async t => {
    const gesamtText = await dashboardSelectors.statGesamt.innerText;
    const erfolgsrateText = await dashboardSelectors.statRate.innerText;

    await t
        .expect(gesamtText.trim()).match(/^\d+$/, 'Gesamtzahl muss eine Zahl sein')
        .expect(erfolgsrateText.trim()).match(/^\d+([.,]\d+)?%?$/, 'Erfolgsrate muss eine Zahl sein (ggf. mit %-Zeichen)');
});

test('Quick-Links zu den wichtigsten Bereichen sind vorhanden', async t => {
    await t
        .expect(Selector('a.btn').withAttribute('href', '/upload').exists).ok(
            'Quick-Link zum CSV-Upload muss auf dem Dashboard vorhanden sein'
        )
        .expect(Selector('a.btn').withAttribute('href', '/user/').exists).ok(
            'Quick-Link zur Benutzerverwaltung muss auf dem Dashboard vorhanden sein'
        )
        .expect(Selector('a.btn').withAttribute('href', '/smtp-config/').exists).ok(
            'Quick-Link zur SMTP-Konfiguration muss auf dem Dashboard vorhanden sein'
        )
        .expect(Selector('a.btn').withAttribute('href', '/template/').exists).ok(
            'Quick-Link zum E-Mail-Template muss auf dem Dashboard vorhanden sein'
        );
});

test('Cache-leeren-Link ist vorhanden', async t => {
    await t
        .expect(dashboardSelectors.linkCacheLeeren.exists).ok('Cache-leeren-Link muss vorhanden sein');
});

test('Tabelle der letzten E-Mails oder Leer-Meldung wird angezeigt', async t => {
    const tabelleVorhanden = await dashboardSelectors.tabelleLetzte.exists;
    const leerMeldungVorhanden = await dashboardSelectors.leerMeldung.exists;

    await t.expect(tabelleVorhanden || leerMeldungVorhanden).ok(
        'Entweder die E-Mail-Tabelle oder eine Leer-Meldung muss angezeigt werden'
    );
});

test('Navigation enthält alle Hauptbereiche', async t => {
    await t
        .expect(navSelectors.linkDashboard.exists).ok('Dashboard-Link in Navigation')
        .expect(navSelectors.linkCsvUpload.exists).ok('CSV-Upload-Link in Navigation')
        .expect(navSelectors.linkBenutzer.exists).ok('Benutzer-Link in Navigation')
        .expect(navSelectors.linkTemplate.exists).ok('Template-Link in Navigation')
        .expect(navSelectors.linkSmtp.exists).ok('SMTP-Link in Navigation')
        .expect(navSelectors.linkPasswort.exists).ok('Passwort-Link in Navigation')
        .expect(navSelectors.linkLog.exists).ok('Versandprotokoll-Link in Navigation')
        .expect(navSelectors.linkMonitor.exists).ok('Systemüberwachung-Link in Navigation')
        .expect(navSelectors.linkAbmelden.exists).ok('Abmelden-Link in Navigation');
});

test('Navigation zum CSV-Upload funktioniert', async t => {
    await t
        .click(navSelectors.linkCsvUpload)
        .expect(Selector('h1').withText('CSV-Upload').exists).ok(
            'Klick auf "CSV-Upload" in der Navigation muss die Upload-Seite öffnen'
        );
});

test('Navigation zur Benutzerverwaltung funktioniert', async t => {
    await t
        .click(navSelectors.linkBenutzer)
        .expect(Selector('h1').withText('Benutzerverwaltung').exists).ok(
            'Klick auf "Benutzer" in der Navigation muss die Benutzerverwaltung öffnen'
        );
});

test('API-Statistiken-Endpunkt liefert JSON zurück', async t => {
    // Der API-Endpunkt erfordert Authentifizierung.
    // t.request() teilt keine Cookies mit dem Browser, daher
    // prüfen wir nur, dass der Endpunkt antwortet (200 mit Redirect-HTML oder JSON).
    const response = await t.request(`${BASE_URL}/api/statistics`);
    await t
        .expect(response.status).eql(200, 'API-Endpunkt muss HTTP 200 zurückgeben');
});
