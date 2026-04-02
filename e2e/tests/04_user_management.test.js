import { Selector } from 'testcafe';
import { adminRole, BASE_URL } from '../helpers/auth.js';
import { userSelectors, flashSelectors, navSelectors } from '../helpers/selectors.js';

const TEST_USERNAME = `e2e_test_${Date.now()}`;
const TEST_EMAIL = `e2etest_${Date.now()}@example.com`;

fixture('04 – Benutzerverwaltung')
    .page(`${BASE_URL}/user/`)
    .beforeEach(async t => {
        await t.useRole(adminRole);
        await t.navigateTo(`${BASE_URL}/user/`);
    });

test('Benutzerliste wird angezeigt', async t => {
    await t
        .expect(userSelectors.heading.exists).ok('Überschrift "Benutzerverwaltung" muss vorhanden sein')
        .expect(userSelectors.newUserButton.exists).ok('"Neuen Benutzer anlegen"-Button muss vorhanden sein');
});

test('Benutzer-Export-Button ist vorhanden', async t => {
    await t
        .expect(Selector('a').withText('CSV Export').exists).ok(
            'CSV-Export-Button muss in der Benutzerliste vorhanden sein'
        );
});

test('Benutzer-Import-Button ist vorhanden', async t => {
    await t
        .expect(Selector('a').withText('CSV Import').exists).ok(
            'CSV-Import-Button muss in der Benutzerliste vorhanden sein'
        );
});

test('Suche nach Benutzern funktioniert', async t => {
    await t
        .expect(userSelectors.searchInput.exists).ok('Suchfeld muss vorhanden sein')
        .typeText(userSelectors.searchInput, 'fixtures_user1')
        .pressKey('enter');

    const resultCount = Selector('td').withText('fixtures_user1').count;
    await t.expect(resultCount).gte(1,
        'Suche nach "fixtures_user1" muss mindestens einen Treffer liefern (Fixtures müssen geladen sein)'
    );
});

test('Suche ohne Treffer zeigt Hinweis an', async t => {
    await t
        .typeText(userSelectors.searchInput, 'xyz_nicht_vorhanden_99999')
        .pressKey('enter')
        .expect(Selector('.alert-info').exists).ok(
            'Bei keinen Treffern muss eine Hinweismeldung erscheinen'
        );
});

test('Neuen Benutzer anlegen-Formular öffnet sich', async t => {
    await t
        .click(userSelectors.newUserButton)
        .expect(userSelectors.usernameInput.exists).ok('Benutzername-Feld muss im Formular vorhanden sein')
        .expect(userSelectors.emailInput.exists).ok('E-Mail-Feld muss im Formular vorhanden sein')
        .expect(userSelectors.saveButton.exists).ok('Speichern-Button muss vorhanden sein');
});

test('Neuen Benutzer erstellen', async t => {
    await t
        .click(userSelectors.newUserButton)
        .typeText(userSelectors.usernameInput, TEST_USERNAME)
        .typeText(userSelectors.emailInput, TEST_EMAIL)
        .click(userSelectors.saveButton);

    // Nach dem Speichern: Weiterleitung zur Benutzerliste mit Erfolgs-Flash
    await t
        .expect(userSelectors.heading.exists).ok('Nach Erstellen muss die Benutzerliste angezeigt werden')
        .expect(flashSelectors.success.exists).ok(
            'Nach erfolgreichem Erstellen muss eine Erfolgsmeldung erscheinen'
        );
});

test('Erstellter Benutzer erscheint in der Liste', async t => {
    // Suche nach dem soeben erstellten Benutzer
    await t
        .typeText(userSelectors.searchInput, TEST_USERNAME)
        .pressKey('enter')
        .expect(Selector('td').withText(TEST_USERNAME).exists).ok(
            `Neu erstellter Benutzer "${TEST_USERNAME}" muss in der Benutzerliste erscheinen`
        );
});

test('Benutzer bearbeiten öffnet Edit-Formular', async t => {
    // Fixtures-Benutzer suchen und bearbeiten
    await t
        .typeText(userSelectors.searchInput, 'fixtures_user1')
        .pressKey('enter');

    const editButton = Selector('a.btn').withText('Bearbeiten').nth(0);
    await t
        .expect(editButton.exists).ok('Bearbeiten-Button muss vorhanden sein (Fixtures müssen geladen sein)')
        .click(editButton)
        .expect(userSelectors.usernameInput.exists).ok('Benutzername-Feld muss im Edit-Formular vorhanden sein')
        .expect(userSelectors.emailInput.exists).ok('E-Mail-Feld muss im Edit-Formular vorhanden sein');
});

test('Benutzer-Import-Seite ist erreichbar', async t => {
    await t
        .click(Selector('a').withText('CSV Import'))
        .expect(Selector('h1').withText('Benutzer').exists).ok(
            'Import-Seite muss erreichbar sein'
        )
        .expect(Selector('input[type="file"]').exists).ok(
            'Datei-Upload-Feld auf der Import-Seite muss vorhanden sein'
        );
});

test('Spaltensortierung per Link funktioniert', async t => {
    const sortByUsername = Selector('a').withAttribute('href', /sort=username/);
    await t
        .expect(sortByUsername.exists).ok('Sortierlink für Benutzername muss vorhanden sein')
        .click(sortByUsername)
        .expect(userSelectors.heading.exists).ok('Benutzerliste muss nach Sortierung noch angezeigt werden');
});
