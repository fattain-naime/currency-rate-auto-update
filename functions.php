<?php
if (!defined('pp_allowed_access')) {
    die('Direct access not allowed');
}

/**
* Currency Rate Auto Update – core
* - DB creds from /pp-config.php (walk up to 8 levels).
* - Base currency = pp_settings.default_currency (id=1 preferred); its rate = 1.0.
* - Unknown currency codes become 0.0.
* - “Last Update” read from DB (MAX(pp_currency.created_at)).
* - “Next Update” calculated for next 00:01 in site timezone.
*/

define('CRAU_SLUG', 'currency-rate-auto-update');
define('CRAU_API_NAME', 'ExchangeRate-API (v6)');
define('CRAU_SCHEDULE_HOUR', 0); // 00:xx
define('CRAU_SCHEDULE_MIN', 1); // xx:01

// Optional lazy scheduler (kept harmless). Not used for display anymore.
if (function_exists('pp_get_plugin_setting') && function_exists('pp_update_plugin_setting')) {
    try {
        crau_maybe_run_schedule();
    } catch (Throwable $e) {}
}

function crau_maybe_run_schedule(): void {
    $settings = pp_get_plugin_setting(CRAU_SLUG) ?: [];
    $now = time();
    $next = isset($settings['next_update_unix']) ? (int)$settings['next_update_unix'] : 0;
    if ($next <= 0 || $now >= $next) {
        crau_update_all_rates(null);
        $settings['next_update_unix'] = crau_next_00_01_ts(crau_get_site_timezone_id());
        $settings['next_update_human'] = crau_human_dt($settings['next_update_unix'], crau_get_site_timezone_id());
        pp_update_plugin_setting(CRAU_SLUG, $settings);
    }
}

/**
* Update all currency rates.
* @param string|null $api_key_override Optional API key (used by ajax.php “Update Now”)
* @return array JSON-serializable result
*/
function crau_update_all_rates(?string $api_key_override = null): array {
    // API key
    $api_key = '';
    if ($api_key_override !== null && trim($api_key_override) !== '') {
        $api_key = trim($api_key_override);
    } elseif (function_exists('pp_get_plugin_setting')) {
        $settings = pp_get_plugin_setting(CRAU_SLUG) ?: [];
        $api_key = trim((string)($settings['api_key'] ?? ''));
    }
    if ($api_key === '') {
        return ['status' => false,
            'message' => 'API key missing. Save your ExchangeRate-API key first.'];
    }

    // DB
    $db = crau_db();
    if (!$db) {
        return ['status' => false,
            'message' => 'Database connection failed (pp-config.php).'];
    }

    // Base currency
    $base = crau_get_base_currency($db);
    if (!$base) {
        return ['status' => false,
            'message' => 'Default currency not found in pp_settings.default_currency.'];
    }
    $base = strtoupper($base);

    // Fetch rates
    $api_url = "https://v6.exchangerate-api.com/v6/{$api_key}/latest/{$base}";
    $http = crau_http_get($api_url);
    $status = (int)($http['status'] ?? 0);
    $body = (string)($http['body'] ?? '');

    if ($status !== 200 || $body === '') {
        return ['status' => false,
            'message' => 'API request failed.'];
    }

    $data = json_decode($body, true);
    if (!is_array($data) || ($data['result'] ?? '') !== 'success' || !isset($data['conversion_rates'])) {
        $err = $data['error-type'] ?? 'Invalid API response';
        return ['status' => false,
            'message' => 'API error: '.$err];
    }

    $rates = $data['conversion_rates'];

    // Currency list
    $rows = crau_get_currency_rows($db);
    if (!$rows) {
        return ['status' => false,
            'message' => 'No currencies found in pp_currency.'];
    }

    // Update rows: base=1, missing=0; set created_at now (system marker)
    $tzId = crau_get_site_timezone_id();
    $now = new DateTime('now', new DateTimeZone($tzId));
    $nowStr = $now->format('Y-m-d H:i:s');

    $updated = 0;
    $stmt = $db->prepare("UPDATE pp_currency SET currency_rate=?, created_at=? WHERE id=?");
    if (!$stmt) {
        return ['status' => false,
            'message' => 'DB error: failed to prepare update statement.'];
    }
    foreach ($rows as $row) {
        $id = (int)$row['id'];
        $code = strtoupper(trim($row['currency_code']));
        if ($code === '' || $code === '--') {
            continue;
        }

        $rate = ($code === $base) ? 1.0 : (isset($rates[$code]) ? (float)$rates[$code] : 0.0);
        $rateStr = (string)$rate;
        $stmt->bind_param('ssi', $rateStr, $nowStr, $id);
        if ($stmt->execute()) {
            $updated++;
        }
    }
    $stmt->close();

    // Derive last & next from system
    $last_ts = crau_get_last_update_ts_from_db($db, $tzId);
    $next_ts = crau_next_00_01_ts($tzId);

    return [
        'status' => true,
        'message' => "Currency rates updated successfully ({$updated} currencies).",
        'updated_count' => $updated,
        'base_currency' => $base,
        'last_update_human' => crau_human_dt($last_ts, $tzId),
        'next_update_human' => crau_human_dt($next_ts, $tzId)
    ];
}

