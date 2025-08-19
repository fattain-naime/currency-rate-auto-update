=== FX Rate Sync ===
Developer: Fattain Naime
Tags: currency, exchange rate, forex, automation, updater, exchangerate-api, cdn jddelivr, piprapay
required: PipraPay Core
Requires at least: 1.0.0
Tested up to: 1.0.0
Stable tag: 1.0.0
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==

Automatically updates currency rates for PipraPay. Supports ExchangeRate-API (v6) and Free Currency Exchange Rates API (jsDelivr) & auto update 4x every day at 00:01 /06:01 / 12:01 /18:01. (server time) using ExchangeRate-API or jsDelivr-API.

== Admin Settings:==
* Module Enable/Disable
* Currency API: `ExchangeRate-API of Free Currency Exchange Rates`
* API Key (input)
* Last Update (DD/MM/YYYY, 24h)
* Next Update (DD/MM/YYYY, 24h)
* Buttons: Save | Force Update
* Debug Panel
* Cron Job 

== Changelog ==

= 1.1.0 =
* Added: Admin ON/OFF switch to globally enable/disable auto updates.
* Added: Second provider Free Currency Exchange Rates API (jsDelivr). No API key required.
* Added: Provider selector with contextual help links.
* Added: Collapsible Debug info panel: counts updated/failed, list of failures, API error snippets, provider/source URL, cron status & last run time, and a Force run cron button.
* Changed: Update cadence to 4x daily at 00:01 /06:01 / 12:01 /18:01.
* Changed: "Update Currency Rate Now" → "Force Update".
* Fixed: Normalized several currency codes.
* Internal: Defensive parsing of jsDelivr responses; uppercase normalization of currency codes; lightweight run logging

= 1.0.0 =
* Initial release
