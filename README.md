# 💱 Currency Rate Auto Update for PipraPay

**Plugin Name:** Currency Rate Auto Update  
**Description:** Automatically updates currency conversion rates daily using [ExchangeRate-API](https://www.exchangerate-api.com/). The base currency is always your PipraPay `Default Currency` (rate = 1).  
**Version:** 1.0.0  
**Author:** [Fattain Naime](https://iamnaime.info.bd/)  
**License:** [GPL-2.0+](https://www.gnu.org/licenses/gpl-2.0.html)  
**Requires PHP:** 7.4+  
**Requires at least PipraPay version:** 1.0.0  
**Plugin URI:** [GitHub Repository](https://github.com/fattain-naime/Currency-Rate-Auto-Update-for-PipraPay)

---

## 📌 Features
- **Daily Auto Update** – Automatically updates all currency conversion rates every day at `00:01` (site timezone).
- **Instant Update** – Click "Update Currency Rate Now" to refresh rates immediately.
- **Base Currency Aware** – Uses PipraPay's default currency as the base (rate = 1).
- **ExchangeRate-API Integration** – Pulls rates securely from [ExchangeRate-API](https://www.exchangerate-api.com/).
- **Cron Integration** – Works with PipraPay's built-in cron system (`?cron`).

---

## 📥 Installation

1. **Download** the plugin from the [latest release](https://github.com/fattain-naime/Currency-Rate-Auto-Update-for-PipraPay/releases/latest).
2. **Upload** the plugin folder to your PipraPay `Plugin` section.
3. **Activate** the plugin from PipraPay’s module settings.
4. Go to **Admin Dashboard → Module → Currency Rate Auto Update**.
5. **Enter your API key** from [ExchangeRate-API](https://www.exchangerate-api.com/) and click **Save Changes**.
6. To update instantly, click **Update Currency Rate Now**.

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
