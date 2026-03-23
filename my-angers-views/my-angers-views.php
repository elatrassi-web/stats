<?php
/*
Plugin Name: My Angers - Compteur de Vues
Description: Statistiques avancées : Compteur AJAX, Visiteurs uniques, Tableau de bord complet et Suivi des Visiteurs EN DIRECT (Version Sécurisée Anti-Crash).
Version: 5.1
Author: Mohamed El Atrassi
*/

if (!defined('ABSPATH')) exit; // Sécurité

/* ==========================================================================
 * 0. CACHER LES NOTIFICATIONS INDÉSIRABLES
 * ========================================================================== */
add_action('admin_head', 'my_angers_hide_annoying_notices');

if (!function_exists('my_angers_hide_annoying_notices')) {
    function my_angers_hide_annoying_notices() {
        echo '<style>
            .ajdg-notification.notice { display: none !important;
             }
             .notice.e-notice.e-notice--dismissible.e-notice--extended {
    display: none;
}
            /* Animation pulse pour le point rouge "En Direct" */
            @keyframes pulse-red { 0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); } 70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); } 100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); } }
            .live-dot { display: inline-block; width: 12px; height: 12px; background: #dc3545; border-radius: 50%; animation: pulse-red 2s infinite; margin-right: 8px; }
        </style>';
    }
}

/* ==========================================================================
 * 1. INSTALLATION DES TABLES SQL
 * ========================================================================== */
register_activation_hook(__FILE__, 'my_angers_views_create_tables');

if (!function_exists('my_angers_views_create_tables')) {
    function my_angers_views_create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Table 1 : Historique complet (Graphique)
        $table_log = $wpdb->prefix . 'my_angers_views_log';
        $sql1 = "CREATE TABLE $table_log (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            visitor_id varchar(64) NOT NULL DEFAULT '',
            view_datetime datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
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
            PRIMARY KEY  (visitor_id),
            KEY last_ping (last_ping)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql1);
        dbDelta($sql2);
    }
}

/* ==========================================================================
 * 2. CHARGEMENT DU SCRIPT AJAX (Visiteur & Live Tracking)
 * ========================================================================== */
add_action('wp_enqueue_scripts', 'my_angers_views_scripts');

if (!function_exists('my_angers_views_scripts')) {
    function my_angers_views_scripts() {
        if (is_single() || is_page()) {
            global $post;
            $ajax_url = admin_url('admin-ajax.php');

            $script = "
                document.addEventListener('DOMContentLoaded', function() {
                    var postID = '" . $post->ID . "';
                    var ajaxUrl = '" . $ajax_url . "';
                    var now = Date.now();

                    // Générer ou récupérer l'ID Visiteur
                    let vid = localStorage.getItem('my_angers_visitor_id');
                    if (!vid) {
                        vid = 'vid_' + Math.random().toString(36).substr(2, 9) + Date.now().toString(36);
                        localStorage.setItem('my_angers_visitor_id', vid);
                    }

                    // 1. TRACKING GLOBAL (1 vue max par minute)
                    if (!window.myAngersViewFired) {
                        window.myAngersViewFired = true;
                        var lastView = sessionStorage.getItem('my_angers_view_time_' + postID);
                        if (!lastView || (now - lastView >= 60000)) {
                            sessionStorage.setItem('my_angers_view_time_' + postID, now);
                            var formData = new FormData();
                            formData.append('action', 'my_angers_track_view');
                            formData.append('post_id', postID);
                            formData.append('visitor_id', vid);
                            fetch(ajaxUrl, { method: 'POST', body: formData });
                        }
                    }

                    // 2. TRACKING EN DIRECT (Heartbeat & Beacon)
                    function pingLiveServer() {
                        if (document.visibilityState === 'visible') {
                            var liveData = new FormData();
                            liveData.append('action', 'my_angers_live_ping');
                            liveData.append('visitor_id', vid);
                            fetch(ajaxUrl, { method: 'POST', body: liveData });
                        }
                    }
                    
                    pingLiveServer(); // Premier ping
                    // ✅ CORRECTION : Ping toutes les 60 secondes pour préserver le serveur o2switch
                    setInterval(pingLiveServer, 60000); 

                    // Si l'utilisateur quitte la page (ferme l'onglet), on le supprime instantanément
                    document.addEventListener('visibilitychange', function() {
                        if (document.visibilityState === 'hidden') {
                            var exitData = new FormData();
                            exitData.append('action', 'my_angers_live_exit');
                            exitData.append('visitor_id', vid);
                            navigator.sendBeacon(ajaxUrl, exitData);
                        }
                    });
                });
            ";
            wp_register_script('my-angers-views-js', '', [], '', true);
            wp_enqueue_script('my-angers-views-js');
            wp_add_inline_script('my-angers-views-js', $script);
        }
    }
}

