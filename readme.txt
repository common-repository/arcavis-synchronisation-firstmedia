=== Arcavis-Synchronisation für WooCommerce - FirstMedia ===

Contributors: firstmedia
Tags: WooCommerce, Webshop, Point Of Sale, Pos, Arcavis, Kasse
Author URI: https://www.firstmedia.swiss
Plugin URI: https://arcavis.firstmedia.swiss/
Author: FirstMedia Solutions GmbH
Requires at least: 4.6
Tested up to: 5.5.1
Stable tag: 2.2.19
Requires PHP: 5.2.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

== Description ==

Arcavis-Synchronisation für WooCommerce von FirstMedia.
Dieses Plugin ermöglicht die Synchronisation von Produkten, Bildern, Kategorien, Gutscheinen, Bestellungen und Zahlungen zwischen WooCommerce und Arcavis.

== Installation ==

1. Download and Activate the Plugin in your WooCommerce WordPress Installation
2. Go to Settings -> Arcavis Settings and Enter your Arcavis Credentials
3. Hit Save and follow the further Instruvctions

== Upgrade Notice ==

== Screenshots ==

1. Übersicht der Arcavis-Synchronisation
2. Ausführen einer manuellen Synchronisation
3. Einstellungen

== Changelog ==
= 2.2.19 =
-Several minor fixes

= 2.2.11 =
-Option to choose allowed stock source ids (Settings -> Extended)
-Stock managment Bugfix
-Resyncing payments optimized
-Fees now also get transmitted to arcavis.
Options can be set under settings -> Additional Config JSON under fees -> "fee-name": arcavis-id
-Bugfix regarding Variation stock sync

= 2.2.6 =
-Added extended configurations
-Added WordPress Actions that trigger after cron job:
    arcavis_after_minutes_sync
    arcavis_after_stock_sync
    arcavis_after_daily_sync
-Minor sync Bugfixes

= 2.2.5 =
Bugfix: Payment synchronisations improved

= 2.2.4 =
New Sync mode: Connect and merge Arcavis and WooCommerce-Products based on SKU or Name
Extended Options in Settings: Do not sync description and do not sync images.

= 2.2.3 =
Fixed some bugs with payments (Regarding TWINT Module)

== Frequently Asked Questions ==

== Donations ==