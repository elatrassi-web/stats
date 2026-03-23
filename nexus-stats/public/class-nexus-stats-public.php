<?php
if (!defined('ABSPATH')) exit;

class Nexus_Stats_Public {
    public static function init() {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
    }

    public static function enqueue_scripts() {
        if (is_single() || is_page()) {
            global $post;

            wp_register_script('nexus-stats-public-js', NEXUS_STATS_VIEWS_URL . 'assets/js/nexus-stats-public.js', [], NEXUS_STATS_VIEWS_VERSION, true);

            $refresh_rate = get_option('nexus_stats_refresh_rate', 60);
            $eco_mode = get_option('nexus_stats_eco_mode', 'no');

            wp_localize_script('nexus-stats-public-js', 'nexusStatsData', [
                'postID' => $post->ID,
                'restUrl' => esc_url_raw(rest_url('nexus-stats/v1')),
                'refreshRate' => (int)$refresh_rate * 1000,
                'ecoMode' => $eco_mode,
                'nonce' => wp_create_nonce('wp_rest') // Pour l'API REST
            ]);

            wp_enqueue_script('nexus-stats-public-js');
        }
    }
}