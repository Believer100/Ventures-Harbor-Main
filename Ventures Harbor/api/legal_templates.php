<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
    requireAuth();
    $templates = $mysqli->query("SELECT id, title, description, file_name, file_path, created_at FROM legal_templates ORDER BY id ASC")->fetch_all(MYSQLI_ASSOC);
    foreach ($templates as &$t) {
        $t['id'] = (string)$t['id'];
    }
    unset($t);

    echo json_encode(['success' => true, 'templates' => $templates]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawAction = $_POST['action'] ?? '';

    if ($rawAction === 'upload') {
        $userId = requireAdmin();
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (!$title) {
            echo json_encode(['success' => false, 'message' => 'Template title is required.']);
            exit;
        }

        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'No valid file was received.']);
            exit;
        }

        $file = $_FILES['file'];
        $allowedExt = ['pdf', 'doc', 'docx', 'html'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExt, true)) {
            echo json_encode(['success' => false, 'message' => 'Only PDF, DOC, DOCX, or HTML files are allowed.']);
            exit;
        }
        if ($file['size'] > 10 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'File must be smaller than 10MB.']);
            exit;
        }

        $uploadDir = __DIR__ . '/../uploads/legal_templates';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $safeName = preg_replace('/[^A-Za-z0-9_\-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
        $newFileName = $safeName . '_' . time() . '.' . $ext;
        $destPath = $uploadDir . '/' . $newFileName;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            echo json_encode(['success' => false, 'message' => 'Failed to save the uploaded file.']);
            exit;
        }

        $relativePath = 'uploads/legal_templates/' . $newFileName;

        $stmt = $mysqli->prepare("INSERT INTO legal_templates (title, description, file_name, file_path, uploaded_by) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssi", $title, $description, $file['name'], $relativePath, $userId);
        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'message' => 'Failed to save template record.']);
            exit;
        }
        $stmt->close();

        echo json_encode(['success' => true, 'message' => 'Template uploaded successfully.']);
        exit;
    }

    if ($rawAction === 'delete') {
        requireAdmin();
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Template ID is required.']);
            exit;
        }

        $stmt = $mysqli->prepare("SELECT file_path FROM legal_templates WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $tpl = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($tpl) {
            $fullPath = __DIR__ . '/../' . $tpl['file_path'];
            if (is_file($fullPath)) {
                @unlink($fullPath);
            }
        }

        $stmt = $mysqli->prepare("DELETE FROM legal_templates WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => true, 'message' => 'Template deleted.']);
        exit;
    }
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Bad request']);
