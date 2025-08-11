<?php
if (!defined('pp_allowed_access')) {
    die('Direct access not allowed');
}

$settings = function_exists('pp_get_plugin_setting') ? (pp_get_plugin_setting('currency-rate-auto-update') ?: []) : [];
$api_key = $settings['api_key'] ?? '';
$api_name = 'ExchangeRate-API (v6)';

// Derive base, last, next from system
$base_currency = '--';
$last_h = '--';
$next_h = '--';

if (function_exists('crau_db') && ($db = crau_db())) {
    $base = crau_get_base_currency($db);
    if ($base) $base_currency = strtoupper($base);

    $tzId = crau_get_site_timezone_id();
    $last_ts = crau_get_last_update_ts_from_db($db, $tzId);
    $next_ts = crau_next_00_01_ts($tzId);

    $last_h = crau_human_dt($last_ts, $tzId);
    $next_h = crau_human_dt($next_ts, $tzId);
}

// Compute ajax.php URL
function crau_guess_ajax_url_from_docroot(): string {
    $doc = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    $mod = str_replace('\\', '/', dirname(__DIR__)); // module root
    if ($doc && strpos($mod, $doc) === 0) {
        $rel = substr($mod, strlen($doc)); // leading slash
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme.'://'.$host.$rel.'/ajax.php';
    }
    return './pp-content/plugins/modules/currency-rate-auto-update/ajax.php';
}
$ajax_url = crau_guess_ajax_url_from_docroot();
?>
<form id="crauSettingsForm" method="post" action="">
    <div class="page-header">
        <div class="row align-items-end">
            <div class="col-sm mb-2 mb-sm-0">
                <h1 class="page-header-title">Currency Rate Auto Update</h1>
                <small class="text-muted">
                    Updates currency rates daily at 00:01.
                    Base currency is <strong><?php echo htmlspecialchars($base_currency); ?></strong> (rate = 1).
                </small>
            </div>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="d-grid gap-3 gap-lg-5">

                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title h4">Exchange Provider</h2>
                    </div>
                    <div class="card-body">
                        <input type="hidden" name="action" value="plugin_update-submit">
                        <input type="hidden" name="plugin_slug" value="currency-rate-auto-update">

                        <div class="mb-4">
                            <label class="form-label">Currency API</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($api_name); ?>" disabled>
                        </div>

                        <div class="mb-2">
                            <label for="api_key" class="form-label">API Key</label>
                            <input type="password" class="form-control" name="api_key" id="api_key" value="<?php echo htmlspecialchars($api_key); ?>" placeholder="Enter your ExchangeRate-API key" autocomplete="off">
                        </div>
                        <div class="mb-4">
                            <a href="https://www.exchangerate-api.com" target="_blank" rel="noopener">Get your API key: https://www.exchangerate-api.com</a>
                        </div>

                        <div class="row mb-4">
                            <div class="col-sm-6">
                                <label class="form-label">Last Update</label>
                                <input type="text" class="form-control" id="last_update" value="<?php echo htmlspecialchars($last_h); ?>" disabled>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">Next Update</label>
                                <input type="text" class="form-control" id="next_update" value="<?php echo htmlspecialchars($next_h); ?>" disabled>
                            </div>
                        </div>

                        <div id="ajaxResponse" class="mb-3"></div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-primary-save">Save</button>
                            <button type="button" class="btn btn-secondary" id="btnForceUpdate" data-ajax="<?php echo htmlspecialchars($ajax_url); ?>">Update Currency Rate Now</button>
                        </div>
                    </div>
                </div>

                <div id="stickyBlockEndPoint"></div>
            </div>
        </div>
    </div>
</form>

<script>
    $(document).ready(function() {
        // Save API key via core admin
        $('#crauSettingsForm').on('submit', function(e) {
            e.preventDefault();
            const $btn = $('.btn-primary-save');
            const orig = $btn.text();
            $btn.prop('disabled', true).html('<div class="spinner-border text-light spinner-border-sm" role="status"><span class="visually-hidden">Loading...</span></div>');

            $.ajax({
                url: $(this).attr('action'),
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                cache: false,
                success: function(response) {
                    $btn.prop('disabled', false).text(orig);
                    if (response.status) {
                        $('#ajaxResponse').removeClass('alert-danger').addClass('alert alert-success').html(response.message);
                    } else {
                        $('#ajaxResponse').removeClass('alert-success').addClass('alert alert-danger').html(response.message);
                    }
                },
                error: function(xhr) {
                    $btn.prop('disabled', false).text(orig);
                    const msg = 'Save failed. HTTP ' + xhr.status + (xhr.responseText ? (' • ' + xhr.responseText.substring(0, 200)): '');
                    $('#ajaxResponse').removeClass('alert-success').addClass('alert alert-danger').text(msg);
                }
            });
        });

        // Force Update (sends API key directly to ajax.php)
        $('#btnForceUpdate').on('click', function() {
            const $btn = $(this);
            const ajaxUrl = $btn.data('ajax');
            const apiKey = $('#api_key').val() || '';
            const orig = $btn.text();
            $btn.prop('disabled',
                true).text('Updating...');

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                dataType: 'json',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                data: {
                    action: 'currency-rate-update-now',
                    api_key: apiKey
                },
                cache: false,
                success: function(payload) {
                    const cls = payload.status ? 'alert-success': 'alert-danger';
                    $('#ajaxResponse').removeClass('alert-danger alert-success').addClass('alert ' + cls)
                    .html(payload.message || (payload.status ? 'Updated.': 'Update failed.'));

                    if (payload.last_update_human) {
                        $('#last_update').val(payload.last_update_human);
                    }
                    if (payload.next_update_human) {
                        $('#next_update').val(payload.next_update_human);
                    }
                    $btn.prop('disabled', false).text(orig);
                },
                error: function(xhr) {
                    $('#ajaxResponse').removeClass('alert-success').addClass('alert alert-danger').text('Unexpected response (HTTP ' + xhr.status + ').');
                    $btn.prop('disabled', false).text(orig);
                }
            });
        });
    });
</script>
