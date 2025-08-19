<?php
if (!defined('pp_allowed_access')) {
    die('Direct access not allowed');
}

/**
* Currency Rate Auto Update – PipraPay Plugin
*/

$plugin_meta = [
    'Plugin Name' => 'FX Rate Sync',
    'Description' => 'Automatically updates currency rates for PipraPay. Supports ExchangeRate-API (v6) and Free Currency Exchange Rates API (jsDelivr).',
    'Version' => '1.1.0',
    'Author' => 'Fattain Naime',
    'Author URI' => 'https://iamnaime.info.bd/',
    'License' => 'GPL-2.0+',
    'License URI' => 'https://www.gnu.org/licenses/gpl-2.0.html',
    'Requires at least' => '1.0.0',
    'Plugin URI' => 'https://github.com/fattain-naime/currency-rate-auto-update',
    'Text Domain' => '',
    'Domain Path' => '',
    'Requires PHP' => '7.4'
];

$funcFile = __DIR__ . '/functions.php';
if (file_exists($funcFile)) {
    require_once $funcFile;
}

/**
* Render admin UI
*/
function currency_rate_auto_update_admin_page() {
    $viewFile = __DIR__ . '/views/admin-ui.php';
    if (file_exists($viewFile)) {
        include $viewFile;
    } else {
        echo "<div class='alert alert-warning'>Admin UI not found.</div>";
    }
}
