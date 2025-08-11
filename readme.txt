=== Currency Rate Auto Update ===
Developer: Fattain Naime
Tags: currency, exchange rate, forex, automation, updater, exchangerate-api, piprapay
Requires at least: 1.0.0
Tested up to: 1.0.0
Stable tag: 1.0.0
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==

Automatically updates currency conversion rates in PipraPay every day at 00:01 (server time) using ExchangeRate-API (v6).
The base currency is taken from PipraPay's default currency. The base currency rate is always set to **1** and is **never** overwritten by the API.

*What it does:*
- Detects the default/base currency from PipraPay.
- Calls ExchangeRate-API: `GET https://v6.exchangerate-api.com/v6/YOUR-API-KEY/latest/{BASE}`.
- Updates `currency rate` for each currency code found in the table.
- Writes timestamps for **Last Update** and **Next Update** and shows them in the admin UI.
- Supports an instant **Force Update** button in the admin.

*Admin Settings:*
- Currency API: `ExchangeRate-API (v6)`
- API Key (input)
- Last Update (DD/MM/YYYY, 24h)
- Next Update (DD/MM/YYYY, 24h)
- Buttons: **Save** | **Update Currency Rate Now**

== Changelog ==

= 1.0.0 =
* Initial release