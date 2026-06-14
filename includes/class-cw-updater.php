<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Auto-update din repo GitHub public (același tipar ca tema Magpress).
 *
 * Citește `.update-info.json` de pe raw.githubusercontent. Dacă versiunea de pe
 * GitHub > versiunea instalată, WordPress afișează „Update available" în Plugins,
 * exact ca pentru un plugin din wp.org — update în 1 click (sau auto-update dacă
 * îl activezi din lista de pluginuri).
 *
 * Flux release: editezi codul -> bump Version în claude-writer.php + .update-info.json
 * -> push pe GitHub. Toate site-urile văd update-ul la următoarea verificare (cache 6h)
 * sau imediat dacă apeși „Verifică update".
 */
class CW_Updater {

    const INFO_URL  = 'https://raw.githubusercontent.com/kreatorteam-collab/claude-writer/main/.update-info.json';
    const CACHE_KEY = 'cw_update_info';

    private $basename; // ex: claude-writer/claude-writer.php
    private $slug;     // ex: claude-writer

    public function init() {
        $this->basename = plugin_basename(CW_PATH . 'claude-writer.php');
        $this->slug     = dirname($this->basename);

        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_update'));
        add_filter('plugins_api', array($this, 'plugin_info'), 10, 3);
        add_filter('upgrader_source_selection', array($this, 'fix_source'), 10, 4);

        // Link „Verifică update" sub plugin în lista de pluginuri.
        add_filter('plugin_action_links_' . $this->basename, array($this, 'action_links'));
        add_action('admin_init', array($this, 'maybe_force_check'));
    }

    /** Citește info-ul de pe GitHub (cache 6h). Returnează array sau false. */
    private function fetch_info($bust = false) {
        if (!$bust) {
            $cached = get_transient(self::CACHE_KEY);
            if ($cached === 'error') { return false; }
            if (is_array($cached)) { return $cached; }
        }

        $url  = self::INFO_URL . ($bust ? ('?_=' . time()) : '');
        $args = array('timeout' => 8);
        if ($bust) {
            $args['headers'] = array('Cache-Control' => 'no-cache', 'Pragma' => 'no-cache');
        }

        $response = wp_remote_get($url, $args);
        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
            set_transient(self::CACHE_KEY, 'error', 30 * MINUTE_IN_SECONDS);
            return false;
        }

        $info = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($info) || empty($info['version']) || empty($info['package'])) {
            set_transient(self::CACHE_KEY, 'error', 30 * MINUTE_IN_SECONDS);
            return false;
        }

        set_transient(self::CACHE_KEY, $info, 6 * HOUR_IN_SECONDS);
        return $info;
    }

    /** Injectează update-ul în transientul WP dacă există o versiune nouă. */
    public function check_update($transient) {
        if (empty($transient->checked)) { return $transient; }

        $installed = isset($transient->checked[$this->basename])
            ? $transient->checked[$this->basename]
            : CW_VERSION;

        $info = $this->fetch_info();
        if (!$info) { return $transient; }

        if (version_compare($installed, $info['version'], '<')) {
            $transient->response[$this->basename] = (object) array(
                'slug'         => $this->slug,
                'plugin'       => $this->basename,
                'new_version'  => $info['version'],
                'url'          => isset($info['url']) ? $info['url'] : '',
                'package'      => $info['package'],
                'tested'       => isset($info['tested']) ? $info['tested'] : '',
                'requires'     => isset($info['requires']) ? $info['requires'] : '',
                'requires_php' => isset($info['requires_php']) ? $info['requires_php'] : '',
            );
        } else {
            // Semnalăm explicit „fără update" — necesar pentru UI-ul de auto-update.
            $transient->no_update[$this->basename] = (object) array(
                'slug'        => $this->slug,
                'plugin'      => $this->basename,
                'new_version' => $info['version'],
                'url'         => isset($info['url']) ? $info['url'] : '',
                'package'     => isset($info['package']) ? $info['package'] : '',
            );
        }

        return $transient;
    }

    /** Popup „View details / Vezi detalii". */
    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information') { return $result; }
        if (empty($args->slug) || $args->slug !== $this->slug) { return $result; }

        $info = $this->fetch_info();
        if (!$info) { return $result; }

        return (object) array(
            'name'          => isset($info['name']) ? $info['name'] : 'Claude Writer',
            'slug'          => $this->slug,
            'version'       => $info['version'],
            'requires'      => isset($info['requires']) ? $info['requires'] : '',
            'requires_php'  => isset($info['requires_php']) ? $info['requires_php'] : '',
            'tested'        => isset($info['tested']) ? $info['tested'] : '',
            'homepage'      => isset($info['url']) ? $info['url'] : '',
            'download_link' => $info['package'],
            'sections'      => array(
                'description' => isset($info['description'])
                    ? $info['description']
                    : 'Generează articole SEO cu Claude direct în editor.',
                'changelog'   => isset($info['changelog_url'])
                    ? '<p><a href="' . esc_url($info['changelog_url']) . '" target="_blank" rel="noopener">Vezi modificările pe GitHub</a></p>'
                    : '',
            ),
        );
    }

    /**
     * GitHub livrează zip-ul cu folderul „claude-writer-main". Îl redenumim la
     * „claude-writer" ca update-ul să suprascrie plugin-ul existent, nu să-l dubleze.
     */
    public function fix_source($source, $remote_source, $upgrader, $hook_extra = array()) {
        // Acționăm doar pentru pachetul nostru: după hook_extra SAU după numele folderului extras.
        $ours = (!empty($hook_extra['plugin']) && $hook_extra['plugin'] === $this->basename)
             || (false !== strpos(basename(rtrim($source, '/')), 'claude-writer'));
        if (!$ours) { return $source; }

        $expected = $this->slug; // claude-writer
        $actual   = basename(rtrim($source, '/'));

        // IMPORTANT: întoarce MEREU calea cu slash final. Altfel Plugin_Upgrader::check_package()
        // face glob('.../claude-writer*.php') în loc de glob('.../claude-writer/*.php'), nu găsește
        // pluginul și raportează „Pachetul nu a putut fi instalat".
        if ($actual === $expected) { return trailingslashit($source); }

        global $wp_filesystem;
        if (!$wp_filesystem) { return $source; }

        $new_source = trailingslashit($remote_source) . $expected;
        if ($wp_filesystem->exists($new_source)) { $wp_filesystem->delete($new_source, true); }
        if ($wp_filesystem->move(untrailingslashit($source), $new_source)) {
            return trailingslashit($new_source);
        }
        return trailingslashit($source);
    }

    public function action_links($links) {
        $url = wp_nonce_url(admin_url('plugins.php?cw_force_check=1'), 'cw_force_check');
        $links[] = '<a href="' . esc_url($url) . '">' . esc_html__('Verifică update', 'claude-writer') . '</a>';
        return $links;
    }

    /** „Verifică update acum": golește cache-ul și re-fetchează cu cache-buster. */
    public function maybe_force_check() {
        if (empty($_GET['cw_force_check']) || !current_user_can('update_plugins')) { return; }
        check_admin_referer('cw_force_check');

        delete_transient(self::CACHE_KEY);
        delete_site_transient('update_plugins');
        if (function_exists('wp_cache_delete')) {
            wp_cache_delete(self::CACHE_KEY, 'transient');
            wp_cache_delete('update_plugins', 'site-transient');
        }
        $this->fetch_info(true);

        wp_safe_redirect(admin_url('plugins.php'));
        exit;
    }
}
