=== WP Weekly Calendar (Attività = Categorie) ===
Contributors: your-name
Tags: calendar, weekly, eventi, cpt, acf
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later

Il calendario settimanale in cui le categorie NON sono tassonomie ma i post del CPT 'attivita'. Ogni 'attività' equivale a una categoria del calendario con colore ACF e link /attivita/slug.

== Descrizione ==
- Ogni post del CPT `attivita` è una categoria utilizzabile per gli eventi.
- Il colore è letto dal campo ACF (name: `colore`).
- Gli eventi (`wpwc_event`) hanno: giorno settimana (1-7), ora inizio/fine, attività collegata.
- Shortcode: `[weekly_calendar]`.

== Installazione ==
1. Copia questa cartella in `wp-content/plugins/`
2. Attiva il plugin.
3. Assicurati di avere il CPT `attivita` con campo ACF `colore`.
4. Crea post `attivita` e poi crea eventi associandoli.

== Shortcode ==
[weekly_calendar]

== REST ==
- GET /wp-json/wpwc/v1/attivita
- GET /wp-json/wpwc/v1/events?day=1&attivita=123

== Note ==
Le "categorie" del calendario sono i post `attivita`. I link puntano a /attivita/slug.
