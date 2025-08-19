<?php
if (!defined('pp_allowed_access')) { define('pp_allowed_access', true); }

/* FX Rate Sync — core (v1.1.0)
   - Store settings in pp_plugins.plugin_array (JSON)
   - Providers: ExchangeRate-API (v6) + jsDelivr (Fawaz) w/ fallback
   - Schedule: 00:01 / 06:01 / 12:01 / 18:01 (site time)
*/

define('CRAU_PLUGIN_SLUG', 'currency-rate-auto-update');
define('CRAU_PLUGIN_NAME', 'FX Rate Sync');

define('CRAU_PROVIDER_EXCHANGERATE', 'exchangerate_api');
define('CRAU_PROVIDER_JSDELIVR',     'jsdelivr_fawaz');
define('CRAU_SCHEDULE_MINUTE',       1); // run at :01

//  use pp-config.php
require_once __DIR__ . '/../../../../pp-config.php';

/* DB handle (simple + strict) */
function crau_db(): ?mysqli {
    global $db_host, $db_user, $db_pass, $db_name;
    if (!$db_host || !$db_user || !$db_name) return null;
    $db = @new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($db->connect_errno) return null;
    $db->set_charset('utf8mb4');
    return $db;
}

/* Prefix (pp-config.php) */
function crau_db_prefix(): string {
    global $db_prefix;
    return (isset($db_prefix) && $db_prefix !== '') ? $db_prefix : 'pp_';
}

/* Find/create row in pp_plugins by slug */
function crau_pp_plugins_get_row(mysqli $db): ?array {
    $table = crau_db_prefix() . 'plugins';
    $slug  = CRAU_PLUGIN_SLUG;
    if ($stmt = $db->prepare("SELECT id, plugin_array FROM {$table} WHERE plugin_slug=? LIMIT 1")) {
        $stmt->bind_param('s', $slug);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        return $row ?: null;
    }
    return null;
}
function crau_pp_plugins_ensure_row(mysqli $db): array {
    $row = crau_pp_plugins_get_row($db);
    if ($row) return $row;
    $table  = crau_db_prefix() . 'plugins';
    $name   = CRAU_PLUGIN_NAME;
    $slug   = CRAU_PLUGIN_SLUG;
    $dir    = 'modules';
    $json   = '{}';
    $status = 'active';
    $created= date('Y-m-d H:i:s');
    if ($stmt = $db->prepare("INSERT INTO {$table} (plugin_name, plugin_slug, plugin_dir, plugin_array, status, created_at) VALUES (?, ?, ?, ?, ?, ?)")) {
        $stmt->bind_param('ssssss', $name, $slug, $dir, $json, $status, $created);
        $stmt->execute();
        $stmt->close();
    }
    $row = crau_pp_plugins_get_row($db);
    return $row ?: ['id'=>0,'plugin_array'=>'{}'];
}

/* Settings load/save */
function crau_settings_load(): array {
    $db = crau_db(); if (!$db) return [];
    $row = crau_pp_plugins_get_row($db);
    if (!$row || empty($row['plugin_array']) || $row['plugin_array'] === '--') return [];
    $arr = json_decode((string)$row['plugin_array'], true);
    return is_array($arr) ? $arr : [];
}
function crau_settings_save(array $settings): bool {
    $db = crau_db(); if (!$db) return false;
    $row = crau_pp_plugins_ensure_row($db);

    $current = [];
    if (!empty($row['plugin_array']) && $row['plugin_array'] !== '--') {
        $dec = json_decode((string)$row['plugin_array'], true);
        if (is_array($dec)) $current = $dec;
    }
    $merged = array_merge($current, $settings);
    $json   = json_encode($merged, JSON_UNESCAPED_SLASHES);

    $table = crau_db_prefix() . 'plugins';
    if ($stmt = $db->prepare("UPDATE {$table} SET plugin_array=? WHERE plugin_slug=? LIMIT 1")) {
        $slug = CRAU_PLUGIN_SLUG;
        $stmt->bind_param('ss', $json, $slug);
        $ok = $stmt->execute();
        $stmt->close();
        return (bool)$ok;
    }
    return false;
}

/* Time helpers (keep simple) */
function crau_site_timezone(): DateTimeZone { return new DateTimeZone('UTC'); }
function crau_human(int $ts): string { return (new DateTime("@{$ts}"))->setTimezone(crau_site_timezone())->format('d/m/Y H:i'); }
function crau_next_6h_ts(): int {
    $tz = crau_site_timezone(); $now = new DateTime('now', $tz);
    foreach ([0,6,12,18] as $h) { $dt = (clone $now)->setTime($h, CRAU_SCHEDULE_MINUTE, 0); if ($dt > $now) return $dt->getTimestamp(); }
    return (new DateTime('tomorrow', $tz))->setTime(0, CRAU_SCHEDULE_MINUTE, 0)->getTimestamp();
}

