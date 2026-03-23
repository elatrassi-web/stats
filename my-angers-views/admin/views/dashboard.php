<div class="wrap my-angers-wrap">
    <div class="my-angers-container">

        <div class="my-angers-header">
            <h1 class="my-angers-title">Vue d'ensemble - Trend 2026</h1>
            <div class="my-angers-actions">
                <select id="my_angers_time_filter" class="my-angers-select">
                    <option value="today">Aujourd'hui</option>
                    <option value="30min">30 Dernières Minutes</option>
                    <option value="yesterday">Hier</option>
                    <option value="7days" selected>7 Derniers Jours</option>
                    <option value="30days">30 Derniers Jours</option>
                    <option value="last_month">Mois Précédent</option>
                </select>
            </div>
        </div>

        <div class="my-angers-bento-grid">

            <!-- Cards (Metrics) -->
            <div class="my-angers-card my-angers-metric-card">
                <div class="my-angers-card-header">
                    <span class="dashicons dashicons-visibility"></span> Vues Totales
                </div>
                <div class="my-angers-card-body">
                    <h2 class="my-angers-stat" id="highlight-views">--</h2>
                </div>
            </div>

            <div class="my-angers-card my-angers-metric-card">
                <div class="my-angers-card-header">
                    <span class="dashicons dashicons-groups"></span> Visiteurs Uniques
                </div>
                <div class="my-angers-card-body">
                    <h2 class="my-angers-stat" id="highlight-visitors">--</h2>
                </div>
            </div>

            <div class="my-angers-card my-angers-metric-card my-angers-live-card">
                <div class="my-angers-card-header">
                    <span class="live-dot pulse-emerald"></span> Visiteurs En Direct
                </div>
                <div class="my-angers-card-body">
                    <h2 class="my-angers-stat pulse-text" id="highlight-live">0</h2>
                </div>
            </div>

            <!-- Main Chart Area -->
            <div class="my-angers-card my-angers-chart-card">
                <div class="my-angers-card-header chart-header-actions">
                    <span><span class="dashicons dashicons-chart-area"></span> Évolution des vues</span>
                    <div class="chart-switcher">
                        <button class="switcher-btn active" data-type="line" title="Tendance"><span class="dashicons dashicons-chart-line"></span></button>
                        <button class="switcher-btn" data-type="bar" title="Comparaison"><span class="dashicons dashicons-chart-bar"></span></button>
                    </div>
                </div>
                <div class="my-angers-card-body chart-wrapper">
                    <canvas id="myAngersMainChart"></canvas>
                </div>
            </div>

            <!-- Device Breakdown (Doughnut) -->
            <div class="my-angers-card my-angers-device-card">
                <div class="my-angers-card-header">
                    <span class="dashicons dashicons-smartphone"></span> Mobile vs Desktop
                </div>
                <div class="my-angers-card-body chart-wrapper doughnut-wrapper">
                    <canvas id="myAngersDeviceChart"></canvas>
                </div>
            </div>

            <!-- Top Content Lists -->
            <div class="my-angers-card my-angers-list-card">
                <div class="my-angers-card-header">
                    <span class="dashicons dashicons-admin-post"></span> Top Articles
                </div>
                <div class="my-angers-card-body p-0">
                    <ul class="my-angers-list" id="my_angers_top_posts">
                        <li class="my-angers-list-item empty">Chargement...</li>
                    </ul>
                </div>
            </div>

            <div class="my-angers-card my-angers-list-card">
                <div class="my-angers-card-header">
                    <span class="dashicons dashicons-admin-page"></span> Top Pages
                </div>
                <div class="my-angers-card-body p-0">
                    <ul class="my-angers-list" id="my_angers_top_pages">
                        <li class="my-angers-list-item empty">Chargement...</li>
                    </ul>
                </div>
            </div>

        </div> <!-- /.my-angers-bento-grid -->

    </div>
</div>
