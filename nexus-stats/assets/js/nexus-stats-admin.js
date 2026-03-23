document.addEventListener("DOMContentLoaded", function() {
    // Vérification des variables globales et des canvas
    if (!window.nexusStatsAdminData) return;

    const restUrl = nexusStatsAdminData.restUrl;
    const nonce = nexusStatsAdminData.nonce;
    const refreshRate = nexusStatsAdminData.refreshRate || 60000;

    const mainCanvas = document.getElementById('nexusStatsMainChart');
    const deviceCanvas = document.getElementById('nexusStatsDeviceChart');
    if (!mainCanvas) return; // Nous ne sommes pas sur la page de dashboard

    const mainCtx = mainCanvas.getContext('2d');
    const deviceCtx = deviceCanvas ? deviceCanvas.getContext('2d') : null;

    let mainChart = null;
    let deviceChart = null;
    let currentChartType = 'line'; // Par défaut

    const filterSelect = document.getElementById('nexus_stats_time_filter');
    const switcherBtns = document.querySelectorAll('.switcher-btn');

    // Nouveaux éléments pour Custom Dates & Theme
    const customDatesWrapper = document.getElementById('nexus_stats_custom_dates_wrapper');
    const inputDateStart = document.getElementById('nexus_stats_date_start');
    const inputDateEnd = document.getElementById('nexus_stats_date_end');
    const btnApplyDates = document.getElementById('nexus_stats_apply_dates');
    const btnThemeToggle = document.getElementById('nexus_stats_theme_toggle');
    const wrapContainer = document.querySelector('.nexus-stats-wrap');

    let currentTheme = nexusStatsAdminData.theme || 'dark';

    // Variables de couleurs dynamiques
    let isDark, gridColor, emeraldColor, emeraldColorBg, pointColor, tooltipBg, tooltipTitle, tooltipBorder;

    function updateChartColors() {
        isDark = currentTheme === 'dark';

        // Configuration globale Chart.js
        Chart.defaults.color = isDark ? '#a0a0a0' : '#646970';
        Chart.defaults.font.family = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif';

        gridColor = isDark ? 'rgba(255, 255, 255, 0.05)' : 'rgba(0, 0, 0, 0.05)';
        emeraldColor = isDark ? '#00ff88' : '#00a32a';
        emeraldColorBg = isDark ? 'rgba(0, 255, 136, 0.1)' : 'rgba(0, 163, 42, 0.1)';
        pointColor = isDark ? '#121212' : '#ffffff';
        tooltipBg = isDark ? 'rgba(18, 18, 18, 0.9)' : 'rgba(255, 255, 255, 0.9)';
        tooltipTitle = isDark ? '#ffffff' : '#2c3338';
        tooltipBorder = isDark ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)';
    }

    updateChartColors(); // Init

    // Fonction de formatage (1 000 au lieu de 1000)
    const formatNumber = (num) => {
        return num.toLocaleString('fr-FR');
    };

    // Fonction de formatage du temps (secondes -> mm:ss)
    const formatTime = (seconds) => {
        if (!seconds || seconds <= 0) return '0s';
        const m = Math.floor(seconds / 60);
        const s = seconds % 60;
        return m > 0 ? `${m}m ${s}s` : `${s}s`;
    };

    // 1. Charger les stats globales du Dashboard
    function loadDashboardData() {
        const timeRange = filterSelect.value;
        let endpoint = `${restUrl}/stats/dashboard?time_range=${timeRange}`;

        if (timeRange === 'custom') {
            const start = inputDateStart.value;
            const end = inputDateEnd.value;
            if (start && end) {
                endpoint += `&custom_start=${start}&custom_end=${end}`;
            } else {
                return; // Ne rien charger si les dates ne sont pas remplies
            }
        }

        fetch(endpoint, {
            method: 'GET',
            headers: {
                'X-WP-Nonce': nonce,
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(res => {
            if (res.success) {
                const data = res.data;

                // Mettre à jour les KPIs textuels
                document.getElementById('highlight-views').innerText = formatNumber(data.total_views);
                document.getElementById('highlight-visitors').innerText = formatNumber(data.total_visitors);

                // --- Graphique Principal (Évolution / Barres) ---
                if (mainChart) mainChart.destroy();

                const mainChartConfig = {
                    type: currentChartType,
                    data: {
                        labels: data.chart_labels,
                        datasets: [{
                            label: 'Vues',
                            data: data.chart_values,
                            backgroundColor: currentChartType === 'line' ? emeraldColorBg : emeraldColor,
                            borderColor: emeraldColor,
                            borderWidth: 2,
                            fill: currentChartType === 'line', // Remplir sous la ligne
                            tension: 0.4, // Courbe douce
                            borderRadius: currentChartType === 'bar' ? 4 : 0,
                            pointBackgroundColor: pointColor,
                            pointBorderColor: emeraldColor,
                            pointHoverBackgroundColor: emeraldColor,
                            pointHoverBorderColor: pointColor,
                            pointRadius: currentChartType === 'line' ? 3 : 0,
                            pointHoverRadius: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: { stepSize: 1, precision: 0 },
                                grid: { color: gridColor, borderDash: [5, 5] },
                                border: { display: false }
                            },
                            x: {
                                grid: { display: false },
                                border: { display: false }
                            }
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: tooltipBg,
                                titleColor: tooltipTitle,
                                bodyColor: emeraldColor,
                                padding: 12,
                                borderColor: tooltipBorder,
                                borderWidth: 1,
                                cornerRadius: 8
                            }
                        }
                    }
                };
                mainChart = new Chart(mainCtx, mainChartConfig);

                // --- Graphique Appareils (Mobile vs Desktop) ---
                if (deviceCtx) {
                    if (deviceChart) deviceChart.destroy();

                    const mobileCount = data.device_stats.mobile || 0;
                    const desktopCount = data.device_stats.desktop || 0;
                    const hasDeviceData = mobileCount > 0 || desktopCount > 0;

                    deviceChart = new Chart(deviceCtx, {
                        type: 'doughnut',
                        data: {
                            labels: ['Mobile', 'Desktop'],
                            datasets: [{
                                data: hasDeviceData ? [mobileCount, desktopCount] : [1, 1], // Fake data si vide pour montrer l'anneau
                                backgroundColor: hasDeviceData ? [isDark ? '#0088ff' : '#2271b1', emeraldColor] : [gridColor, gridColor],
                                hoverOffset: 4,
                                borderWidth: 0,
                                cutout: '75%'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { position: 'bottom', labels: { usePointStyle: true, padding: 20 } },
                                tooltip: {
                                    enabled: hasDeviceData,
                                    callbacks: {
                                        label: function(context) {
                                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                            const val = context.raw;
                                            const percent = Math.round((val / total) * 100);
                                            return ` ${context.label}: ${formatNumber(val)} (${percent}%)`;
                                        }
                                    }
                                }
                            }
                        }
                    });
                }

                // --- Listes Top Contenus ---
                const createListHtml = (items) => {
                    if (!items || items.length === 0) return '<li class="nexus-stats-list-item empty">Aucune donnée pour cette période.</li>';
                    let html = '';
                    items.forEach(function(item) {
                        html += `
                        <li class="nexus-stats-list-item">
                            <a href="${item.edit_link}" class="nexus-stats-list-link" target="_blank" title="Modifier ${item.title}">${item.title}</a>
                            <div class="nexus-stats-list-meta">
                                <span class="nexus-stats-read-time" title="Temps de lecture moyen">
                                    <span class="dashicons dashicons-clock"></span> ${formatTime(item.avg_read_time)}
                                </span>
                                <span class="nexus-stats-badge emerald" title="Vues Totales">
                                    ${formatNumber(item.views)}
                                </span>
                            </div>
                        </li>`;
                    });
                    return html;
                };

                document.getElementById('nexus_stats_top_posts').innerHTML = createListHtml(data.top_posts);
                document.getElementById('nexus_stats_top_pages').innerHTML = createListHtml(data.top_pages);
            }
        })
        .catch(err => console.error("Erreur chargement Dashboard Nexus Stats:", err));
    }

    // 2. Mettre à jour UNIQUEMENT le compteur "En Direct"
    function fetchLiveCount() {
        fetch(`${restUrl}/stats/live`, {
            method: 'GET',
            headers: { 'X-WP-Nonce': nonce }
        })
        .then(response => response.json())
        .then(res => {
            if (res.success) {
                const liveEl = document.getElementById('highlight-live');
                if (liveEl) {
                    // Animation simple si le chiffre change
                    if (liveEl.innerText !== res.data.toString()) {
                        liveEl.style.opacity = 0.5;
                        setTimeout(() => {
                            liveEl.innerText = res.data;
                            liveEl.style.opacity = 1;
                        }, 150);
                    }
                }
            }
        });
    }

    // 3. Événements

    // Changement de période
    if (filterSelect) {
        filterSelect.addEventListener('change', function() {
            if (this.value === 'custom') {
                customDatesWrapper.style.display = 'flex';
                // Ne pas charger immédiatement, attendre le clic sur "Appliquer"
            } else {
                customDatesWrapper.style.display = 'none';
                loadDashboardData();
            }
        });
    }

    // Appliquer dates personnalisées
    if (btnApplyDates) {
        btnApplyDates.addEventListener('click', loadDashboardData);
    }

    // Changement de Thème (Toggle)
    if (btnThemeToggle) {
        btnThemeToggle.addEventListener('click', function(e) {
            e.preventDefault();
            // Toggle Theme value
            currentTheme = currentTheme === 'dark' ? 'light' : 'dark';

            // Mettre à jour la classe CSS du container
            wrapContainer.classList.remove('nexus-stats-theme-dark', 'nexus-stats-theme-light');
            wrapContainer.classList.add(`nexus-stats-theme-${currentTheme}`);

            // Mettre à jour les couleurs Chart.js
            updateChartColors();

            // Re-dessiner les graphiques avec les nouvelles couleurs
            loadDashboardData();

            // Envoyer la préférence au serveur pour la sauvegarder
            fetch(`${restUrl}/theme`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce
                },
                body: JSON.stringify({ theme: currentTheme })
            });
        });
    }

    // Switcher de type de graphique (Ligne vs Barre)
    switcherBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            // Retirer la classe active de tous
            switcherBtns.forEach(b => b.classList.remove('active'));
            // Ajouter active au bouton cliqué
            this.classList.add('active');

            const type = this.getAttribute('data-type');
            if (type && type !== currentChartType) {
                currentChartType = type;
                loadDashboardData(); // Recharger le graphique avec le nouveau type
            }
        });
    });

    // 4. Initialisation
    loadDashboardData();
    fetchLiveCount();

    // Actualisation du live selon les réglages
    setInterval(fetchLiveCount, refreshRate);
});