<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Client pentru API-ul Anthropic (Messages API).
 * Construiește payload-ul adaptat la capabilitățile fiecărui model.
 */
class CW_API {

    const ENDPOINT = 'https://api.anthropic.com/v1/messages';
    const VERSION  = '2023-06-01';

    public static function api_key() {
        // Cheia e stocată criptat; o decriptăm la citire.
        return trim((string) CW_Crypto::decrypt(get_option('cw_api_key', '')));
    }

    public static function has_key() {
        return self::api_key() !== '';
    }

    public static function headers() {
        return array(
            'x-api-key'         => self::api_key(),
            'anthropic-version' => self::VERSION,
            'content-type'      => 'application/json',
        );
    }

    /**
     * Construiește corpul cererii pentru un model + prompt date.
     *
     * @param string $model_id
     * @param string $user_prompt
     * @param bool   $stream
     * @param int    $max_tokens
     */
    public static function build_payload($model_id, $user_prompt, $stream = false, $max_tokens = null) {
        $m = CW_Models::get($model_id);
        if (!$m) { $model_id = CW_Models::default_model(); $m = CW_Models::get($model_id); }

        if ($max_tokens === null) {
            $max_tokens = (int) get_option('cw_max_tokens', 8000);
        }
        $max_tokens = max(256, min(16000, (int) $max_tokens));

        $system = trim((string) get_option('cw_system_prompt', ''));

        // $user_prompt poate fi un string (un singur mesaj „user") sau un array de mesaje
        // gata construit — folosit pentru continuarea automată cu prefill „assistant".
        $messages = is_array($user_prompt)
            ? $user_prompt
            : array(array('role' => 'user', 'content' => $user_prompt));

        $payload = array(
            'model'      => $model_id,
            'max_tokens' => $max_tokens,
            'messages'   => $messages,
        );

        // System prompt cu prompt caching (gratuit; reduce costul când e refolosit).
        if ($system !== '') {
            $payload['system'] = array(
                array(
                    'type'          => 'text',
                    'text'          => $system,
                    'cache_control' => array('type' => 'ephemeral'),
                ),
            );
        }

        // Parametrii se trimit DOAR pe modelele care îi acceptă, ca să nu primim 400:
        //  - temperature: Haiku 4.5 și Sonnet 4.6 (Opus 4.8 o respinge);
        //  - output_config.effort: Sonnet 4.6 și Opus 4.8 (Haiku nu îl acceptă).
        // Pe Sonnet pot coexista (temperature = creativitate, effort = cost/profunzime).
        if (!empty($m['temperature'])) {
            $temp = (float) get_option('cw_temperature', 0.8);
            $payload['temperature'] = max(0.0, min(1.0, $temp));
        }
        if (!empty($m['effort'])) {
            $effort = get_option('cw_effort', 'medium');
            if (!in_array($effort, array('low', 'medium', 'high'), true)) { $effort = 'medium'; }
            $payload['output_config'] = array('effort' => $effort);
        }

        if ($stream) {
            $payload['stream'] = true;
        }

        return $payload;
    }

    /**
     * Apel non-streaming. Returnează array('text','usage','cost') sau WP_Error.
     */
    public static function generate($model_id, $user_prompt, $max_tokens = null) {
        if (!self::has_key()) {
            return new WP_Error('cw_no_key', __('Cheia API Anthropic nu este configurată.', 'claude-writer'));
        }
        if (CW_Usage::limit_reached()) {
            return new WP_Error('cw_limit', __('Ai atins limita lunară de cheltuieli. Mărește-o din setări.', 'claude-writer'));
        }

        // Continuăm automat cât timp răspunsul se taie la „max_tokens", ca articolul să fie
        // întotdeauna terminat. 1 apel inițial + maximum 2 continuări prin prefill „assistant".
        $messages   = array(array('role' => 'user', 'content' => $user_prompt));
        $full       = '';
        $cost       = 0.0;
        $last_usage = array();
        $max_rounds = 3;

        for ($round = 0; $round < $max_rounds; $round++) {
            $payload = self::build_payload($model_id, $messages, false, $max_tokens);

            $response = wp_remote_post(self::ENDPOINT, array(
                'headers' => self::headers(),
                'body'    => wp_json_encode($payload),
                'timeout' => 180,
            ));

            if (is_wp_error($response)) {
                if ($full !== '') { break; } // păstrăm textul parțial deja obținut
                return new WP_Error('cw_http', __('Eroare de conexiune: ', 'claude-writer') . $response->get_error_message());
            }

            $code = wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);

            if ($code !== 200 || !is_array($body)) {
                if ($full !== '') { break; }
                $msg = is_array($body) && isset($body['error']['message'])
                    ? $body['error']['message']
                    : __('Răspuns invalid de la API.', 'claude-writer');
                return new WP_Error('cw_api', sprintf(__('Eroare API (%d): %s', 'claude-writer'), $code, $msg));
            }

            // Extrage textul din blocurile de conținut.
            $text = '';
            if (isset($body['content']) && is_array($body['content'])) {
                foreach ($body['content'] as $block) {
                    if (isset($block['type']) && $block['type'] === 'text' && isset($block['text'])) {
                        $text .= $block['text'];
                    }
                }
            }

            $usage      = isset($body['usage']) && is_array($body['usage']) ? $body['usage'] : array();
            $last_usage = $usage;
            $cost      += CW_Usage::log($model_id, $usage);
            $full      .= $text;

            $stop = isset($body['stop_reason']) ? $body['stop_reason'] : '';
            if ($stop !== 'max_tokens') {
                break; // articol terminat natural
            }

            // S-a tăiat: continuăm cu prefill — modelul continuă exact de unde a rămas.
            $full = rtrim($full);
            if ($full === '') { break; }
            $messages = array(
                array('role' => 'user',      'content' => $user_prompt),
                array('role' => 'assistant', 'content' => $full),
            );
        }

        return array(
            'text'  => trim($full),
            'usage' => $last_usage,
            'cost'  => $cost,
        );
    }
}
