import { Role, Selector } from 'testcafe';

const BASE_URL = process.env.APP_URL || 'http://localhost:8090';
const ADMIN_PASSWORD = process.env.ADMIN_PASSWORD || 'AdminP@ssw0rd123!';

const adminRole = Role(`${BASE_URL}/login`, async t => {
    await t
        .typeText('#password', ADMIN_PASSWORD)
        .click('button[type="submit"]');

    await t.expect(Selector('h1').withText('Dashboard').exists).ok(
        'Login fehlgeschlagen - Dashboard nicht gefunden. ' +
        'Bitte sicherstellen, dass Fixtures geladen wurden (make fixtures-force).',
        { timeout: 10000 }
    );
}, { preserveUrl: false });

export { adminRole, BASE_URL, ADMIN_PASSWORD };
