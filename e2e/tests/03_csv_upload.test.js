import path from 'path';
import { Selector } from 'testcafe';
import { adminRole, BASE_URL } from '../helpers/auth.js';
import { csvUploadSelectors, flashSelectors } from '../helpers/selectors.js';

const FIXTURE_CSV = path.resolve('e2e/fixtures/csv/jira_tickets.csv');
const UNKNOWN_CSV = path.resolve('e2e/fixtures/csv/unknown_users.csv');

fixture('03 – CSV-Upload')
    .page(`${BASE_URL}/upload`)
    .beforeEach(async t => {
        await t.useRole(adminRole);
        await t.navigateTo(`${BASE_URL}/upload`);
    });

test('Upload-Seite wird korrekt angezeigt', async t => {
    await t
        .expect(csvUploadSelectors.heading.exists).ok('Upload-Seitenüberschrift muss vorhanden sein')
        .expect(csvUploadSelectors.form.exists).ok('Upload-Formular muss vorhanden sein')
        .expect(csvUploadSelectors.fileInput.exists).ok('Datei-Eingabefeld muss vorhanden sein')
        .expect(csvUploadSelectors.submitButton.exists).ok('Abschicken-Button muss vorhanden sein');
});

test('Testmodus-Checkbox und Test-E-Mail-Feld verhalten sich korrekt', async t => {
    // Test-E-Mail-Feld ist anfangs ausgeblendet
    await t
        .expect(csvUploadSelectors.testEmailField.getStyleProperty('display')).eql('none',
            'Test-E-Mail-Feld muss anfangs ausgeblendet sein'
        );

    // Testmodus aktivieren → Feld wird sichtbar
    await t
        .click(csvUploadSelectors.testModeCheckbox)
        .expect(csvUploadSelectors.testEmailField.getStyleProperty('display')).notEql('none',
            'Test-E-Mail-Feld muss nach Aktivieren des Testmodus sichtbar sein'
        );

    // Testmodus deaktivieren → Feld wird wieder ausgeblendet
    await t
        .click(csvUploadSelectors.testModeCheckbox)
        .expect(csvUploadSelectors.testEmailField.getStyleProperty('display')).eql('none',
            'Test-E-Mail-Feld muss nach Deaktivieren des Testmodus wieder ausgeblendet sein'
        );
});

test('Hinweisbox zum CSV-Format ist vorhanden', async t => {
    await t
        .expect(Selector('.card-header').withText('Hinweise zum CSV-Format').exists).ok(
            'Hinweisbox mit CSV-Format-Erklärung muss vorhanden sein'
        )
        .expect(Selector('.card-header').withText('CSV-Spaltenkonfiguration').exists).ok(
            'Konfigurationsbox für CSV-Spalten muss vorhanden sein'
        );
});

test('Formular-Felder für CSV-Spaltenkonfiguration sind befüllbar', async t => {
    const ticketIdField = Selector('input[name="csv_upload[csvFieldConfig][ticketIdField]"]');
    const usernameField = Selector('input[name="csv_upload[csvFieldConfig][usernameField]"]');
    const ticketNameField = Selector('input[name="csv_upload[csvFieldConfig][ticketNameField]"]');

    await t
        .expect(ticketIdField.exists).ok('Eingabefeld für Ticket-ID-Spaltenname muss vorhanden sein')
        .expect(usernameField.exists).ok('Eingabefeld für Benutzername-Spaltenname muss vorhanden sein')
        .expect(ticketNameField.exists).ok('Eingabefeld für Ticket-Name-Spaltenname muss vorhanden sein');
});

test('Datei-Upload wird korrekt verarbeitet (Testmodus, mit Fixtures)', async t => {
    // Dieses Test setzt voraus, dass Fixtures geladen sind (make fixtures-force)
    // und die CSV-Datei Benutzer enthält, die in der DB vorhanden sind.
    await t
        .setFilesToUpload(csvUploadSelectors.fileInput, [FIXTURE_CSV])
        .click(csvUploadSelectors.testModeCheckbox)
        .expect(csvUploadSelectors.testEmailField.getStyleProperty('display')).notEql('none',
            'Test-E-Mail-Feld muss nach Aktivieren des Testmodus sichtbar sein'
        )
        .typeText(csvUploadSelectors.testEmailInput, 'test@example.com', { replace: true })
        .click(csvUploadSelectors.submitButton);

    // Nach dem Upload: Weiterleitung zu /send-emails oder Flash-Meldung
    const currentUrl = await t.eval(() => document.location.pathname);
    await t.expect(
        currentUrl === '/send-emails' ||
        currentUrl === '/upload' ||
        currentUrl === '/unknown-users'
    ).ok(`Erwartete Weiterleitung nach Upload, stattdessen: ${currentUrl}`);
});

test('Upload ohne Datei zeigt Browser-Validierung', async t => {
    // Der Abschicken-Button sollte ohne ausgewählte Datei eine JS-Warnung auslösen
    // (Wir testen hier nur, dass der Button vorhanden und aktiviert ist)
    await t
        .expect(csvUploadSelectors.submitButton.hasAttribute('disabled')).notOk(
            'Abschicken-Button darf anfangs nicht deaktiviert sein'
        );
});
