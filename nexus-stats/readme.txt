=== Nexus Stats ===
Contributors: elatrassi
Donate link: https://www.linkedin.com/in/elatrassi/
Tags: analytics, statistics, visitors, heatmap, tracking
Requires at least: 5.6
Tested up to: 6.9
Stable tag: 1.0.0
Requires PHP: 7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Advanced analytics: AJAX counter, Unique visitors, beautiful Dashboard, and secure LIVE Visitor Tracking. Lightweight and GDPR ready.

== Description ==

**Nexus Stats** is the next-generation WordPress traffic analysis tool.
Designed to be ultra-lightweight and not slow down your site, it advantageously replaces heavy solutions by offering a modern dashboard (Bento Grid / Glassmorphism style) directly in your WordPress administration interface.

= Key Features (2026 Trend) =
*   **Real Time (Live):** See exactly how many visitors are on your site right now, with a green pulsating dot in your admin bar.
*   **Minimalist Heatmap:** Visualize the most clicked areas of your site directly from the front-end (reserved for administrators).
*   **"Total Privacy" Mode (GDPR Ready):** Strict anonymization of IP addresses and total removal of cookies/localStorage for maximum compliance.
*   **"Eco-Design" Mode (Green IT):** Intelligently ignores indexing robots to relieve your database.
*   **Traffic Origin & WooCommerce:** Discover where your visitors come from (Search, Social, Direct) and link these sources directly to your WooCommerce revenue.
*   **Content Health (Core Web Vitals):** Measure reading time, scroll depth, and the actual loading time of your pages.
*   **Downtime Alerts:** Receive an automatic email if your traffic drops to zero abnormally compared to your usual average.
*   **Client-Ready:** Share a secure link to your statistics with your clients, without giving them access to your WordPress back-office.
*   **PDF Export:** Generate professional monthly reports with one click.

== Installation ==

1. Download the `.zip` file of the plugin.
2. Go to your WordPress administration > **Plugins** > **Add New**.
3. Click on **Upload Plugin**, choose the zip file and click on **Install Now**.
4. Activate the plugin via the WordPress "Plugins" menu.
5. Go to the new **Nexus Stats** menu to view your data and configure your settings.

== Frequently Asked Questions ==

= Does this plugin slow down my site? =
No. Nexus Stats was designed for performance ("Eco-Design"). It uses the native WordPress REST API (much faster than the old `admin-ajax.php`) and uses `navigator.sendBeacon()` to avoid blocking page loads.

= Is it GDPR compliant? =
Yes. By activating the "Total Privacy" mode in the settings, the plugin does not drop any cookies, does not use LocalStorage, and hashes all IP addresses before processing them.

= How does the share link (Client-Ready) work? =
In the plugin settings, set a "Share Token" (e.g., `myclient2026`). You can then send the URL `yoursite.com/?nexus_stats_share=myclient2026` to your client. They will see the statistics dashboard without needing to log in.

== Screenshots ==

1. Main dashboard with evolution chart and goals.
2. View of WooCommerce conversion statistics and traffic origin.
3. Settings panel with GDPR, Eco-design, and Alerts options.

== Changelog ==

= 1.0.0 =
* Initial launch of "Nexus Stats" (Total overhaul of the old My Angers plugin).
* New modular MVC architecture.
* Completely redesigned administration interface (Bento Grid, Dark/Light Mode).
* Added scroll depth tracking.
* Added tracking of clicks on outbound links.
* WooCommerce integration (Revenue / Traffic source link).
* Intelligent 404 error detection.
* Abnormal traffic drop alerts (Downtime).
* Added PDF export.
* Minimalist Heatmap module on the front-end.
* Full translations (English, Spanish, German, Russian, Arabic, Italian, Dutch).
