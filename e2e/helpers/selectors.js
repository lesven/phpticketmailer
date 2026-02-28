import { Selector } from 'testcafe';

// ---- Login Page ----
export const loginSelectors = {
    passwordInput:  Selector('#password'),
    submitButton:   Selector('button[type="submit"]'),
    errorAlert:     Selector('.alert-danger'),
    loginHeading:   Selector('.card-header').withText('Login'),
};

// ---- Navigation ----
export const navSelectors = {
    brand:          Selector('.navbar-brand'),
    linkDashboard:  Selector('.nav-link').withText('Dashboard'),
    linkCsvUpload:  Selector('.nav-link').withText('CSV-Upload'),
    linkBenutzer:   Selector('.nav-link').withText('Benutzer'),
    linkTemplate:   Selector('.nav-link').withText('E-Mail-Template'),
    linkSmtp:       Selector('.nav-link').withText('SMTP-Konfiguration'),
    linkPasswort:   Selector('.nav-link').withText('Passwort ändern'),
    linkLog:        Selector('.nav-link').withText('Versandprotokoll'),
    linkMonitor:    Selector('.nav-link').withText('Systemüberwachung'),
    linkAbmelden:   Selector('.nav-link').withText('Abmelden'),
};

// ---- Dashboard ----
export const dashboardSelectors = {
    heading:             Selector('h1').withText('Dashboard'),
    statGesamt:          Selector('.card.bg-primary h4'),
    statErfolgreich:     Selector('.card.bg-success h4'),
    statFehlgeschlagen:  Selector('.card.bg-danger h4'),
    statUebersprungen:   Selector('.card.bg-warning h4'),
    statBenutzer:        Selector('.card.bg-info h4'),
    statRate:            Selector('.card.bg-secondary h4'),
    linkCacheLeeren:     Selector('a').withAttribute('href', '/cache/clear'),
    tabelleLetzte:       Selector('table.table-striped.table-hover'),
    leerMeldung:         Selector('.alert-info').withText('noch keine E-Mails'),
};

// ---- CSV Upload ----
export const csvUploadSelectors = {
    heading:            Selector('h1').withText('CSV-Upload'),
    form:               Selector('#csvUploadForm'),
    fileInput:          Selector('input[type="file"]'),
    submitButton:       Selector('#submitBtn'),
    testModeCheckbox:   Selector('input[name="csv_upload[testMode]"]'),
    forceResendCheckbox:Selector('input[name="csv_upload[forceResend]"]'),
    testEmailField:     Selector('#testEmailField'),
    testEmailInput:     Selector('input[name="csv_upload[testEmail]"]'),
};

// ---- User Management ----
export const userSelectors = {
    heading:            Selector('h1').withText('Benutzerverwaltung'),
    newUserButton:      Selector('a').withText('Neuen Benutzer anlegen'),
    searchInput:        Selector('input[name="search"]'),
    searchButton:       Selector('button[type="submit"]').withText('Suchen'),
    userTable:          Selector('table.table-striped.table-hover'),
    // New/Edit form
    usernameInput:      Selector('input[name="user[username]"]'),
    emailInput:         Selector('input[name="user[email]"]'),
    saveButton:         Selector('button[type="submit"]'),
};

// ---- Email Log ----
export const emailLogSelectors = {
    heading:            Selector('h1').withText('Versandprotokoll'),
    searchInput:        Selector('input[name="search"]'),
    filterAll:          Selector('a').withAttribute('href', /filter=all/),
    logTable:           Selector('table.table-striped'),
    leerMeldung:        Selector('.alert-info'),
};

// ---- SMTP Config ----
export const smtpSelectors = {
    heading:            Selector('h2, h1').withText('SMTP'),
    hostInput:          Selector('input[name="smtp_config[host]"]'),
    portInput:          Selector('input[name="smtp_config[port]"]'),
    saveButton:         Selector('button[type="submit"]'),
};

// ---- Flash Messages ----
export const flashSelectors = {
    success:    Selector('.alert-arz-success'),
    error:      Selector('.alert-arz-danger'),
    info:       Selector('.alert-arz-info'),
};
