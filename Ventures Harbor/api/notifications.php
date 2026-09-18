<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Decode json payload
$raw = file_get_contents('php://input');
$input = json_decode($raw, true) ?? $_POST;
if (!$action) {
    $action = $input['action'] ?? '';
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'list') {
        $userId = requireAuth();

        // seconds_ago is measured inside SQL — see the note in api/dashboard_data.php.
        // created_at carries no timezone, so anything that parses it in the browser
        // or against PHP's clock is wrong by whatever those clocks differ by.
        $stmt = $mysqli->prepare(
            "SELECT *, TIMESTAMPDIFF(SECOND, created_at, NOW()) AS seconds_ago
               FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 30"
        );
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $notifs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Convert read status and timestamps to match mock data
        $data = [];
        foreach ($notifs as $n) {
            $data[] = [
                'id' => (string)$n['id'],
                'type' => $n['type'],
                'title' => $n['title'],
                'message' => $n['message'],
                'time' => $n['created_at'],
                'seconds_ago' => max(0, (int)$n['seconds_ago']),
                'read' => $n['is_read'] == 1
            ];
        }

        echo json_encode([
            'success' => true,
            'data' => $data
        ]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'mark_read') {
        $userId = requireAuth();
        $id = isset($input['id']) ? (int)$input['id'] : 0;

        if ($id > 0) {
            $stmt = $mysqli->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND id = ?");
            $stmt->bind_param("ii", $userId, $id);
        } else {
            $stmt = $mysqli->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
        }

        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'message' => 'Failed to update notification status.']);
            exit;
        }
        $stmt->close();

        echo json_encode(['success' => true, 'message' => 'Notifications updated.']);
        exit;
    }
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Bad request']);
