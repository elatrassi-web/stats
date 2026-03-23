<?php
/**
 * Plugin Name: Nexus Stats
 * Description: Statistiques avancées : Compteur AJAX, Visiteurs uniques, Tableau de bord complet et Suivi des Visiteurs EN DIRECT (Version Sécurisée Anti-Crash). Refonte 2026.
 * Version: 1.0.0
 * Author: Mohamed El Atrassi
 * Author URI: https://www.linkedin.com/in/elatrassi/
 * Text Domain: nexus-stats
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) exit; // Sécurité

define('NEXUS_STATS_VIEWS_VERSION', '1.0.0');
define('NEXUS_STATS_VIEWS_DIR', plugin_dir_path(__FILE__));
define('NEXUS_STATS_VIEWS_URL', plugin_dir_url(__FILE__));

// Charger les classes
require_once NEXUS_STATS_VIEWS_DIR . 'includes/class-nexus-stats-db.php';
require_once NEXUS_STATS_VIEWS_DIR . 'includes/class-nexus-stats-rest.php';
require_once NEXUS_STATS_VIEWS_DIR . 'includes/class-nexus-stats-hooks.php';
require_once NEXUS_STATS_VIEWS_DIR . 'admin/class-nexus-stats-admin.php';
require_once NEXUS_STATS_VIEWS_DIR . 'public/class-nexus-stats-public.php';

// Initialisation
function nexus_stats_views_init() {
    load_plugin_textdomain('nexus-stats', false, dirname(plugin_basename(__FILE__)) . '/languages');
    Nexus_Stats_DB::init();
    Nexus_Stats_REST::init();
    Nexus_Stats_Hooks::init();
    Nexus_Stats_Admin::init();
    Nexus_Stats_Public::init();
}
add_action('plugins_loaded', 'nexus_stats_views_init');

// Activation (Création des tables & Cron)
register_activation_hook(__FILE__, 'nexus_stats_views_activate');
function nexus_stats_views_activate() {
    Nexus_Stats_DB::create_tables();
    if (!wp_next_scheduled('nexus_stats_downtime_check')) {
        wp_schedule_event(time(), 'hourly', 'nexus_stats_downtime_check');
    }
}

// Deactivation (Cron)
register_deactivation_hook(__FILE__, 'nexus_stats_views_deactivate');
function nexus_stats_views_deactivate() {
    wp_clear_scheduled_hook('nexus_stats_downtime_check');
}

// Cacher les notifications indésirables (héritage de l'ancienne version)
add_action('admin_head', 'nexus_stats_hide_annoying_notices');
function nexus_stats_hide_annoying_notices() {
    echo '<style>
        .ajdg-notification.notice { display: none !important; }
        .notice.e-notice.e-notice--dismissible.e-notice--extended { display: none; }
    </style>';
}
