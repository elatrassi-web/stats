<?php
$theme = get_option('nexus_stats_theme', 'dark');
$theme_class = ($theme === 'light') ? 'nexus-stats-theme-light' : 'nexus-stats-theme-dark';
?>
<div class="wrap nexus-stats-wrap <?php echo esc_attr($theme_class); ?>">
    <div class="nexus-stats-container">

        <div class="nexus-stats-header">
            <h1 class="nexus-stats-title">Vue d'ensemble - Trend 2026</h1>
            <div class="nexus-stats-actions">
                <button id="nexus_stats_theme_toggle" class="nexus-stats-icon-btn" title="Changer le thème">
                    <span class="dashicons dashicons-admin-appearance"></span>
                </button>
                <div class="nexus-stats-custom-dates" id="nexus_stats_custom_dates_wrapper" style="display: none;">
                    <input type="date" id="nexus_stats_date_start" class="nexus-stats-input" title="Date de début">
                    <span class="nexus-stats-date-separator">au</span>
                    <input type="date" id="nexus_stats_date_end" class="nexus-stats-input" title="Date de fin">
                    <button id="nexus_stats_apply_dates" class="nexus-stats-btn">Appliquer</button>
                </div>
                <select id="nexus_stats_time_filter" class="nexus-stats-select">
                    <option value="today">Aujourd'hui</option>
                    <option value="30min">30 Dernières Minutes</option>
                    <option value="yesterday">Hier</option>
                    <option value="7days" selected>7 Derniers Jours</option>
                    <option value="30days">30 Derniers Jours</option>
                    <option value="last_month">Mois Précédent</option>
                    <option value="custom">Période personnalisée...</option>
                </select>
            </div>
        </div>

        <div class="nexus-stats-bento-grid">

            <!-- Cards (Metrics) -->
            <div class="nexus-stats-card nexus-stats-metric-card">
                <div class="nexus-stats-card-header">
                    <span class="dashicons dashicons-visibility"></span> Vues Totales
                </div>
                <div class="nexus-stats-card-body">
                    <h2 class="nexus-stats-stat" id="highlight-views">--</h2>
                </div>
            </div>

            <div class="nexus-stats-card nexus-stats-metric-card">
                <div class="nexus-stats-card-header">
                    <span class="dashicons dashicons-groups"></span> Visiteurs Uniques
                </div>
                <div class="nexus-stats-card-body">
                    <h2 class="nexus-stats-stat" id="highlight-visitors">--</h2>
                </div>
            </div>

            <div class="nexus-stats-card nexus-stats-metric-card nexus-stats-live-card">
                <div class="nexus-stats-card-header">
                    <span class="live-dot pulse-emerald"></span> Visiteurs En Direct
                </div>
                <div class="nexus-stats-card-body">
                    <h2 class="nexus-stats-stat pulse-text" id="highlight-live">0</h2>
                </div>
            </div>

            <!-- Main Chart Area -->
            <div class="nexus-stats-card nexus-stats-chart-card">
                <div class="nexus-stats-card-header chart-header-actions">
                    <span><span class="dashicons dashicons-chart-area"></span> Évolution des vues</span>
                    <div class="chart-switcher">
                        <button class="switcher-btn active" data-type="line" title="Tendance"><span class="dashicons dashicons-chart-line"></span></button>
                        <button class="switcher-btn" data-type="bar" title="Comparaison"><span class="dashicons dashicons-chart-bar"></span></button>
                    </div>
                </div>
                <div class="nexus-stats-card-body chart-wrapper">
                    <canvas id="nexusStatsMainChart"></canvas>
                </div>
            </div>

            <!-- Device Breakdown (Doughnut) -->
            <div class="nexus-stats-card nexus-stats-device-card">
                <div class="nexus-stats-card-header">
                    <span class="dashicons dashicons-smartphone"></span> Mobile vs Desktop
                </div>
                <div class="nexus-stats-card-body chart-wrapper doughnut-wrapper">
                    <canvas id="nexusStatsDeviceChart"></canvas>
                </div>
            </div>

            <!-- Top Content Lists -->
            <div class="nexus-stats-card nexus-stats-list-card">
                <div class="nexus-stats-card-header">
                    <span class="dashicons dashicons-admin-post"></span> Top Articles
                </div>
                <div class="nexus-stats-card-body p-0">
                    <ul class="nexus-stats-list" id="nexus_stats_top_posts">
                        <li class="nexus-stats-list-item empty">Chargement...</li>
                    </ul>
                </div>
            </div>

            <div class="nexus-stats-card nexus-stats-list-card">
                <div class="nexus-stats-card-header">
                    <span class="dashicons dashicons-admin-page"></span> Top Pages
                </div>
                <div class="nexus-stats-card-body p-0">
                    <ul class="nexus-stats-list" id="nexus_stats_top_pages">
                        <li class="nexus-stats-list-item empty">Chargement...</li>
                    </ul>
                </div>
            </div>

        </div> <!-- /.nexus-stats-bento-grid -->

    </div>
</div>
