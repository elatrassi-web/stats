<?php
if (!defined('ABSPATH')) exit;

class My_Angers_Admin {
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
    }

    public static function add_admin_menu() {
        add_menu_page('Nexus Stats', 'Stats Vues', 'manage_options', 'my-angers-stats', [__CLASS__, 'render_admin_page'], 'dashicons-chart-bar', 6);
        add_submenu_page('my-angers-stats', 'Réglages Nexus Stats', 'Réglages', 'manage_options', 'my-angers-settings', [__CLASS__, 'render_settings_page']);
    }

    public static function enqueue_scripts($hook) {
        if ($hook != 'toplevel_page_my-angers-stats' && $hook != 'index.php') return;

        wp_enqueue_style('my-angers-admin-css', MY_ANGERS_VIEWS_URL . 'assets/css/my-angers-admin.css', [], MY_ANGERS_VIEWS_VERSION);
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], null, true);

        if ($hook == 'toplevel_page_my-angers-stats') {
            wp_enqueue_script('my-angers-admin-js', MY_ANGERS_VIEWS_URL . 'assets/js/my-angers-admin.js', ['chart-js'], MY_ANGERS_VIEWS_VERSION, true);

            $refresh_rate = get_option('my_angers_refresh_rate', 60);

            wp_localize_script('my-angers-admin-js', 'myAngersAdminData', [
                'restUrl' => esc_url_raw(rest_url('my-angers/v1')),
                'nonce' => wp_create_nonce('wp_rest'),
                'refreshRate' => (int)$refresh_rate * 1000
            ]);
        }
    }

    public static function register_settings() {
        register_setting('my_angers_settings_group', 'my_angers_refresh_rate', [
            'type' => 'integer',
            'default' => 60,
            'sanitize_callback' => 'absint'
        ]);
        register_setting('my_angers_settings_group', 'my_angers_eco_mode', [
            'type' => 'string',
            'default' => 'no',
            'sanitize_callback' => 'sanitize_text_field'
        ]);
    }

    // --- Colonnes ---
    public static function add_views_column($columns) {
        if (is_array($columns) && isset($columns['post_views'])) unset($columns['post_views']);
        $columns['my_angers_views_col'] = '<span class="dashicons dashicons-visibility" title="Vues"></span> Vues';
        return $columns;
    }

    public static function views_column_data($column, $post_id) {
        if ($column === 'my_angers_views_col') {
            $views = (int) get_post_meta($post_id, 'my_angers_view_count', true);
            // Mode Focus: Highlight if > threshold (e.g., 500)
            $class = ($views > 500) ? 'my-angers-viral' : '';
            echo '<span class="' . $class . '"><strong>' . number_format($views, 0, ',', ' ') . '</strong></span>';
        }
    }

    public static function sortable_views_column($columns) {
        $columns['my_angers_views_col'] = 'my_angers_views_col';
        return $columns;
    }

    public static function views_orderby($query) {
        if (!is_admin() || !$query->is_main_query()) return;
        if ('my_angers_views_col' === $query->get('orderby')) {
            $query->set('meta_key', 'my_angers_view_count');
            $query->set('orderby', 'meta_value_num');
        }
    }

    // --- Admin Bar ---
    public static function add_admin_bar_node($wp_admin_bar) {
        if (!current_user_can('manage_options')) return;

        $live_count = My_Angers_DB::get_live_count();

        $args = array(
            'id'    => 'my_angers_live_stats',
            'title' => '<span class="ab-icon dashicons dashicons-chart-line"></span><span class="ab-label" style="color:#00ff88;font-weight:bold;"><span class="live-dot-mini" style="display:inline-block;width:6px;height:6px;background:#00ff88;border-radius:50%;margin-right:4px;animation:pulse-green 2s infinite;"></span>' . $live_count . ' Live</span>',
            'href'  => admin_url('admin.php?page=my-angers-stats'),
            'meta'  => array(
                'class' => 'my-angers-admin-bar-node',
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
            wp_add_dashboard_widget('my_angers_dashboard_widget', 'Stats Vues (24h)', [__CLASS__, 'render_dashboard_widget']);
        }
    }

    public static function render_dashboard_widget() {
        // Un placeholder pour le script JS
        echo '<div id="my-angers-sparkline-container" style="height: 100px; width: 100%; position: relative;"><canvas id="myAngersSparkline"></canvas></div>';
        echo '<p style="text-align:center;margin-top:10px;"><a href="' . admin_url('admin.php?page=my-angers-stats') . '">Voir le tableau de bord complet</a></p>';

        // Script inline minimal pour le sparkline
        ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var canvas = document.getElementById('myAngersSparkline');
                if(!canvas) return;
                var ctx = canvas.getContext('2d');

                fetch('<?php echo esc_url_raw(rest_url('my-angers/v1/stats/dashboard?time_range=yesterday')); ?>', {
                    headers: { 'X-WP-Nonce': '<?php echo wp_create_nonce("wp_rest"); ?>' }
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

    // --- Pages d'admin ---
    public static function render_admin_page() {
        // Inclus le HTML du tableau de bord
        require_once MY_ANGERS_VIEWS_DIR . 'admin/views/dashboard.php';
    }

    public static function render_settings_page() {
        ?>
        <div class="wrap my-angers-wrap">
            <h1>Réglages Nexus Stats</h1>
            <form method="post" action="options.php">
                <?php settings_fields('my_angers_settings_group'); ?>
                <?php do_settings_sections('my_angers_settings_group'); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Délai d'actualisation En Direct (secondes)</th>
                        <td>
                            <select name="my_angers_refresh_rate">
                                <option value="15" <?php selected(get_option('my_angers_refresh_rate', 60), 15); ?>>15 secondes (Très rapide - Attention serveur)</option>
                                <option value="30" <?php selected(get_option('my_angers_refresh_rate', 60), 30); ?>>30 secondes (Rapide)</option>
                                <option value="60" <?php selected(get_option('my_angers_refresh_rate', 60), 60); ?>>60 secondes (Recommandé - Économique)</option>
                            </select>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Mode Éco-Conception (Green IT)</th>
                        <td>
                            <label>
                                <input type="checkbox" name="my_angers_eco_mode" value="yes" <?php checked(get_option('my_angers_eco_mode', 'no'), 'yes'); ?> />
                                Ignorer les bots connus et optimiser les requêtes
                            </label>
                            <p class="description">Activez cette option pour réduire la charge sur votre serveur en ne comptabilisant pas les robots d'indexation (Google, Bing, etc.).</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}