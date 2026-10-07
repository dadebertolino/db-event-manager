# DB Event Manager — Piano di test e correzioni

Documento di lavoro per portare Event Manager allo stesso livello di test di
DB Cookie Manager e DB Privacy Hub (unit + integration + E2E + run notturna).
Si procede fase per fase; ogni fase è una PR. Le caselle si spuntano man mano.

Base di partenza: 1.9.0 in sviluppo (analisi del 2026-10-07, branch `sviluppo`).

---

## 1. Situazione attuale

- ~6.300 righe PHP in 19 classi (`inc/`), 4 file JS (`assets/js/`), template
  frontend e admin.
- **Test:** 100 unit test PHPUnit con stub di WordPress (`tests/`, `$wpdb` finto
  che riconosce frammenti di SQL). Nessun integration test, nessun E2E.
- **CI** (`.github/workflows/ci.yml`): `php -l` + PHPUnit su PHP 7.4–8.5, PHPCS
  (`WordPress.Security` + PHPCompatibilityWP 7.4+), sintassi JS. Release da tag
  `v*` (`release.yml`: versione, changelog, ZIP con `git archive`).
- Il valore del plugin sta in:
  1. **iscrizione pubblica** (visitatori anonimi, pagine in cache, DB Form Builder);
  2. **dati personali** (iscrizioni, consenso GDPR, survey, QR, DSAR, registro
     trattamenti di Privacy Hub);
  3. **giornata dell'evento** (check-in da telefono con PIN, lista partecipanti);
  4. **email e cron** (conferme, approvazioni, promemoria, survey automatici).

La piramide pesa quindi su **integration** (tabelle, cron, fusi orari, DSAR) ed
**E2E** (form, check-in, pagine con PIN, accessibilità).

### Contratti pubblici (da proteggere con i test)

| Filtro / azione / endpoint | Forma attesa | Note |
|---|---|---|
| `wp_ajax(_nopriv)_dbem_register` | POST form integrato → JSON | anonimi: Origin/Referer + rate limit |
| `wp_ajax(_nopriv)_dbem_register_dbfb` | POST dopo invio DB Form Builder → JSON | idem |
| `wp_ajax(_nopriv)_dbem_public_*` | pagine check-in e partecipanti, PIN | rate limit `dbem_public_rate_limit` |
| `wp_ajax(_nopriv)_dbem_submit_survey` | token personale | rate limit `dbem_survey_rate_limit` |
| `dbem_registration_rate_limit` | int, `0` disattiva | default 5/minuto |
| `dbem_duplicate_skipped_meta`, `dbem_event_duplicated` | 1.9.0 | |
| `dbph_processing_register` | dichiarazioni al registro di Privacy Hub | `DBEM_Privacy_Declarations` |
| `dbph_consents_register` | registro consensi di Privacy Hub | idem |
| `wp_privacy_personal_data_exporters` / `_erasers` (e varianti `dbph_`) | export e cancellazione DSAR | `DBEM_Privacy_DSAR` |
| Segnaposto email (`{nome}`, `{campo:id}`, …) | `DBEM_Email::placeholders_for()` | |
| Variabili CSS `--dbem-*` | aspetto | README «Aspetto» |

---

## 2. Bug noti (da correggere, ciascuno con il suo test)

Priorità: **A** = dati personali/legali, sicurezza, crash; **B** = dati errati o
funzione rotta; **C** = robustezza e qualità.

Colonna **Ver.**: ✔ = verificato leggendo il codice durante l'analisi; ○ =
segnalato con percorso del codice, lo conferma (o lo smentisce) il test.

Legenda del numero: ✅ = corretto nella 1.9.0 (2026-10-07); ◐ = corretto in parte.
#25: formato del PIN (4-10 cifre) corretto; resta il contatore dei tentativi non
atomico. #6 e #23 non hanno ancora un test automatico: arriva con integration ed
E2E (Fase 2 e 3). #2, #12, #13 hanno unit test sulla logica; il percorso completo
su database va in Fase 2.

### A

