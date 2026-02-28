import { Selector } from 'testcafe';
import { adminRole, BASE_URL, ADMIN_PASSWORD } from '../helpers/auth.js';
import { loginSelectors, navSelectors } from '../helpers/selectors.js';

fixture('01 – Login und Authentifizierung')
    .page(`${BASE_URL}/login`);

test('Login-Seite zeigt Passwort-Formular an', async t => {
    await t
        .expect(loginSelectors.passwordInput.exists).ok('Passwortfeld muss vorhanden sein')
        .expect(loginSelectors.submitButton.exists).ok('Anmelden-Button muss vorhanden sein')
        .expect(loginSelectors.loginHeading.exists).ok('Login-Überschrift muss angezeigt werden')
        .expect(Selector('p').withText('Passwort').exists).ok('Passwort-Hinweistext muss vorhanden sein');
});

test('Geschützte Seiten leiten unauthentifizierte Nutzer zum Login weiter', async t => {
    await t
        .navigateTo(`${BASE_URL}/`)
        .expect(Selector('h3.card-title').withText('Login').exists).ok(
            'Dashboard sollte für unauthentifizierte Nutzer zur Login-Seite weiterleiten'
        );
});

test('Falsches Passwort zeigt Fehlermeldung an', async t => {
    await t
        .typeText(loginSelectors.passwordInput, 'FalschesPasswort123!')
        .click(loginSelectors.submitButton)
        .expect(loginSelectors.errorAlert.exists).ok('Fehlermeldung muss bei falschem Passwort erscheinen')
        .expect(loginSelectors.errorAlert.innerText).contains('Ungültiges Passwort');
});

test('Leeres Passwortfeld löst HTML5-Validierung aus', async t => {
    // Das required-Attribut verhindert das Absenden ohne Passwort
    await t
        .expect(loginSelectors.passwordInput.getAttribute('required')).ok('Passwortfeld muss required-Attribut haben');
});

test('Richtiges Passwort leitet zum Dashboard weiter', async t => {
    await t
        .typeText(loginSelectors.passwordInput, ADMIN_PASSWORD)
        .click(loginSelectors.submitButton)
        .expect(Selector('h1').withText('Dashboard').exists).ok(
            'Nach erfolgreichem Login muss das Dashboard angezeigt werden'
        );
});

test('Navbar ist nach Login sichtbar', async t => {
    await t.useRole(adminRole);
    await t
        .expect(navSelectors.brand.exists).ok('Navbar-Brand muss vorhanden sein')
        .expect(navSelectors.linkDashboard.exists).ok('Dashboard-Link in Navbar muss vorhanden sein')
        .expect(navSelectors.linkCsvUpload.exists).ok('CSV-Upload-Link in Navbar muss vorhanden sein')
        .expect(navSelectors.linkAbmelden.exists).ok('Abmelden-Link muss vorhanden sein');
});

test('Logout leitet zur Login-Seite zurück', async t => {
    await t.useRole(adminRole);
    await t
        .click(navSelectors.linkAbmelden)
        .expect(loginSelectors.passwordInput.exists).ok(
            'Nach dem Abmelden muss die Login-Seite angezeigt werden'
        )
        .expect(navSelectors.linkAbmelden.exists).notOk(
            'Navbar darf nach dem Abmelden nicht mehr sichtbar sein'
        );
});

test('Nach Logout ist das Dashboard nicht mehr erreichbar', async t => {
    await t.useRole(adminRole);
    await t
        .navigateTo(`${BASE_URL}/logout`)
        .navigateTo(`${BASE_URL}/`)
        .expect(loginSelectors.passwordInput.exists).ok(
            'Dashboard muss nach Logout gesperrt sein'
        );
});
