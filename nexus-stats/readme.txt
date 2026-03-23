=== Nexus Stats ===
Contributors: elatrassi
Donate link: https://www.linkedin.com/in/elatrassi/
Tags: analytics, statistics, visitors, heatmap, tracking
Requires at least: 5.6
Tested up to: 6.9
Stable tag: 6.0
Requires PHP: 7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Statistiques avancées : Compteur AJAX, Visiteurs uniques, Tableau de bord complet et Suivi des Visiteurs EN DIRECT (Version Sécurisée Anti-Crash). Refonte 2026.

== Description ==

**Nexus Stats** est l'outil d'analyse de trafic WordPress nouvelle génération.
Conçu pour être ultra-léger et ne pas ralentir votre site, il remplace avantageusement les solutions lourdes en offrant un tableau de bord moderne (style Bento Grid / Glassmorphism) directement dans votre interface d'administration WordPress.

= Fonctionnalités Clés (Trend 2026) =
*   **Temps Réel (Live) :** Voyez exactement combien de visiteurs sont sur votre site en ce moment, avec un point de pulsation vert dans votre barre d'administration.
*   **Heatmap Minimaliste :** Visualisez les zones les plus cliquées de votre site directement depuis le front-end (réservé aux administrateurs).
*   **Mode "Confidentialité Totale" (RGPD Ready) :** Anonymisation stricte des adresses IP et suppression totale des cookies/localStorage pour une conformité maximale.
*   **Mode "Éco-Conception" (Green IT) :** Ignore intelligemment les robots d'indexation pour soulager votre base de données.
*   **Origine du Trafic & WooCommerce :** Découvrez d'où viennent vos visiteurs (Recherche, Social, Direct) et liez ces sources directement à vos revenus WooCommerce.
*   **Santé du Contenu (Core Web Vitals) :** Mesurez le temps de lecture, le taux de défilement (Scroll Depth) et le temps de chargement réel de vos pages.
*   **Alertes de Downtime :** Recevez un email automatique si votre trafic chute à zéro de manière anormale par rapport à votre moyenne habituelle.
*   **Client-Ready :** Partagez un lien sécurisé de vos statistiques à vos clients, sans leur donner accès à votre back-office WordPress.
*   **Export PDF :** Générez des rapports mensuels professionnels en un clic.

== Installation ==

1. Téléchargez le fichier `.zip` de l'extension.
2. Allez dans votre administration WordPress > **Extensions** > **Ajouter**.
3. Cliquez sur **Téléverser une extension**, choisissez le fichier zip et cliquez sur **Installer maintenant**.
4. Activez l'extension via le menu "Extensions" de WordPress.
5. Allez dans le nouveau menu **Nexus Stats** pour consulter vos données et configurer vos réglages.

== Frequently Asked Questions ==

= Est-ce que ce plugin ralentit mon site ? =
Non. Nexus Stats a été conçu pour la performance ("Éco-Conception"). Il utilise l'API REST native de WordPress (beaucoup plus rapide que l'ancien `admin-ajax.php`) et utilise `navigator.sendBeacon()` pour ne pas bloquer le chargement des pages.

= Est-il conforme au RGPD ? =
Oui. En activant le mode "Confidentialité Totale" dans les réglages, le plugin ne dépose aucun cookie, n'utilise pas le LocalStorage, et hache toutes les adresses IP avant de les traiter.

= Comment fonctionne le lien de partage (Client-Ready) ? =
Dans les réglages du plugin, définissez un "Jeton de partage" (ex: `monclient2026`). Vous pourrez alors envoyer l'URL `votre-site.com/?nexus_stats_share=monclient2026` à votre client. Il verra le tableau de bord des statistiques sans avoir besoin de se connecter.

== Screenshots ==

1. Tableau de bord principal avec le graphique d'évolution et les objectifs.
2. Vue des statistiques de conversion WooCommerce et de l'origine du trafic.
3. Panneau de réglages avec les options RGPD, Éco-conception et Alertes.

== Changelog ==

= 6.0 =
* Lancement initial de "Nexus Stats" (Refonte totale de l'ancien plugin My Angers).
* Nouvelle architecture modulaire MVC.
* Interface d'administration totalement repensée (Bento Grid, Dark/Light Mode).
* Ajout du suivi de profondeur de défilement (Scroll Depth).
* Ajout du suivi des clics sur les liens sortants (Outbound Links).
* Intégration WooCommerce (Liaison Revenus / Source de trafic).
* Détection intelligente des erreurs 404.
* Alertes de chute anormale de trafic (Downtime).
* Ajout de l'export PDF.
* Module de Heatmap minimaliste sur le front-end.
* Traductions complètes (Anglais, Espagnol, Allemand, Russe, Arabe, Italien, Néerlandais).