// ----------------------------- DB LAYER -------------------------------

function crau_find_pp_config(): ?string {
    $start = __DIR__;
    for ($i = 0; $i < 8; $i++) {
        $root = dirname($start, $i + 1);
        $cfg = $root . '/pp-config.php';
        if (is_file($cfg) && is_readable($cfg)) {
            return realpath($cfg);
        }
    }
    return null;
}

function crau_db(): ?mysqli {
    static $mysqli = null;
    if ($mysqli instanceof mysqli) return $mysqli;

    $cfg = crau_find_pp_config();
    if (!$cfg) return null;

    $creds = (function($cfgPath) {
        $db_host = $db_user = $db_pass = $db_name = null;
        $db_port = 3306;
        $lvl = function_exists('ob_get_level') ? ob_get_level() : 0;
        if ($lvl !== false) ob_start();
        @require $cfgPath; // sets $db_host, $db_user, $db_pass, $db_name, optional $db_port
        if ($lvl !== false) {
            while (ob_get_level() > $lvl) {
                @ob_end_clean();
            }
        }
        if (isset($GLOBALS['db_port'])) {
            $db_port = (int)$GLOBALS['db_port'];
        }
        return compact('db_host', 'db_user', 'db_pass', 'db_name', 'db_port');
    })($cfg);

    if (empty($creds['db_host']) || empty($creds['db_user']) || empty($creds['db_name'])) return null;

    $mysqli = @new mysqli($creds['db_host'], $creds['db_user'], $creds['db_pass'], $creds['db_name'], (int)$creds['db_port']);
    if ($mysqli->connect_error) return null;
    @$mysqli->set_charset('utf8mb4');
    return $mysqli;
}

/** Site timezone ID from pp_settings.default_timezone; fallback to PHP default/UTC */
function crau_get_site_timezone_id(): string {
    $db = crau_db();
    if ($db && $res = $db->query("SELECT default_timezone FROM pp_settings WHERE id=1 LIMIT 1")) {
        $row = $res->fetch_assoc();
        $tz = isset($row['default_timezone']) ? trim((string)$row['default_timezone']) : '';
        if ($tz !== '') return $tz;
    }
    $phpTz = @date_default_timezone_get();
    return $phpTz ?: 'UTC';
}

/** Get default/base currency */
function crau_get_base_currency(mysqli $db): ?string {
    if ($res = $db->query("SELECT default_currency FROM pp_settings WHERE id=1 LIMIT 1")) {
        $row = $res->fetch_assoc();
        $val = isset($row['default_currency']) ? trim((string)$row['default_currency']) : '';
        if ($val !== '') return $val;
    }
    if ($res = $db->query("SELECT default_currency FROM pp_settings WHERE default_currency IS NOT NULL AND default_currency <> '' ORDER BY id ASC LIMIT 1")) {
        $row = $res->fetch_assoc();
        $val = isset($row['default_currency']) ? trim((string)$row['default_currency']) : '';
        if ($val !== '') return $val;
    }
    return null;
}

/** Fetch currency rows (id + code) */
function crau_get_currency_rows(mysqli $db): array {
    $out = [];
    if ($res = $db->query("SELECT id, currency_code FROM pp_currency")) {
        while ($row = $res->fetch_assoc()) $out[] = $row;
        $res->close();
    }
    return $out;
}

/** MAX(created_at) across pp_currency (ignores '--'), returned as Unix TS in site TZ */
function crau_get_last_update_ts_from_db(mysqli $db, string $tzId): ?int {
    $maxTs = null;
    if ($res = $db->query("SELECT MAX(NULLIF(created_at,'--')) AS lu FROM pp_currency")) {
        $row = $res->fetch_assoc();
        $lu = $row['lu'] ?? null;
        if ($lu && $lu !== '--') {
            // created_at stored as 'Y-m-d H:i:s'
            try {
                $dt = new DateTime($lu, new DateTimeZone($tzId));
                $maxTs = $dt->getTimestamp();
            } catch (Throwable $e) {
                /* ignore */
            }
        }
        $res->close();
    }
    return $maxTs;
}

