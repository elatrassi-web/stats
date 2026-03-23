<?php
$theme = get_option('nexus_stats_theme', 'dark');
$theme_class = ($theme === 'light') ? 'nexus-stats-theme-light' : 'nexus-stats-theme-dark';
?>
<div class="wrap nexus-stats-wrap <?php echo esc_attr($theme_class); ?>">
    <div class="nexus-stats-container">

        <div class="nexus-stats-header">
            <h1 class="nexus-stats-title"><?php esc_html_e("Vue d'ensemble - Trend 2026", 'nexus-stats-views'); ?></h1>
            <div class="nexus-stats-actions">
                <button id="nexus_stats_theme_toggle" class="nexus-stats-icon-btn" title="<?php esc_attr_e('Changer le thème', 'nexus-stats-views'); ?>">
                    <span class="dashicons dashicons-admin-appearance"></span>
                </button>
                <div class="nexus-stats-custom-dates" id="nexus_stats_custom_dates_wrapper" style="display: none;">
                    <input type="date" id="nexus_stats_date_start" class="nexus-stats-input" title="<?php esc_attr_e('Date de début', 'nexus-stats-views'); ?>">
                    <span class="nexus-stats-date-separator"><?php esc_html_e('au', 'nexus-stats-views'); ?></span>
                    <input type="date" id="nexus_stats_date_end" class="nexus-stats-input" title="<?php esc_attr_e('Date de fin', 'nexus-stats-views'); ?>">
                    <button id="nexus_stats_apply_dates" class="nexus-stats-btn"><?php esc_html_e('Appliquer', 'nexus-stats-views'); ?></button>
                </div>
                <select id="nexus_stats_time_filter" class="nexus-stats-select">
                    <option value="today"><?php esc_html_e('Aujourd\'hui', 'nexus-stats-views'); ?></option>
                    <option value="30min"><?php esc_html_e('30 Dernières Minutes', 'nexus-stats-views'); ?></option>
                    <option value="yesterday"><?php esc_html_e('Hier', 'nexus-stats-views'); ?></option>
                    <option value="7days" selected><?php esc_html_e('7 Derniers Jours', 'nexus-stats-views'); ?></option>
                    <option value="30days"><?php esc_html_e('30 Derniers Jours', 'nexus-stats-views'); ?></option>
                    <option value="last_month"><?php esc_html_e('Mois Précédent', 'nexus-stats-views'); ?></option>
                    <option value="custom"><?php esc_html_e('Période personnalisée...', 'nexus-stats-views'); ?></option>
                </select>
                <div style="display: flex; align-items: center; gap: 4px;">
                    <label class="nexus-stats-switch" title="<?php esc_attr_e('Comparer avec la période précédente', 'nexus-stats-views'); ?>">
                        <input type="checkbox" id="nexus_stats_compare_toggle">
                        <span class="nexus-stats-slider"></span>
                    </label>
                    <span style="font-size: 12px; font-weight: 600; color: var(--nexus-stats-text-muted);"><?php esc_html_e('Vs Précédent', 'nexus-stats-views'); ?></span>
                </div>
            </div>
        </div>

        <div class="nexus-stats-header-actions">
            <button id="nexus_stats_export_pdf" class="nexus-stats-btn"><span class="dashicons dashicons-media-document"></span> <?php esc_html_e('Exporter PDF', 'nexus-stats-views'); ?></button>
            <button id="nexus_stats_cleanup_ghosts" class="nexus-stats-btn" style="background:var(--nexus-stats-blue);"><span class="dashicons dashicons-shield"></span> <?php esc_html_e('Nettoyer Stats', 'nexus-stats-views'); ?></button>
        </div>

        <div class="nexus-stats-bento-grid" id="nexus_stats_pdf_area">

            <!-- Goal Widget (Full Width) -->
            <div class="nexus-stats-card nexus-stats-goal-card">
                <div class="nexus-stats-card-header">
                    <span class="dashicons dashicons-flag"></span> <?php esc_html_e('Objectif Mensuel :', 'nexus-stats-views'); ?> <span id="nexus_stats_goal_text">--</span>
                </div>
                <div class="nexus-stats-card-body p-0">
                    <div class="nexus-stats-progress-wrapper">
                        <div id="nexus_stats_goal_bar" class="nexus-stats-progress-bar"></div>
                    </div>
                </div>
            </div>

            <!-- Cards (Metrics) -->
            <div class="nexus-stats-card nexus-stats-metric-card">
                <div class="nexus-stats-card-header">
                    <span class="dashicons dashicons-visibility"></span> <?php esc_html_e('Vues Totales', 'nexus-stats-views'); ?>
                </div>
                <div class="nexus-stats-card-body">
                    <h2 class="nexus-stats-stat" id="highlight-views">--</h2>
                </div>
            </div>

            <div class="nexus-stats-card nexus-stats-metric-card">
                <div class="nexus-stats-card-header">
                    <span class="dashicons dashicons-groups"></span> <?php esc_html_e('Visiteurs Uniques', 'nexus-stats-views'); ?>
                </div>
                <div class="nexus-stats-card-body">
                    <h2 class="nexus-stats-stat" id="highlight-visitors">--</h2>
                </div>
            </div>

            <div class="nexus-stats-card nexus-stats-metric-card nexus-stats-live-card">
                <div class="nexus-stats-card-header">
                    <span class="live-dot pulse-emerald"></span> <?php esc_html_e('Visiteurs En Direct', 'nexus-stats-views'); ?>
                </div>
                <div class="nexus-stats-card-body">
                    <h2 class="nexus-stats-stat pulse-text" id="highlight-live">0</h2>
                </div>
            </div>

            <!-- Main Chart Area -->
            <div class="nexus-stats-card nexus-stats-chart-card">
                <div class="nexus-stats-card-header chart-header-actions">
                    <span><span class="dashicons dashicons-chart-area"></span> <?php esc_html_e('Évolution des vues', 'nexus-stats-views'); ?></span>

                    <div class="nexus-stats-annotation-form">
                        <input type="date" id="nexus_stats_annot_date" class="nexus-stats-input-mini" title="<?php esc_attr_e('Date de la note', 'nexus-stats-views'); ?>">
                        <input type="text" id="nexus_stats_annot_text" class="nexus-stats-input-mini" placeholder="<?php esc_attr_e('Lancement...', 'nexus-stats-views'); ?>" title="<?php esc_attr_e('Texte de la note', 'nexus-stats-views'); ?>">
                        <button id="nexus_stats_add_annotation" class="nexus-stats-btn-mini" title="<?php esc_attr_e('Ajouter la note', 'nexus-stats-views'); ?>">+</button>
                    </div>

                    <div class="chart-switcher">
                        <button class="switcher-btn active" data-type="line" title="<?php esc_attr_e('Tendance', 'nexus-stats-views'); ?>"><span class="dashicons dashicons-chart-line"></span></button>
                        <button class="switcher-btn" data-type="bar" title="<?php esc_attr_e('Comparaison', 'nexus-stats-views'); ?>"><span class="dashicons dashicons-chart-bar"></span></button>
                    </div>
                </div>
                <div class="nexus-stats-card-body chart-wrapper">
                    <canvas id="nexusStatsMainChart"></canvas>
                </div>
            </div>

            <!-- Device Breakdown (Doughnut) -->
            <div class="nexus-stats-card nexus-stats-device-card">
                <div class="nexus-stats-card-header">
                    <span class="dashicons dashicons-smartphone"></span> <?php esc_html_e('Mobile vs Desktop', 'nexus-stats-views'); ?>
                </div>
                <div class="nexus-stats-card-body chart-wrapper doughnut-wrapper">
                    <canvas id="nexusStatsDeviceChart"></canvas>
                </div>
            </div>

            <!-- Traffic Sources (Donut) -->
            <div class="nexus-stats-card nexus-stats-sources-card">
                <div class="nexus-stats-card-header">
                    <span class="dashicons dashicons-networking"></span> <?php esc_html_e('Origine du Trafic', 'nexus-stats-views'); ?>
                </div>
                <div class="nexus-stats-card-body chart-wrapper doughnut-wrapper">
                    <canvas id="nexusStatsSourcesChart"></canvas>
                </div>
            </div>

            <!-- Geolocation (World Map & List) -->
            <div class="nexus-stats-card nexus-stats-geo-card">
                <div class="nexus-stats-card-header">
                    <span class="dashicons dashicons-admin-site-alt3"></span> <?php esc_html_e('Géolocalisation', 'nexus-stats-views'); ?>
                </div>
                <div class="nexus-stats-card-body p-0">
                    <div id="nexus_stats_world_map" class="nexus-stats-world-map"></div>
                    <ul class="nexus-stats-list" id="nexus_stats_top_countries">
                        <li class="nexus-stats-list-item empty"><?php esc_html_e('Chargement...', 'nexus-stats-views'); ?></li>
                    </ul>
                </div>
            </div>

            <!-- Top Referrers -->
            <div class="nexus-stats-card nexus-stats-list-card">
                <div class="nexus-stats-card-header">
                    <span class="dashicons dashicons-external"></span> <?php esc_html_e('Sites Référents & Engagement', 'nexus-stats-views'); ?>
                </div>
                <div class="nexus-stats-card-body p-0">
                    <ul class="nexus-stats-list" id="nexus_stats_top_referrers">
                        <li class="nexus-stats-list-item empty"><?php esc_html_e('Chargement...', 'nexus-stats-views'); ?></li>
                    </ul>
                </div>
            </div>

            <!-- Top Languages -->
            <div class="nexus-stats-card nexus-stats-list-card">
                <div class="nexus-stats-card-header">
                    <span class="dashicons dashicons-translation"></span> <?php esc_html_e('Langues des Navigateurs', 'nexus-stats-views'); ?>
                </div>
                <div class="nexus-stats-card-body p-0">
                    <ul class="nexus-stats-list" id="nexus_stats_top_languages">
                        <li class="nexus-stats-list-item empty"><?php esc_html_e('Chargement...', 'nexus-stats-views'); ?></li>
                    </ul>
                </div>
            </div>

            <!-- Top Content Lists -->
            <div class="nexus-stats-card nexus-stats-list-card">
                <div class="nexus-stats-card-header">
                    <span class="dashicons dashicons-admin-post"></span> <?php esc_html_e('Top Articles', 'nexus-stats-views'); ?>
                </div>
                <div class="nexus-stats-card-body p-0">
                    <ul class="nexus-stats-list" id="nexus_stats_top_posts">
                        <li class="nexus-stats-list-item empty"><?php esc_html_e('Chargement...', 'nexus-stats-views'); ?></li>
                    </ul>
                </div>
            </div>

            <div class="nexus-stats-card nexus-stats-list-card">
                <div class="nexus-stats-card-header">
                    <span class="dashicons dashicons-admin-page"></span> <?php esc_html_e('Top Pages', 'nexus-stats-views'); ?>
                </div>
                <div class="nexus-stats-card-body p-0">
                    <ul class="nexus-stats-list" id="nexus_stats_top_pages">
                        <li class="nexus-stats-list-item empty"><?php esc_html_e('Chargement...', 'nexus-stats-views'); ?></li>
                    </ul>
                </div>
            </div>

        </div> <!-- /.nexus-stats-bento-grid -->

    </div>
</div>