/* HTTP GET (quiet + resilient) */
function crau_http_get(string $url): array {
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL=>$url, CURLOPT_RETURNTRANSFER=>true, CURLOPT_SSL_VERIFYPEER=>true,
            CURLOPT_CONNECTTIMEOUT=>15, CURLOPT_TIMEOUT=>30, CURLOPT_ENCODING=>'identity',
            CURLOPT_FOLLOWLOCATION=>true, CURLOPT_MAXREDIRS=>5,
            CURLOPT_HTTPHEADER=>['Accept: application/json','User-Agent: PipraPay-FXRateSync/1.1'],
            CURLOPT_HTTP_VERSION=>CURL_HTTP_VERSION_1_1, CURLOPT_IPRESOLVE=>CURL_IPRESOLVE_V4,
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['status'=>$code,'body'=>(string)$body];
    }
    $ctx = stream_context_create([
        'http'=>['method'=>'GET','timeout'=>30,'ignore_errors'=>true,'protocol_version'=>1.1,'header'=>implode("\r\n",['Accept: application/json','User-Agent: PipraPay-FXRateSync/1.1','Accept-Encoding: identity'])],
        'ssl' =>['verify_peer'=>true,'verify_peer_name'=>true]
    ]);
    $body = @file_get_contents($url, false, $ctx);
    $code = 0;
    if (isset($http_response_header)) foreach ($http_response_header as $h) if (preg_match('#^HTTP/\d\.\d\s+(\d{3})#',$h,$m)) {$code=(int)$m[1]; break;}
    return ['status'=>$code,'body'=>(string)$body];
}

/* Fawaz/jsDelivr (fallback chain, tolerant) */
function crau_fetch_rates_jsdelivr(string $base): array {
    $b = strtolower(trim($base));
    $urls = [
        "https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@latest/v1/currencies/{$b}.min.json",
        "https://latest.currency-api.pages.dev/v1/currencies/{$b}.min.json",
        "https://raw.githubusercontent.com/fawazahmed0/currency-api/gh-pages/v1/currencies/{$b}.min.json",
    ];
    foreach ($urls as $u) {
        $r = crau_http_get($u);
        if ((int)$r['status'] !== 200) continue;
        $raw = (string)$r['body'];
        if ($raw === '') continue;
        $j = json_decode($raw, true);
        if (!is_array($j)) continue;
        $key = isset($j[$b]) && is_array($j[$b]) ? $b : (function($x){ $k=array_values(array_filter(array_keys($x),fn($y)=>$y!=='date')); return count($k)===1 ? $k[0] : null; })($j);
        if (!$key || !isset($j[$key]) || !is_array($j[$key])) continue;
        $norm=[]; foreach ($j[$key] as $k=>$v) if (is_numeric($v)) $norm[strtoupper($k)]=(float)$v;
        return ['rates'=>$norm,'source'=>$u];
    }
    throw new RuntimeException('jsDelivr not available');
}

/* Defsult currency (fallback BDT) */
function crau_get_base_currency(mysqli $db): string {
    $p = crau_db_prefix();
    $res = @$db->query("SELECT default_currency FROM {$p}settings WHERE id=1");
    if ($res && ($row=$res->fetch_assoc())) { $cur=strtoupper(trim((string)$row['default_currency'])); if ($cur) return $cur; }
    return 'BDT';
}

/* Currency rows */
function crau_get_currency_rows(mysqli $db): array {
    $rows=[]; $p=crau_db_prefix();
    if ($res=@$db->query("SELECT currency_name, currency_code FROM {$p}currency")) while ($r=$res->fetch_assoc()) $rows[]=$r;
    return $rows;
}

/* Update a few names → codes directly in DB (some currency code are wrong */
function crau_normalize_currency_codes_in_db(mysqli $db): void {
    $map = [
        'Afghan Afghani'      => 'AFN',
        'Belarusian Ruble'    => 'BYN',
        'Zambian Kwacha'      => 'ZMW', 
        'Mozambican Metical'  => 'MZN', 
        'Mauritanian Ouguiya' => 'MRU',
        'Venezuelan BolÃvar'  => 'VED',
    ];
    $p=crau_db_prefix();
    foreach ($map as $full=>$code) if ($stmt=$db->prepare("UPDATE {$p}currency SET currency_code=? WHERE currency_name=?")) { $stmt->bind_param('ss',$code,$full); $stmt->execute(); $stmt->close(); }
}

