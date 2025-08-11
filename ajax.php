<?php
/**
 * Currency Rate Auto Update: JSON endpoint
 * - Same-origin POST check
 * - Loads module functions only
 * - Accepts api_key in POST for “Update Now”
 */

@ini_set('display_errors', '0');
@ini_set('log_errors', '1');

if (function_exists('ob_get_level')) { while (ob_get_level() > 0) { @ob_end_clean(); } }
if (function_exists('header_remove')) { @header_remove(); }
@header('Content-Type: application/json; charset=utf-8');
@header('X-Content-Type-Options: nosniff');
@header('X-Frame-Options: SAMEORIGIN');
@header('Referrer-Policy: same-origin');

function crau_same_origin_ok(): bool {
  $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
  $host   = $_SERVER['HTTP_HOST'] ?? '';
  if ($origin === '' || $host === '') return true;
  $p = parse_url($origin);
  return isset($p['host']) && strtolower($p['host']) === strtolower($host);
}

if (!defined('pp_allowed_access')) { define('pp_allowed_access', true); }

$funcs = __DIR__ . '/functions.php';
if (!is_file($funcs)) {
  http_response_code(500);
  echo json_encode(['status'=>false,'message'=>'Handler bootstrap failed (functions.php missing)']);
  exit;
}
require_once $funcs;

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405);
  echo json_encode(['status'=>false,'message'=>'Method not allowed']);
  exit;
}
if (!crau_same_origin_ok()) {
  http_response_code(403);
  echo json_encode(['status'=>false,'message'=>'Cross-origin request blocked']);
  exit;
}

$action  = $_POST['action']  ?? '';
$api_key = $_POST['api_key'] ?? null;

try {
  if ($action === 'currency-rate-update-now') {
    $out = crau_update_all_rates($api_key);
    http_response_code(200);
    echo json_encode($out);
    exit;
  }
  http_response_code(400);
  echo json_encode(['status'=>false,'message'=>'Unknown action']);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['status'=>false,'message'=>'Exception: '.$e->getMessage()]);
}