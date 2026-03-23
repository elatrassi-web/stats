<?php
if (!defined('ABSPATH')) exit;

class My_Angers_Public {
    public static function init() {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
    }

    public static function enqueue_scripts() {
        if (is_single() || is_page()) {
            global $post;

            wp_register_script('my-angers-public-js', MY_ANGERS_VIEWS_URL . 'assets/js/my-angers-public.js', [], MY_ANGERS_VIEWS_VERSION, true);

            $refresh_rate = get_option('my_angers_refresh_rate', 60);
            $eco_mode = get_option('my_angers_eco_mode', 'no');

            wp_localize_script('my-angers-public-js', 'myAngersData', [
                'postID' => $post->ID,
                'restUrl' => esc_url_raw(rest_url('my-angers/v1')),
                'refreshRate' => (int)$refresh_rate * 1000,
                'ecoMode' => $eco_mode,
                'nonce' => wp_create_nonce('wp_rest') // Pour l'API REST
            ]);

            wp_enqueue_script('my-angers-public-js');
        }
    }
}