<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

header('Content-Type: application/json');

function uploadErrorMessage(int $code): string
{
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'That file is larger than this server allows (check upload_max_filesize / post_max_size in php.ini).';
        case UPLOAD_ERR_PARTIAL:
            return 'The file was only partially uploaded. Please try again.';
        case UPLOAD_ERR_NO_FILE:
            return 'No file was selected.';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Server is missing a temporary folder for uploads. Contact your host/admin.';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Server failed to write the uploaded file to disk (check folder permissions).';
        case UPLOAD_ERR_EXTENSION:
            return 'A server extension blocked this upload.';
        default:
            return 'Unknown upload error (code ' . $code . ').';
    }
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

$raw = file_get_contents('php://input');
$input = json_decode($raw, true) ?? $_POST;
if (!$action) {
    $action = $input['action'] ?? '';
}

function isVentureMember(mysqli $mysqli, int $ventureId, int $userId): bool
{
    $stmt = $mysqli->prepare("SELECT id FROM venture_members WHERE venture_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $ventureId, $userId);
    $stmt->execute();
    $stmt->store_result();
    $ok = $stmt->num_rows > 0;
    $stmt->close();
    return $ok;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
    $ventureId = isset($_GET['venture_id']) ? (int)$_GET['venture_id'] : 0;
    $userId = requireAuth();
    $afterId = isset($_GET['after_id']) ? (int)$_GET['after_id'] : 0;

    if (!$ventureId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Asset ID is required.']);
        exit;
    }

    if (!isVentureMember($mysqli, $ventureId, $userId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You must join this Asset to access its group chat.']);
        exit;
    }

    if ($afterId > 0) {
        $stmt = $mysqli->prepare("
            SELECT cm.id, cm.user_id, cm.message, cm.image_path, cm.created_at,
                   UNIX_TIMESTAMP(cm.created_at) AS created_ts,
                   u.name, u.avatar, u.avatar_url
            FROM venture_chat_messages cm
            JOIN users u ON cm.user_id = u.id
            WHERE cm.venture_id = ? AND cm.id > ?
            ORDER BY cm.id ASC
        ");
        $stmt->bind_param("ii", $ventureId, $afterId);
        $stmt->execute();
        $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        echo json_encode(['success' => true, 'messages' => $messages]);
        exit;
    }

    // Full load: venture info + member roster + message history
    $stmt = $mysqli->prepare("SELECT title FROM ventures WHERE id = ?");
    $stmt->bind_param("i", $ventureId);
    $stmt->execute();
    $venture = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$venture) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Asset not found.']);
        exit;
    }

    $stmt = $mysqli->prepare("
        SELECT u.id, u.name, u.avatar, u.avatar_url, vm.role
        FROM venture_members vm
        JOIN users u ON vm.user_id = u.id
        WHERE vm.venture_id = ?
        ORDER BY vm.joined_at ASC
    ");
    $stmt->bind_param("i", $ventureId);
    $stmt->execute();
    $members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $stmt = $mysqli->prepare("
        SELECT cm.id, cm.user_id, cm.message, cm.image_path, cm.created_at,
                   UNIX_TIMESTAMP(cm.created_at) AS created_ts,
                   u.name, u.avatar, u.avatar_url
        FROM venture_chat_messages cm
        JOIN users u ON cm.user_id = u.id
        WHERE cm.venture_id = ?
        ORDER BY cm.id ASC
        LIMIT 200
    ");
    $stmt->bind_param("i", $ventureId);
    $stmt->execute();
    $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo json_encode([
        'success' => true,
        'ventureTitle' => $venture['title'],
        'members' => $members,
        'messages' => $messages
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'send') {
    $ventureId = isset($input['venture_id']) ? (int)$input['venture_id'] : 0;
    $userId = requireAuth();
    $message = trim($input['message'] ?? '');
    $imagePath = null;

    if (!$ventureId) {
        echo json_encode(['success' => false, 'message' => 'Asset ID is required.']);
        exit;
    }

    if (!isVentureMember($mysqli, $ventureId, $userId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You must join this Asset to post in its group chat.']);
        exit;
    }

    // Optional image attachment
    if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['image'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => uploadErrorMessage($file['error'])]);
            exit;
        }

        $allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExt, true)) {
            echo json_encode(['success' => false, 'message' => 'Only JPG, PNG, GIF, or WEBP images are allowed.']);
            exit;
        }
        if ($file['size'] > 5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'Image must be smaller than 5MB.']);
            exit;
        }

        $uploadDir = __DIR__ . '/../uploads/chat';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            echo json_encode(['success' => false, 'message' => 'Server could not create the uploads/chat folder. Check that the uploads directory is writable.']);
            exit;
        }
        if (!is_writable($uploadDir)) {
            echo json_encode(['success' => false, 'message' => 'The uploads/chat folder is not writable by the server. Check its permissions.']);
            exit;
        }

        $newFileName = 'chat_' . $ventureId . '_' . $userId . '_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
        $destPath = $uploadDir . '/' . $newFileName;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            echo json_encode(['success' => false, 'message' => 'Failed to save the uploaded image to disk.']);
            exit;
        }
        $imagePath = 'uploads/chat/' . $newFileName;
    }

    if ($message === '' && !$imagePath) {
        echo json_encode(['success' => false, 'message' => 'Message cannot be empty.']);
        exit;
    }

    if (mb_strlen($message) > 1000) {
        echo json_encode(['success' => false, 'message' => 'Message is too long (max 1000 characters).']);
        exit;
    }

    $stmt = $mysqli->prepare("INSERT INTO venture_chat_messages (venture_id, user_id, message, image_path) VALUES (?, ?, ?, ?)");
    $messageOrNull = $message !== '' ? $message : null;
    $stmt->bind_param("iiss", $ventureId, $userId, $messageOrNull, $imagePath);
    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'Failed to send message.']);
        exit;
    }
    $messageId = $stmt->insert_id;
    $stmt->close();

    $stmt = $mysqli->prepare("SELECT name, avatar, avatar_url FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    echo json_encode([
        'success' => true,
        'message_data' => [
            'id' => $messageId,
            'user_id' => $userId,
            'message' => $messageOrNull,
            'image_path' => $imagePath,
            'created_at' => date('Y-m-d H:i:s'),
            'name' => $user['name'] ?? '',
            'avatar' => $user['avatar'] ?? '',
            'avatar_url' => $user['avatar_url'] ?? null
        ]
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Bad request']);