/* ==========================================================================
 * 3. TRAITEMENT AJAX (Insertion Vues & Live)
 * ========================================================================== */
// Enregistrer une vue (Total & Unique)
add_action('wp_ajax_my_angers_track_view', 'my_angers_track_view_ajax');
add_action('wp_ajax_nopriv_my_angers_track_view', 'my_angers_track_view_ajax');
if (!function_exists('my_angers_track_view_ajax')) {
    function my_angers_track_view_ajax() {
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $visitor_id = isset($_POST['visitor_id']) ? sanitize_text_field($_POST['visitor_id']) : 'unknown';

        if ($post_id > 0) {
            global $wpdb;
            $wpdb->suppress_errors = true;
            $wpdb->insert($wpdb->prefix . 'my_angers_views_log', array(
                'post_id'       => $post_id,
                'visitor_id'    => $visitor_id,
                'view_datetime' => current_time('mysql')
            ));
            $current_views = (int) get_post_meta($post_id, 'my_angers_view_count', true);
            update_post_meta($post_id, 'my_angers_view_count', $current_views + 1);
        }
        wp_die();
    }
}

// Ping "Je suis en ligne"
add_action('wp_ajax_my_angers_live_ping', 'my_angers_live_ping_ajax');
add_action('wp_ajax_nopriv_my_angers_live_ping', 'my_angers_live_ping_ajax');
if (!function_exists('my_angers_live_ping_ajax')) {
    function my_angers_live_ping_ajax() {
        $visitor_id = isset($_POST['visitor_id']) ? sanitize_text_field($_POST['visitor_id']) : '';
        if ($visitor_id) {
            global $wpdb;
            $table = $wpdb->prefix . 'my_angers_live_users';
            $wpdb->query($wpdb->prepare("INSERT INTO $table (visitor_id, last_ping) VALUES (%s, CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE last_ping = CURRENT_TIMESTAMP", $visitor_id));
        }
        wp_die();
    }
}

// Exit "Je quitte la page" (Beacon)
add_action('wp_ajax_my_angers_live_exit', 'my_angers_live_exit_ajax');
add_action('wp_ajax_nopriv_my_angers_live_exit', 'my_angers_live_exit_ajax');
if (!function_exists('my_angers_live_exit_ajax')) {
    function my_angers_live_exit_ajax() {
        $visitor_id = isset($_POST['visitor_id']) ? sanitize_text_field($_POST['visitor_id']) : '';
        if ($visitor_id) {
            global $wpdb;
            $wpdb->delete($wpdb->prefix . 'my_angers_live_users', ['visitor_id' => $visitor_id]);
        }
        wp_die();
    }
}

/* ==========================================================================
 * 4. COLONNES WP ADMIN (Liste des articles/pages)
 * ========================================================================== */
add_filter('manage_posts_columns', 'my_angers_views_column', 99);
add_filter('manage_pages_columns', 'my_angers_views_column', 99);
if (!function_exists('my_angers_views_column')) {
    function my_angers_views_column($columns) {
        if (is_array($columns) && isset($columns['post_views'])) unset($columns['post_views']);
        $columns['my_angers_views_col'] = '👁️ Vues';
        return $columns;
    }
}
add_action('manage_posts_custom_column', 'my_angers_views_column_data', 10, 2);
add_action('manage_pages_custom_column', 'my_angers_views_column_data', 10, 2);
if (!function_exists('my_angers_views_column_data')) {
    function my_angers_views_column_data($column, $post_id) {
        if ($column === 'my_angers_views_col') {
            $views = (int) get_post_meta($post_id, 'my_angers_view_count', true);
            echo '<strong>' . number_format($views, 0, ',', ' ') . '</strong>';
        }
    }
}
add_filter('manage_edit-post_sortable_columns', 'my_angers_views_sortable_column');
add_filter('manage_edit-page_sortable_columns', 'my_angers_views_sortable_column');
if (!function_exists('my_angers_views_sortable_column')) { function my_angers_views_sortable_column($columns) { $columns['my_angers_views_col'] = 'my_angers_views_col'; return $columns; } }
add_action('pre_get_posts', 'my_angers_views_orderby');
if (!function_exists('my_angers_views_orderby')) {
    function my_angers_views_orderby($query) {
        if (!is_admin() || !$query->is_main_query()) return;
        if ('my_angers_views_col' === $query->get('orderby')) {
            $query->set('meta_key', 'my_angers_view_count');
            $query->set('orderby', 'meta_value_num');
        }
    }
}

