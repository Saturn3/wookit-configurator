# WooKit Configurator – Kit regalo configurabili

Plugin WordPress/WooCommerce per creare **kit di prodotti configurabili** (es. *Christmas Gift Set* di nefel.it) con:

- una **confezione sempre inclusa** (prodotto WooCommerce, es. scatola natalizia);
- **N prodotti scelti dal cliente** (predefinito: 2), prima per **linea** (categoria) e poi per **fragranza**;
- una **landing page** con la seguente struttura: hero → step di scelta → riepilogo "Il tuo GIFT SET" → sezione finale;
- testi, immagini e colori modificabili da pannello.

Nel selettore il cliente **non vede nome e foto del prodotto**: vede l'**anteprima** e il **nome della fragranza**, letti da due custom field del prodotto. Nel riepilogo e nel carrello compare invece il prodotto reale (es. "Sicilian Bergamot Hand Soap").

---

## Installazione

1. WordPress → **Plugin → Aggiungi nuovo → Carica plugin** → `wookit-configurator.zip` → **Attiva**.
2. Compare il menu **Kit regalo** nella barra laterale.
3. Vai su **Impostazioni → Permalink** e premi **Salva** una volta (rigenera gli URL `/kit/...`).

Requisiti: WordPress 6.2+, WooCommerce 7.0+, PHP 7.4+. Testato su WordPress 7.1.3 e WooCommerce 11.2.1.

## Configurazione per nefel.it

### 1. Campi personalizzati della fragranza
**Kit regalo → Impostazioni**

| Campo | Default | Note |
|---|---|---|
| Meta key – Nome fragranza | `nome_fragranza` | es. "Sicilian Bergamot" |
| Meta key – Anteprima fragranza | `anteprima_fragranza` | ID allegato, URL o campo immagine ACF |
| Meta key – Note olfattive (facoltativo) | `note_fragranza` | es. "Agrumata - Luminosa" |

Il campo propone in automatico le meta key già presenti sui prodotti: scegli quelle usate su nefel.it. Con l'opzione "Escludi prodotti senza nome fragranza" (attiva di default) i prodotti senza fragranza non compaiono nel selettore.

### 2. Prodotto confezione
Crea un prodotto semplice, es. **"Christmas Gift Box"** a 1,50 €, con l'immagine della scatola. Consigliato: *Visibilità catalogo = Nascosto*. Con l'opzione "Confezione vendibile solo nel kit" (attiva) il prodotto non si può acquistare da solo.

### 3. Crea il kit
**Kit regalo → Nuovo kit**, titolo "Christmas Gift Set".

- **Composizione del kit**
  - *Confezione*: cerca e seleziona il prodotto confezione.
  - *Prodotti da scegliere (step)*: 2 righe precompilate ("1. Scegli il primo prodotto", …). Puoi aggiungerne o toglierne.
  - *Linee prodotto*: aggiungi le categorie **Hand Soap** e **Body Wash**. Titolo, sottotitolo e immagine sono facoltativi: se li lasci vuoti si usano nome, descrizione e immagine della categoria WooCommerce.
  - Opzioni: stesso prodotto in più step, preselezione (nessuna / prima linea / prima fragranza), comportamento dopo "Aggiungi al carrello" (resta in pagina, carrello o checkout).
- **Testi e immagini** (schede Hero, Configuratore, Riepilogo, Sezione finale): tutti i testi del PDF sono precompilati e modificabili; le immagini si scelgono dalla Libreria media.
- **Colori**: sfondo delle sezioni (bordeaux `#80161b` preso dal PDF), testo, bordo della selezione, pulsante.

Pubblica il kit. La landing è subito disponibile su `https://<dominio>/kit/christmas-gift-set/`.

### 4. In alternativa: Elementor
Se preferisci una pagina Elementor con header e footer del tema Cosmetsy, inserisci un widget **Shortcode** con:

```
[wookit_kit id="123"]
```

(l'ID e lo shortcode sono indicati nel riquadro "Landing page" del kit). Con "Sezioni a tutta larghezza" attivo, hero e sezione finale si estendono a tutto schermo anche dentro i contenitori di Elementor.

## Come funziona il carrello

Il kit viene aggiunto come **righe collegate**:

```
Christmas Gift Box        1,50 €   Contenuto: Sicilian Bergamot Hand Soap, Turkish Rose Body Wash
  ↳ Sicilian Bergamot Hand Soap  9,50 €   Parte di: Christmas Gift Set
  ↳ Turkish Rose Body Wash      10,50 €   Parte di: Christmas Gift Set
```

- I prezzi, l'IVA e le **giacenze restano quelle dei singoli prodotti** (il magazzino scala correttamente).
- La quantità si cambia solo sulla confezione: i prodotti del kit la seguono automaticamente.
- I prodotti del kit non hanno il pulsante di rimozione: rimuovendo la confezione si rimuove l'intero kit, e "Annulla" lo ripristina tutto.
- Se un kit nel carrello risulta incompleto (es. prodotto esaurito o eliminato), viene rimosso con un avviso.
- Nell'ordine ogni riga riporta "Kit / Contenuto / Parte di" e metadati nascosti (`_wkc_group`, `_wkc_kit_id`, `_wkc_role`) per eventuali export o integrazioni gestionali. "Ordina di nuovo" ricostruisce il kit.
- Lato server, ogni richiesta viene ricontrollata: categoria ammessa, prodotto pubblicato e disponibile, numero di prodotti corretto, eventuali duplicati.

## Multilingua (WPML)

nefel.it usa WPML e WooCommerce Multilingual: il file `wpml-config.xml` incluso rende il kit traducibile. Testi e titoli degli step si traducono, mentre confezione, categorie, immagini e colori vengono copiati e convertiti in automatico negli ID della lingua corrente. Le stringhe fisse del plugin si traducono con `languages/wookit-configurator.pot`.

## Cache

L'aggiunta al carrello usa l'endpoint `?wc-ajax=wkc_add_kit`, che non passa dalla cache, quindi la landing può restare in cache. Prezzi e disponibilità mostrati nella pagina sono quelli del momento in cui è stata messa in cache: dopo una modifica a un prodotto, svuota la cache della landing (il server rifiuta comunque i prodotti non più disponibili).

## Personalizzazione per sviluppatori

- Template sovrascrivibili nel tema child: `cosmetsy-child/wookit/landing.php` e `cosmetsy-child/wookit/single-kit.php`.
- Filtri: `wkc_kit_lines_data` (linee e prodotti), `wkc_validate_kit` (validazione extra), `wkc_template_vars`.
- Azione: `wkc_kit_added_to_cart( $group, $kit_id, $products, $box_id )`.
- CSS: tutte le classi hanno prefisso `.wkc-`; i colori sono variabili CSS (`--wkc-accent`, `--wkc-btn`, …).

## Limiti noti

- Sono selezionabili i **prodotti semplici**; i prodotti variabili/variazioni sono esclusi.
- Il prezzo del kit è la somma di confezione e prodotti: non sono previsti sconti dedicati al kit.
- Va verificato su staging l'aggiornamento del mini-carrello laterale del tema Cosmetsy: il plugin emette gli eventi standard WooCommerce (`added_to_cart`, `wc_fragment_refresh`).
