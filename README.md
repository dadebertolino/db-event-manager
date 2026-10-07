# DB Event Manager

Gestione eventi con iscrizione, QR code personale, check-in e survey post-evento.  
Niente Eventbrite, niente SaaS, niente abbonamenti. Tutto nel tuo WordPress.

**Versione:** 1.9.1
**Autore:** [Davide Bertolino](https://www.davidebertolino.it)  
**Licenza:** GPL v2 or later  
**Richiede:** WordPress 6.0+, PHP 7.4+  
**GitHub:** [dadebertolino/db-event-manager](https://github.com/dadebertolino/db-event-manager)

---

## Cosa fa

### 📅 Gestione eventi
- Crea eventi con nome, descrizione (editor Gutenberg), data inizio/fine, luogo, posti disponibili
- Categorie evento gerarchiche per organizzare e filtrare
- Chiusura automatica iscrizioni (posti esauriti o deadline)
- Stato evento automatico: bozza, in programma, in corso, concluso
- Pagina singola evento e archivio generati automaticamente dal plugin
- **Duplica evento**: copia impostazioni, form, email, survey, categorie e immagine in una nuova bozza, senza partecipanti

### 📝 Iscrizione frontend
- Due modalità form:
  - **Form integrato** con campi personalizzabili drag & drop
  - **DB Form Builder** — usa un form DBFB esistente (se il plugin è installato)
- Due modalità accettazione:
  - **Automatica** — iscrizione confermata subito, QR code immediato
  - **Con approvazione** — iscrizione in attesa, l'approvatore riceve email con bottoni Approva/Rifiuta
- Barra progresso posti, badge stato, messaggi dinamici
- Honeypot anti-spam, rate limiting, GDPR checkbox
- Accessibilità WCAG 2.1 AA

### ✅ Approvazione con assegnazione orario
- Modalità configurabile per evento: automatica o con approvazione
- **Assegnazione orario**: l'approvatore può assegnare un orario al partecipante al momento dell'approvazione
- Email approvatore personalizzabile (può essere diverso dal creatore evento)
- L'approvatore riceve email con bottoni ✅ Approva e ❌ Rifiuta, niente login
- Il bottone apre una pagina con il riepilogo dell'iscrizione e i pulsanti Approva/Rifiuta: la decisione parte solo dal pulsante, così i filtri antivirus e le anteprime dei link nelle email non approvano né rifiutano da soli
- Se l'assegnazione orario è attiva, la stessa pagina contiene il campo orario
- Link protetti con HMAC (non indovinabili, non riusabili)
- Approvazione → genera QR code → invia email conferma all'iscritto (con orario se assegnato)
- Rifiuto → invia email notifica all'iscritto
- Gestibile anche dalla pagina Partecipanti admin o pubblica (singola e bulk)

### 📱 QR Code personale
- QR code generato con phpqrcode (LGPL 3), libreria PHP pura con namespace isolato (`DBEM_`) per evitare conflitti con altri plugin
- Visibile nel corpo dell'email di conferma + allegato PNG
- Contiene link univoco per check-in
- Leggibile da qualsiasi scanner (smartphone, app dedicate)

### ✅ Check-in con QR code
- **Pagina pubblica check-in** — aprila sul telefono, niente login WordPress
- Protetta da PIN obbligatorio (generato automaticamente, modificabile in Impostazioni); ogni evento può avere un PIN dedicato
- Il PIN apre solo gli eventi pubblicati che gli corrispondono: un QR di un altro evento non passa
- Scanner QR integrato (fotocamera smartphone)
- Ricerca manuale per nome/email sugli eventi aperti dal PIN (i risultati non contengono i token dei QR code)
- Le iscrizioni in attesa di approvazione o rifiutate non passano il check-in
- Feedback visivo grande e chiaro: ✅ Presente, ⚠️ Già registrato, ❌ Non valido
- Dopo check-in riuscito, lo scanner si riapre automaticamente
- Funziona anche dalla pagina admin Event Manager → Check-in

### 👥 Pagina pubblica partecipanti
- **Pagina pubblica** accessibile da telefono senza login WordPress
- Protetta dallo stesso PIN del check-in
- Selettore con i soli eventi aperti dal PIN (con un solo evento si apre direttamente), contatore presenti/iscritti, filtri per stato
- Tabella con nome, email, stato, orario assegnato
- Azioni: approva, rifiuta, segna presente, annulla, reinvia email, modifica orario
- **Iscrizione manuale**: il responsabile può iscrivere un partecipante direttamente (nome + email + orario opzionale), con generazione QR e invio email automatico
- **Modifica orario**: bottone 🕐 su ogni riga per modificare l'orario assegnato. Per notificare il partecipante del cambio, premere 📧
- **Export CSV**: scarica la lista filtrata in formato CSV
- Link: `tuosito.it/?dbem_participants_page=1`

### 📧 Email automatiche
- Conferma iscrizione con QR code nel corpo + allegato
- Notifica "in attesa di approvazione" (per modalità con approvazione)
- Richiesta approvazione all'approvatore con bottoni Approva/Rifiuta
- Notifica rifiuto iscrizione
- Promemoria evento (configurabile per evento: N ore prima dell'inizio)
- Survey post-evento (manuale o automatico)
- Email annullamento
- Notifica admin personalizzabile per evento (anche più destinatari)
- Segnaposto dinamici: {nome}, {email}, {evento}, {data_evento}, {luogo}, {orario}, {riepilogo_dati}, {qrcode_url}, {token}, {sito}, {survey_link}; nel promemoria anche {periodo}, {dettagli}, {scelte}, {attivita}
- Segnaposto cliccabili sotto ogni editor (conferma, survey, promemoria): un clic li inserisce nel punto del cursore, nell'oggetto o nel messaggio
- Segnaposto dei campi del form integrato: `{campo:id}` inserisce il valore compilato in quel campo. L'id non cambia se si rinomina l'etichetta; i segnaposto di campi eliminati spariscono dal testo
- Mittente configurabile in Impostazioni (nome e indirizzo), con ripiego sul nome del sito e sull'email dell'amministratore
- Compatibile con qualsiasi plugin SMTP

### 📋 Survey post-evento
- Campi survey configurabili per evento
- Link univoco per partecipante (no login richiesto)
- Invio a tutti o solo ai presenti (checked-in)
- Riepilogo risposte nell'admin con conteggi
- Export CSV

### 👥 Gestione partecipanti
- Tabella con nome, email, stato, check-in, orario assegnato
- Stati: 🕐 In attesa, ⏳ Confermato, ✅ Presente, ❌ Annullato, 🚫 Rifiutato
- Azioni con tooltip: approva, rifiuta, conferma, annulla, segna presente, reinvia email, elimina
- Azioni bulk (conferma, annulla, rifiuta, segna presente, elimina)
- Export CSV con tutti i dati + orario assegnato

### 🗓️ Eventi su più giorni
- Se data di inizio e fine cadono in giorni diversi, il riquadro della card mostra l'intervallo (`21-25 SET`, `28-03 SET-OTT`) e compare il badge "Più date"
- Opzione per evento per nascondere del tutto il giorno nel riquadro e lasciare solo mese e anno

### 🏷️ Categorie evento
- Tassonomia gerarchica (come le categorie WordPress)
- Colonna categoria nella lista admin
- Badge categoria nelle card evento
- Filtro shortcode per categoria

### 🔗 Integrazione DB Form Builder
- Se DB Form Builder è installato, puoi usare un form DBFB esistente come form di iscrizione
- Mappatura campi Nome e Email del form DBFB
- DBFB gestisce validazione e raccolta dati, DBEM gestisce iscrizione + QR + email

### 🧩 Blocchi Gutenberg
- Blocco "Evento singolo" con selettore evento
- Blocco "Lista eventi" con filtro passati/futuri e limite

### 🔄 Aggiornamenti automatici
- Il plugin si aggiorna dal pannello Plugin di WordPress
- Notifica automatica quando esce una nuova versione
- Aggiornamento con un clic, niente download manuali

### ⚙️ Impostazioni
- Pagina elenco eventi personalizzabile (pagina WP o archivio automatico)
- Titolo pagina archivio configurabile
- PIN di sistema obbligatorio per le pagine pubbliche (check-in e partecipanti), da 4 a 10 cifre: generato automaticamente, rigenerabile dalla pagina Impostazioni. Apre gli eventi pubblicati senza un PIN proprio
- PIN dedicato per evento (scheda Iscrizioni): da dare a chi gestisce solo quell'ingresso; l'evento si apre solo con quel PIN
- Link check-in e partecipanti da condividere con lo staff
- Opzione "Elimina tutti i dati alla disinstallazione" (disattivata di default)
- Riepilogo shortcode disponibili

### 🎨 Colori
- Tre colori: sfondo, pulsanti e testo, impostabili in Impostazioni → Colori e, per il singolo evento, nei Dettagli Evento
- Precedenza: colore dell'evento → colore globale → colore predefinito. Un campo vuoto eredita
- I colori valgono per la pagina evento, lo shortcode `[dbem_event]`, gli elenchi (il contenitore usa i colori globali, ogni card quelli del suo evento) e il form di iscrizione integrato. Le pagine di check-in, partecipanti e sondaggio mantengono il loro aspetto
- Gli altri colori sono calcolati per restare WCAG 2.1 AA: testo dei pulsanti bianco o nero secondo il contrasto, hover più scuro o più chiaro, link nel colore dei pulsanti solo se leggibili sullo sfondo, testo nero o bianco se si imposta solo lo sfondo
- Campi di input, avvisi, badge e messaggi hanno colori fissi, leggibili su qualunque sfondo
- Anteprima nell'admin e avviso quando il contrasto tra testo e sfondo è sotto 4,5:1
- Senza colori impostati l'aspetto resta quello di sempre

Variabili CSS pubbliche, impostabili anche dal tema su un contenitore della pagina: `--dbem-bg`, `--dbem-surface` (sfondo delle card), `--dbem-border`, `--dbem-text`, `--dbem-text-muted`, `--dbem-primary`, `--dbem-primary-hover`, `--dbem-button-text`, `--dbem-link`.

### 👤 Gestori degli eventi
- Un amministratore può abilitare la gestione completa degli eventi dal profilo dell'utente, in **Utenti → Modifica utente → DB Event Manager**
- L'utente autorizzato può creare, modificare, pubblicare ed eliminare eventi e categorie
- Può inoltre usare partecipanti, check-in, survey, reminder ed esportazioni
- Non può accedere o modificare le impostazioni globali del plugin
- Se il suo ruolo non permette di caricare file, riceve anche `upload_files` per l'immagine in evidenza; il permesso viene tolto quando la gestione eventi è disattivata
- Alla disinstallazione le capability vengono rimosse da ruoli e utenti

---

## Installazione

1. Scarica lo ZIP da [GitHub Releases](https://github.com/dadebertolino/db-event-manager/releases/latest)
2. WordPress Admin → Plugin → Aggiungi nuovo → Carica plugin → Seleziona ZIP
3. Attiva il plugin
4. Vai in **Impostazioni → Permalink → Salva modifiche**
5. Nel menu admin compare **Event Manager** con icona calendario

### Primo utilizzo

1. **Event Manager → Aggiungi Evento** — compila nome, date, luogo
2. Scrivi la descrizione nell'editor Gutenberg
3. Configura il form iscrizione (integrato o DB Form Builder)
4. Scegli la modalità: accettazione automatica o con approvazione
5. Se scegli approvazione, abilita "Assegnazione orario" per permettere di assegnare un orario a ogni partecipante
6. Personalizza l'email di conferma (usa {orario} per includere l'orario assegnato)
7. Pubblica — il link è nella sidebar

Per delegare la gestione operativa, vai in **Utenti**, apri l'utente desiderato e attiva **Gestione eventi** nella sezione **DB Event Manager**.

### Check-in all'ingresso

1. **Event Manager → Impostazioni** — imposta un PIN (oppure un PIN dedicato nella scheda Iscrizioni dell'evento)
2. Condividi il link check-in e il PIN con lo staff
3. All'ingresso: link sul telefono → PIN → scansiona QR
4. Se qualcuno non ha il QR: cerca per nome nella barra di ricerca

### Gestione partecipanti da telefono

1. Apri `tuosito.it/?dbem_participants_page=1`
2. Inserisci il PIN
3. Seleziona l'evento
4. Vedi la lista iscritti con stato, orario, contatore
5. Approva, rifiuta, segna presente, modifica orario o reinvia email
6. Usa ➕ per iscrivere manualmente un partecipante
7. Usa 📥 per scaricare l'export CSV

---

## Shortcode

| Shortcode | Descrizione |
|-----------|-------------|
| `[dbem_event id="X"]` | Dettagli evento + form iscrizione |
| `[dbem_events]` | Lista eventi futuri |
| `[dbem_events past="1"]` | Lista eventi passati |
| `[dbem_events limit="5"]` | Limita il numero |
| `[dbem_events cols="2"]` | Layout a 2 colonne (fino a 4) |
| `[dbem_events category="workshop"]` | Filtra per categoria (slug) |
| `[dbem_events category="workshop,seminario"]` | Più categorie |

Tutti i parametri sono combinabili.

---

## Pagine pubbliche

| URL | Descrizione | Protezione |
|-----|-------------|------------|
| `/?dbem_checkin_page=1` | Check-in con scanner QR | PIN |
| `/?dbem_participants_page=1` | Lista partecipanti con azioni | PIN |
| `/?dbem_checkin={token}` | Check-in diretto da QR code | Token univoco |
| `/?dbem_survey={token}` | Survey post-evento | Token univoco |

---

## Struttura cartelle

```
db-event-manager/
├── db-event-manager.php
├── uninstall.php                # Pulizia dati alla rimozione (opzionale)
├── README.md
├── LICENSE
├── assets/
│   ├── css/
│   │   ├── db-admin-ui.css      # Design system condiviso
│   │   ├── admin.css
│   │   └── frontend.css
│   └── js/
│       ├── admin.js
│       ├── frontend.js
│       ├── checkin.js
│       ├── blocks.js
│       └── vendor/
│           ├── Sortable.min.js
│           └── html5-qrcode.min.js
├── inc/
│   ├── class-updater.php        # GitHub auto-updater
│   ├── class-security.php       # PIN, nonce e rate limit endpoint pubblici
│   ├── class-db.php
│   ├── class-cpt.php
│   ├── class-admin.php
│   ├── class-frontend.php
│   ├── class-registration.php
│   ├── class-email.php
│   ├── class-qrcode.php
│   ├── class-checkin.php
│   ├── class-survey.php
│   ├── class-export.php
│   ├── class-cron.php
│   ├── class-shortcodes.php
│   ├── class-gutenberg.php
│   ├── class-privacy-declarations.php
│   ├── class-privacy-dsar.php
│   └── lib/
│       └── phpqrcode.php        # QR code PHP pura (LGPL 3), classi prefissate DBEM_
├── languages/                   # File di traduzione (.pot/.po/.mo)
└── templates/
    ├── single-dbem_event.php
    ├── archive-dbem_event.php
    ├── admin/
    │   ├── checkin.php
    │   ├── participants.php
    │   └── survey.php
    └── frontend/
        ├── checkin.php
        ├── participants.php
        └── survey.php
```

---

## Integrazione ecosistema DB privacy

Quando uno o più di questi plugin sono installati, DB Event Manager li sfrutta automaticamente — senza configurazione.

### Dichiarazione trattamenti al registro privacy unificato
Quando **DB Privacy Hub 1.0.0+** è installato, il plugin dichiara automaticamente i trattamenti nel pannello "Privacy → Registro trattamenti".

| ID | Quando appare |
|---|---|
| `dbem_registrations` | Sempre (almeno 1 evento pubblicato) |
| `dbem_email` | Almeno 1 evento con email di conferma configurata |
| `dbem_survey` | Almeno 1 evento con survey attivo |

### DSAR (accesso e cancellazione dati)
Il plugin risponde alle richieste di esportazione/cancellazione dati personali (GDPR art. 15 e 17) tramite doppio canale: Privacy Hub (primario) e WordPress core (fallback). L'export include registrazioni, risposte survey e campi custom. La cancellazione rimuove anche i file QR code associati.

### Consenso documentato (art. 7.1 GDPR)
Ogni iscrizione con checkbox GDPR attiva salva 5 campi di prova del consenso: flag consenso, testo esatto mostrato all'utente, timestamp, URL informativa privacy, ID versione della Privacy Policy (se Privacy Hub è installato). I consensi sono visibili nel "Registro consensi" unificato del Privacy Hub.

### Marker `DBEM_DSAR_AVAILABLE`
La costante segnala al Privacy Hub che il plugin supporta DSAR, permettendo di menzionare la procedura semplificata nella Privacy Policy generata.

---

## Note tecniche

- **QR code**: phpqrcode (LGPL 3), classi prefissate `DBEM_` per evitare conflitti con altri plugin
- **Scanner QR**: html5-qrcode inclusa localmente (375KB)
- **Drag & drop**: SortableJS inclusa localmente (45KB)
- **Sicurezza**: nonce (admin e utenti loggati), controllo di origine Origin/Referer per le iscrizioni dei visitatori anonimi (compatibile con la cache di pagina), capability check, sanitizzazione, rate limiting (hash salato dell'IP, filtro `dbem_registration_rate_limit`), PIN check-in/partecipanti, HMAC per link approvazione
- **Token**: bin2hex(random_bytes(32)) — 64 caratteri hex
- **Email**: HTML responsive, compatibile con plugin SMTP
- **Auto-updater**: controlla GitHub Releases ogni 12h
- **Integrazione DBFB**: rileva se DB Form Builder è attivo, nessuna dipendenza hard
- **Template**: sovrascrivibili dal tema
- **Timezone**: le date degli eventi sono salvate in ora locale e visualizzate senza conversione timezone

### Sviluppo
Dopo `composer install`:
- `composer test` — unit test PHPUnit, senza WordPress (`tests/unit/`)
- `composer test:integration` — test con WordPress e MySQL veri (`tests/integration/`), dopo `bin/install-wp-tests.sh`
- `composer phpcs` — regole `WordPress.Security` e compatibilità con PHP 7.4+ (PHPCompatibilityWP) su tutto il PHP del plugin, template compresi; ogni violazione è un errore
- `composer check-js` — sintassi dei file in `assets/js` e degli script inline nei file PHP

Test nel browser (wp-env + Playwright): `npm ci`, `npx wp-env start`, `npm run env:setup`, `npx playwright test`.

La CI esegue a ogni push sintassi e unit test su PHP 7.4–8.5, PHPCS, il controllo JavaScript, gli integration test (WordPress 6.0 e latest, anche multisite) e gli E2E; ogni notte gli stessi contro WordPress in sviluppo. Dettagli in [TESTING.md](TESTING.md). Un tag `vX.Y.Z` pubblica la release solo se tag, header `Version` e `DBEM_VERSION` coincidono e il README ha la voce `### X.Y.Z`; lo ZIP allegato contiene la cartella `db-event-manager/` senza test e file di sviluppo.

---

## Accessibilità (WCAG 2.1 AA)

- aria-required, aria-invalid, aria-describedby sui form
- fieldset/legend per radio/checkbox
- role="alert" + aria-live per messaggi dinamici
- Tooltip CSS con aria-label
- Touch target ≥ 44×44px, contrasto ≥ 4.5:1
- Supporto prefers-reduced-motion e forced-colors
- Check-in: feedback con icona + testo + colore

---

## Changelog

### 1.9.1
**Fuso orario, posti, stati delle iscrizioni e altre correzioni**

Patch: solo correzioni, dalla Fase 2 di `TESTING-PLAN.md` (numeri tra parentesi). Ogni correzione
ha il suo test; da questa versione girano anche gli integration test (WordPress e MySQL veri) e gli
E2E nel browser, vedi [TESTING.md](TESTING.md).

**Fuso orario (#7):**
- Le date degli eventi sono salvate in ora locale, ma venivano lette come UTC (WordPress tiene PHP in
  UTC). Su un sito italiano in estate: promemoria e survey automatici partivano **2 ore dopo** il
  previsto (un promemoria «1 ora prima» arrivava dopo l'inizio), le iscrizioni restavano aperte 2 ore
  oltre la scadenza, gli stati «in corso» e «concluso» erano sfasati, e gli orari di iscrizione,
  check-in e survey comparivano +2 ore nelle pagine admin, check-in e approvazione. Ora ogni
  conversione passa da `DBEM_Time` con il fuso del sito. Promemoria già programmati: si correggono
  risalvando l'evento o disattivando e riattivando il plugin

**Posti e invii automatici (#8, #16, #17):**
- Le iscrizioni rifiutate non occupano più posti
- Il controllo orario non chiude più le iscrizioni per posti esauriti in modo permanente: i posti si
  contano al momento, e un annullamento o un rifiuto riapre le iscrizioni da solo (la scadenza
  continua a chiuderle)
- Promemoria e survey automatici partono solo per eventi pubblicati: un evento annullato e spostato
  nel cestino non manda più il promemoria
- Disattivare e riattivare il plugin cancellava tutti i promemoria e survey programmati: ora la
  riattivazione li riprogramma

**Stati delle iscrizioni (#14, #13 C, #20, #21):**
- La pagina partecipanti da telefono usa le stesse regole dell'admin: ogni azione solo dallo stato di
  partenza ammesso (prima «approva» su un presente lo riportava a confermato). Riconfermare un
  annullato o un rifiutato controlla i posti e invia QR ed email (prima non partiva nulla)
- Ogni cambio di stato ricontrolla lo stato nel database: due scansioni dello stesso QR, o due
  operatori insieme, non fanno il check-in due volte (la seconda risponde «già registrato»)
- Orario assegnato oltre 50 caratteri: prima il salvataggio falliva in silenzio ma QR ed email di
  approvazione partivano e la pagina diceva «approvata». Ora il valore viene rifiutato prima, e senza
  salvataggio non parte nulla
- Survey: si risponde solo con il survey attivo e un'iscrizione confermata o presente (prima un invio
  diretto funzionava anche a survey disattivato, e da iscritti in attesa, rifiutati o annullati)

**Form e pagine (#18, #11, #15, #26, #27, #28, #37, #39):**
- Campi del form integrato validati anche lato server: scelte solo tra le opzioni definite, email,
  numeri e date nel loro formato; le aree di testo conservano gli a capo; «0» è una risposta valida
  nei campi obbligatori (anche nel survey); i messaggi d'errore non mostrano più `&#039;` al posto
  dell'apostrofo
- Eventi protetti da password: pagina, shortcode e iscrizione chiedono la password (prima descrizione
  e form erano visibili e utilizzabili)
- Check-in admin: un QR di un altro evento rispetto a quello scelto non fa il check-in e lo segnala
- Pagina partecipanti da telefono: cambiando evento in fretta non compaiono più i dati di quello
  precedente; la ricerca restituisce fino a 25 risultati
- Le barre rovesciate nei testi dell'evento non si perdono più al salvataggio («Aula B\2»)

**Iscrizioni contemporanee e PIN (#9, #25, #22):**
- Due o più invii insieme sull'ultimo posto: prima entravano tutti (posti superati) e con la stessa
  email nascevano doppioni. Controllo dell'email, dei posti e salvataggio avvengono ora sotto un lock
  MySQL per evento (`GET_LOCK`), anche per DB Form Builder e per l'aggiunta manuale da telefono
- Tentativi di PIN errati: una raffica di richieste parallele superava il limite di 10; ora il
  contatore è aggiornato sotto lock
- DB Form Builder: l'iscrizione all'evento partiva anche quando DBFB mostrava un errore (validazione,
  captcha), perché bastava un testo qualsiasi nella zona messaggi. Ora solo dopo l'invio riuscito

**Database e privacy (#32, #33, #34, #35, #36):**
- Schema con versione (`dbem_db_version`): tabelle, colonne e indici si aggiornano con `dbDelta` solo
  al cambio di versione. Prima ogni richiesta eseguiva controlli sulle tabelle e un indice nuovo non
  arrivava mai a chi aggiornava. Indice sull'email sui primi 191 caratteri (MySQL 5.6 / MariaDB 10.1),
  indice sui campi del consenso per il registro di Privacy Hub
- Export e cancellazione dei dati personali: ricerca dell'email con l'indice; la cancellazione non può
  più ciclare all'infinito se il database restituisce un errore; vengono cancellate anche le modifiche
  in attesa di conferma (nome, email, IP e campi conservati per 24 ore)
- Registro consensi di Privacy Hub: nessun errore SQL se il plugin è attivo ma non ha ancora tabelle;
  email mascherate correttamente anche con lettere accentate

**Email ed export (#29, #30, #31, #38):**
- I testi delle email sono testo semplice: prima si salvavano con l'HTML ma l'invio lo mostrava
  letterale (`<b>`). I testi già salvati vengono convertiti all'invio
- Un meta email malformato non impedisce più di aprire l'evento (errore fatale su PHP 8)
- Un parametro inviato come array (`dbem_email[]=…`) non manda più in errore 500 l'iscrizione
- Export CSV dell'admin separato da punto e virgola, come quello della pagina partecipanti: Excel in
  italiano lo apriva in una sola colonna

**Pagine (#40, #41, #42, #43):**
- Pagina di approvazione nella lingua del sito; pulsanti «Approva» e «Rifiuta» delle email traducibili
- Menu eventi di Partecipanti e Survey: anche eventi programmati, privati e in revisione, senza il
  limite di 100 (50 per il Check-in)
- Check-in da telefono: la fotocamera si riaccende solo dopo una scansione, non dopo una ricerca
  manuale; un avviso nuovo non viene più nascosto dal timer di quello precedente
- Builder dei campi: a ogni modifica si aggiungeva un'istanza del trascinamento, che ripeteva il
  riordino a ogni spostamento

**Altro (#24, #49):**
- Disinstallazione con «Elimina tutti i dati»: vengono eliminati anche gli eventi nel cestino e
  l'opzione della versione dello schema
- Libreria QR: niente più avvisi di deprecazione su PHP 8, che con la visualizzazione degli errori
  attiva potevano finire in una risposta o nel PNG

### 1.9.0
**Duplica evento, PIN per evento, correzioni di sicurezza e privacy**

Minor: nuove funzioni (Duplica, PIN per evento, conferma delle opzioni rinominate) e cambia la
risposta del modulo di iscrizione. Le correzioni vengono dall'analisi del codice in
`TESTING-PLAN.md` (i numeri tra parentesi sono quelli del piano).

**Duplica evento:**
- Nuova azione «Duplica» nell'elenco eventi e riquadro «Duplica evento» nella colonna laterale
  dell'editor (a blocchi e classico): crea una **bozza** con tutte le impostazioni dell'originale (nome, descrizione, date, luogo, posti,
  campi del form o collegamento a DB Form Builder, approvazione, privacy, email di conferma e
  promemoria, survey, aspetto, categorie, immagine in evidenza) e la apre nell'editor
- **Non** vengono copiati partecipanti, check-in e risposte al survey: stanno nelle tabelle del plugin,
  legate all'evento originale
- Le iscrizioni della copia ripartono **aperte**, anche se sull'originale le aveva chiuse il cron per
  scadenza o posti esauriti. Un avviso ricorda di aggiornare date, luogo e scadenza prima di pubblicare
- Promemoria e survey automatici non vengono programmati finché non si salva la copia, quindi con le
  date nuove
- Permessi: serve poter creare eventi e modificare quello originale (link con nonce)
- Non vengono copiati il PIN dedicato dell'evento né le rinomine di opzioni in attesa di decisione
- Per gli sviluppatori: filtro `dbem_duplicate_skipped_meta` (meta da non copiare) e azione
  `dbem_event_duplicated` (`$new_id`, `$source_id`)

**PIN per evento e pagine pubbliche (A4, D1):**
- Ogni evento può avere un **PIN dedicato** (scheda Iscrizioni, 4-10 cifre). Se vuoto vale il PIN
  di sistema delle Impostazioni. Un evento con PIN dedicato si apre solo con quello
- Prima un solo PIN apriva i dati di **tutti** gli eventi, anche bozze, privati e nel cestino: con un
  `event_id` qualsiasi si leggevano nomi, email e campi e si agiva sulle iscrizioni. Ora ogni chiamata
  (lista, azioni, aggiunta, orario, ricerca, check-in da QR o da ricerca) verifica che l'evento sia
  pubblicato e aperto da quel PIN; un QR di un altro evento risponde «non accessibile con questo PIN»
- La pagina partecipanti riceve l'elenco degli eventi dopo il PIN (prima era stampato nell'HTML della
  pagina per chiunque); con un solo evento lo apre direttamente
- PIN validati al salvataggio: da 4 a 10 cifre. Prima si poteva salvare «1», o un PIN più lungo di
  quanto il campo del telefono permettesse di digitare

**Iscrizione (A1, A2, B10, D2):**
- Il modulo **non rivela più chi è iscritto**: a un indirizzo nuovo e a uno già iscritto risponde
  «Richiesta ricevuta! Ti abbiamo inviato un'email con i dettagli». Prima «Questo indirizzo email è già
  registrato» o la domanda «vuoi sostituire la prenotazione?» dicevano a chiunque se una persona
  partecipava. Ora chi è già iscritto riceve il link per confermare la modifica (reiscrizione attiva)
  oppure un avviso che l'iscrizione esiste già (al massimo uno all'ora); lo stato dell'iscrizione non
  viene mai indicato. Iscrizioni chiuse o posti esauriti valgono per tutti, anche per chi è già iscritto
- L'endpoint di DB Form Builder accettava anche eventi con il form integrato: con un invio diretto ci
  si iscriveva **senza consenso privacy** e senza i campi obbligatori. Ora ogni endpoint accetta solo
  gli eventi con il proprio form
- Iscrizioni solo a eventi **pubblicati**: prima si poteva iscriversi a bozze o eventi nel cestino, e
  l'email di conferma ne rivelava i dati
- Una modifica confermata senza nuovo consenso (GDPR disattivato nel frattempo, campo privacy DBFB non
  mappato) non cancella più la **prova del consenso originale** (art. 7.1)

**Opzioni rinominate (D5):**
- Cambiare il testo di un'opzione scelta da qualche iscritto non riscrive più le iscrizioni da solo.
  Nel riquadro del form, per ogni rinomina: «Aggiorna N iscrizioni» (stessa opzione, testo corretto) o
  «Lascia com'è» (opzione nuova al posto di un'altra). Prima, sostituendo «Lab 10 ott» con «Lab 24 ott»
  perché pieno, tutti gli iscritti del 10 passavano al 24 senza possibilità di tornare indietro.
  Nell'editor a blocchi il riquadro si aggiorna dopo il salvataggio e compare un avviso

**Altre correzioni:**
- Export CSV: anche le intestazioni passano dalla neutralizzazione delle formule. I nomi delle colonne
  dei campi vengono dai dati inviati con il form, e una chiave come `=HYPERLINK(...)` diventava una
  formula all'apertura in Excel (A3)
- Registro trattamenti di Privacy Hub: considera anche eventi in bozza, privati, programmati e nel
  cestino (prima, senza eventi pubblicati, il registro restava vuoto con i dati ancora nel database);
  dichiara sempre le email transazionali (prima solo con un testo di conferma personalizzato) e gli
  invii dei dati agli approvatori e agli indirizzi di notifica (A5)
- Attivazione: l'hook era registrato dentro `plugins_loaded` e non partiva mai, quindi su
  un'installazione nuova le pagine evento e `/eventi/` davano 404 fino al salvataggio dei permalink (B6)
- Azioni sulle iscrizioni (singole e in blocco) solo dagli stati di partenza ammessi: «Conferma» con
  «seleziona tutti» riportava i presenti a confermati e rimandava loro l'email, «Rifiuta» rimandava
  l'email ai già rifiutati, «Segna presente» marcava anche annullati e rifiutati. Le iscrizioni saltate
  vengono indicate. «Reinvia email» solo alle iscrizioni confermate (B12, B13)
- Email: gli indirizzi web scritti dall'iscritto (nel nome o nei campi) non diventano più link
  cliccabili nelle email inviate dal sito, a lui o ai responsabili (B19)

**Aggiornamento da GitHub (updater 1.1.0, lo stesso di DB Privacy Hub e DB Debug Manager):**
- Dopo l'aggiornamento il plugin viene riattivato **solo se era attivo**, anche se attivo su tutta
  la rete di un multisite. Prima veniva sempre attivato, anche se l'amministratore lo aveva disattivato
- Release letta in modo più robusto: si usa un asset `.zip` valido, in mancanza lo ZIP del sorgente;
  se mancano entrambi l'aggiornamento non viene proposto
- Nessun errore se il filesystem di WordPress non è disponibile o l'installazione non restituisce
  la cartella di destinazione
- Test: `UpdaterTest` (release, scelta dello ZIP, cache degli errori, cartella, riattivazione)

**Requisiti e test:**
- WordPress minimo **6.0** (prima 5.8), come gli altri plugin DB; PHP minimo invariato, 7.4
- Export CSV di partecipanti e survey: `fputcsv()` riceve l'escape vuoto (CSV standard, RFC 4180).
  Su PHP 8.4 la chiamata senza escape genera un avviso di deprecazione, che con `display_errors`
  attivo poteva finire dentro il file; una barra rovesciata prima di una virgoletta ora resta testo
- CI: `php -l` e PHPUnit su PHP 7.4, 8.0, 8.1, 8.2, 8.3, 8.4 e 8.5 (prima `php -l` su 7.4 e 8.3 e
  test solo su 8.2: PHPUnit 11 non gira su PHP 7.4). PHPUnit passa alla 9.6, la piattaforma Composer
  è fissata a PHP 7.4; run annullata a ogni nuovo push sullo stesso branch; azioni GitHub e Node aggiornati
- PHPCS: aggiunto PHPCompatibilityWP con `testVersion 7.4-` (versione 10 alpha, l'unica che conosce
  la sintassi di PHP 8)

### 1.8.0
**Iscrizioni compatibili con la cache di pagina + dichiarazioni privacy accurate**

Minor e non patch: cambia il contratto di verifica dell'endpoint pubblico di iscrizione
(niente più nonce per gli anonimi), nasce il filtro pubblico `dbem_registration_rate_limit`
e cambia il testo dichiarato al Registro trattamenti del Privacy Hub.

**Iscrizioni con cache di pagina (WP Rocket, LiteSpeed Cache, Cloudflare APO, cache dell'hosting):**
- Prima il form (integrato e DB Form Builder) inviava un nonce stampato nell'HTML: con la pagina
  in cache il nonce scadeva dopo 12–24 ore e da quel momento **ogni iscrizione veniva rifiutata**
  con «Richiesta non valida.»
- Ora `dbem_register` e `dbem_register_dbfb` verificano: utenti loggati → nonce (le loro pagine non
  sono in cache); visitatori anonimi → header `Origin` (in mancanza `Referer`) dello stesso host di
  `home_url()`/`site_url()`, più honeypot e rate limit per IP già esistenti. Il nonce viene stampato
  solo per gli utenti loggati. Stesso schema di `DBCM_Consent_API` (DB Cookie Manager 3.7.1)
- Rate limit: la chiave del transient è un hash SHA-256 salato dell'IP (prima `md5` senza sale,
  invertibile); limite filtrabile con `dbem_registration_rate_limit` (default 5/minuto, `0` disattiva).
  Superato il limite l'endpoint risponde HTTP 429; origine non valida o sessione scaduta → HTTP 403
- Frontend: le risposte non riuscite non sono più silenziose. `console.warn` con lo stato HTTP e
  messaggio del server mostrato all'utente (prima un 403/429 dava solo «Errore. Riprova.» e il form
  DB Form Builder non mostrava nulla se la risposta non era JSON)

**Stesso schema per gli altri endpoint pubblici (questionario, check-in e partecipanti da telefono):**
- La logica è ora condivisa in `DBEM_Security::verify_request()` (nonce per i loggati; `Origin`/`Referer`
  dello stesso sito + rate limit per gli anonimi), `origin_matches()`, `check_rate_limit()` (chiave
  transient = hash SHA-256 salato di contesto + IP) e `no_cache_page()`. L'iscrizione la riusa;
  `DBEM_Registration::origin_matches()` resta come alias deprecato
- Questionario post-evento (`dbem_submit_survey`): prima il nonce stampato nella pagina del link
  personale poteva scadere in cache e bloccare l'invio. Ora anonimi → origine + rate limit
  (`dbem_survey_rate_limit`, default 5/minuto); l'autorizzazione resta il token personale
- Pagine pubbliche check-in e partecipanti (`dbem_public_*`): anonimi → origine + rate limit
  (`dbem_public_rate_limit`, default 120/minuto per IP), poi il **PIN** con blocco dopo 10 tentativi
  errati, invariato. `DBEM_Security::public_nonce()` restituisce il nonce solo agli utenti loggati
- Pagine per-visitatore o legate a un token (check-in, partecipanti, questionario, conferma modifica
  iscrizione, approvazione da email): header no-cache + `DONOTCACHEPAGE`
- JS delle pagine check-in, partecipanti e questionario: `console.warn` su risposta non 2xx e messaggio
  del server a video (prima un errore di verifica PIN o una risposta non JSON restava silenzioso, e
  la ricerca check-in mostrava «Nessun risultato» anche in caso di errore)

**Registro trattamenti (`dbem_registrations`):**
- `data_collected` riscritto: prima era copiato dalla voce email (citava solo nome, email e il
  mittente). Ora elenca nome, email, campi personalizzati del form (anche DB Form Builder), indirizzo
  IP **salvato in chiaro** (`REMOTE_ADDR`, non anonimizzato), data/ora e stato dell'iscrizione, token
  del QR code, data/ora del check-in, orario assegnato (solo se almeno un evento ha l'assegnazione
  orario) e prova del consenso GDPR (solo se almeno un evento ha la checkbox attiva)

**Registro consensi (`dbph_consents_register`):**
- `hub_query_consents` legge il limite dalla chiave pubblica `limit` passata dal Privacy Hub (fallback
  `_internal_limit`, default 1000, massimo 50000). Prima ignorava `limit` e restituiva sempre fino a 1000 righe
- Ricerca per soggetto: `%` e `_` nel testo cercato sono ora trattati come caratteri letterali (`$wpdb->esc_like`)

**DSAR:**
- L'export delle iscrizioni include `gdpr_consent_policy_version` («Versione informativa privacy», v#N
  dello snapshot Privacy Hub, oppure «Non registrata» se l'Hub era assente)

**Nessuna migrazione di schema, nessun breaking change** per gli admin: form, email e dati salvati invariati.

### 1.7.0
**Novità**
- Colori personalizzabili: sfondo, pulsanti e testo, globali e per evento, con anteprima e avviso di contrasto nell'admin. I colori derivati (testo dei pulsanti, hover, link) vengono calcolati per restare WCAG AA. Gli eventi esistenti non cambiano aspetto, salvo due correzioni di contrasto: il rosso dei messaggi di errore e del badge «Posti esauriti» e il grigio del badge «Concluso» sono più scuri, perché prima erano sotto il minimo AA
- I campi del form di iscrizione hanno sempre testo scuro su sfondo bianco: prima prendevano il colore del testo del tema, illeggibile con i temi scuri
- Segnaposto cliccabili negli editor delle email di conferma, del sondaggio e del promemoria, con la descrizione di ciascuno: prima erano un elenco da copiare a mano
- Segnaposto dei singoli campi del form nelle email (`{campo:id}`), con un pulsante per campo sotto gli editor che si aggiorna mentre si modifica il form
- Ogni campo del form integrato ha un id stabile. Rinominando l'etichetta di un campo, le iscrizioni già raccolte vengono aggiornate: prima i dati inseriti con l'etichetta vecchia sparivano da export, filtri e reminder. Anche il riconoscimento delle opzioni rinominate segue il campo quando cambia l'etichetta
- Mittente delle email configurabile (nome e indirizzo). Prima era sempre l'email dell'amministratore, che spesso non appartiene al dominio del sito e fa finire le email nello spam
- Tutti i testi degli script (admin, check-in, blocchi Gutenberg, pagine pubbliche di check-in, partecipanti e sondaggio) sono traducibili; i blocchi usano `wp.i18n`
- Pagina Check-in dell'admin: l'elenco dei partecipanti e i contatori ora si caricano. Prima restavano «Seleziona un evento» e 0 / 0
- Stesso testo prima e dopo le azioni: i pulsanti «Invia survey» e «Scansiona QR Code» e il contatore della pagina partecipanti non cambiano più scritta dopo il primo uso

**Correzioni di sicurezza e di comportamento**
- Link Approva/Rifiuta nelle email: prima approvavano o rifiutavano all'apertura, quindi anche i filtri antivirus o le anteprime dei link potevano decidere al posto del responsabile. Ora aprono una pagina di conferma e la decisione parte dal pulsante
- Reiscrizione con la stessa email: prima chi conosceva l'email di un iscritto poteva sostituirne nome, dati e consenso. Ora i nuovi dati arrivano come link di conferma all'indirizzo già iscritto (valido 24 ore, una sola volta) e l'iscrizione cambia solo dopo il clic
- Un iscritto rifiutato non può più reiscriversi: prima la reiscrizione faceva ripartire la richiesta di approvazione
- Check-in: prima la scansione del QR di un'iscrizione in attesa o rifiutata non dava alcuna risposta e lo scanner restava bloccato. Ora compare «check-in non consentito». Anche il pulsante «Segna presente» della pagina pubblica accetta solo iscrizioni confermate
- La ricerca della pagina pubblica di check-in non restituisce più i token delle iscrizioni, che valgono anche come QR code e link al sondaggio
- Iscrizioni dai form DB Form Builder: aggiunto il limite di 5 invii al minuto per IP già presente nel form integrato. Prima l'endpoint si poteva chiamare direttamente senza alcun limite
- I campi personalizzati chiamati «nome» o «email» non sovrascrivono più nome ed email dell'iscritto nei dati salvati
- Eliminare un'iscrizione cancella anche le risposte al sondaggio e il QR code. Eliminare definitivamente un evento cancella iscrizioni, risposte, QR code, reminder e sondaggio programmati. Prima questi dati restavano orfani
- Il sondaggio segnala l'errore se le risposte non vengono salvate: prima rispondeva comunque «Grazie»
- «Invia survey a tutti» esclude le iscrizioni in attesa e rifiutate
- Azzerando le ore del sondaggio automatico, l'invio già programmato viene annullato
- Il controllo orario degli eventi viene riprogrammato anche dopo un aggiornamento, non solo all'attivazione
- Disattivazione e disinstallazione rimuovono anche reminder e sondaggi programmati per singolo evento
- Disinstallazione: pulisce tutti i siti di un multisite, le categorie evento e funziona anche da WP-CLI
- Email: segnaposto sostituiti in un solo passaggio (un nome come «{token}» non viene più espanso), oggetto sempre su una riga, mittente con il nome del sito ripulito da virgolette ed entità HTML
- Apostrofi e virgolette nei dati inviati: prima venivano salvati con una barra davanti (es. «D\\'Angelo» nel nome dell'iscritto o nel titolo della pagina eventi). Ora vengono salvati come scritti. I dati già salvati non vengono modificati
- Tutti i testi fissi dell'interfaccia passano dall'escape HTML
- Le release allegano uno ZIP con la cartella `db-event-manager/`: prima l'aggiornamento scaricava lo zipball di GitHub, con una cartella dal nome diverso
- CI: controlli di sicurezza PHPCS anche sui template, controllo della sintassi JavaScript (script inline compresi), `php -l` con PHP 7.4 e 8.3, verifica della versione al rilascio

### 1.6.4
- Testo del reminder modificabile per evento dall'anteprima, con aggiornamento in tempo reale, salvataggio e ripristino del testo predefinito; il testo salvato vale anche per il reminder automatico
- Segnaposto del reminder: {nome}, {email}, {evento}, {periodo}, {data_evento}, {luogo}, {orario}, {scelte}, {attivita}, {dettagli}, {sito}
- Quando si modifica il testo di un'opzione nel form dell'evento, le iscrizioni esistenti vengono aggiornate con il nuovo testo, così reminder, filtri ed export restano allineati
- Avviso dopo il salvataggio con le opzioni aggiornate e il numero di iscrizioni coinvolte, anche nell'editor a blocchi
- Le opzioni aggiunte, rimosse o spostate non modificano le iscrizioni; i casi ambigui vengono lasciati invariati

### 1.6.3
- Con la reiscrizione attiva, se l'email è già iscritta il form chiede «Vuoi sostituire la prenotazione precedente con quella di adesso?» prima di sostituirla
- Due risposte possibili: «Sì, sostituisci» aggiorna la prenotazione mantenendo QR code e stato, «No, mantieni la precedente» lascia tutto invariato
- Conferma disponibile sia nel form interno sia nei form DB Form Builder

### 1.6.2
- Corretto il pulsante "Invia reminder a tutti", che nella 1.6.0 non inviava alcuna email
- Anteprima del reminder manuale prima dell'invio, partecipante per partecipante, con oggetto, allegato e contenuto
- Nuova opzione per evento "Contenuto del promemoria": data e sede dell'evento oppure solo le opzioni scelte dal partecipante (campi Selezione, Scelta singola e Scelta multipla), utile quando ogni opzione indica già giorno e orario
- Il reminder riporta l'orario assegnato all'approvazione
- Con l'assegnazione orario attiva, il periodo generale del reminder mostra solo la data
- I campi Data del form compaiono nel reminder in formato gg/mm/aaaa

### 1.6.1
- Le capability dei gestori vengono assegnate all'amministratore all'attivazione e solo quando cambiano, non più a ogni caricamento
- I gestori senza permesso di caricare file ricevono `upload_files` per l'immagine in evidenza, ritirato alla disattivazione solo se concesso dal plugin
- La disinstallazione rimuove le capability da ruoli e utenti
- Test PHPUnit sulle capability dei gestori; configurazione PHPUnit migrata al nuovo schema

### 1.6.0
- Gestori eventi assegnabili ai singoli utenti con capability dedicate, senza accesso alle impostazioni globali
- Gestione completa di eventi, categorie, partecipanti, check-in, survey, reminder ed esportazioni per gli utenti autorizzati
- Reminder inviabili a tutti i partecipanti validi oppure solo a quelli visualizzati dopo il filtro
- Export CSV disponibile per tutti i partecipanti oppure solo per quelli visualizzati dopo il filtro

### 1.5.0
- Opzione per evento per consentire la reiscrizione con la stessa email: la nuova richiesta sostituisce i dati dell'iscrizione esistente e mantiene QR code e stato
- Aggiornamento consentito anche quando le nuove iscrizioni sono chiuse o i posti sono esauriti, senza creare un secondo partecipante
- Pulsante admin per inviare il reminder a tutti i partecipanti confermati o già presenti dell'evento selezionato
- Elenco partecipanti più leggibile: mostra le attività selezionate e permette di filtrare per le opzioni del form

### 1.4.1
- Aggiunta base di test automatica con PHPUnit
- Aggiunto workflow GitHub Actions per esecuzione automatica di test su push/PR
- Aggiunta configurazione git per ignorare vendor e cache di test
- Verificata la stabilità del flusso di sicurezza, registrazione, reminder e approvazione

### 1.4.0
**Elenco eventi**
- Gli eventi su più giorni mostrano un intervallo nel riquadro data (`21-25 SET`) invece del solo giorno di inizio, la riga di dettaglio riporta l'intervallo completo e compare il badge "Più date"
- Nuova opzione per evento "Non mostrare il giorno nel riquadro della card": il riquadro riporta solo mese e anno

**Sicurezza**
- Le pagine pubbliche di check-in e partecipanti richiedono ora obbligatoriamente un PIN. Prima il PIN era opzionale e vuoto di default: chiunque conoscesse l'indirizzo poteva leggere le anagrafiche complete degli iscritti, approvare o annullare iscrizioni e inviare email dal sito. Il PIN viene generato automaticamente all'aggiornamento e si legge in Impostazioni
- Nuova classe `DBEM_Security`: confronto del PIN in tempo costante (`hash_equals`), rate limit di 10 tentativi per IP ogni 15 minuti, verifica nonce su tutte le chiamate AJAX pubbliche (protezione CSRF)
- Corretto un XSS memorizzato nella pagina pubblica partecipanti: il nome di un iscritto poteva iniettare attributi HTML nella tabella dell'organizzatore
- Le azioni sui partecipanti sono vincolate all'evento selezionato
- Export CSV: neutralizzate le formule (`=`, `+`, `-`, `@`) interpretate da Excel

**Dati e consenso**
- Corretto l'array di format di `wpdb->insert`: aveva 8 specificatori per 13 colonne, quindi l'indirizzo IP veniva salvato come `0` a ogni iscrizione e, con checkbox GDPR attiva, testo/timestamp/URL del consenso venivano azzerati (su MySQL in strict mode l'iscrizione falliva del tutto mostrando comunque un messaggio di successo)
- Corretto uno statement troncato che rendeva inefficace il controllo di errore sull'inserimento
- Il path DB Form Builder non registra più un consenso non verificato: nuovo campo "Campo Privacy" nella mappatura del form, la prova viene salvata solo a fronte di una checkbox effettivamente spuntata
- Nuovo `uninstall.php` con opzione "Elimina tutti i dati alla disinstallazione" (disattivata di default)

**Correzioni**
- Il promemoria evento ora funziona: nuovo campo "Promemoria evento" (N ore prima dell'inizio). L'hook cron esisteva ma non veniva mai pianificato
- L'azione in blocco "Conferma" non reinvia più l'email a chi era già confermato
- Corretto il markup del metabox Iscrizioni: un `</table>` fuori posto lasciava la sezione Privacy/GDPR e "Assegnazione orario" senza layout
- Capability corretta nel salvataggio evento (`edit_post` al posto di `manage_options`)
- Rimossa la registrazione AJAX `dbem_save_event_meta`, il cui callback non è mai esistito
- Aggiunto `load_plugin_textdomain` e cartella `languages/`: le stringhe sono ora traducibili
- Lo shortcode `[dbem_event]` non applica più `the_content` in modo annidato

### 1.3.0
- Integrazione ecosistema privacy DB: dichiarazioni trattamenti al Privacy Hub, DSAR export/erase con dual channel (Hub + WP core), consent storage con 5 colonne GDPR
- Checkbox GDPR configurabile per evento: on/off, testo personalizzabile, link privacy policy (fallback a pagina Privacy WP)
- Link "Leggi l'informativa privacy" cliccabile nel form di iscrizione
- Checkbox GDPR condizionale: visibile e validata solo se attivata per l'evento
- Fix variabili consent mancanti nel metodo iscrizione via DB Form Builder
- Dichiarazione email privacy con mittente configurato e info trasporto SMTP
- Costante `DBEM_DSAR_AVAILABLE` per integrazione con Privacy Hub policy generator

### 1.2.0
- Pagina pubblica partecipanti: iscrizione manuale (nome + email + orario opzionale) con generazione QR e invio email automatico
- Pagina pubblica partecipanti: modifica orario assegnato con modal dedicato (bottone 🕐)
- Pagina pubblica partecipanti: export CSV lato client con filtro attivo
- Fix sfarfallio hover sulla tabella partecipanti
- Bottoni azioni a dimensione fissa per stabilità layout

### 1.1.0
- Approvazione con assegnazione orario
- Form orario inline nel link di approvazione email
- Nuovo placeholder email `{orario}`
- Se assegnazione orario è attiva, la data evento mostra solo il giorno (senza ora)
- Colonna "Orario assegnato" nella tabella partecipanti admin e nel CSV export
- Libreria phpqrcode refactorizzata con namespace isolato (`DBEM_QRcode_Lib`) per evitare conflitti
- Generazione QR via file temporaneo per compatibilità con ambienti restrittivi
- Fix timezone: le date vengono visualizzate in ora locale senza doppia conversione
- Nuove costanti protette con `if (!defined(...))` per convivenza con altri plugin QR
- Stati "In attesa" e "Rifiutato" aggiunti ai label export CSV

### 1.0.0
- Release iniziale
- Gestione eventi con CPT, categorie, meta
- Form iscrizione integrato + integrazione DB Form Builder
- Modalità accettazione automatica e con approvazione
- Approvazione via email con bottoni Approva/Rifiuta (link HMAC)
- QR code con phpqrcode (libreria PHP pura affidabile)
- Check-in con scanner QR — pagina pubblica con PIN
- Ricerca partecipanti cross-evento
- Email: conferma, pending, approvazione, rifiuto, promemoria, survey, annullamento
- Survey post-evento con link univoco
- Gestione partecipanti con 5 stati, tooltip, azioni bulk, export CSV
- Shortcode con parametri: past, limit, cols, category
- Blocchi Gutenberg
- Aggiornamenti automatici da GitHub
- Design system admin condiviso (db-admin-ui.css)
- Accessibilità WCAG 2.1 AA

---

## Licenza

GPL v2 or later.  
Sei libero di utilizzare, modificare e distribuire questo plugin.

---

## Autore

**Davide Bertolino**  
🌐 [davidebertolino.it](https://www.davidebertolino.it)
