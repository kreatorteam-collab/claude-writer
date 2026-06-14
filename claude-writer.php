<?php
/**
 * Plugin Name: Claude Writer
 * Plugin URI: https://targetseo.ro/
 * Description: Generează și rescrie articole SEO direct în editor, cu alegere între cele 3 modele Claude (Haiku 4.5, Sonnet 4.6, Opus 4.8). Conexiune directă la API-ul Anthropic, cu calcul de cost real per model și limită lunară de cheltuieli.
 * Version: 1.2.0
 * Author: Eduard / TargetSEO
 * Text Domain: claude-writer
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * License: GPL v2 or later
 */

if (!defined('ABSPATH')) {
    exit;
}

define('CW_VERSION', '1.2.0');
define('CW_PATH', plugin_dir_path(__FILE__));
define('CW_URL', plugin_dir_url(__FILE__));

require_once CW_PATH . 'includes/class-cw-crypto.php';
require_once CW_PATH . 'includes/class-cw-models.php';
require_once CW_PATH . 'includes/class-cw-usage.php';
require_once CW_PATH . 'includes/class-cw-api.php';
require_once CW_PATH . 'includes/class-cw-admin.php';
require_once CW_PATH . 'includes/class-cw-editor.php';
require_once CW_PATH . 'includes/class-cw-rest.php';
require_once CW_PATH . 'includes/class-cw-updater.php';

function cw_init() {
    (new CW_Admin())->init();
    (new CW_Editor())->init();
    (new CW_Rest())->init();
    (new CW_Updater())->init();
}
add_action('plugins_loaded', 'cw_init');

/**
 * Migrare la upgrade: pe site-urile deja active, opțiunile de prompt au fost
 * salvate la activare cu valorile vechi, iar add_option() nu le suprascrie.
 * Aici reîmprospătăm promptul de sistem + articol la noile valori implicite,
 * DAR doar dacă utilizatorul nu și-a personalizat textul (păstrăm editările manuale).
 */
