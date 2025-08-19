<?php
if (!defined('pp_allowed_access')) { define('pp_allowed_access', true); }

require_once __DIR__ . '/../functions.php';

/* get current host */
$proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
$path  = '/pp-content/plugins/modules/currency-rate-auto-update/ajax.php';
$ajax_url = $proto . $host . $path;

define('FXRS_AJAX_URL', $ajax_url);

$s = crau_settings_load();
$api_key  = isset($s['api_key'])  ? (string)$s['api_key']  : '';
$provider = isset($s['provider']) ? (string)$s['provider'] : 'exchangerate_api';
$enabled  = isset($s['enabled'])  ? (int)$s['enabled']     : 1;

$last_h = isset($s['last_update_unix']) ? date('d/m/Y H:i', (int)$s['last_update_unix']) : '—';
$next_h = isset($s['next_update_unix']) ? date('d/m/Y H:i', (int)$s['next_update_unix']) : '—';

$db = crau_db(); $base_currency = $db ? crau_get_base_currency($db) : '—';
?>
<style>
.fxrs-wrap{max-width:960px;margin:16px auto;padding:0 10px;font:14px/1.5 system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif}
.fxrs-title{margin:0 0 4px;font-size:22px;font-weight:700}
.fxrs-desc{margin:0 0 12px;color:#555}
.fxrs-card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:12px}
.fxrs-row{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.fxrs-field{display:flex;flex-direction:column;gap:6px;margin:8px 0}
.fxrs-label{font-weight:600;color:#374151}
.fxrs-input,.fxrs-select{width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:8px}
.fxrs-badge{display:inline-block;padding:3px 8px;border-radius:999px;background:#e0f2fe;color:#0369a1;font-size:12px}
.fxrs-btn{border:0;border-radius:8px;padding:10px 14px;font-weight:600;cursor:pointer}
.fxrs-btn-primary{background:#2563eb;color:#fff}
.fxrs-btn-success{background:#16a34a;color:#fff}
.fxrs-note{font-size:12px;color:#6b7280}
.fxrs-hr{height:1px;background:#e5e7eb;border:0;margin:12px 0}
.fxrs-msg{min-height:18px}
.fxrs-switch{display:inline-flex;align-items:center;gap:8px}
.fxrs-switch input{appearance:none;width:46px;height:26px;border-radius:999px;background:#e5e7eb;position:relative;outline:none;cursor:pointer;border:1px solid #d1d5db;transition:background .15s}
.fxrs-switch input:checked{background:#16a34a;border-color:#16a34a}
.fxrs-switch input::after{content:"";position:absolute;top:3px;left:3px;width:20px;height:20px;border-radius:999px;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.2);transition:left .15s}
.fxrs-switch input:checked::after{left:23px}
@media (min-width:700px){ .fxrs-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:12px} }
</style>

<div class="fxrs-wrap">
  <div class="fxrs-card" style="margin-bottom:12px;">
    <div class="fxrs-row" style="justify-content:space-between">
      <div>
        <h2 class="fxrs-title">FX Rate Sync</h2>
        <p class="fxrs-desc">Auto-sync currency rates every 6 hours.</p>
      </div>
      <label class="fxrs-switch" title="Enable/disable automatic updates">
        <input id="fxrs_enabled" type="checkbox" <?php echo $enabled? 'checked':''; ?> />
        <span class="fxrs-note">Module</span>
      </label>
    </div>
  </div>

  <div class="fxrs-card">
    <div class="fxrs-grid-2">
      <div class="fxrs-field">
        <div class="fxrs-label">Base currency</div>
        <div><span class="fxrs-badge"><?php echo htmlspecialchars($base_currency); ?></span></div>
      </div>

      <div class="fxrs-field">
        <div class="fxrs-label">Provider</div>
        <select id="fxrs_provider" class="fxrs-select">
          <option value="exchangerate_api" <?php echo ($provider==='exchangerate_api')?'selected':''; ?>>ExchangeRate-api</option>
          <option value="jsdelivr_fawaz" <?php echo ($provider==='jsdelivr_fawaz')?'selected':''; ?>>Free Currency Exchange Rates API</option>
        </select>
        <div id="fxrs_help_ex" class="fxrs-note" style="margin-top:6px; <?php echo ($provider==='exchangerate_api')?'':'display:none;'; ?>">
          Get API key: <a href="https://www.exchangerate-api.com" target="_blank">exchangerate-api.com</a>
        </div>
        <div id="fxrs_help_jd" class="fxrs-note" style="margin-top:6px; <?php echo ($provider==='jsdelivr_fawaz')?'':'display:none;'; ?>">
          jsDelivr free API: <a href="https://github.com/fawazahmed0/exchange-api" target="_blank">docs</a>
        </div>
      </div>

      <div class="fxrs-field" id="fxrs_api_row" style="<?php echo ($provider==='exchangerate_api')?'':'display:none;'; ?>">
        <div class="fxrs-label">ExchangeRate‑API Key</div>
        <input id="fxrs_api_key" class="fxrs-input" type="text" value="<?php echo htmlspecialchars($api_key); ?>" placeholder="Your API key" />
      </div>

      <div class="fxrs-field">
        <div class="fxrs-label">Last Update</div>
        <div><?php echo htmlspecialchars($last_h); ?></div>
      </div>
      <div class="fxrs-field">
        <div class="fxrs-label">Next Update</div>
        <div><?php echo htmlspecialchars($next_h); ?></div>
      </div>
    </div>

    <div class="fxrs-hr"></div>

    <div class="fxrs-row">
      <button id="fxrs_save" class="fxrs-btn fxrs-btn-primary" type="button">Save</button>
      <button id="fxrs_force" class="fxrs-btn fxrs-btn-success" type="button">Force Update</button>
      <span id="fxrs_msg" class="fxrs-note fxrs-msg"></span>
    </div>
  </div>
</div>

<script>
const AJAX = <?php echo json_encode(FXRS_AJAX_URL); ?>;
const $ = (s)=>document.querySelector(s);
function msg(t, ok=true){ const el=$("#fxrs_msg"); el.textContent=t||""; el.style.color=ok?"#065f46":"#991b1b"; }

$("#fxrs_provider").addEventListener("change", e=>{
  const v=e.target.value;
  $("#fxrs_api_row").style.display = (v==='exchangerate_api') ? '' : 'none';
  $("#fxrs_help_ex").style.display = (v==='exchangerate_api') ? '' : 'none';
  $("#fxrs_help_jd").style.display = (v==='jsdelivr_fawaz') ? '' : 'none';
});

async function post(body){
  try{
    const r = await fetch(AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams(body).toString()});
    const t = await r.text(); try { return {ok:true, json:JSON.parse(t)}; } catch{ return {ok:false, text:t}; }
  }catch(e){ return {ok:false, text:String(e)}; }
}

$("#fxrs_enabled").addEventListener("change", async e=>{
  const res = await post({action:'set_enabled', enabled: e.target.checked ? '1':'0'});
  if (res.ok && res.json && res.json.ok) msg(e.target.checked?'Enabled':'Disabled', true);
  else msg('Toggle failed', false);
});

$("#fxrs_save").addEventListener("click", async ()=>{
  const body = {action:'save_settings', provider: $("#fxrs_provider").value};
  const ak = $("#fxrs_api_key"); if (ak) body.api_key = ak.value;
  const res = await post(body);
  if (res.ok && res.json && res.json.ok) msg('Saved.', true);
  else msg('Save failed.', false);
});

$("#fxrs_force").addEventListener("click", async ()=>{
  const body = {action:'force_update', provider: $("#fxrs_provider").value};
  const ak = $("#fxrs_api_key"); if (ak) body.api_key = ak.value;
  const res = await post(body);
  if (res.ok && res.json && res.json.ok) msg(`Updated: ${res.json.updated||0}, Failed: ${res.json.failed||0}`, true);
  else msg('Update failed.', false);
});
</script>
