<?php
if (!defined('ABSPATH')) exit;

class Nexus_Stats_REST {
    public static function init() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    public static function register_routes() {
        // Track View
        register_rest_route('nexus-stats/v1', '/track', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'track_view'],
            'permission_callback' => '__return_true', // Ouvert à tous pour le tracking
        ]);

        // Live Ping
        register_rest_route('nexus-stats/v1', '/live/ping', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'live_ping'],
            'permission_callback' => '__return_true',
        ]);

        // Live Exit
        register_rest_route('nexus-stats/v1', '/live/exit', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'live_exit'],
            'permission_callback' => '__return_true',
        ]);

        // Update Read Time
        register_rest_route('nexus-stats/v1', '/read-time', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'update_read_time'],
            'permission_callback' => '__return_true',
        ]);

        // Get Dashboard Data (Admin only)
        register_rest_route('nexus-stats/v1', '/stats/dashboard', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'get_dashboard_data'],
            'permission_callback' => [__CLASS__, 'check_admin_permissions'],
        ]);

        // Get Live Count (Admin only)
        register_rest_route('nexus-stats/v1', '/stats/live', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'get_live_count'],
            'permission_callback' => [__CLASS__, 'check_admin_permissions'],
        ]);

        // Toggle Theme (Admin only)
        register_rest_route('nexus-stats/v1', '/theme', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'update_theme'],
            'permission_callback' => [__CLASS__, 'check_admin_permissions'],
        ]);

        // Clicks Tracking (Public)
        register_rest_route('nexus-stats/v1', '/clicks/track', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'track_click'],
            'permission_callback' => '__return_true',
        ]);

        // Advanced Metrics Tracking (Scroll & Load Time)
        register_rest_route('nexus-stats/v1', '/metrics/update', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'update_advanced_metrics'],
            'permission_callback' => '__return_true',
        ]);

        // Outbound Link Tracking
        register_rest_route('nexus-stats/v1', '/outbound/track', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'track_outbound_link'],
            'permission_callback' => '__return_true',
        ]);

        // Heatmap Data (Admin only)
        register_rest_route('nexus-stats/v1', '/clicks/data', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'get_heatmap_data'],
            'permission_callback' => [__CLASS__, 'check_admin_permissions'],
        ]);

        // Annotations (Admin only)
        register_rest_route('nexus-stats/v1', '/annotations', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'add_annotation'],
            'permission_callback' => [__CLASS__, 'check_admin_permissions'],
        ]);

        // Cleanup Ghost Traffic (Admin only)
        register_rest_route('nexus-stats/v1', '/cleanup', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'cleanup_ghost_traffic'],
            'permission_callback' => [__CLASS__, 'check_admin_permissions'],
        ]);
    }

    public static function check_admin_permissions(WP_REST_Request $request) {
        if (current_user_can('manage_options')) {
            return true;
        }

        // Handle Client-Ready Shared Dashboard Authorization
        $share_token = $request->get_header('X-Nexus-Stats-Share');
        $saved_token = get_option('nexus_stats_share_token', '');
        if (!empty($saved_token) && !empty($share_token) && $share_token === $saved_token) {
            return true;
        }

        return false;
    }

    public static function track_view(WP_REST_Request $request) {
        $post_id    = intval($request->get_param('post_id'));
        $visitor_id = sanitize_text_field($request->get_param('visitor_id'));
        $device     = sanitize_text_field($request->get_param('device'));
        $referrer   = sanitize_text_field($request->get_param('referrer'));

        $lang = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_ACCEPT_LANGUAGE'])) : 'en';

        if (!$visitor_id) $visitor_id = 'unknown';
        if (!$device) $device = 'desktop';

        // Check eco mode
        $eco_mode = get_option('nexus_stats_eco_mode', 'no');
        if ($eco_mode === 'yes') {
            // Very basic bot detection based on user agent
            $ua = isset($_SERVER['HTTP_USER_AGENT']) ? strtolower(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT']))) : '';
            if (preg_match('/bot|crawl|slurp|spider|mediapartners/i', $ua)) {
                return rest_ensure_response(['success' => true, 'message' => 'Eco mode: Bot ignored']);
            }
        }

        if ($post_id > 0) {
            Nexus_Stats_DB::track_view($post_id, $visitor_id, $device, $referrer, $lang);
        }

        return rest_ensure_response(['success' => true]);
    }

    public static function update_read_time(WP_REST_Request $request) {
        $post_id    = intval($request->get_param('post_id'));
        $visitor_id = sanitize_text_field($request->get_param('visitor_id'));
        $read_time  = intval($request->get_param('time')); // en secondes

        if ($post_id > 0 && $visitor_id && $read_time > 0) {
            Nexus_Stats_DB::update_read_time($post_id, $visitor_id, $read_time);
        }
        return rest_ensure_response(['success' => true]);
    }

    public static function update_advanced_metrics(WP_REST_Request $request) {
        $post_id    = intval($request->get_param('post_id'));
        $visitor_id = sanitize_text_field($request->get_param('visitor_id'));
        $scroll     = intval($request->get_param('scroll'));
        $load_time  = intval($request->get_param('load_time'));

        if ($post_id > 0 && $visitor_id) {
            Nexus_Stats_DB::update_metrics($post_id, $visitor_id, $scroll, $load_time);
        }
        return rest_ensure_response(['success' => true]);
    }

    public static function track_outbound_link(WP_REST_Request $request) {
        $url = sanitize_text_field($request->get_param('url'));
        if (!empty($url)) {
            Nexus_Stats_DB::track_outbound($url);
        }
        return rest_ensure_response(['success' => true]);
    }

    public static function live_ping(WP_REST_Request $request) {
        $visitor_id = sanitize_text_field($request->get_param('visitor_id'));
        $post_id    = intval($request->get_param('post_id'));

        if ($visitor_id) {
            Nexus_Stats_DB::live_ping($visitor_id, $post_id);
        }
        return rest_ensure_response(['success' => true]);
    }

    public static function live_exit(WP_REST_Request $request) {
        $visitor_id = sanitize_text_field($request->get_param('visitor_id'));
        if ($visitor_id) {
            Nexus_Stats_DB::live_exit($visitor_id);
        }
        return rest_ensure_response(['success' => true]);
    }

    public static function track_click(WP_REST_Request $request) {
        $post_id  = intval($request->get_param('post_id'));
        $selector = sanitize_text_field($request->get_param('selector'));

        if ($post_id > 0 && !empty($selector)) {
            global $wpdb;
            $table = $wpdb->prefix . 'nexus_stats_clicks';
            // Limit selector length to match DB
            $selector = substr($selector, 0, 191);
            $wpdb->query($wpdb->prepare("
                INSERT INTO $table (post_id, element_selector)
                VALUES (%d, %s)
                ON DUPLICATE KEY UPDATE click_count = click_count + 1
            ", $post_id, $selector));
        }
        return rest_ensure_response(['success' => true]);
    }

    public static function get_heatmap_data(WP_REST_Request $request) {
        $post_id = intval($request->get_param('post_id'));
        global $wpdb;
        $table = $wpdb->prefix . 'nexus_stats_clicks';
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT element_selector, click_count
            FROM $table
            WHERE post_id = %d
            ORDER BY click_count DESC
            LIMIT 50
        ", $post_id));
        return rest_ensure_response(['success' => true, 'data' => $results]);
    }

    public static function add_annotation(WP_REST_Request $request) {
        $date = sanitize_text_field($request->get_param('date'));
        $text = sanitize_text_field($request->get_param('text'));

        if ($date && $text) {
            global $wpdb;
            $table = $wpdb->prefix . 'nexus_stats_annotations';
            $wpdb->insert($table, [
                'note_date' => $date,
                'note_text' => $text
            ]);
            return rest_ensure_response(['success' => true]);
        }
        return rest_ensure_response(['success' => false]);
    }

    public static function cleanup_ghost_traffic(WP_REST_Request $request) {
        global $wpdb;
        $table_log = $wpdb->prefix . 'nexus_stats_views_log';

        // Define ghost traffic heuristics:
        // Ex: read_time is exactly 0 and visitor only has 1 view total.
        $wpdb->query("
            DELETE FROM $table_log
            WHERE read_time_seconds = 0
            AND visitor_id IN (
                SELECT vid FROM (
                    SELECT visitor_id as vid
                    FROM $table_log
                    GROUP BY visitor_id
                    HAVING COUNT(id) = 1
                ) as tmp
            )
        ");

        $deleted = $wpdb->rows_affected;
        return rest_ensure_response(['success' => true, 'deleted' => $deleted]);
    }

    public static function update_theme(WP_REST_Request $request) {
        $theme = sanitize_text_field($request->get_param('theme'));
        if (in_array($theme, ['dark', 'light'])) {
            update_option('nexus_stats_theme', $theme);
            return rest_ensure_response(['success' => true]);
        }
        return rest_ensure_response(['success' => false, 'message' => 'Invalid theme']);
    }

    public static function get_dashboard_data(WP_REST_Request $request) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'nexus_stats_views_log';
        $time_range = sanitize_text_field($request->get_param('time_range'));
        if (!$time_range) $time_range = 'today';

        $now = current_time('timestamp');
        $start_date = '';
        $end_date = gmdate('Y-m-d H:i:s', $now);
        $group_format = '';

        switch ($time_range) {
            case 'custom':
                $custom_start = sanitize_text_field($request->get_param('custom_start'));
                $custom_end   = sanitize_text_field($request->get_param('custom_end'));

                if ($custom_start && $custom_end) {
                    $start_date = gmdate('Y-m-d 00:00:00', strtotime($custom_start));
                    $end_date   = gmdate('Y-m-d 23:59:59', strtotime($custom_end));

                    $diff = strtotime($end_date) - strtotime($start_date);
                    $days = round($diff / 86400);

                    if ($days <= 1) {
                        $group_format = '%H:00';
                    } elseif ($days <= 60) {
                        $group_format = '%d/%m';
                    } else {
                        $group_format = '%m/%Y';
                    }
                } else {
                    // Fallback to today if dates are missing
                    $start_date = gmdate('Y-m-d 00:00:00', $now);
                    $group_format = '%H:00';
                }
                break;
            case '30min':
                $start_date = gmdate('Y-m-d H:i:s', strtotime('-30 minutes', $now));
                $group_format = '%H:%i';
                break;
            case 'yesterday':
                $start_date = gmdate('Y-m-d 00:00:00', strtotime('yesterday', $now));
                $end_date = gmdate('Y-m-d 23:59:59', strtotime('yesterday', $now));
                $group_format = '%H:00';
                break;
            case '7days':
                $start_date = gmdate('Y-m-d 00:00:00', strtotime('-6 days', $now));
                $group_format = '%d/%m';
                break;
            case '30days':
                $start_date = gmdate('Y-m-d 00:00:00', strtotime('-29 days', $now));
                $group_format = '%d/%m';
                break;
            case 'last_month':
                $start_date = gmdate('Y-m-01 00:00:00', strtotime('first day of last month', $now));
                $end_date = gmdate('Y-m-t 23:59:59', strtotime('last day of last month', $now));
                $group_format = '%d/%m';
                break;
            case 'today':
            default:
                $start_date = gmdate('Y-m-d 00:00:00', $now);
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

        // Mobile vs Desktop
        $device_stats = $wpdb->get_results($wpdb->prepare("
            SELECT device_type, COUNT(id) as count
            FROM $table_name
            WHERE view_datetime >= %s AND view_datetime <= %s
            GROUP BY device_type
        ", $start_date, $end_date));

        $devices = ['mobile' => 0, 'desktop' => 0];
        if ($device_stats) {
            foreach ($device_stats as $stat) {
                if (isset($devices[$stat->device_type])) {
                    $devices[$stat->device_type] = (int)$stat->count;
                }
            }
        }

        // Get Annotations for the timeframe
        $table_annotations = $wpdb->prefix . 'nexus_stats_annotations';
        $annotations = $wpdb->get_results($wpdb->prepare("
            SELECT note_date, note_text
            FROM $table_annotations
            WHERE note_date >= DATE(%s) AND note_date <= DATE(%s)
        ", $start_date, $end_date));

        // Get Monthly Goal Progress
        $monthly_goal = (int) get_option('nexus_stats_monthly_goal', 10000);
        $month_start = gmdate('Y-m-01 00:00:00', $now);
        $month_views = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(id) FROM $table_name
            WHERE view_datetime >= %s
        ", $month_start));

        // Narrative Summary logic
        $prev_month_start = gmdate('Y-m-01 00:00:00', strtotime('first day of last month'));
        $prev_month_end   = gmdate('Y-m-t 23:59:59', strtotime('last day of last month'));

        $prev_month_views = (int) $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(id) FROM $table_name
            WHERE view_datetime >= %s AND view_datetime <= %s
        ", $prev_month_start, $prev_month_end));

        $delta = 0;
        if ($prev_month_views > 0) {
            $delta = round((($month_views - $prev_month_views) / $prev_month_views) * 100);
        }

        $top_source_obj = $wpdb->get_row($wpdb->prepare("
            SELECT referrer_type, COUNT(id) as count
            FROM $table_name
            WHERE view_datetime >= %s
            GROUP BY referrer_type
            ORDER BY count DESC LIMIT 1
        ", $month_start));
        $top_source = $top_source_obj ? $top_source_obj->referrer_type : 'direct';

        $narrative = "Pas assez de données pour générer un résumé.";
        if ($month_views > 0 && $prev_month_views > 0) {
            $direction = $delta >= 0 ? "augmenté" : "baissé";
            $narrative = sprintf(
                "Votre trafic a %s de %s%% ce mois-ci par rapport au mois dernier. Votre canal d'acquisition le plus fort est actuellement le trafic '%s'.",
                $direction,
                abs($delta),
                ucfirst($top_source)
            );
        }

        // Additional Modules Data (404s, Outbounds, WooCommerce, Vitals)
        $avg_load = $wpdb->get_var($wpdb->prepare("SELECT AVG(load_time_ms) FROM $table_name WHERE view_datetime >= %s AND view_datetime <= %s AND load_time_ms > 0", $start_date, $end_date));
        $avg_scroll = $wpdb->get_var($wpdb->prepare("SELECT AVG(scroll_depth) FROM $table_name WHERE view_datetime >= %s AND view_datetime <= %s AND scroll_depth > 0", $start_date, $end_date));
        $avg_db_time = get_option('nexus_stats_avg_query_time', 0);

        $table_404 = $wpdb->prefix . 'nexus_stats_404';
        $errors_404 = $wpdb->get_results("SELECT requested_url, hit_count FROM $table_404 ORDER BY hit_count DESC LIMIT 5");

        $table_outbound = $wpdb->prefix . 'nexus_stats_outbound';
        $outbounds = $wpdb->get_results("SELECT target_url, click_count FROM $table_outbound ORDER BY click_count DESC LIMIT 5");

        $woo_revenue = 0;
        $woo_sources = [];
        if (class_exists('WooCommerce')) {
            $table_woo = $wpdb->prefix . 'nexus_stats_woo';
            $woo_revenue = (float) $wpdb->get_var($wpdb->prepare("SELECT SUM(order_total) FROM $table_woo WHERE order_date >= %s AND order_date <= %s", $start_date, $end_date));
            $woo_sources = $wpdb->get_results($wpdb->prepare("SELECT referrer_type, SUM(order_total) as revenue, COUNT(id) as orders FROM $table_woo WHERE order_date >= %s AND order_date <= %s GROUP BY referrer_type ORDER BY revenue DESC", $start_date, $end_date));
        }

        // Sources (Categories)
        $sources = $wpdb->get_results($wpdb->prepare("
            SELECT referrer_type, COUNT(id) as count
            FROM $table_name
            WHERE view_datetime >= %s AND view_datetime <= %s
            GROUP BY referrer_type
        ", $start_date, $end_date));

        // Top Referrers (Domains) + Read Time Correlation
        $referrers = $wpdb->get_results($wpdb->prepare("
            SELECT referrer_domain, referrer_type, COUNT(id) as count, AVG(read_time_seconds) as avg_time
            FROM $table_name
            WHERE view_datetime >= %s AND view_datetime <= %s AND referrer_type != 'direct' AND referrer_type != 'internal'
            GROUP BY referrer_domain, referrer_type
            ORDER BY count DESC
            LIMIT 5
        ", $start_date, $end_date));

        // Countries
        $countries = $wpdb->get_results($wpdb->prepare("
            SELECT country_code, COUNT(id) as count
            FROM $table_name
            WHERE view_datetime >= %s AND view_datetime <= %s
            GROUP BY country_code
            ORDER BY count DESC
            LIMIT 10
        ", $start_date, $end_date));

        // Languages
        $languages = $wpdb->get_results($wpdb->prepare("
            SELECT browser_lang, COUNT(id) as count
            FROM $table_name
            WHERE view_datetime >= %s AND view_datetime <= %s
            GROUP BY browser_lang
            ORDER BY count DESC
            LIMIT 5
        ", $start_date, $end_date));

        // Get Previous Period Data for Comparison (if requested)
        $compare = sanitize_text_field($request->get_param('compare')) === 'true';
        $chart_values_prev = [];
        if ($compare) {
            $diff_seconds = strtotime($end_date) - strtotime($start_date);
            $prev_end_date = $start_date;
            $prev_start_date = date('Y-m-d H:i:s', strtotime($start_date) - $diff_seconds);

            $chart_results_prev = $wpdb->get_results($wpdb->prepare("
                SELECT DATE_FORMAT(view_datetime, %s) as time_label, COUNT(id) as view_count
                FROM $table_name
                WHERE view_datetime >= %s AND view_datetime <= %s
                GROUP BY time_label
                ORDER BY view_datetime ASC
            ", $group_format, $prev_start_date, $prev_end_date));

            // Align previous data with current labels for overlaying correctly on the chart
            $temp_prev = [];
            if ($chart_results_prev) {
                foreach ($chart_results_prev as $row) {
                    $temp_prev[$row->time_label] = $row->view_count;
                }
            }
            // Map to current labels (approximate visualization)
            foreach ($chart_labels as $label) {
                // In a perfect system, we'd shift the labels by the exact time diff.
                // For simplicity in this demo, we just align by index or pad 0s.
                $chart_values_prev[] = !empty($temp_prev) ? (array_shift($temp_prev) ?? 0) : 0;
            }
        }

        return rest_ensure_response([
            'success' => true,
            'data' => [
                'total_views'    => ($totals && $totals->total_views) ? (int)$totals->total_views : 0,
                'total_visitors' => ($totals && $totals->total_visitors) ? (int)$totals->total_visitors : 0,
                'chart_labels'   => $chart_labels,
                'chart_values'   => $chart_values,
                'chart_values_prev' => $chart_values_prev,
                'device_stats'   => $devices,
                'sources'        => $sources,
                'referrers'      => $referrers,
                'countries'      => $countries,
                'languages'      => $languages,
                'top_posts'      => self::get_top_content('post', $start_date, $end_date),
                'top_pages'      => self::get_top_content('page', $start_date, $end_date),
                'annotations'    => $annotations,
                'monthly_goal'   => $monthly_goal,
                'monthly_views'  => (int)$month_views,
                'narrative'      => $narrative,
                'vitals'         => [
                    'avg_load'   => round((float)$avg_load),
                    'avg_scroll' => round((float)$avg_scroll),
                    'db_time'    => round((float)$avg_db_time, 2)
                ],
                'errors_404'     => $errors_404,
                'outbounds'      => $outbounds,
                'woo'            => [
                    'revenue' => $woo_revenue,
                    'sources' => $woo_sources
                ]
            ]
        ]);
    }

    private static function get_top_content($post_type, $start_date, $end_date) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'nexus_stats_views_log';
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT p.post_title, p.ID, COUNT(v.id) as views, AVG(v.read_time_seconds) as avg_read_time
            FROM $table_name v
            INNER JOIN {$wpdb->posts} p ON v.post_id = p.ID
            WHERE p.post_type = %s AND p.post_status = 'publish'
            AND v.view_datetime >= %s AND v.view_datetime <= %s
            GROUP BY p.ID
            ORDER BY views DESC
            LIMIT 5
        ", $post_type, $start_date, $end_date));

        // Content Health Score Logic (Recent half vs Older half of the timeframe)
        $diff = strtotime($end_date) - strtotime($start_date);
        $mid_date = gmdate('Y-m-d H:i:s', strtotime($start_date) + ($diff / 2));

        $output = [];
        if ($results) {
            foreach ($results as $row) {
                // Get older half views
                $older_views = (int) $wpdb->get_var($wpdb->prepare("
                    SELECT COUNT(id) FROM $table_name
                    WHERE post_id = %d AND view_datetime >= %s AND view_datetime < %s
                ", $row->ID, $start_date, $mid_date));

                // Get recent half views
                $recent_views = (int) $wpdb->get_var($wpdb->prepare("
                    SELECT COUNT(id) FROM $table_name
                    WHERE post_id = %d AND view_datetime >= %s AND view_datetime <= %s
                ", $row->ID, $mid_date, $end_date));

                // Calculate Health Score
                $health = 'stable';
                if ($older_views > 0) {
                    $change = ($recent_views - $older_views) / $older_views;
                    if ($change >= 0.1) $health = 'evergreen'; // Growing
                    elseif ($change <= -0.2) $health = 'dying'; // Dropping > 20%
                }

                $output[] = [
                    'id' => $row->ID,
                    'title' => wp_trim_words($row->post_title, 6, '...'),
                    'views' => $row->views,
                    'avg_read_time' => round((float)$row->avg_read_time),
                    'health' => $health,
                    'edit_link' => get_edit_post_link($row->ID, 'raw'),
                    'permalink' => get_permalink($row->ID)
                ];
            }
        }
        return $output;
    }

    public static function get_live_count(WP_REST_Request $request) {
        $count = Nexus_Stats_DB::get_live_count();
        return rest_ensure_response(['success' => true, 'data' => $count]);
    }
}