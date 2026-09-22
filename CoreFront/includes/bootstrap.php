<?php
set_include_path('/var/www/html/classes/');
date_default_timezone_set('Africa/Lagos');

function cors() {
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
        header('Access-Control-Allow-Credentials: true');
        header("Content-Type: application/json; charset=UTF-8");
        header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers: *");
        header('Access-Control-Max-Age: 86400');    // cache for 1 day
    }

    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
            header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
            header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
        exit(0);
    }
}
cors();

include 'includes/include.all.php';

/**
 * Recursively mask credential-bearing keys before anything is written to
 * logs. The server decrypts request/response bodies in cleartext, so
 * without this a password/PIN/OTP/session token/crypto key would land in
 * error_log and the Log table exactly as sent.
 */
function redact_sensitive($value) {
    static $sensitive = ['password', 'current_password', 'new_password', 'old_password', 'auth_pin', 'pin', 'otp', 'otp_code', 'session_token', 'token', 'signature', 'crypto_key', 'crypto_iv', 'x-authid-token'];
    if (!is_array($value)) {
        return $value;
    }
    $out = [];
    foreach ($value as $k => $v) {
        if (is_array($v)) {
            $out[$k] = redact_sensitive($v);
        } elseif (in_array(strtolower((string) $k), $sensitive, true)) {
            $out[$k] = '***REDACTED***';
        } else {
            $out[$k] = $v;
        }
    }
    return $out;
}

$secure = new Secure();
$user = new User();

$headers = apache_request_headers();

// Validate-and-fetch the session in a single Redis GET (User::getSession()).
// The record carries the UserID and the per-session AES key/IV, so it both
// authenticates the request and supplies the decrypt keys — require_session()
// reuses it with no extra lookup. Endpoint files that have both an
// authenticated and an unauthenticated action set (user.php, utils.php)
// branch on this directly instead of calling require_session(), which is
// only for "this action always needs a session, otherwise 401" endpoints.
$current_session = null;
if (isset($headers['X-Clientid'], $headers['X-Authid-Token'])) {
    $current_session = $user->getSession($headers['X-Authid-Token']);
}

$raw_body = file_get_contents("php://input");
$data = null;
if ($current_session) {
    // Authenticated request — body is encrypted with this session's own key.
    $secure->setKeys($current_session['CryptoKey'], $current_session['CryptoIV']);
    if ($raw_body) {
        $data = json_decode($secure->decrypt($raw_body), true);
    }
} else if ($raw_body) {
    // Pre-session request (login, validateOTP, forgot_password, ...) — no
    // shared key exists yet, so this is plain JSON over TLS.
    $data = json_decode($raw_body, true);
}
if ($raw_body) {
    error_log(print_r(redact_sensitive($data), true));
}

foreach ($_GET as $name => $value) {
    error_log("$name: $value\n");
}
error_log(json_encode(redact_sensitive($headers)));

$logged_in_user = null;

/** For endpoints where every action requires a session. 401s and exits otherwise. */
function require_session() {
    global $user, $headers, $logged_in_user, $current_session;
    if (!isset($headers['X-Authid-Token']) || !isset($headers['X-Clientid'])) {
        header("HTTP/1.1 401 Unauthorized");
        exit;
    }
    // $current_session was looked up by token alone; cross-check its own
    // UserID against the caller's claimed X-Clientid so a valid token can't
    // be replayed against a different client id.
    if (!is_array($current_session) || $current_session['UserID'] !== $headers['X-Clientid']) {
        header("HTTP/1.1 401 Unauthorized");
        exit;
    }
    $logged_in_user = $user->getUserDetails($headers['X-Clientid']);
    return $logged_in_user;
}

function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) == $date;
}

ob_start();

// Logs the request/response pair for every endpoint from one place, so it
// fires on a fatal error and not only on a caught Exception, and so the
// redaction above applies uniformly.
register_shutdown_function(function () {
    global $secure, $user, $data, $headers, $current_session;
    $tx_echo = ob_get_contents();
    ob_end_flush();
    $response_plain = '';
    if ($tx_echo) {
        if ($current_session) {
            // Authenticated responses are encrypted with the session's key.
            $decrypted = $secure->decrypt($tx_echo);
            $response_plain = ($decrypted !== false && $decrypted !== null) ? $decrypted : $tx_echo;
        } else {
            // Pre-session responses (login, validateOTP, ...) are already plain JSON.
            $response_plain = $tx_echo;
        }
    }
    $resp_decoded = json_decode($response_plain, true);
    $response_logged = is_array($resp_decoded) ? json_encode(redact_sensitive($resp_decoded)) : $response_plain;
    try {
        $user->insert_data(
            "insert into Log(UserID, Payload, Response, Action) values(?,?,?,?)",
            [@$headers['X-Clientid'], json_encode(redact_sensitive($data)), $response_logged, @$_GET['action']]
        );
    } catch (Exception $ex) {
        error_log("Log insert failed: " . $ex->getMessage());
    }
});
