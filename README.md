# 💱 Currency Rate Auto Update for PipraPay

**Plugin Name:** FX Rate Sync  
**Description:** Automatically updates currency rates for [PipraPay](https://piprapay.com). Supports [ExchangeRate-API (v6)](https://www.exchangerate-api.com) and [Free Currency Exchange Rates API (jsDelivr)](https://github.com/fawazahmed0/exchange-api) & auto update 4x every day at 00:01 /06:01 / 12:01 /18:01. (server time) using ExchangeRate-API or jsDelivr-API.  
**Version:** 1.0.0  
**Author:** [Fattain Naime](https://iamnaime.info.bd)  
**License:** [GPL-2.0+](https://www.gnu.org/licenses/gpl-2.0.html)  
**Requires PHP:** 7.4+  
**Requires at least PipraPay version:** 1.0.0  
**Plugin URI:** [GitHub Repository](https://github.com/fattain-naime/Currency-Rate-Auto-Update-for-PipraPay)

---

## 📌 Features
- **Daily Auto Update** – Automatically updates all currency conversion rates every day after 6 hours (site timezone).
- **Instant Update** – Click "Force Update" to refresh rates immediately.
- **Base Currency Aware** – Uses PipraPay's default currency as the base (rate = 1).
- **ExchangeRate-API Integration** – Pulls rates securely from [ExchangeRate-API](https://www.exchangerate-api.com/) and [Free Currency Exchange Rates API (jsDelivr)](https://github.com/fawazahmed0/exchange-api).
- **Cron Integration** – Works with PipraPay's built-in cron system (`?cron`).

---

## 📥 Installation

1. **Download** the plugin from the [latest release](https://github.com/fattain-naime/Currency-Rate-Auto-Update-for-PipraPay/releases/latest).
2. **Upload** the plugin folder to your PipraPay `Plugin` section.
3. **Activate** the plugin from PipraPay’s module settings.
4. Go to **Admin Dashboard → Module → Currency Rate Auto Update**.
5. **Enter your API key** from [ExchangeRate-API](https://www.exchangerate-api.com/) and click **Save Changes**.
6. To update instantly, click **Force Update**.

---

## ⚙️ How It Works
- Get your default currency.
- Sets that currency’s rate to `1.0000`.
- Fetches fresh rates from ExchangeRate-API.
- Updates `PipraPay Currency` for all currencies found.
- Skips base currency in API updates.
- Runs daily at `00:01` automatically via PipraPay cron:  
  ```bash
  curl -s https://your-site.com/?cron >/dev/null 2>&1
