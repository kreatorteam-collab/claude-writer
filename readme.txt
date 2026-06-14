=== Claude Writer ===
Contributors: Eduard / TargetSEO
Tags: ai, claude, anthropic, content, seo, articole
Requires at least: 5.6
Requires PHP: 7.4
Stable tag: 1.2.2
License: GPLv2 or later

Generează și rescrie articole SEO direct în editor, cu alegere între cele 3 modele Claude.

== Descriere ==

Modul de scriere AI conectat DIRECT la API-ul Anthropic (fără proxy intermediar).
Alegi modelul per generare, chiar din pagina de editare a articolului:

* Haiku 4.5  — ieftin & rapid  (1$/5$ per 1M tokeni)
* Sonnet 4.6 — echilibrat       (3$/15$)
* Opus 4.8   — calitate maximă  (5$/25$)

Funcții:
* Generează articol complet (HTML curat, lungime configurabilă)
* Rescrie conținutul existent din editor
* Generează titlu SEO
* Extrage cuvinte cheie SEO
* Streaming opțional (text afișat în timp real)
* Calcul de cost real per model + limită lunară de cheltuieli
* Stil de scriere configurabil (system prompt cu diacritice RO obligatorii, anti-clișee AI)
* Cheie API criptată AES-256-GCM în baza de date (cheia de criptare din wp-config.php)
* Funcționează în Editorul Clasic și în Gutenberg

== Instalare ==

1. Urcă folderul `claude-writer` în `/wp-content/plugins/` (sau încarcă ZIP-ul din Plugins → Add New).
2. Activează plugin-ul.
3. Mergi la Setări → Claude Writer, pune cheia API Anthropic și apasă „Testează cheia".
4. Deschide un articol: panoul „Claude Writer" apare în coloana din dreapta.

== Changelog ==

= 1.2.2 =
* Model implicit schimbat pe Haiku 4.5 (mult mai rapid și mai ieftin la generare; problemele de formatare ale lui sunt acum prinse în cod). Comutare unică de pe Sonnet pe Haiku (Opus rămâne; reversibil din Setări → Model implicit).

= 1.2.1 =
* Auto-update activat implicit: site-urile instalează singure versiunile noi (WordPress verifică de ~2 ori/zi), fără click pe fiecare site. Cache de verificare redus la 1h. Se poate opri din Setări → „Auto-update".

= 1.2.0 =
* Verificare auto-update (fără schimbări funcționale): confirmă că update-ul din 1.1.9 se instalează corect prin buton.

= 1.1.9 =
* Nota de final (disclaimer) garantată în FIECARE articol, ca blockquote: dacă modelul nu o include, pluginul o adaugă automat; dacă o pune ca text simplu, o împachetează în blockquote. Text configurabil în Setări → „Notă de final".

= 1.1.8 =
* Fix critic auto-update: „Pachetul nu a putut fi instalat". Folderul redenumit din arhiva GitHub se întorcea fără slash final, iar verificarea pachetului din WordPress eșua. Acum update-ul se instalează corect. (Necesită o ultimă instalare manuală, fiindcă versiunile vechi conțineau bug-ul.)

= 1.1.7 =
* În timpul generării nu se mai afișează textul brut (HTML-ul live); apare doar un spinner animat lângă „Se generează…". Articolul intră în editor doar la final, complet.

= 1.1.6 =
* Fix „Conexiune pierdută" / salvare blocată la generări lungi: eliberează lock-ul de sesiune PHP în timpul streaming-ului și încetinește heartbeat-ul wp-admin pe durata generării (revine la normal la final).

= 1.1.5 =
* Auto-update din repo GitHub public (ca tema Magpress): site-urile detectează singure versiunile noi și se actualizează din 1 click (sau auto-update). Gata cu urcatul manual de zip pe fiecare site. Link „Verifică update" sub plugin.

= 1.1.4 =
* Nota informativă (disclaimer) devine obligatorie în FIECARE articol, fără excepție (inclusiv lifestyle/rețete) — nu se mai omite. Specialistul rămâne adaptat la nișă, iar nota începe cu „Notă:".

= 1.1.3 =
* Streaming pornit implicit — textul apare în timp real în loc să aștepți tot articolul; generarea pare mult mai rapidă (fără pierdere de calitate, cu fallback automat pe non-streaming dacă serverul nu suportă).

= 1.1.2 =
* Lungime implicită mai mare: articol standard 900–1000 cuvinte, iar ghidurile/temele complexe se extind automat la 1500–2000. Plafon max_tokens ridicat la 8000 (din 4000) ca articolele să nu se mai taie la mijloc de frază; migrare automată care ridică max_tokens pe site-urile unde era prea mic. Opțiune nouă „Ghid lung" în panou.
* Continuare automată: dacă răspunsul se oprește din cauza limitei de tokeni (stop_reason=max_tokens), pluginul cere automat continuarea prin prefill și lipește textul, ca articolul să fie mereu terminat — pe ambele căi (streaming și non-streaming).
* Model implicit nou: Sonnet 4.6 (respectă mai bine instrucțiunile). Site-urile rămase pe Haiku sunt comutate automat; alegerile manuale (Sonnet/Opus) rămân neatinse.

= 1.1.1 =
* Fix: curăță învelișul de bloc de cod markdown (```html ... ```) când modelul îl adaugă, ca backtick-urile să nu mai ajungă ca text în articol. Rulează înainte de eliminarea titlului, pe ambele căi (streaming și non-streaming).

= 1.1.0 =
* Prompt editorial nou (anti-clișee AI, optimizare Discover/SEO, reguli pe nișă). Eliminarea automată a titlului/H1 din corpul articolului.

= 1.0.0 =
* Versiune inițială: scriere cu Haiku 4.5 / Sonnet 4.6 / Opus 4.8, cost tracking, streaming.