add_action('admin_init', function () {
    $stored = get_option('cw_db_version', '');
    if (version_compare($stored, CW_VERSION, '>=')) {
        return;
    }

    // Vechile valori implicite (înainte de 1.1.0). Dacă opțiunea încă le conține,
    // o considerăm „needitată” și o aducem la noul prompt editorial.
    $old_system  = "Ești un copywriter SEO profesionist care scrie în limba română. Reguli stricte:\n"
        . "- Folosește OBLIGATORIU diacriticele românești corecte (ă, â, î, ș, ț).\n"
        . "- Scrie natural, direct, ca un expert uman. Evită limbajul artificial de AI.\n"
        . "- INTERZIS: clișee și umplutură de tipul „În era digitală”, „În lumea de azi”, „Este important de menționat”, „Cu toate acestea”, „În concluzie”.\n"
        . "- Fără emoji. Fără liniuță lungă (—); folosește virgulă sau punct.\n"
        . "- Structurează cu subtitluri (H2/H3) relevante, paragrafe scurte și liste când ajută.\n"
        . "- Ton informativ, concret, util. Exemple specifice, nu generalități.\n"
        . "- Nu inventa date sau statistici false.";

    $old_article = "Scrie CORPUL unui articol complet, optimizat SEO, pe tema: {{continut}}.\n"
        . "NU include titlul ca H1 — titlul există deja separat în pagină. Începe direct cu un paragraf de introducere care prinde cititorul.\n"
        . "Folosește subtitluri H2/H3 relevante, paragrafe scurte, liste când ajută și o încheiere utilă (fără subtitlul „Concluzie”).\n"
        . "Lungime: aproximativ {{cuvinte}} de cuvinte.\n"
        . "Returnează DOAR HTML curat: h2, h3, p, ul, li, strong. FĂRĂ h1, fără blocuri de cod, fără ```html.";

    // Promptul de articol implicit din 1.1.0/1.1.1 (înainte de regula de lungime pe ghiduri).
    // Dacă opțiunea încă îl conține neschimbat, o considerăm „needitată".
    $old_article_110 = <<<'TXT'
Redactează articolul pe baza acestei teme / titlu / brief:
{{continut}}

Lungime țintă: aproximativ {{cuvinte}} de cuvinte (respectă regula de a nu umfla artificial — dacă subiectul cere mai puțin, scrie mai puțin).
Începe DIRECT cu primul paragraf (lead-ul). NU repeta titlul în conținut: fără H1, și fără ca primul subtitlu (H2/H3) să fie titlul articolului sau o reformulare a lui. Titlul există deja deasupra, în pagină. Primul element livrat trebuie să fie un <p>, nu un heading. Aplică toate regulile editoriale, de structură și de format HTML din instrucțiunile de sistem.
Livrează DOAR HTML curat (h2, h3, p, ul, ol, li, strong, i), fără markdown, fără blocuri de cod și fără niciun comentariu înainte sau după articol.
TXT;

    $cur_system = get_option('cw_system_prompt', '');
    if ($cur_system === '' || $cur_system === $old_system) {
        update_option('cw_system_prompt', CW_Admin::default_system_prompt(), 'no');
    } else {
        // Patch-uri chirurgicale pe promptul de sistem (păstrăm restul personalizărilor).
        $patched = $cur_system;

        // 1) Linia de lungime (1.1.0 -> 1.1.2).
        $old_len = '– Lungimea optimă: 900–1300 cuvinte (nu umfla artificial)';
        $new_len = '– Lungime: articol standard 900–1000 cuvinte; pentru ghiduri, tutoriale, liste pas-cu-pas sau teme complexe extinde la 1500–2000 de cuvinte (fără a umfla artificial)';
        if (strpos($patched, $old_len) !== false) {
            $patched = str_replace($old_len, $new_len, $patched);
        }

        // 2) Nota informativă devine OBLIGATORIE în fiecare articol (1.1.3 -> 1.1.4).
        $old_nota = <<<'TXT'
La finalul articolului, înainte de paragraful de încheiere SAU după, adaugă un paragraf în <i> </i> cu un disclaimer scurt, neutru, profesionist. Specialistul recomandat se alege în funcție de nișă: medic, farmacist, nutriționist, dermatolog, ginecolog, pediatru, avocat, consultant financiar, broker credite, mecanic auto, electrician autorizat, instalator, veterinar, psiholog, antrenor personal, agent imobiliar etc. Nu folosi ton alarmist.

Pentru articole pur recreative (rețete simple, recomandări de filme/cărți, povești, lifestyle ușor) unde nu are sens un disclaimer cu specialist, poți omite nota informativă sau o poți formula ca o simplă mențiune editorială scurtă.
TXT;
        $new_nota = <<<'TXT'
OBLIGATORIU în FIECARE articol, fără excepție: la finalul articolului, înainte de paragraful de încheiere SAU după, adaugă o notă informativă (paragraf în <i> </i>, începută cu „Notă:") — un disclaimer scurt, neutru, profesionist. Specialistul recomandat se alege în funcție de nișă: medic, farmacist, nutriționist, dermatolog, ginecolog, pediatru, avocat, consultant financiar, broker credite, mecanic auto, electrician autorizat, instalator, veterinar, psiholog, antrenor personal, agent imobiliar etc. Nu folosi ton alarmist.

Chiar și pentru articolele pur recreative (rețete, recomandări de filme/cărți, povești, lifestyle ușor) nota NU se omite niciodată — o formulezi scurt și editorial (o mențiune de bun-simț), dar trebuie să existe în orice articol.
TXT;
        if (strpos($patched, $old_nota) !== false) {
            $patched = str_replace($old_nota, $new_nota, $patched);
        }

        // 3) Nota informativă: format <blockquote> (1.1.4 -> 1.1.9).
        $patched = str_replace(
            '(paragraf în <i> </i>, începută cu „Notă:")',
            'într-un bloc <blockquote> cu textul în <i> </i>, începută cu „Notă:"',
            $patched
        );
        $patched = str_replace(
            '– Disclaimer-ul cu <i> ... </i>',
            '– Nota informativă (disclaimer) e OBLIGATORIE și se pune într-un <blockquote> cu textul în <i> ... </i>',
            $patched
        );

        if ($patched !== $cur_system) {
            update_option('cw_system_prompt', $patched, 'no');
        }
    }

    $cur_article = get_option('cw_article_prompt', '');
    if ($cur_article === '' || $cur_article === $old_article || $cur_article === $old_article_110) {
        update_option('cw_article_prompt', CW_Admin::default_article_prompt(), 'no');
    }

    // Articolele se trunchiau acolo unde max_tokens era prea mic (ex.: 2456 -> articol tăiat la
    // jumătate de frază). Ridicăm plafonul ca să încapă și un ghid de ~2000 de cuvinte în română.
    $max = (int) get_option('cw_max_tokens', 8000);
    if ($max > 0 && $max < 6000) {
        update_option('cw_max_tokens', 8000, 'no');
    }

    // Modelul implicit recomandat e acum Sonnet 4.6 (respectă mai bine instrucțiunile decât
    // Haiku). Comutăm site-urile rămase pe vechiul implicit Haiku; alegerile manuale rămân.
    // Reversibil oricând din Setări → Claude Writer.
    if (get_option('cw_default_model', '') === 'claude-haiku-4-5') {
        update_option('cw_default_model', 'claude-sonnet-4-6', 'no');
    }

    // Streaming pornit implicit: textul apare în timp real, deci generarea pare mult mai
    // rapidă (zero pierdere de calitate; dacă serverul nu-l suportă, JS-ul cade automat pe
    // non-streaming). Se poate opri oricând din Setări → Claude Writer.
    if ((int) get_option('cw_stream_enabled', 0) === 0) {
        update_option('cw_stream_enabled', 1, 'no');
    }

    // Notă de final (disclaimer) configurabilă — rezerva garantată din editor.js.
    if (false === get_option('cw_disclaimer', false)) {
        update_option('cw_disclaimer', CW_Admin::default_disclaimer(), 'no');
    }

    update_option('cw_db_version', CW_VERSION, 'no');
});

register_activation_hook(__FILE__, function () {
    // Valori implicite la activare, cu autoload='no': opțiunile sunt folosite
    // doar în admin, deci NU trebuie încărcate pe fiecare pagină din front-end.
    $defaults = array(
        'cw_api_key'            => '',
        'cw_default_model'      => 'claude-sonnet-4-6',
        'cw_effort'             => 'medium',
        'cw_temperature'        => 0.8,
        'cw_max_tokens'         => 8000,
        'cw_stream_enabled'     => 1,
        'cw_monthly_cost_limit' => 0,
        'cw_system_prompt'      => CW_Admin::default_system_prompt(),
        'cw_article_prompt'     => CW_Admin::default_article_prompt(),
        'cw_rewrite_prompt'     => CW_Admin::default_rewrite_prompt(),
        'cw_title_prompt'       => CW_Admin::default_title_prompt(),
        'cw_keywords_prompt'    => CW_Admin::default_keywords_prompt(),
        'cw_disclaimer'         => CW_Admin::default_disclaimer(),
        // Statistici de utilizare (create din timp ca să nu se autoîncarce)
        'cw_calls_total'        => 0,
        'cw_cost_total'         => 0,
        'cw_cost_monthly'       => array(),
        'cw_usage_by_model'     => array(),
    );
    foreach ($defaults as $name => $value) {
        add_option($name, $value, '', 'no');
    }
});