// ----------------------------- HTTP ----------------------------------

function crau_http_get(string $url): array {
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_ENCODING => '',
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Accept-Encoding: gzip, deflate, br',
                'User-Agent: PipraPay-CurrencyModule/1.0 (+https://piprapay.com)'
            ],
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (($body === false || $body === '') && $code === 200) {
            curl_setopt($ch, CURLOPT_ENCODING, 'identity');
            $body = curl_exec($ch);
        }
        if (($body === false || $body === '') && $code === 200) {
            curl_setopt_array($ch, [CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1, CURLOPT_ENCODING => 'identity']);
            $body = curl_exec($ch);
        }
        curl_close($ch);
        return ['status' => $code,
            'body' => (string)$body];
    }

    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 30,
            'ignore_errors' => true,
            'protocol_version' => 1.1,
            'header' => implode("\r\n", [
                'Accept: application/json',
                'Accept-Encoding: identity',
                'Connection: close',
                'User-Agent: PipraPay-CurrencyModule/1.0 (+https://piprapay.com)'
            ])
        ]
    ]);
    $body = @file_get_contents($url, false, $ctx);
    $code = 0;
    if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
        $code = (int)$m[1];
    }
    return ['status' => $code,
        'body' => (string)$body];
}

// ---------------------------- Time helpers ---------------------------

/** Next 00:01 local timestamp */
function crau_next_00_01_ts(string $tzId): int {
    $tz = new DateTimeZone($tzId);
    $now = new DateTime('now', $tz);
    $tomorrow = clone $now;
    $tomorrow->modify('tomorrow');
    $tomorrow->setTime(CRAU_SCHEDULE_HOUR, CRAU_SCHEDULE_MIN, 0);
    return $tomorrow->getTimestamp();
}

/** Format TS for UI in site timezone: d/m/Y H:i (24h) */
function crau_human_dt(?int $ts, string $tzId): string {
    if (!$ts) return '--';
    $tz = new DateTimeZone($tzId);
    $dt = (new DateTime('@'.$ts))->setTimezone($tz);
    return $dt->format('d/m/Y H:i');
}

// ==================== CRON INTEGRATION (via ?cron) ====================

/**
* Determine if a daily update is due (first time after today's 00:01 local).
* @return bool
*/
function crau_is_daily_update_due(): bool {
    $db = crau_db();
    if (!$db) return false;

    $tzId = crau_get_site_timezone_id();

    // Last update = MAX(pp_currency.created_at)
    $lastTs = crau_get_last_update_ts_from_db($db, $tzId);

    // Compute today's 00:01 local
    $tz = new DateTimeZone($tzId);
    $now = new DateTime('now', $tz);

    $today001 = (clone $now);
    $today001->setTime(CRAU_SCHEDULE_HOUR, CRAU_SCHEDULE_MIN, 0);
    // If it's before 00:01 now, today's window hasn’t opened yet
    if ($now < $today001) return false;

    // If never updated yet OR last update before today's 00:01 => due
    if (!$lastTs) return true;
    $last = (new DateTime('@'.$lastTs))->setTimezone($tz);

    return ($last < $today001);
}

/**
* Cron tick: called on ?cron. If due, perform update using stored API key.
* Outputs nothing (cron is usually silenced), returns void.
*/
function crau_cron_tick(): void {
    // Only proceed if we actually need to run now
    if (!crau_is_daily_update_due()) return;

    // Let crau_update_all_rates() read the API key from plugin settings
    $result = crau_update_all_rates(null);

    // Optional: you can log to error_log if you want a trace
    // error_log('[CRAU] '.($result['status'] ? 'OK' : 'FAIL').': '.$result['message']);
}

// Wire into site cron: only when the site is hit with ?cron
if (isset($_GET['cron'])) {
    // Hard-stop if someone hits ajax.php directly with ?cron; we only want root cron
    if (php_sapi_name() === 'cli' || (isset($_SERVER['SCRIPT_NAME']) && strpos($_SERVER['SCRIPT_NAME'], '/ajax.php') === false)) {
        // Run tick (no output)
        crau_cron_tick();
    }
}
// ================== END CRON INTEGRATION (via ?cron) ==================