/* ==========================================================================
 * 5. CRÉATION DE LA PAGE D'ADMINISTRATION
 * ========================================================================== */
add_action('admin_menu', 'my_angers_views_add_admin_menu');
if (!function_exists('my_angers_views_add_admin_menu')) {
    function my_angers_views_add_admin_menu() {
        add_menu_page('Statistiques My Angers', 'Stats Vues', 'manage_options', 'my-angers-stats', 'my_angers_views_admin_page', 'dashicons-chart-bar', 6);
    }
}

add_action('admin_enqueue_scripts', 'my_angers_views_admin_scripts');
if (!function_exists('my_angers_views_admin_scripts')) {
    function my_angers_views_admin_scripts($hook) {
        if ($hook != 'toplevel_page_my-angers-stats') return;
        wp_enqueue_style('bootstrap-5', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css', [], '5.3.2');
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], null, true);
    }
}

if (!function_exists('my_angers_views_admin_page')) {
    function my_angers_views_admin_page() {
        ?>
        <div class="wrap" style="margin-top: 20px;">
            <div class="container-fluid px-0">

                <div class="mb-4">
                    <h1 class="display-6 fw-normal text-dark m-0">Résumé</h1>
                </div>

                <div class="row mb-4">
                    <div class="col-md-4 mb-3">
                        <div class="card shadow-sm border-0 rounded-3 h-100">
                            <div class="card-body p-4">
                                <h6 class="text-muted fw-bold text-uppercase mb-2"><i class="dashicons dashicons-visibility align-middle"></i> Vues Totales</h6>
                                <h2 class="display-4 fw-bold text-dark m-0" id="highlight-views">--</h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card shadow-sm border-0 rounded-3 h-100">
                            <div class="card-body p-4">
                                <h6 class="text-muted fw-bold text-uppercase mb-2"><i class="dashicons dashicons-groups align-middle"></i> Visiteurs Uniques</h6>
                                <h2 class="display-4 fw-bold text-dark m-0" id="highlight-visitors">--</h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card shadow-sm border-0 rounded-3 h-100" style="background: linear-gradient(135deg, #fff, #fff5f5);">
                            <div class="card-body p-4">
                                <h6 class="text-danger fw-bold text-uppercase mb-2"><span class="live-dot"></span> Visiteurs En Direct</h6>
                                <h2 class="display-4 fw-bold text-danger m-0" id="highlight-live">0</h2>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0 rounded-3 mb-4 w-100" style="max-width: 100%;">
                    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                        <h5 class="m-0 fw-bold text-dark"><i class="dashicons dashicons-chart-bar align-middle"></i> Évolution des vues</h5>
                        <select id="my_angers_time_filter" class="form-select w-auto shadow-sm border-0 fw-bold bg-light">
                            <option value="today">Aujourd'hui</option>
                            <option value="30min">30 Dernières Minutes</option>
                            <option value="yesterday">Hier</option>
                            <option value="7days" selected>7 Derniers Jours</option>
                            <option value="30days">30 Derniers Jours</option>
                            <option value="last_month">Mois Précédent</option>
                        </select>
                    </div>
                    <div class="card-body p-4 w-100">
                        <div style="position: relative; height: 350px; width: 100%;">
                            <canvas id="myAngersChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="row w-100 m-0">
                    <div class="col-md-6 mb-4 ps-0 pe-md-2">
                        <div class="card shadow-sm border-0 rounded-3 h-100">
                            <div class="card-header bg-white border-bottom fw-bold py-3 fs-5">Top Articles</div>
                            <ul class="list-group list-group-flush" id="my_angers_top_posts">
                                <li class="list-group-item text-center py-4 text-muted">Chargement...</li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-6 mb-4 pe-0 ps-md-2">
                        <div class="card shadow-sm border-0 rounded-3 h-100">
                            <div class="card-header bg-white border-bottom fw-bold py-3 fs-5">Top Pages</div>
                            <ul class="list-group list-group-flush" id="my_angers_top_pages">
                                <li class="list-group-item text-center py-4 text-muted">Chargement...</li>
                            </ul>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <script>
            document.addEventListener("DOMContentLoaded", function() {
                var canvas = document.getElementById('myAngersChart');
                if(!canvas) return;

                var ctx = canvas.getContext('2d');
                var myChart = null;
                var filterSelect = document.getElementById('my_angers_time_filter');

                // 1. Charger les stats globales (Graphe + Tops)
                function loadStats() {
                    var timeRange = filterSelect.value;
                    var formData = new FormData();
                    formData.append('action', 'my_angers_get_dashboard_data');
                    formData.append('time_range', timeRange);
                    formData.append('security', '<?php echo wp_create_nonce("my_angers_dashboard_nonce"); ?>');

                    fetch('<?php echo admin_url("admin-ajax.php"); ?>', { method: 'POST', body: formData })
                        .then(response => response.json())
                        .then(data => {
                            if(data.success) {
                                document.getElementById('highlight-views').innerText = data.data.total_views.toLocaleString('fr-FR');
                                document.getElementById('highlight-visitors').innerText = data.data.total_visitors.toLocaleString('fr-FR');

                                if(myChart) myChart.destroy();
                                myChart = new Chart(ctx, {
                                    type: 'bar',
                                    data: {
                                        labels: data.data.chart_labels,
                                        datasets: [{
                                            label: 'Vues',
                                            data: data.data.chart_values,
                                            backgroundColor: '#00a02b',
                                            borderRadius: 3,
                                            categoryPercentage: 0.9,
                                            barPercentage: 1.0
                                        }]
                                    },
                                    options: {
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        scales: {
                                            y: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 }, grid: { borderDash: [5, 5] } },
                                            x: { grid: { display: false } }
                                        },
                                        plugins: { legend: { display: false }, tooltip: { backgroundColor: '#333', padding: 10 } }
                                    }
                                });

                                function createListHtml(items) {
                                    if(items.length === 0) return '<li class="list-group-item text-center text-muted py-3">Aucune donnée.</li>';
                                    let html = '';
                                    items.forEach(function(item) {
                                        html += `<li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                            <a href="${item.edit_link}" class="text-decoration-none text-dark fw-medium" target="_blank">${item.title}</a>
                                            <span class="badge bg-light text-dark border px-3 py-2 rounded-pill">${item.views.toLocaleString('fr-FR')}</span>
                                         </li>`;
                                    });
                                    return html;
                                }

                                document.getElementById('my_angers_top_posts').innerHTML = createListHtml(data.data.top_posts);
                                document.getElementById('my_angers_top_pages').innerHTML = createListHtml(data.data.top_pages);
                            }
                        });
                }

                // 2. Mettre à jour UNIQUEMENT le compteur "En Direct"
                function fetchLiveCount() {
                    var formData = new FormData();
                    formData.append('action', 'my_angers_get_live_count');
                    fetch('<?php echo admin_url("admin-ajax.php"); ?>', { method: 'POST', body: formData })
                        .then(response => response.json())
                        .then(data => {
                            if(data.success) {
                                document.getElementById('highlight-live').innerText = data.data;
                            }
                        });
                }

                filterSelect.addEventListener('change', loadStats);
                loadStats();
                fetchLiveCount();
                // ✅ CORRECTION : Actualisation du tableau de bord toutes les 60 secondes au lieu de 5
                setInterval(fetchLiveCount, 60000);
            });
        </script>
        <?php
    }
}

