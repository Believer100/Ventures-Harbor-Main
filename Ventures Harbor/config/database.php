<?php
/**
 * Local XAMPP database connection.
 *
 * The current DB schema is still incomplete in this branch, but the project must
 * keep using the real mysqli type so PHP accepts the connection everywhere.
 * The original bootstrap logic is still preserved in setup.php for later restore,
 * but runtime should use the actual database connection object instead of a fake
 * replacement that breaks method signatures and type hints.
 */

set_exception_handler(function (Throwable $e) {
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json');
    }
    error_log('[Ventures Harbor] Uncaught: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
            . (str_contains($e->getMessage(), "doesn't exist") || str_contains($e->getMessage(), 'Unknown column')
                ? ' — your database schema looks out of date. Restore or recreate the venture_harbor tables.'
                : '')
    ]);
    exit;
});

$isLocalhost = in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1'], true);

if ($isLocalhost) {
    $host = '127.0.0.1';
    $user = 'root';
    $pass = '1234';
    $db   = 'venture_harbor';
} else {
    $host = 'localhost';
    $user = 'u127049976_venture';
    $pass = 'Ahmadfaraz73!';
    $db   = 'u127049976_venture';
}

$mysqli = new mysqli($host, $user, $pass, $db);

if ($mysqli->connect_errno) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$mysqli->set_charset('utf8mb4');
