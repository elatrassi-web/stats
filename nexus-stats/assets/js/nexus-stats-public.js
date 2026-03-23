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

    // 2. Gestion de l'ID Visiteur
    let vid = localStorage.getItem('nexus_stats_visitor_id');
    if (!vid) {
        vid = 'vid_' + Math.random().toString(36).substr(2, 9) + Date.now().toString(36);
        localStorage.setItem('nexus_stats_visitor_id', vid);
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
                device: device
            });
        }
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
});