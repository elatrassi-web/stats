<?php
/**
 * Plugin Name: Nexus Stats
 * Plugin URI: https://www.linkedin.com/in/elatrassi/
 * Description: Statistiques avancées : Compteur AJAX, Visiteurs uniques, Tableau de bord complet et Suivi des Visiteurs EN DIRECT (Version Sécurisée Anti-Crash). Refonte 2026.
 * Version: 6.0
 * Author: Mohamed El Atrassi
 * Author URI: https://www.linkedin.com/in/elatrassi/
 * Text Domain: my-angers-views
 */

if (!defined('ABSPATH')) exit; // Sécurité

define('MY_ANGERS_VIEWS_VERSION', '6.0');
define('MY_ANGERS_VIEWS_DIR', plugin_dir_path(__FILE__));
define('MY_ANGERS_VIEWS_URL', plugin_dir_url(__FILE__));

// Charger les classes
require_once MY_ANGERS_VIEWS_DIR . 'includes/class-my-angers-db.php';
require_once MY_ANGERS_VIEWS_DIR . 'includes/class-my-angers-rest.php';
require_once MY_ANGERS_VIEWS_DIR . 'admin/class-my-angers-admin.php';
require_once MY_ANGERS_VIEWS_DIR . 'public/class-my-angers-public.php';

// Initialisation
function my_angers_views_init() {
    My_Angers_DB::init();
    My_Angers_REST::init();
    My_Angers_Admin::init();
    My_Angers_Public::init();
}
add_action('plugins_loaded', 'my_angers_views_init');

// Activation (Création des tables)
register_activation_hook(__FILE__, ['My_Angers_DB', 'create_tables']);

// Cacher les notifications indésirables (héritage de l'ancienne version)
add_action('admin_head', 'my_angers_hide_annoying_notices');
function my_angers_hide_annoying_notices() {
    echo '<style>
        .ajdg-notification.notice { display: none !important; }
        .notice.e-notice.e-notice--dismissible.e-notice--extended { display: none; }
    </style>';
}