/* Translate non-ISO DB codes → ISO for API lookups */
function crau_alt_code_for_api(string $dbCode): ?string {
    $map = ['JMW'=>'ZMW','MZM'=>'MZN','BYR'=>'BYN','VEF'=>'VED'];
    $c = strtoupper(trim($dbCode)); return $map[$c] ?? null;
}

/* Main: fetch + apply */
function crau_update_all_rates(?string $api_key_override, ?string $provider_override = null): array {
    $s = crau_settings_load();
    $api_key  = ($api_key_override!==null && trim($api_key_override)!=='') ? trim($api_key_override) : trim((string)($s['api_key'] ?? ''));
    $provider = $provider_override ?? ($s['provider'] ?? CRAU_PROVIDER_EXCHANGERATE);
    $enabled  = (int)($s['enabled'] ?? 1);
    if ($enabled === 0) return ['status'=>true,'skipped'=>true,'message'=>'Module OFF','provider_used'=>$provider];

    if ($provider === CRAU_PROVIDER_EXCHANGERATE && $api_key === '') return ['status'=>false,'message'=>'Missing API key','provider_used'=>$provider];

    $db = crau_db(); if (!$db) return ['status'=>false,'message'=>'DB not available','provider_used'=>$provider];
    $base = crau_get_base_currency($db);

    $rates=[]; $source=null;
    if ($provider === CRAU_PROVIDER_EXCHANGERATE) {
        $url = 'https://v6.exchangerate-api.com/v6/'.rawurlencode($api_key).'/latest/'.rawurlencode($base);
        $resp = crau_http_get($url); if ((int)$resp['status'] !== 200) return ['status'=>false,'message'=>'API error','provider_used'=>$provider];
        $json = json_decode((string)$resp['body'], true); if (!is_array($json) || !isset($json['conversion_rates'])) return ['status'=>false,'message'=>'Bad API body','provider_used'=>$provider];
        $rates = $json['conversion_rates']; $source=$url;
    } else {
        try { $d = crau_fetch_rates_jsdelivr($base); $rates=$d['rates']; $source=$d['source']; }
        catch (Throwable $e) { return ['status'=>false,'message'=>'API error','provider_used'=>$provider]; }
    }

    crau_normalize_currency_codes_in_db($db);

    $rows = crau_get_currency_rows($db);
    if (!$rows) return ['status'=>false,'message'=>'No currency rows','provider_used'=>$provider];

    $p=crau_db_prefix(); $now=time(); $updated=0; $failed=[];
    foreach ($rows as $r) {
        $codeDb = strtoupper(trim($r['currency_code'] ?? '')); $name=(string)($r['currency_name'] ?? $codeDb);
        if ($codeDb === strtoupper($base)) $val=1.0;
        elseif (isset($rates[$codeDb]) && is_numeric($rates[$codeDb])) $val=(float)$rates[$codeDb];
        else { $alt=crau_alt_code_for_api($codeDb); if ($alt && isset($rates[$alt])) $val=(float)$rates[$alt]; else { $val=0.0; $failed[]=['name'=>$name,'code'=>$codeDb]; } }
        if ($stmt=$db->prepare("UPDATE {$p}currency SET currency_rate=?, created_at=FROM_UNIXTIME(?) WHERE currency_code=?")) { $stmt->bind_param('dis',$val,$now,$codeDb); $stmt->execute(); if ($stmt->affected_rows>=0) $updated++; $stmt->close(); }
    }

    // Keep only small items for the UI to show something meaningful
    $s['provider']          = $provider;
    $s['last_source_url']   = $source;
    $s['last_updated']      = $updated;
    $s['last_failed']       = count($failed);
    $s['last_update_unix']  = $now;
    $s['last_update_human'] = crau_human($now);
    $s['next_update_unix']  = crau_next_6h_ts();
    $s['next_update_human'] = crau_human($s['next_update_unix']);
    crau_settings_save($s);

    return ['status'=>true,'provider_used'=>$provider,'source_url'=>$source,'updated'=>$updated,'failed'=>count($failed)];
}

/* Cron entry (manual trigger) */
function crau_cron_tick(): array {
    $s = crau_settings_load(); $now=time(); $due=(int)($s['next_update_unix'] ?? 0);
    $out = ($due===0 || $now >= $due) ? crau_update_all_rates(null,null) : ['status'=>true,'skipped'=>true];
    $s = crau_settings_load(); $s['cron_last_run'] = date('Y-m-d H:i:s',$now); crau_settings_save($s);
    return $out;
}
