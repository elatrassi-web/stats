<?php
if (!defined('ABSPATH')) exit;

class Nexus_Stats_Admin {
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);

        // Settings registration
        add_action('admin_init', [__CLASS__, 'register_settings']);

        // Columns
        add_filter('manage_posts_columns', [__CLASS__, 'add_views_column'], 99);
        add_filter('manage_pages_columns', [__CLASS__, 'add_views_column'], 99);
        add_action('manage_posts_custom_column', [__CLASS__, 'views_column_data'], 10, 2);
        add_action('manage_pages_custom_column', [__CLASS__, 'views_column_data'], 10, 2);
        add_filter('manage_edit-post_sortable_columns', [__CLASS__, 'sortable_views_column']);
        add_filter('manage_edit-page_sortable_columns', [__CLASS__, 'sortable_views_column']);
        add_action('pre_get_posts', [__CLASS__, 'views_orderby']);

        // Admin bar
        add_action('admin_bar_menu', [__CLASS__, 'add_admin_bar_node'], 999);

        // Dashboard widget
        add_action('wp_dashboard_setup', [__CLASS__, 'add_dashboard_widget']);

        // Client-Ready Presentation Mode (Shared URL)
        add_action('init', [__CLASS__, 'handle_shared_dashboard']);
    }

    public static function add_admin_menu() {
        add_menu_page('Nexus Stats', 'Stats Vues', 'manage_options', 'nexus-stats-stats', [__CLASS__, 'render_admin_page'], 'dashicons-chart-bar', 6);
        add_submenu_page('nexus-stats-stats', 'Réglages Nexus Stats', 'Réglages', 'manage_options', 'nexus-stats-settings', [__CLASS__, 'render_settings_page']);
    }

    public static function enqueue_scripts($hook) {
        if ($hook != 'toplevel_page_nexus-stats-stats' && $hook != 'index.php') return;

        wp_enqueue_style('nexus-stats-admin-css', NEXUS_STATS_VIEWS_URL . 'assets/css/nexus-stats-admin.css', [], NEXUS_STATS_VIEWS_VERSION);
        wp_enqueue_script('chart-js', NEXUS_STATS_VIEWS_URL . 'assets/vendor/chart.min.js', [], NEXUS_STATS_VIEWS_VERSION, true);
        wp_enqueue_script('chartjs-plugin-annotation', NEXUS_STATS_VIEWS_URL . 'assets/vendor/chartjs-plugin-annotation.min.js', ['chart-js'], NEXUS_STATS_VIEWS_VERSION, true);
        wp_enqueue_script('html2pdf-js', NEXUS_STATS_VIEWS_URL . 'assets/vendor/html2pdf.bundle.min.js', [], NEXUS_STATS_VIEWS_VERSION, true);

        if ($hook == 'toplevel_page_nexus-stats-stats') {
            wp_enqueue_style('jsvectormap-css', NEXUS_STATS_VIEWS_URL . 'assets/vendor/jsvectormap.min.css', [], NEXUS_STATS_VIEWS_VERSION);
            wp_enqueue_script('jsvectormap-js', NEXUS_STATS_VIEWS_URL . 'assets/vendor/jsvectormap.min.js', [], NEXUS_STATS_VIEWS_VERSION, true);
            wp_enqueue_script('jsvectormap-world', NEXUS_STATS_VIEWS_URL . 'assets/vendor/world.js', ['jsvectormap-js'], NEXUS_STATS_VIEWS_VERSION, true);
            wp_enqueue_script('nexus-stats-admin-js', NEXUS_STATS_VIEWS_URL . 'assets/js/nexus-stats-admin.js', ['chart-js', 'chartjs-plugin-annotation', 'html2pdf-js', 'jsvectormap-js', 'jsvectormap-world'], NEXUS_STATS_VIEWS_VERSION, true);

            $refresh_rate = get_option('nexus_stats_refresh_rate', 60);
            $theme = get_option('nexus_stats_theme', 'dark');

            wp_localize_script('nexus-stats-admin-js', 'nexusStatsAdminData', [
                'restUrl' => esc_url_raw(rest_url('nexus-stats/v1')),
                'nonce' => wp_create_nonce('wp_rest'),
                'refreshRate' => (int)$refresh_rate * 1000,
                'theme' => sanitize_text_field($theme),
                'i18n' => [
                    'views' => __('Vues', 'nexus-stats'),
                    'prev_views' => __('Vues (Précédent)', 'nexus-stats'),
                    'mobile' => __('Mobile', 'nexus-stats'),
                    'desktop' => __('Desktop', 'nexus-stats'),
                    'no_data' => __('Aucune donnée', 'nexus-stats'),
                    'empty_period' => __('Aucune donnée pour cette période.', 'nexus-stats'),
                    'evergreen_title' => __('Evergreen (Stable/Croissant)', 'nexus-stats'),
                    'dying_title' => __('Mourant (En baisse)', 'nexus-stats'),
                    'total_views' => __('Vues Totales', 'nexus-stats'),
                    'avg_read_time' => __('Temps de lecture moyen', 'nexus-stats'),
                    'avg_source_time' => __('Temps de lecture moyen généré par cette source', 'nexus-stats'),
                    'views_brought' => __('Vues apportées', 'nexus-stats'),
                    'fill_date_text' => __('Veuillez remplir la date et le texte.', 'nexus-stats'),
                    'pdf_name' => __('Rapport_Nexus_Stats.pdf', 'nexus-stats'),
                    'confirm_ghost' => __('Voulez-vous vraiment supprimer le trafic fantôme (bots à 0 seconde) ?', 'nexus-stats'),
                    /* translators: %s: number of ghost views */
                    'cleanup_done' => __('Nettoyage terminé : %s vues fantômes supprimées.', 'nexus-stats'),
                ]
            ]);
        }
    }

    public static function register_settings() {
        register_setting('nexus_stats_settings_group', 'nexus_stats_refresh_rate', [
            'type' => 'integer',
            'default' => 60,
            'sanitize_callback' => 'absint'
        ]);
        register_setting('nexus_stats_settings_group', 'nexus_stats_eco_mode', [
            'type' => 'string',
            'default' => 'no',
            'sanitize_callback' => 'sanitize_text_field'
        ]);
        register_setting('nexus_stats_settings_group', 'nexus_stats_theme', [
            'type' => 'string',
            'default' => 'dark',
            'sanitize_callback' => 'sanitize_text_field'
        ]);
        register_setting('nexus_stats_settings_group', 'nexus_stats_monthly_goal', [
            'type' => 'integer',
            'default' => 10000,
            'sanitize_callback' => 'absint'
        ]);
        register_setting('nexus_stats_settings_group', 'nexus_stats_gdpr_strict', [
            'type' => 'string',
            'default' => 'no',
            'sanitize_callback' => 'sanitize_text_field'
        ]);
        register_setting('nexus_stats_settings_group', 'nexus_stats_share_token', [
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field'
        ]);
        register_setting('nexus_stats_settings_group', 'nexus_stats_track_scroll', [
            'type' => 'string',
            'default' => 'yes',
            'sanitize_callback' => 'sanitize_text_field'
        ]);
        register_setting('nexus_stats_settings_group', 'nexus_stats_track_outbound', [
            'type' => 'string',
            'default' => 'yes',
            'sanitize_callback' => 'sanitize_text_field'
        ]);
        register_setting('nexus_stats_settings_group', 'nexus_stats_track_404', [
            'type' => 'string',
            'default' => 'yes',
            'sanitize_callback' => 'sanitize_text_field'
        ]);
        register_setting('nexus_stats_settings_group', 'nexus_stats_woo_sync', [
            'type' => 'string',
            'default' => 'yes',
            'sanitize_callback' => 'sanitize_text_field'
        ]);
        register_setting('nexus_stats_settings_group', 'nexus_stats_downtime_alerts', [
            'type' => 'string',
            'default' => 'yes',
            'sanitize_callback' => 'sanitize_text_field'
        ]);
        register_setting('nexus_stats_settings_group', 'nexus_stats_narrative', [
            'type' => 'string',
            'default' => 'yes',
            'sanitize_callback' => 'sanitize_text_field'
        ]);
        register_setting('nexus_stats_settings_group', 'nexus_stats_health', [
            'type' => 'string',
            'default' => 'yes',
            'sanitize_callback' => 'sanitize_text_field'
        ]);
    }

    // --- Colonnes ---
    public static function add_views_column($columns) {
        if (is_array($columns) && isset($columns['post_views'])) unset($columns['post_views']);
        $columns['nexus_stats_views_col'] = '<span class="dashicons dashicons-visibility" title="' . esc_attr__('Vues', 'nexus-stats') . '"></span> ' . esc_html__('Vues', 'nexus-stats');
        return $columns;
    }

    public static function views_column_data($column, $post_id) {
        if ($column === 'nexus_stats_views_col') {
            $views = (int) get_post_meta($post_id, 'nexus_stats_view_count', true);
            // Mode Focus: Highlight if > threshold (e.g., 500)
            $class = ($views > 500) ? 'nexus-stats-viral' : '';
            echo '<span class="' . esc_attr($class) . '"><strong>' . esc_html(number_format($views, 0, ',', ' ')) . '</strong></span>';
        }
    }

    public static function sortable_views_column($columns) {
        $columns['nexus_stats_views_col'] = 'nexus_stats_views_col';
        return $columns;
    }

    public static function views_orderby($query) {
        if (!is_admin() || !$query->is_main_query()) return;
        if ('nexus_stats_views_col' === $query->get('orderby')) {
            $query->set('meta_key', 'nexus_stats_view_count');
            $query->set('orderby', 'meta_value_num');
        }
    }

    // --- Admin Bar ---
    public static function add_admin_bar_node($wp_admin_bar) {
        if (!current_user_can('manage_options')) return;

        $live_count = Nexus_Stats_DB::get_live_count();

        $args = array(
            'id'    => 'nexus_stats_live_stats',
            'title' => '<span class="ab-icon dashicons dashicons-chart-line"></span><span class="ab-label" style="color:#00ff88;font-weight:bold;"><span class="live-dot-mini" style="display:inline-block;width:6px;height:6px;background:#00ff88;border-radius:50%;margin-right:4px;animation:pulse-green 2s infinite;"></span><span id="nexus_stats_topbar_live_count">' . esc_html($live_count) . '</span> ' . esc_html__('Live', 'nexus-stats') . '</span>',
            'href'  => esc_url(admin_url('admin.php?page=nexus-stats-stats')),
            'meta'  => array(
                'class' => 'nexus-stats-admin-bar-node',
            )
        );
        $wp_admin_bar->add_node($args);

        // Add CSS for admin bar pulse
        add_action('admin_head', function() {
            echo '<style>@keyframes pulse-green { 0% { box-shadow: 0 0 0 0 rgba(0, 255, 136, 0.7); } 70% { box-shadow: 0 0 0 5px rgba(0, 255, 136, 0); } 100% { box-shadow: 0 0 0 0 rgba(0, 255, 136, 0); } }</style>';
        });
        add_action('wp_head', function() {
            echo '<style>@keyframes pulse-green { 0% { box-shadow: 0 0 0 0 rgba(0, 255, 136, 0.7); } 70% { box-shadow: 0 0 0 5px rgba(0, 255, 136, 0); } 100% { box-shadow: 0 0 0 0 rgba(0, 255, 136, 0); } }</style>';
        });
    }

    // --- Dashboard Widget ---
    public static function add_dashboard_widget() {
        if (current_user_can('manage_options')) {
            wp_add_dashboard_widget('nexus_stats_dashboard_widget', esc_html__('Stats Vues (24h)', 'nexus-stats'), [__CLASS__, 'render_dashboard_widget']);
        }
    }

    public static function render_dashboard_widget() {
        // Un placeholder pour le script JS
        echo '<div id="nexus-stats-sparkline-container" style="height: 100px; width: 100%; position: relative;"><canvas id="nexusStatsSparkline"></canvas></div>';
        echo '<p style="text-align:center;margin-top:10px;"><a href="' . esc_url(admin_url('admin.php?page=nexus-stats-stats')) . '">' . esc_html__('Voir le tableau de bord complet', 'nexus-stats') . '</a></p>';

        // Script inline minimal pour le sparkline
        ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var canvas = document.getElementById('nexusStatsSparkline');
                if(!canvas) return;
                var ctx = canvas.getContext('2d');

                fetch('<?php echo esc_url_raw(rest_url('nexus-stats/v1/stats/dashboard?time_range=yesterday')); ?>', {
                    headers: { 'X-WP-Nonce': '<?php echo esc_attr(wp_create_nonce("wp_rest")); ?>' }
                })
                .then(res => res.json())
                .then(response => {
                    if (response.success) {
                        new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: response.data.chart_labels,
                                datasets: [{
                                    data: response.data.chart_values,
                                    borderColor: '#00ff88',
                                    backgroundColor: 'rgba(0, 255, 136, 0.1)',
                                    borderWidth: 2,
                                    fill: true,
                                    tension: 0.4,
                                    pointRadius: 0
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: { legend: { display: false } },
                                scales: { x: { display: false }, y: { display: false } }
                            }
                        });
                    }
                });
            });
        </script>
        <?php
    }

    // --- Client-Ready Shared Dashboard ---
    public static function handle_shared_dashboard() {
        if (isset($_GET['nexus_stats_share'])) {
            $token = sanitize_text_field(wp_unslash($_GET['nexus_stats_share']));
            $saved_token = get_option('nexus_stats_share_token', '');

            if (!empty($saved_token) && $token === $saved_token) {
                // Generate a temporary nonce for the REST API for this shared view
                // Since this user isn't logged in, they normally couldn't hit the admin endpoints.
                // We'll bypass the REST permission check if they send this specific token as a header.
                // However, the cleanest way without altering the REST API class is to temporarily set the current user to an admin
                // OR modify the REST class to accept the token. For security, we'll modify the REST class check.

                // Load dependencies
                wp_enqueue_style('nexus-stats-admin-css', NEXUS_STATS_VIEWS_URL . 'assets/css/nexus-stats-admin.css', [], NEXUS_STATS_VIEWS_VERSION);
                wp_enqueue_script('chart-js', NEXUS_STATS_VIEWS_URL . 'assets/vendor/chart.min.js', [], NEXUS_STATS_VIEWS_VERSION, true);
                wp_enqueue_script('chartjs-plugin-annotation', NEXUS_STATS_VIEWS_URL . 'assets/vendor/chartjs-plugin-annotation.min.js', ['chart-js'], NEXUS_STATS_VIEWS_VERSION, true);
                wp_enqueue_script('html2pdf-js', NEXUS_STATS_VIEWS_URL . 'assets/vendor/html2pdf.bundle.min.js', [], NEXUS_STATS_VIEWS_VERSION, true);
                wp_enqueue_style('jsvectormap-css', NEXUS_STATS_VIEWS_URL . 'assets/vendor/jsvectormap.min.css', [], NEXUS_STATS_VIEWS_VERSION);
                wp_enqueue_script('jsvectormap-js', NEXUS_STATS_VIEWS_URL . 'assets/vendor/jsvectormap.min.js', [], NEXUS_STATS_VIEWS_VERSION, true);
                wp_enqueue_script('jsvectormap-world', NEXUS_STATS_VIEWS_URL . 'assets/vendor/world.js', ['jsvectormap-js'], NEXUS_STATS_VIEWS_VERSION, true);
                wp_enqueue_script('nexus-stats-admin-js', NEXUS_STATS_VIEWS_URL . 'assets/js/nexus-stats-admin.js', ['chart-js', 'chartjs-plugin-annotation', 'html2pdf-js', 'jsvectormap-js', 'jsvectormap-world'], NEXUS_STATS_VIEWS_VERSION, true);

                $refresh_rate = get_option('nexus_stats_refresh_rate', 60);
                $theme = get_option('nexus_stats_theme', 'dark');

                wp_localize_script('nexus-stats-admin-js', 'nexusStatsAdminData', [
                    'restUrl' => esc_url_raw(rest_url('nexus-stats/v1')),
                    'nonce' => wp_create_nonce('wp_rest'),
                    'shareToken' => $token, // Pass token to JS so it can send it in headers
                    'refreshRate' => (int)$refresh_rate * 1000,
                    'theme' => sanitize_text_field($theme)
                ]);

                // Render standalone page
                echo '<!DOCTYPE html><html><head><title>Rapport Stats</title>';
                wp_head(); // Print enqueued scripts/styles
            echo '<style>body { margin:0; padding:20px; background: ' . esc_attr($theme === 'dark' ? '#121212' : '#f5f7fa') . ';} .nexus-stats-wrap { margin:0!important; }</style>';
                echo '</head><body>';
                require_once NEXUS_STATS_VIEWS_DIR . 'admin/views/dashboard.php';
                wp_footer();
                echo '</body></html>';
                exit;
            } else {
            wp_die(esc_html__("Lien expiré ou invalide.", 'nexus-stats'), esc_html__("Accès Refusé", 'nexus-stats'), ['response' => 403]);
            }
        }
    }

    // --- Pages d'admin ---
    public static function render_admin_page() {
        // Inclus le HTML du tableau de bord
        require_once NEXUS_STATS_VIEWS_DIR . 'admin/views/dashboard.php';
    }

    public static function render_settings_page() {
        ?>
        <div class="wrap nexus-stats-wrap">
            <h1>Réglages Nexus Stats</h1>
            <form method="post" action="options.php">
                <?php settings_fields('nexus_stats_settings_group'); ?>
                <?php do_settings_sections('nexus_stats_settings_group'); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Thème d'affichage</th>
                        <td>
                            <select name="nexus_stats_theme">
                                <option value="dark" <?php selected(get_option('nexus_stats_theme', 'dark'), 'dark'); ?>>Mode Sombre (Dark Theme)</option>
                                <option value="light" <?php selected(get_option('nexus_stats_theme', 'dark'), 'light'); ?>>Mode Clair (Light Theme)</option>
                            </select>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Délai d'actualisation En Direct (secondes)</th>
                        <td>
                            <select name="nexus_stats_refresh_rate">
                                <option value="15" <?php selected(get_option('nexus_stats_refresh_rate', 60), 15); ?>>15 secondes (Très rapide - Attention serveur)</option>
                                <option value="30" <?php selected(get_option('nexus_stats_refresh_rate', 60), 30); ?>>30 secondes (Rapide)</option>
                                <option value="60" <?php selected(get_option('nexus_stats_refresh_rate', 60), 60); ?>>60 secondes (Recommandé - Économique)</option>
                            </select>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Mode Éco-Conception (Green IT)</th>
                        <td>
                            <label>
                                <input type="checkbox" name="nexus_stats_eco_mode" value="yes" <?php checked(get_option('nexus_stats_eco_mode', 'no'), 'yes'); ?> />
                                Ignorer les bots connus et optimiser les requêtes
                            </label>
                            <p class="description">Activez cette option pour réduire la charge sur votre serveur en ne comptabilisant pas les robots d'indexation (Google, Bing, etc.).</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Mode "Confidentialité Totale" (RGPD Ready)</th>
                        <td>
                            <label>
                                <input type="checkbox" name="nexus_stats_gdpr_strict" value="yes" <?php checked(get_option('nexus_stats_gdpr_strict', 'no'), 'yes'); ?> />
                                Anonymisation stricte (Zéro Cookie)
                            </label>
                            <p class="description">N'utilise plus le LocalStorage. Génère un identifiant de session volatile uniquement. Vous exempte potentiellement du bandeau de consentement.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Modules de Suivi (UX & Front-end)', 'nexus-stats'); ?></th>
                        <td>
                            <label style="display:block; margin-bottom:8px;">
                                <input type="checkbox" name="nexus_stats_track_scroll" value="yes" <?php checked(get_option('nexus_stats_track_scroll', 'yes'), 'yes'); ?> />
                                <?php esc_html_e('Suivre la profondeur de défilement (Scroll Depth 25-100%)', 'nexus-stats'); ?>
                            </label>
                            <label style="display:block; margin-bottom:8px;">
                                <input type="checkbox" name="nexus_stats_track_outbound" value="yes" <?php checked(get_option('nexus_stats_track_outbound', 'yes'), 'yes'); ?> />
                                <?php esc_html_e('Suivre les clics sur les liens sortants (Outbound Links)', 'nexus-stats'); ?>
                            </label>
                            <label style="display:block; margin-bottom:8px;">
                                <input type="checkbox" name="nexus_stats_track_404" value="yes" <?php checked(get_option('nexus_stats_track_404', 'yes'), 'yes'); ?> />
                                <?php esc_html_e('Détection Intelligente des Erreurs 404 (Liens cassés)', 'nexus-stats'); ?>
                            </label>
                            <?php if (class_exists('WooCommerce')): ?>
                            <label style="display:block; margin-bottom:8px;">
                                <input type="checkbox" name="nexus_stats_woo_sync" value="yes" <?php checked(get_option('nexus_stats_woo_sync', 'yes'), 'yes'); ?> />
                                <?php esc_html_e('Module de Conversion WooCommerce (Lier le CA à la source de trafic)', 'nexus-stats'); ?>
                            </label>
                            <?php endif; ?>
                            <label style="display:block; margin-bottom:8px;">
                                <input type="checkbox" name="nexus_stats_downtime_alerts" value="yes" <?php checked(get_option('nexus_stats_downtime_alerts', 'yes'), 'yes'); ?> />
                                <?php esc_html_e('Alertes de "Downtime" (M\'avertir si le trafic chute à 0 anormalement)', 'nexus-stats'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Widgets du Tableau de Bord', 'nexus-stats'); ?></th>
                        <td>
                            <label style="display:block; margin-bottom:8px;">
                                <input type="checkbox" name="nexus_stats_narrative" value="yes" <?php checked(get_option('nexus_stats_narrative', 'yes'), 'yes'); ?> />
                                <?php esc_html_e('Afficher le "Résumé Narratif" (Analyse automatisée)', 'nexus-stats'); ?>
                            </label>
                            <label style="display:block; margin-bottom:8px;">
                                <input type="checkbox" name="nexus_stats_health" value="yes" <?php checked(get_option('nexus_stats_health', 'yes'), 'yes'); ?> />
                                <?php esc_html_e('Afficher le widget "Santé & UX Core Vitals"', 'nexus-stats'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Objectif Mensuel de Vues</th>
                        <td>
                            <input type="number" name="nexus_stats_monthly_goal" value="<?php echo esc_attr(get_option('nexus_stats_monthly_goal', 10000)); ?>" class="regular-text" />
                            <p class="description">Fixez un objectif pour activer la jauge de progression dans le tableau de bord (Gamification).</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Lien de Partage (Client-Ready)</th>
                        <td>
                            <input type="text" name="nexus_stats_share_token" value="<?php echo esc_attr(get_option('nexus_stats_share_token', '')); ?>" class="regular-text" />
                            <p class="description">Générez un mot de passe ou jeton ici (ex: "client2026"). Le tableau de bord sera visible sans être connecté à l'adresse :<br>
                            <code><?php echo esc_url(site_url('/?nexus_stats_share=VOTRE_JETON')); ?></code>
                            </p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}