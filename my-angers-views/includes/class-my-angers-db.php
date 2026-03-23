<?php
if (!defined('ABSPATH')) exit; // Sécurité

class My_Angers_DB {
    public static function init() {
        // Logique d'initialisation de la DB si nécessaire (ex: cron)
    }

    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Table 1 : Historique complet (Graphique)
        $table_log = $wpdb->prefix . 'my_angers_views_log';
        $sql1 = "CREATE TABLE $table_log (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            visitor_id varchar(64) NOT NULL DEFAULT '',
            view_datetime datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            device_type varchar(10) DEFAULT 'desktop',
            read_time_seconds int(11) DEFAULT 0,
            PRIMARY KEY  (id),
            KEY post_id (post_id),
            KEY visitor_id (visitor_id),
            KEY view_datetime (view_datetime)
        ) $charset_collate;";

        // Table 2 : Visiteurs en Direct (S'auto-nettoie)
        $table_live = $wpdb->prefix . 'my_angers_live_users';
        $sql2 = "CREATE TABLE $table_live (
            visitor_id varchar(64) NOT NULL,
            last_ping datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            post_id bigint(20) DEFAULT 0,
            PRIMARY KEY  (visitor_id),
            KEY last_ping (last_ping)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql1);
        dbDelta($sql2);
    }

    // Helper : Track a view
    public static function track_view($post_id, $visitor_id, $device_type = 'desktop') {
        global $wpdb;
        $table_log = $wpdb->prefix . 'my_angers_views_log';

        $wpdb->suppress_errors = true;
        $wpdb->insert($table_log, array(
            'post_id'       => $post_id,
            'visitor_id'    => $visitor_id,
            'device_type'   => $device_type,
            'view_datetime' => current_time('mysql')
        ));

        $current_views = (int) get_post_meta($post_id, 'my_angers_view_count', true);
        update_post_meta($post_id, 'my_angers_view_count', $current_views + 1);
        return true;
    }

    // Helper : Update read time
    public static function update_read_time($post_id, $visitor_id, $read_time) {
        global $wpdb;
        $table_log = $wpdb->prefix . 'my_angers_views_log';

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
        $table = $wpdb->prefix . 'my_angers_live_users';
        $wpdb->query($wpdb->prepare("
            INSERT INTO $table (visitor_id, last_ping, post_id)
            VALUES (%s, CURRENT_TIMESTAMP, %d)
            ON DUPLICATE KEY UPDATE last_ping = CURRENT_TIMESTAMP, post_id = %d
        ", $visitor_id, $post_id, $post_id));
    }

    // Helper : Live exit
    public static function live_exit($visitor_id) {
        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'my_angers_live_users', ['visitor_id' => $visitor_id]);
    }

    // Nettoyage des live users
    public static function cleanup_live_users() {
        global $wpdb;
        $table = $wpdb->prefix . 'my_angers_live_users';
        // Le délai de nettoyage dépend du paramètre (ex: 60s -> nettoyer après 65s)
        $refresh_rate = get_option('my_angers_refresh_rate', 60);
        $cleanup_time = (int)$refresh_rate + 5;
        $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE last_ping < (CURRENT_TIMESTAMP - INTERVAL %d SECOND)", $cleanup_time));
    }

    // Compter les live users
    public static function get_live_count() {
        self::cleanup_live_users();
        global $wpdb;
        $table = $wpdb->prefix . 'my_angers_live_users';
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
    }
}
