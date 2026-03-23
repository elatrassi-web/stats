<?php
if (!defined('ABSPATH')) exit; // Sécurité

class Nexus_Stats_DB {
    public static function init() {
        // Logique d'initialisation de la DB si nécessaire (ex: cron)
    }

    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Table 1 : Historique complet (Graphique)
        $table_log = $wpdb->prefix . 'nexus_stats_views_log';
        $sql1 = "CREATE TABLE $table_log (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            visitor_id varchar(64) NOT NULL DEFAULT '',
            view_datetime datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            device_type varchar(10) DEFAULT 'desktop',
            read_time_seconds int(11) DEFAULT 0,
            scroll_depth int(3) DEFAULT 0,
            load_time_ms int(11) DEFAULT 0,
            referrer_type varchar(20) DEFAULT 'direct',
            referrer_domain varchar(100) DEFAULT '',
            country_code varchar(2) DEFAULT 'XX',
            browser_lang varchar(5) DEFAULT 'en',
            PRIMARY KEY  (id),
            KEY post_id (post_id),
            KEY visitor_id (visitor_id),
            KEY view_datetime (view_datetime),
            KEY referrer_type (referrer_type),
            KEY country_code (country_code)
        ) $charset_collate;";

        // Table 2 : Visiteurs en Direct (S'auto-nettoie)
        $table_live = $wpdb->prefix . 'nexus_stats_live_users';
        $sql2 = "CREATE TABLE $table_live (
            visitor_id varchar(64) NOT NULL,
            last_ping datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            post_id bigint(20) DEFAULT 0,
            PRIMARY KEY  (visitor_id),
            KEY last_ping (last_ping)
        ) $charset_collate;";

        // Table 3 : Annotations (Graphique)
        $table_annotations = $wpdb->prefix . 'nexus_stats_annotations';
        $sql3 = "CREATE TABLE $table_annotations (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            note_date date NOT NULL,
            note_text varchar(255) NOT NULL,
            PRIMARY KEY  (id),
            KEY note_date (note_date)
        ) $charset_collate;";

        // Table 4 : Heatmap Clicks
        $table_clicks = $wpdb->prefix . 'nexus_stats_clicks';
        $sql4 = "CREATE TABLE $table_clicks (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            element_selector varchar(255) NOT NULL,
            click_count bigint(20) DEFAULT 1,
            PRIMARY KEY  (id),
            UNIQUE KEY post_selector (post_id, element_selector(191))
        ) $charset_collate;";

        // Table 5 : Local IP Country Cache (RGPD+)
        $table_ip_cache = $wpdb->prefix . 'nexus_stats_ip_cache';
        $sql5 = "CREATE TABLE $table_ip_cache (
            ip_hash varchar(64) NOT NULL,
            country_code varchar(2) NOT NULL,
            last_checked datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (ip_hash)
        ) $charset_collate;";

        // Table 6 : Outbound Links
        $table_outbound = $wpdb->prefix . 'nexus_stats_outbound';
        $sql6 = "CREATE TABLE $table_outbound (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            target_url varchar(255) NOT NULL,
            click_count bigint(20) DEFAULT 1,
            last_clicked datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY target_url (target_url(191))
        ) $charset_collate;";

        // Table 7 : 404 Errors
        $table_404 = $wpdb->prefix . 'nexus_stats_404';
        $sql7 = "CREATE TABLE $table_404 (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            requested_url varchar(255) NOT NULL,
            referer_url varchar(255) DEFAULT '',
            hit_count bigint(20) DEFAULT 1,
            last_hit datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY requested_url (requested_url(191))
        ) $charset_collate;";

        // Table 8 : WooCommerce Conversions
        $table_woo = $wpdb->prefix . 'nexus_stats_woo';
        $sql8 = "CREATE TABLE $table_woo (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            order_id bigint(20) NOT NULL,
            referrer_type varchar(20) NOT NULL,
            order_total decimal(10,2) DEFAULT 0.00,
            order_date datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY order_id (order_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql1);
        dbDelta($sql2);
        dbDelta($sql3);
        dbDelta($sql4);
        dbDelta($sql5);
        dbDelta($sql6);
        dbDelta($sql7);
        dbDelta($sql8);
    }

    // Static Query Timer
    public static $query_time = 0;

    // Helper : Save rolling average of query time
    private static function save_query_time_average($time_ms) {
        $avg = get_option('nexus_stats_avg_query_time', 0);
        if ($avg == 0) {
            $new_avg = $time_ms;
        } else {
            // Rolling average (weighting recent queries slightly more)
            $new_avg = ($avg * 0.9) + ($time_ms * 0.1);
        }
        update_option('nexus_stats_avg_query_time', $new_avg, false);
    }

    // Helper : Get Country Code safely (RGPD+)
    public static function get_country_code($ip) {
        if (empty($ip) || $ip === '127.0.0.1' || $ip === '::1') return 'XX';

        global $wpdb;
        $table_ip_cache = $wpdb->prefix . 'nexus_stats_ip_cache';
        $ip_hash = md5($ip . wp_salt()); // Hash IP so it's never stored in clear text

        // Check cache
        $cached_country = $wpdb->get_var($wpdb->prepare("SELECT country_code FROM $table_ip_cache WHERE ip_hash = %s", $ip_hash));
        if ($cached_country) return $cached_country;

        // Fallback API if no GeoLite2 is present (Note: For a real 2026 local DB, you'd use a maxmind reader library here.
        // Using a free API with a timeout as a placeholder since we can't bundle a 60MB Maxmind DB).
        $response = wp_remote_get("http://ip-api.com/json/{$ip}?fields=countryCode", ['timeout' => 2]);
        $country = 'XX';
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) == 200) {
            $body = json_decode(wp_remote_retrieve_body($response));
            if (!empty($body->countryCode)) $country = strtoupper($body->countryCode);
        }

        // Save to cache
        $wpdb->replace($table_ip_cache, ['ip_hash' => $ip_hash, 'country_code' => $country]);
        return $country;
    }

    // Helper : Parse Referrer & Detect Dark Traffic
    public static function parse_traffic_source($referrer_url, $is_home) {
        if (empty($referrer_url)) {
            // Dark Traffic Logic: Empty referrer + deep link (not home) = Private Share (WhatsApp/Signal)
            return $is_home ? ['type' => 'direct', 'domain' => ''] : ['type' => 'private', 'domain' => 'Dark Social'];
        }

        $parsed = wp_parse_url($referrer_url);
        $domain = isset($parsed['host']) ? strtolower(str_replace('www.', '', $parsed['host'])) : '';

        // Search Engines
        if (preg_match('/google\.|bing\.|yahoo\.|duckduckgo\.|yandex\.|qwant\./', $domain)) {
            return ['type' => 'search', 'domain' => $domain];
        }

        // Social Networks
        if (preg_match('/facebook\.|t\.co|twitter\.|x\.|instagram\.|linkedin\.|pinterest\.|tiktok\./', $domain)) {
            return ['type' => 'social', 'domain' => $domain];
        }

        // Self (Internal traffic)
        $home_url = wp_parse_url(home_url(), PHP_URL_HOST);
        if ($domain === str_replace('www.', '', $home_url)) {
            return ['type' => 'internal', 'domain' => $domain];
        }

        // Referral (Other sites)
        return ['type' => 'referral', 'domain' => $domain];
    }

    // Helper : Track a view
    public static function track_view($post_id, $visitor_id, $device_type = 'desktop', $referrer = '', $lang = 'en') {
        global $wpdb;
        $table_log = $wpdb->prefix . 'nexus_stats_views_log';

        // Process IP for Geolocation (Anonymized)
        $ip = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR'])) : (isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '');
        $country_code = self::get_country_code(explode(',', $ip)[0]);

        // Process Referrer
        $is_home = (get_option('page_on_front') == $post_id) || ($post_id == 0);
        $source = self::parse_traffic_source($referrer, $is_home);

        // Process Language (Extract just the 2 letter code, e.g., 'fr-FR' -> 'fr')
        $lang_code = strtolower(substr($lang, 0, 2));

        $wpdb->suppress_errors = true;
        $start_time = microtime(true);

        $wpdb->insert($table_log, array(
            'post_id'       => $post_id,
            'visitor_id'    => $visitor_id,
            'device_type'   => $device_type,
            'view_datetime' => current_time('mysql'),
            'referrer_type' => $source['type'],
            'referrer_domain'=> substr($source['domain'], 0, 100),
            'country_code'  => $country_code,
            'browser_lang'  => $lang_code
        ));

        self::$query_time += (microtime(true) - $start_time) * 1000;
        self::save_query_time_average(self::$query_time);

        $current_views = (int) get_post_meta($post_id, 'nexus_stats_view_count', true);
        update_post_meta($post_id, 'nexus_stats_view_count', $current_views + 1);

        return ['success' => true, 'source' => $source['type']];
    }

    // Helper : Update advanced metrics (Scroll & Load Time)
    public static function update_metrics($post_id, $visitor_id, $scroll, $load_time) {
        global $wpdb;
        $table_log = $wpdb->prefix . 'nexus_stats_views_log';
        $start_time = microtime(true);

        $last_view = $wpdb->get_var($wpdb->prepare("
            SELECT id FROM $table_log
            WHERE post_id = %d AND visitor_id = %s AND view_datetime >= (CURRENT_TIMESTAMP - INTERVAL 1 HOUR)
            ORDER BY view_datetime DESC LIMIT 1
        ", $post_id, $visitor_id));

        if ($last_view) {
            $update_data = [];
            if ($scroll > 0) $update_data['scroll_depth'] = $scroll;
            if ($load_time > 0) $update_data['load_time_ms'] = $load_time;

            if (!empty($update_data)) {
                $wpdb->update($table_log, $update_data, ['id' => $last_view]);
            }
        }
        self::$query_time += (microtime(true) - $start_time) * 1000;
        self::save_query_time_average(self::$query_time);
    }

    // Helper : Track Outbound Link
    public static function track_outbound($url) {
        global $wpdb;
        $table = $wpdb->prefix . 'nexus_stats_outbound';
        $start_time = microtime(true);

        $url = substr($url, 0, 191);
        $wpdb->query($wpdb->prepare("
            INSERT INTO $table (target_url, click_count, last_clicked)
            VALUES (%s, 1, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE click_count = click_count + 1, last_clicked = CURRENT_TIMESTAMP
        ", $url));

        self::$query_time += (microtime(true) - $start_time) * 1000;
    }

    // Helper : Update read time
    public static function update_read_time($post_id, $visitor_id, $read_time) {
        global $wpdb;
        $table_log = $wpdb->prefix . 'nexus_stats_views_log';

        // Trouver la dernière vue pour ce visiteur et cet article dans la dernière heure
        $last_view = $wpdb->get_var($wpdb->prepare("
            SELECT id FROM $table_log
            WHERE post_id = %d AND visitor_id = %s AND view_datetime >= (CURRENT_TIMESTAMP - INTERVAL 1 HOUR)
            ORDER BY view_datetime DESC LIMIT 1
        ", $post_id, $visitor_id));

        if ($last_view) {
            $wpdb->update($table_log, ['read_time_seconds' => $read_time], ['id' => $last_view]);
        }
    }

    // Helper : Live ping
    public static function live_ping($visitor_id, $post_id = 0) {
        global $wpdb;
        $table = $wpdb->prefix . 'nexus_stats_live_users';
        $wpdb->query($wpdb->prepare("
            INSERT INTO $table (visitor_id, last_ping, post_id)
            VALUES (%s, CURRENT_TIMESTAMP, %d)
            ON DUPLICATE KEY UPDATE last_ping = CURRENT_TIMESTAMP, post_id = %d
        ", $visitor_id, $post_id, $post_id));
    }

    // Helper : Live exit
    public static function live_exit($visitor_id) {
        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'nexus_stats_live_users', ['visitor_id' => $visitor_id]);
    }

    // Nettoyage des live users
    public static function cleanup_live_users() {
        global $wpdb;
        $table = $wpdb->prefix . 'nexus_stats_live_users';
        // Le délai de nettoyage dépend du paramètre (ex: 60s -> nettoyer après 65s)
        $refresh_rate = get_option('nexus_stats_refresh_rate', 60);
        $cleanup_time = (int)$refresh_rate + 5;
        $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE last_ping < (CURRENT_TIMESTAMP - INTERVAL %d SECOND)", $cleanup_time));
    }

    // Compter les live users
    public static function get_live_count() {
        self::cleanup_live_users();
        global $wpdb;
        $table = $wpdb->prefix . 'nexus_stats_live_users';
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
    }
}
