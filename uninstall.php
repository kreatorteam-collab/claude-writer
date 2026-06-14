<?php
if (!defined('WP_UNINSTALL_PLUGIN')) { exit; }

$options = array(
    'cw_api_key', 'cw_default_model', 'cw_effort', 'cw_temperature',
    'cw_max_tokens', 'cw_stream_enabled', 'cw_monthly_cost_limit',
    'cw_system_prompt', 'cw_article_prompt', 'cw_rewrite_prompt',
    'cw_title_prompt', 'cw_keywords_prompt',
    'cw_calls_total', 'cw_cost_total', 'cw_cost_monthly', 'cw_usage_by_model',
);
foreach ($options as $opt) { delete_option($opt); }