/* ==========================================================================
 * 6. FONCTIONS GLOBALES
 * ========================================================================== */
if (!function_exists('my_angers_get_top_content')) {
    function my_angers_get_top_content($wpdb, $table_name, $start_date, $end_date, $post_type) {
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT p.post_title, p.ID, COUNT(v.id) as views
            FROM $table_name v
            INNER JOIN {$wpdb->posts} p ON v.post_id = p.ID
            WHERE p.post_type = %s AND p.post_status = 'publish'
            AND v.view_datetime >= %s AND v.view_datetime <= %s
            GROUP BY p.ID
            ORDER BY views DESC
            LIMIT 10
        ", $post_type, $start_date, $end_date));

        $output = [];
        foreach ($results as $row) {
            $output[] = [
                'title' => wp_trim_words($row->post_title, 8, '...'),
                'views' => $row->views,
                'edit_link' => get_edit_post_link($row->ID, 'raw')
            ];
        }
        return $output;
    }
}

/* ==========================================================================
 * 7. REQUÊTES AJAX POUR LE TABLEAU DE BORD
 * ========================================================================== */
// Les stats globales
add_action('wp_ajax_my_angers_get_dashboard_data', 'my_angers_get_dashboard_data_ajax');
if (!function_exists('my_angers_get_dashboard_data_ajax')) {
    function my_angers_get_dashboard_data_ajax() {
        check_ajax_referer('my_angers_dashboard_nonce', 'security');

        global $wpdb;
        $table_name = $wpdb->prefix . 'my_angers_views_log';
        $time_range = isset($_POST['time_range']) ? sanitize_text_field($_POST['time_range']) : 'today';

        $now = current_time('timestamp');
        $start_date = '';
        $end_date = date('Y-m-d H:i:s', $now);
        $group_format = '';

        switch ($time_range) {
            case '30min':
                $start_date = date('Y-m-d H:i:s', strtotime('-30 minutes', $now));
                $group_format = '%H:%i';
                break;
            case 'yesterday':
                $start_date = date('Y-m-d 00:00:00', strtotime('yesterday', $now));
                $end_date = date('Y-m-d 23:59:59', strtotime('yesterday', $now));
                $group_format = '%H:00';
                break;
            case '7days':
                $start_date = date('Y-m-d 00:00:00', strtotime('-6 days', $now));
                $group_format = '%d/%m';
                break;
            case '30days':
                $start_date = date('Y-m-d 00:00:00', strtotime('-29 days', $now));
                $group_format = '%d/%m';
                break;
            case 'last_month':
                $start_date = date('Y-m-01 00:00:00', strtotime('first day of last month', $now));
                $end_date = date('Y-m-t 23:59:59', strtotime('last day of last month', $now));
                $group_format = '%d/%m';
                break;
            case 'today':
            default:
                $start_date = date('Y-m-d 00:00:00', $now);
                $group_format = '%H:00';
                break;
        }

        $wpdb->suppress_errors = true;

        $totals = $wpdb->get_row($wpdb->prepare("
            SELECT COUNT(id) as total_views, COUNT(DISTINCT visitor_id) as total_visitors
            FROM $table_name
            WHERE view_datetime >= %s AND view_datetime <= %s
        ", $start_date, $end_date));

        $chart_results = $wpdb->get_results($wpdb->prepare("
            SELECT DATE_FORMAT(view_datetime, %s) as time_label, COUNT(id) as view_count
            FROM $table_name
            WHERE view_datetime >= %s AND view_datetime <= %s
            GROUP BY time_label
            ORDER BY view_datetime ASC
        ", $group_format, $start_date, $end_date));

        $chart_labels = [];
        $chart_values = [];
        if ($chart_results) {
            foreach ($chart_results as $row) {
                $chart_labels[] = $row->time_label;
                $chart_values[] = $row->view_count;
            }
        }

        wp_send_json_success([
            'total_views'    => ($totals && $totals->total_views) ? (int)$totals->total_views : 0,
            'total_visitors' => ($totals && $totals->total_visitors) ? (int)$totals->total_visitors : 0,
            'chart_labels'   => $chart_labels,
            'chart_values'   => $chart_values,
            'top_posts'      => my_angers_get_top_content($wpdb, $table_name, $start_date, $end_date, 'post'),
            'top_pages'      => my_angers_get_top_content($wpdb, $table_name, $start_date, $end_date, 'page')
        ]);
    }
}

// Obtenir le nombre En Direct (Nettoyage + Comptage)
add_action('wp_ajax_my_angers_get_live_count', 'my_angers_get_live_count_ajax');
if (!function_exists('my_angers_get_live_count_ajax')) {
    function my_angers_get_live_count_ajax() {
        global $wpdb;
        $table = $wpdb->prefix . 'my_angers_live_users';

        // 1. Nettoyer les fantômes (ceux qui ont perdu la connexion internet sans envoyer de balise) depuis 65 secondes (pour correspondre au ping de 60s)
        $wpdb->query("DELETE FROM $table WHERE last_ping < (CURRENT_TIMESTAMP - INTERVAL 65 SECOND)");

        // 2. Compter les restants
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");

        wp_send_json_success((int)$count);
    }
}