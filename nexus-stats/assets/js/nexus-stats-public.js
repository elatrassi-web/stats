document.addEventListener('DOMContentLoaded', function() {
    // Les données passées via wp_localize_script
    const data = window.nexusStatsData;
    if (!data || !data.postID || !data.restUrl) return;

    const postID = data.postID;
    const restUrl = data.restUrl;
    const refreshRate = data.refreshRate || 60000;
    const nonce = data.nonce;
    const now = Date.now();

    // 1. Détection de l'appareil (très simple)
    const device = window.innerWidth <= 768 ? 'mobile' : 'desktop';

    // 2. Gestion de l'ID Visiteur (RGPD Strict = Pas de LocalStorage)
    let vid;
    if (data.gdprStrict === 'yes') {
        vid = sessionStorage.getItem('nexus_stats_visitor_id');
        if (!vid) {
            vid = 'anon_' + Math.random().toString(36).substr(2, 9) + Date.now().toString(36);
            sessionStorage.setItem('nexus_stats_visitor_id', vid);
        }
    } else {
        vid = localStorage.getItem('nexus_stats_visitor_id');
        if (!vid) {
            vid = 'vid_' + Math.random().toString(36).substr(2, 9) + Date.now().toString(36);
            localStorage.setItem('nexus_stats_visitor_id', vid);
        }
    }

    // Fonction utilitaire pour envoyer des requêtes POST fetch
    const sendPostRequest = async (endpoint, payload) => {
        try {
            await fetch(restUrl + endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce
                },
                body: JSON.stringify(payload)
            });
        } catch (error) {
            console.error('MyAngers Tracking Error:', error);
        }
    };

    // 3. TRACKING GLOBAL (1 vue max par minute pour un article donné)
    if (!window.nexusStatsViewFired) {
        window.nexusStatsViewFired = true;
        const lastView = sessionStorage.getItem('nexus_stats_view_time_' + postID);

        if (!lastView || (now - parseInt(lastView)) >= 60000) {
            sessionStorage.setItem('nexus_stats_view_time_' + postID, now);
            sendPostRequest('/track', {
                post_id: postID,
                visitor_id: vid,
                device: device,
                referrer: document.referrer || ''
            });
        }

        // Capter le temps de chargement une seule fois après la vue
        window.addEventListener('load', function() {
            setTimeout(function() {
                let loadTime = 0;
                if (window.performance && performance.getEntriesByType) {
                    const navEntries = performance.getEntriesByType("navigation");
                    if (navEntries.length > 0) {
                        loadTime = Math.round(navEntries[0].loadEventEnd);
                    }
                }
                if (loadTime > 0) {
                    sendPostRequest('/metrics/update', { post_id: postID, visitor_id: vid, load_time: loadTime, scroll: 0 });
                }
            }, 500);
        });
    }

    // 4. TRACKING EN DIRECT (Heartbeat)
    function pingLiveServer() {
        if (document.visibilityState === 'visible') {
            sendPostRequest('/live/ping', {
                visitor_id: vid,
                post_id: postID
            });
        }
    }

    pingLiveServer(); // Premier ping
    const pingInterval = setInterval(pingLiveServer, refreshRate);

    // 5. TEMPS DE LECTURE (Qualité de vue)
    let readTimeSeconds = 0;
    const readTimeInterval = setInterval(() => {
        if (document.visibilityState === 'visible') {
            readTimeSeconds += 10; // Incrémente toutes les 10 secondes
            // On envoie le temps de lecture toutes les 30 secondes pour ne pas spammer
            if (readTimeSeconds > 0 && readTimeSeconds % 30 === 0) {
                sendPostRequest('/read-time', {
                    post_id: postID,
                    visitor_id: vid,
                    time: readTimeSeconds
                });
            }
        }
    }, 10000);

    // 6. SORTIE (Beacon lors de la fermeture de l'onglet/page)
    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'hidden') {
            // Utiliser sendBeacon car fetch risque d'être annulé quand la page se décharge
            const beaconUrl = restUrl + '/live/exit';
            const payload = JSON.stringify({ visitor_id: vid });
            // Certains serveurs WordPress requièrent le nonce même pour un exit rapide si l'API est restreinte
            // Mais notre endpoint est ouvert (__return_true)
            const blob = new Blob([payload], { type: 'application/json' });
            navigator.sendBeacon(beaconUrl, blob);
        }
    });

    // 7. TRACKING DES LIENS SORTANTS
    if (data.trackOutbound === 'yes') {
        document.addEventListener('click', function(e) {
            const target = e.target.closest('a');
            if (target && target.href && !target.href.includes(window.location.hostname)) {
                // Outbound link clicked, use sendBeacon for reliability
                const beaconUrl = restUrl + '/outbound/track';
                const payload = JSON.stringify({ url: target.href });
                const blob = new Blob([payload], { type: 'application/json' });
                navigator.sendBeacon(beaconUrl, blob);
            }
        });
    }

    // 8. TRACKING SCROLL DEPTH
    if (data.trackScroll === 'yes') {
        let maxScroll = 0;
        let lastReportedScroll = 0;
        let debounceTimer;

        window.addEventListener('scroll', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function() {
                const scrollHeight = document.documentElement.scrollHeight;
                const scrollTop = document.documentElement.scrollTop || document.body.scrollTop;
                const clientHeight = document.documentElement.clientHeight;

                const percent = Math.round((scrollTop + clientHeight) / scrollHeight * 100);

                if (percent > maxScroll) {
                    maxScroll = percent;
                    let toReport = 0;
                    if (maxScroll >= 25 && lastReportedScroll < 25) toReport = 25;
                    else if (maxScroll >= 50 && lastReportedScroll < 50) toReport = 50;
                    else if (maxScroll >= 75 && lastReportedScroll < 75) toReport = 75;
                    else if (maxScroll >= 100 && lastReportedScroll < 100) toReport = 100;

                    if (toReport > 0) {
                        lastReportedScroll = toReport;
                        sendPostRequest('/metrics/update', { post_id: postID, visitor_id: vid, scroll: toReport, load_time: 0 });
                    }
                }
            }, 500);
        });
    }

    // 9. HEATMAP: TRACKING DES CLICS (A et BUTTON)
    document.addEventListener('click', function(e) {
        const target = e.target.closest('a, button');
        if (target) {
            // Générer un sélecteur CSS simple pour identifier l'élément
            let selector = target.tagName.toLowerCase();
            if (target.id) {
                selector += '#' + target.id;
            } else if (target.className) {
                selector += '.' + target.className.trim().replace(/\s+/g, '.');
            } else if (target.href) {
                // Pour les liens sans classe, on utilise le href
                selector += '[href="' + target.getAttribute('href') + '"]';
            } else if (target.innerText) {
                // Dernier recours: le texte
                selector += ':contains("' + target.innerText.substring(0, 15).trim() + '")';
            }

            sendPostRequest('/clicks/track', {
                post_id: postID,
                selector: selector
            });
        }
    });
});