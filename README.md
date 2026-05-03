# DB Event Manager

Gestione eventi con iscrizione, QR code personale, check-in e survey post-evento.  
Niente Eventbrite, niente SaaS, niente abbonamenti. Tutto nel tuo WordPress.

**Versione:** 1.3.0  
**Autore:** [Davide Bertolino](https://www.davidebertolino.it)  
**Licenza:** GPL v2 or later  
**Richiede:** WordPress 5.8+, PHP 7.4+  
**GitHub:** [dadebertolino/db-event-manager](https://github.com/dadebertolino/db-event-manager)

---

## Cosa fa

### 📅 Gestione eventi
- Crea eventi con nome, descrizione (editor Gutenberg), data inizio/fine, luogo, posti disponibili
- Categorie evento gerarchiche per organizzare e filtrare
- Chiusura automatica iscrizioni (posti esauriti o deadline)
- Stato evento automatico: bozza, in programma, in corso, concluso
- Pagina singola evento e archivio generati automaticamente dal plugin

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
- L'approvatore riceve email con bottoni ✅ Approva e ❌ Rifiuta — un clic, niente login
- Se l'assegnazione orario è attiva, cliccando "Approva" si apre un form con campo orario, riepilogo iscrizione e bottoni Approva/Rifiuta
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
- Protetta da PIN configurabile
- Scanner QR integrato (fotocamera smartphone)
- Ricerca manuale per nome/email su tutti gli eventi
- Feedback visivo grande e chiaro: ✅ Presente, ⚠️ Già registrato, ❌ Non valido
- Dopo check-in riuscito, lo scanner si riapre automaticamente
- Funziona anche dalla pagina admin Event Manager → Check-in

### 👥 Pagina pubblica partecipanti
- **Pagina pubblica** accessibile da telefono senza login WordPress
- Protetta dallo stesso PIN del check-in
- Selettore evento, contatore presenti/iscritti, filtri per stato
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
- Promemoria evento (programmabile via WP Cron)
- Survey post-evento (manuale o automatico)
- Email annullamento
- Notifica admin personalizzabile per evento (anche più destinatari)
- Placeholder dinamici: {nome}, {email}, {evento}, {data_evento}, {luogo}, {orario}, {riepilogo_dati}, {qrcode_url}, {token}, {sito}, {survey_link}
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
- PIN check-in per proteggere le pagine pubbliche (check-in e partecipanti)
- Link check-in e partecipanti da condividere con lo staff
- Riepilogo shortcode disponibili

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

### Check-in all'ingresso

1. **Event Manager → Impostazioni** — imposta un PIN
2. Condividi il link check-in con lo staff
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
│   └── lib/
│       └── phpqrcode.php        # QR code PHP pura (LGPL 3), classi prefissate DBEM_
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
- **Sicurezza**: nonce, capability check, sanitizzazione, rate limiting, PIN check-in/partecipanti, HMAC per link approvazione
- **Token**: bin2hex(random_bytes(32)) — 64 caratteri hex
- **Email**: HTML responsive, compatibile con plugin SMTP
- **Auto-updater**: controlla GitHub Releases ogni 12h
- **Integrazione DBFB**: rileva se DB Form Builder è attivo, nessuna dipendenza hard
- **Template**: sovrascrivibili dal tema
- **Timezone**: le date degli eventi sono salvate in ora locale e visualizzate senza conversione timezone

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
