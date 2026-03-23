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
            $gdpr_strict = get_option('nexus_stats_gdpr_strict', 'no');

            wp_localize_script('nexus-stats-public-js', 'nexusStatsData', [
                'postID' => $post->ID,
                'restUrl' => esc_url_raw(rest_url('nexus-stats/v1')),
                'refreshRate' => (int)$refresh_rate * 1000,
                'ecoMode' => $eco_mode,
                'gdprStrict' => $gdpr_strict,
                'trackScroll' => get_option('nexus_stats_track_scroll', 'yes'),
                'trackOutbound' => get_option('nexus_stats_track_outbound', 'yes'),
                'nonce' => wp_create_nonce('wp_rest') // Pour l'API REST
            ]);

            wp_enqueue_script('nexus-stats-public-js');

            // Si l'utilisateur est admin, charger le Heatmap
            if (current_user_can('manage_options')) {
                wp_register_script('nexus-stats-heatmap-js', NEXUS_STATS_VIEWS_URL . 'assets/js/nexus-stats-heatmap.js', [], NEXUS_STATS_VIEWS_VERSION, true);
                wp_localize_script('nexus-stats-heatmap-js', 'nexusStatsHeatmapData', [
                    'postID' => $post->ID,
                    'restUrl' => esc_url_raw(rest_url('nexus-stats/v1')),
                    'nonce' => wp_create_nonce('wp_rest'),
                    'i18n' => [
                        'clicks' => __('clics', 'nexus-stats'),
                        'clicks_on_element' => __('Nexus Stats: %s clics sur cet élément', 'nexus-stats')
                    ]
                ]);
                wp_enqueue_script('nexus-stats-heatmap-js');
            }
        }
    }
}