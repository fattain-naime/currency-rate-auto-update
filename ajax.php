<?php
if (!defined('pp_allowed_access')) { define('pp_allowed_access', true); }
error_reporting(0);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');

ob_start();
register_shutdown_function(function(){
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR,E_USER_ERROR], true)) {
        while (ob_get_level()>0) { ob_end_clean(); }
        echo json_encode(['ok'=>false,'status'=>500,'message'=>'Fatal']);
    }
});

require_once __DIR__ . '/functions.php';

/* keep responses short + not chatty */
function respond(array $r){ while (ob_get_level()>0) { ob_end_clean(); } echo json_encode($r); exit; }

/* no CSRF layer here (admin only + same-origin fetch). add if platform has a token */
$action = $_POST['action'] ?? $_GET['action'] ?? '';
if ($action === '') respond(['ok'=>false,'message'=>'Missing action']);

function s_load(): array {
    $s = crau_settings_load();
    if (!isset($s['enabled']))  $s['enabled']=1;
    if (!isset($s['provider'])) $s['provider']=CRAU_PROVIDER_EXCHANGERATE;
    if (!isset($s['api_key']))  $s['api_key']='';
    if (!isset($s['name']))     $s['name']=CRAU_PLUGIN_NAME;
    if (!isset($s['status']))   $s['status']='enable';
    return $s;
}
function s_save(array $s): bool { return crau_settings_save($s); }

try {
    switch ($action) {
        case 'save_settings': {
            $s = s_load();
            $s['provider'] = $_POST['provider'] ?? CRAU_PROVIDER_EXCHANGERATE;
            $s['api_key']  = trim((string)($_POST['api_key'] ?? ''));
            if (!isset($s['next_update_unix']) || (int)$s['next_update_unix'] < time()) {
                $s['next_update_unix']  = crau_next_6h_ts();
                $s['next_update_human'] = crau_human($s['next_update_unix']);
            }
            $ok = s_save($s);
            respond(['ok'=>$ok, 'message'=>$ok?'Settings saved.':'Save failed.']);
        }

        case 'set_enabled': {
            $s = s_load();
            $s['enabled'] = (int)($_POST['enabled'] ?? 1);
            $ok = s_save($s);
            respond(['ok'=>$ok, 'enabled'=>$s['enabled']]);
        }

        case 'force_update': {
            $api_key = trim((string)($_POST['api_key'] ?? ''));
            $provider = $_POST['provider'] ?? null;
            $res = crau_update_all_rates($api_key!=='' ? $api_key : null, $provider);
            if (!empty($res['provider_used'])) { $snap = s_load(); $snap['provider'] = $res['provider_used']; s_save($snap); }
            $res['ok'] = (bool)($res['status'] ?? false);
            unset($res['status']);
            respond($res);
        }

        case 'force_cron': {
            $res = crau_cron_tick();
            $res['ok'] = (bool)($res['status'] ?? false);
            unset($res['status']);
            respond($res);
        }

        default: respond(['ok'=>false,'message'=>'Unknown action']);
    }
} catch (Throwable $e) { respond(['ok'=>false,'message'=>'Error']); }
