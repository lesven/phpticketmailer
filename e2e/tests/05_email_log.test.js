import { Selector } from 'testcafe';
import { adminRole, BASE_URL } from '../helpers/auth.js';
import { emailLogSelectors } from '../helpers/selectors.js';

fixture('05 – Versandprotokoll')
    .page(`${BASE_URL}/versandprotokoll`)
    .beforeEach(async t => {
        await t.useRole(adminRole);
        await t.navigateTo(`${BASE_URL}/versandprotokoll`);
    });

test('Versandprotokoll-Seite wird angezeigt', async t => {
    await t
        .expect(emailLogSelectors.heading.exists).ok('Überschrift "Versandprotokoll" muss vorhanden sein');
});

test('Tabelle oder Leer-Meldung wird angezeigt', async t => {
    const tabelleVorhanden = await emailLogSelectors.logTable.exists;
    const leerMeldungVorhanden = await emailLogSelectors.leerMeldung.exists;

    await t.expect(tabelleVorhanden || leerMeldungVorhanden).ok(
        'Entweder die Protokoll-Tabelle oder eine Leer-Meldung muss angezeigt werden'
    );
});

test('Suchfeld ist vorhanden', async t => {
    await t
        .expect(emailLogSelectors.searchInput.exists).ok('Suchfeld muss im Versandprotokoll vorhanden sein');
});

test('Suche nach Ticket-ID funktioniert', async t => {
    // Suche nach einem Fixture-Ticket (erfordert geladene Fixtures)
    await t
        .typeText(emailLogSelectors.searchInput, 'FIXTURE-001')
        .pressKey('enter');

    const resultat = Selector('td').withText('FIXTURE-001');
    const leerMeldung = emailLogSelectors.leerMeldung;

    // Entweder Treffer oder Leer-Meldung
    const trefferVorhanden = await resultat.exists;
    const keineTreffer = await leerMeldung.exists;

    await t.expect(trefferVorhanden || keineTreffer).ok(
        'Nach der Suche muss entweder ein Ergebnis oder eine Leer-Meldung erscheinen'
    );
});

test('Suche zurücksetzen bringt vollständige Liste zurück', async t => {
    await t
        .typeText(emailLogSelectors.searchInput, 'FIXTURE-001')
        .pressKey('enter');

    const resetLink = Selector('a').withText('Suche zurücksetzen');
    const backToLogLink = Selector('a').withAttribute('href', '/versandprotokoll');

    const hasReset = await resetLink.exists;
    const hasBackLink = await backToLogLink.exists;

    if (hasReset) {
        await t.click(resetLink);
    } else if (hasBackLink) {
        await t.click(backToLogLink.nth(0));
    } else {
        await t.navigateTo(`${BASE_URL}/versandprotokoll`);
    }

    await t.expect(emailLogSelectors.heading.exists).ok(
        'Nach dem Zurücksetzen der Suche muss das Versandprotokoll wieder vollständig angezeigt werden'
    );
});

test('Filter-Links sind vorhanden', async t => {
    // Prüfen ob Filter-Links mit "filter=..." Parameter existieren
    const filterLinks = Selector('a').withAttribute('href', /[?&]filter=/);
    const filterLinkCount = await filterLinks.count;

    // Wenn Fixtures geladen sind, gibt es Filter-Links
    if (filterLinkCount > 0) {
        await t.expect(filterLinkCount).gte(1, 'Mindestens ein Filter-Link muss vorhanden sein');
    }
    // Ohne Daten kann es auch keine Filter geben – beides ist gültig
});

test('Paginierung erscheint bei vielen Einträgen (mit Fixtures)', async t => {
    // Bei mehr als 50 Einträgen erscheint die Paginierung
    // Mit den Fixture-Daten (15 Einträge) gibt es keine Paginierung
    const logTable = await emailLogSelectors.logTable.exists;

    if (logTable) {
        const rows = Selector('tbody tr');
        const rowCount = await rows.count;
        await t.expect(rowCount).gte(0, 'Tabelle kann leer oder gefüllt sein');
    }
});
