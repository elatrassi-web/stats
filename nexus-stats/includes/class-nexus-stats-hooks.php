<?php
if (!defined('ABSPATH')) exit;

class Nexus_Stats_Hooks {
    public static function init() {
        // 404 Detection
        add_action('template_redirect', [__CLASS__, 'detect_404']);

        // WooCommerce Tracking
        if (class_exists('WooCommerce')) {
            // Track source before checkout
            add_action('wp', [__CLASS__, 'woo_capture_source']);
            // Process order after payment
            add_action('woocommerce_thankyou', [__CLASS__, 'woo_process_conversion']);
        }

        // Downtime Cron
        add_action('nexus_stats_downtime_check', [__CLASS__, 'check_downtime']);
    }

    // --- 404 Tracker ---
    public static function detect_404() {
        if (is_404() && get_option('nexus_stats_track_404', 'yes') === 'yes') {
            global $wpdb;
            $table = $wpdb->prefix . 'nexus_stats_404';

            $url = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '';
            $referer = wp_get_referer() ?: '';

            $wpdb->query($wpdb->prepare("
                INSERT INTO $table (requested_url, referer_url, hit_count, last_hit)
                VALUES (%s, %s, 1, CURRENT_TIMESTAMP)
                ON DUPLICATE KEY UPDATE hit_count = hit_count + 1, last_hit = CURRENT_TIMESTAMP
            ", substr($url, 0, 191), substr($referer, 0, 255)));
        }
    }

    // --- WooCommerce Tracker ---
    public static function woo_capture_source() {
        if (get_option('nexus_stats_woo_sync', 'yes') === 'yes' && !is_admin() && !isset($_COOKIE['nexus_stats_source'])) {
            $referrer = isset($_SERVER['HTTP_REFERER']) ? esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER'])) : '';
            $source = Nexus_Stats_DB::parse_traffic_source($referrer, is_front_page());
            // Store in cookie for 30 days
            setcookie('nexus_stats_source', $source['type'], time() + (30 * 24 * 60 * 60), COOKIEPATH, COOKIE_DOMAIN);
        }
    }

    public static function woo_process_conversion($order_id) {
        if (get_option('nexus_stats_woo_sync', 'yes') !== 'yes' || !$order_id) return;

        $order = wc_get_order($order_id);
        if (!$order) return;

        global $wpdb;
        $table = $wpdb->prefix . 'nexus_stats_woo';

        $source = isset($_COOKIE['nexus_stats_source']) ? sanitize_text_field(wp_unslash($_COOKIE['nexus_stats_source'])) : 'direct';
        $total = $order->get_total();

        $wpdb->query($wpdb->prepare("
            INSERT IGNORE INTO $table (order_id, referrer_type, order_total, order_date)
            VALUES (%d, %s, %f, CURRENT_TIMESTAMP)
        ", $order_id, $source, $total));
    }

    // --- Downtime Alert Cron ---
    public static function check_downtime() {
        if (get_option('nexus_stats_downtime_alerts', 'yes') !== 'yes') return;

        global $wpdb;
        $table = $wpdb->prefix . 'nexus_stats_views_log';

        // 1. Get average views for this exact hour over the last 30 days
        $current_hour = gmdate('H');
        $thirty_days_ago = gmdate('Y-m-d H:i:s', strtotime('-30 days'));

        $avg_hourly_traffic = (int) $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(id) / 30
            FROM $table
            WHERE HOUR(view_datetime) = %d AND view_datetime >= %s
        ", $current_hour, $thirty_days_ago));

        // If the average traffic is low anyway (e.g. < 5 views/hour), ignore to avoid false positives in the middle of the night
        if ($avg_hourly_traffic < 5) return;

        // 2. Get views in the last 4 hours
        $four_hours_ago = gmdate('Y-m-d H:i:s', strtotime('-4 hours'));
        $recent_traffic = (int) $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(id) FROM $table
            WHERE view_datetime >= %s
        ", $four_hours_ago));

        // 3. If recent traffic is exactly 0, trigger alert
        if ($recent_traffic === 0) {
            $admin_email = get_option('admin_email');
            $site_name = get_bloginfo('name');
            $subject = "[Urgent] Chute anormale de trafic sur $site_name";
            $message = "Bonjour,\n\nNexus Stats a détecté une chute anormale de trafic.\n";
            $message .= "Vous avez eu 0 visiteur au cours des 4 dernières heures, alors que votre moyenne est d'environ $avg_hourly_traffic visiteurs à cette heure-ci.\n";
            $message .= "Veuillez vérifier si votre site est toujours en ligne (Downtime) ou s'il y a un problème serveur.\n\nL'équipe Nexus Stats.";

            wp_mail($admin_email, $subject, $message);
        }
    }
}