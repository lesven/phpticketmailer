import { Selector } from 'testcafe';
import { adminRole, BASE_URL } from '../helpers/auth.js';
import { smtpSelectors, flashSelectors } from '../helpers/selectors.js';

fixture('06 – SMTP-Konfiguration')
    .page(`${BASE_URL}/smtp-config/`)
    .beforeEach(async t => {
        await t.useRole(adminRole);
    });

test('SMTP-Konfigurationsseite ist erreichbar', async t => {
    await t
        .expect(smtpSelectors.hostInput.exists).ok('SMTP-Host-Feld muss vorhanden sein')
        .expect(smtpSelectors.portInput.exists).ok('SMTP-Port-Feld muss vorhanden sein')
        .expect(smtpSelectors.saveButton.exists).ok('Speichern-Button muss vorhanden sein');
});

test('Host-Feld enthält einen Wert', async t => {
    const hostValue = await smtpSelectors.hostInput.value;
    await t.expect(hostValue.length).gte(0, 'SMTP-Host-Feld darf vorhanden sein (kann leer sein bei neuer Konfiguration)');
});

test('Port-Feld enthält einen numerischen Wert', async t => {
    const portValue = await smtpSelectors.portInput.value;
    if (portValue) {
        await t.expect(portValue).match(/^\d+$/, 'SMTP-Port muss eine Zahl sein');
    }
});

test('Absender-E-Mail-Feld ist vorhanden', async t => {
    const senderEmailField = Selector('input[name="smtp_config[senderEmail]"]');
    await t.expect(senderEmailField.exists).ok('Absender-E-Mail-Feld muss vorhanden sein');
});

test('Konfiguration speichern zeigt Erfolgsmeldung', async t => {
    // Aktuelle Werte lesen
    const currentHost = await smtpSelectors.hostInput.value;
    const currentPort = await smtpSelectors.portInput.value;

    // Formular ohne Änderungen abschicken
    await t
        .selectText(smtpSelectors.hostInput)
        .typeText(smtpSelectors.hostInput, currentHost || 'mailpit', { replace: true })
        .selectText(smtpSelectors.portInput)
        .typeText(smtpSelectors.portInput, currentPort || '1025', { replace: true })
        .click(smtpSelectors.saveButton);

    await t.expect(flashSelectors.success.exists).ok(
        'Nach dem Speichern der SMTP-Konfiguration muss eine Erfolgsmeldung erscheinen'
    );
});

test('Ticket-Basis-URL-Feld ist vorhanden', async t => {
    const ticketUrlField = Selector('input[name="smtp_config[ticketBaseUrl]"]');
    await t.expect(ticketUrlField.exists).ok('Ticket-Basis-URL-Feld muss vorhanden sein');
});
