<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

header('Content-Type: application/json');

requireAuth();

$mode = $_GET['mode'] ?? 'ventures';

if ($mode === 'ventures') {
    $result = $mysqli->query('SELECT * FROM ventures ORDER BY id DESC LIMIT 20');
    $items = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    echo json_encode(['success' => true, 'data' => $items]);
    exit;
}

if ($mode === 'meetups') {
    $result = $mysqli->query('SELECT * FROM meetups ORDER BY id DESC LIMIT 20');
    $items = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    echo json_encode(['success' => true, 'data' => $items]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown mode.']);
