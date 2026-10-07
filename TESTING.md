# Test & CI — DB Event Manager

Piramide dei test su GitHub Actions: `.github/workflows/ci.yml` a ogni push e PR,
`.github/workflows/nightly.yml` ogni notte. Gli E2E stanno in un workflow
riutilizzabile (`.github/workflows/e2e.yml`) chiamato da entrambi.
Il piano dei test da scrivere e dei bug collegati è in `TESTING-PLAN.md`.

## I job della CI

| Job | Cosa verifica | Quando |
|-----|---------------|--------|
| **php** | `php -l` + unit test (PHPUnit, WordPress simulato), PHP 7.4–8.5 | ogni push/PR |
| **phpcs** | `WordPress.Security` + PHPCompatibilityWP 7.4+ su `inc/`, `templates/` e file principali | ogni push/PR |
| **javascript** | `node --check` su `assets/js` e sugli script inline dei PHP | ogni push/PR |
| **integration** | WordPress e MySQL veri: WP 6.0 (minimo dichiarato) e latest, anche multisite | dopo **php** |
| **e2e** | Browser reale su wp-env con Playwright, desktop e telefono (Pixel 7) | dopo php, phpcs, javascript |

Il rilascio è in `release.yml`: un tag `vX.Y.Z` pubblica lo ZIP solo se tag, header
`Version` e `DBEM_VERSION` coincidono e il README ha la voce `### X.Y.Z`.

## Run notturna

`nightly.yml` gira ogni notte alle 04:07 UTC (e a mano da *Actions → Nightly → Run
workflow*):

| Variante | Perché |
|----------|--------|
| E2E su WordPress trunk (PHP 8.3) | accorgersi prima che una nuova versione di WordPress rompa il plugin |
| E2E su PHP 8.4 | la CI normale esegue gli E2E su 8.1 |
| Integration su WordPress trunk (PHP 8.4) | stesso motivo, lato database e core |

wp-env scarica DB Form Builder e DB Privacy Hub dall'ultimo commit di `main`: la
run notturna segnala anche un'incompatibilità introdotta da loro. GitHub sospende
i workflow pianificati dopo 60 giorni senza attività nel repository.

## Eseguire i test in locale

### Unit (nessun requisito oltre PHP e Composer)

```bash
composer install
composer test
```

Stanno in `tests/unit/`. `tests/unit/bootstrap.php` simula le funzioni di
WordPress che servono e un `$wpdb` finto che riconosce i frammenti di SQL usati
dal plugin: adatto alla logica, non alle query vere (quelle vanno negli
integration test). `dbem_call_private()` chiama i metodi privati su ogni
versione di PHP.

### Integration (MySQL o MariaDB, Subversion)

```bash
bash bin/install-wp-tests.sh wordpress_test root root 127.0.0.1 latest
WP_TESTS_DIR=/tmp/wordpress-tests-lib composer test:integration
```

Stanno in `tests/integration/` (file `*IntegrationTest.php`, classi
`WP_UnitTestCase`). Le tabelle del plugin si creano nel bootstrap, dopo
`plugins_loaded`: dentro un test la suite di WordPress trasforma `CREATE TABLE`
in tabelle temporanee, che `SHOW TABLES` non vede. Con `WP_MULTISITE=1` girano
in multisite.

### E2E (Docker, Node 22)

```bash
npm ci
npx playwright install chromium
npx wp-env start
npm run env:setup
npx playwright test
```

`npx playwright test --project=chromium` o `--project=mobile` per un solo
progetto, `npm run test:e2e:headed` per vedere il browser.

L'ambiente (`.wp-env.json`) contiene il plugin, DB Form Builder, DB Privacy Hub
e il mu-plugin `tests/fixtures/dbem-e2e-fixture.php`, attivo solo con
`WP_ENVIRONMENT_TYPE` `local`. La fixture:

- **cattura le email**: `wp_mail` non invia nulla, i messaggi finiscono in
  un'opzione;
- `POST /?rest_route=/dbem-e2e/v1/reset` riporta allo stato baseline (nessun
  evento né iscrizione, PIN di sistema `123456`, rate limit dell'iscrizione
  spento) e crea gli eventi richiesti dallo spec;
- `GET /?rest_route=/dbem-e2e/v1/state` restituisce email catturate e iscrizioni.

`bin/setup-e2e.sh` imposta permalink, fuso orario **Europe/Rome** (con ora
legale: serve ai test sulle date, bug #7 del piano) e attiva i plugin.

Negli spec (`tests/e2e/`), `helpers.js` espone `resetState()`, `getState()`,
`submitRegistration()`, il PIN e la sessione admin (`ADMIN_STATE`, salvata da
`auth.setup.js`). I file `*.mobile.spec.js` girano solo nel progetto telefono.
`infra.spec.js` e `infra.mobile.spec.js` sono gli smoke test: se falliscono, gli
altri risultati non sono attendibili.

## Lo ZIP di release

Lo ZIP nasce da `git archive`: tutto ciò che è sviluppo (test, workflow,
configurazioni di Composer, npm, wp-env e Playwright, documenti del piano) è
escluso con `export-ignore` in `.gitattributes`. Un file nuovo di sviluppo va
aggiunto lì.