| # | Ver. | Dove | Problema | Test che lo prova |
|---|---|---|---|---|
| 1 ✅ | ✔ | `class-registration.php:372-463` | `handle_dbfb_registration` non controlla che l'evento usi DB Form Builder. Su un evento con form integrato e GDPR attivo un POST diretto a `dbem_register_dbfb` iscrive **senza consenso** e senza campi obbligatori. | Integration: evento builtin + GDPR, POST a `dbem_register_dbfb` → errore. |
| 2 ✅ | ✔ | `class-db.php:307-330` (`replace_registration`) | La modifica confermata di un'iscrizione sovrascrive sempre i campi `gdpr_*`: se nel frattempo il GDPR è stato disattivato (o la modifica arriva da DBFB senza campo mappato) la **prova del consenso originale** (art. 7.1) diventa NULL. | Integration: iscrizione con consenso, GDPR off, modifica → consenso originale conservato. |
| 3 ✅ | ✔ | `class-export.php:46-61` | CSV admin: `csv_safe()` è applicato alle celle ma **non alle intestazioni**, che sono le chiavi JSON di `data` scelte dal client. `{"=HYPERLINK(...)":"a"}` diventa una formula nella prima riga. (Vedi anche #18: le chiavi andrebbero whitelistate.) | Unit: export con chiave che inizia per `=` → intestazione neutralizzata. |
| 4 ✅ | ✔ | `class-checkin.php:296-339` e altri `handle_public_*` | Le pagine pubbliche con PIN accettano qualunque `event_id` senza controllare tipo e stato del post: con il PIN si leggono nomi, email e campi di **bozze, eventi privati e nel cestino**, e si agisce su iscrizioni di qualunque evento (anche il check-in per `registration_id`). | Integration: PIN corretto + id di una bozza / di un altro post → errore. |
| 5 ✅ | ○ | `class-privacy-declarations.php:36-50, 117` | Registro trattamenti (art. 30) incoerente con i dati salvati: considera solo eventi `publish` (con eventi tutti in bozza il registro è vuoto ma la tabella contiene nomi, email, IP); dichiara il trattamento «email» solo con un template di conferma personalizzato; dichiara `transfers: Nessuno` ma notifica admin e richiesta di approvazione inviano i dati a indirizzi configurati. | Unit con stub: evento solo in bozza → registro presente; approvazione senza template → `dbem_email` presente. |

### B

| # | Ver. | Dove | Problema | Test che lo prova |
|---|---|---|---|---|
| 6 ✅ | ✔ | `db-event-manager.php:85, 490` | `register_activation_hook` è registrato dentro `plugins_loaded`: durante l'attivazione non esiste ancora, quindi `DBEM_DB::activate()` **non gira mai**. Tabelle e capability si salvano altrove, ma `flush_rewrite_rules()` no: su installazione nuova `/eventi/` e le pagine evento danno 404 finché non si salvano i permalink. La correzione deve registrare il CPT prima del flush. | Integration: attivazione → regole con `eventi/?$`; `has_action('activate_…')`. |
| 7 | ✔ | `class-admin.php:884,895`, `class-cron.php:49`, `class-cpt.php:178-196`, `class-checkin.php:55,61,125,157,222,228,276`, `templates/admin/participants.php:242-243`, `templates/admin/survey.php:145`, `templates/admin/checkin.php:27`, `db-event-manager.php:368-369` | **Fuso orario.** Le date sono in ora locale, ma `strtotime()` le legge come UTC (WordPress imposta il fuso di PHP a UTC) e `wp_date()` applica di nuovo l'offset. Con Europe/Rome in estate: promemoria 2 ore dopo il previsto (anche dopo l'inizio), survey automatico in ritardo, iscrizioni aperte 2 ore oltre la scadenza, stati «in corso/concluso» sfasati, orari di check-in e iscrizione mostrati +2 h. Pagine diverse mostrano orari diversi (`date()` vs `wp_date()`). Va introdotto un helper unico locale↔timestamp. | Integration con `timezone_string=Europe/Rome`: `wp_next_scheduled`, `are_registrations_open`, orari mostrati. |
| 8 | ✔ | `class-db.php:121-135` | `count_registrations()` esclude solo `cancelled`: le iscrizioni **rifiutate** (e in attesa) occupano posti. Il cron chiude le iscrizioni scrivendo `_dbem_registration_open=0` in modo permanente: un posto liberato non le riapre. `email_exists_for_event` blocca anche la reiscrizione di chi era stato rifiutato. | Integration: max 10, 8 confermati + 2 rifiutati → iscrizioni aperte. |
| 9 | ○ | `class-registration.php:77-84, 142, 407-408`, `class-db.php:41-45` | Overbooking ed email doppie con invii concorrenti: COUNT poi INSERT senza lock, nessun UNIQUE su `(event_id, email)`. | Integration: richieste parallele sull'ultimo posto. |
| 10 ✅ | ✔ | `class-registration.php:23`, `class-cpt.php:189-216` | Ci si può iscrivere a eventi in **bozza, privati, programmati o nel cestino**: il server controlla solo `post_type`. L'email di conferma rivela dati dell'evento non pubblicato. | Integration: POST con id di una bozza → errore. |
| 11 | ○ | `templates/single-dbem_event.php:12,32`, `class-shortcodes.php:31-47` | Eventi protetti da password: contenuto e form stampati senza `post_password_required()`. | E2E. |
| 12 ✅ | ✔ | `class-admin.php:1323-1352` | Azioni in blocco senza filtro sullo stato: «Conferma» riporta a `confirmed` chi era già **presente** (e gli rimanda la conferma); «Rifiuta» rimanda l'email a chi era già rifiutato; «Segna presente» marca anche annullati e rifiutati e sovrascrive l'orario di check-in. | Integration: mix di stati, conteggio di `wp_mail`. |
| 13 ✅ | ○ | `class-admin.php:1365-1383`, `participants.php:255` | «Reinvia email» manda «iscrizione confermata» (con QR) anche a iscritti in attesa, rifiutati o annullati. | Integration. |
| 14 | ○ | `class-checkin.php:362-371, 43, 211, 387` | Pagina partecipanti pubblica: le azioni accettano qualunque stato di partenza (`confirm` su `checked_in` lo declassa lasciando `checked_in_at`); riconfermare un rifiutato non invia QR né email e non controlla i posti. Gli UPDATE di check-in non hanno `AND status = …` (doppia scansione, annullamento concorrente). | Integration: matrice stato × azione. |
| 15 | ○ | `class-checkin.php:19-58`, `assets/js/checkin.js:108-121` | Check-in admin: un QR di **un altro evento** dà «Check-in effettuato» in verde; il JS non invia `event_id` e non mostra l'evento della risposta. | E2E con due eventi. |
| 16 | ○ | `class-cron.php:68-93`, `class-admin.php:879-899` | Promemoria e survey automatici partono anche per eventi **nel cestino** o tornati in bozza: gli invii si cancellano solo all'eliminazione definitiva e i callback non controllano lo stato del post. | Integration: `wp_trash_post`, poi l'hook → nessuna email. |
| 17 | ○ | `class-cron.php:24-28` | Disattivare e riattivare il plugin cancella **tutti** i promemoria e survey programmati; tornano solo risalvando ogni evento. | Integration. |
| 18 | ○ | `class-registration.php:57-72, 127, 418` | Campi custom senza whitelist né validazione di tipo: select/radio/checkbox accettano valori fuori elenco, email/number/url/date non validati, textarea perde gli a capo, `empty('0')` rifiuta un obbligatorio con valore 0, etichette duplicate o chiamate «nome»/«email» perse in silenzio, chiavi arbitrarie salvate in `data` (origine del #3). | Unit con stub. |
| 19 ✅ | ✔ | `class-email.php:476-477` | Gli URL scritti dall'iscritto (es. nel nome) diventano **link cliccabili** nelle email inviate dal sito: il form diventa un relay di link verso indirizzi di terzi. Vale per `{nome}`, `{riepilogo_dati}`, notifica admin. | Unit su `build_html_email`: solo gli URL del template diventano link. |
| 20 | ○ | `class-db.php:33`, `db-event-manager.php:459-470`, `class-checkin.php:506-514` | `assigned_time` è `varchar(50)`: con un testo più lungo l'UPDATE fallisce in silenzio (stato resta `pending`) ma QR ed email di conferma partono e la pagina dice «approvata». | Integration con 60 caratteri. |
| 21 | ○ | `class-survey.php:58-105` | Il survey si invia anche se disattivato e da iscritti in attesa, rifiutati o annullati (token del QR); nessun UNIQUE su `registration_id` (doppio invio concorrente). | Integration: POST diretto. |
| 22 | ○ | `class-frontend.php:292-404` | Flusso DB Form Builder lato browser: l'iscrizione all'evento parte anche se DBFB mostra un errore (basta un messaggio ≥ 5 caratteri), poi `registered=true` blocca il secondo invio; con evento pieno o chiuso DBFB salva e invia le sue email prima del controllo. Markup reale di DBFB da verificare. | E2E con DBFB installato. |
| 23 ✅ | ✔ | `class-duplicate.php:19-21` (1.9.0) | **Duplica evento** nell'editor a blocchi: il link nel box Pubblica (`post_submitbox_misc_actions`) e l'avviso `admin_notices` non compaiono, perché il CPT usa l'editor a blocchi (`show_in_rest`). Funziona solo l'azione nell'elenco eventi. Il titolo «(copia)» sparisce al primo salvataggio (ripreso da `_dbem_event_name`): due eventi omonimi nel menu Partecipanti. | E2E: duplica dall'editor, avviso visibile. |
| 24 | ○ | `uninstall.php:54-62` | «Elimina tutti i dati» lascia gli eventi **nel cestino** (`post_status => 'any'` esclude `trash` e `auto-draft`) con i meta (testi email, destinatari). | Integration. |
| 25 ◐ | ✔ | `class-security.php:69-87`, `class-admin.php:1129-1133`, template check-in/partecipanti | PIN: contatore dei tentativi non atomico (una raffica parallela supera i 10 tentativi); l'admin può salvare un PIN come `1`; un PIN > 10 caratteri non si può digitare (`maxlength="10"`). | Integration (richieste parallele) + unit sul salvataggio. |
| 26 | ○ | `templates/frontend/participants.php:308-325` | Cambiando evento in fretta la lista mostra (ed esporta) i dati dell'evento precedente sotto il nome del nuovo: le risposte in ritardo non vengono scartate. | E2E con rete rallentata. |
| 27 | ○ | `class-db.php:231-241` | Ricerca pubblica: massimo 10 risultati su **tutti** gli eventi, passati compresi; la persona giusta all'evento di oggi può non comparire. | Integration. |

### C

| # | Ver. | Dove | Problema |
|---|---|---|---|
| 28 | ○ | `class-admin.php:751,803,846,854,863,1457, 742` | Barre rovesciate perse: valori già `wp_unslash`-ati passati a `update_post_meta`, che toglie un altro livello («Aula B\2» → «Aula B2»). |
| 29 | ○ | `class-admin.php:844,861` vs `class-email.php:476` | Testi email salvati con `wp_kses_post` ma inviati con `esc_html`: chi scrive `<b>` lo vede letterale. Scegliere testo semplice (`sanitize_textarea_field`). |
| 30 | ○ | `class-admin.php:554,649` | Meta email malformato (stringa) → TypeError su PHP 8, l'editor dell'evento non si apre. |
| 31 | ○ | `class-registration.php:29, 389` | `dbem_email[]=x` → TypeError (500) invece dell'errore JSON. Da verificare anche `sanitize_key()` su array con WP 6.0 (`db-event-manager.php:225,402,403`). |
| 32 | ○ | `class-db.php:24-46, 86-105, 109-116` | Schema senza versione: SHOW TABLES + SHOW COLUMNS a ogni richiesta; dbDelta gira solo se manca la tabella iscrizioni (colonne/indici nuovi non arrivano a chi aggiorna, la tabella survey non viene ricreata); l'indice sul consenso esiste solo sulle installazioni aggiornate. Serve `dbem_db_version`. |
| 33 | ○ | `class-db.php:28, 44` | Indice su `email varchar(255)` utf8mb4: «key too long» su MySQL 5.6 / MariaDB 10.1 senza large prefix. Da verificare; il core usa 191. |
| 34 | ○ | `class-privacy-dsar.php:79, 176, 175-197` | `LOWER(email) = %s` impedisce l'indice; l'eraser può ciclare all'infinito se il DELETE fallisce (`done` sempre `false`). |
| 35 | ○ | `class-registration.php:282` | Il transient della modifica in attesa (nome, email, IP, campi, 24 h) non è coperto da export e cancellazione DSAR. |
| 36 | ○ | `class-privacy-declarations.php:169-188` | Query del registro consensi senza `ensure_tables()` e senza offset oltre 50.000 righe. |
| 37 | ○ | `class-survey.php:97, 104` | Survey: `empty('0')` su obbligatorio; risposte indicizzate per etichetta (rinominare una domanda le fa sparire dal riepilogo, etichette duplicate si sovrascrivono). |
| 38 | ○ | `class-export.php` | CSV admin con `,` (Excel italiano lo apre in una colonna; l'export pubblico usa `;`); include l'IP (minimizzazione). |
| 39 | ○ | `class-registration.php:66-69`, survey | Messaggi d'errore con escape doppio («L'aula» → `L&#039;aula`). |
| 40 | ○ | vari | i18n: `d/m/Y` fisso invece di `date_format`, `lang="it"` fisso nella pagina di approvazione, «✅ Approva»/«❌ Rifiuta» fissi nelle email. |
| 41 | ○ | `participants.php:4-10` | Menu eventi della pagina Partecipanti: solo `publish`/`draft`, massimo 100. |
| 42 | ○ | `templates/frontend/checkin.php:333-338`, `checkin.js:146`, `participants.php:444` | Lo scanner pubblico riaccende la fotocamera dopo ogni check-in (anche da ricerca); i timer degli avvisi non vengono azzerati. |
| 43 | ○ | `admin.js:87, 96-110` | `Sortable.create` richiamato a ogni render: istanze che si accumulano. Da verificare l'effetto. |
| 44 | ○ | vari | Invii doppi non tracciati (promemoria/survey manuale + automatico, nessun indicatore «inviato»). |
| 49 | ✔ | `inc/lib/phpqrcode.php:957, 3551` | Libreria QR: parametri opzionali prima di uno obbligatorio in `QRimage::png()` e `QRvect::svg()`, avviso di deprecazione su PHP 8.0+ al caricamento del file. Con `display_errors` attivo l'avviso può finire in una risposta AJAX (JSON non valido) o in un PNG. Trovato il 2026-10-07 con `php -l` su PHP 8.5. |

### Accessibilità (WCAG 2.1 AA) — da trattare in un blocco unico

| # | Dove | Problema |
|---|---|---|
| 45 | `frontend.js:25-35` | Radio obbligatorio senza scelta supera la validazione; gruppo checkbox obbligatorio senza `required`/`aria-required`, asterisco `aria-hidden` (3.3.2). |
| 46 | form frontend | Errori non collegati ai campi (`aria-describedby`); `role="alert"` insieme ad `aria-live="polite"`; progressbar senza nome; pulsante senza nome durante l'invio; `<main>` annidato nel main del tema. |
| 47 | `templates/frontend/checkin.php:14` | `user-scalable=no, maximum-scale=1` (1.4.4). |
| 48 | pagine check-in / partecipanti | Contrasti sotto 4.5:1 (`.ci-or`, `.ci-fb-error`, header `small`); errori PIN senza `aria-live`; risultati `role=button` che non rispondono allo Spazio; modale orario senza `role=dialog`, trappola e ritorno del focus; pulsanti solo emoji (✅ con due significati); nessuna conferma prima di annullare. |

### Verificato e corretto (non sono bug)

Controlli fatti durante l'analisi che **non** hanno trovato problemi, da non
rianalizzare: nonce e capability su tutti gli AJAX admin, Impostazioni solo
`manage_options`, Duplica con nonce + `create_posts` + `edit_post`;
`save_metabox` protetto dal nonce (modifica rapida, REST e autosave escono
subito, le checkbox non si azzerano); header email (mittente e destinatari con
`is_email`, oggetto senza a capo); Origin/Referer che fallisce in sicurezza; IP
solo da `REMOTE_ADDR`; `escHtml` nelle pagine check-in e partecipanti; CSV
client con formule neutralizzate; link di modifica monouso con nonce e
no-cache; nonce stampato solo per i loggati; `esc_like` in tutte le LIKE;
placeholder di IN e LIMIT preparati, `orderby` in whitelist; cron con argomenti
int; uninstall multisite per sito; eraser che copre survey e PNG dei QR;
cestino che conserva i dati, eliminazione definitiva che li cancella; PIN
generato con `random_int`, confrontato con `hash_equals`; token
`bin2hex(random_bytes(32))`.

---

## 3. Decisioni

Comportamenti che non sono errori di codice ma scelte, con impatto su privacy o
uso reale.

**Prese il 2026-10-07:**
- **D1 ✅** PIN per evento, con il PIN di sistema come ripiego; pagine pubbliche
  limitate agli eventi pubblicati aperti dal PIN (1.9.0).
- **D2 ✅** Risposta neutra: stesso messaggio per indirizzi nuovi e già iscritti,
  le differenze solo nell'email (1.9.0).
- **D3** Rate limit per IP invariato (5 iscrizioni/minuto, filtro
  `dbem_registration_rate_limit` per chi ne ha bisogno).
- **D4 ✅** Conseguenza di D2: iscrizioni chiuse o posti esauriti valgono anche per
  chi è già iscritto, che non può più modificare i dati dopo la chiusura
  (altrimenti la differenza di risposta rivelerebbe l'iscrizione).
- **D5 ✅** Rinomina delle opzioni solo dopo conferma, per ogni rinomina (1.9.0).
- **D6, D7** da decidere.

| # | Tema | Situazione | Opzioni |
|---|---|---|---|
| D1 | **Un PIN per tutti gli eventi** (#4) | Chi ha il PIN vede e modifica le iscrizioni di ogni evento, anche passati; può aggiungere iscrizioni con email qualsiasi (il sito invia la conferma) e reinviare email fino a 120/minuto. | PIN per evento (meta, ripiego su quello globale) · limitare le pagine pubbliche agli eventi pubblicati e non conclusi · limitare aggiunta/reinvio. |
| D2 | **Enumerazione delle email** | «Questo indirizzo email è già registrato» dice a chiunque se una persona è iscritta (es. a uno sportello). | Messaggio neutro + email alla persona («hai già un'iscrizione, ecco il link») · lasciare così per eventi non sensibili. |
| D3 | **Rate limit per IP nelle scuole** | 5 iscrizioni/minuto per IP: una classe dietro lo stesso NAT si blocca dal 6° studente; 10 PIN errati dalla stessa rete bloccano lo staff per 15 minuti. Con IPv6 si aggira ruotando gli indirizzi. | Limite più alto di default · limite per (IP, email) · raggruppare IPv6 per /64 · blocco PIN per (IP, evento). |
| D4 | **Modifica dopo la chiusura** | Un iscritto può modificare i dati anche a iscrizioni chiuse o evento concluso. | Bloccare dopo scadenza/inizio evento · consentire fino all'inizio. |
| D5 | **Rinomina delle opzioni** | Cambiare il testo di un'opzione riscrive le iscrizioni esistenti; se l'opzione è stata *sostituita* (Lab 10 ott → Lab 24 ott) le iscrizioni cambiano data in modo irreversibile. | Chiedere conferma prima di applicare · applicare solo con una spunta esplicita. |
| D6 | **Email di annullamento** | `DBEM_Email::send_cancellation()` esiste ma non viene mai chiamata: annullare da admin non avvisa il partecipante. | Inviarla (con spunta «avvisa») · rimuovere il codice morto. |
| D7 | **IP nell'export CSV** e dati orfani dopo l'uninstall senza «elimina dati» | L'IP finisce nel CSV; dopo l'uninstall le tabelle restano senza exporter, eraser né dichiarazione. | Togliere l'IP dall'export · avviso esplicito accanto all'opzione di uninstall. |

---

## 4. Fase 0 — Infrastruttura

- [ ] `tests/` → `tests/unit/` (bootstrap e testsuite aggiornati)
- [ ] Integration: `bin/install-wp-tests.sh`, `phpunit-integration.xml.dist`,
      `tests/integration/bootstrap.php`, `yoast/phpunit-polyfills`; job CI con
      MySQL 8 (WordPress 6.0 minimo e `latest`)
- [ ] E2E: `package.json` + lockfile (`@wordpress/env`, `@playwright/test`,
      `@axe-core/playwright`), `.wp-env.json` (con DB Form Builder e DB Privacy
      Hub), `playwright.config.js` (desktop + Pixel 7), `bin/setup-e2e.sh`
- [ ] mu-plugin di test: cattura di `wp_mail`, fuso `Europe/Rome`, pagine fixture
      con shortcode e blocchi, cache di pagina simulata
- [ ] Workflow `e2e.yml` riutilizzabile, `nightly.yml` (WordPress trunk, PHP più
      recente), dipendenze tra job come nel Cookie Manager
- [ ] `.gitattributes`: `export-ignore` per i nuovi file (lo ZIP nasce da `git archive`)
- [ ] `TESTING.md`

## 5. Fase 1 — Unit test

Bug coperti: #3, #5, #18, #19, #25 (salvataggio PIN), #30, #31, #37, #39.

- [ ] Sanitizzazione e whitelist dei campi custom (#18)
- [ ] `build_html_email`: link solo dal template (#19)
- [ ] Export CSV: intestazioni e celle neutralizzate (#3)
- [ ] Dichiarazioni a Privacy Hub per ogni configurazione (#5)
- [ ] Helper fuso orario (#7), con fuso diverso da UTC e ora legale

## 6. Fase 2 — Integration test (WordPress + MySQL)

Bug coperti: #1, #2, #4, #6, #7, #8, #9, #10, #12, #13, #14, #16, #17, #20,
#21, #24, #25 (raffica), #27, #32–#36.

- [ ] Attivazione, schema e upgrade da uno schema 1.2; multisite
- [ ] Iscrizione: form integrato e DBFB, consenso, stati del post, posti, concorrenza
- [ ] Stati dell'iscrizione: ogni azione (admin, blocco, pagina pubblica) su ogni stato
- [ ] Cron: programmazione con fuso, cestino, disattivazione/riattivazione
- [ ] DSAR: export a pagine (> 100 righe), cancellazione ripetuta, transient di modifica
- [ ] Uninstall con e senza «elimina dati», cestino compreso
- [ ] Duplica: meta serializzati, termini, immagine, nessun partecipante

## 7. Fase 3 — E2E (wp-env + Playwright)

Bug coperti: #11, #15, #22, #23, #26, #42, accessibilità #45–#48.

- [ ] Iscrizione anonima da pagina in cache «vecchia» (regressione 1.8.0); 403 senza Origin
- [ ] Form integrato: validazione, campi obbligatori, honeypot, 429 visibile
- [ ] DB Form Builder end-to-end
- [ ] Email catturate: conferma, attesa, approvazione dal link, rifiuto, modifica
- [ ] Check-in admin e pagina pubblica con PIN (blocco dopo 10 errori), telefono
- [ ] Partecipanti: azioni, aggiunta manuale, export CSV scaricato
- [ ] Survey: link personale, invio, risultati
- [ ] Admin: evento con campi, Duplica (elenco ed editor), gestore delegato
- [ ] axe su form, pagina evento, archivio, check-in, partecipanti (anche con colori personalizzati)
- [ ] Ecosistema con Privacy Hub: registro trattamenti, registro consensi, DSAR

---

## 8. Ordine proposto delle correzioni

1. ✅ **1.9.0 (prima del rilascio):** gli A (#1–#5), #6 attivazione, #10 eventi non
   pubblicati, #23 Duplica nell'editor a blocchi, #12–#13 stati nelle azioni
   admin, #19 link nelle email, più D1, D2, D5.
2. **Fuso orario (#7)** in una PR dedicata: tocca cron, scadenze, stati e tutte
   le visualizzazioni; serve l'integration test con Europe/Rome.
3. Il resto dei B e i C seguendo le fasi, dopo le decisioni D1–D7.

## 9. Convenzioni

- Ogni bug corretto: casella ✅ in tabella, test che fallisce prima e passa dopo,
  voce nel changelog del README («cosa succedeva prima, cosa succede ora»).
- Una fase = una PR verso `sviluppo`; rilascio unico via tag a fine lavori.
- I test non toccano lo ZIP di release (`export-ignore`).
