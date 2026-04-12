# DB Event Manager

Gestione eventi con iscrizione, QR code personale, check-in e survey post-evento.  
Niente Eventbrite, niente SaaS, niente abbonamenti. Tutto nel tuo WordPress.

**Versione:** 1.0.0  
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

### ✅ Approvazione iscrizioni
- Modalità configurabile per evento: automatica o con approvazione
- Email approvatore personalizzabile (può essere diverso dal creatore evento)
- L'approvatore riceve email con bottoni ✅ Approva e ❌ Rifiuta — un clic, niente login
- Link protetti con HMAC (non indovinabili, non riusabili)
- Approvazione → genera QR code → invia email conferma all'iscritto
- Rifiuto → invia email notifica all'iscritto
- Gestibile anche dalla pagina Partecipanti admin (singola e bulk)

### 📱 QR Code personale
- QR code generato con phpqrcode (libreria PHP pura, affidabile, zero dipendenze)
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

### 📧 Email automatiche
- Conferma iscrizione con QR code nel corpo + allegato
- Notifica "in attesa di approvazione" (per modalità con approvazione)
- Richiesta approvazione all'approvatore con bottoni Approva/Rifiuta
- Notifica rifiuto iscrizione
- Promemoria evento (programmabile via WP Cron)
- Survey post-evento (manuale o automatico)
- Email annullamento
- Notifica admin personalizzabile per evento (anche più destinatari)
- Placeholder dinamici: {nome}, {email}, {evento}, {data_evento}, {luogo}, {riepilogo_dati}, {qrcode_url}, {token}, {sito}, {survey_link}
- Compatibile con qualsiasi plugin SMTP

### 📋 Survey post-evento
- Campi survey configurabili per evento
- Link univoco per partecipante (no login richiesto)
- Invio a tutti o solo ai presenti (checked-in)
- Riepilogo risposte nell'admin con conteggi
- Export CSV

### 👥 Gestione partecipanti
- Tabella con nome, email, stato, check-in
- Stati: 🕐 In attesa, ⏳ Confermato, ✅ Presente, ❌ Annullato, 🚫 Rifiutato
- Azioni con tooltip: approva, rifiuta, conferma, annulla, segna presente, reinvia email, elimina
- Azioni bulk (conferma, annulla, rifiuta, segna presente, elimina)
- Export CSV con tutti i dati

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
- PIN check-in per proteggere la pagina pubblica
- Link check-in da condividere con lo staff
- Riepilogo shortcode disponibili

---

## Installazione

1. Scarica lo ZIP da [GitHub Releases](https://github.com/dadebertolino/db-event-manager/releases)
2. WordPress Admin → Plugin → Aggiungi nuovo → Carica plugin → Seleziona ZIP
3. Attiva il plugin
4. Vai in **Impostazioni → Permalink → Salva modifiche**
5. Nel menu admin compare **Event Manager** con icona calendario

### Primo utilizzo

1. **Event Manager → Aggiungi Evento** — compila nome, date, luogo
2. Scrivi la descrizione nell'editor Gutenberg
3. Configura il form iscrizione (integrato o DB Form Builder)
4. Scegli la modalità: accettazione automatica o con approvazione
5. Personalizza l'email di conferma
6. Pubblica — il link è nella sidebar

### Check-in all'ingresso

1. **Event Manager → Impostazioni** — imposta un PIN
2. Condividi il link check-in con lo staff
3. All'ingresso: link sul telefono → PIN → scansiona QR
4. Se qualcuno non ha il QR: cerca per nome nella barra di ricerca

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
│       └── phpqrcode.php        # Libreria QR code PHP pura (LGPL 3)
└── templates/
    ├── single-dbem_event.php
    ├── archive-dbem_event.php
    ├── admin/
    │   ├── checkin.php
    │   ├── participants.php
    │   └── survey.php
    └── frontend/
        ├── checkin.php
        └── survey.php
```

---

## Note tecniche

- **QR code**: phpqrcode (LGPL 3), libreria PHP pura provata, zero dipendenze
- **Scanner QR**: html5-qrcode inclusa localmente (375KB)
- **Drag & drop**: SortableJS inclusa localmente (45KB)
- **Sicurezza**: nonce, capability check, sanitizzazione, rate limiting, PIN check-in, HMAC per link approvazione
- **Token**: bin2hex(random_bytes(32)) — 64 caratteri hex
- **Email**: HTML responsive, compatibile con plugin SMTP
- **Auto-updater**: controlla GitHub Releases ogni 12h
- **Integrazione DBFB**: rileva se DB Form Builder è attivo, nessuna dipendenza hard
- **Template**: sovrascrivibili dal tema

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

GPL v2 or later. Sei libero di utilizzare, modificare e distribuire questo plugin.

---

## Autore

**Davide Bertolino**  
🌐 [davidebertolino.it](https://www.davidebertolino.it)  
📧 info@davidebertolino.it
