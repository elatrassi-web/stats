document.addEventListener('DOMContentLoaded', function() {
    if (!window.nexusStatsHeatmapData) return;

    const data = window.nexusStatsHeatmapData;
    const postID = data.postID;
    const restUrl = data.restUrl;
    const nonce = data.nonce;

    // Helper: Find element by custom CSS selector pseudo :contains
    // Since document.querySelector doesn't support :contains natively
    function findElementBySelector(selector) {
        if (selector.includes(':contains("')) {
            const parts = selector.split(':contains("');
            const tag = parts[0];
            const text = parts[1].replace('")', '');
            const elements = document.querySelectorAll(tag);
            for (let el of elements) {
                if (el.innerText && el.innerText.includes(text)) {
                    return el;
                }
            }
            return null;
        }

        try {
            return document.querySelector(selector);
        } catch(e) {
            return null;
        }
    }

    // Fetch heatmap data
    fetch(`${restUrl}/clicks/data?post_id=${postID}`, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': nonce
        }
    })
    .then(res => res.json())
    .then(response => {
        if (response.success && response.data && response.data.length > 0) {

            // Add Global CSS for Heatmap Badges
            const style = document.createElement('style');
            style.innerHTML = `
                .nexus-stats-heatmap-badge {
                    position: absolute;
                    top: -10px;
                    right: -10px;
                    background: #ff0055;
                    color: white;
                    font-size: 11px;
                    font-weight: bold;
                    padding: 2px 6px;
                    border-radius: 10px;
                    z-index: 999999;
                    box-shadow: 0 0 10px rgba(255, 0, 85, 0.8);
                    pointer-events: none;
                    animation: nexus-pulse-red 2s infinite;
                }
                @keyframes nexus-pulse-red {
                    0% { box-shadow: 0 0 0 0 rgba(255, 0, 85, 0.7); }
                    70% { box-shadow: 0 0 0 6px rgba(255, 0, 85, 0); }
                    100% { box-shadow: 0 0 0 0 rgba(255, 0, 85, 0); }
                }
            `;
            document.head.appendChild(style);

            // Overlay the badges
            response.data.forEach(item => {
                const selector = item.element_selector;
                const count = item.click_count;

                const el = findElementBySelector(selector);

                if (el) {
                    // Ensure parent has position relative for absolute badge
                    const style = window.getComputedStyle(el);
                    if (style.position === 'static') {
                        el.style.position = 'relative';
                    }

                    const badge = document.createElement('div');
                    badge.className = 'nexus-stats-heatmap-badge';
                    const i18n = data.i18n || {};
                    const clicksText = i18n.clicks || 'clics';
                    const clicksTitle = (i18n.clicks_on_element || 'Nexus Stats: %s clics sur cet élément').replace('%s', count);

                    badge.innerText = count + ' ' + clicksText;
                    badge.title = clicksTitle;

                    el.appendChild(badge);
                }
            });
        }
    })
    .catch(err => console.error("Nexus Stats Heatmap Error:", err));
});