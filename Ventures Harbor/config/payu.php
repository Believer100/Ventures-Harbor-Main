<?php
/* PAYU PAYMENT GATEWAY */

// Test pair — from the Test Mode dashboard (MID 87885247).
const PAYU_TEST_KEY  = 'Rlg1R5';
const PAYU_TEST_SALT = 'v7gGHs03CTKiOsbt852cdzqyz45sTbAx';

// Live pair — real money. MID 13723058.
const PAYU_LIVE_KEY  = 'qC87Cl';
const PAYU_LIVE_SALT = 'ZLJ0Rp3V6GYX2nkqDkL4rpLMnu2cbElG';

const PAYU_TEST_ENDPOINT = 'https://test.payu.in/_payment';
const PAYU_LIVE_ENDPOINT = 'https://secure.payu.in/_payment';

const PAYU_MIN_AMOUNT = 1.00;

function vh_payu_config(mysqli $mysqli): array
{
    $get = function (string $key, string $default) use ($mysqli): string {
        $stmt = $mysqli->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return ($row && $row['setting_value'] !== null && $row['setting_value'] !== '')
            ? (string)$row['setting_value']
            : $default;
    };

    $enabled = $get('payu_enabled', '0') === '1';
    $mode    = $get('payu_mode', 'test') === 'live' ? 'live' : 'test';

    $key      = $mode === 'live' ? PAYU_LIVE_KEY : PAYU_TEST_KEY;
    $salt     = $mode === 'live' ? PAYU_LIVE_SALT : PAYU_TEST_SALT;
    $endpoint = $mode === 'live' ? PAYU_LIVE_ENDPOINT : PAYU_TEST_ENDPOINT;

    $error = null;
    if ($enabled && ($key === '' || $salt === '')) {
        $error = $mode === 'live'
            ? 'PayU is set to Live mode but no live Key/Salt is configured on the server (config/payu.php).'
            : 'PayU is enabled but no Key/Salt is configured on the server (config/payu.php).';
    }

    return [
        'enabled'  => $enabled,
        'mode'     => $mode,
        'key'      => $key,
        'salt'     => $salt,
        'endpoint' => $endpoint,
        'error'    => $error,
    ];
}

/**
 * May a fee be completed WITHOUT the gateway — the 'manual' path that writes a
 * completed transaction and grants membership on the spot?
 *
 * Only when there is no live gateway to go through: PayU switched off entirely
 * (the simulated demo flow that keeps the platform demonstrable), or on in test
 * mode. In LIVE mode real money is the only thing that can complete a payment,
 * so this closes — and it closes on the MODE alone, deliberately not on whether
 * the credentials resolved. A live site whose key/salt are missing must refuse
 * the payment, not fall back to granting memberships for free.
 *
 * The routing decision used to live only in join-venture.js, which is to say in
 * the browser: `action=join` never asked the gateway anything, so a hand-made
 * POST completed a paid membership without PayU ever being contacted, and a
 * misconfigured live site did the same thing for every ordinary visitor.
 */
function vh_payu_allows_manual_payment(array $cfg): bool
{
    return empty($cfg['enabled']) || ($cfg['mode'] ?? 'test') !== 'live';
}

/**
 * The refusal a caller sends back when it doesn't. Separate from the check so
 * every path says the same thing — this is what a partner sees if they reach
 * the pay step while the live gateway is misconfigured.
 */
function vh_payu_manual_blocked_message(array $cfg): string
{
    return ($cfg['error'] ?? null) !== null
        ? 'Payments are temporarily unavailable on this site — the payment gateway is not correctly configured. '
          . 'Nothing has been charged and you have not been signed up. Please try again later or contact support.'
        : 'This payment has to be completed through PayU. Please start the payment again and complete it on the '
          . 'gateway — a membership is only created once PayU confirms the payment.';
}

function vh_payu_amount(int $rupees): string
{
    return number_format($rupees, 2, '.', '');
}

function vh_payu_request_hash(array $p, string $salt): string
{
    $sequence = [
        $p['key'], $p['txnid'], $p['amount'], $p['productinfo'],
        $p['firstname'], $p['email'],
        $p['udf1'] ?? '', $p['udf2'] ?? '', $p['udf3'] ?? '',
        $p['udf4'] ?? '', $p['udf5'] ?? '',
    ];

    return hash('sha512', implode('|', $sequence) . '||||||' . $salt);
}

function vh_payu_response_hash(array $post, string $salt): string
{
    $sequence = [
        $salt,
        $post['status'] ?? '',

        '', '', '', '', '',
        $post['udf5'] ?? '', $post['udf4'] ?? '', $post['udf3'] ?? '',
        $post['udf2'] ?? '', $post['udf1'] ?? '',
        $post['email'] ?? '', $post['firstname'] ?? '',
        $post['productinfo'] ?? '', $post['amount'] ?? '',
        $post['txnid'] ?? '', $post['key'] ?? '',
    ];

    $base = implode('|', $sequence);

    if (isset($post['additionalCharges']) && $post['additionalCharges'] !== '') {
        $base = $post['additionalCharges'] . '|' . $base;
    }

    return hash('sha512', $base);
}

function vh_payu_verify_response(array $post, string $salt): bool
{
    $received = (string)($post['hash'] ?? '');
    if ($received === '') {
        return false;
    }
    return hash_equals(vh_payu_response_hash($post, $salt), $received);
}

function vh_payu_base_url(): string
{
    $https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $https ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';

    // .../api/payments.php  →  the app root one level up.
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    $root = preg_replace('#/api$#', '', $dir);

    return $scheme . '://' . $host . $root;
}

function vh_payu_clean(string $value, int $maxLength = 100): string
{
    $value = preg_replace('/[^A-Za-z0-9 ._\-]/', ' ', $value);
    $value = trim(preg_replace('/\s+/', ' ', $value));
    return $value === '' ? 'NA' : mb_substr($value, 0, $maxLength);
}
