<details>
<summary><h2>Problema</h2></summary>

L'applicazione gestisce le spedizioni di merci tra due città per i vari clienti (team).

Ogni spedizione ha dei referenti per il punto di partenza (start) e il punto di arrivo (end).

Allo stato attuale quando un referente viene aggiunto tramite il pannello di amminstrazione è salvato come nuovo elemento e collegato alla spedizione.

Questo ha portato la tabella ad avere molti record duplicati.

Come possiamo risolvere questo problema al fine di migliorare la nostra applicazione e l'esperienza utente?

</details>

# Soluzione

### Backend

È stato creato il comando Artisan `referents:normalize`, pensato per normalizzare i referenti duplicati presenti nel database.

Il comando identifica i referenti duplicati utilizzando la coppia (email, team_id) come chiave logica e:
- mantiene un solo record per ogni coppia, conservando quello con ID più alto (considerato il più recente)
- aggiorna tutte le relazioni esistenti (referent_shipment) affinché puntino al record mantenuto
- rimuove in modo sicuro i referenti duplicati non più necessari
- garantisce l’integrità dei dati evitando riferimenti orfani

L’operazione è supportata da un backup delle tabelle `referents` e `referent_shipment` che all'evenienza possono essere ripristinate tramite il comando artisan `referents:rollback`.

È stato inoltre implementato un test che verifica il corretto funzionamento del comando sia su un set minimo di elementi sia sui dati generati dal seeder.

### Frontend
Il form dei referenti è stato aggiornato per prevenire la creazione di duplicati: prima di creare un nuovo referente viene verificata l’esistenza di uno con la stessa (email, team_id), che viene riutilizzato e aggiornato se presente.
Nel caso in cui si tenti di associare un referente già esistente allo stesso shipment, il sistema restituisce un errore mostrato tramite il componente `AlertError`.

Con questa modifica è anche stato fixato un bug nel `ShipmentController` per cui il parametro scope non veniva utilizzato.

## Considerazioni sull'implementazione
Inizialmente ero partito con l'idea di sfruttare la funzione `chunk` del query builder di laravel per scorrere gli id "da mantenere" e conseguentemente sostituire quelli vecchi.
Mi sono però subito reso conto che con un dataset come quello fornito dal seeder (circa ~12k righe) avrei comunque eseguito all'incirca 1500 query.
Nonostante questo carico sia tranquillamente sostenibile, sarebbe comunque stato relativamente lungo e quindi uno spreco di risorse.

Ho quindi optato per una soluzione più "a basso livello", svolgendo tutte le operazioni direttamente nel database.
Viene creata una _temporary table_ popolata con la mappatura degli id dei referenti, grazie alla quale il resto delle operazioni si semplifica notevolmente andando in join su di questa.

<details>
<summary><h2>Setup ambiente</h2></summary>
1) Scarica il repository in locale

```
git clone https://github.com/karrycar/devtest.git

cd devtest
```

2) Rinomina il file .env.example

```
mv .env.example .env
```

3) Esegui i seguenti comandi

```
composer install

sail up -d

sail artisan key:generate

sail art migrate:fresh --seed

npm install

npm run dev
```

## Accesso al pannello
http://localhost:3000/login

user: admin@karrycar.com

pass: password

</details>

### Avvio comando di normalizzazione

Esegui il comando `sail artisan referents:normalize`

### Ripristino backup creato dalla normalizzazione

Esegui il comando `sail artisan referents:rollback`

### Avvio test suite

Esegui il comando `sail pest`

Per eseguire nello specifico il test del comando normalize:
`sail pest tests/Feature/ReferentsNormalizeTest.php`

**Nota**: il test impiega ~35s in quanto al suo interno esegue anche il seeder.
