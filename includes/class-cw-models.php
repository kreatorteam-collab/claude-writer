<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Registrul celor 3 modele Claude: preț, capabilități, etichete.
 * Sursă prețuri ($/1M tokeni): Haiku 4.5 = 1/5, Sonnet 4.6 = 3/15, Opus 4.8 = 5/25.
 */
class CW_Models {

    public static function all() {
        return array(
            'claude-haiku-4-5' => array(
                'label'    => 'Haiku 4.5',
                'tagline'  => 'Ieftin & rapid',
                'desc'     => 'Cel mai ieftin. Bun pentru volum mare și articole generale.',
                'in'       => 1.0,   // $ / 1M tokeni input
                'out'      => 5.0,   // $ / 1M tokeni output
                'temperature' => true,   // acceptă temperature
                'effort'      => false,  // NU acceptă output_config.effort (dă eroare 400)
                'badge'    => 'ieftin',
            ),
            'claude-sonnet-4-6' => array(
                'label'    => 'Sonnet 4.6',
                'tagline'  => 'Echilibrat',
                'desc'     => 'Cel mai bun raport calitate/preț. Recomandat pentru articole de nișă.',
                'in'       => 3.0,
                'out'      => 15.0,
                'temperature' => true,
                'effort'      => true,
                'badge'    => 'echilibrat',
            ),
            'claude-opus-4-8' => array(
                'label'    => 'Opus 4.8',
                'tagline'  => 'Calitate maximă',
                'desc'     => 'Cel mai capabil. Pentru conținut premium care trebuie să convertească.',
                'in'       => 5.0,
                'out'      => 25.0,
                'temperature' => false,  // Opus 4.8 NU acceptă temperature/top_p (eroare 400)
                'effort'      => true,
                'badge'    => 'premium',
            ),
        );
    }

    public static function exists($id) {
        return array_key_exists($id, self::all());
    }

    public static function get($id) {
        $all = self::all();
        return isset($all[$id]) ? $all[$id] : null;
    }

    /** Modelul implicit configurat (cu fallback sigur). */
    public static function default_model() {
        $m = get_option('cw_default_model', 'claude-haiku-4-5');
        return self::exists($m) ? $m : 'claude-haiku-4-5';
    }

    /**
     * Cost în dolari pentru o utilizare dată.
     * cache_read ~0.1x input, cache_creation ~1.25x input.
     */
    public static function cost($model_id, $usage) {
        $m = self::get($model_id);
        if (!$m) { return 0.0; }
        $in    = isset($usage['input_tokens']) ? (int) $usage['input_tokens'] : 0;
        $out   = isset($usage['output_tokens']) ? (int) $usage['output_tokens'] : 0;
        $cread = isset($usage['cache_read_input_tokens']) ? (int) $usage['cache_read_input_tokens'] : 0;
        $ccrea = isset($usage['cache_creation_input_tokens']) ? (int) $usage['cache_creation_input_tokens'] : 0;

        $cost  = ($in / 1000000.0) * $m['in'];
        $cost += ($out / 1000000.0) * $m['out'];
        $cost += ($cread / 1000000.0) * $m['in'] * 0.1;
        $cost += ($ccrea / 1000000.0) * $m['in'] * 1.25;
        return $cost;
    }
}
